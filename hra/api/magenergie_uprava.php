<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_predmety.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

// Magenergie existuje jen na postavy (viz migrace
// 0050_vtt_postava_magenergie.sql) — nestvury instance ji ve schématu
// vůbec nemají, na rozdíl od HP. typ_entity se tu proto nepřebírá z
// requestu jako u hp_uprava.php, vždy je to 'postava'.
$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$entitaId = (int)($data['entita_id'] ?? 0);
$delta = (int)($data['delta'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

$entity = dracak_vtt_entity_row('postava', $entitaId);
if (!$entity) {
    http_response_code(404);
    echo json_encode(['error' => 'Postava nenalezena.']);
    exit;
}
// Stejné právo jako úprava života (dracak_vtt_can_edit_hp): pj/admin
// komukoliv, hráč jen své vlastní postavě.
if (!dracak_vtt_can_edit_hp($user, 'postava', $entity)) {
    http_response_code(403);
    echo json_encode(['error' => 'Tady upravovat magenergii nesmíš.']);
    exit;
}
if (!dracak_vtt_ma_magenergii($entity)) {
    http_response_code(422);
    echo json_encode(['error' => 'Tahle postava magenergii nepoužívá (max_magenergie není nastaveno).']);
    exit;
}

$maxMagenergie = (int)$entity['max_magenergie'];
$novaMagenergie = max(0, min($maxMagenergie, (int)$entity['aktualni_magenergie'] + $delta));

$upd = $pdo->prepare('UPDATE postavy SET aktualni_magenergie = ? WHERE id = ?');
$upd->execute([$novaMagenergie, $entitaId]);

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'magenergie_zmena', [
    'typ_entity' => 'postava',
    'entita_id' => $entitaId,
    'nova_magenergie' => $novaMagenergie,
    'max_magenergie' => $maxMagenergie,
], (int)$user['id']);

echo json_encode(['ok' => true, 'nova_magenergie' => $novaMagenergie, 'max_magenergie' => $maxMagenergie, 'udalost_id' => $eventId]);
