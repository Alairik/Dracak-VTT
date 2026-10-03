<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mlha.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin má náhled cizí mlhy.']);
    exit;
}

$mapaId = (int)($_GET['mapa_id'] ?? 0);
$ucetId = (int)($_GET['ucet_id'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
// Cílový účet musí být skutečně hráč TOHOHLE světa — jinak by šlo
// nahlížet do mlhy někoho zcela cizího.
$stmt = $pdo->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
$stmt->execute([(int)$mapa['svet_id'], $ucetId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'Tenhle účet není hráč v tomhle světě.']);
    exit;
}

$stav = dracak_vtt_mlha_nacti($pdo, $mapa, $ucetId);
echo json_encode([
    'typ' => $stav['typ'],
    'bunka_px' => $stav['bunka_px'],
    'sloupcu' => $stav['sloupcu'],
    'radku' => $stav['radku'],
    'min_row' => $stav['min_row'] ?? 0,
    'min_col' => $stav['min_col'] ?? 0,
    'bitmapa_b64' => base64_encode($stav['bitmapa']),
]);
