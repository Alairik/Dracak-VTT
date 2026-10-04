<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

// Krok zpět (V1 rozsah, viz docs/vtt-datovy-model-navrh-v1.md, sekce
// "Mechanismy" → "Krok zpět"): vezme JEDNU poslední událost v
// svet_udalosti pro danou mapu a — jen pokud nese dost dat, aby šla
// vrátit BEZPEČNĚ, ne odhadem — vrátí stav a zapíše NOVOU událost
// 'krok_zpet' dokumentující revert. svet_udalosti zůstává append-only:
// starý řádek se tu nikdy needituje ani nemaže, jen se k němu přidá
// další INSERT.
//
// Bezpečně vratné typy (a proč):
//  - token_presun: payload nese x_pred/y_pred přímo — doslova "hodnota
//    před", přesně podle návrhu.
//  - token_pridan, zed_pridana, efekt_aplikovan: vznik řádku. "Před" je
//    triviálně "řádek neexistoval" a payload nese přesné id toho, co
//    vzniklo (token_id / id zdi / aktivni_efekt_id) — vrácení je tedy
//    smazání přesně toho řádku, žádné hádání dat.
//
// Vědomě NEřešené (payload nenese dost, nebo je událost multi-tabulková
// bez úplného "před" snapshotu — radši zamítnout než hádat):
//  - hp_zmena: nese jen nove_hp/max_hp, žádné "před" (šlo by dopočítat z
//    PŘEDCHOZÍ hp_zmena stejné entity, ale úplně první změna života na
//    mapě nemá na co navázat — vynecháno, ne odhadováno).
//  - token_smazan / zed_smazana: payload nese jen id smazaného řádku,
//    ne jeho data (x/y, typ_entity/entita_id, x1..y2, blokuje_*…) — po
//    smazání už není z čeho obnovit.
//  - efekt_konci: payload nese jen čitelný název efektu (efekt_nazev),
//    ne efekt_id/aktivni_efekt_id/zbyva_kol — nejde znovu vytvořit
//    přesně ten samý aktivní efekt.
//  - kolo_nove / tah_zmena (dalsi_tah.php): kolo_nove navíc maže celou
//    kolo_iniciativa pro mapu (žádný snapshot v payloadu); tah_zmena
//    před sebou odečítá akce_zbyvajici předchozímu aktivnímu tokenu, ale
//    payload nenese PŮVODNÍ počet akcí — slepé "+1" by u GREATEST(0, …)
//    ořezu mohlo navrátit víc, než kolik PŘED tahem skutečně bylo.
//  - iniciativa_hozena: upsert (ON DUPLICATE KEY UPDATE) — u rerollu
//    přepíše předchozí hod/modifikátor/akce, ale payload nenese, jaké
//    hodnoty tam byly PŘED přepsáním (ani nerozlišuje první hod od
//    rerollu).
//  - predmet_pouzit / predmet_loot: jedna událost kombinuje spotřebu
//    položky (bez zápisu původního zbývajícího množství/existence
//    řádku), případně HP deltu a vznik efektů — částečné vrácení jen
//    HP/efektů by nekonzistentně nechalo spotřebu položky beze změny.
//  - mapa_grid_zmena: payload nese jen NOVÉ hodnoty gridu, ne předchozí.
//  - kostka_hod / ping: nic nemění ve stavových tabulkách (jen log),
//    vrácení nedává smysl.
//  - krok_zpet samotný: v1 je "jeden krok zpět", ne obecný redo
//    zásobník — zřetězené vracení vlastních kroků zpět je mimo rozsah.
//
// PJ/admin only — stejná kontrola role jako dalsi_tah.php/kolo_konec.php.

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
$svetId = (int)$mapa['svet_id'];
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může vrátit krok zpět.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM svet_udalosti WHERE mapa_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$mapaId]);
$posledni = $stmt->fetch();
if (!$posledni) {
    http_response_code(422);
    echo json_encode(['error' => 'Na téhle mapě ještě není žádná událost k vrácení.']);
    exit;
}

