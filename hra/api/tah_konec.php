<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Předčasný konec tahu: akce, které tvor v tomhle tahu ještě mohl odehrát,
// propadnou jako čekání (h1623: "čekání — nulová akce, slouží hlavně k zabíjení
// času"), tah jde dál. Smí postava na tahu (její hráč) nebo PJ.
// Tělo: {mapa_id}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId);
$pdo = dracak_db();
$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null || $stav['faze'] !== 'kolo' || $stav['aktivni_token_id'] === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Teď nikdo není na tahu.');
    }
    $tokenId = $stav['aktivni_token_id'];
    if (dracak_vtt_vyzva_otevrene($pdo, $mapaId, true)) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Nejdřív se musí dohrát rozehraný hod (nebo ho PJ zruší).');
    }
    if (!isset($info[$tokenId]) || !dracak_vtt_souboj_ovlada($user, $jePj, $info[$tokenId])) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(403, 'Tah ukončit nesmíš.');
    }
    $q = $pdo->prepare('SELECT akce_celkem, akce_zbyvajici FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ? FOR UPDATE');
    $q->execute([$mapaId, $tokenId]);
    $ki = $q->fetch();
    $kvota = $ki ? dracak_vtt_souboj_kvota((int)$ki['akce_celkem'], (int)$ki['akce_zbyvajici'], $stav['polovina']) : 0;
    if ($kvota > 0) {
        $pdo->prepare('UPDATE kolo_iniciativa SET akce_zbyvajici = akce_zbyvajici - ? WHERE mapa_id = ? AND token_id = ?')
            ->execute([$kvota, $mapaId, $tokenId]);
    }
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'tah_konec', [
        'token_id' => $tokenId,
        'label' => $info[$tokenId]['label'],
        'propadlo' => $kvota,
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
echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'posun' => $posun], JSON_UNESCAPED_UNICODE);
