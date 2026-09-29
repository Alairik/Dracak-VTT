<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_predmety.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$typEntity = ($data['typ_entity'] ?? '') === 'nestvura_instance' ? 'nestvura_instance' : 'postava';
$entitaId = (int)($data['entita_id'] ?? 0);
$typPolozky = (string)($data['typ_polozky'] ?? '');
$polozkaId = (int)($data['polozka_id'] ?? 0);
// Cíl je nepovinný — bez zadání efekt/kostka dopadá na entitu, co položku
// použila (typický "vypij lektvar na sebe"). PJ/hráč ho zadá, když je cíl
// jiný token (hoď lektvar po nestvůře, sešli kouzlo na spoluhráče...).
$cilTypEntity = ($data['cil_typ_entity'] ?? '') === 'nestvura_instance' ? 'nestvura_instance'
    : (($data['cil_typ_entity'] ?? '') === 'postava' ? 'postava' : $typEntity);
$cilEntitaId = !empty($data['cil_entita_id']) ? (int)$data['cil_entita_id'] : $entitaId;
$pozadovanaMapaId = !empty($data['mapa_id']) ? (int)$data['mapa_id'] : null;

$cfg = dracak_vtt_polozka_config($typPolozky);
if (!$cfg) {
    http_response_code(422);
    echo json_encode(['error' => 'Neplatný typ položky.']);
    exit;
}

$pdo = dracak_db();

$entity = dracak_vtt_entity_row($typEntity, $entitaId);
if (!$entity) {
    http_response_code(404);
    echo json_encode(['error' => 'Entita nenalezena.']);
    exit;
}
$svetMapa = dracak_vtt_entity_svet_mapa($typEntity, $entity);
if ($svetMapa['svet_id'] === 0 || !dracak_vtt_svet_access($user, $svetMapa['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Bez přístupu.']);
    exit;
}
// Stejné pravidlo jako u úpravy života (dracak_vtt_can_edit_hp): pj/admin
// smí použít cokoliv, hráč jen inventář vlastní postavy — instance
// nestvůry je vždy v gesci PJ.
if (!dracak_vtt_can_edit_hp($user, $typEntity, $entity)) {
    http_response_code(403);
    echo json_encode(['error' => 'Tenhle předmět použít nesmíš.']);
    exit;
}

$cilEntity = dracak_vtt_entity_row($cilTypEntity, $cilEntitaId);
if (!$cilEntity) {
    http_response_code(404);
    echo json_encode(['error' => 'Cíl nenalezen.']);
    exit;
}
$cilSvetMapa = dracak_vtt_entity_svet_mapa($cilTypEntity, $cilEntity);
if ($cilSvetMapa['svet_id'] !== $svetMapa['svet_id']) {
    http_response_code(404);
    echo json_encode(['error' => 'Cíl není ve stejném světě.']);
    exit;
}

// Ověření, že entita položku fakticky má (nevěřit klientovi) — postava má
// typované tabulky, nestvura_instance jednu polymorfní výbavu.
if ($typEntity === 'postava') {
    $stmt = $pdo->prepare("SELECT * FROM {$cfg['inventar']} WHERE postava_id = ? AND {$cfg['fk']} = ?");
    $stmt->execute([$entitaId, $polozkaId]);
} else {
    $stmt = $pdo->prepare(
        'SELECT * FROM nestvura_instance_vybava WHERE nestvura_instance_id = ? AND typ_polozky = ? AND polozka_id = ? ORDER BY id LIMIT 1'
    );
    $stmt->execute([$entitaId, $typPolozky, $polozkaId]);
}
$invRow = $stmt->fetch();
// postava_zna_kouzlo nemá mnozstvi (znalost kouzla se nepočítá na kusy) —
// existence řádku stačí. U předmětu/lektvaru navíc ověř, že kus fakticky
// zbývá (obrana proti mnozstvi=0, který by neměl nastat, ale nevěřit datům).
if (!$invRow || ($typPolozky !== 'kouzlo' && (int)$invRow['mnozstvi'] < 1)) {
    http_response_code(404);
    echo json_encode(['error' => 'Tuhle položku entita nemá.']);
    exit;
}

$katalog = dracak_vtt_polozka_katalog($typPolozky, $polozkaId);
if (!$katalog) {
    http_response_code(404);
    echo json_encode(['error' => 'Položka nenalezena v katalogu.']);
    exit;
}