$typ = (string)$posledni['typ'];
$udalostId = (int)$posledni['id'];
$payload = json_decode((string)$posledni['payload'], true) ?: [];
$reverzniTypyChyba = 'Poslední událost na téhle mapě (' . $typ . ') nejde bezpečně vrátit zpět.';

// Validace + načtení dotčeného řádku PŘED zahájením transakce — ať
// případná chyba (404/422) neskončí s otevřenou transakcí.
$token = null;
$zed = null;
$aktivniEfekt = null;

switch ($typ) {
    case 'token_presun':
    case 'token_pridan':
        $tokenId = (int)($payload['token_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM tokeny WHERE id = ? AND mapa_id = ?');
        $stmt->execute([$tokenId, $mapaId]);
        $token = $stmt->fetch();
        if (!$token) {
            http_response_code(422);
            echo json_encode(['error' => 'Token z poslední události už na mapě není, nejde vrátit.']);
            exit;
        }
        break;

    case 'zed_pridana':
        $zedId = (int)($payload['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM zdi WHERE id = ? AND mapa_id = ?');
        $stmt->execute([$zedId, $mapaId]);
        $zed = $stmt->fetch();
        if (!$zed) {
            http_response_code(422);
            echo json_encode(['error' => 'Zeď z poslední události už na mapě není, nejde vrátit.']);
            exit;
        }
        break;

    case 'efekt_aplikovan':
        $aktivniId = (int)($payload['aktivni_efekt_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM aktivni_efekty WHERE id = ?');
        $stmt->execute([$aktivniId]);
        $aktivniEfekt = $stmt->fetch();
        if (!$aktivniEfekt) {
            http_response_code(422);
            echo json_encode(['error' => 'Efekt z poslední události už neplatí (např. vypršel), nejde vrátit.']);
            exit;
        }
        break;

    default:
        http_response_code(422);
        echo json_encode(['error' => $reverzniTypyChyba]);
        exit;
}

$pdo->beginTransaction();
try {
    $revertPayload = ['vraceno_udalost_id' => $udalostId, 'vraceny_typ' => $typ];

    if ($typ === 'token_presun') {
        $xPred = (int)($payload['x_pred'] ?? 0);
        $yPred = (int)($payload['y_pred'] ?? 0);
        $pdo->prepare('UPDATE tokeny SET x = ?, y = ? WHERE id = ?')->execute([$xPred, $yPred, (int)$token['id']]);
        $revertPayload += [
            'token_id' => (int)$token['id'],
            'x_pred' => (int)$token['x'], // stav před krokem zpět (= x_po původní události)
            'y_pred' => (int)$token['y'],
            'x_po' => $xPred, // stav po kroku zpět (= x_pred původní události)
            'y_po' => $yPred,
        ];
    } elseif ($typ === 'token_pridan') {
        $pdo->prepare('DELETE FROM tokeny WHERE id = ?')->execute([(int)$token['id']]);
        $revertPayload += ['token_id' => (int)$token['id'], 'nazev' => $payload['nazev'] ?? null];
    } elseif ($typ === 'zed_pridana') {
        $pdo->prepare('DELETE FROM zdi WHERE id = ?')->execute([(int)$zed['id']]);
        $revertPayload += ['id' => (int)$zed['id'], 'viditelna_hracum' => (bool)$zed['viditelna_hracum']];
    } elseif ($typ === 'efekt_aplikovan') {
        $pdo->prepare('DELETE FROM aktivni_efekty WHERE id = ?')->execute([(int)$aktivniEfekt['id']]);
        $revertPayload += [
            'typ_entity' => $aktivniEfekt['typ_entity'],
            'entita_id' => (int)$aktivniEfekt['entita_id'],
            'aktivni_efekt_id' => (int)$aktivniEfekt['id'],
            'efekt_nazev' => $payload['efekt_nazev'] ?? null,
        ];
    }

    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'krok_zpet', $revertPayload, (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'vraceny_typ' => $typ], JSON_UNESCAPED_UNICODE);
