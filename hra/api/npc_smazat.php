<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_npc.php';

// Smaže NPC ze zásoby nadobro (jen to, co nestojí na mapě — token se sundává jinak). Jen PJ/admin.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$instanceId = (int)($data['instance_id'] ?? 0);

if (!dracak_vtt_npc_funkce()) {
    http_response_code(409);
    echo json_encode(['error' => 'Zásoba NPC se zapne po jednorázovém kroku správce (ruční SQL).']);
    exit;
}
$pdo = dracak_db();
$q = $pdo->prepare(
    'SELECT ni.id, ni.svet_id, ni.mapa_id, (SELECT COUNT(*) FROM tokeny t WHERE t.typ_entity = "nestvura_instance" AND t.entita_id = ni.id) AS tokenu
     FROM nestvura_instance ni WHERE ni.id = ?'
);
$q->execute([$instanceId]);
$npc = $q->fetch(PDO::FETCH_ASSOC);
if (!$npc || !$npc['svet_id'] || !dracak_vtt_svet_access($user, (int)$npc['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'NPC nenalezeno.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$npc['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může mazat NPC.']);
    exit;
}
if ($npc['mapa_id'] !== null || (int)$npc['tokenu'] > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Tohle NPC stojí na mapě — nejdřív sundej jeho token.']);
    exit;
}
$pdo->prepare('DELETE FROM nestvura_instance WHERE id = ?')->execute([$instanceId]);
echo json_encode(['ok' => true]);
