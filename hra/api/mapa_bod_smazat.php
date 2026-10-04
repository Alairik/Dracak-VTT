<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_body.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$bodId = (int)($data['id'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT b.*, m.svet_id FROM mapa_body b JOIN mapy m ON m.id = b.mapa_id WHERE b.id = ?');
$stmt->execute([$bodId]);
$bod = $stmt->fetch();
if (!$bod || !dracak_vtt_svet_access($user, (int)$bod['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Pin nenalezen.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$bod['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin maže piny.']);
    exit;
}

dracak_vtt_mapa_bod_smazat($pdo, $bodId, (int)$bod['mapa_id']);
echo json_encode(['ok' => true]);
