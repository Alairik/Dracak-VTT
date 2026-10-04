<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$zedId = (int)($data['id'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT z.*, m.svet_id FROM zdi z JOIN mapy m ON m.id = z.mapa_id WHERE z.id = ?');
$stmt->execute([$zedId]);
$zed = $stmt->fetch();
if (!$zed || !dracak_vtt_svet_access($user, (int)$zed['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Zeď nenalezena.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$zed['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může mazat zdi.']);
    exit;
}

$del = $pdo->prepare('DELETE FROM zdi WHERE id = ?');
$del->execute([$zedId]);

$eventId = dracak_vtt_log_event((int)$zed['svet_id'], (int)$zed['mapa_id'], 'zed_smazana', [
    'id' => $zedId,
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $eventId]);
