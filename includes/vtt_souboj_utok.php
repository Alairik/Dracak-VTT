<?php
declare(strict_types=1);

// Souboj, tah v2 — etapa 2: výzvy k hodu, útok proti obraně, zranění
// (docs/navrh-tahu-v2.md; pravidla content/pravidla-hrac.html h1610–h1613, h1623).
//
// VÝZVA K HODU = čekající hod, který vidí všichni u stolu (tabulka souboj_vyzva, ruční SQL
// z docs/sql-k-rucnimu-spusteni.sql). Hody se nehází samy: hráč (nebo PJ za něj) klikne na
// "Hodit" -> 3D kostka, nebo zapíše hod z vlastní kostky; server jen hlídá rozsah a čísla
// (ÚČ/OČ, opravy) počítá sám. PJ smí před hodem změnit číslo (oprava), u nestvůr doplnit ÚČ/OČ.
//
// Průběh útoku (h1623: "útok přeruší tah, napadený se rozhodne, jak se bude bránit"):
//   1. tah_akce.php odehraje pohyb a založí výzvu "utok" (hází útočník), tah se NEposouvá
//   2. útočník hodí 1k6+ -> vznikne výzva "obrana" (bránit se zbraní za akci / bez zbraně −3)
//   3. obránce hodí 1k6+ -> zásah (útok >= obrana), zranění = rozdíl + útočnost zbraně
//      (aspoň 1), životy se odečtou, tah útočníka pokračuje (dracak_vtt_souboj_posun)
//
// Výzva 'past' (hod proti pasti, 1k10 + bonus vlastnosti >= nebezpečnost) je v souboj_pasti.php.

require_once __DIR__ . '/vtt_souboj.php';
require_once __DIR__ . '/vtt_souboj_pohyb.php';
require_once __DIR__ . '/vtt_denik.php';

// Tabulka rozdílu velikostí (h1610, b10702–b10708): [velikost obránce][velikost útočníka] = oprava
// hodu na útok. Sloupce A0, A, B, C, D, E; hodnoty přesně podle knihy (i ty, které vypadají zvláštně).
const DRACAK_VTT_ROZDIL_VELIKOSTI = [
    'A0' => ['A0' => 0, 'A' => 2, 'B' => 3, 'C' => 4, 'D' => 0, 'E' => -2],
    'A' => ['A0' => -2, 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 0, 'E' => -1],
    'B' => ['A0' => -3, 'A' => -1, 'B' => 0, 'C' => 1, 'D' => 2, 'E' => 0],
    'C' => ['A0' => -4, 'A' => -2, 'B' => -1, 'C' => 0, 'D' => 1, 'E' => 2],
    'D' => ['A0' => -5, 'A' => -3, 'B' => -2, 'C' => -1, 'D' => 0, 'E' => 1],
    'E' => ['A0' => -7, 'A' => -4, 'B' => -3, 'C' => -2, 'D' => -1, 'E' => 0],
];
const DRACAK_VTT_VYZVA_STAVY_OTEVRENE = ['ceka', 'hozeno'];

function dracak_vtt_vyzva_funkce(): bool
{
    return dracak_vtt_tokeny_funkce()['vyzvy'];
}

// ---------------------------------------------------------------------------------------------
// Úložiště výzev
// ---------------------------------------------------------------------------------------------

function dracak_vtt_vyzva_radek(array $r): array
{
    $d = json_decode((string)$r['data'], true);
    return [
        'id' => (int)$r['id'],
        'mapa_id' => (int)$r['mapa_id'],
        'druh' => (string)$r['druh'],
        'stav' => (string)$r['stav'],
        'token_id' => (int)$r['token_id'],
        'cil_token_id' => $r['cil_token_id'] !== null ? (int)$r['cil_token_id'] : null,
        'rodic_id' => $r['rodic_id'] !== null ? (int)$r['rodic_id'] : null,
        'data' => is_array($d) ? $d : [],
    ];
}

function dracak_vtt_vyzva_nacti(PDO $pdo, int $id, bool $zamknout = false): ?array
{
    $q = $pdo->prepare('SELECT * FROM souboj_vyzva WHERE id = ?' . ($zamknout ? ' FOR UPDATE' : ''));
    $q->execute([$id]);
    $r = $q->fetch();
    return $r ? dracak_vtt_vyzva_radek($r) : null;
}

function dracak_vtt_vyzva_vloz(PDO $pdo, int $mapaId, string $druh, int $tokenId, ?int $cilId, ?int $rodicId, array $data): int
{
    $pdo->prepare('INSERT INTO souboj_vyzva (mapa_id, druh, stav, token_id, cil_token_id, rodic_id, data) VALUES (?, ?, "ceka", ?, ?, ?, ?)')
        ->execute([$mapaId, $druh, $tokenId, $cilId, $rodicId, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    return (int)$pdo->lastInsertId();
}

function dracak_vtt_vyzva_uloz(PDO $pdo, int $id, array $data, ?string $stav = null): void
{
    if ($stav === null) {
        $pdo->prepare('UPDATE souboj_vyzva SET data = ? WHERE id = ?')->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $id]);
    } else {
        $pdo->prepare('UPDATE souboj_vyzva SET data = ?, stav = ? WHERE id = ?')->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $stav, $id]);
    }
}

