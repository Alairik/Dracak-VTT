<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_predmety.php';
require_once __DIR__ . '/../../includes/vtt_denik.php';

// Inventář jedné entity (postava / instance nestvůry) pro panel tokenu.
// Dřív se posílal v HTML stránky pro všechny tokeny najednou — při obnově
// mapy "na místě" by to znamenalo stovky dotazů, proto se teď načítá až
// pro ten jeden token, na který někdo klikl. Smí ho vidět PJ/admin a
// vlastník postavy; cizí postavy a nestvůry hráč nevidí.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

$typEntity = (string)($_GET['typ_entity'] ?? '');
$entitaId = (int)($_GET['entita_id'] ?? 0);
if (!in_array($typEntity, ['postava', 'nestvura_instance'], true) || $entitaId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Neplatná entita.']);
    exit;
}

$pdo = dracak_db();
if ($typEntity === 'postava') {
    $stmt = $pdo->prepare('SELECT svet_id, vlastnik_ucet_id FROM postavy WHERE id = ?');
    $stmt->execute([$entitaId]);
    $r = $stmt->fetch();
    $svetId = $r ? (int)$r['svet_id'] : 0;
    $vlastnikId = $r ? (int)$r['vlastnik_ucet_id'] : 0;
} else {
    $stmt = $pdo->prepare('SELECT m.svet_id FROM nestvura_instance ni JOIN mapy m ON m.id = ni.mapa_id WHERE ni.id = ?');
    $stmt->execute([$entitaId]);
    $svetId = (int)($stmt->fetchColumn() ?: 0);
    $vlastnikId = 0;
}
if ($svetId <= 0 || !dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Entita nenalezena.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, $svetId) && $vlastnikId !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Cizí inventář nevidíš.']);
    exit;
}

$odpoved = ['polozky' => dracak_vtt_inventar($typEntity, $entitaId)];
// PJ u postavy navíc vidí, co únava a naložení právě mění (stejná sekce jako
// na stránce družiny) — hra to počítá sama, obtížnost dovedností určuje PJ.
if ($typEntity === 'postava' && dracak_vtt_je_pj_sveta($user, $svetId)) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM postavy WHERE id = ?');
        $stmt->execute([$entitaId]);
        $postava = $stmt->fetch();
        if ($postava) {
            $odpoved['pj_stav'] = dracak_vtt_stav_pro_pj(dracak_vtt_denik_data($pdo, $postava, false));
        }
    } catch (Throwable $e) {
        // výpočet stavu je jen pomůcka, inventář se vrátí i bez něj
    }
}
echo json_encode($odpoved, JSON_UNESCAPED_UNICODE);
