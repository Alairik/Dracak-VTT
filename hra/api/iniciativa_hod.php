<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_iniciativa.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$tokenId = (int)($data['token_id'] ?? 0);
$bonusIds = array_map('intval', is_array($data['bonus_ids'] ?? null) ? $data['bonus_ids'] : []);
$jinyBonus = (int)($data['jiny_bonus'] ?? 0);

$pdo = dracak_db();

// Modifikátor = součet vybraných položek z Tabulky bonusů a postihů k
// iniciativě (str. 78, database/migrations/0045_iniciativa_bonusy_tabulka.sql)
// + volitelná ruční úprava pro cokoliv, co tabulka nepokrývá. Popisy se
// vrací i do logu, ať je vidět PROČ vyšlo dané číslo, ne jen výsledné číslo.
$vybraneBonusy = [];
if ($bonusIds) {
    $placeholders = implode(',', array_fill(0, count($bonusIds), '?'));
    $stmt = $pdo->prepare("SELECT id, popis, bonus FROM iniciativa_bonusy WHERE id IN ($placeholders)");
    $stmt->execute($bonusIds);
    $vybraneBonusy = $stmt->fetchAll();
}
$modifikator = array_sum(array_column($vybraneBonusy, 'bonus')) + $jinyBonus;
// TINYINT sloupec (-128..127) — jen ochrana proti přetečení sloupce, NE
// pravidlová hranice (ta už je dána tabulkou bonusů výš).
$modifikator = max(-100, min(100, $modifikator));

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
        'bonusy' => array_column($vybraneBonusy, 'popis'),
        'jiny_bonus' => $jinyBonus,
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
    'bonusy' => array_column($vybraneBonusy, 'popis'),
    'vysledek' => $vysledek,
    'akce_celkem' => $akce,
    'akce_zbyvajici' => $akce,
], JSON_UNESCAPED_UNICODE);
