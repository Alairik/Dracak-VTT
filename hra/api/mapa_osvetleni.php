<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_videni.php';

// Osvětlení mapy (jen PJ/admin): „den“ (dosavadní mlha války) nebo „tma“ (hráči vidí jen to, co osvětlí zdroje světla; viz includes/vtt_videni.php).
// Tělo: {mapa_id, osvetleni: "den" | "tma"}. Událost „mapa_osvetleni“ — klienti si znovu načtou mlhu a tokeny.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$osvetleni = dracak_text($data['osvetleni'] ?? '');
if (!in_array($osvetleni, ['den', 'tma'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Osvětlení je „den“ nebo „tma“.']);
    exit;
}

$pdo = dracak_db();
if (!dracak_vtt_videni_funkce($pdo)) {
    http_response_code(409);
    echo json_encode(['error' => 'Světlo a tma ještě nejsou zapnuté (čeká se na databázi).']);
    exit;
}
$stmt = $pdo->prepare('SELECT svet_id FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$svetId = $stmt->fetchColumn();
if ($svetId === false || !dracak_vtt_svet_access($user, (int)$svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Osvětlení mapy mění jen PJ/admin.']);
    exit;
}

$pdo->prepare('UPDATE mapy SET osvetleni = ? WHERE id = ?')->execute([$osvetleni, $mapaId]);
$eventId = dracak_vtt_log_event((int)$svetId, $mapaId, 'mapa_osvetleni', ['osvetleni' => $osvetleni], (int)$user['id']);
echo json_encode(['ok' => true, 'osvetleni' => $osvetleni, 'udalost_id' => $eventId]);
