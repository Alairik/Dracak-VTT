<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

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
if (!dracak_vtt_je_pj_sveta($user, (int)$mapa['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin přepíná aktivní mapu.']);
    exit;
}

$pdo->prepare('UPDATE svet SET aktivni_mapa_id = ? WHERE id = ?')->execute([$mapaId, (int)$mapa['svet_id']]);
$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'aktivni_mapa_zmena', [
    'mapa_id' => $mapaId, 'nazev' => $mapa['nazev'],
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $eventId]);
