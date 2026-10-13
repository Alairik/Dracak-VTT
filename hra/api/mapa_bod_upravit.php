<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_body.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$bodId = (int)($data['id'] ?? 0);
$nazev = trim((string)($data['nazev'] ?? ''));
$typ = in_array($data['typ'] ?? '', ['mesto', 'vesnice', 'tajne_misto', 'budova', 'mistnost', 'sklep', 'prechod', 'jine'], true) ? $data['typ'] : 'jine';
$popis = trim((string)($data['popis'] ?? '')) ?: null;
$viditelnyHracum = !empty($data['viditelny_hracum']);
$cilovaMapaId = !empty($data['cilova_mapa_id']) ? (int)$data['cilova_mapa_id'] : null;

$pdo = dracak_db();
// svet_id přímo na pinu (migrace 0063) — bez JOINu přes mapy, viz
// konverzace o "piny mají mít napojení na svět". mapa_id ale pořád
// potřebujeme zvlášť, je to vlastní lokální zóna pinu (beze změny),
// na rozdíl od cilova_mapa_id (kam pin vede) — ta je editovatelná.
$stmt = $pdo->prepare('SELECT svet_id, mapa_id FROM mapa_body WHERE id = ?');
$stmt->execute([$bodId]);
$bod = $stmt->fetch();
if (!$bod || !dracak_vtt_svet_access($user, (int)$bod['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Pin nenalezen.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$bod['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin upravuje piny.']);
    exit;
}
if ($nazev === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Pin musí mít název.']);
    exit;
}

$chybaCile = dracak_vtt_mapa_bod_validuj_cil($pdo, (int)$bod['svet_id'], (int)$bod['mapa_id'], $cilovaMapaId);
if ($chybaCile !== null) {
    http_response_code(422);
    echo json_encode(['error' => $chybaCile]);
    exit;
}

$ok = dracak_vtt_mapa_bod_upravit($pdo, $bodId, (int)$bod['svet_id'], $nazev, $typ, $cilovaMapaId, $popis, $viditelnyHracum);

$eventId = dracak_vtt_log_event((int)$bod['svet_id'], (int)$bod['mapa_id'], 'mapa_bod_pridan', [
    'id' => $bodId, 'nazev' => $nazev, 'typ' => $typ, 'cilova_mapa_id' => $cilovaMapaId,
    'viditelny_hracum' => $viditelnyHracum, 'akce' => 'upraven',
], (int)$user['id']);

echo json_encode(['ok' => $ok, 'udalost_id' => $eventId]);
