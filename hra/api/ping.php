<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$svetId = (int)($data['svet_id'] ?? 0);
$mapaId = (int)($data['mapa_id'] ?? 0);
$x = (int)($data['x'] ?? 0);
$y = (int)($data['y'] ?? 0);

if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Bez přístupu.']);
    exit;
}
$stmt = dracak_db()->prepare('SELECT 1 FROM mapy WHERE id = ? AND svet_id = ?');
$stmt->execute([$mapaId, $svetId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

// Ping je jen efemérní ukazovátko pro ostatní u stolu — nic mechanického
// se z něj nepočítá, proto stačí prostý zápis do logu bez transakce.
$eventId = dracak_vtt_log_event($svetId, $mapaId, 'ping', [
    'x' => $x,
    'y' => $y,
    'jmeno' => $user['jmeno'],
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'jmeno' => $user['jmeno']], JSON_UNESCAPED_UNICODE);
