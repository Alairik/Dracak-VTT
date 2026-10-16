<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_souboj.php';
require_once __DIR__ . '/../../includes/vtt_nestvury.php';

// Malý deník nestvůry (jen PJ — bestiář je tajný): přehled z bestiáře + čísla pro boj, která PJ jednou doplní
// a uloží pro všechny nestvůry stejného druhu v tomhle světě (includes/vtt_nestvury.php).
//   GET  ?mapa_id=&token_id=                          -> přehled a čísla
//   POST {mapa_id, token_id, akce: "uloz", staty}     -> uloží čísla (utoky [{nazev, uc, utocnost}], oc, oz, velikost, odolnost)
//   POST {mapa_id, token_id, akce: "vrat"}            -> zahodí uložená čísla (zase platí text bestiáře)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}
$metoda = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$vstup = $metoda === 'POST' ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : $_GET;
$mapaId = (int)($vstup['mapa_id'] ?? 0);
$tokenId = (int)($vstup['token_id'] ?? 0);

function nestvura_denik_chyba(int $kod, string $text): void
{
    http_response_code($kod);
    echo json_encode(['error' => $text], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = dracak_db();
$q = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$q->execute([$mapaId]);
$mapa = $q->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    nestvura_denik_chyba(404, 'Mapa nenalezena.');
}
$svetId = (int)$mapa['svet_id'];
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    nestvura_denik_chyba(403, 'Deník nestvůry vidí jen PJ.');
}
$q = $pdo->prepare(
    'SELECT t.id, t.nazev AS token_nazev, ni.id AS instance_id, ni.nazev_instance, ni.aktualni_hp, ni.max_hp, ni.nestvura_id
     FROM tokeny t JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = "nestvura_instance"
     WHERE t.id = ? AND t.mapa_id = ?'
);
$q->execute([$tokenId, $mapaId]);
$t = $q->fetch();
if (!$t) {
    nestvura_denik_chyba(404, 'Tohle není nestvůra na téhle mapě.');
}
$nestvuraId = (int)$t['nestvura_id'];
$n = dracak_vtt_nestvura_radek($pdo, $nestvuraId);
if (!$n) {
    nestvura_denik_chyba(404, 'Nestvůra v bestiáři nenalezena.');
}

$ulozeno = false;
if ($metoda === 'POST') {
    if (!dracak_vtt_denik_nestvur_funkce()) {
        nestvura_denik_chyba(409, 'Ukládání čísel ještě není na serveru zapnuté (chybí ruční SQL).');
    }
    $akce = (string)($vstup['akce'] ?? '');
    if ($akce === 'uloz') {
        [$staty, $chyba] = dracak_vtt_nestvura_over_staty($vstup['staty'] ?? null);
        if ($staty === null) {
            nestvura_denik_chyba(422, (string)$chyba);
        }
        dracak_vtt_nestvura_uloz($pdo, $svetId, $nestvuraId, $staty);
    } elseif ($akce === 'vrat') {
        dracak_vtt_nestvura_vrat($pdo, $svetId, $nestvuraId);
    } else {
        nestvura_denik_chyba(422, 'Neznámá akce.');
    }
    $ulozeno = true;
}

$s = dracak_vtt_nestvura_staty($pdo, $svetId, $nestvuraId, $n);
$mez = null;
if ($s['staty']['odolnost'] !== null && (int)$t['max_hp'] > 0) {
    $mez = dracak_vtt_bojeschopnost((int)$s['staty']['odolnost'], (int)$t['max_hp'], (int)$t['aktualni_hp'])['mez_vyrazeni'];
}
$q = $pdo->prepare('SELECT z.nazev FROM nestvura_schopnosti ns JOIN zvlastni_schopnosti z ON z.id = ns.schopnost_id WHERE ns.nestvura_id = ? ORDER BY z.nazev');
$q->execute([$nestvuraId]);
$schopnosti = $q->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'ok' => true,
    'ulozeno_nyni' => $ulozeno,
    'token_id' => $tokenId,
    'nestvura_id' => $nestvuraId,
    'nazev' => (string)$n['nazev'],
    'bestiar' => [
        'zivotaschopnost' => $n['zivotaschopnost'], 'velikost' => $n['velikost'], 'odolnost' => $n['odolnost'],
        'uc' => $n['uc'], 'oc' => $n['oc'], 'bojovnost' => $n['bojovnost'], 'pohyblivost' => $n['pohyblivost'],
        'vytrvalost' => $n['vytrvalost'], 'inteligence' => $n['inteligence'], 'charisma' => $n['charisma'],
    ],
    'staty' => $s['staty'],
    'vychozi' => $s['vychozi'],
    'ulozeno' => $s['ulozeno'],
    'nespocteno' => $s['nespocteno'],
    'hp' => ['aktualni' => (int)$t['aktualni_hp'], 'max' => (int)$t['max_hp']],
    'mez_vyrazeni' => $mez,
    'schopnosti' => $schopnosti,
    'lze_ulozit' => dracak_vtt_denik_nestvur_funkce(),
], JSON_UNESCAPED_UNICODE);
