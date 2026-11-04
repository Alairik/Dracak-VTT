<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mlha.php';
require_once __DIR__ . '/../../includes/vtt_videni.php';

// Přehled světla a stínů pro PJ (přepínač „Ukázat světlo“ v okně Mlha): GET mapa_id → {videni, geom, diagnoza}. Jen PJ/admin světa. Hráči tohle nedostanou — nese polohy všech zdrojů světla.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_require_login();

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
if (!dracak_vtt_je_pj_sveta($user, (int)$mapa['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Přehled světla vidí jen PJ/admin.']);
    exit;
}
$geom = dracak_vtt_videni_geometrie_pj($pdo, $mapa);
echo json_encode(['videni' => $geom !== null, 'geom' => $geom, 'diagnoza' => dracak_vtt_videni_diagnoza($pdo, $mapa)], JSON_UNESCAPED_UNICODE);