// Otevřené (nevyřešené) výzvy mapy, od nejstarší.
// $bezTiku: bez tiků efektů (krvácení, regenerace) — ty tah nezastavují, vyřídí se, kdy se to hodí.
function dracak_vtt_vyzva_otevrene(PDO $pdo, int $mapaId, bool $zamknout = false, bool $bezTiku = false): array
{
    if (!dracak_vtt_vyzva_funkce()) {
        return [];
    }
    $q = $pdo->prepare('SELECT * FROM souboj_vyzva WHERE mapa_id = ? AND stav IN ("ceka", "hozeno")'
        . ($bezTiku ? ' AND druh <> "tik"' : '') . ' ORDER BY id' . ($zamknout ? ' FOR UPDATE' : ''));
    $q->execute([$mapaId]);
    return array_map('dracak_vtt_vyzva_radek', $q->fetchAll());
}

// Zruší všechny otevřené výzvy mapy (PJ přeskočil, souboj skončil).
function dracak_vtt_vyzva_zrus_vse(PDO $pdo, int $mapaId, bool $bezTiku = false): int
{
    if (!dracak_vtt_vyzva_funkce()) {
        return 0;
    }
    $q = $pdo->prepare('UPDATE souboj_vyzva SET stav = "zruseno" WHERE mapa_id = ? AND stav IN ("ceka", "hozeno")' . ($bezTiku ? ' AND druh <> "tik"' : ''));
    $q->execute([$mapaId]);
    return $q->rowCount();
}

// ---------------------------------------------------------------------------------------------
// Hody
// ---------------------------------------------------------------------------------------------

// Hod 1k6+ (h1610, kap. Kostky b58): po šestce se hází znovu a výsledky se sčítají -> všechny
// hody kromě posledního jsou šestky, poslední není šestka. Vrací seznam hodů, nebo null.
function dracak_vtt_hody_k6plus_over($hody): ?array
{
    if (!is_array($hody) || !$hody || count($hody) > 30) {
        return null;
    }
    $hody = array_values(array_map('intval', $hody));
    foreach ($hody as $i => $h) {
        if ($h < 1 || $h > 6) {
            return null;
        }
        $posledni = $i === count($hody) - 1;
        if ($posledni === ($h === 6)) {
            return null;
        }
    }
    return $hody;
}

// Ruční zápis součtu z fyzické kostky ("9" = šestka + trojka). Násobek šesti nejde: šestka se
// přehazuje, takže součet vždy končí hodem 1–5.
function dracak_vtt_hody_z_souctu(int $soucet): ?array
{
    if ($soucet < 1 || $soucet > 180 || $soucet % 6 === 0) {
        return null;
    }
    return array_merge(array_fill(0, intdiv($soucet, 6), 6), [$soucet % 6]);
}

// Hod 1k10 (past): jedno číslo 1–10.
function dracak_vtt_hody_k10_over($hody): ?array
{
    if (!is_array($hody) || count($hody) !== 1) {
        return null;
    }
    $h = (int)$hody[0];
    return $h >= 1 && $h <= 10 ? [$h] : null;
}

// ---------------------------------------------------------------------------------------------
// Bojovník: čísla z deníku postavy / textu nestvůry
// ---------------------------------------------------------------------------------------------

// Text s číslem z bestiáře (ÚČ, OČ) bez závorek a se správnými minusy.
function dracak_vtt_text_cisla(string $s): string
{
    return trim(str_replace(['−', '–', '—', "\u{00A0}"], ['-', '-', '-', ' '], $s));
}

// ÚČ nestvůry z textu bestiáře: jedna nebo víc variant útoku ("(+2+2/+1)=4/+1 (drápy) + (+2+3/+1)=5/+1
// (tlama)"; číslo za "=" je ÚČ, za lomítkem útočnost). Vzorec bez výsledku ("+5 + síla zbraně") se
// nehádá -> prázdný seznam, PJ číslo doplní. Vrací [['uc' => int, 'utocnost' => int, 'popis' => string]].
function dracak_vtt_nestvura_uc(?string $text): array
{
    $t = dracak_vtt_text_cisla((string)$text);
    $varianty = [];
    if (preg_match_all('/=\s*(-?\d+)(?:\s*\/\s*([+-]?\d+))?(?:\s*\(([^)]*)\))?/u', $t, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $varianty[] = ['uc' => max(0, (int)$x[1]), 'utocnost' => (int)($x[2] ?? 0), 'popis' => trim($x[3] ?? '')];
        }
    } elseif (preg_match('/^\s*(\d+)(?:\s*\/\s*([+-]?\d+))?\s*(?:\(([^)]*)\))?\s*$/u', $t, $x)) {
        $varianty[] = ['uc' => (int)$x[1], 'utocnost' => (int)($x[2] ?? 0), 'popis' => trim($x[3] ?? '')];
    }
    return $varianty;
}

// OČ nestvůry z textu bestiáře: číslo za "=" nebo holé číslo. Vzorec s "kvalita zbroje" se nehádá.
function dracak_vtt_nestvura_oc(?string $text): ?int
{
    $t = dracak_vtt_text_cisla((string)$text);
    if (preg_match('/=\s*(-?\d+)/u', $t, $m)) {
        return max(0, (int)$m[1]);
    }
    if (preg_match('/^\s*(\d+)\s*(?:\([^)]*\))?\s*$/u', $t, $m)) {
        return (int)$m[1];
    }
    return null;
}

