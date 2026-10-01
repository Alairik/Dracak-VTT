<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může kreslit zdi.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$x1 = (float)($data['x1'] ?? 0);
$y1 = (float)($data['y1'] ?? 0);
$x2 = (float)($data['x2'] ?? 0);
$y2 = (float)($data['y2'] ?? 0);
$sirkaPx = max(1.0, (float)($data['sirka_px'] ?? 6));
$blokujePohyb = !empty($data['blokuje_pohyb']) ? 1 : 0;
$blokujeVystrel = !empty($data['blokuje_vystrel']) ? 1 : 0;
$viditelnaHracum = !empty($data['viditelna_hracum']) ? 1 : 0;

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
if ($x1 === $x2 && $y1 === $y2) {
    http_response_code(400);
    echo json_encode(['error' => 'Zeď musí mít nenulovou délku.']);
    exit;
}

$ins = $pdo->prepare(
    'INSERT INTO zdi (mapa_id, x1, y1, x2, y2, sirka_px, blokuje_pohyb, blokuje_vystrel, viditelna_hracum)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$ins->execute([$mapaId, $x1, $y1, $x2, $y2, $sirkaPx, $blokujePohyb, $blokujeVystrel, $viditelnaHracum]);
$zedId = (int)$pdo->lastInsertId();

// payload nese i viditelna_hracum — udalosti.php podle něj rozhoduje,
// jestli event smí dojít i hráčům (skrytá zeď je tajemství PJ, nesmí
// prosáknout přes polling, i když se vykreslí jen jemu).
$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'zed_pridana', [
    'id' => $zedId,
    'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2,
    'sirka_px' => $sirkaPx,
    'blokuje_pohyb' => (bool)$blokujePohyb,
    'blokuje_vystrel' => (bool)$blokujeVystrel,
    'viditelna_hracum' => (bool)$viditelnaHracum,
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'id' => $zedId]);
