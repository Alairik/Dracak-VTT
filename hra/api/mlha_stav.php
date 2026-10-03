<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mlha.php';

header('Content-Type: application/json; charset=utf-8');
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
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

// PJ/admin nemá mlhu vůbec — vidí celou mapu vždycky (mimo výslovný
// náhled hráčova pohledu, viz hra/api/mlha_nahled.php — to je zvlášť
// endpoint, sem se PJ vůbec nedostane dřív, než bude mít roli hráč).
// Taky mapa, kde PJ mlhu vypnul (mapy.mlha_aktivni), nemá mlhu nikomu.
if (in_array($user['role'], ['admin', 'pj'], true) || !$mapa['mlha_aktivni']) {
    echo json_encode(['zadna_mlha' => true]);
    exit;
}

$stav = dracak_vtt_mlha_nacti($pdo, $mapa, (int)$user['id']);
echo json_encode([
    'typ' => $stav['typ'],
    'bunka_px' => $stav['bunka_px'],
    'sloupcu' => $stav['sloupcu'],
    'radku' => $stav['radku'],
    // min_row/min_col: klient potřebuje k převodu bitmapy (vždy 0-indexované
    // řádky/sloupce) zpátky na skutečné (row,col) buňky gridu, viz
    // hra/mapa.php mlhaBunkaStred() — gridu samotnému totiž nic nebrání
    // mít zápornou buňku (posun gridu doleva/nahoru od obrázku).
    'min_row' => $stav['min_row'] ?? 0,
    'min_col' => $stav['min_col'] ?? 0,
    // Base64 — bitmapa je binární (NUL bajty), JSON string to nezvládne
    // syrově; klient si ho dekóduje přes atob().
    'bitmapa_b64' => base64_encode($stav['bitmapa']),
]);
