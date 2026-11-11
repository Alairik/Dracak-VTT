<?php
declare(strict_types=1);

// „Hoď si“ — PJ vyzve hráče, aby si hodil kostkou (zadání uživatele 2026-11-11, návrh na plátně
// https://claude.ai/artifact/RZz5NCY8rSRKjty6fm7btz, deska „Hoď si — co vidí PJ“).
//
// Jak to funguje: výzva je jen událost ve světě. Hráčův prohlížeč ji při pollu uvidí, pozná, že míří na jeho
// postavu, a otevře malé okno s kostkou. Odpovědí je obyčejný hod (hra/api/kostka_hod.php) s odkazem na
// výzvu, takže se nic nového do databáze neukládá a výzva nemůže „uvíznout“ — po hodu prostě zanikne.
//
// Vstup: {mapa_id, token_id, kostka: 6|10|100|null}
//   kostka = null znamená „hoď si, čím chceš“ — hráč si kostku vybere sám (rozhodnutí uživatele:
//   buď je to příkaz na konkrétní kostku, nebo volba hráče; nic mezi tím).
// Právo: jen PJ světa. Hráč si hod nevyžaduje.

require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$tokenId = (int)($data['token_id'] ?? 0);
$kostka = isset($data['kostka']) && $data['kostka'] !== null && $data['kostka'] !== ''
    ? (int)$data['kostka']
    : null;

function hod_vyzva_chyba(int $kod, string $text): void
{
    http_response_code($kod);
    echo json_encode(['error' => $text]);
    exit;
}

if ($kostka !== null && !in_array($kostka, DRACAK_VTT_KOSTKY_VYZVY, true)) {
    hod_vyzva_chyba(422, 'Takovou kostkou se vyzvat nedá.');
}

$pdo = dracak_db();
$q = $pdo->prepare('SELECT m.id, m.svet_id FROM mapy m WHERE m.id = ?');
$q->execute([$mapaId]);
$mapa = $q->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    hod_vyzva_chyba(404, 'Mapa nenalezena.');
}
if (!dracak_vtt_je_pj_sveta($user, (int)$mapa['svet_id'])) {
    hod_vyzva_chyba(403, 'Vyzvat k hodu může jen Pán jeskyně.');
}

$q = $pdo->prepare('SELECT id, typ_entity, entita_id, nazev FROM tokeny WHERE id = ? AND mapa_id = ?');
$q->execute([$tokenId, $mapaId]);
$token = $q->fetch();
if (!$token) {
    hod_vyzva_chyba(404, 'Token nenalezen.');
}
if ($token['typ_entity'] !== 'postava') {
    hod_vyzva_chyba(422, 'Vyzvat k hodu jde jen postavu hráče — nestvůře hází PJ sám.');
}

$q = $pdo->prepare('SELECT id, nazev, vlastnik_ucet_id FROM postavy WHERE id = ?');
$q->execute([(int)$token['entita_id']]);
$postava = $q->fetch();
if (!$postava) {
    hod_vyzva_chyba(404, 'Postava nenalezena.');
}

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'hod_vyzva', [
    'token_id' => (int)$token['id'],
    'postava_id' => (int)$postava['id'],
    'label' => (string)($token['nazev'] ?? $postava['nazev']),
    'vlastnik_ucet_id' => $postava['vlastnik_ucet_id'] !== null ? (int)$postava['vlastnik_ucet_id'] : null,
    'kostka' => $kostka,
    'vyzval' => (string)$user['jmeno'],
], (int)$user['id']);

echo json_encode([
    'ok' => true,
    'vyzva_id' => $eventId,
    'postava_id' => (int)$postava['id'],
    'kostka' => $kostka,
]);
