<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_predmety.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může předávat kořist.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$nestvuraInstanceId = (int)($data['nestvura_instance_id'] ?? 0);
$typPolozky = (string)($data['typ_polozky'] ?? '');
$polozkaId = (int)($data['polozka_id'] ?? 0);
$cilovaPostavaId = (int)($data['cilova_postava_id'] ?? 0);
// Nepovinné — když chybí, předá se celý aktuální kus/hromádka (typické
// chování tlačítka "Předat" u jedné položky výbavy).
$pozadovaneMnozstvi = !empty($data['mnozstvi']) ? max(1, (int)$data['mnozstvi']) : null;

$cfg = dracak_vtt_polozka_config($typPolozky);
if (!$cfg) {
    http_response_code(422);
    echo json_encode(['error' => 'Neplatný typ položky.']);
    exit;
}

$pdo = dracak_db();

$nestvura = dracak_vtt_entity_row('nestvura_instance', $nestvuraInstanceId);
if (!$nestvura) {
    http_response_code(404);
    echo json_encode(['error' => 'Nestvůra nenalezena.']);
    exit;
}
$svetMapa = dracak_vtt_entity_svet_mapa('nestvura_instance', $nestvura);
if ($svetMapa['svet_id'] === 0 || !dracak_vtt_svet_access($user, $svetMapa['svet_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Bez přístupu.']);
    exit;
}
// Server-side, nevěřit klientovi: kořist jde vzít, jen když je nestvůra
// mrtvá (viz "What's already decided" — dead monster stays as lootable
// token, PJ mediuje předání).
if ((int)$nestvura['aktualni_hp'] > 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Nestvůra ještě žije, kořistit nejde.']);
    exit;
}

$postava = dracak_vtt_entity_row('postava', $cilovaPostavaId);
if (!$postava) {
    http_response_code(404);
    echo json_encode(['error' => 'Cílová postava nenalezena.']);
    exit;
}
if ((int)$postava['svet_id'] !== $svetMapa['svet_id']) {
    http_response_code(404);
    echo json_encode(['error' => 'Postava není ve stejném světě jako nestvůra.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT * FROM nestvura_instance_vybava WHERE nestvura_instance_id = ? AND typ_polozky = ? AND polozka_id = ? ORDER BY id LIMIT 1'
);
$stmt->execute([$nestvuraInstanceId, $typPolozky, $polozkaId]);
$vybavaRow = $stmt->fetch();
if (!$vybavaRow || (int)$vybavaRow['mnozstvi'] < 1) {
    http_response_code(404);
    echo json_encode(['error' => 'Tuhle položku nestvůra u sebe nemá.']);
    exit;
}

$katalog = dracak_vtt_polozka_katalog($typPolozky, $polozkaId);
if (!$katalog) {
    http_response_code(404);
    echo json_encode(['error' => 'Položka nenalezena v katalogu.']);
    exit;
}

// Znalost kouzla se nepočítá na kusy — buď ji postava zná, nebo ne.
$prevod = $typPolozky === 'kouzlo' ? 1 : min($pozadovaneMnozstvi ?? (int)$vybavaRow['mnozstvi'], (int)$vybavaRow['mnozstvi']);

$pdo->beginTransaction();
try {
    if ($typPolozky === 'kouzlo') {
        $del = $pdo->prepare('DELETE FROM nestvura_instance_vybava WHERE id = ?');
        $del->execute([(int)$vybavaRow['id']]);
        $ins = $pdo->prepare('INSERT IGNORE INTO postava_zna_kouzlo (postava_id, kouzlo_id) VALUES (?, ?)');
        $ins->execute([$cilovaPostavaId, $polozkaId]);
    } else {
        if ($prevod >= (int)$vybavaRow['mnozstvi']) {
            $del = $pdo->prepare('DELETE FROM nestvura_instance_vybava WHERE id = ?');
            $del->execute([(int)$vybavaRow['id']]);
        } else {
            $upd = $pdo->prepare('UPDATE nestvura_instance_vybava SET mnozstvi = mnozstvi - ? WHERE id = ?');
            $upd->execute([$prevod, (int)$vybavaRow['id']]);
        }
        $ins = $pdo->prepare(
            "INSERT INTO {$cfg['inventar']} (postava_id, {$cfg['fk']}, mnozstvi) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE mnozstvi = mnozstvi + VALUES(mnozstvi)"
        );
        $ins->execute([$cilovaPostavaId, $polozkaId, $prevod]);
    }

    $eventId = dracak_vtt_log_event($svetMapa['svet_id'], $svetMapa['mapa_id'], 'predmet_loot', [
        'nestvura_instance_id' => $nestvuraInstanceId,
        'typ_polozky' => $typPolozky,
        'polozka_id' => $polozkaId,
        'polozka_nazev' => $katalog['nazev'],
        'mnozstvi' => $prevod,
        'cilova_postava_id' => $cilovaPostavaId,
        'cilova_postava_nazev' => $postava['nazev'],
        'provedl' => $user['jmeno'],
    ], (int)$user['id']);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    dracak_fail_safely('Předání kořisti selhalo: ' . $e->getMessage());
}

echo json_encode([
    'ok' => true,
    'udalost_id' => $eventId,
    'polozka_nazev' => $katalog['nazev'],
    'mnozstvi' => $prevod,
], JSON_UNESCAPED_UNICODE);
