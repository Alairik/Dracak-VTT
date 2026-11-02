<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

// Otevření / zavření dveří (zeď s příznakem `dvere`, migrace 0106). Smí jen PJ/admin světa.
// Tělo: {id, otevrene?: bool} — bez „otevrene“ se stav přepne. Otevřené dveře neblokují pohyb, výhled ani střelu
// (dracak_vtt_zdi_bez_otevrenych_dveri v includes/vtt.php). Událost „zed_zmena“ nese viditelna_hracum:
// skrytá zeď / dveře jsou tajemství PJ, hráčům se přes polling nedostanou.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$zedId = (int)($data['id'] ?? 0);

$pdo = dracak_db();
if (!dracak_vtt_zdi_ma_dvere($pdo)) {
    http_response_code(409);
    echo json_encode(['error' => 'Dveře se zatím nedají používat (čeká se na databázi).']);
    exit;
}
$stmt = $pdo->prepare('SELECT z.*, m.svet_id FROM zdi z JOIN mapy m ON m.id = z.mapa_id WHERE z.id = ?');
$stmt->execute([$zedId]);
$zed = $stmt->fetch();
if (!$zed || !dracak_vtt_svet_access($user, (int)$zed['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Dveře nenalezeny.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$zed['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Dveře smí otevírat a zavírat jen PJ/admin.']);
    exit;
}
if ((int)$zed['dvere'] !== 1) {
    http_response_code(422);
    echo json_encode(['error' => 'Tohle jsou obyčejné zdi, ne dveře.']);
    exit;
}
$otevrene = array_key_exists('otevrene', $data) ? (!empty($data['otevrene']) ? 1 : 0) : ((int)$zed['otevrene'] === 1 ? 0 : 1);
$pdo->prepare('UPDATE zdi SET otevrene = ? WHERE id = ?')->execute([$otevrene, $zedId]);

$eventId = dracak_vtt_log_event((int)$zed['svet_id'], (int)$zed['mapa_id'], 'zed_zmena', [
    'id' => $zedId,
    'otevrene' => (bool)$otevrene,
    'viditelna_hracum' => (bool)$zed['viditelna_hracum'],
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'otevrene' => (bool)$otevrene]);