$pdo->beginTransaction();
try {
    // Spotřeba: jen lektvar (vypitý kus mizí). Předmět (zbraň/zbroj/
    // artefakt) a kouzlo (znalost) se použitím nespotřebovávají.
    if ($typPolozky === 'lektvar') {
        if ((int)$invRow['mnozstvi'] <= 1) {
            $del = $pdo->prepare(($typEntity === 'postava' ? "DELETE FROM {$cfg['inventar']}" : 'DELETE FROM nestvura_instance_vybava') . ' WHERE id = ?');
            $del->execute([(int)$invRow['id']]);
        } else {
            $upd = $pdo->prepare(($typEntity === 'postava' ? "UPDATE {$cfg['inventar']}" : 'UPDATE nestvura_instance_vybava') . ' SET mnozstvi = mnozstvi - 1 WHERE id = ?');
            $upd->execute([(int)$invRow['id']]);
        }
    }

    // Kostky "na jedno použití" bere engine VŽDY z vlastního záznamu
    // položky (pocet_kostek/typ_kostky/pevny_bonus), ne z efektu — viz
    // includes/vtt_predmety.php.
    $kostka = null;
    if (!empty($katalog['pocet_kostek']) && !empty($katalog['typ_kostky'])) {
        $typKostkyInt = dracak_vtt_typ_kostky_na_int($katalog['typ_kostky']);
        $kostka = dracak_vtt_hod_kostkou((int)$katalog['pocet_kostek'], $typKostkyInt, (int)($katalog['pevny_bonus'] ?? 0));
    }

    $efekty = dracak_vtt_polozka_efekty($typPolozky, $polozkaId);
    $hpVysledek = null;
    $aplikovaneEfekty = [];
    foreach ($efekty as $efekt) {
        $znamenko = dracak_vtt_hp_znamenko($efekt);
        if ($znamenko !== null) {
            // Poškození/Léčení: jednorázový přímý zásah do života, žádný
            // trvalý záznam v aktivni_efekty (mechanika_aplikace je
            // 'jednorazove', trvani 'okamžité' — není co odpočítávat).
            if ($kostka === null) {
                // Efekt existuje, ale položka nemá definované kostky — není
                // z čeho spočítat kolik životů strhnout/přidat. Nehádat,
                // efekt se přeskočí (viz CLAUDE.md o nehádání mechaniky).
                continue;
            }
            $delta = $znamenko * $kostka['celkem'];
            [$noveHp, $maxHp] = dracak_vtt_aplikuj_hp_deltu($cilTypEntity, $cilEntity, $delta);
            $cilEntity['aktualni_hp'] = $noveHp;
            $hpVysledek = ['delta' => $delta, 'nove_hp' => $noveHp, 'max_hp' => $maxHp];
            $aplikovaneEfekty[] = ['efekt_id' => (int)$efekt['id'], 'nazev' => $efekt['nazev'], 'hp_delta' => $delta];
        } else {
            // Běžný stavový efekt (buff/debuff/dot/hot/modifikator/imunita/
            // zranitelnost/odolnost jiný než Poškození/Léčení) — zapiš jako
            // aktivní efekt na cílovou entitu stejně jako efekt_pridat.php.
            // Trvání v kolech odsud neznáme (žádný vstup uživatele u "použij
            // předmět", efekty.trvani je jen volný text) — necháváme
            // zbyva_kol NULL (trvá, dokud ho PJ ručně nesundá).
            $ins = $pdo->prepare('INSERT INTO aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol) VALUES (?, ?, ?, NULL)');
            $ins->execute([$cilTypEntity, $cilEntitaId, (int)$efekt['id']]);
            $aplikovaneEfekty[] = [
                'efekt_id' => (int)$efekt['id'],
                'nazev' => $efekt['nazev'],
                'aktivni_efekt_id' => (int)$pdo->lastInsertId(),
                'zbyva_kol' => null,
            ];
        }
    }

    $logMapaId = $pozadovanaMapaId ?? $svetMapa['mapa_id'] ?? $cilSvetMapa['mapa_id'];
    $eventId = dracak_vtt_log_event($svetMapa['svet_id'], $logMapaId, 'predmet_pouzit', [
        'typ_entity' => $typEntity,
        'entita_id' => $entitaId,
        'typ_polozky' => $typPolozky,
        'polozka_id' => $polozkaId,
        'polozka_nazev' => $katalog['nazev'],
        'cil_typ_entity' => $cilTypEntity,
        'cil_entita_id' => $cilEntitaId,
        'kostka' => $kostka,
        'hp' => $hpVysledek,
        'efekty' => $aplikovaneEfekty,
        'pouzil' => $user['jmeno'],
    ], (int)$user['id']);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    dracak_fail_safely('Použití předmětu selhalo: ' . $e->getMessage());
}

echo json_encode([
    'ok' => true,
    'udalost_id' => $eventId,
    'polozka_nazev' => $katalog['nazev'],
    'kostka' => $kostka,
    'hp' => $hpVysledek,
    'efekty' => $aplikovaneEfekty,
], JSON_UNESCAPED_UNICODE);
