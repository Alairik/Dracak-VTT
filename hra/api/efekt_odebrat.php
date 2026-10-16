<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_efekty.php';

// Sundá nasazený efekt z tvora (jen PJ): krvácení po ošetření, spánek po zlomení kouzla, berserk po uklidnění…
// Tělo: {mapa_id, aktivni_efekt_id}
// Okamžité změny akcí, které efekt udělal při nasazení (Rychlost ×2…), se nevracejí — platí jen pro kolo, ve kterém vznikly.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$aktivniId = (int)($data['aktivni_efekt_id'] ?? 0);

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
    echo json_encode(['error' => 'Jen PJ/admin může sundat efekt.']);
    exit;
}
// Efekt musí patřit tvoru, který má na téhle mapě token.
$stmt = $pdo->prepare(
    'SELECT ae.id FROM aktivni_efekty ae
     WHERE ae.id = ? AND EXISTS (SELECT 1 FROM tokeny t WHERE t.mapa_id = ? AND t.typ_entity = ae.typ_entity AND t.entita_id = ae.entita_id)'
);
$stmt->execute([$aktivniId, $mapaId]);
if ($stmt->fetchColumn() === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Efekt na téhle mapě nenalezen.']);
    exit;
}

$svetId = (int)$mapa['svet_id'];
$pdo->beginTransaction();
try {
    $nazev = dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, (int)$user['id'], $aktivniId);
    $posun = dracak_vtt_efekt_posun_po_nasazeni($pdo, $svetId, $mapaId, (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
echo json_encode(['ok' => true, 'efekt_nazev' => $nazev, 'posun' => $posun], JSON_UNESCAPED_UNICODE);
