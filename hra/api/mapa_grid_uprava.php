<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
// Prázdné/0 = grid vypnutý — žádný zvláštní "zapnuto" sloupec, stejný
// princip jako dřív v svet.php (NULL = bez gridu).
// Sloupce jsou SMALLINT (velikost buňky 0–65535, posun ±32767): obří hodnota z klienta by spadla na chybě databáze.
$gridVelikostPx = !empty($data['grid_velikost_px']) ? max(4, min(2000, (int)$data['grid_velikost_px'])) : null;
$gridTyp = ($data['grid_typ'] ?? '') === 'hex' ? 'hex' : 'ctverec';
$gridPosunX = max(-10000, min(10000, (int)($data['grid_posun_x'] ?? 0)));
$gridPosunY = max(-10000, min(10000, (int)($data['grid_posun_y'] ?? 0)));

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
    echo json_encode(['error' => 'Jen PJ/admin upravuje grid.']);
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
