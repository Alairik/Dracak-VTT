<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mlha.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$tokenId = (int)($data['token_id'] ?? 0);
$noveX = (int)($data['x'] ?? 0);
$noveY = (int)($data['y'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare(
    'SELECT t.*, m.svet_id, m.sirka_px, m.vyska_px, m.grid_velikost_px, m.grid_typ, m.grid_posun_x, m.grid_posun_y
     FROM tokeny t JOIN mapy m ON m.id = t.mapa_id WHERE t.id = ?'
);
$stmt->execute([$tokenId]);
$token = $stmt->fetch();
if (!$token || !dracak_vtt_svet_access($user, (int)$token['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Token nenalezen.']);
    exit;
}
if (!dracak_vtt_can_move_token($user, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Tímhle tokenem hýbat nesmíš.']);
    exit;
}
// Pořadí tahů (viz includes/vtt.php, dracak_vtt_je_na_tahu): probíhá-li
// na téhle mapě boj (řádek v kolo_stav), smí token hýbat jen hráč,
// jehož token je zrovna aktivni_token_id — pj/admin výjimka je uvnitř
// helperu. Bez kolo_stav (boj neprobíhá) zůstává volný pohyb jako dřív.
if (!dracak_vtt_je_na_tahu($user, (int)$token['mapa_id'], $tokenId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Teď nejsi na tahu — tímhle tokenem teď hýbat nesmíš.']);
    exit;
}
// Zeď blokuje pohyb jen hráčům — PJ/admin smí token na zeď i přes ni
// položit záměrně (postava odhozená do zdi, vylézání apod.), viz
// dracak_vtt_pohyb_prochazi_zdi().
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);
if (!$isPjOrAdmin && dracak_vtt_pohyb_prochazi_zdi($pdo, (int)$token['mapa_id'], (float)$token['x'], (float)$token['y'], (float)$noveX, (float)$noveY)) {
    http_response_code(403);
    echo json_encode(['error' => 'Cesta vede přes zeď, kterou nejde projít.']);
    exit;
}

$pdo->beginTransaction();
try {
    $upd = $pdo->prepare('UPDATE tokeny SET x = ?, y = ? WHERE id = ?');
    $upd->execute([$noveX, $noveY, $tokenId]);
    // Payload nese i hodnotu PŘED přesunem — bez toho by "krok zpět"
    // (viz docs/vtt-datovy-model-navrh-v1.md) šlo jen dopředu.
    $eventId = dracak_vtt_log_event((int)$token['svet_id'], (int)$token['mapa_id'], 'token_presun', [
        'token_id' => $tokenId,
        'x_pred' => (int)$token['x'],
        'y_pred' => (int)$token['y'],
        'x_po' => $noveX,
        'y_po' => $noveY,
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

// Mlha války se odhaluje kolem VLASTNÍKA postavy (postavy.vlastnik_ucet_id),
// ne kolem toho, kdo akci provedl — PJ může posunout hráčův token za něj
// (viz dracak_vtt_can_move_token), ale odhaluje se pořád hráčova vlastní
// mlha, ne PJova. Nestvury hráčům vidění nepřidávají.
if ($token['typ_entity'] === 'postava') {
    $vlastnikStmt = $pdo->prepare('SELECT vlastnik_ucet_id FROM postavy WHERE id = ?');
    $vlastnikStmt->execute([(int)$token['entita_id']]);
    $vlastnikUcetId = $vlastnikStmt->fetchColumn();
    if ($vlastnikUcetId !== false) {
        dracak_vtt_mlha_odhal_kolem_bodu($pdo, [
            'id' => (int)$token['mapa_id'],
            'sirka_px' => $token['sirka_px'],
            'vyska_px' => $token['vyska_px'],
            'grid_velikost_px' => $token['grid_velikost_px'],
            'grid_typ' => $token['grid_typ'],
            'grid_posun_x' => $token['grid_posun_x'],
            'grid_posun_y' => $token['grid_posun_y'],
        ], (int)$vlastnikUcetId, (float)$noveX, (float)$noveY);
    }
}

echo json_encode(['ok' => true, 'udalost_id' => $eventId]);