// Údaje o tvoru na tokenu, které boj potřebuje. Postava: z deníku (zbraně, OČ, bojeschopnost, naložení);
// nestvůra: z textu bestiáře (varianty ÚČ, OČ, velikost, odolnost) + životy instance.
function dracak_vtt_souboj_bojovnik(PDO $pdo, array $infoTokenu): array
{
    $b = [
        'typ' => $infoTokenu['typ_entity'], 'entita_id' => $infoTokenu['entita_id'], 'label' => $infoTokenu['label'],
        'velikost' => null, 'odolnost' => null, 'hp' => 0, 'max_hp' => 0, 'bojeschopnost' => null,
        'nalozeni_velke' => false, 'zbrane' => [], 'oc' => null, 'stit' => false, 'oz' => null, 'oz_nazev' => null,
        'uc_text' => null, 'oc_text' => null, 'bonus_obratnost' => null,
        'efekty' => dracak_vtt_efekty_souhrn(
            $pdo, (string)$infoTokenu['typ_entity'], (int)$infoTokenu['entita_id']
        ),
    ];
    if ($infoTokenu['typ_entity'] === 'postava') {
        $q = $pdo->prepare('SELECT * FROM postavy WHERE id = ?');
        $q->execute([$infoTokenu['entita_id']]);
        $p = $q->fetch();
        if (!$p) {
            return $b;
        }
        $d = dracak_vtt_denik_data($pdo, $p, false);
        $b['velikost'] = isset(DRACAK_VTT_ROZDIL_VELIKOSTI[(string)$d['velikost']]) ? (string)$d['velikost'] : null;
        $b['odolnost'] = $d['stupne']['odolnost'];
        $b['hp'] = (int)$p['aktualni_hp'];
        $b['max_hp'] = (int)$p['max_hp'];
        $b['bojeschopnost'] = $d['bojeschopnost'];
        $b['nalozeni_velke'] = in_array($d['pohyblivost']['nalozeni']['kod'] ?? null, ['velke', 'prilis_velke'], true);
        foreach ($d['zbrane'] as $z) {
            if ($z['uc_tvar_v_tvar'] !== null) {
                $b['zbrane'][] = ['id' => $z['id'], 'nazev' => $z['nazev'], 'uc' => (int)$z['uc_tvar_v_tvar'],
                    'utocnost' => (int)($z['utocnost'] ?? 0), 'drzena' => (bool)$z['drzena']];
            }
            if ($z['drzena']) {
                $b['oz'] = $z['oz'];
                $b['oz_nazev'] = $z['nazev'];
            }
        }
        if ($d['obrana']) {
            $b['oc'] = $d['obrana']['oc'];
            $b['stit'] = $d['obrana']['bonus_stit'] > 0;
            $b['bonus_obratnost'] = (int)$d['obrana']['bonus_obratnost'];
        }
        return $b;
    }
    $q = $pdo->prepare(
        'SELECT ni.nestvura_id, ni.aktualni_hp, ni.max_hp, n.velikost, n.uc, n.oc, n.odolnost
         FROM nestvura_instance ni JOIN nestvury n ON n.id = ni.nestvura_id WHERE ni.id = ?'
    );
    $q->execute([$infoTokenu['entita_id']]);
    $n = $q->fetch();
    if (!$n) {
        return $b;
    }
    // Čísla pro boj: uložená v deníku nestvůry (PJ je doplnil jednou za druh), jinak z textu bestiáře.
    require_once __DIR__ . '/vtt_nestvury.php';
    $s = dracak_vtt_nestvura_staty($pdo, (int)$infoTokenu['svet_id'], (int)$n['nestvura_id'], $n);
    $st = $s['staty'];
    $velikostText = trim(dracak_vtt_text_cisla((string)$n['velikost']));
    $b['velikost'] = $st['velikost'];
    $b['velikost_text'] = $velikostText !== '' ? $velikostText : null;
    $b['odolnost'] = $st['odolnost'];
    $b['hp'] = (int)$n['aktualni_hp'];
    $b['max_hp'] = (int)$n['max_hp'];
    if ($b['odolnost'] !== null && $b['max_hp'] > 0) {
        $b['bojeschopnost'] = dracak_vtt_bojeschopnost($b['odolnost'], $b['max_hp'], $b['hp']);
    }
    $b['uc_text'] = $n['uc'] !== null ? (string)$n['uc'] : null;
    $b['oc_text'] = $n['oc'] !== null ? (string)$n['oc'] : null;
    foreach ($st['utoky'] as $i => $u) {
        $b['zbrane'][] = ['id' => $i + 1, 'nazev' => (string)$u['nazev'], 'uc' => (int)$u['uc'], 'utocnost' => (int)$u['utocnost'], 'drzena' => $i === 0];
    }
    $b['oc'] = $st['oc'];
    $b['oz'] = $st['oz'];
    $b['oz_nazev'] = $st['oz'] !== null ? 'nestvůry' : null;
    $b['staty_ulozeno'] = $s['ulozeno'];
    return $b;
}

// Je tvor vyřazený z boje (životy na mezi vyřazení nebo pod ní, h1610)? Bez znalosti odolnosti a
// maximálních životů se nehádá (nestvůra s neúplnými daty).
function dracak_vtt_souboj_vyrazen(PDO $pdo, array $infoTokenu): bool
{
    $b = dracak_vtt_souboj_bojovnik($pdo, $infoTokenu);
    return $b['bojeschopnost'] !== null && $b['bojeschopnost']['vyrazena'];
}

// ---------------------------------------------------------------------------------------------
// Strana útoku, složky čísel
// ---------------------------------------------------------------------------------------------

// Útočí se na obránce zepředu (útočník stojí v jeho hexech dosahu), nebo z boku / zezadu (h1623: −4)?
function dracak_vtt_utok_strana(array $mapa, array $cilToken, array $utocnikToken): string
{
    $roz = dracak_vtt_hex_rozmery($mapa);
    if ($roz === null) {
        return 'predni';
    }
    $telo = dracak_vtt_token_telo($roz, $cilToken);
    $dosah = [];
    foreach (dracak_vtt_hex_dosah_utoku($telo['tvar'], $telo['pivot'], $telo['orientace']) as $b) {
        $dosah[$b[0] . ',' . $b[1]] = true;
    }
    foreach (dracak_vtt_token_telo($roz, $utocnikToken)['bunky'] as $b) {
        if (isset($dosah[$b[0] . ',' . $b[1]])) {
            return 'predni';
        }
    }
    return 'bok';
}

