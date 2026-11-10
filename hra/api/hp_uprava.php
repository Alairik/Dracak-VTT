<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$typEntity = ($data['typ_entity'] ?? '') === 'nestvura_instance' ? 'nestvura_instance' : 'postava';
$entitaId = (int)($data['entita_id'] ?? 0);
$delta = (int)($data['delta'] ?? 0);
// „nastavit“ = ruční přepis životů na konkrétní číslo (okno tvora, rozhodnutí uživatele 2026-11-10).
// Neposílá se delta, protože PJ píše cílovou hodnotu a mezitím se mohlo trefit zranění od někoho jiného.
$nastavit = array_key_exists('nastavit', $data) && $data['nastavit'] !== null && $data['nastavit'] !== ''
    ? max(0, min(32767, (int)$data['nastavit']))
    : null;

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

$entity = dracak_vtt_entity_row($typEntity, $entitaId);
if (!$entity) {
    http_response_code(404);
    echo json_encode(['error' => 'Entita nenalezena.']);
    exit;
}
if (!dracak_vtt_can_edit_hp($user, $typEntity, $entity)) {
    http_response_code(403);
    echo json_encode(['error' => 'Tady upravovat život nesmíš.']);
    exit;
}

// max_hp = 0 znamená "nezadáno/neznámé" (viz nečistá data bestiáře), ne
// "postava/nestvůra nemůže mít žádný život" — v tom případě nahoru neklem.
// Změna je jeden UPDATE v DB (dracak_vtt_hp_zmen), ne čtení a zápis v PHP: dva současné zásahy se nesmí ztratit.
[$noveHp, $maxHp] = ($nastavit !== null
    ? dracak_vtt_hp_nastav($pdo, $typEntity, $entitaId, $nastavit)
    : dracak_vtt_hp_zmen($pdo, $typEntity, $entitaId, $delta)) ?? [0, (int)$entity['max_hp']];

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'hp_zmena', [
    'typ_entity' => $typEntity,
    'entita_id' => $entitaId,
    'nove_hp' => $noveHp,
    'max_hp' => $maxHp,
], (int)$user['id']);

echo json_encode(['ok' => true, 'nove_hp' => $noveHp, 'udalost_id' => $eventId]);

