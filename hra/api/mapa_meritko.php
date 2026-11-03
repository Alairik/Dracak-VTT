<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';

// Měřítko světové mapy (jen PJ/admin; migrace 0110): kolik mil je 1000 px mapy. Tělo: {mapa_id, mil_na_1000px: číslo 0,01–100 000 | null (= zrušit)}.
// Měřidlo na mapě pak ukáže vzdálenost i v mílích a PJ ji pošle do kalkulačky cesty (hra/cesta.php?vzdalenost=…). Událost mapa_grid_zmena se tu neposílá —
// měřítko jen čte PJ při měření a při dalším načtení mapy.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$pdo = dracak_db();
if (empty(dracak_vtt_tokeny_funkce()['meritko'])) {
    http_response_code(409);
    echo json_encode(['error' => 'Měřítko mapy ještě není zapnuté (čeká se na databázi).']);
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
    echo json_encode(['error' => 'Měřítko mapy nastavuje jen PJ/admin.']);
    exit;
}

$hodnota = $data['mil_na_1000px'] ?? null;
if ($hodnota === null || $hodnota === '') {
    $mil = null;
} else {
    $mil = round((float)$hodnota, 2);
    if (!is_finite($mil) || $mil < 0.01 || $mil > 100000) {
        http_response_code(422);
        echo json_encode(['error' => 'Měřítko je od 0,01 do 100 000 mil na 1000 px.']);
        exit;
    }
}
$pdo->prepare('UPDATE mapy SET mil_na_1000px = ? WHERE id = ?')->execute([$mil, $mapaId]);
echo json_encode(['ok' => true, 'mil_na_1000px' => $mil]);
