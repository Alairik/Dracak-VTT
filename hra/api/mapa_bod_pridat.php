<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_body.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$nazev = trim((string)($data['nazev'] ?? ''));
$typ = in_array($data['typ'] ?? '', ['mesto', 'vesnice', 'tajne_misto', 'jine'], true) ? $data['typ'] : 'jine';
$x = (int)($data['x'] ?? 0);
$y = (int)($data['y'] ?? 0);
$popis = trim((string)($data['popis'] ?? '')) ?: null;
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
// Cílová mapa (pokud zadaná) musí patřit do stejného světa — jinak by
// šlo pinem na jednom světě otevřít mapu z úplně cizí kampaně.
if ($cilovaMapaId !== null) {
    $stmt = $pdo->prepare('SELECT 1 FROM mapy WHERE id = ? AND svet_id = ?');
    $stmt->execute([$cilovaMapaId, (int)$mapa['svet_id']]);
    if (!$stmt->fetchColumn()) {
        http_response_code(422);
        echo json_encode(['error' => 'Cílová mapa nepatří do tohohle světa.']);
        exit;
    }
}

$bodId = dracak_vtt_mapa_bod_pridat($pdo, $mapaId, $nazev, $typ, $x, $y, $cilovaMapaId, $popis, $viditelnyHracum);

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'mapa_bod_pridan', [
    'id' => $bodId, 'nazev' => $nazev, 'typ' => $typ, 'x' => $x, 'y' => $y,
    'cilova_mapa_id' => $cilovaMapaId, 'viditelny_hracum' => $viditelnyHracum,
], (int)$user['id']);

echo json_encode(['ok' => true, 'id' => $bodId, 'udalost_id' => $eventId]);
