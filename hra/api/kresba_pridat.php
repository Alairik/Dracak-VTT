<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';
require_once __DIR__ . '/../../includes/vtt_kresby.php';

// Přidá kresbu na mapu. Smí kdokoli ze světa; skrytou kresbu (jen pro PJ)
// smí založit jen PJ/admin.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
if (!dracak_vtt_tokeny_funkce()['kresby']) {
    http_response_code(409);
    echo json_encode(['error' => 'Kreslení se zapne po jednorázovém kroku správce (ruční SQL).']);
    exit;
}
$svetId = (int)$mapa['svet_id'];
$jePj = dracak_vtt_je_pj_sveta($user, $svetId);

$over = dracak_vtt_kresba_over($data);
if (isset($over['chyba'])) {
    http_response_code(422);
    echo json_encode(['error' => $over['chyba']]);
    exit;
}
$viditelne = $jePj ? !empty($data['viditelne_hracum']) || !array_key_exists('viditelne_hracum', $data) : true;

$q = $pdo->prepare('SELECT COUNT(*) FROM mapa_kresby WHERE mapa_id = ?');
$q->execute([$mapaId]);
if ((int)$q->fetchColumn() >= DRACAK_VTT_KRESBY_NA_MAPU) {
    http_response_code(422);
    echo json_encode(['error' => 'Na mapě je moc kreseb (' . DRACAK_VTT_KRESBY_NA_MAPU . '). Smaž staré.']);
    exit;
}

$pdo->prepare('INSERT INTO mapa_kresby (mapa_id, ucet_id, typ, data, barva, sirka, viditelne_hracum) VALUES (?, ?, ?, ?, ?, ?, ?)')
    ->execute([$mapaId, (int)$user['id'], $over['typ'], json_encode($over['data'], JSON_UNESCAPED_UNICODE), $over['barva'], $over['sirka'], $viditelne ? 1 : 0]);
$id = (int)$pdo->lastInsertId();

$kresba = [
    'id' => $id, 'typ' => $over['typ'], 'b' => $over['data']['b'], 't' => $over['data']['t'] ?? null,
    'barva' => $over['barva'], 'sirka' => $over['sirka'], 'viditelne_hracum' => $viditelne,
];
$udalostId = dracak_vtt_log_event($svetId, $mapaId, 'kresba_pridana', $kresba + ['ucet_id' => (int)$user['id']], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $udalostId, 'kresba' => $kresba + ['moje' => true, 'smi_smazat' => true]], JSON_UNESCAPED_UNICODE);
