<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$tokenId = (int)($data['token_id'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT t.*, m.svet_id FROM tokeny t JOIN mapy m ON m.id = t.mapa_id WHERE t.id = ?');
$stmt->execute([$tokenId]);
$token = $stmt->fetch();
if (!$token || !dracak_vtt_svet_access($user, (int)$token['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Token nenalezen.']);
    exit;
}
if (!dracak_vtt_can_move_token($user, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Tenhle token smazat nesmíš.']);
    exit;
}

$del = $pdo->prepare('DELETE FROM tokeny WHERE id = ?');
$del->execute([$tokenId]);

$eventId = dracak_vtt_log_event((int)$token['svet_id'], (int)$token['mapa_id'], 'token_smazan', [
    'token_id' => $tokenId,
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $eventId]);

