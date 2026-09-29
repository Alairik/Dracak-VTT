<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_iniciativa.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$tokenId = (int)($data['token_id'] ?? 0);
$modifikator = (int)($data['modifikator'] ?? 0);
// TINYINT sloupec (-128..127) — jen ochrana proti přetečení sloupce, NE
// pravidlová hranice (ta chybí, viz includes/vtt_iniciativa.php nahoře).
$modifikator = max(-100, min(100, $modifikator));

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT t.*, m.svet_id FROM tokeny t JOIN mapy m ON m.id = t.mapa_id WHERE t.id = ? AND t.mapa_id = ?');
$stmt->execute([$tokenId, $mapaId]);
$token = $stmt->fetch();
if (!$token || !dracak_vtt_svet_access($user, (int)$token['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Token nenalezen.']);
    exit;
}
if (!dracak_vtt_can_roll_iniciativa($user, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Iniciativu za tenhle token hodit nesmíš.']);
    exit;
}

$hozeno = dracak_vtt_hod_kostkou(1, 6, 0);
$hod = $hozeno['hody'][0];
$vysledek = $hod + $modifikator;
$akce = dracak_vtt_iniciativa_akce($vysledek);

$pdo->beginTransaction();
try {
    // Založí kolo_stav pro mapu, pokud ještě neexistuje — první hod na
    // téhle mapě zakládá kolo 1, aktivní token zatím žádný (nastaví ho
    // až první zavolání dalsi_tah.php). Když už řádek existuje (ať jsme
    // uprostřed kola, nebo se právě přehazuje nové kolo po
    // dalsi_tah.php smazání kolo_iniciativa), INSERT IGNORE ho nechá
    // beze změny.
    $pdo->prepare('INSERT IGNORE INTO kolo_stav (mapa_id) VALUES (?)')->execute([$mapaId]);

    // Upsert: jeden řádek na (mapa, token) = aktuální kolo (h1622 — hází
    // se na začátku KAŽDÉHO kola, ne jednou za soubor). Nový hod přepíše
    // starý bez ohledu na to, kolik akcí zbývalo — viz report, "reroll
    // mid-round" je zatím vědomě neošetřená mezera, enforcement je mimo
    // rozsah téhle dávky.
    $upsert = $pdo->prepare(
        'INSERT INTO kolo_iniciativa (mapa_id, token_id, hod, modifikator, vysledek, akce_celkem, akce_zbyvajici)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE hod = VALUES(hod), modifikator = VALUES(modifikator),
             vysledek = VALUES(vysledek), akce_celkem = VALUES(akce_celkem), akce_zbyvajici = VALUES(akce_zbyvajici)'
    );
    $upsert->execute([$mapaId, $tokenId, $hod, $modifikator, $vysledek, $akce, $akce]);

    $eventId = dracak_vtt_log_event((int)$token['svet_id'], $mapaId, 'iniciativa_hozena', [
        'token_id' => $tokenId,
        'hod' => $hod,
        'modifikator' => $modifikator,
        'vysledek' => $vysledek,
        'akce_celkem' => $akce,
        'akce_zbyvajici' => $akce,
        'hodil' => $user['jmeno'],
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo json_encode([
    'ok' => true,
    'udalost_id' => $eventId,
    'token_id' => $tokenId,
    'hod' => $hod,
    'modifikator' => $modifikator,
    'vysledek' => $vysledek,
    'akce_celkem' => $akce,
    'akce_zbyvajici' => $akce,
], JSON_UNESCAPED_UNICODE);
