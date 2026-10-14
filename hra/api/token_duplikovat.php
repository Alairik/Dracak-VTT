<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_tokeny.php';

// Zkopíruje token na nové místo (jen PJ/admin): nestvůra se stejnými
// statistikami a plnými životy (a stejnou výbavou), vlastní token se stejným
// vzhledem. Postavu duplikovat nejde — jedna postava má na mapě jeden token.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$tokenId = (int)($data['token_id'] ?? 0);
$x = (int)($data['x'] ?? 0);
$y = (int)($data['y'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT t.*, m.svet_id FROM tokeny t JOIN mapy m ON m.id = t.mapa_id WHERE t.id = ?');
$stmt->execute([$tokenId]);
$token = $stmt->fetch();
if (!$token || !dracak_vtt_svet_access($user, (int)$token['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Token nenalezen.']);
    exit;
}
$svetId = (int)$token['svet_id'];
$mapaId = (int)$token['mapa_id'];
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může tokeny kopírovat.']);
    exit;
}
if ($token['typ_entity'] === 'postava') {
    http_response_code(422);
    echo json_encode(['error' => 'Postavu zkopírovat nejde — jedna postava má na mapě jeden token.']);
    exit;
}

$pdo->beginTransaction();
try {
    $novyNazevTokenu = null;
    if ($token['typ_entity'] === 'nestvura_instance') {
        $q = $pdo->prepare('SELECT ni.*, n.nazev AS druh FROM nestvura_instance ni JOIN nestvury n ON n.id = ni.nestvura_id WHERE ni.id = ?');
        $q->execute([(int)$token['entita_id']]);
        $inst = $q->fetch();
        if (!$inst) {
            throw new RuntimeException('Nestvůra tokenu už neexistuje.');
        }
        $nazev = dracak_vtt_dalsi_nazev_instance($pdo, $mapaId, (int)$inst['nestvura_id'], (string)$inst['druh']);
        $pdo->prepare('INSERT INTO nestvura_instance (mapa_id, nestvura_id, nazev_instance, aktualni_hp, max_hp) VALUES (?, ?, ?, ?, ?)')
            ->execute([$mapaId, (int)$inst['nestvura_id'], $nazev, (int)$inst['max_hp'], (int)$inst['max_hp']]);
        $entitaId = (int)$pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO nestvura_instance_vybava (nestvura_instance_id, typ_polozky, polozka_id, mnozstvi)
             SELECT ?, typ_polozky, polozka_id, mnozstvi FROM nestvura_instance_vybava WHERE nestvura_instance_id = ?'
        )->execute([$entitaId, (int)$token['entita_id']]);
    } else {
        $entitaId = 0;
        if (!empty($token['nazev'])) {
            $q = $pdo->prepare("SELECT nazev FROM tokeny WHERE mapa_id = ? AND typ_entity = 'obecny' AND nazev IS NOT NULL");
            $q->execute([$mapaId]);
            // základ bez případného čísla na konci ("Sud 2" → "Sud")
            $zaklad = preg_replace('/ \d+$/u', '', (string)$token['nazev']);
            $novyNazevTokenu = dracak_vtt_dalsi_nazev($q->fetchAll(PDO::FETCH_COLUMN), $zaklad);
        }
        $nazev = $novyNazevTokenu ?? 'Token';
    }

    // Nový řádek = kopie starého bez id/času a s novou polohou; sloupce, které
    // v databázi ještě nejsou (před ručním SQL), se prostě nekopírují.
    $kopie = $token;
    unset($kopie['id'], $kopie['vytvoreno'], $kopie['svet_id']);
    $kopie['entita_id'] = $entitaId;
    $kopie['x'] = $x;
    $kopie['y'] = $y;
    $kopie['z_poradi'] = (int)$token['z_poradi'] + 1;
    if (array_key_exists('zamceno', $kopie)) {
        $kopie['zamceno'] = 0;
    }
    if ($novyNazevTokenu !== null && array_key_exists('nazev', $kopie)) {
        $kopie['nazev'] = $novyNazevTokenu;
    }
    $sloupce = array_keys($kopie);
    $pdo->prepare(
        'INSERT INTO tokeny (`' . implode('`, `', $sloupce) . '`) VALUES (' . implode(', ', array_fill(0, count($sloupce), '?')) . ')'
    )->execute(array_values($kopie));
    $novyId = (int)$pdo->lastInsertId();

    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'token_pridan', [
        'token_id' => $novyId,
        'typ_entity' => $token['typ_entity'],
        'entita_id' => $entitaId,
        'nazev' => $nazev,
        'x' => $x,
        'y' => $y,
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Token se nepodařilo zkopírovat.']);
    exit;
}

echo json_encode(['ok' => true, 'token_id' => $novyId, 'udalost_id' => $eventId, 'nazev' => $nazev], JSON_UNESCAPED_UNICODE);
