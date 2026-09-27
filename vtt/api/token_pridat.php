<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$typEntity = ($data['typ_entity'] ?? 'postava') === 'nestvura' ? 'nestvura' : 'postava';
$x = (int)($data['x'] ?? 0);
$y = (int)($data['y'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

if ($typEntity === 'nestvura') {
    if (!$isPjOrAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Jen PJ/admin může přidat nestvůru.']);
        exit;
    }
    $nestvuraId = (int)($data['nestvura_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM nestvury WHERE id = ?');
    $stmt->execute([$nestvuraId]);
    $nestvura = $stmt->fetch();
    if (!$nestvura) {
        http_response_code(404);
        echo json_encode(['error' => 'Nestvůra nenalezena.']);
        exit;
    }
    // Životaschopnost je v katalogu volný text ("20", "20-25", "viz text"...) —
    // vezmi první číslo, PJ si HP hned poté může ručně opravit.
    $hp = dracak_vtt_prvni_cislo($nestvura['zivotaschopnost']);
    $ins = $pdo->prepare(
        'INSERT INTO nestvura_instance (mapa_id, nestvura_id, nazev_instance, aktualni_hp, max_hp) VALUES (?, ?, ?, ?, ?)'
    );
    $ins->execute([$mapaId, $nestvuraId, $nestvura['nazev'], $hp, $hp]);
    $entitaId = (int)$pdo->lastInsertId();
    $nazev = $nestvura['nazev'];
} else {
    $postavaId = (int)($data['postava_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM postavy WHERE id = ? AND svet_id = ?');
    $stmt->execute([$postavaId, $mapa['svet_id']]);
    $postava = $stmt->fetch();
    if (!$postava) {
        http_response_code(404);
        echo json_encode(['error' => 'Postava nenalezena v tomhle světě.']);
        exit;
    }
    if (!$isPjOrAdmin && (int)$postava['vlastnik_ucet_id'] !== (int)$user['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Tohle není tvoje postava.']);
        exit;
    }
    $entitaId = $postavaId;
    $nazev = $postava['nazev'];
}

$dbTypEntity = $typEntity === 'nestvura' ? 'nestvura_instance' : 'postava';
$stmt = $pdo->prepare(
    'INSERT INTO tokeny (mapa_id, typ_entity, entita_id, x, y) VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$mapaId, $dbTypEntity, $entitaId, $x, $y]);
$tokenId = (int)$pdo->lastInsertId();

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'token_pridan', [
    'token_id' => $tokenId,
    'typ_entity' => $dbTypEntity,
    'entita_id' => $entitaId,
    'nazev' => $nazev,
    'x' => $x,
    'y' => $y,
], (int)$user['id']);

echo json_encode(['ok' => true, 'token_id' => $tokenId, 'udalost_id' => $eventId, 'nazev' => $nazev]);
