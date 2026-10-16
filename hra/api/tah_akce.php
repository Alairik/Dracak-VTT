<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj_pohyb.php';
require_once __DIR__ . '/../../includes/vtt_tokeny.php';

// Tvor na tahu odehrál akci — ubude mu z "akce zbývající" a (na hexové mapě)
// provede se její mapová část: krok na sousední hex, otočka, útok na tvora
// před sebou (pravidla viz includes/vtt_souboj_pohyb.php). Vyčerpá-li kvótu
// tahu (větší půlka akcí v 1. půlce kola, zbytek ve 2.), tah jde dál.
// Smí postava na tahu (její hráč) nebo PJ. Samotný útok (hod, zranění) přijde
// v dalším kroku; teď se jen zapíše, na koho se útočí.
// Tělo: {mapa_id, token_id, druh, cil?: {col,row}, smer?: stupně, cil_token_id?}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId);
$pdo = dracak_db();
$tokenId = (int)($data['token_id'] ?? 0);
$druh = (string)($data['druh'] ?? 'jina');
if (!isset(DRACAK_VTT_AKCE_DRUHY[$druh])) {
    $druh = 'jina';
}
$pocet = max(1, min(9, (int)($data['pocet'] ?? 1)));

$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
if (!isset($info[$tokenId]) || !dracak_vtt_souboj_ovlada($user, $jePj, $info[$tokenId])) {
    dracak_vtt_souboj_chyba(403, 'Za tenhle token jednat nesmíš.');
}

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null || $stav['faze'] !== 'kolo') {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Teď neběží tahy.');
    }
    if ($stav['aktivni_token_id'] !== $tokenId) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Tenhle tvor teď není na tahu.');
    }
    $q = $pdo->prepare('SELECT akce_celkem, akce_zbyvajici FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ? FOR UPDATE');
    $q->execute([$mapaId, $tokenId]);
    $ki = $q->fetch();
    $kvota = $ki ? dracak_vtt_souboj_kvota((int)$ki['akce_celkem'], (int)$ki['akce_zbyvajici'], $stav['polovina']) : 0;
    if ($pocet > $kvota) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'V tomhle tahu smíš odehrát už jen ' . $kvota . ' ' . ($kvota === 1 ? 'akci' : 'akcí') . '.');
    }

    // Mapová část akce — pravidla pohybu, čela a dosahu; při porušení skončí chybou.
    $q = $pdo->prepare('SELECT * FROM tokeny WHERE id = ? AND mapa_id = ?');
    $q->execute([$tokenId, $mapaId]);
    $token = $q->fetch();
    $q = $pdo->prepare('SELECT umysl FROM kolo_ucastnik WHERE mapa_id = ? AND token_id = ?');
    $q->execute([$mapaId, $tokenId]);
    $umysl = $q->fetchColumn();
    // Únava: unavená postava nesmí sprintovat, vyčerpaná ani běhat (hráče to zastaví, PJ smí rozhodnout jinak);
    // sprint navíc stojí bod únavy za každé použití (HB, souboj_pohyb.unava_bodu).
    $rezimPohybu = DRACAK_VTT_AKCE_REZIM_POHYBU[$druh] ?? null;
    $unavaPlus = 0;
    $unavaNova = null;
    $postavaRadek = null;
    if ($rezimPohybu !== null && $info[$tokenId]['typ_entity'] === 'postava') {
        $q = $pdo->prepare('SELECT * FROM postavy WHERE id = ? FOR UPDATE');
        $q->execute([(int)$info[$tokenId]['entita_id']]);
        $postavaRadek = $q->fetch() ?: null;
        $stupen = $postavaRadek ? dracak_vtt_postava_unava_stupen($postavaRadek) : null;
        if ($stupen !== null && !$jePj && in_array($rezimPohybu, $stupen['zakazy'], true)) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Postava je ' . $stupen['nazev'] . ' — ' . ($rezimPohybu === 'sprint' ? 'sprintovat' : 'běhat') . ' nesmí.');
        }
    }
    $pohyb = dracak_vtt_souboj_pohyb($pdo, $mapa, $token, $info, $umysl !== false ? $umysl : null, $druh, $data, $jePj);
    if ($rezimPohybu !== null && $postavaRadek !== null && array_key_exists('unava', $postavaRadek)) {
        $bodu = (int)(dracak_vtt_souboj_pravidla()['pohyb'][$rezimPohybu]['unava_bodu'] ?? 0) * $pocet;
        if ($bodu > 0) {
            $unavaNova = min(65535, (int)$postavaRadek['unava'] + $bodu);
            $pdo->prepare('UPDATE postavy SET unava = ? WHERE id = ?')->execute([$unavaNova, (int)$postavaRadek['id']]);
            $unavaPlus = $bodu;
        }
    }

    $pohnul = false;
    if ($pohyb['mapove']) {
        $maSmer = dracak_vtt_tokeny_ma_sloupec('smer');
        if ($pohyb['x'] !== (int)$token['x'] || $pohyb['y'] !== (int)$token['y']) {
            $pdo->prepare('UPDATE tokeny SET x = ?, y = ? WHERE id = ?')->execute([$pohyb['x'], $pohyb['y'], $tokenId]);
            dracak_vtt_log_event($svetId, $mapaId, 'token_presun', [
                'token_id' => $tokenId,
                'x_pred' => (int)$token['x'],
                'y_pred' => (int)$token['y'],
                'x_po' => $pohyb['x'],
                'y_po' => $pohyb['y'],
            ], (int)$user['id']);
            $pohnul = true;
        }
        if ($maSmer && (int)($token['smer'] ?? 0) !== $pohyb['smer']) {
            $pdo->prepare('UPDATE tokeny SET smer = ? WHERE id = ?')->execute([$pohyb['smer'], $tokenId]);
            dracak_vtt_log_event($svetId, $mapaId, 'token_zmena', [
                'token_id' => $tokenId,
                'zmeny' => ['smer' => $pohyb['smer']],
                'pred' => ['smer' => (int)($token['smer'] ?? 0)],
            ], (int)$user['id']);
        }
    }

    $pdo->prepare('UPDATE kolo_iniciativa SET akce_zbyvajici = akce_zbyvajici - ? WHERE mapa_id = ? AND token_id = ?')
        ->execute([$pocet, $mapaId, $tokenId]);
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'akce_provedena', [
        'token_id' => $tokenId,
        'label' => $info[$tokenId]['label'],
        'druh' => $druh,
        'druh_nazev' => DRACAK_VTT_AKCE_DRUHY[$druh],
        'pocet' => $pocet,
        'akce_zbyvajici' => (int)$ki['akce_zbyvajici'] - $pocet,
        'cil_token_id' => $pohyb['mapove'] ? $pohyb['cil_token_id'] : null,
        'cil_label' => $pohyb['mapove'] ? $pohyb['cil_label'] : null,
        'unava_plus' => $unavaPlus,
        'unava_nova' => $unavaNova,
        'skryta' => !dracak_vtt_souboj_vidi_hrac($info[$tokenId]),
    ], (int)$user['id']);
    $posun = dracak_vtt_souboj_posun($pdo, $svetId, $mapaId, (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

// Hráčova mlha se odhaluje kolem jeho postavy, která se pohnula.
if (!empty($pohnul)) {
    dracak_vtt_token_odhal_mlhu($pdo, array_merge($token, [
        'sirka_px' => $mapa['sirka_px'], 'vyska_px' => $mapa['vyska_px'], 'grid_velikost_px' => $mapa['grid_velikost_px'],
        'grid_typ' => $mapa['grid_typ'], 'grid_posun_x' => $mapa['grid_posun_x'], 'grid_posun_y' => $mapa['grid_posun_y'],
    ]), $pohyb['x'], $pohyb['y']);
}
echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'posun' => $posun], JSON_UNESCAPED_UNICODE);
