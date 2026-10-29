<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_body.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$nazev = mb_substr(trim(dracak_text($data['nazev'] ?? '')), 0, 150);   // sloupec nazev je VARCHAR(150)
$typ = in_array($data['typ'] ?? '', ['mesto', 'vesnice', 'tajne_misto', 'budova', 'mistnost', 'sklep', 'prechod', 'jine'], true) ? $data['typ'] : 'jine';
$x = dracak_vtt_souradnice($data['x'] ?? 0);
$y = dracak_vtt_souradnice($data['y'] ?? 0);
$popis = trim(dracak_text($data['popis'] ?? '')) ?: null;
$viditelnyHracum = !empty($data['viditelny_hracum']);
$cilovaMapaId = !empty($data['cilova_mapa_id']) ? (int)$data['cilova_mapa_id'] : null;

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
    echo json_encode(['error' => 'Jen PJ/admin přidává piny.']);
    exit;
}
if ($nazev === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Pin musí mít název.']);
    exit;
}
$chybaCile = dracak_vtt_mapa_bod_validuj_cil($pdo, (int)$mapa['svet_id'], $mapaId, $cilovaMapaId);
if ($chybaCile !== null) {
    http_response_code(422);
    echo json_encode(['error' => $chybaCile]);
    exit;
}

$bodId = dracak_vtt_mapa_bod_pridat($pdo, (int)$mapa['svet_id'], $mapaId, $nazev, $typ, $x, $y, $cilovaMapaId, $popis, $viditelnyHracum);

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'mapa_bod_pridan', [
    'id' => $bodId, 'nazev' => $nazev, 'typ' => $typ, 'x' => $x, 'y' => $y,
    'cilova_mapa_id' => $cilovaMapaId, 'viditelny_hracum' => $viditelnyHracum,
], (int)$user['id']);

echo json_encode(['ok' => true, 'id' => $bodId, 'udalost_id' => $eventId]);
