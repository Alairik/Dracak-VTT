<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

$mapaId = (int)($_GET['mapa_id'] ?? 0);
$stmt = dracak_db()->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena nebo bez přístupu.']);
    exit;
}
$svetId = (int)$mapa['svet_id'];

// Pořadí je důležité: nejdřív poslední_udalost_id, teprve pak tokeny —
// jinak hrozí mezera mezi snapshotem a prvním pollem (viz
// docs/vtt-datovy-model-navrh-v1.md, mechanismy).
$posledniId = (int)(dracak_db()->query('SELECT MAX(id) FROM svet_udalosti WHERE svet_id = ' . $svetId)->fetchColumn() ?: 0);

$stmt = dracak_db()->prepare(
    'SELECT id, mapa_id, typ_entity, entita_id, x, y, z_poradi, viditelny_hracum FROM tokeny WHERE mapa_id = ?'
);
$stmt->execute([$mapaId]);
$tokeny = $stmt->fetchAll();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    $tokeny = array_values(array_filter($tokeny, fn($t) => (bool)$t['viditelny_hracum']));
}

echo json_encode([
    'svet_id' => $svetId,
    'mapa' => $mapa,
    'tokeny' => $tokeny,
    'posledni_udalost_id' => $posledniId,
], JSON_UNESCAPED_UNICODE);