function dracak_vtt_oprava(string $nazev, int $hodnota): array
{
    return ['nazev' => $nazev, 'hodnota' => $hodnota];
}

// Opravy hodu na obranu podle volby obránce a strany útoku (h1611, h1623):
//  - štít +1; bojeschopnost; −1 velké naložení
//  - "bránit se" (stojí akci): obrana zbraně (OZ), bez zbraně −3; z boku / zezadu navíc −4
//  - "nebránit se" (jen brněním): −3; z boku / zezadu dohromady −6 (zahrnuje nepočítanou OZ i útok zezadu)
function dracak_vtt_obrana_opravy(array $d): array
{
    $o = [];
    if (!empty($d['stit'])) {
        $o[] = dracak_vtt_oprava('štít', 1);
    }
    if ((int)($d['postih_zivoty'] ?? 0) !== 0) {
        $o[] = dracak_vtt_oprava('méně než 1/3 životů', (int)$d['postih_zivoty']);
    }
    if (!empty($d['nalozeni_velke'])) {
        $o[] = dracak_vtt_oprava('velké naložení', -1);
    }
    foreach (($d['efekt_opravy'] ?? []) as $x) {
        if ((int)$x['hodnota'] !== 0) {
            $o[] = dracak_vtt_oprava((string)$x['nazev'], (int)$x['hodnota']);
        }
    }
    $volba = $d['volba'] ?? null;
    $nehybny = !empty($d['nehybny']);
    $bok = !$nehybny && ($d['strana'] ?? 'predni') === 'bok';
    if ($nehybny) {
        // h1613: nehybný obránce — místo bonusu za obratnost postih −5 (tedy odečíst bonus za obratnost a ještě 5).
        $o[] = dracak_vtt_oprava('nehybný (' . $d['nehybny'] . '): místo bonusu za obratnost −5', -((int)($d['bonus_obratnost'] ?? 0) + 5));
    }
    if ($volba === 'branit') {
        if (($d['oz_znamo'] ?? false) === true) {
            $o[] = $d['oz'] === null ? dracak_vtt_oprava('bez zbraně', -3) : dracak_vtt_oprava('obrana zbraně (OZ ' . ($d['oz_nazev'] ?? '') . ')', (int)$d['oz']);
        }
        if ($bok) {
            $o[] = dracak_vtt_oprava('útok z boku nebo zezadu', -4);
        }
    } elseif ($volba === 'nebranit') {
        $o[] = $bok ? dracak_vtt_oprava('bez obrany zbraní, útok z boku nebo zezadu', -6) : dracak_vtt_oprava('bez obrany zbraní', -3);
    }
    return $o;
}

function dracak_vtt_vyzva_opravy(string $druh, array $d): array
{
    return $druh === 'obrana' ? dracak_vtt_obrana_opravy($d) : ($d['opravy'] ?? []);
}

// Číslo bez hodu: základ (ÚČ / OČ) + opravy + oprava PJ. Null, dokud základ chybí.
function dracak_vtt_vyzva_cislo(string $druh, array $d): ?int
{
    if (!array_key_exists('zaklad', $d) || $d['zaklad'] === null) {
        return null;
    }
    return (int)$d['zaklad'] + array_sum(array_column(dracak_vtt_vyzva_opravy($druh, $d), 'hodnota')) + (int)($d['modifikator'] ?? 0);
}

// ---------------------------------------------------------------------------------------------
// Založení útoku
// ---------------------------------------------------------------------------------------------

// Kolik akcí v řadě naposledy sprintoval tvor (z událostí akce_provedena tohoto kola, bez právě
// probíhající akce): rozběh (HB) = dvě akce sprintu za sebou a následný útok, který smí být spojený
// s druhým sprintem. Počítá se jen z akcí tohoto tvora ve stejném kole.
function dracak_vtt_souboj_sprinty_v_rade(PDO $pdo, int $mapaId, int $tokenId, int $cisloKola): int
{
    $q = $pdo->prepare(
        'SELECT payload FROM svet_udalosti WHERE mapa_id = ? AND typ = "akce_provedena" ORDER BY id DESC LIMIT 60'
    );
    $q->execute([$mapaId]);
    $rada = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $p = json_decode((string)$json, true);
        if (!is_array($p) || (int)($p['token_id'] ?? 0) !== $tokenId) {
            continue;
        }
        if ((int)($p['cislo_kola'] ?? 0) !== $cisloKola) {
            break;
        }
        if (in_array($p['druh'] ?? '', ['sprint', 'sprint_utok'], true)) {
            $rada += max(1, (int)($p['pocet'] ?? 1));
        } else {
            break;
        }
    }
    return $rada;
}

// Kolik útoků už tvor v tomhle kole odehrál (z událostí akce_provedena s cílem) — nestvůra podle toho střídá druhy útoku.
function dracak_vtt_souboj_utoku_v_kole(PDO $pdo, int $mapaId, int $tokenId): int
{
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId);
    if ($stav === null) {
        return 0;
    }
    $q = $pdo->prepare('SELECT payload FROM svet_udalosti WHERE mapa_id = ? AND typ = "akce_provedena" ORDER BY id DESC LIMIT 80');
    $q->execute([$mapaId]);
    $pocet = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $p = json_decode((string)$json, true);
        if (!is_array($p) || (int)($p['token_id'] ?? 0) !== $tokenId) {
            continue;
        }
        if ((int)($p['cislo_kola'] ?? 0) !== $stav['cislo_kola']) {
            break;
        }
        if (!empty($p['cil_token_id'])) {
            $pocet++;
        }
    }
    return $pocet;
}

