<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

// "Cestovat sem" u pinu (viz konverzace) — PJ zadá ad-hoc, kolik dní
// cesta na tohle místo zabrala, rovnou se posune svet.aktualni_den_offset.
// Žádná uložená vzdálenost mezi piny (to by byla zbytečná N² tabulka) —
// PJ to pokaždé odhadne podle situace v daný moment.
//
// Nezaloguje se jako svet_udalosti — ten typ sloupec je pevný ENUM a
// nový typ jen kvůli tomuhle by byla další ruční ALTER migrace navíc,
// za tak malou věc to nestojí (viz CLAUDE.md o tom držet migrace malé).
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$bodId = (int)($data['bod_id'] ?? 0);
$dny = (int)($data['dny'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT b.id, m.svet_id FROM mapa_body b JOIN mapy m ON m.id = b.mapa_id WHERE b.id = ?');
$stmt->execute([$bodId]);
$radek = $stmt->fetch();
if (!$radek || !dracak_vtt_svet_access($user, (int)$radek['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Pin nenalezen.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$radek['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může posunout čas cestováním.']);
    exit;
}
if ($dny < 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Počet dní nemůže být záporný.']);
    exit;
}

$svetId = (int)$radek['svet_id'];
$stmt = $pdo->prepare('UPDATE svet SET aktualni_den_offset = aktualni_den_offset + ? WHERE id = ?');
$stmt->execute([$dny, $svetId]);

$stmt = $pdo->prepare('SELECT aktualni_den_offset FROM svet WHERE id = ?');
$stmt->execute([$svetId]);

echo json_encode(['ok' => true, 'novy_offset' => (int)$stmt->fetchColumn()]);
