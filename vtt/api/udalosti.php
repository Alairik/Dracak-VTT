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

$svetId = (int)($_GET['svet_id'] ?? 0);
$od = (int)($_GET['od'] ?? 0);
if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Bez přístupu.']);
    exit;
}

$stmt = dracak_db()->prepare(
    'SELECT id, mapa_id, typ, payload, ucet_id, vytvoreno FROM svet_udalosti
     WHERE svet_id = ? AND id > ? ORDER BY id LIMIT 200'
);
$stmt->execute([$svetId, $od]);
$udalosti = $stmt->fetchAll();
foreach ($udalosti as &$u) {
    $u['payload'] = json_decode((string)$u['payload'], true);
}
unset($u);

echo json_encode(['udalosti' => $udalosti], JSON_UNESCAPED_UNICODE);