// Složky hodu na útok: základ vybrané zbraně + opravy (bojeschopnost, naložení, rozdíl velikostí, rozběh).
function dracak_vtt_utok_priprav(PDO $pdo, array $utocnik, array $cil, bool $rozbeh, int $poradiUtoku = 0): array
{
    $opravy = [];
    $upozorneni = [];
    $bp = $utocnik['bojeschopnost']['postih_aktualni'] ?? 0;
    if ($bp !== 0) {
        $opravy[] = dracak_vtt_oprava('méně než 1/3 životů', $bp);
    }
    if ($utocnik['nalozeni_velke']) {
        $opravy[] = dracak_vtt_oprava('velké naložení', -1);
    }
    foreach ($utocnik['efekty']['opravy_uc'] as $x) {
        if ((int)$x['hodnota'] !== 0) {
            $opravy[] = dracak_vtt_oprava((string)$x['nazev'], (int)$x['hodnota']);
        }
    }
    if ($cil['efekty']['nehybny'] !== null) {
        // h1613: na nehybnou postavu se nevztahují opravy za rozdíl velikostí.
        $upozorneni[] = $cil['label'] . ' je nehybný (' . $cil['efekty']['nehybny'] . ') — bez opravy za rozdíl velikostí, obrana jen s postihem (h1613).';
    } elseif ($utocnik['velikost'] !== null && $cil['velikost'] !== null) {
        $rozdil = DRACAK_VTT_ROZDIL_VELIKOSTI[$cil['velikost']][$utocnik['velikost']];
        if ($rozdil !== 0) {
            $opravy[] = dracak_vtt_oprava('rozdíl velikostí (' . $utocnik['velikost'] . ' proti ' . $cil['velikost'] . ')', $rozdil);
        }
    } else {
        $upozorneni[] = 'Rozdíl velikostí neznám'
            . (($utocnik['velikost_text'] ?? null) ? ' (útočník ' . $utocnik['velikost_text'] . ')' : '')
            . (($cil['velikost_text'] ?? null) ? ' (obránce ' . $cil['velikost_text'] . ')' : '')
            . ' — doplň ho do opravy podle tabulky v pravidlech.';
    }
    if ($rozbeh) {
        $opravy[] = dracak_vtt_oprava('rozběh (dva sprinty za sebou, HB)', 1);
    }
    $vybrana = null;
    if ($utocnik['typ'] !== 'postava' && count($utocnik['zbrane']) > 1) {
        // Nestvůra s víc druhy útoku je střídá v uvedeném pořadí: nejdřív každý druh jednou, pak znovu (PJ-kniha, bestiář).
        $vybrana = $utocnik['zbrane'][$poradiUtoku % count($utocnik['zbrane'])];
    }
    foreach ($utocnik['zbrane'] as $z) {
        if ($vybrana === null && $z['drzena']) {
            $vybrana = $z;
        }
    }
    if ($vybrana === null && $utocnik['zbrane']) {
        $vybrana = $utocnik['zbrane'][0];
    }
    if ($vybrana === null) {
        $upozorneni[] = $utocnik['typ'] === 'postava'
            ? 'Postava nemá zbraň se spočítaným ÚČ — doplň útočné číslo.'
            : 'Útočné číslo nestvůry se z textu bestiáře nedá spočítat (' . ($utocnik['uc_text'] ?? 'bez údaje') . ') — doplň ho'
                . (dracak_vtt_tokeny_funkce()['denik_nestvur'] ? ' (nejlíp v deníku nestvůry v panelu tokenu, pak platí pro celý druh).' : '.');
    }
    return [
        'utocnik_label' => $utocnik['label'],
        'cil_label' => $cil['label'],
        'zaklad' => $vybrana ? $vybrana['uc'] : null,
        'zaklad_nazev' => $vybrana ? 'ÚČ ' . $vybrana['nazev'] : 'ÚČ',
        'utocnost' => $vybrana ? $vybrana['utocnost'] : 0,
        'zbran_id' => $vybrana ? $vybrana['id'] : null,
        'varianty' => $utocnik['zbrane'],
        'opravy' => $opravy,
        'modifikator' => 0,
        'upozorneni' => $upozorneni,
        'rozbeh' => $rozbeh,
        'hody' => null,
        'soucet_hodu' => null,
        'celkem' => null,
    ];
}

// Založí výzvu k útoku. Volá tah_akce.php uvnitř transakce po provedení pohybu; $utocnikToken je
// řádek tokenu už s novou polohou (cíl se načte znovu). $rozbeh = má útok bonus za rozběh (HB).
function dracak_vtt_utok_zahaj(PDO $pdo, array $mapa, array $info, array $utocnikToken, int $cilTokenId, bool $rozbeh, int $ucetId): int
{
    $mapaId = (int)$mapa['id'];
    $utId = (int)$utocnikToken['id'];
    $q = $pdo->prepare('SELECT * FROM tokeny WHERE id = ? AND mapa_id = ?');
    $q->execute([$cilTokenId, $mapaId]);
    $cilToken = $q->fetch();
    $utocnik = dracak_vtt_souboj_bojovnik($pdo, $info[$utId]);
    $cil = dracak_vtt_souboj_bojovnik($pdo, $info[$cilTokenId]);
    $d = dracak_vtt_utok_priprav($pdo, $utocnik, $cil, $rozbeh, dracak_vtt_souboj_utoku_v_kole($pdo, $mapaId, $utId));
    $d['strana'] = $cilToken ? dracak_vtt_utok_strana($mapa, $cilToken, $utocnikToken) : 'predni';
    $id = dracak_vtt_vyzva_vloz($pdo, $mapaId, 'utok', $utId, $cilTokenId, null, $d);
    dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'vyzva_nova', dracak_vtt_vyzva_payload($info, 'utok', $id, $utId, $cilTokenId), $ucetId);
    return $id;
}

