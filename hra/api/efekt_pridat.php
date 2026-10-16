<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_efekty.php';

// Nasadí efekt tvorovi (jen PJ). Všechno se děje v includes/vtt_efekty.php: výchozí trvání efektu, kolo
// nasazení, okamžité změny akcí (Rychlost ×2, Protoplazma ½…), obnovení trvání u efektu, který už na tvoru je.
// Tělo: {mapa_id, typ_entity, entita_id, efekt_id, zbyva_kol?}
//   zbyva_kol prázdné = výchozí trvání efektu, 0 = bez limitu (trvá, dokud ho PJ nesundá), jinak počet kol.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$typEntity = ($data['typ_entity'] ?? '') === 'nestvura_instance' ? 'nestvura_instance' : 'postava';
$entitaId = (int)($data['entita_id'] ?? 0);
$efektId = (int)($data['efekt_id'] ?? 0);
$volby = [];
if (($data['zbyva_kol'] ?? '') !== '' && $data['zbyva_kol'] !== null) {
    $kola = (int)$data['zbyva_kol'];
    $volby['zbyva_kol'] = $kola > 0 ? $kola : null;
}

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
    echo json_encode(['error' => 'Jen PJ/admin může aplikovat efekt.']);
    exit;
}
if (!dracak_vtt_entity_row($typEntity, $entitaId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Entita nenalezena.']);
    exit;
}
$efekt = dracak_vtt_efekt_radek($pdo, $efektId);
if ($efekt === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Efekt nenalezen.']);
    exit;
}
if (dracak_vtt_hp_znamenko($efekt) !== null) {
    http_response_code(422);
    echo json_encode(['error' => 'Poškození a Léčení nejsou trvalé efekty — životy se mění úpravou životů, kouzlem nebo lektvarem.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$svetId = (int)$mapa['svet_id'];
$pdo->beginTransaction();
try {
    $vysledek = dracak_vtt_efekt_aplikuj($pdo, $mapaId, $typEntity, $entitaId, $efektId, $volby);
    $posun = dracak_vtt_efekt_posun_po_nasazeni($pdo, $svetId, $mapaId, (int)$user['id']);
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'efekt_aplikovan', [
        'typ_entity' => $typEntity,
        'entita_id' => $entitaId,
        'aktivni_efekt_id' => $vysledek['aktivni_efekt_id'],
        'efekt_id' => $efektId,
        'efekt_nazev' => $vysledek['nazev'],
        'zbyva_kol' => $vysledek['zbyva_kol'],
        'obnoveno' => $vysledek['obnoveno'],
        'zbyva_pred' => $vysledek['zbyva_pred'],
        'shrnuti' => $vysledek['shrnuti'],
        'akce' => $vysledek['akce'],
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo json_encode([
    'ok' => true,
    'udalost_id' => $eventId,
    'efekt' => $vysledek,
    'posun' => $posun,
], JSON_UNESCAPED_UNICODE);
