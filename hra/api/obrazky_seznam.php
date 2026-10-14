<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

// Knihovna obrázků světa (nejnovější první).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_require_login();
$svetId = (int)($_GET['svet_id'] ?? 0);
if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Svět nenalezen.']);
    exit;
}
try {
    $stmt = dracak_db()->prepare('SELECT id, nazev FROM svet_obrazky WHERE svet_id = ? ORDER BY id DESC LIMIT 400');
    $stmt->execute([$svetId]);
    $obrazky = $stmt->fetchAll();
} catch (Throwable $e) {
    $obrazky = []; // tabulka ještě není (ruční SQL nespuštěné)
}
echo json_encode(['obrazky' => array_map(fn($o) => ['id' => (int)$o['id'], 'nazev' => (string)$o['nazev']], $obrazky)], JSON_UNESCAPED_UNICODE);
