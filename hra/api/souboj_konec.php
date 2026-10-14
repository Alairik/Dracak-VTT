<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

// Ukončí souboj na mapě (jen PJ/admin): smaže pořadí tahů i stav kola. Bez
// aktivního souboje (kolo_stav) hráči zase volně chodí a otáčejí tokeny
// (dracak_vtt_je_na_tahu). Souboj začíná prvním hodem iniciativy.
//
// Událost je záměrně "kolo_nove" s příznakem souboj_konec — ten typ klienti
// i bez dalších úprav znají a po něm si načtou stav mapy znovu.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
$svetId = (int)$mapa['svet_id'];
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může ukončit souboj.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM kolo_stav WHERE mapa_id = ?');
$stmt->execute([$mapaId]);
$koloStav = $stmt->fetch();
if (!$koloStav) {
    http_response_code(422);
    echo json_encode(['error' => 'Na téhle mapě žádný souboj neprobíhá.']);
    exit;
}

$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM kolo_iniciativa WHERE mapa_id = ?')->execute([$mapaId]);
    $pdo->prepare('DELETE FROM kolo_stav WHERE mapa_id = ?')->execute([$mapaId]);
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'kolo_nove', [
        'souboj_konec' => true,
        'predchozi_kolo' => (int)$koloStav['cislo_kola'],
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo json_encode(['ok' => true, 'udalost_id' => $eventId]);
