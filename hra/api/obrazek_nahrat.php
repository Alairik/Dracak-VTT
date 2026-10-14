<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';
require_once __DIR__ . '/../../includes/vtt_obrazky.php';

// Nahraje obrázek do knihovny světa (multipart: svet_id, soubor, nazev).
// Smí kdokoli ze světa — hráč si takhle dá portrét své postavě.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$svetId = (int)($_POST['svet_id'] ?? 0);
if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Svět nenalezen.']);
    exit;
}
if (!dracak_vtt_tokeny_funkce()['obrazek']) {
    http_response_code(409);
    echo json_encode(['error' => 'Obrázky tokenů se zapnou po jednorázovém kroku správce (ruční SQL).']);
    exit;
}
$vysledek = dracak_vtt_obrazek_ulozit(dracak_db(), $svetId, (int)$user['id'], $_FILES['soubor'] ?? [], (string)($_POST['nazev'] ?? ''));
if (isset($vysledek['chyba'])) {
    http_response_code(422);
    echo json_encode(['error' => $vysledek['chyba']]);
    exit;
}
echo json_encode(['ok' => true] + $vysledek, JSON_UNESCAPED_UNICODE);