// Základ události o výzvě: kdo, proti komu, a zda ji smí vidět hráči (skrytá, jen když tajný je
// útočník i obránce; jména skrytých tvorů hráčům neodcházejí).
function dracak_vtt_vyzva_payload(array $info, string $druh, int $vyzvaId, int $tokenId, ?int $cilId, array $navic = []): array
{
    $viditelny = static fn(?int $id): bool => $id !== null && isset($info[$id]) && $info[$id]['viditelny'];
    return array_merge([
        'vyzva_id' => $vyzvaId,
        'druh' => $druh,
        'token_id' => $tokenId,
        'label' => $viditelny($tokenId) ? $info[$tokenId]['label'] : null,
        'cil_token_id' => $cilId,
        'cil_label' => $viditelny($cilId) ? $info[$cilId]['label'] : null,
        'skryta' => !$viditelny($tokenId) && !$viditelny($cilId),
    ], $navic);
}

// ---------------------------------------------------------------------------------------------
// Hod, obrana, vyhodnocení
// ---------------------------------------------------------------------------------------------

// Obrana po hodu útoku: složky podle obránce (OČ, štít, OZ, životy, naložení), strana útoku,
// smí-li se bránit akcí (zbývá mu akce).
function dracak_vtt_obrana_zaloz(PDO $pdo, array $mapa, array $info, array $utokVyzva, int $ucetId): int
{
    $mapaId = (int)$mapa['id'];
    $obrId = (int)$utokVyzva['cil_token_id'];
    $utId = (int)$utokVyzva['token_id'];
    $obr = dracak_vtt_souboj_bojovnik($pdo, $info[$obrId]);
    $q = $pdo->prepare('SELECT akce_zbyvajici FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ?');
    $q->execute([$mapaId, $obrId]);
    $akce = $q->fetchColumn();
    $ef = $obr['efekty'];
    $nehybny = $ef['nehybny'];
    $duvodNebrani = $nehybny ?? $ef['nemuze_branit'] ?? $ef['nemuze_jednat'];
    $muzeBranit = $duvodNebrani === null && $akce !== false && (int)$akce > 0;
    $upozorneni = [];
    if ($duvodNebrani !== null) {
        $upozorneni[] = $obr['label'] . ' se nemůže bránit zbraní (efekt ' . $duvodNebrani . ').';
    }
    if ($nehybny !== null && $obr['typ'] !== 'postava') {
        $upozorneni[] = 'Nehybná nestvůra: odečten postih −5 (v OČ nestvůry nevím, kolik je bonus za obratnost) — uprav v opravě, kdyby to bylo jinak.';
    }
    $hintDenik = dracak_vtt_tokeny_funkce()['denik_nestvur'] ? ' (nejlíp v deníku nestvůry v panelu tokenu, pak platí pro celý druh)' : '';
    if ($obr['oc'] === null) {
        $upozorneni[] = $obr['typ'] === 'postava'
            ? 'Obranné číslo postavy se nedá spočítat (chybí obratnost) — doplň ho.'
            : 'Obranné číslo nestvůry se z textu bestiáře nedá spočítat (' . ($obr['oc_text'] ?? 'bez údaje') . ') — doplň ho' . $hintDenik . '.';
    }
    $ozZnamo = $obr['typ'] === 'postava' || $obr['oz'] !== null;
    if (!$ozZnamo) {
        $upozorneni[] = 'Obranu zbraní nestvůry (OZ) nestvůra v bestiáři nemá rozepsanou — když se brání, připiš ji do opravy' . $hintDenik . '.';
    }
    $d = [
        'utocnik_label' => $info[$utId]['label'],
        'obrance_label' => $info[$obrId]['label'],
        'zaklad' => $obr['oc'],
        'zaklad_nazev' => 'OČ',
        'stit' => $nehybny !== null ? false : $obr['stit'],
        'nehybny' => $nehybny,
        'bonus_obratnost' => $obr['bonus_obratnost'],
        'efekt_opravy' => $ef['opravy_oc'],
        'duvod_nebrani' => $duvodNebrani,
        'oz' => $obr['oz'],
        'oz_nazev' => $obr['oz_nazev'],
        'oz_znamo' => $ozZnamo,
        'postih_zivoty' => $obr['bojeschopnost']['postih_aktualni'] ?? 0,
        'nalozeni_velke' => $obr['nalozeni_velke'],
        'strana' => $nehybny !== null ? 'predni' : (string)($utokVyzva['data']['strana'] ?? 'predni'),
        'volba' => $muzeBranit ? null : 'nebranit',
        'muze_branit' => $muzeBranit,
        'modifikator' => 0,
        'upozorneni' => $upozorneni,
        'utok_celkem' => (int)$utokVyzva['data']['celkem'],
        'utocnost' => (int)($utokVyzva['data']['utocnost'] ?? 0),
        'hody' => null,
        'soucet_hodu' => null,
        'celkem' => null,
    ];
    $id = dracak_vtt_vyzva_vloz($pdo, $mapaId, 'obrana', $obrId, $utId, (int)$utokVyzva['id'], $d);
    dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'vyzva_nova', dracak_vtt_vyzva_payload($info, 'obrana', $id, $obrId, $utId), $ucetId);
    return $id;
}

