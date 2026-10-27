<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_denik.php';
require_once __DIR__ . '/../../includes/vtt_souboj_akce.php';

// Vytasení / výměna zbraně z Osobního deníku (hra/denik.php). Mimo boj se zbraň mění zdarma; v souboji je to úkon kola
// (h1606, b10619), a tak se tady zavolá obyčejná akce tahu `vytasit` (includes/vtt_souboj_akce.php) — odečte akci,
// zapíše se do logu a tah jde dál stejně jako u každé jiné akce. Postava musí být na tahu a zbraň mít u sebe;
// PJ může zbraň změnit i bez akce (`bez_akce`), třeba při překvapení nebo opravě omylu.
//
// Vstup JSON: {postava_id, zbran_id (0 = prázdné ruce), bez_akce?: bool (jen PJ)}
// Výstup: {ok, zdarma: bool, zbran: ?string, akci_zbyva?: int}
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

function odpoved_vytasit(int $kod, array $data): void
{
    http_response_code($kod);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$postavaId = (int)($data['postava_id'] ?? 0);
$zbranId = max(0, (int)($data['zbran_id'] ?? 0));

$pdo = dracak_db();
$postava = dracak_vtt_entity_row('postava', $postavaId);
if (!$postava) {
    odpoved_vytasit(404, ['error' => 'Postava nenalezena.']);
}
if (!dracak_vtt_can_edit_hp($user, 'postava', $postava)) {
    odpoved_vytasit(403, ['error' => 'Tuhle postavu upravovat nesmíš.']);
}
$svetId = (int)($postava['svet_id'] ?? 0);
$jePj = $user['role'] === 'admin' || ($svetId > 0 && dracak_vtt_je_pj_sveta($user, $svetId));

$boj = dracak_vtt_zbrane_boj_kontext($pdo, $postava);
if ($boj !== null && !($jePj && !empty($data['bez_akce']))) {
    // V boji: akce tahu `vytasit` (kontroluje tah, zbývající akce, ovládání tokenu i „zbraň je u sebe“).
    if (!$boj['na_tahu']) {
        odpoved_vytasit(422, ['error' => 'V boji zbraň vytasíš na svém tahu — stojí akci (nebo ji PJ změní bez akce).']);
    }
    if ($boj['akci_zbyva'] < 1) {
        odpoved_vytasit(422, ['error' => 'V tomhle tahu už nemáš akci, kterou bys na vytasení zbraně měl.']);
    }
    [$mapa, , $jePjMapy, $uzivatel] = dracak_vtt_souboj_zacatek($boj['mapa_id']);
    $vysledek = dracak_vtt_souboj_proved_akci($pdo, $mapa, $jePjMapy, $uzivatel, [
        'mapa_id' => $boj['mapa_id'], 'token_id' => $boj['token_id'], 'druh' => 'vytasit', 'zbran_id' => $zbranId,
    ]);
    if ($vysledek['po_commitu'] !== null) {
        ($vysledek['po_commitu'])();
    }
    $po = dracak_vtt_zbrane_boj_kontext($pdo, dracak_vtt_entity_row('postava', $postavaId) ?: $postava);
    echo json_encode(['ok' => true, 'zdarma' => false, 'akci_zbyva' => $po['akci_zbyva'] ?? 0, 'posun' => $vysledek['odpoved']['posun'] ?? null], JSON_UNESCAPED_UNICODE);
    exit;
}

// Mimo boj (nebo PJ bez akce): změna zdarma.
try {
    $pdo->beginTransaction();
    $nazev = dracak_vtt_zbrane_vytasit($pdo, $postava, $zbranId, false);
    $pdo->commit();
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    odpoved_vytasit(422, ['error' => $e->getMessage()]);
}
odpoved_vytasit(200, ['ok' => true, 'zdarma' => true, 'zbran' => $nazev]);
