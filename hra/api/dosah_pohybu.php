<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_pohyblivost.php';
require_once __DIR__ . '/../../includes/vtt_cestovani.php';

// Dosah pohybu postavy mimo boj (includes/vtt_pohyblivost.php, dracak_vtt_dosah_pohybu): kolik sáhů uběhne za kolo (poklus / běh / sprint) a ujde za směnu
// (procházka / chůze / spěch) při dané náročnosti terénu. GET token_id, narocnost (body na míli / na 10 sáhů, výchozí 10 = dobrá cesta).
// Smí PJ/admin světa a vlastník té postavy. Vrací i seznam povrchů z PJ pravidel (h2366) pro výběr náročnosti.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_require_login();

$tokenId = (int)($_GET['token_id'] ?? 0);
$narocnost = (float)($_GET['narocnost'] ?? 10);
if (!is_finite($narocnost) || $narocnost < 1 || $narocnost > 200) {
    http_response_code(422);
    echo json_encode(['error' => 'Náročnost terénu je od 1 do 200 bodů.']);
    exit;
}
$pdo = dracak_db();
$q = $pdo->prepare(
    'SELECT p.*, m.svet_id AS mapa_svet_id FROM tokeny t JOIN mapy m ON m.id = t.mapa_id
     JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava" WHERE t.id = ?'
);
$q->execute([$tokenId]);
$postava = $q->fetch();
if (!$postava || !dracak_vtt_svet_access($user, (int)$postava['mapa_svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Postava nenalezena.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, (int)$postava['mapa_svet_id']) && (int)$postava['vlastnik_ucet_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Dosah pohybu cizí postavy vidí jen PJ.']);
    exit;
}
$vysledek = dracak_vtt_dosah_pohybu($pdo, $postava, $narocnost);
$povrchy = array_map(static fn($p) => ['nazev' => $p[0], 'narocnost' => (int)round(($p[1] + $p[2]) / 2), 'od' => $p[1], 'do' => $p[2]], DRACAK_VTT_POVRCHY);
echo json_encode(['ok' => !isset($vysledek['chyba']), 'povrchy' => $povrchy] + $vysledek, JSON_UNESCAPED_UNICODE);
