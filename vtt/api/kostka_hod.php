<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$svetId = (int)($data['svet_id'] ?? 0);
$mapaId = !empty($data['mapa_id']) ? (int)$data['mapa_id'] : null;
$notace = trim((string)($data['notace'] ?? ''));

if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Bez přístupu.']);
    exit;
}

$rozebrano = dracak_vtt_parsuj_kostky($notace);
if ($rozebrano === null) {
    http_response_code(422);
    echo json_encode(['error' => 'Neplatný zápis kostek. Použij např. "2k6+2".']);
    exit;
}

$vysledek = dracak_vtt_hod_kostkou($rozebrano['pocet'], $rozebrano['typ'], $rozebrano['bonus']);

$eventId = dracak_vtt_log_event($svetId, $mapaId, 'kostka_hod', [
    'notace' => $notace,
    'typ_kostky' => $rozebrano['typ'],
    'hody' => $vysledek['hody'],
    'bonus' => $vysledek['bonus'],
    'celkem' => $vysledek['celkem'],
    'hodil' => $user['jmeno'],
], (int)$user['id']);

echo json_encode([
    'ok' => true,
    'udalost_id' => $eventId,
    'notace' => $notace,
    'hody' => $vysledek['hody'],
    'bonus' => $vysledek['bonus'],
    'celkem' => $vysledek['celkem'],
    'hodil' => $user['jmeno'],
], JSON_UNESCAPED_UNICODE);
