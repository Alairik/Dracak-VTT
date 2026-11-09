<?php
declare(strict_types=1);

// Export a import OBLASTI — kusu světa, který jde přenést jinam (návrh: docs/export-import-oblasti-navrh.md).
//
// Oblast = jedna mapa (nebo mapa, na kterou vede pin) a **všechno pod ní**: mapy, na které z ní vedou piny,
// z nich další a tak dál. Zadání uživatele 2026-11-09: „mělo by to vzít PIN/mapu a vše úrovňové pod ní“.
// Hierarchie map v databázi není — skládá se z pinů (mapa_body.cilova_mapa_id), stejně jako „Mapa míst“
// v liště (dracak_vtt_mapa_strom). Sestup proto jde po pinech dopředu; mapa, kterou už máme, se nepřidává
// znovu, takže zpáteční pin na výchozí mapu oblast nerozšíří.
//
// Co se veze a co ne (podrobně i s důvody v návrhu):
//   VEZE:   mapy, zdi, kresby, piny, tokeny nestvůr a vlastní tokeny, NPC i s výbavou a vlastními čísly,
//           čísla druhů nestvůr pro tenhle svět, obrázky (soubory) a poznámky PJ navázané na ty mapy.
//   NEVEZE: katalog pravidel (nestvůry, předměty, kouzla… je všude stejný — odkazuje se JMÉNEM),
//           postavy a hráče (oblast je místo, ne družina), provozní stav (mlha, log událostí, rozehraný souboj).
//
// Čísla záznamů jsou v databázi globální, takže se v souboru nevezou skutečná id, ale dočasná („mapa:1“).
// Import si je přeloží na nová (dracak_vtt_oblast_import).

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_mapa_stav.php';   // dracak_vtt_tokeny_ma_sloupec() — import funguje i bez novějších sloupců

const DRACAK_OBLAST_FORMAT = 1;          // verze formátu souboru
const DRACAK_OBLAST_MAX_MAP = 200;       // strop map v jedné oblasti (pojistka proti zacyklení přes piny)

// Mapy pod výchozí mapou včetně jí samé, v pořadí, v jakém se na ně narazilo.
function dracak_vtt_oblast_mapy(PDO $pdo, int $svetId, int $startMapaId): array
{
    $vysledek = [];
    $fronta = [$startMapaId];
    $videne = [$startMapaId => true];
    $q = $pdo->prepare('SELECT DISTINCT cilova_mapa_id FROM mapa_body WHERE mapa_id = ? AND cilova_mapa_id IS NOT NULL');
    $qm = $pdo->prepare('SELECT * FROM mapy WHERE id = ? AND svet_id = ?');
    while ($fronta && count($vysledek) < DRACAK_OBLAST_MAX_MAP) {
        $id = array_shift($fronta);
        $qm->execute([$id, $svetId]);
        $mapa = $qm->fetch(PDO::FETCH_ASSOC);
        if (!$mapa) {
            continue;   // pin vede mimo svět nebo na smazanou mapu — přeskoč
        }
        $vysledek[] = $mapa;
        $q->execute([$id]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $cil) {
            $cil = (int)$cil;
            if ($cil > 0 && !isset($videne[$cil])) {
                $videne[$cil] = true;
                $fronta[] = $cil;
            }
        }
    }
    return $vysledek;
}

// Pomůcka: "mapa:3" místo skutečného id
function dracak_vtt_oblast_ref(string $druh, $id): ?string
{
    return $id === null || (int)$id <= 0 ? null : $druh . ':' . (int)$id;
}

