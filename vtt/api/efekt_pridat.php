<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může aplikovat efekt.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$typEntity = ($data['typ_entity'] ?? '') === 'nestvura_instance' ? 'nestvura_instance' : 'postava';
$entitaId = (int)($data['entita_id'] ?? 0);
$efektId = (int)($data['efekt_id'] ?? 0);
$zbyvaKol = ($data['zbyva_kol'] ?? '') !== '' ? max(1, (int)$data['zbyva_kol']) : null;

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
if (!dracak_vtt_entity_row($typEntity, $entitaId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Entita nenalezena.']);
    exit;
}
$stmt = $pdo->prepare('SELECT nazev FROM efekty WHERE id = ?');
$stmt->execute([$efektId]);
$efektNazev = $stmt->fetchColumn();
if ($efektNazev === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Efekt nenalezen.']);
    exit;
}

$ins = $pdo->prepare('INSERT INTO aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol) VALUES (?, ?, ?, ?)');
$ins->execute([$typEntity, $entitaId, $efektId, $zbyvaKol]);
$aktivniId = (int)$pdo->lastInsertId();

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'efekt_aplikovan', [
    'typ_entity' => $typEntity,
    'entita_id' => $entitaId,
    'aktivni_efekt_id' => $aktivniId,
    'efekt_id' => $efektId,
    'efekt_nazev' => $efektNazev,
    'zbyva_kol' => $zbyvaKol,
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $eventId]);

