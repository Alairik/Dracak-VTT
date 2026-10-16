<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Hod k výzvě (includes/vtt_souboj_utok.php). HODY SE NEHÁZEJÍ AUTOMATICKY: hráč klikne na "Hodit",
// ukáže se 3D kostka a čísla, která ukáže, přijdou sem v "hody" (nebo hráč zapíše součet z vlastní
// kostky, "soucet" + "rucne": true). Server hlídá rozsah a řetěz šestek (1k6+), čísla ÚČ/OČ a opravy
// počítá sám.
//  - útok:  po hodu vznikne výzva "obrana" pro napadeného
//  - obrana: po hodu se vyhodnotí zásah, zranění a životy; tah útočníka pokračuje
// Hodit smí ten, kdo ovládá tvora, který hází, nebo PJ.
// Tělo: {mapa_id, vyzva_id, hody: [..] | soucet: n, rucne?}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId);
$pdo = dracak_db();
$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
$rucne = !empty($data['rucne']);

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    $v = dracak_vtt_vyzva_nacti($pdo, (int)($data['vyzva_id'] ?? 0), true);
    if (!$v || $v['mapa_id'] !== $mapaId) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(404, 'Výzva nenalezena.');
    }
    if ($v['stav'] !== 'ceka') {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'K téhle výzvě už je hozeno.');
    }
    if (!isset($info[$v['token_id']]) || !dracak_vtt_souboj_ovlada($user, $jePj, $info[$v['token_id']])) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(403, 'Za tohohle tvora házet nesmíš.');
    }
    $d = $v['data'];
    $cislo = dracak_vtt_vyzva_cislo($v['druh'], $d);
    if ($cislo === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, $v['druh'] === 'obrana'
            ? 'Chybí obranné číslo — PJ ho musí doplnit.'
            : ($v['druh'] === 'past' ? 'Chybí základ hodu — PJ ho musí doplnit.' : 'Chybí útočné číslo — PJ ho musí doplnit.'));
    }
    if ($v['druh'] === 'obrana' && ($d['volba'] ?? null) === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Nejdřív se musí rozhodnout, jak se obránce brání.');
    }
    if ($v['druh'] === 'past' && ($d['nebezpecnost'] ?? null) === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Čeká se, až PJ nastaví obtížnost hodu.');
    }

    if ($v['druh'] === 'past') {
        $hody = dracak_vtt_hody_k10_over($data['hody'] ?? (isset($data['soucet']) ? [$data['soucet']] : null));
        $chybaHodu = 'Hod proti pasti je 1k10, tedy jedno číslo 1 až 10.';
    } elseif ($v['druh'] === 'tik') {
        $hody = isset($data['hody'])
            ? dracak_vtt_hody_kostky_over($data['hody'], (int)$d['pocet'], (int)$d['typ'])
            : (isset($data['soucet']) ? dracak_vtt_hody_kostky_soucet((int)$data['soucet'], (int)$d['pocet'], (int)$d['typ']) : null);
        $chybaHodu = 'Hod je ' . $d['pocet'] . 'k' . $d['typ'] . ' — ' . ($d['pocet'] > 1 ? 'každá kostka 1 až ' . $d['typ'] . ', ruční součet ' . $d['pocet'] . ' až ' . ($d['pocet'] * $d['typ']) . '.' : 'jedno číslo 1 až ' . $d['typ'] . '.');
    } else {
        $hody = isset($data['hody']) ? dracak_vtt_hody_k6plus_over($data['hody']) : (isset($data['soucet']) ? dracak_vtt_hody_z_souctu((int)$data['soucet']) : null);
        $chybaHodu = 'Hod 1k6+ je číslo 1 až 6; šestka se přehazuje a sčítá, takže součet nemůže končit šestkou (6 + 3 = 9, ale 6 samotná ne).';
    }
    if ($hody === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, $chybaHodu);
    }
    $soucetHodu = array_sum($hody);
    $d['hody'] = $hody;
    $d['soucet_hodu'] = $soucetHodu;
    $d['celkem'] = $soucetHodu + $cislo;
    $d['hodil'] = $user['jmeno'];
    $d['rucne'] = $rucne;
    dracak_vtt_vyzva_uloz($pdo, $v['id'], $d, 'hozeno');
    $v['data'] = $d;
    $v['stav'] = 'hozeno';

    $uid = (int)$user['id'];
    $vysledek = null;
    $dalsi = null;
    $payloadNavic = [
        'hody' => $hody, 'soucet_hodu' => $soucetHodu, 'cislo' => $cislo, 'celkem' => $d['celkem'],
        'opravy' => dracak_vtt_vyzva_opravy($v['druh'], $d), 'modifikator' => (int)($d['modifikator'] ?? 0),
        'zaklad' => $d['zaklad'], 'zaklad_nazev' => $d['zaklad_nazev'] ?? '',
        'hodil' => $user['jmeno'], 'rucne' => $rucne,
    ];
    // Rozpis čísla nestvůry (ÚČ / OČ, opravy) nepatří do veřejné události — jen hody a součet.
    if ($info[$v['token_id']]['typ_entity'] !== 'postava') {
        unset($payloadNavic['cislo'], $payloadNavic['opravy'], $payloadNavic['modifikator'], $payloadNavic['zaklad'], $payloadNavic['zaklad_nazev']);
    }
    if ($v['druh'] === 'past') {
        $payloadNavic['uspech'] = $d['celkem'] >= (int)$d['nebezpecnost'];
    }
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'vyzva_hod',
        dracak_vtt_vyzva_payload($info, $v['druh'], $v['id'], $v['token_id'], $v['cil_token_id'], $payloadNavic), $uid);

    if ($v['druh'] === 'utok') {
        $dalsi = dracak_vtt_obrana_zaloz($pdo, $mapa, $info, $v, $uid);
    } elseif ($v['druh'] === 'obrana') {
        $utok = dracak_vtt_vyzva_nacti($pdo, (int)$v['rodic_id'], true);
        if (!$utok) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(500, 'K obraně chybí útok.');
        }
        $vysledek = dracak_vtt_utok_vyhodnot($pdo, $mapa, $info, $utok, $v, $uid);
        $posun = dracak_vtt_souboj_posun($pdo, $svetId, $mapaId, $uid);
    } elseif ($v['druh'] === 'tik') {
        $vysledek = dracak_vtt_tik_vyhodnot($pdo, $mapa, $info, $v, $uid);
    } elseif ($v['druh'] === 'past') {
        require_once __DIR__ . '/../../includes/vtt_souboj_past.php';
        $vysledek = dracak_vtt_past_vyhodnot($pdo, $mapa, $info, $v, $user);
        $poCommitu = $vysledek['po_commitu'];
        unset($vysledek['po_commitu']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
if (!empty($poCommitu)) {
    $poCommitu();
}
echo json_encode([
    'ok' => true, 'udalost_id' => $eventId, 'hody' => $hody, 'celkem' => $d['celkem'],
    'dalsi_vyzva_id' => $dalsi, 'vysledek' => $vysledek, 'posun' => $posun ?? null,
], JSON_UNESCAPED_UNICODE);