// Odečte životy (nejníž na 0, jako hp_uprava.php) a zapíše hp_zmena. Vrací
// ['hp_pred', 'hp_nove', 'max_hp', 'vyrazen'].
function dracak_vtt_souboj_zranit(PDO $pdo, int $svetId, int $mapaId, array $infoTokenu, int $zraneni, int $ucetId): array
{
    $tabulka = $infoTokenu['typ_entity'] === 'nestvura_instance' ? 'nestvura_instance' : 'postavy';
    $q = $pdo->prepare("SELECT aktualni_hp, max_hp FROM $tabulka WHERE id = ? FOR UPDATE");
    $q->execute([$infoTokenu['entita_id']]);
    $r = $q->fetch();
    if (!$r) {
        return ['hp_pred' => 0, 'hp_nove' => 0, 'max_hp' => 0, 'vyrazen' => false];
    }
    $pred = (int)$r['aktualni_hp'];
    $nove = max(0, $pred - $zraneni);
    $pdo->prepare("UPDATE $tabulka SET aktualni_hp = ? WHERE id = ?")->execute([$nove, $infoTokenu['entita_id']]);
    dracak_vtt_log_event($svetId, $mapaId, 'hp_zmena', [
        'typ_entity' => $infoTokenu['typ_entity'],
        'entita_id' => $infoTokenu['entita_id'],
        'nove_hp' => $nove,
        'max_hp' => (int)$r['max_hp'],
        'zdroj' => 'souboj',
    ], $ucetId);
    return ['hp_pred' => $pred, 'hp_nove' => $nove, 'max_hp' => (int)$r['max_hp'], 'vyrazen' => dracak_vtt_souboj_vyrazen($pdo, $infoTokenu)];
}

// Vyhodnotí hozenou obranu: zásah, zranění, životy, akce obránce, událost; obě výzvy skončí.
// Volat uvnitř transakce se zamčeným kolo_stav. Vrací výsledek pro odpověď.
function dracak_vtt_utok_vyhodnot(PDO $pdo, array $mapa, array $info, array $utokVyzva, array $obranaVyzva, int $ucetId): array
{
    $mapaId = (int)$mapa['id'];
    $svetId = (int)$mapa['svet_id'];
    $utok = (int)$utokVyzva['data']['celkem'];
    $obrana = (int)$obranaVyzva['data']['celkem'];
    $utocnost = (int)($utokVyzva['data']['utocnost'] ?? 0);
    $zasah = $utok >= $obrana;
    $zraneni = $zasah ? max(1, $utok - $obrana + $utocnost) : 0;
    $obrId = (int)$obranaVyzva['token_id'];
    $utId = (int)$utokVyzva['token_id'];

    $hp = ['hp_pred' => null, 'hp_nove' => null, 'max_hp' => null, 'vyrazen' => false];
    if ($zraneni > 0) {
        $hp = dracak_vtt_souboj_zranit($pdo, $svetId, $mapaId, $info[$obrId], $zraneni, $ucetId);
    }
    // Obrana akcí (h1623) stojí obránce jednu akci.
    $akceObrance = null;
    if (($obranaVyzva['data']['volba'] ?? null) === 'branit') {
        $pdo->prepare('UPDATE kolo_iniciativa SET akce_zbyvajici = GREATEST(0, akce_zbyvajici - 1) WHERE mapa_id = ? AND token_id = ?')
            ->execute([$mapaId, $obrId]);
        $q = $pdo->prepare('SELECT akce_zbyvajici FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ?');
        $q->execute([$mapaId, $obrId]);
        $akceObrance = $q->fetchColumn();
        $akceObrance = $akceObrance !== false ? (int)$akceObrance : null;
    }
    $vysledek = [
        'zasah' => $zasah,
        'zraneni' => $zraneni,
        'utok' => $utok,
        'obrana' => $obrana,
        'utocnost' => $utocnost,
        'hp_pred' => $hp['hp_pred'],
        'hp_nove' => $hp['hp_nove'],
        'max_hp' => $hp['max_hp'],
        'vyrazen' => $hp['vyrazen'],
        'branil_se' => ($obranaVyzva['data']['volba'] ?? null) === 'branit',
        'obrance_akce_zbyvajici' => $akceObrance,
    ];
    $utokVyzva['data']['vysledek'] = $vysledek;
    $obranaVyzva['data']['vysledek'] = $vysledek;
    dracak_vtt_vyzva_uloz($pdo, $utokVyzva['id'], $utokVyzva['data'], 'hotovo');
    dracak_vtt_vyzva_uloz($pdo, $obranaVyzva['id'], $obranaVyzva['data'], 'hotovo');
    // Do události jen to, co smí vidět každý: útočnost zbraně a životy skrytého obránce ne.
    $verejne = $vysledek;
    unset($verejne['utocnost']);
    if (!$info[$obrId]['viditelny']) {
        $verejne['hp_pred'] = $verejne['hp_nove'] = $verejne['max_hp'] = null;
        $verejne['vyrazen'] = false;
    }
    dracak_vtt_log_event($svetId, $mapaId, 'souboj_vysledek',
        dracak_vtt_vyzva_payload($info, 'vysledek', $utokVyzva['id'], $utId, $obrId, $verejne), $ucetId);
    return $vysledek;
}

// ---------------------------------------------------------------------------------------------
// Výzvy pro klienta
// ---------------------------------------------------------------------------------------------

