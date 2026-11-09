<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_npc.php';

// Naváže NPC na místo (lokaci), nebo ho z místa uvolní do obecné zásoby. Jen PJ/admin.
//   POST {instance_id, mapa_id}  -> naváže na tuhle mapu (musí být ve stejném světě)
//   POST {instance_id}           -> uvolní (NPC je pak bez místa, zásoba pro budoucí mapy)
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$instanceId = (int)($data['instance_id'] ?? 0);
$mapaId = (int)($data['mapa_id'] ?? 0) > 0 ? (int)$data['mapa_id'] : null;

if (!dracak_vtt_npc_funkce()) {
    http_response_code(409);
    echo json_encode(['error' => 'Zásoba NPC se zapne po jednorázovém kroku správce (ruční SQL).']);
    exit;
}
$pdo = dracak_db();
$q = $pdo->prepare('SELECT id, svet_id FROM nestvura_instance WHERE id = ?');
$q->execute([$instanceId]);
$npc = $q->fetch(PDO::FETCH_ASSOC);
if (!$npc || !$npc['svet_id'] || !dracak_vtt_svet_access($user, (int)$npc['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'NPC nenalezeno.']);
    exit;
}
$svetId = (int)$npc['svet_id'];
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Místo NPC mění jen PJ.']);
    exit;
}
if ($mapaId !== null) {
    $q = $pdo->prepare('SELECT svet_id FROM mapy WHERE id = ?');
    $q->execute([$mapaId]);
    if ((int)$q->fetchColumn() !== $svetId) {
        http_response_code(404);
        echo json_encode(['error' => 'Místo (mapa) v tomhle světě není.']);
        exit;
    }
}
dracak_vtt_npc_lokace_nastav($pdo, $instanceId, $mapaId);
echo json_encode(['ok' => true, 'lokace_mapa_id' => $mapaId]);
