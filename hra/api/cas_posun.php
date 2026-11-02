<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_cas.php';

// Čas ve světě (jen PJ/admin; includes/vtt_cas.php, migrace 0109).
// Tělo: {svet_id, smeny: 1–1440}  — posune čas dopředu o N směn (1 směna ≈ 10 minut) a odečte hoření světel; zhasnutá světla přijdou jako události token_zmena,
//       {svet_id, nastav_smenu: 0–143} — nastaví čas ve dni přesně (oprava, nic nehoří).
// Událost „cas_zmena“ (bez mapy) — klienti si přepíšou hodiny v liště.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$svetId = (int)($data['svet_id'] ?? 0);
$pdo = dracak_db();
$existuje = $pdo->prepare('SELECT 1 FROM svet WHERE id = ?');
$existuje->execute([$svetId]);
if ($svetId <= 0 || !$existuje->fetchColumn() || !dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Svět nenalezen.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Čas ve světě posouvá jen PJ/admin.']);
    exit;
}
if (empty(dracak_vtt_tokeny_funkce()['hodiny'])) {
    http_response_code(409);
    echo json_encode(['error' => 'Hodiny světa ještě nejsou zapnuté (čeká se na databázi).']);
    exit;
}

if (isset($data['nastav_smenu'])) {
    $smena = filter_var($data['nastav_smenu'], FILTER_VALIDATE_INT);
    if ($smena === false || $smena < 0 || $smena >= DRACAK_VTT_SMEN_ZA_DEN) {
        http_response_code(422);
        echo json_encode(['error' => 'Čas ve dni je směna 0 až ' . (DRACAK_VTT_SMEN_ZA_DEN - 1) . ' (1 směna = 10 minut).']);
        exit;
    }
    $vysledek = dracak_vtt_cas_nastav($pdo, $svetId, $smena, (int)$user['id']);
} else {
    $smeny = filter_var($data['smeny'] ?? null, FILTER_VALIDATE_INT);
    if ($smeny === false || $smeny < 1 || $smeny > 1440) {
        http_response_code(422);
        echo json_encode(['error' => 'Čas se posouvá o 1 až 1440 směn (1 směna = 10 minut).']);
        exit;
    }
    $vysledek = dracak_vtt_cas_posun($pdo, $svetId, $smeny, (int)$user['id']);
}
if ($vysledek === null) {
    http_response_code(409);
    echo json_encode(['error' => 'Čas se nepodařilo změnit.']);
    exit;
}
echo json_encode(['ok' => true] + $vysledek, JSON_UNESCAPED_UNICODE);
