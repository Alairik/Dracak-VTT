<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mlha.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

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
if (!dracak_vtt_je_pj_sveta($user, (int)$mapa['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin má náhled cizí mlhy.']);
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

require_once __DIR__ . '/../../includes/vtt_videni.php';
$stav = dracak_vtt_mlha_nacti($pdo, $mapa, $ucetId);
$videni = dracak_vtt_videni_vypocet($pdo, $mapa, $ucetId);   // náhled nic nezapisuje do paměti hráče
echo json_encode([
    'videni' => $videni !== null,
    'videne_b64' => $videni !== null ? base64_encode($videni['videno']) : null,
    // Světlo a stíny (assets/js/svetlo_stiny.js): jen mnohoúhelníky, které hráč smí znát; bez nich klient kreslí mlhu po buňkách jako dřív.
    'geom' => $videni !== null ? dracak_vtt_videni_geometrie($mapa, $videni) : null,
    'typ' => $stav['typ'],
    'bunka_px' => $stav['bunka_px'],
    'sloupcu' => $stav['sloupcu'],
    'radku' => $stav['radku'],
    'min_row' => $stav['min_row'] ?? 0,
    'min_col' => $stav['min_col'] ?? 0,
    'bitmapa_b64' => base64_encode($stav['bitmapa']),
]);
