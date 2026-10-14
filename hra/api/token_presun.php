<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_tokeny.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$tokenId = (int)($data['token_id'] ?? 0);
$noveX = (int)($data['x'] ?? 0);
$noveY = (int)($data['y'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare(
    'SELECT t.*, m.svet_id, m.sirka_px, m.vyska_px, m.grid_velikost_px, m.grid_typ, m.grid_posun_x, m.grid_posun_y
     FROM tokeny t JOIN mapy m ON m.id = t.mapa_id WHERE t.id = ?'
);
$stmt->execute([$tokenId]);
$token = $stmt->fetch();
if (!$token || !dracak_vtt_svet_access($user, (int)$token['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Token nenalezen.']);
    exit;
}
$chyba = dracak_vtt_token_kontrola_presunu($pdo, $user, $token, $noveX, $noveY);
if ($chyba !== null) {
    http_response_code($chyba[0]);
    echo json_encode(['error' => $chyba[1]]);
    exit;
}

$pdo->beginTransaction();
try {
    $upd = $pdo->prepare('UPDATE tokeny SET x = ?, y = ? WHERE id = ?');
    $upd->execute([$noveX, $noveY, $tokenId]);
    // Payload nese i hodnotu PŘED přesunem — bez toho by "krok zpět"
    // (viz docs/vtt-datovy-model-navrh-v1.md) šlo jen dopředu.
    $eventId = dracak_vtt_log_event((int)$token['svet_id'], (int)$token['mapa_id'], 'token_presun', [
        'token_id' => $tokenId,
        'x_pred' => (int)$token['x'],
        'y_pred' => (int)$token['y'],
        'x_po' => $noveX,
        'y_po' => $noveY,
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

dracak_vtt_token_odhal_mlhu($pdo, $token, $noveX, $noveY);

echo json_encode(['ok' => true, 'udalost_id' => $eventId]);
