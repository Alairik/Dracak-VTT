<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$postavaId = (int)($data['postava_id'] ?? 0);
$x = (int)($data['x'] ?? 0);
$y = (int)($data['y'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM postavy WHERE id = ? AND svet_id = ?');
$stmt->execute([$postavaId, $mapa['svet_id']]);
$postava = $stmt->fetch();
if (!$postava) {
    http_response_code(404);
    echo json_encode(['error' => 'Postava nenalezena v tomhle světě.']);
    exit;
}
// Hráč smí na mapu položit jen svou vlastní postavu, pj/admin kteroukoli.
if (!$isPjOrAdmin && (int)$postava['vlastnik_ucet_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Tohle není tvoje postava.']);
    exit;
}

$stmt = $pdo->prepare(
    "INSERT INTO tokeny (mapa_id, typ_entity, entita_id, x, y) VALUES (?, 'postava', ?, ?, ?)"
);
$stmt->execute([$mapaId, $postavaId, $x, $y]);
$tokenId = (int)$pdo->lastInsertId();

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'token_pridan', [
    'token_id' => $tokenId,
    'typ_entity' => 'postava',
    'entita_id' => $postavaId,
    'nazev' => $postava['nazev'],
    'x' => $x,
    'y' => $y,
], (int)$user['id']);

echo json_encode(['ok' => true, 'token_id' => $tokenId, 'udalost_id' => $eventId, 'nazev' => $postava['nazev']]);
