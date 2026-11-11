<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_prechod.php';

// Zpáteční pin, který si hra po vstupu do nové zóny založila sama (viz includes/vtt_prechod.php).
// PJ s ním v hlášce po vstupu může udělat dvě věci — rozhodnutí uživatele 2026-11-11:
//   „měl by se zakládat automaticky. respektive ten prompt k výběru by měl jít zrušit, aby pin
//    nevznikl. něco jako ‚pin nedělej‘“ a „mělo by se zeptat ‚přesun družiny na pin?‘ a buď dám ano
//    nebo vyberu jinam, kde se přemístí okolo pinu“.
//
// Tělo: {akce: 'zrusit'|'posunout', bod_id, x?, y?}
//   zrusit   — pin smaže (družina zůstane, kde je),
//   posunout — pin přesune na [x, y] a družinu přemístí kolem něj (volná pole počítá server,
//              ať se postavy nepřekrývají s tím, co na mapě stojí).
// Právo: jen PJ světa. Sahá se jen na pin, který opravdu vede zpátky na jinou mapu.

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$akce = (string)($data['akce'] ?? '');
$bodId = (int)($data['bod_id'] ?? 0);

function pin_zpatecni_chyba(int $kod, string $text): void
{
    http_response_code($kod);
    echo json_encode(['error' => $text]);
    exit;
}

if (!in_array($akce, ['zrusit', 'posunout'], true)) {
    pin_zpatecni_chyba(422, 'Neznámá akce.');
}

$pdo = dracak_db();
$q = $pdo->prepare('SELECT b.*, m.svet_id, m.sirka_px, m.vyska_px, m.grid_velikost_px, m.grid_typ, m.grid_posun_x, m.grid_posun_y
                    FROM mapa_body b JOIN mapy m ON m.id = b.mapa_id WHERE b.id = ?');
$q->execute([$bodId]);
$bod = $q->fetch();
if (!$bod || !dracak_vtt_svet_access($user, (int)$bod['svet_id'])) {
    pin_zpatecni_chyba(404, 'Pin nenalezen.');
}
if (!dracak_vtt_je_pj_sveta($user, (int)$bod['svet_id'])) {
    pin_zpatecni_chyba(403, 'Se zpátečním pinem hýbe jen Pán jeskyně.');
}
if (empty($bod['cilova_mapa_id'])) {
    pin_zpatecni_chyba(422, 'Tenhle pin nikam nevede — není to cesta zpátky.');
}

$svetId = (int)$bod['svet_id'];
$mapaId = (int)$bod['mapa_id'];

if ($akce === 'zrusit') {
    $pdo->prepare('DELETE FROM mapa_body WHERE id = ?')->execute([$bodId]);
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'mapa_bod_smazan', ['id' => $bodId], (int)$user['id']);
    echo json_encode(['ok' => true, 'smazano' => true, 'udalost_id' => $eventId]);
    exit;
}

$x = dracak_vtt_souradnice($data['x'] ?? 0);
$y = dracak_vtt_souradnice($data['y'] ?? 0);

// Postavy družiny na téhle mapě (stejná definice jako při vstupu: vlastní je někdo jiný než PJ).
$pjId = (int)($pdo->query('SELECT pj_ucet_id FROM svet WHERE id = ' . $svetId)->fetchColumn() ?: 0);
$q = $pdo->prepare(
    'SELECT t.id, t.x, t.y FROM tokeny t JOIN postavy p ON p.id = t.entita_id
     WHERE t.mapa_id = ? AND t.typ_entity = "postava" AND p.vlastnik_ucet_id IS NOT NULL AND p.vlastnik_ucet_id <> ? ORDER BY t.id'
);
$q->execute([$mapaId, $pjId]);
$druzina = $q->fetchAll();

// Obsazená pole = všechno ostatní, co na mapě stojí.
$q = $pdo->prepare('SELECT id, x, y FROM tokeny WHERE mapa_id = ?');
$q->execute([$mapaId]);
$druzinaIds = array_flip(array_map(static fn($t) => (int)$t['id'], $druzina));
$obsazene = [];
foreach ($q->fetchAll() as $t) {
    if (!isset($druzinaIds[(int)$t['id']])) {
        $obsazene[] = [(int)$t['x'], (int)$t['y']];
    }
}
$mista = dracak_vtt_prechod_volna_mista([
    'id' => $mapaId, 'sirka_px' => $bod['sirka_px'], 'vyska_px' => $bod['vyska_px'],
    'grid_velikost_px' => $bod['grid_velikost_px'], 'grid_typ' => $bod['grid_typ'],
    'grid_posun_x' => $bod['grid_posun_x'], 'grid_posun_y' => $bod['grid_posun_y'],
], (float)$x, (float)$y, $obsazene, count($druzina));

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE mapa_body SET x = ?, y = ? WHERE id = ?')->execute([$x, $y, $bodId]);
    $presuny = [];
    foreach ($druzina as $i => $t) {
        if (!isset($mista[$i])) {
            break;
        }
        $nx = (int)round($mista[$i][0]);
        $ny = (int)round($mista[$i][1]);
        $pdo->prepare('UPDATE tokeny SET x = ?, y = ? WHERE id = ?')->execute([$nx, $ny, (int)$t['id']]);
        $presuny[] = ['token_id' => (int)$t['id'], 'x' => $nx, 'y' => $ny, 'stare_x' => (int)$t['x'], 'stare_y' => (int)$t['y']];
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

dracak_vtt_log_event($svetId, $mapaId, 'mapa_bod_upraven', [
    'id' => $bodId, 'nazev' => (string)$bod['nazev'], 'typ' => (string)$bod['typ'], 'x' => $x, 'y' => $y,
    'cilova_mapa_id' => (int)$bod['cilova_mapa_id'], 'viditelny_hracum' => (bool)$bod['viditelny_hracum'],
    'popis' => $bod['popis'],
], (int)$user['id']);
foreach ($presuny as $p) {
    dracak_vtt_log_event($svetId, $mapaId, 'token_presun', $p, (int)$user['id']);
}

echo json_encode(['ok' => true, 'x' => $x, 'y' => $y, 'presunuto' => count($presuny)]);
