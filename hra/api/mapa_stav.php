<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';

// Aktuální stav mapy pro klienta (tokeny, efekty, pořadí tahů, postavy k
// položení) — klient s ním překreslí mapu "na místě" místo location.reload().
// Počítá se pro přihlášeného uživatele (owned / skryté tokeny), nic se
// necachuje. Starší hra/api/stav.php zůstává pro jednoduchý snapshot.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

$mapaId = (int)($_GET['mapa_id'] ?? 0);
$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena nebo bez přístupu.']);
    exit;
}

echo json_encode(
    dracak_vtt_mapa_stav($pdo, $user, $mapa, dracak_vtt_je_pj_sveta($user, (int)$mapa['svet_id'])),
    JSON_UNESCAPED_UNICODE
);
