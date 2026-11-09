<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_npc.php';

// Zásoba NPC světa (nepoložená na žádné mapě) a naposledy použité druhy — pro lištu správy tokenů.
// Jen PJ/admin: bestiář i připravené NPC jsou tajné.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_require_login();
$svetId = (int)($_GET['svet_id'] ?? 0);
// Volitelně mapa, která je právě otevřená — NPC navázaná na tuhle lokaci (i ta, co tam stojí) se vrátí zvlášť.
$mapaId = (int)($_GET['mapa_id'] ?? 0);
if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Svět nenalezen.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Zásobu NPC vidí jen PJ.']);
    exit;
}
if (!dracak_vtt_npc_funkce()) {
    echo json_encode(['funkce' => false, 'zasoba' => [], 'naposledy' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
$pdo = dracak_db();
$lokace = [];
if ($mapaId > 0) {
    $q = $pdo->prepare('SELECT svet_id FROM mapy WHERE id = ?');
    $q->execute([$mapaId]);
    if ((int)$q->fetchColumn() === $svetId) {
        $lokace = dracak_vtt_npc_lokace($pdo, $svetId, $mapaId);
    }
}
echo json_encode([
    'funkce' => true,
    'zasoba' => dracak_vtt_npc_zasoba($pdo, $svetId),
    'naposledy' => dracak_vtt_npc_naposledy($pdo, $svetId),
    'lokace' => $lokace,
], JSON_UNESCAPED_UNICODE);
