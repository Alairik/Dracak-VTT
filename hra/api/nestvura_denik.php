<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_souboj.php';
require_once __DIR__ . '/../../includes/vtt_nestvury.php';
require_once __DIR__ . '/../../includes/vtt_npc.php';

// Malý deník nestvůry (jen PJ — bestiář je tajný): přehled z bestiáře + čísla pro boj, která PJ jednou doplní
// a uloží pro všechny nestvůry stejného druhu v tomhle světě (includes/vtt_nestvury.php).
//   GET  ?mapa_id=&token_id=                          -> přehled a čísla
//   POST {mapa_id, token_id, akce: "uloz", staty, jen_tento}  -> uloží čísla (utoky [{nazev, uc, utocnost}], oc, oz, velikost, odolnost)
//   POST {mapa_id, token_id, akce: "vrat"}                    -> zahodí uložená čísla (zase platí text bestiáře)
//
// jen_tento (migrace 0115): čísla platí jen pro TENHLE kus a tím se z něj stane jedinečné NPC — vyplněný deník
// dělá z tokenu unikát (zadání uživatele 2026-11-09). Bez něj se ukládají pro celý druh ve světě jako dosud.
// U kusu, který už jedinečný je, se vždy ukládá jen jemu (jinak by úprava jeho čísel přepsala celý druh).
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
$instanceId = (int)$t['instance_id'];
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
    $vlastni = dracak_vtt_nestvura_instance_vlastni($pdo, $instanceId);
    $jenTento = $vlastni !== null && (!empty($vstup['jen_tento']) || $vlastni['jedinecny']);
    if ($akce === 'uloz') {
        [$staty, $chyba] = dracak_vtt_nestvura_over_staty($vstup['staty'] ?? null);
        if ($staty === null) {
            nestvura_denik_chyba(422, (string)$chyba);
        }
        if ($jenTento) {
            dracak_vtt_nestvura_uloz_instanci($pdo, $instanceId, $staty);
        } else {
            dracak_vtt_nestvura_uloz($pdo, $svetId, $nestvuraId, $staty);
        }
    } elseif ($akce === 'vrat') {
        if ($jenTento) {
            dracak_vtt_nestvura_vrat_instanci($pdo, $instanceId);
        } else {
            dracak_vtt_nestvura_vrat($pdo, $svetId, $nestvuraId);
        }
    } else {
        nestvura_denik_chyba(422, 'Neznámá akce.');
    }
    $ulozeno = true;
}

$s = dracak_vtt_nestvura_staty($pdo, $svetId, $nestvuraId, $n, $instanceId);
$mez = null;
$bojeschopnost = null;
if ($s['staty']['odolnost'] !== null && (int)$t['max_hp'] > 0) {
    // Celý stav, ne jen mez: okno tvora z toho píše „vyřazená“ / „pod třetinou životů, postih −2“.
    $bojeschopnost = dracak_vtt_bojeschopnost((int)$s['staty']['odolnost'], (int)$t['max_hp'], (int)$t['aktualni_hp']);
    $mez = $bojeschopnost['mez_vyrazeni'];
}
// Schopnosti i s hodnotou, když ji kniha uvádí (migrace 0116): „Infravidění 30 sáhů“ místo holého názvu.
try {
    $q = $pdo->prepare('SELECT z.nazev, ns.hodnota FROM nestvura_schopnosti ns JOIN zvlastni_schopnosti z ON z.id = ns.schopnost_id WHERE ns.nestvura_id = ? ORDER BY z.nazev');
    $q->execute([$nestvuraId]);
    $schopnostiRadky = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $q = $pdo->prepare('SELECT z.nazev, NULL AS hodnota FROM nestvura_schopnosti ns JOIN zvlastni_schopnosti z ON z.id = ns.schopnost_id WHERE ns.nestvura_id = ? ORDER BY z.nazev');
    $q->execute([$nestvuraId]);
    $schopnostiRadky = $q->fetchAll(PDO::FETCH_ASSOC);
}
$schopnosti = [];
foreach ($schopnostiRadky as $r) {
    $schopnosti[] = $r['hodnota'] !== null && $r['hodnota'] !== ''
        ? $r['nazev'] . ' ' . $r['hodnota'] . ' sáhů'
        : $r['nazev'];
}

// Na co je tvor citlivý (kouzelná zbraň, oheň, stříbro, jed, mentální útok…). V datech to je od začátku,
// ale hra to dosud nikde neukazovala — přitom je to v boji první otázka, co na nestvůru platí.
$zranitelnosti = [];
try {
    // Za písmenem může být v knize číslo (½, ¼, 2): „Tímto číslem se vynásobí počet odebraných životů
    // po úspěšném útoku (zaokrouhluj nahoru)“ — méně než 1 = částečně odolná, víc než 1 = náchylná.
    $q = $pdo->prepare(
        'SELECT k.kod, k.nazev, k.popis, nz.modifikator FROM nestvura_zranitelnosti nz
         JOIN kody_zranitelnosti k ON k.id = nz.kod_zranitelnosti_id
         WHERE nz.nestvura_id = ? AND k.nazev IS NOT NULL AND k.nazev <> "" ORDER BY k.id'
    );
    $q->execute([$nestvuraId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $z) {
        $zranitelnosti[] = [
            'kod' => (string)$z['kod'],
            'nazev' => (string)$z['nazev'],
            'popis' => $z['popis'] !== null && $z['popis'] !== '' ? (string)$z['popis'] : null,
            'nasobek' => $z['modifikator'] !== null && $z['modifikator'] !== '' ? (string)$z['modifikator'] : null,
        ];
    }
} catch (Throwable $e) {
    // tabulka tu není — zranitelnosti se prostě neukážou
}

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
    // true = tenhle kus má vlastní čísla (jedinečné NPC); UI podle toho píše „jen tento kus“ místo „celý druh“
    'jedinecny' => $s['jedinecny'],
    'lze_jen_tento' => dracak_vtt_npc_funkce(),
    'hp' => ['aktualni' => (int)$t['aktualni_hp'], 'max' => (int)$t['max_hp']],
    'mez_vyrazeni' => $mez,
    'bojeschopnost' => $bojeschopnost,
    'schopnosti' => $schopnosti,
    'zranitelnosti' => $zranitelnosti,
    // Pravidlo z bestiáře, ať ho PJ nemusí hledat: co v seznamu není, nestvůře podle pravidel neublíží.
    'zranitelnosti_pozn' => $zranitelnosti ? 'Co v seznamu není, nestvůře podle pravidel neublíží.' : null,
    // Co PJ potřebuje po boji, ne během něj: zkušenost za zabití a poklady z bestiáře.
    'zkusenost' => $n['zkusenost'] !== null && $n['zkusenost'] !== '' ? (string)$n['zkusenost'] : null,
    'poklady' => $n['poklady'] !== null && $n['poklady'] !== '' ? (string)$n['poklady'] : null,
    'popis' => $n['popis'] !== null && $n['popis'] !== '' ? (string)$n['popis'] : null,
    'lze_ulozit' => dracak_vtt_denik_nestvur_funkce(),
], JSON_UNESCAPED_UNICODE);