// Otevřené výzvy mapy tak, jak je smí vidět tenhle účet. Čísla jsou už spočítaná serverem (klient
// nic nepočítá): složky, součet bez hodu, hody, celkem. Hráč nevidí nebezpečnost pasti ani jména
// skrytých tvorů; "smi_hodit" / "smi_volit" říkají, které tlačítko má dostat.
function dracak_vtt_vyzvy_klient(PDO $pdo, array $user, bool $jePj, array $info, int $mapaId): array
{
    $vystup = [];
    foreach (dracak_vtt_vyzva_otevrene($pdo, $mapaId) as $v) {
        $t = $v['token_id'];
        $c = $v['cil_token_id'];
        $tViditelny = isset($info[$t]) && $info[$t]['viditelny'];
        $cViditelny = $c !== null && isset($info[$c]) && $info[$c]['viditelny'];
        if (!$jePj && !$tViditelny && !$cViditelny) {
            continue;
        }
        $d = $v['data'];
        $cislo = dracak_vtt_vyzva_cislo($v['druh'], $d);
        $ovlada = isset($info[$t]) && dracak_vtt_souboj_ovlada($user, $jePj, $info[$t]);
        $pripraveno = $v['stav'] === 'ceka' && $cislo !== null && ($v['druh'] !== 'obrana' || ($d['volba'] ?? null) !== null)
            && ($v['druh'] !== 'past' || ($d['nebezpecnost'] ?? null) !== null);
        $radek = [
            'id' => $v['id'],
            'druh' => $v['druh'],
            'stav' => $v['stav'],
            'rodic_id' => $v['rodic_id'],
            'token_id' => $t,
            'label' => $jePj || $tViditelny ? ($info[$t]['label'] ?? '?') : 'Neznámý tvor',
            'cil_token_id' => $jePj || $cViditelny ? $c : null,
            'cil_label' => $c === null ? null : ($jePj || $cViditelny ? ($info[$c]['label'] ?? '?') : 'Neznámý tvor'),
            'smi_hodit' => $ovlada && $pripraveno,
            'ovladam' => $ovlada,
            'je_pj' => $jePj,
            'zaklad' => $d['zaklad'] ?? null,
            'zaklad_nazev' => $d['zaklad_nazev'] ?? '',
            'opravy' => dracak_vtt_vyzva_opravy($v['druh'], $d),
            'modifikator' => (int)($d['modifikator'] ?? 0),
            'cislo' => $cislo,
            'upozorneni' => $d['upozorneni'] ?? [],
            'kostka' => $v['druh'] === 'past' ? 10 : ($v['druh'] === 'tik' ? (int)($d['typ'] ?? 6) : 6),
            'hody' => $d['hody'] ?? null,
            'soucet_hodu' => $d['soucet_hodu'] ?? null,
            'celkem' => $d['celkem'] ?? null,
            'strana' => $d['strana'] ?? null,
            'popis' => $d['popis'] ?? null,
        ];
        if ($v['druh'] === 'utok') {
            $radek['varianty'] = $d['varianty'] ?? [];
            $radek['zbran_id'] = $d['zbran_id'] ?? null;
            $radek['utocnost'] = (int)($d['utocnost'] ?? 0);
            $radek['rozbeh'] = !empty($d['rozbeh']);
        } elseif ($v['druh'] === 'obrana') {
            $radek['volba'] = $d['volba'] ?? null;
            $radek['muze_branit'] = !empty($d['muze_branit']);
            $radek['duvod_nebrani'] = $d['duvod_nebrani'] ?? null;
            $radek['nehybny'] = !empty($d['nehybny']);
            $radek['utok_celkem'] = $d['utok_celkem'] ?? null;
            $radek['utocnik_label'] = $jePj || ($c !== null && $cViditelny) ? ($d['utocnik_label'] ?? null) : 'Neznámý tvor';
            $radek['smi_volit'] = $ovlada && $v['stav'] === 'ceka';
            $radek['oz_znamo'] = !empty($d['oz_znamo']);
        } elseif ($v['druh'] === 'tik') {
            $radek['pocet_kostek'] = (int)($d['pocet'] ?? 1);
            $radek['efekt_nazev'] = $d['efekt_nazev'] ?? null;
            $radek['znamenko'] = (int)($d['znamenko'] ?? -1);
        } elseif ($v['druh'] === 'past') {
            $radek['nebezpecnost'] = $jePj ? ($d['nebezpecnost'] ?? null) : null;
            $radek['nebezpecnost_nastavena'] = ($d['nebezpecnost'] ?? null) !== null;
            $radek['vysledek'] = $d['vysledek'] ?? null;
            $radek['nebezpecnost_nazev'] = $d['nebezpecnost_nazev'] ?? null;
            $radek['vlastnost'] = $d['vlastnost'] ?? null;
        }
        // Staty nestvůr (ÚČ, OČ, opravy, útočnost) jsou tajemství PJ stejně jako bestiář: hráč u hodu nestvůry vidí
        // jen hody a celkový součet, ne rozpis čísla.
        if (!$jePj && isset($info[$t]) && $info[$t]['typ_entity'] !== 'postava') {
            $radek['zaklad'] = null;
            $radek['zaklad_nazev'] = '';
            $radek['opravy'] = [];
            $radek['modifikator'] = 0;
            $radek['cislo'] = null;
            $radek['upozorneni'] = [];
            $radek['varianty'] = [];
            $radek['zbran_id'] = null;
            $radek['utocnost'] = 0;
            $radek['rozbeh'] = false;
        }
        $vystup[] = $radek;
    }
    return $vystup;
}
