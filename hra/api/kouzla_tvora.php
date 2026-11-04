<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_kouzleni.php';

// Kouzla tvora pro výběr u akce kouzlení v souboji (assets/js/souboj.js): GET token_id → {kouzla: [{id, nazev, doba, akci}], pocita: bool}.
// `akci` = kolik akcí kouzlení kouzlo vyžaduje (2 × vyvolání v kolech), null = hra to z údaje „vyvolání“ nepozná (směny, „ihned“…).
// Postava: kouzla, která zná (smí PJ/admin světa a vlastník postavy). Nestvůra nemá seznam kouzel — PJ zadá počet akcí ručně.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_require_login();

$tokenId = (int)($_GET['token_id'] ?? 0);
$pdo = dracak_db();
$q = $pdo->prepare('SELECT t.typ_entity, t.entita_id, m.svet_id FROM tokeny t JOIN mapy m ON m.id = t.mapa_id WHERE t.id = ?');
$q->execute([$tokenId]);
$t = $q->fetch();
if (!$t || !dracak_vtt_svet_access($user, (int)$t['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Token nenalezen.']);
    exit;
}
$jePj = dracak_vtt_je_pj_sveta($user, (int)$t['svet_id']);
$kouzla = [];
if ($t['typ_entity'] === 'postava') {
    $q = $pdo->prepare('SELECT vlastnik_ucet_id FROM postavy WHERE id = ?');
    $q->execute([(int)$t['entita_id']]);
    $vlastnik = $q->fetchColumn();
    if (!$jePj && (int)$vlastnik !== (int)$user['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Kouzla cizí postavy vidí jen PJ.']);
        exit;
    }
    $kouzla = dracak_vtt_kouzla_postavy_pro_vyber($pdo, (int)$t['entita_id']);
}
echo json_encode(['kouzla' => $kouzla, 'pocita' => dracak_vtt_tokeny_funkce()['kouzleni_postup']], JSON_UNESCAPED_UNICODE);
