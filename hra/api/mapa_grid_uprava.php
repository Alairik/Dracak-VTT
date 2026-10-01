<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin upravuje grid.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
// Prázdné/0 = grid vypnutý — žádný zvláštní "zapnuto" sloupec, stejný
// princip jako dřív v svet.php (NULL = bez gridu).
$gridVelikostPx = !empty($data['grid_velikost_px']) ? max(4, (int)$data['grid_velikost_px']) : null;
$gridTyp = ($data['grid_typ'] ?? '') === 'hex' ? 'hex' : 'ctverec';
$gridPosunX = (int)($data['grid_posun_x'] ?? 0);
$gridPosunY = (int)($data['grid_posun_y'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

$upd = $pdo->prepare('UPDATE mapy SET grid_velikost_px = ?, grid_typ = ?, grid_posun_x = ?, grid_posun_y = ? WHERE id = ?');
$upd->execute([$gridVelikostPx, $gridTyp, $gridPosunX, $gridPosunY, $mapaId]);

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'mapa_grid_zmena', [
    'grid_velikost_px' => $gridVelikostPx,
    'grid_typ' => $gridTyp,
    'grid_posun_x' => $gridPosunX,
    'grid_posun_y' => $gridPosunY,
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $eventId]);
