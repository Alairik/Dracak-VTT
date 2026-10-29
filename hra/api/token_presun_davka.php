<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_tokeny.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';

// Přesun skupiny tokenů najednou (výběr více tokenů, tah společně).
// Platí stejná pravidla jako u jednoho tokenu (includes/vtt_tokeny.php) —
// stačí, aby jeden token nesměl, a nepohne se žádný. Zapíše se JEDNA
// událost tokeny_presun, takže "Krok zpět" vrátí celou skupinu naráz.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

if (!dracak_vtt_tokeny_funkce()['udalosti']) {
    http_response_code(409);
    echo json_encode(['error' => 'Společný pohyb se zapne po jednorázovém kroku správce (ruční SQL).']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$pozadavky = is_array($data['tokeny'] ?? null) ? $data['tokeny'] : [];
if (!$pozadavky || count($pozadavky) > 100) {
    http_response_code(422);
    echo json_encode(['error' => 'Vyber 1 až 100 tokenů.']);
    exit;
}

$pdo = dracak_db();
$stmt = $pdo->prepare(
    'SELECT t.*, m.svet_id, m.sirka_px, m.vyska_px, m.grid_velikost_px, m.grid_typ, m.grid_posun_x, m.grid_posun_y
     FROM tokeny t JOIN mapy m ON m.id = t.mapa_id WHERE t.id = ?'
);
$presuny = [];
$mapaId = null;
$svetId = null;
foreach ($pozadavky as $p) {
    $tokenId = (int)($p['token_id'] ?? 0);
    $x = dracak_vtt_souradnice($p['x'] ?? 0);
    $y = dracak_vtt_souradnice($p['y'] ?? 0);
    $stmt->execute([$tokenId]);
    $token = $stmt->fetch();
    if (!$token || !dracak_vtt_svet_access($user, (int)$token['svet_id'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Token nenalezen.']);
        exit;
    }
    if ($mapaId !== null && (int)$token['mapa_id'] !== $mapaId) {
        http_response_code(422);
        echo json_encode(['error' => 'Všechny tokeny musí být na stejné mapě.']);
        exit;
    }
    $mapaId = (int)$token['mapa_id'];
    $svetId = (int)$token['svet_id'];
    $chyba = dracak_vtt_token_kontrola_presunu($pdo, $user, $token, $x, $y);
    if ($chyba !== null) {
        http_response_code($chyba[0]);
        echo json_encode(['error' => $chyba[1] . ' (' . ($token['nazev'] ?? ('token ' . $tokenId)) . ')']);
        exit;
    }
    $presuny[$tokenId] = ['token' => $token, 'x' => $x, 'y' => $y];
}

$pdo->beginTransaction();
try {
    $upd = $pdo->prepare('UPDATE tokeny SET x = ?, y = ? WHERE id = ?');
    $zaznam = [];
    foreach ($presuny as $tokenId => $p) {
        $upd->execute([$p['x'], $p['y'], $tokenId]);
        $zaznam[] = [
            'token_id' => $tokenId,
            'x_pred' => (int)$p['token']['x'],
            'y_pred' => (int)$p['token']['y'],
            'x_po' => $p['x'],
            'y_po' => $p['y'],
        ];
    }
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'tokeny_presun', ['presuny' => $zaznam], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

foreach ($presuny as $p) {
    dracak_vtt_token_odhal_mlhu($pdo, $p['token'], $p['x'], $p['y']);
}

echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'pocet' => count($presuny)]);
