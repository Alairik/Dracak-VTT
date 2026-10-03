<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mlha.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin ručně upravuje mlhu.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$ucetId = (int)($data['ucet_id'] ?? 0);
$x = (float)($data['x'] ?? 0);
$y = (float)($data['y'] ?? 0);
// x2/y2 nepovinné — s nimi se bere jako obdélníkový výběr (tažení přes
// víc buněk najednou), bez nich jako jedna buňka pod bodem (x,y).
$x2 = isset($data['x2']) ? (float)$data['x2'] : null;
$y2 = isset($data['y2']) ? (float)$data['y2'] : null;
$odhalit = !empty($data['odhalit']);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
$stmt = $pdo->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
$stmt->execute([(int)$mapa['svet_id'], $ucetId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'Tenhle účet není hráč v tomhle světě.']);
    exit;
}

if ($x2 !== null && $y2 !== null) {
    dracak_vtt_mlha_nastav_obdelnik($pdo, $mapa, $ucetId, $x, $y, $x2, $y2, $odhalit);
} else {
    dracak_vtt_mlha_nastav_bod($pdo, $mapa, $ucetId, $x, $y, $odhalit);
}
echo json_encode(['ok' => true]);
