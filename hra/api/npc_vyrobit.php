<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_npc.php';

// Vyrobí NPC do zásoby světa (dávkou, bez položení na mapu): „10× Goblin“, volitelně „každý jedinečný“
// (vlastní čísla pro boj). Jen PJ/admin.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$svetId = (int)($data['svet_id'] ?? 0);
$nestvuraId = (int)($data['nestvura_id'] ?? 0);
$pocet = (int)($data['pocet'] ?? 1);
$jedinecny = !empty($data['jedinecny']);
// Místo, kam NPC patří: mapa ve stejném světě, nebo nic = bez místa (zásoba pro budoucí mapy).
$lokaceMapaId = (int)($data['lokace_mapa_id'] ?? 0) > 0 ? (int)$data['lokace_mapa_id'] : null;

if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Svět nenalezen.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může vyrábět NPC.']);
    exit;
}
if (!dracak_vtt_npc_funkce()) {
    http_response_code(409);
    echo json_encode(['error' => 'Zásoba NPC se zapne po jednorázovém kroku správce (ruční SQL).']);
    exit;
}
if ($pocet < 1 || $pocet > DRACAK_VTT_NPC_DAVKA_MAX) {
    http_response_code(422);
    echo json_encode(['error' => 'Počet kusů musí být 1 až ' . DRACAK_VTT_NPC_DAVKA_MAX . '.']);
    exit;
}
$pdo = dracak_db();
if ($lokaceMapaId !== null) {
    $q = $pdo->prepare('SELECT svet_id FROM mapy WHERE id = ?');
    $q->execute([$lokaceMapaId]);
    if ((int)$q->fetchColumn() !== $svetId) {
        http_response_code(404);
        echo json_encode(['error' => 'Místo (mapa) v tomhle světě není.']);
        exit;
    }
}
$q = $pdo->prepare('SELECT id, nazev, zivotaschopnost, velikost FROM nestvury WHERE id = ?');
$q->execute([$nestvuraId]);
$nestvura = $q->fetch(PDO::FETCH_ASSOC);
if (!$nestvura) {
    http_response_code(404);
    echo json_encode(['error' => 'Nestvůra nenalezena.']);
    exit;
}

$pdo->beginTransaction();
try {
    $vyrobene = dracak_vtt_npc_vyrobit($pdo, $svetId, $nestvura, $pocet, $jedinecny, $lokaceMapaId);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo json_encode(['ok' => true, 'vyrobeno' => count($vyrobene), 'npc' => $vyrobene], JSON_UNESCAPED_UNICODE);
