<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mlha.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
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
if (!dracak_vtt_je_pj_sveta($user, (int)$mapa['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin takhle upravuje mlhu.']);
    exit;
}

// "Celá mapa odhalit/zatáhnout VŠEM hráčům" — na rozdíl od
// hra/api/mlha_uprava.php (jeden vybraný hráč, viz náhled v popoveru)
// tohle je hromadná akce přes celý svet_hraci tohohle světa najednou.
$stmt = $pdo->prepare('SELECT ucet_id FROM svet_hraci WHERE svet_id = ?');
$stmt->execute([(int)$mapa['svet_id']]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $ucetId) {
    dracak_vtt_mlha_nastav_vse($pdo, $mapa, (int)$ucetId, $odhalit);
}

echo json_encode(['ok' => true]);
