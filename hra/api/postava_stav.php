<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_predmety.php';
require_once __DIR__ . '/../../includes/vtt_kontejnery.php';
require_once __DIR__ . '/../../includes/vtt_zbrane_u_sebe.php';

// Rychlé zápisy z Osobního deníku (hra/denik.php): životy, magenergie,
// únava, zkušenost, peníze, co postava nosí/drží, osobní texty. Na rozdíl
// od hra/api/hp_uprava.php a magenergie_uprava.php se tu adresuje přímo
// postava (deník nemusí být otevřený z mapy) — změny života a magenergie
// se ale zapisují do logu světa stejně jako tam, ať se na mapě projeví.
//
// Vstup JSON: {postava_id, akce, ...}. Akce:
//   hp {delta} · magenergie {delta} · unava {delta} · spanek {} · zkusenost {delta}
//   penize {mena: zl|st|md, hodnota} · vyber {pole, hodnota} · zbran_u_sebe {polozka_id, hodnota 0|1} · text {pole, hodnota}
//   velikost {hodnota} · pohyblivost_rucne {hodnota|null}
//   inventar_pridat {typ_polozky: predmet|lektvar|kouzlo, polozka_id, mnozstvi}
//   inventar_zmena {typ_polozky: predmet|lektvar, polozka_id, delta} · inventar_odebrat {typ_polozky, polozka_id}
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

