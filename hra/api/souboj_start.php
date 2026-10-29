<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Zahájí souboj na mapě (jen PJ): vybere účastníky a jejich strany, určí
// překvapení (−6 k iniciativě v 1. kole) a otevře fázi úmyslů.
// Tělo: {mapa_id, prekvapeni: zadne|druzina|nepratele|obe,
//        ucastnici: [{token_id, strana: druzina|nepratele}]}
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
foreach ((is_array($data['ucastnici'] ?? null) ? $data['ucastnici'] : []) as $u) {
    $tokenId = (int)($u['token_id'] ?? 0);
    if (!isset($info[$tokenId]) || !in_array($info[$tokenId]['typ_entity'], ['postava', 'nestvura_instance'], true)) {
        continue;
    }
    $strana = (string)($u['strana'] ?? $info[$tokenId]['strana_vychozi']);
    $vybrani[$tokenId] = isset(DRACAK_VTT_STRANY[$strana]) ? $strana : $info[$tokenId]['strana_vychozi'];
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
echo json_encode(['ok' => true, 'udalost_id' => $eventId], JSON_UNESCAPED_UNICODE);
