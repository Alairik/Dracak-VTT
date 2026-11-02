<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_prechod.php';

// „Vstoupit s družinou“ u pinu s cílovou mapou (jen PJ/admin; includes/vtt_prechod.php): přenese postavy družiny z mapy pinu na vstupní bod cílové mapy,
// přepne aktivní mapu světa a pošle událost aktivni_mapa_zmena s příznakem vstup — hráči se na ni přepnou. Tělo: {bod_id}.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$bodId = (int)($data['bod_id'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT b.*, m.svet_id FROM mapa_body b JOIN mapy m ON m.id = b.mapa_id WHERE b.id = ?');
$stmt->execute([$bodId]);
$bod = $stmt->fetch();
if (!$bod || !dracak_vtt_svet_access($user, (int)$bod['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Pin nenalezen.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$bod['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Družinu přenáší na jinou mapu jen PJ/admin.']);
    exit;
}
$v = dracak_vtt_prechod_vstoupit($pdo, $bod, (int)$user['id']);
if (isset($v['chyba'])) {
    http_response_code((int)$v['kod']);
    echo json_encode(['error' => $v['chyba']]);
    exit;
}
echo json_encode(['ok' => true] + $v, JSON_UNESCAPED_UNICODE);
