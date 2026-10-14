<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';

// Smaže kresbu (vlastní nebo — jako PJ/admin — jakoukoli). Režimy:
//   {id}                      jedna kresba
//   {mapa_id, rezim: "moje"}   všechny moje kresby na mapě
//   {mapa_id, rezim: "vsechny"} všechny kresby na mapě (jen PJ/admin)
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$pdo = dracak_db();

function kresba_smazat_chyba(int $kod, string $zprava): void
{
    http_response_code($kod);
    echo json_encode(['error' => $zprava]);
    exit;
}
if (!dracak_vtt_tokeny_funkce()['kresby']) {
    kresba_smazat_chyba(409, 'Kreslení se zapne po jednorázovém kroku správce (ruční SQL).');
}

if (!empty($data['id'])) {
    $stmt = $pdo->prepare('SELECT k.*, m.svet_id FROM mapa_kresby k JOIN mapy m ON m.id = k.mapa_id WHERE k.id = ?');
    $stmt->execute([(int)$data['id']]);
    $k = $stmt->fetch();
    if (!$k || !dracak_vtt_svet_access($user, (int)$k['svet_id'])) {
        kresba_smazat_chyba(404, 'Kresba nenalezena.');
    }
    $jePj = dracak_vtt_je_pj_sveta($user, (int)$k['svet_id']);
    if (!$jePj && (int)$k['ucet_id'] !== (int)$user['id']) {
        kresba_smazat_chyba(403, 'Cizí kresbu smí smazat jen PJ.');
    }
    $mapaId = (int)$k['mapa_id'];
    $svetId = (int)$k['svet_id'];
    $ids = [(int)$k['id']];
} else {
    $mapaId = (int)($data['mapa_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
    $stmt->execute([$mapaId]);
    $mapa = $stmt->fetch();
    if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
        kresba_smazat_chyba(404, 'Mapa nenalezena.');
    }
    $svetId = (int)$mapa['svet_id'];
    $jePj = dracak_vtt_je_pj_sveta($user, $svetId);
    $rezim = (string)($data['rezim'] ?? '');
    if ($rezim === 'vsechny') {
        if (!$jePj) kresba_smazat_chyba(403, 'Všechny kresby smí smazat jen PJ.');
        $q = $pdo->prepare('SELECT id FROM mapa_kresby WHERE mapa_id = ?');
        $q->execute([$mapaId]);
    } elseif ($rezim === 'moje') {
        $q = $pdo->prepare('SELECT id FROM mapa_kresby WHERE mapa_id = ? AND ucet_id = ?');
        $q->execute([$mapaId, (int)$user['id']]);
    } else {
        kresba_smazat_chyba(422, 'Neznámý způsob mazání.');
    }
    $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM mapa_kresby WHERE id IN ($in)")->execute($ids);
}
$udalostId = dracak_vtt_log_event($svetId, $mapaId, 'kresba_smazana', ['ids' => $ids], (int)$user['id']);
echo json_encode(['ok' => true, 'udalost_id' => $udalostId, 'ids' => $ids]);
