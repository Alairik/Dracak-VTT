<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin přepíná mlhu.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$aktivni = !empty($data['aktivni']) ? 1 : 0;

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT svet_id FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$svetId = $stmt->fetchColumn();
if ($svetId === false || !dracak_vtt_svet_access($user, (int)$svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

$pdo->prepare('UPDATE mapy SET mlha_aktivni = ? WHERE id = ?')->execute([$aktivni, $mapaId]);
echo json_encode(['ok' => true, 'aktivni' => (bool)$aktivni]);