function odpoved(int $kod, array $data): void
{
    http_response_code($kod);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$postavaId = (int)($data['postava_id'] ?? 0);
$akce = (string)($data['akce'] ?? '');

$pdo = dracak_db();
$postava = dracak_vtt_entity_row('postava', $postavaId);
if (!$postava) {
    odpoved(404, ['error' => 'Postava nenalezena.']);
}
if (!dracak_vtt_can_edit_hp($user, 'postava', $postava)) {
    odpoved(403, ['error' => 'Tuhle postavu upravovat nesmíš.']);
}
$svetId = (int)($postava['svet_id'] ?? 0);
$jePj = $user['role'] === 'admin' || ($svetId > 0 && dracak_vtt_je_pj_sveta($user, $svetId));

// Sloupec, který existuje až po ruční migraci — bez něj je pole "nezavedené".
function pole_existuje(array $postava, string $pole): bool
{
    return array_key_exists($pole, $postava);
}

$delta = (int)($data['delta'] ?? 0);
$aktivniMapaId = null;
if ($svetId > 0) {
    $stmt = $pdo->prepare('SELECT aktivni_mapa_id FROM svet WHERE id = ?');
    $stmt->execute([$svetId]);
    $v = $stmt->fetchColumn();
    $aktivniMapaId = $v !== false && $v !== null ? (int)$v : null;
}

switch ($akce) {
    case 'hp': {
        $max = (int)$postava['max_hp'];
        $nove = max(0, (int)$postava['aktualni_hp'] + $delta);
        if ($max > 0) {
            $nove = min($max, $nove);
        }
        $pdo->prepare('UPDATE postavy SET aktualni_hp = ? WHERE id = ?')->execute([$nove, $postavaId]);
        if ($svetId > 0) {
            dracak_vtt_log_event($svetId, $aktivniMapaId, 'hp_zmena', [
                'typ_entity' => 'postava', 'entita_id' => $postavaId, 'nove_hp' => $nove, 'max_hp' => $max,
            ], (int)$user['id']);
        }
        odpoved(200, ['ok' => true]);
    }
    case 'magenergie': {
        if (!dracak_vtt_ma_magenergii($postava)) {
            odpoved(422, ['error' => 'Tahle postava magenergii nepoužívá.']);
        }
        $max = (int)$postava['max_magenergie'];
        $nova = max(0, min($max, (int)$postava['aktualni_magenergie'] + $delta));
        $pdo->prepare('UPDATE postavy SET aktualni_magenergie = ? WHERE id = ?')->execute([$nova, $postavaId]);
        if ($svetId > 0) {
            dracak_vtt_log_event($svetId, $aktivniMapaId, 'magenergie_zmena', [
                'typ_entity' => 'postava', 'entita_id' => $postavaId, 'nova_magenergie' => $nova, 'max_magenergie' => $max,
            ], (int)$user['id']);
        }
        odpoved(200, ['ok' => true]);
    }
    case 'unava': {
        if (!pole_existuje($postava, 'unava')) {
            odpoved(422, ['error' => 'Únava se zatím nedá ukládat (chybí migrace 0065).']);
        }
        $nova = max(0, min(65535, (int)$postava['unava'] + $delta));
        $pdo->prepare('UPDATE postavy SET unava = ? WHERE id = ?')->execute([$nova, $postavaId]);
        odpoved(200, ['ok' => true]);
    }
    case 'spanek': {
        // Prospaná doba "ke snížení o stupeň únavy" (h1634 b10872): zmizí tolik
        // bodů únavy, kolik je odolnost. Hodiny počítá dracak_vtt_spanek().
        if (!pole_existuje($postava, 'unava')) {
            odpoved(422, ['error' => 'Únava se zatím nedá ukládat (chybí migrace 0065).']);
        }
        require_once __DIR__ . '/../../includes/vtt_pohyblivost.php';
        $spanek = dracak_vtt_spanek($pdo, $postava);
        if (!$spanek) {
            odpoved(422, ['error' => 'Doplň odolnost postavy (vytrvalost = odolnost).']);
        }
        $nova = max(0, (int)$postava['unava'] - $spanek['bodu']);
        $pdo->prepare('UPDATE postavy SET unava = ? WHERE id = ?')->execute([$nova, $postavaId]);
        odpoved(200, ['ok' => true, 'hodin' => $spanek['hodin'], 'unava' => $nova]);
    }
    case 'zkusenost': {
        if (!pole_existuje($postava, 'zkusenost')) {
            odpoved(422, ['error' => 'Zkušenost se zatím nedá ukládat (chybí migrace 0070).']);
        }
        // Zkušenost přiděluje PJ (h109); postava bez světa nemá PJ, tam smí
        // vlastník.
        if (!$jePj && $svetId > 0) {
            odpoved(403, ['error' => 'Zkušenost přiděluje PJ.']);
        }
        $nova = max(0, min(4294967295, (int)$postava['zkusenost'] + $delta));
        $pdo->prepare('UPDATE postavy SET zkusenost = ? WHERE id = ?')->execute([$nova, $postavaId]);
        odpoved(200, ['ok' => true]);
    }
    case 'penize': {
        $mena = (string)($data['mena'] ?? '');
        if (!in_array($mena, ['zl', 'st', 'md'], true) || !pole_existuje($postava, 'penize_' . $mena)) {
            odpoved(422, ['error' => 'Peníze se zatím nedají ukládat (chybí migrace 0070).']);
        }
        $hodnota = max(0, min(4294967295, (int)($data['hodnota'] ?? 0)));
        $pdo->prepare("UPDATE postavy SET penize_$mena = ? WHERE id = ?")->execute([$hodnota, $postavaId]);
        odpoved(200, ['ok' => true]);
    }
    case 'vyber': {
        $pole = (string)($data['pole'] ?? '');
        if (!in_array($pole, ['zbroj_id', 'stit_id', 'drzena_zbran_id'], true) || !pole_existuje($postava, $pole)) {
            odpoved(422, ['error' => 'Tohle pole se zatím nedá ukládat (chybí migrace 0070).']);
        }
        $predmetId = (int)($data['hodnota'] ?? 0);
        // Zbraň v ruce se v boji mění akcí „vytasit“ (hra/api/zbran_vytasit.php) — tady jen mimo boj nebo jako PJ.
        if ($pole === 'drzena_zbran_id' && !$jePj && dracak_vtt_zbrane_boj_kontext($pdo, $postava) !== null) {
            odpoved(422, ['error' => 'V boji se zbraň mění vytasením — stojí akci.']);
        }
        // Zbraň v ruce jde přes společnou funkci jako vytasení — odložená zbraň se zasune k tělu (u_sebe) a platí stejné kontroly.
        if ($pole === 'drzena_zbran_id') {
            if ($predmetId > 0 && (int)($postava['drzena_zbran_id'] ?? 0) === $predmetId) {
                odpoved(200, ['ok' => true]);   // už ji drží
            }
            try {
                dracak_vtt_zbrane_vytasit($pdo, $postava, $predmetId, false);
            } catch (RuntimeException $ex) {
                odpoved(422, ['error' => $ex->getMessage()]);
            }
            odpoved(200, ['ok' => true]);
        }
        if ($predmetId > 0) {
            // Jen předmět, který postava má v inventáři, a správného typu.
            $typ = $pole === 'drzena_zbran_id' ? 'zbran' : 'zbroj';
            $stmt = $pdo->prepare(
                'SELECT 1 FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
                 WHERE pp.postava_id = ? AND pp.predmet_id = ? AND p.typ = ?'
            );
            $stmt->execute([$postavaId, $predmetId, $typ]);
            if (!$stmt->fetchColumn()) {
                odpoved(422, ['error' => 'Tenhle předmět postava nemá, nebo je jiného typu.']);
            }
        }
        $pdo->prepare("UPDATE postavy SET $pole = ? WHERE id = ?")->execute([$predmetId > 0 ? $predmetId : null, $postavaId]);
        odpoved(200, ['ok' => true]);
    }
    case 'zbran_u_sebe': {
        // Zbraň při těle (opasek, pochva, závěs, toulec) — v boji ji lze vytasit. V boji se výbava nepřebaluje (jen PJ).
        if (!dracak_vtt_zbrane_u_sebe_funkce($pdo)) {
            odpoved(422, ['error' => 'Zbraně u sebe se zatím nedají ukládat (chybí ruční SQL, docs/sql-k-rucnimu-spusteni.sql).']);
        }
        if (!$jePj && dracak_vtt_zbrane_boj_kontext($pdo, $postava) !== null) {
            odpoved(422, ['error' => 'V boji se výbava nepřebaluje — zbraň si dej k tělu mimo boj.']);
        }
        $predmetId = (int)($data['polozka_id'] ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
             SET pp.u_sebe = ? WHERE pp.postava_id = ? AND pp.predmet_id = ? AND p.typ = "zbran"'
        );
        $stmt->execute([!empty($data['hodnota']) ? 1 : 0, $postavaId, $predmetId]);
        $stmt = $pdo->prepare('SELECT 1 FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id WHERE pp.postava_id = ? AND pp.predmet_id = ? AND p.typ = "zbran"');
        $stmt->execute([$postavaId, $predmetId]);
        if (!$stmt->fetchColumn()) {
            odpoved(422, ['error' => 'Tuhle zbraň postava nemá.']);
        }
        odpoved(200, ['ok' => true]);
    }
    case 'text': {
        $pole = (string)($data['pole'] ?? '');
        if (!in_array($pole, ['puvod', 'osobnost', 'poznamky'], true) || !pole_existuje($postava, $pole)) {
            odpoved(422, ['error' => 'Tohle pole se zatím nedá ukládat (chybí migrace 0070).']);
        }
        $text = trim((string)($data['hodnota'] ?? ''));
        $pdo->prepare("UPDATE postavy SET $pole = ? WHERE id = ?")->execute([$text !== '' ? mb_substr($text, 0, 20000) : null, $postavaId]);
        odpoved(200, ['ok' => true]);
    }
    case 'velikost': {
        if (!pole_existuje($postava, 'velikost')) {
            odpoved(422, ['error' => 'Velikost se zatím nedá ukládat (chybí migrace 0070).']);
        }
        $v = (string)($data['hodnota'] ?? '');
        if ($v !== '' && !in_array($v, ['A0', 'A', 'B', 'C', 'D', 'E'], true)) {
            odpoved(422, ['error' => 'Neplatná velikost.']);
        }
        $pdo->prepare('UPDATE postavy SET velikost = ? WHERE id = ?')->execute([$v !== '' ? $v : null, $postavaId]);
        odpoved(200, ['ok' => true]);
    }
    case 'pohyblivost_rucne': {
        // Ručně zadaná (okamžitá) pohyblivost — platí jen ve světě s vypnutým
        // svet.auto_pohyblivost (Fáze 5, migrace 0077/0078). Prázdná hodnota = zrušit.
        if (!pole_existuje($postava, 'pohyblivost_rucne')) {
            odpoved(422, ['error' => 'Ruční pohyblivost se zatím nedá ukládat (chybí migrace 0078).']);
        }
        $v = $data['hodnota'] ?? null;
        $hodnota = ($v === null || $v === '') ? null : (int)$v;
        if ($hodnota !== null && ($hodnota < 1 || $hodnota > 60)) {
            odpoved(422, ['error' => 'Pohyblivost zadej v rozsahu 1–60.']);
        }
        $pdo->prepare('UPDATE postavy SET pohyblivost_rucne = ? WHERE id = ?')->execute([$hodnota, $postavaId]);
        odpoved(200, ['ok' => true]);
    }
    case 'inventar_pridat':
    case 'inventar_zmena':
    case 'inventar_odebrat': {
        // Správa inventáře postavy: předměty a lektvary po kusech, kouzla
        // (znalost) jen přidat/odebrat. Vlastník i PJ/admin (stejné právo
        // jako u životů); PJ-only přidávání z katalogu ve svet.php zůstává.
        $typ = (string)($data['typ_polozky'] ?? '');
        $cfg = dracak_vtt_polozka_config($typ);
        if (!$cfg) {
            odpoved(422, ['error' => 'Neplatný typ položky.']);
        }
        $polozkaId = (int)($data['polozka_id'] ?? 0);
        if (!dracak_vtt_polozka_katalog($typ, $polozkaId)) {
            odpoved(404, ['error' => 'Položka v katalogu neexistuje.']);
        }
        $tabulka = $cfg['inventar'];
        $fk = $cfg['fk'];
        if ($akce === 'inventar_pridat') {
            if ($typ === 'kouzlo') {
                $pdo->prepare("INSERT IGNORE INTO $tabulka (postava_id, $fk) VALUES (?, ?)")->execute([$postavaId, $polozkaId]);
            } else {
                $mnozstvi = max(1, min(1000, (int)($data['mnozstvi'] ?? 1)));
                // Předmět s kapacitou (vak, truhla…) je kontejner: každý kus je
                // vlastní fyzický kontejner (vlastní obsah), ne řádek s počtem.
                if ($typ === 'predmet' && dracak_vtt_kontejnery_dostupne($pdo)) {
                    $kontejnerPrvni = dracak_vtt_kontejner_pridat($pdo, $postavaId, $polozkaId);
                    if ($kontejnerPrvni !== null) {
                        for ($i = 1; $i < min($mnozstvi, 20); $i++) {
                            dracak_vtt_kontejner_pridat($pdo, $postavaId, $polozkaId);
                        }
                        odpoved(200, ['ok' => true, 'kontejner' => true]);
                    }
                }
                $pdo->prepare(
                    "INSERT INTO $tabulka (postava_id, $fk, mnozstvi) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE mnozstvi = LEAST(65000, mnozstvi + VALUES(mnozstvi))"
                )->execute([$postavaId, $polozkaId, $mnozstvi]);
            }
            odpoved(200, ['ok' => true]);
        }
        $odebrat = $akce === 'inventar_odebrat';
        if (!$odebrat) {
            if ($typ === 'kouzlo') {
                odpoved(422, ['error' => 'Kouzlo nemá množství.']);
            }
            $stmt = $pdo->prepare("SELECT mnozstvi FROM $tabulka WHERE postava_id = ? AND $fk = ?");
            $stmt->execute([$postavaId, $polozkaId]);
            $kusu = $stmt->fetchColumn();
            if ($kusu === false) {
                odpoved(404, ['error' => 'Tuhle položku postava nemá.']);
            }
            $nove = (int)$kusu + $delta;
            if ($nove < 1) {
                $odebrat = true;
            } else {
                $pdo->prepare("UPDATE $tabulka SET mnozstvi = ? WHERE postava_id = ? AND $fk = ?")->execute([min(65000, $nove), $postavaId, $polozkaId]);
                odpoved(200, ['ok' => true]);
            }
        }
        $pdo->prepare("DELETE FROM $tabulka WHERE postava_id = ? AND $fk = ?")->execute([$postavaId, $polozkaId]);
        if ($typ === 'predmet') {
            // Odebraný předmět se už nemůže nosit ani držet.
            foreach (['zbroj_id', 'stit_id', 'drzena_zbran_id'] as $sloupec) {
                if (pole_existuje($postava, $sloupec)) {
                    $pdo->prepare("UPDATE postavy SET $sloupec = NULL WHERE id = ? AND $sloupec = ?")->execute([$postavaId, $polozkaId]);
                }
            }
        }
        odpoved(200, ['ok' => true]);
    }
    default:
        odpoved(422, ['error' => 'Neznámá akce.']);
}