function dracak_vtt_oblast_vyber(PDO $pdo, string $sql, array $param): array
{
    $q = $pdo->prepare($sql);
    $q->execute($param);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

// Sestaví obsah oblasti. Vrací ['data' => …, 'soubory' => [cesta_v_zipu => absolutní cesta], 'souhrn' => […]].
function dracak_vtt_oblast_export(PDO $pdo, int $svetId, int $startMapaId): array
{
    $mapy = dracak_vtt_oblast_mapy($pdo, $svetId, $startMapaId);
    if (!$mapy) {
        return ['chyba' => 'Mapa nenalezena v tomhle světě.'];
    }
    $mapaIds = array_map(fn($m) => (int)$m['id'], $mapy);
    $in = implode(',', array_fill(0, count($mapaIds), '?'));
    $koren = dirname(__DIR__);
    $soubory = [];

    $data = ['mapy' => [], 'zdi' => [], 'kresby' => [], 'piny' => [], 'tokeny' => [],
             'npc' => [], 'npc_vybava' => [], 'staty_druhu' => [], 'obrazky' => [], 'poznamky' => []];

    foreach ($mapy as $m) {
        $obrazek = null;
        if (!empty($m['obrazek_cesta'])) {
            $jmeno = basename((string)$m['obrazek_cesta']);
            $zdroj = $koren . '/uploads/mapy/' . $jmeno;
            if (is_file($zdroj)) {
                $soubory['uploads/mapy/' . $jmeno] = $zdroj;
                $obrazek = $jmeno;
            }
        }
        $data['mapy'][] = [
            'ref' => dracak_vtt_oblast_ref('mapa', $m['id']),
            'nazev' => $m['nazev'], 'typ_mapy' => $m['typ_mapy'], 'obrazek' => $obrazek,
            'sirka_px' => $m['sirka_px'], 'vyska_px' => $m['vyska_px'],
            'grid_velikost_px' => $m['grid_velikost_px'], 'grid_typ' => $m['grid_typ'],
            'grid_posun_x' => $m['grid_posun_x'], 'grid_posun_y' => $m['grid_posun_y'],
            'mlha_aktivni' => $m['mlha_aktivni'], 'osvetleni' => $m['osvetleni'] ?? null,
            'mil_na_1000px' => $m['mil_na_1000px'] ?? null,
        ];
    }

    foreach (dracak_vtt_oblast_vyber($pdo, "SELECT * FROM zdi WHERE mapa_id IN ($in)", $mapaIds) as $z) {
        $data['zdi'][] = [
            'mapa' => dracak_vtt_oblast_ref('mapa', $z['mapa_id']),
            'x1' => $z['x1'], 'y1' => $z['y1'], 'x2' => $z['x2'], 'y2' => $z['y2'], 'sirka_px' => $z['sirka_px'],
            'blokuje_pohyb' => $z['blokuje_pohyb'], 'blokuje_vystrel' => $z['blokuje_vystrel'],
            'viditelna_hracum' => $z['viditelna_hracum'], 'dvere' => $z['dvere'] ?? 0, 'otevrene' => $z['otevrene'] ?? 0,
        ];
    }
    foreach (dracak_vtt_oblast_vyber($pdo, "SELECT * FROM mapa_kresby WHERE mapa_id IN ($in)", $mapaIds) as $k) {
        $data['kresby'][] = [
            'mapa' => dracak_vtt_oblast_ref('mapa', $k['mapa_id']),
            'typ' => $k['typ'], 'data' => $k['data'], 'barva' => $k['barva'],
            'sirka' => $k['sirka'], 'viditelne_hracum' => $k['viditelne_hracum'],
        ];
    }
    // Pin vedoucí mimo oblast se neveze jako odkaz, ale zapíše se do „visících přechodů“ — po importu je PJ dopojí.
    $visici = [];
    foreach (dracak_vtt_oblast_vyber($pdo, "SELECT * FROM mapa_body WHERE mapa_id IN ($in)", $mapaIds) as $b) {
        $cil = (int)($b['cilova_mapa_id'] ?? 0);
        $cilVOblasti = $cil > 0 && in_array($cil, $mapaIds, true);
        if ($cil > 0 && !$cilVOblasti) {
            $visici[] = ['pin' => (string)$b['nazev'], 'mapa' => (string)$b['mapa_id']];
        }
        $data['piny'][] = [
            'mapa' => dracak_vtt_oblast_ref('mapa', $b['mapa_id']),
            'cil' => $cilVOblasti ? dracak_vtt_oblast_ref('mapa', $cil) : null,
            'nazev' => $b['nazev'], 'typ' => $b['typ'], 'x' => $b['x'], 'y' => $b['y'],
            'popis' => $b['popis'], 'viditelny_hracum' => $b['viditelny_hracum'],
        ];
    }

    // NPC: ta, co na mapách stojí, i ta, co k nim patří a leží v zásobě.
    $npcSql = "SELECT ni.*, n.nazev AS druh FROM nestvura_instance ni JOIN nestvury n ON n.id = ni.nestvura_id
               WHERE ni.mapa_id IN ($in)";
    $param = $mapaIds;
    try {
        $pdo->query('SELECT lokace_mapa_id FROM nestvura_instance LIMIT 0');
        $npcSql = "SELECT ni.*, n.nazev AS druh FROM nestvura_instance ni JOIN nestvury n ON n.id = ni.nestvura_id
                   WHERE ni.mapa_id IN ($in) OR ni.lokace_mapa_id IN ($in)";
        $param = array_merge($mapaIds, $mapaIds);
    } catch (Throwable $e) {
        // sloupec lokace_mapa_id ještě není (ruční SQL nespuštěné) — vezmou se jen NPC stojící na mapách
    }
    $druhy = [];
    foreach (dracak_vtt_oblast_vyber($pdo, $npcSql, $param) as $n) {
        $druhy[(int)$n['nestvura_id']] = (string)$n['druh'];
        $data['npc'][] = [
            'ref' => dracak_vtt_oblast_ref('npc', $n['id']),
            'druh' => $n['druh'],                       // katalog se neveze — odkaz jménem
            'nazev' => $n['nazev_instance'],
            'mapa' => dracak_vtt_oblast_ref('mapa', $n['mapa_id'] ?? null),
            'lokace' => dracak_vtt_oblast_ref('mapa', $n['lokace_mapa_id'] ?? null),
            'aktualni_hp' => $n['aktualni_hp'], 'max_hp' => $n['max_hp'],
            'jedinecny' => $n['jedinecny'] ?? 0, 'data' => $n['data'] ?? null,
        ];
    }
    if ($data['npc']) {
        $npcIds = array_map(fn($x) => (int)substr((string)$x['ref'], 4), $data['npc']);
        $inN = implode(',', array_fill(0, count($npcIds), '?'));
        foreach (dracak_vtt_oblast_vyber($pdo,
            "SELECT v.*, p.nazev AS predmet, l.nazev AS lektvar FROM nestvura_instance_vybava v
             LEFT JOIN predmety p ON p.id = v.polozka_id AND v.typ_polozky = 'predmet'
             LEFT JOIN lektvary l ON l.id = v.polozka_id AND v.typ_polozky = 'lektvar'
             WHERE v.nestvura_instance_id IN ($inN)", $npcIds) as $v) {
            $data['npc_vybava'][] = [
                'npc' => dracak_vtt_oblast_ref('npc', $v['nestvura_instance_id']),
                'typ_polozky' => $v['typ_polozky'],
                'polozka' => $v['predmet'] ?? $v['lektvar'],   // odkaz jménem z katalogu
                'mnozstvi' => $v['mnozstvi'],
            ];
        }
    }
    // Čísla druhů nestvůr, která PJ v tomhle světě doplnil (jinak by NPC po importu měla jiné hodnoty).
    if ($druhy) {
        $inD = implode(',', array_fill(0, count($druhy), '?'));
        try {
            foreach (dracak_vtt_oblast_vyber($pdo,
                "SELECT s.*, n.nazev AS druh FROM nestvura_staty_sveta s JOIN nestvury n ON n.id = s.nestvura_id
                 WHERE s.svet_id = ? AND s.nestvura_id IN ($inD)", array_merge([$svetId], array_keys($druhy))) as $s) {
                $data['staty_druhu'][] = ['druh' => $s['druh'], 'data' => $s['data']];
            }
        } catch (Throwable $e) {
            // tabulka nestvura_staty_sveta ještě není
        }
    }

    // Tokeny: nestvůry a vlastní tokeny. Postavy ne — oblast je místo, ne družina.
    $obrazkyIds = [];
    foreach (dracak_vtt_oblast_vyber($pdo,
        "SELECT * FROM tokeny WHERE mapa_id IN ($in) AND typ_entity <> 'postava'", $mapaIds) as $t) {
        if (!empty($t['obrazek_id'])) {
            $obrazkyIds[(int)$t['obrazek_id']] = true;
        }
        $data['tokeny'][] = [
            'mapa' => dracak_vtt_oblast_ref('mapa', $t['mapa_id']),
            'typ_entity' => $t['typ_entity'],
            'npc' => $t['typ_entity'] === 'nestvura_instance' ? dracak_vtt_oblast_ref('npc', $t['entita_id']) : null,
            'x' => $t['x'], 'y' => $t['y'], 'z_poradi' => $t['z_poradi'],
            'smer' => $t['smer'] ?? 0, 'velikost_sahu' => $t['velikost_sahu'] ?? 1, 'hexu' => $t['hexu'] ?? 1,
            'barva' => $t['barva'] ?? null, 'zamceno' => $t['zamceno'] ?? 0, 'nazev' => $t['nazev'] ?? null,
            'obrazek' => dracak_vtt_oblast_ref('obrazek', $t['obrazek_id'] ?? null),
            'viditelny_hracum' => $t['viditelny_hracum'],
            'svetlo_sahy' => $t['svetlo_sahy'] ?? null, 'svetlo_typ' => $t['svetlo_typ'] ?? null,
            'videni_infra_sahy' => $t['videni_infra_sahy'] ?? null, 'studeny' => $t['studeny'] ?? 0,
            'videni_noc_sahy' => $t['videni_noc_sahy'] ?? null, 'svetlo_zbyva' => $t['svetlo_zbyva'] ?? null,
        ];
    }
    if ($obrazkyIds) {
        $inO = implode(',', array_fill(0, count($obrazkyIds), '?'));
        foreach (dracak_vtt_oblast_vyber($pdo,
            "SELECT * FROM svet_obrazky WHERE id IN ($inO) AND svet_id = ?",
            array_merge(array_keys($obrazkyIds), [$svetId])) as $o) {
            $jmeno = basename((string)$o['soubor']);
            $zdroj = $koren . '/uploads/obrazky/' . $jmeno;
            if (!is_file($zdroj)) {
                continue;
            }
            $soubory['uploads/obrazky/' . $jmeno] = $zdroj;
            $data['obrazky'][] = [
                'ref' => dracak_vtt_oblast_ref('obrazek', $o['id']),
                'nazev' => $o['nazev'], 'soubor' => $jmeno, 'sirka' => $o['sirka'], 'vyska' => $o['vyska'],
            ];
        }
    }

    // Poznámky PJ navázané na ty mapy. Poznámka o konkrétní postavě se neveze — cizí postava v jiném světě neexistuje.
    foreach (dracak_vtt_oblast_vyber($pdo,
        "SELECT * FROM svet_poznamky WHERE mapa_id IN ($in) AND postava_id IS NULL", $mapaIds) as $p) {
        $data['poznamky'][] = [
            'mapa' => dracak_vtt_oblast_ref('mapa', $p['mapa_id']),
            'text' => $p['text'], 'stitek' => $p['stitek'], 'den_pri_vytvoreni' => $p['den_pri_vytvoreni'],
        ];
    }

    $souhrn = [
        'map' => count($data['mapy']), 'zdi' => count($data['zdi']), 'piny' => count($data['piny']),
        'tokeny' => count($data['tokeny']), 'npc' => count($data['npc']), 'obrazky' => count($data['obrazky']),
        'poznamky' => count($data['poznamky']), 'kresby' => count($data['kresby']),
        'visici_prechody' => $visici,
    ];
    return ['data' => $data, 'soubory' => $soubory, 'souhrn' => $souhrn, 'nazev' => (string)$mapy[0]['nazev']];
}

// --- Import -------------------------------------------------------------------------------------------
//
// Dvoufázový: nejdřív KONTROLA (co v katalogu chybí, kolik čeho přibude, visící přechody) a teprve po
// odsouhlasení ZÁPIS. Import bez kontroly by tiše vyrobil NPC bez pravidel a projevilo by se to až v boji.
//
// Přečíslování: soubor nese dočasná id („mapa:1“), zápis jde v pořadí závislostí a překladová tabulka
// drží staré → nové. Všechno v jedné transakci; DDL se při importu nedělá, takže rollback funguje.

// Co v katalogu pravidel chybí (druhy nestvůr, předměty, lektvary). Vrací seznam textů pro uživatele.
function dracak_vtt_oblast_chybejici(PDO $pdo, array $data): array
{
    $chybi = [];
    $jmena = static function (string $tabulka, array $hledane) use ($pdo): array {
        if (!$hledane) {
            return [];
        }
        $in = implode(',', array_fill(0, count($hledane), '?'));
        $q = $pdo->prepare("SELECT nazev FROM $tabulka WHERE nazev IN ($in)");
        $q->execute(array_values($hledane));
        return array_diff($hledane, $q->fetchAll(PDO::FETCH_COLUMN));
    };
    $druhy = array_values(array_unique(array_filter(array_column($data['npc'] ?? [], 'druh'))));
    foreach ($jmena('nestvury', $druhy) as $d) {
        $chybi[] = 'Nestvůra „' . $d . '“ — není v bestiáři';
    }
    $predmety = $lektvary = [];
    foreach ($data['npc_vybava'] ?? [] as $v) {
        if (($v['polozka'] ?? null) === null) {
            continue;
        }
        if ($v['typ_polozky'] === 'lektvar') { $lektvary[] = $v['polozka']; } else { $predmety[] = $v['polozka']; }
    }
    foreach ($jmena('predmety', array_values(array_unique($predmety))) as $p) {
        $chybi[] = 'Předmět „' . $p . '“ — není v katalogu';
    }
    foreach ($jmena('lektvary', array_values(array_unique($lektvary))) as $l) {
        $chybi[] = 'Lektvar „' . $l . '“ — není v katalogu';
    }
    return $chybi;
}

function dracak_vtt_oblast_kontrola(PDO $pdo, array $data): array
{
    $visici = [];
    foreach ($data['piny'] ?? [] as $p) {
        if (($p['cil'] ?? null) === null && in_array((string)($p['typ'] ?? ''), ['prechod', 'mesto', 'vesnice', 'budova', 'mistnost', 'sklep'], true)) {
            $visici[] = (string)$p['nazev'];
        }
    }
    return [
        'pribude' => [
            'map' => count($data['mapy'] ?? []), 'tokenu' => count($data['tokeny'] ?? []),
            'npc' => count($data['npc'] ?? []), 'pinu' => count($data['piny'] ?? []),
            'zdi' => count($data['zdi'] ?? []), 'poznamek' => count($data['poznamky'] ?? []),
        ],
        'chybi_v_katalogu' => dracak_vtt_oblast_chybejici($pdo, $data),
        'piny_bez_cile' => $visici,
    ];
}

// Zapíše oblast do světa. $soubory = [cesta_v_zipu => dočasná cesta rozbaleného souboru].
// Vrací ['mapy' => [nove_id…], 'prvni_mapa' => id, 'preskoceno' => [texty]].
function dracak_vtt_oblast_import(PDO $pdo, int $svetId, array $data, array $soubory): array
{
    $koren = dirname(__DIR__);
    $preskoceno = [];
    $id = ['mapa' => [], 'npc' => [], 'obrazek' => []];        // dočasné ref → nové id
    $prelozId = static function (?string $ref) use (&$id): ?int {
        if ($ref === null || strpos($ref, ':') === false) {
            return null;
        }
        [$druh, $cislo] = explode(':', $ref, 2);
        return $id[$druh][$ref] ?? null;
    };
    // Název z katalogu → id (katalog se neveze, dohledává se u příjemce).
    $katalog = static function (string $tabulka, ?string $nazev) use ($pdo): ?int {
        if ($nazev === null || $nazev === '') {
            return null;
        }
        static $cache = [];
        $klic = $tabulka . '|' . $nazev;
        if (!array_key_exists($klic, $cache)) {
            $q = $pdo->prepare("SELECT id FROM $tabulka WHERE nazev = ? LIMIT 1");
            $q->execute([$nazev]);
            $v = $q->fetchColumn();
            $cache[$klic] = $v === false ? null : (int)$v;
        }
        return $cache[$klic];
    };

    // 1) obrázky (soubory + řádky) — tokeny na ně odkazují
    $insO = $pdo->prepare('INSERT INTO svet_obrazky (svet_id, nazev, soubor, sirka, vyska) VALUES (?, ?, ?, ?, ?)');
    foreach ($data['obrazky'] ?? [] as $o) {
        $zdroj = $soubory['uploads/obrazky/' . $o['soubor']] ?? null;
        if ($zdroj === null || !is_file($zdroj)) {
            $preskoceno[] = 'Obrázek „' . $o['nazev'] . '“ — soubor v balíčku chybí';
            continue;
        }
        $novy = dracak_vtt_oblast_ulozit_soubor($zdroj, $koren . '/uploads/obrazky', (string)$o['soubor']);
        $insO->execute([$svetId, $o['nazev'], $novy, $o['sirka'], $o['vyska']]);
        $id['obrazek'][$o['ref']] = (int)$pdo->lastInsertId();
    }

    // 2) mapy
    $insM = $pdo->prepare(
        'INSERT INTO mapy (svet_id, nazev, typ_mapy, obrazek_cesta, sirka_px, vyska_px, grid_velikost_px, grid_typ,
                           grid_posun_x, grid_posun_y, mlha_aktivni) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($data['mapy'] ?? [] as $m) {
        $obrazek = null;
        if (!empty($m['obrazek'])) {
            $zdroj = $soubory['uploads/mapy/' . $m['obrazek']] ?? null;
            if ($zdroj !== null && is_file($zdroj)) {
                $obrazek = dracak_vtt_oblast_ulozit_soubor($zdroj, $koren . '/uploads/mapy', (string)$m['obrazek']);
            } else {
                $preskoceno[] = 'Podklad mapy „' . $m['nazev'] . '“ — soubor v balíčku chybí';
            }
        }
        $insM->execute([$svetId, $m['nazev'], $m['typ_mapy'], $obrazek, $m['sirka_px'], $m['vyska_px'],
            $m['grid_velikost_px'], $m['grid_typ'], $m['grid_posun_x'], $m['grid_posun_y'], $m['mlha_aktivni']]);
        $noveId = (int)$pdo->lastInsertId();
        $id['mapa'][$m['ref']] = $noveId;
        // Sloupce z pozdějších migrací se doplní zvlášť, aby import fungoval i bez nich.
        foreach ([['osvetleni', $m['osvetleni'] ?? null], ['mil_na_1000px', $m['mil_na_1000px'] ?? null]] as [$sl, $hod]) {
            if ($hod === null) {
                continue;
            }
            try { $pdo->prepare("UPDATE mapy SET `$sl` = ? WHERE id = ?")->execute([$hod, $noveId]); }
            catch (Throwable $e) { /* sloupec v téhle instalaci není */ }
        }
    }

    // 3) zdi, kresby, piny
    $insZ = $pdo->prepare('INSERT INTO zdi (mapa_id, x1, y1, x2, y2, sirka_px, blokuje_pohyb, blokuje_vystrel, viditelna_hracum) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($data['zdi'] ?? [] as $z) {
        $m = $prelozId($z['mapa']);
        if ($m === null) { continue; }
        $insZ->execute([$m, $z['x1'], $z['y1'], $z['x2'], $z['y2'], $z['sirka_px'], $z['blokuje_pohyb'], $z['blokuje_vystrel'], $z['viditelna_hracum']]);
        $zedId = (int)$pdo->lastInsertId();
        if (!empty($z['dvere'])) {
            try { $pdo->prepare('UPDATE zdi SET dvere = ?, otevrene = ? WHERE id = ?')->execute([1, (int)($z['otevrene'] ?? 0), $zedId]); }
            catch (Throwable $e) { /* sloupce dveří v téhle instalaci nejsou */ }
        }
    }
    try {
        $insK = $pdo->prepare('INSERT INTO mapa_kresby (mapa_id, ucet_id, typ, data, barva, sirka, viditelne_hracum) VALUES (?, NULL, ?, ?, ?, ?, ?)');
        foreach ($data['kresby'] ?? [] as $k) {
            $m = $prelozId($k['mapa']);
            if ($m === null) { continue; }
            $insK->execute([$m, $k['typ'], $k['data'], $k['barva'], $k['sirka'], $k['viditelne_hracum']]);
        }
    } catch (Throwable $e) {
        $preskoceno[] = 'Kresby na mapách — tahle instalace je zatím neumí';
    }
    $insP = $pdo->prepare('INSERT INTO mapa_body (svet_id, mapa_id, cilova_mapa_id, nazev, typ, x, y, popis, viditelny_hracum) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($data['piny'] ?? [] as $p) {
        $m = $prelozId($p['mapa']);
        if ($m === null) { continue; }
        $insP->execute([$svetId, $m, $prelozId($p['cil'] ?? null), $p['nazev'], $p['typ'], $p['x'], $p['y'], $p['popis'], $p['viditelny_hracum']]);
    }

    // 4) NPC (+ čísla druhů a výbava)
    $maNpcSloupce = true;
    try { $pdo->query('SELECT svet_id, lokace_mapa_id, jedinecny, data FROM nestvura_instance LIMIT 0'); }
    catch (Throwable $e) { $maNpcSloupce = false; }
    foreach ($data['npc'] ?? [] as $n) {
        $druhId = $katalog('nestvury', $n['druh'] ?? null);
        if ($druhId === null) {
            $preskoceno[] = 'NPC „' . ($n['nazev'] ?? '?') . '“ — druh „' . ($n['druh'] ?? '?') . '“ není v bestiáři';
            continue;
        }
        $mapa = $prelozId($n['mapa'] ?? null);
        if ($maNpcSloupce) {
            $q = $pdo->prepare('INSERT INTO nestvura_instance (svet_id, mapa_id, lokace_mapa_id, nestvura_id, nazev_instance, aktualni_hp, max_hp, jedinecny, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $q->execute([$svetId, $mapa, $prelozId($n['lokace'] ?? null), $druhId, $n['nazev'], $n['aktualni_hp'], $n['max_hp'], (int)($n['jedinecny'] ?? 0), $n['data'] ?? null]);
        } else {
            if ($mapa === null) { continue; }   // bez sloupců nejde NPC bez mapy uložit
            $q = $pdo->prepare('INSERT INTO nestvura_instance (mapa_id, nestvura_id, nazev_instance, aktualni_hp, max_hp) VALUES (?, ?, ?, ?, ?)');
            $q->execute([$mapa, $druhId, $n['nazev'], $n['aktualni_hp'], $n['max_hp']]);
        }
        $id['npc'][$n['ref']] = (int)$pdo->lastInsertId();
    }
    $insV = $pdo->prepare('INSERT INTO nestvura_instance_vybava (nestvura_instance_id, typ_polozky, polozka_id, mnozstvi) VALUES (?, ?, ?, ?)');
    foreach ($data['npc_vybava'] ?? [] as $v) {
        $npc = $prelozId($v['npc']);
        $polozkaId = $katalog($v['typ_polozky'] === 'lektvar' ? 'lektvary' : 'predmety', $v['polozka'] ?? null);
        if ($npc === null || $polozkaId === null) {
            if ($npc !== null) { $preskoceno[] = 'Výbava „' . ($v['polozka'] ?? '?') . '“ — není v katalogu'; }
            continue;
        }
        $insV->execute([$npc, $v['typ_polozky'], $polozkaId, $v['mnozstvi']]);
    }
    foreach ($data['staty_druhu'] ?? [] as $s) {
        $druhId = $katalog('nestvury', $s['druh'] ?? null);
        if ($druhId === null) { continue; }
        try {
            $pdo->prepare('INSERT INTO nestvura_staty_sveta (svet_id, nestvura_id, data) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE data = VALUES(data)')->execute([$svetId, $druhId, $s['data']]);
        } catch (Throwable $e) { /* tabulka tu není */ }
    }

    // 5) tokeny
    $zakladni = ['mapa_id', 'typ_entity', 'entita_id', 'x', 'y', 'z_poradi', 'viditelny_hracum'];
    $volitelne = ['smer', 'velikost_sahu', 'hexu', 'barva', 'zamceno', 'nazev', 'obrazek_id',
                  'svetlo_sahy', 'svetlo_typ', 'videni_infra_sahy', 'studeny', 'videni_noc_sahy', 'svetlo_zbyva'];
    $maSloupec = [];
    foreach ($volitelne as $sl) {
        $maSloupec[$sl] = dracak_vtt_tokeny_ma_sloupec($sl);
    }
    foreach ($data['tokeny'] ?? [] as $t) {
        $mapa = $prelozId($t['mapa']);
        if ($mapa === null) { continue; }
        $entita = 0;
        if ($t['typ_entity'] === 'nestvura_instance') {
            $entita = $prelozId($t['npc'] ?? null);
            if ($entita === null) { continue; }      // NPC se neimportovalo (chybí druh)
        }
        $sloupce = $zakladni;
        $hodnoty = [$mapa, $t['typ_entity'], $entita, $t['x'], $t['y'], $t['z_poradi'], $t['viditelny_hracum']];
        foreach ($volitelne as $sl) {
            if (!$maSloupec[$sl]) { continue; }
            $klic = $sl === 'obrazek_id' ? 'obrazek' : $sl;
            $hod = $sl === 'obrazek_id' ? $prelozId($t['obrazek'] ?? null) : ($t[$klic] ?? null);
            if ($hod === null) { continue; }
            $sloupce[] = $sl;
            $hodnoty[] = $hod;
        }
        $otazniky = implode(', ', array_fill(0, count($sloupce), '?'));
        $pdo->prepare('INSERT INTO tokeny (`' . implode('`, `', $sloupce) . "`) VALUES ($otazniky)")->execute($hodnoty);
    }

    // 6) poznámky PJ
    $pjId = (int)($pdo->query('SELECT pj_ucet_id FROM svet WHERE id = ' . $svetId)->fetchColumn() ?: 0);
    if ($pjId > 0) {
        $insPo = $pdo->prepare('INSERT INTO svet_poznamky (svet_id, autor_ucet_id, text, den_pri_vytvoreni, mapa_id, stitek) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($data['poznamky'] ?? [] as $p) {
            $m = $prelozId($p['mapa']);
            if ($m === null) { continue; }
            $insPo->execute([$svetId, $pjId, $p['text'], (int)($p['den_pri_vytvoreni'] ?? 0), $m, $p['stitek']]);
        }
    }

    return [
        'mapy' => array_values($id['mapa']),
        'prvni_mapa' => $id['mapa'][$data['mapy'][0]['ref'] ?? ''] ?? null,
        'preskoceno' => $preskoceno,
    ];
}

// Uloží soubor z balíčku pod volným jménem (stejné jméno už v uploads být může — patří něčemu jinému).
function dracak_vtt_oblast_ulozit_soubor(string $zdroj, string $cilovaSlozka, string $jmeno): string
{
    if (!is_dir($cilovaSlozka)) {
        @mkdir($cilovaSlozka, 0775, true);
    }
    $pripona = strtolower(pathinfo($jmeno, PATHINFO_EXTENSION));
    $pripona = preg_match('/^(png|jpg|jpeg|webp|gif)$/', $pripona) ? $pripona : 'png';
    do {
        $nove = 'import_' . bin2hex(random_bytes(8)) . '.' . $pripona;
    } while (file_exists($cilovaSlozka . '/' . $nove));
    copy($zdroj, $cilovaSlozka . '/' . $nove);
    return $nove;
}
