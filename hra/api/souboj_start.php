<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';
require_once __DIR__ . '/../../includes/vtt_denik.php';
require_once __DIR__ . '/../../includes/vtt_zbrane_u_sebe.php';

// Zahájí souboj na mapě (jen PJ): vybere účastníky a jejich strany, určí
// překvapení (−6 k iniciativě v 1. kole) a otevře fázi úmyslů.
// Tělo: {mapa_id, prekvapeni: zadne|druzina|nepratele|obe,
//        ucastnici: [{token_id, strana: druzina|nepratele, zbran?: bool}]}
//
// `zbran` (jen u postav; návrh souboje 2026-11-12, krok 0): má postava na začátku boje zbraň v ruce?
//   true  = kdo má prázdné ruce, vytasí bez akce zbraň, kterou má u sebe (nejvyšší ÚČ tváří v tvář; bez takové
//           první zbraň u sebe). Kdo už něco drží, drží to dál. Zbraň z batohu se do ruky sama nedostane.
//   false = boj ji zastihl beze zbraně: co drží, zasune k tělu a tasí až svou akcí.
//   chybí = nic se nemění (starší volání).
// Je to domácí pravidlo družiny („v jakési bonusové neviditelné akci všichni vytasili“) — kniha jinak říká,
// že vytasení stojí akci (h1606, b10619). S překvapením to nesouvisí: i překvapený může mít zbraň v ruce.
// Odpověď navíc nese `zbrane` {tasili: [{kdo, zbran}], schovali: [{kdo, zbran}], bez: [kdo]} — jen pro PJ, do
// události se to nezapisuje (hráči by jinak viděli výzbroj cizích postav).
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId, true);
$pdo = dracak_db();

$prekvapeni = dracak_text($data['prekvapeni'] ?? 'zadne');
if (!in_array($prekvapeni, DRACAK_VTT_PREKVAPENI, true)) {
    dracak_vtt_souboj_chyba(422, 'Neplatné překvapení.');
}
$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
$vybrani = [];
$zbranVRuce = [];
$zbrane = ['tasili' => [], 'schovali' => [], 'bez' => []];
foreach ((is_array($data['ucastnici'] ?? null) ? $data['ucastnici'] : []) as $u) {
    $tokenId = (int)($u['token_id'] ?? 0);
    if (!isset($info[$tokenId]) || !in_array($info[$tokenId]['typ_entity'], ['postava', 'nestvura_instance'], true)) {
        continue;
    }
    $strana = (string)($u['strana'] ?? $info[$tokenId]['strana_vychozi']);
    $vybrani[$tokenId] = isset(DRACAK_VTT_STRANY[$strana]) ? $strana : $info[$tokenId]['strana_vychozi'];
    if ($info[$tokenId]['typ_entity'] === 'postava' && array_key_exists('zbran', $u) && is_bool($u['zbran'])) {
        $zbranVRuce[$tokenId] = $u['zbran'];
    }
}
if (!$vybrani) {
    dracak_vtt_souboj_chyba(422, 'Vyber aspoň jednoho účastníka souboje.');
}

$pdo->beginTransaction();
try {
    if (dracak_vtt_souboj_nacti($pdo, $mapaId, true) !== null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Na téhle mapě už souboj probíhá.');
    }
    $pdo->prepare('INSERT INTO kolo_stav (mapa_id, cislo_kola, aktivni_token_id, faze, polovina, prekvapeni) VALUES (?, 1, NULL, "umysly", 1, ?)')
        ->execute([$mapaId, $prekvapeni]);
    $ins = $pdo->prepare('INSERT INTO kolo_ucastnik (mapa_id, token_id, strana) VALUES (?, ?, ?)');
    foreach ($vybrani as $tokenId => $strana) {
        $ins->execute([$mapaId, $tokenId, $strana]);
    }
    foreach ($zbranVRuce as $tokenId => $maMit) {
        $q = $pdo->prepare('SELECT * FROM postavy WHERE id = ? FOR UPDATE');
        $q->execute([$info[$tokenId]['entita_id']]);
        $p = $q->fetch();
        if (!$p || !array_key_exists('drzena_zbran_id', $p)) {
            continue;
        }
        $kdo = (string)$info[$tokenId]['label'];
        $drzi = !empty($p['drzena_zbran_id']);
        if ($maMit && !$drzi) {
            $nejlepsi = null;
            foreach (dracak_vtt_denik_data($pdo, $p, false)['zbrane'] as $z) {
                if (!$z['u_sebe']) {
                    continue;
                }
                $uc = $z['uc_tvar_v_tvar'] !== null ? (int)$z['uc_tvar_v_tvar'] : -1000;
                if ($nejlepsi === null || $uc > $nejlepsi['uc']) {
                    $nejlepsi = ['id' => (int)$z['id'], 'uc' => $uc];
                }
            }
            if ($nejlepsi === null) {
                $zbrane['bez'][] = $kdo;
            } else {
                $zbrane['tasili'][] = ['kdo' => $kdo, 'zbran' => dracak_vtt_zbrane_vytasit($pdo, $p, $nejlepsi['id'], false)];
            }
        } elseif (!$maMit && $drzi) {
            $q = $pdo->prepare('SELECT nazev FROM predmety WHERE id = ?');
            $q->execute([(int)$p['drzena_zbran_id']]);
            $zbrane['schovali'][] = ['kdo' => $kdo, 'zbran' => (string)($q->fetchColumn() ?: 'zbraň')];
            dracak_vtt_zbrane_vytasit($pdo, $p, 0, false);
        }
    }
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'souboj_start', [
        'ucastnici' => array_keys($vybrani),
        'prekvapeni' => $prekvapeni,
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'zbrane' => $zbrane], JSON_UNESCAPED_UNICODE);
