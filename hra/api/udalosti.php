<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

$svetId = (int)($_GET['svet_id'] ?? 0);
$od = (int)($_GET['od'] ?? 0);
if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Bez přístupu.']);
    exit;
}

$stmt = dracak_db()->prepare(
    'SELECT id, mapa_id, typ, payload, ucet_id, vytvoreno FROM svet_udalosti
     WHERE svet_id = ? AND id > ? ORDER BY id LIMIT 200'
);
$stmt->execute([$svetId, $od]);
$udalosti = $stmt->fetchAll();
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);
foreach ($udalosti as &$u) {
    $u['payload'] = json_decode((string)$u['payload'], true);
}
unset($u);
// Skrytá zeď (viditelna_hracum = false) je tajemství PJ — i přes polling
// smí dojít jen PJ/adminovi, jinak by unikla hráčům obcházející filtr,
// který jim mapa.php jinak aplikuje na počáteční SELECT.
if (!$isPjOrAdmin) {
    $udalosti = array_values(array_filter($udalosti, function (array $u): bool {
        if ($u['typ'] !== 'zed_pridana') {
            return true;
        }
        return !empty($u['payload']['viditelna_hracum']);
    }));
}

echo json_encode(['udalosti' => $udalosti], JSON_UNESCAPED_UNICODE);

