<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// PJ zruší rozehraný hod (útok s obranou, hod proti pasti): nikdo neutrpí zranění, akce útočníka
// zůstává utracená, tah jde dál. "PJ může vynutit a ovládat vše, včetně přeskočení na dalšího"
// (docs/navrh-tahu-v2.md); čekání na obránce nemá časovač.
// Tělo: {mapa_id}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId, true);
$pdo = dracak_db();
$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);

$pdo->beginTransaction();
try {
    dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    $otevrene = dracak_vtt_vyzva_otevrene($pdo, $mapaId, true);
    if (!$otevrene) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Žádný hod nečeká.');
    }
    dracak_vtt_vyzva_zrus_vse($pdo, $mapaId);
    $prvni = $otevrene[0];
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'vyzva_zrusena',
        dracak_vtt_vyzva_payload($info, $prvni['druh'], $prvni['id'], $prvni['token_id'], $prvni['cil_token_id'], ['pocet' => count($otevrene)]),
        (int)$user['id']);
    $posun = dracak_vtt_souboj_posun($pdo, $svetId, $mapaId, (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'posun' => $posun], JSON_UNESCAPED_UNICODE);
