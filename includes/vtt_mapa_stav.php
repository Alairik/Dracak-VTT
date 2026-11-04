<?php
declare(strict_types=1);

// Stav jedné mapy pro klienta (tokeny, efekty, pořadí tahů, postavy k
// položení). Jediný zdroj pravdy pro počáteční vykreslení v hra/mapa.php
// i pro obnovu "na místě" přes hra/api/mapa_stav.php — díky tomu se po
// přidání tokenu, hodu iniciativy, novém kole… stránka už nepřenačítá a
// hráčům nemizí otevřené panely, zoom ani vybraný token.
//
// Počítá se pro konkrétního uživatele (owned / na_tahu / skryté tokeny),
// takže se nikdy nesmí cachovat napříč účty.

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_iniciativa.php';
require_once __DIR__ . '/vtt_kresby.php';
require_once __DIR__ . '/vtt_souboj.php';

// Stav ručně spouštěného SQL (migrace 0084) — jedna sada dotazů na
// information_schema za požadavek. Mapa je napsaná tak, aby fungovala i
// před spuštěním SQL (nové funkce se pak jen schovají).
function dracak_vtt_ddl_mapa(): array
{
    static $stav = null;
    if ($stav !== null) {
        return $stav;
    }
    $stav = ['sloupce' => [], 'typ_entity' => '', 'udalosti_varchar' => false, 'tabulky' => [], 'kolo_sloupce' => [], 'efekty_sloupce' => [], 'ini_sloupce' => [], 'videni_sloupce' => []];
    try {
        $pdo = dracak_db();
        foreach ($pdo->query(
            'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "tokeny"'
        )->fetchAll(PDO::FETCH_NUM) as [$nazev, $typ]) {
            $stav['sloupce'][$nazev] = true;
            if ($nazev === 'typ_entity') {
                $stav['typ_entity'] = (string)$typ;
            }
        }
        $typUdalosti = $pdo->query(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "svet_udalosti" AND COLUMN_NAME = "typ"'
        )->fetchColumn();
        $stav['udalosti_varchar'] = $typUdalosti !== false && strtolower((string)$typUdalosti) === 'varchar';
        foreach ($pdo->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ("svet_obrazky", "mapa_kresby", "kolo_ucastnik", "souboj_vyzva", "nestvura_staty_sveta", "efekt_slozky")'
        )->fetchAll(PDO::FETCH_COLUMN) as $tabulka) {
            $stav['tabulky'][$tabulka] = true;
        }
        foreach ($pdo->query(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "kolo_stav"'
        )->fetchAll(PDO::FETCH_COLUMN) as $sloupec) {
            $stav['kolo_sloupce'][$sloupec] = true;
        }
        foreach ($pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ("kolo_ucastnik", "kolo_iniciativa", "predmety")
               AND COLUMN_NAME IN ("utoku_za_2_kola", "zavazek", "zavazek_akci", "zavazek_hotovo", "akce_hotovo", "chvat", "trida_zbrane", "ini_postih", "ini_prenos")'
        )->fetchAll(PDO::FETCH_NUM) as [$tabulka, $sloupec]) {
            $stav['ini_sloupce'][$tabulka . '.' . $sloupec] = true;
        }
        foreach ($pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME = "mapy" AND COLUMN_NAME = "osvetleni")
               OR (TABLE_NAME = "svet" AND COLUMN_NAME = "aktualni_smena")
               OR (TABLE_NAME = "mapy" AND COLUMN_NAME = "mil_na_1000px")
               OR (TABLE_NAME = "tokeny" AND COLUMN_NAME IN ("svetlo_sahy", "svetlo_typ", "videni_infra_sahy", "studeny", "videni_noc_sahy", "svetlo_zbyva")))'
        )->fetchAll(PDO::FETCH_NUM) as [$tabulka, $sloupec]) {
            $stav['videni_sloupce'][$tabulka . '.' . $sloupec] = true;
        }
        foreach ($pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ("efekty", "aktivni_efekty")
               AND COLUMN_NAME IN ("mechanika_stav", "trvani_kol", "kolo_se_nepocita", "opakovani", "nasazeno_kolo", "kostky", "kouzlo_id", "akci_celkem", "akci_hotovo", "kouzleni_data")'
        )->fetchAll(PDO::FETCH_NUM) as [$tabulka, $sloupec]) {
            $stav['efekty_sloupce'][$tabulka . '.' . $sloupec] = true;
        }
    } catch (Throwable $e) {
        // information_schema nedostupné — nové funkce zůstanou vypnuté
    }
    return $stav;
}

function dracak_vtt_tokeny_ma_sloupec(string $sloupec): bool
{
    return isset(dracak_vtt_ddl_mapa()['sloupce'][$sloupec]);
}

// Které z nových funkcí mapy jsou v DB k dispozici (UI podle toho nabízí
// nebo schovává ovládání, API je podle toho povolují).
function dracak_vtt_tokeny_funkce(): array
{
    $ddl = dracak_vtt_ddl_mapa();
    $sl = $ddl['sloupce'];
    $udalosti = $ddl['udalosti_varchar'];
    return [
        'smer' => isset($sl['smer']) && $udalosti,
        'velikost' => isset($sl['velikost_sahu']) && $udalosti,
        'barva' => isset($sl['barva']) && $udalosti,
        'zamek' => isset($sl['zamceno']) && $udalosti,
        'nazev' => isset($sl['nazev']) && $udalosti,
        'obrazek' => isset($sl['obrazek_id']) && $udalosti && isset($ddl['tabulky']['svet_obrazky']),
        'hexy' => isset($sl['hexu']) && $udalosti,
        'obecny' => str_contains($ddl['typ_entity'], 'obecny'),
        'kresby' => $udalosti && isset($ddl['tabulky']['mapa_kresby']),
        'udalosti' => $udalosti,
        // Souboj s fázemi a půlkami kola (migrace 0085) — bez ručního SQL
        // zůstává dnešní jednoduchá iniciativa.
        'souboj' => $udalosti && isset($ddl['tabulky']['kolo_ucastnik'])
            && isset($ddl['kolo_sloupce']['faze'], $ddl['kolo_sloupce']['polovina'], $ddl['kolo_sloupce']['prekvapeni']),
        // Výzvy k hodu (útok, obrana, past; tabulka souboj_vyzva z ručního SQL) — bez nich se útok
        // jen zapíše do logu.
        // Deník nestvůry (tabulka nestvura_staty_sveta z ručního SQL): čísla pro boj, která PJ doplní jednou za druh.
        'denik_nestvur' => isset($ddl['tabulky']['nestvura_staty_sveta']),
        'vyzvy' => $udalosti && isset($ddl['tabulky']['kolo_ucastnik'], $ddl['tabulky']['souboj_vyzva'])
            && isset($ddl['kolo_sloupce']['faze'], $ddl['kolo_sloupce']['polovina'], $ddl['kolo_sloupce']['prekvapeni']),
        // Efekty s mechanikou (tabulka efekt_slozky + nové sloupce efektů a nasazených efektů z ručního SQL):
        // bez nich jsou efekty jen štítky.
        // Iniciativa v2 (ruční SQL: kolo_ucastnik.utoku_za_2_kola, kolo_iniciativa.zavazek…): jedno pole „útoků za 2 kola“
        // místo zaškrtávání bonusů a hlídání slíbeného úkonu. Bez nich zůstává dosavadní způsob.
        'iniciativa_v2' => $udalosti && isset($ddl['tabulky']['kolo_ucastnik'])
            && isset($ddl['ini_sloupce']['kolo_ucastnik.utoku_za_2_kola'], $ddl['ini_sloupce']['kolo_iniciativa.zavazek'],
                $ddl['ini_sloupce']['kolo_iniciativa.zavazek_akci'], $ddl['ini_sloupce']['kolo_iniciativa.zavazek_hotovo'],
                $ddl['ini_sloupce']['kolo_iniciativa.akce_hotovo']),
        // Zbraně s údajem o držení, třídě a postihu k iniciativě (ruční SQL: predmety.chvat, trida_zbrane, ini_postih):
        // postih držené zbraně se přičte k iniciativě sám, místo ručního zaškrtávání „Obouruční zbraň“ / „Těžká kuše“.
        // Přenos výsledku iniciativy ≤ 0 do dalšího kola (ruční SQL: kolo_ucastnik.ini_prenos, migrace 0104): bez sloupce se nepřenáší.
        'ini_prenos' => $udalosti && isset($ddl['tabulky']['kolo_ucastnik']) && isset($ddl['ini_sloupce']['kolo_ucastnik.ini_prenos']),
        // Vidění a světlo (ruční SQL, migrace 0107: mapy.osvetleni, tokeny.svetlo_sahy…): mapa „Tma“ a zdroje světla na tokenech.
        'videni' => isset($ddl['videni_sloupce']['mapy.osvetleni'], $ddl['videni_sloupce']['tokeny.svetlo_sahy'], $ddl['videni_sloupce']['tokeny.svetlo_typ'],
            $ddl['videni_sloupce']['tokeny.videni_infra_sahy'], $ddl['videni_sloupce']['tokeny.studeny']),
        // Noční vidění (ruční SQL, migrace 0108: tokeny.videni_noc_sahy) a stav mapy „Noc“: bez sloupce se řádek v panelu tokenu nezobrazí.
        'videni_noc' => isset($ddl['videni_sloupce']['tokeny.videni_noc_sahy']),
        // Hodiny světa (ruční SQL, migrace 0109): čas ve dni (svet.aktualni_smena) a hoření světel (tokeny.svetlo_zbyva). Bez sloupců zůstává všechno jako dosud.
        'hodiny' => isset($ddl['videni_sloupce']['svet.aktualni_smena']),
        // Měřítko světové mapy (ruční SQL, migrace 0110: mapy.mil_na_1000px): měřidlo ukáže míle a pošle je do kalkulačky cesty.
        'meritko' => isset($ddl['videni_sloupce']['mapy.mil_na_1000px']),
        'svetlo_zbyva' => isset($ddl['videni_sloupce']['tokeny.svetlo_zbyva']) && isset($ddl['videni_sloupce']['tokeny.svetlo_sahy']),
        'zbrane_ini' => isset($ddl['ini_sloupce']['predmety.chvat'], $ddl['ini_sloupce']['predmety.trida_zbrane'], $ddl['ini_sloupce']['predmety.ini_postih']),
        // Postup kouzlení na štítku „Kouzlí“ (ruční SQL, migrace 0113: aktivni_efekty.kouzlo_id, akci_celkem, akci_hotovo): hra počítá akce kouzlení podle vyvolání kouzla.
        'kouzleni_postup' => isset($ddl['efekty_sloupce']['aktivni_efekty.kouzlo_id'], $ddl['efekty_sloupce']['aktivni_efekty.akci_celkem'], $ddl['efekty_sloupce']['aktivni_efekty.akci_hotovo']),
        // Automatické seslání kouzla po předposlední akci kouzlení (ruční SQL, migrace 0114: aktivni_efekty.kouzleni_data): bez sloupce se účinek zadává z deníku.
        'kouzleni_ucinek' => isset($ddl['efekty_sloupce']['aktivni_efekty.kouzleni_data']) && isset($ddl['efekty_sloupce']['aktivni_efekty.akci_celkem']),
        'efekty_mech' => isset($ddl['tabulky']['efekt_slozky'])
            && isset($ddl['efekty_sloupce']['efekty.mechanika_stav'], $ddl['efekty_sloupce']['efekty.trvani_kol'],
                $ddl['efekty_sloupce']['efekty.kolo_se_nepocita'], $ddl['efekty_sloupce']['efekty.opakovani'],
                $ddl['efekty_sloupce']['aktivni_efekty.nasazeno_kolo'], $ddl['efekty_sloupce']['aktivni_efekty.kostky']),
    ];
}

// Klíče, kterými se mění vzhled tokenu (token_uprava.php, událost token_zmena),
// a sloupce v tabulce tokeny, které jim odpovídají.
const DRACAK_VTT_TOKEN_KLIC_NA_SLOUPEC = [
    'smer' => 'smer',
    'velikost' => 'velikost_sahu',
    'hexu' => 'hexu',
    'barva' => 'barva',
    'zamceno' => 'zamceno',
    'nazev' => 'nazev',
    'obrazek_id' => 'obrazek_id',
    'viditelny' => 'viditelny_hracum',
    'z' => 'z_poradi',
    'svetlo' => 'svetlo_sahy',
    'svetlo_typ' => 'svetlo_typ',
    'infra' => 'videni_infra_sahy',
    'noc' => 'videni_noc_sahy',
    'zbyva' => 'svetlo_zbyva',
    'studeny' => 'studeny',
];

// Hodnota klíče z řádku tabulky tokeny ve tvaru, v jakém ji nese token_zmena.
function dracak_vtt_token_hodnota_klice(array $radek, string $klic)
{
    $sloupec = DRACAK_VTT_TOKEN_KLIC_NA_SLOUPEC[$klic] ?? null;
    if ($sloupec === null || !array_key_exists($sloupec, $radek)) {
        return null;
    }
    $h = $radek[$sloupec];
    return match ($klic) {
        'smer', 'z', 'hexu' => (int)$h,
        'velikost' => (float)$h,
        'zamceno', 'viditelny', 'studeny' => (bool)$h,
        'svetlo' => $h === null ? null : (float)$h,
        'infra', 'noc', 'zbyva' => $h === null ? null : (int)$h,
        'obrazek_id' => $h === null ? null : (int)$h,
        default => $h === null ? null : (string)$h,
    };
}

// Doplňky tokenu jen pro PJ: podtitul pod jménem v raid panelu (druh a úroveň, jméno hráče) a únava postavy.
function dracak_vtt_mapa_stav_pj_info(array $t, array $i): array
{
    $unava = null;
    if ($t['typ_entity'] === 'postava') {
        $hracova = (int)($i['vlastnik_ucet_id'] ?? 0) > 0 && (int)$i['vlastnik_ucet_id'] !== (int)($i['pj_ucet_id'] ?? 0);
        $druh = trim((string)($i['povolani'] ?? '') . ((int)($i['uroven'] ?? 0) > 0 ? ' ' . (int)$i['uroven'] . '. úr.' : ''));
        $podtitul = ($druh !== '' ? $druh : 'Postava') . ($hracova ? ' · hráč ' . (string)($i['hrac'] ?? '?') : ' · NPC');
        $unava = isset($i['unava']) ? (int)$i['unava'] : null;
    } elseif ($t['typ_entity'] === 'nestvura_instance') {
        $podtitul = 'Nestvůra' . (($i['druh'] ?? '') !== '' ? ' · ' . (string)$i['druh'] : '');
    } else {
        $podtitul = 'Značka nebo předmět';
    }
    return ['podtitul' => $podtitul, 'unava' => $unava];
}

function dracak_vtt_mapa_stav(PDO $pdo, array $user, array $mapa, bool $jePj): array
{
    $mapaId = (int)$mapa['id'];

    // Pořadí je důležité: nejdřív poslední událost, teprve potom snímek
    // tokenů — jinak by mezi nimi mohla vzniknout mezera, ve které se
    // událost ztratí (stejná úvaha jako v hra/api/stav.php).
    $posledniUdalostId = (int)($pdo->query('SELECT MAX(id) FROM svet_udalosti WHERE svet_id = ' . (int)$mapa['svet_id'])->fetchColumn() ?: 0);

    $stmt = $pdo->prepare(
        'SELECT t.*,
                p.nazev AS postava_nazev, p.vlastnik_ucet_id, p.aktualni_hp AS postava_hp, p.max_hp AS postava_max_hp,
                p.aktualni_magenergie AS postava_magenergie, p.max_magenergie AS postava_max_magenergie,
                ni.nazev_instance, ni.aktualni_hp AS nestvura_hp, ni.max_hp AS nestvura_max_hp
         FROM tokeny t
         LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
         LEFT JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = "nestvura_instance"
         WHERE t.mapa_id = ?
         ORDER BY t.z_poradi, t.id'
    );
    $stmt->execute([$mapaId]);
    $radky = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM kolo_stav WHERE mapa_id = ?');
    $stmt->execute([$mapaId]);
    $koloStav = $stmt->fetch() ?: null;
    $aktivniTokenId = $koloStav ? (int)($koloStav['aktivni_token_id'] ?? 0) : 0;

    // Mapa „Tma“: hráč nedostane tokeny, které zrovna nevidí (jen družina a to, co osvětlí světlo nebo prozradí infravidění).
    $skryteVidenim = [];
    if (!$jePj) {
        require_once __DIR__ . '/vtt_videni.php';
        $skryteVidenim = dracak_vtt_videni_skryte_tokeny($pdo, $mapa, (int)$user['id']);
    }
    // Jen pro PJ (raid panel, assets/js/raid_panel.js): druh/povolání, jméno hráče a únava u každého tokenu — jedním dotazem.
    $pjInfo = [];
    if ($jePj) {
        $q = $pdo->prepare(
            'SELECT t.id, p.uroven, p.unava, p.vlastnik_ucet_id, po.nazev AS povolani, u.jmeno AS hrac, n.nazev AS druh, s.pj_ucet_id
             FROM tokeny t
             JOIN mapy m ON m.id = t.mapa_id JOIN svet s ON s.id = m.svet_id
             LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
             LEFT JOIN povolani po ON po.id = p.povolani_id
             LEFT JOIN ucty u ON u.id = p.vlastnik_ucet_id
             LEFT JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = "nestvura_instance"
             LEFT JOIN nestvury n ON n.id = ni.nestvura_id
             WHERE t.mapa_id = ?'
        );
        $q->execute([$mapaId]);
        foreach ($q->fetchAll() as $r) {
            $pjInfo[(int)$r['id']] = $r;
        }
    }
    // Strana pro raid panel (všichni): 'hrac' = postava, kterou vlastní někdo jiný než PJ světa (družina), jinak 'pj'.
    $pjUcetId = (int)($pdo->query('SELECT pj_ucet_id FROM svet WHERE id = ' . (int)$mapa['svet_id'])->fetchColumn() ?: 0);
    // Neviditelní tvorové (efekt Neviditelnost): hráči je vidí jen u vlastních postav (viz dracak_vtt_videni_skryte_tokeny), PJ a vlastník dostanou příznak a token se jim
    // kreslí jako polopropustný.
    require_once __DIR__ . '/vtt_videni.php';
    $neviditelne = dracak_vtt_videni_neviditelne_tokeny($pdo, $mapaId);
    $tokeny = [];
    foreach ($radky as $t) {
        if (!$jePj && (!(bool)$t['viditelny_hracum'] || isset($skryteVidenim[(int)$t['id']]))) {
            continue;
        }
        $jePostava = $t['typ_entity'] === 'postava';
        $jeNestvura = $t['typ_entity'] === 'nestvura_instance';
        $owned = $jePj || ($jePostava && (int)($t['vlastnik_ucet_id'] ?? 0) === (int)$user['id']);
        $nazevRucne = isset($t['nazev']) && $t['nazev'] !== null && $t['nazev'] !== '' ? (string)$t['nazev'] : null;
        $label = $jePostava ? (string)$t['postava_nazev'] : ($jeNestvura ? (string)$t['nazev_instance'] : '');
        if ($nazevRucne !== null) {
            $label = $nazevRucne;
        }
        $hp = $jePostava ? $t['postava_hp'] : ($jeNestvura ? $t['nestvura_hp'] : null);
        $maxHp = $jePostava ? $t['postava_max_hp'] : ($jeNestvura ? $t['nestvura_max_hp'] : null);
        $maxMag = $jePostava ? $t['postava_max_magenergie'] : null;
        $tokeny[] = [
            'id' => (int)$t['id'],
            'typ_entity' => (string)$t['typ_entity'],
            'entita_id' => (int)$t['entita_id'],
            'x' => (int)$t['x'],
            'y' => (int)$t['y'],
            'z' => (int)$t['z_poradi'],
            'viditelny' => (bool)$t['viditelny_hracum'],
            'label' => $label,
            'hp' => $hp !== null ? (int)$hp : null,
            'max_hp' => $maxHp !== null ? (int)$maxHp : null,
            'magenergie' => $jePostava && $t['postava_magenergie'] !== null ? (int)$t['postava_magenergie'] : null,
            'max_magenergie' => ($maxMag !== null && (int)$maxMag > 0) ? (int)$maxMag : null,
            'owned' => $owned,
            'na_tahu' => $jePj || !$koloStav || $aktivniTokenId === (int)$t['id'],
            'efekty' => [],
            'smer' => isset($t['smer']) ? (int)$t['smer'] : 0,
            'velikost' => isset($t['velikost_sahu']) ? (float)$t['velikost_sahu'] : 1.0,
            'hexu' => isset($t['hexu']) ? max(1, (int)$t['hexu']) : 1,
            'barva' => isset($t['barva']) && $t['barva'] !== null && $t['barva'] !== '' ? (string)$t['barva'] : null,
            'zamceno' => isset($t['zamceno']) ? (bool)$t['zamceno'] : false,
            'obrazek_id' => isset($t['obrazek_id']) && $t['obrazek_id'] !== null ? (int)$t['obrazek_id'] : null,
            'svetlo' => isset($t['svetlo_sahy']) && $t['svetlo_sahy'] !== null ? (float)$t['svetlo_sahy'] : null,
            'svetlo_typ' => isset($t['svetlo_typ']) && $t['svetlo_typ'] !== null ? (string)$t['svetlo_typ'] : null,
            'infra' => isset($t['videni_infra_sahy']) && $t['videni_infra_sahy'] !== null ? (int)$t['videni_infra_sahy'] : null,
            'noc' => isset($t['videni_noc_sahy']) && $t['videni_noc_sahy'] !== null ? (int)$t['videni_noc_sahy'] : null,
            'zbyva' => isset($t['svetlo_zbyva']) && $t['svetlo_zbyva'] !== null ? (int)$t['svetlo_zbyva'] : null,
            'studeny' => isset($t['studeny']) ? (bool)$t['studeny'] : false,
            'neviditelny' => isset($neviditelne[(int)$t['id']]),
            'strana' => $jePostava && (int)($t['vlastnik_ucet_id'] ?? 0) > 0 && (int)$t['vlastnik_ucet_id'] !== $pjUcetId ? 'hrac' : 'pj',
        ] + ($jePj ? dracak_vtt_mapa_stav_pj_info($t, $pjInfo[(int)$t['id']] ?? []) : []);
    }

    // Aktivní efekty všech entit s tokenem na téhle mapě — jeden dotaz.
    $entity = [];
    foreach ($tokeny as $t) {
        if ($t['typ_entity'] === 'postava' || $t['typ_entity'] === 'nestvura_instance') {
            $entity[$t['typ_entity'] . ':' . $t['entita_id']] = [$t['typ_entity'], $t['entita_id']];
        }
    }
    $efektyDetail = dracak_vtt_efekty_mapy($pdo, array_values($entity));
    foreach ($tokeny as &$t) {
        $detail = $efektyDetail[$t['typ_entity'] . ':' . $t['entita_id']] ?? [];
        // Texty pro odznak na tokenu ("Název (N kol)") + podrobnosti pro tooltip a panel PJ.
        $t['efekty'] = array_map(
            static fn(array $e): string => $e['nazev'] . ($e['zbyva_kol'] !== null ? ' (' . $e['zbyva_kol'] . ' kol)' : ''),
            $detail
        );
        $t['efekty_detail'] = $detail;
    }
    unset($t);

    // Pořadí tahů: hráč vidí jen tokeny, které na mapě vidí (skrytý token
    // PJ nesmí prozradit ani přes iniciativu).
    $kolo = null;
    $iniciativa = [];
    $ucastnici = [];
    $vyzvy = [];
    if ($koloStav && dracak_vtt_tokeny_funkce()['souboj']) {
        // Souboj s fázemi (migrace 0085): hráč nevidí iniciativu a úmysly
        // nestvůr ani postav ovládaných PJ.
        $sb = dracak_vtt_souboj_klient($pdo, $user, $mapa, $jePj, dracak_vtt_souboj_nacti($pdo, $mapaId));
        $kolo = $sb['kolo'];
        $iniciativa = $sb['iniciativa'];
        $ucastnici = $sb['ucastnici'];
        $vyzvy = $sb['vyzvy'];
    } elseif ($koloStav) {
        $viditelneId = array_flip(array_column($tokeny, 'id'));
        foreach (dracak_vtt_iniciativa_poradi($pdo, $mapaId) as $r) {
            if (!$jePj && !isset($viditelneId[(int)$r['token_id']])) {
                continue;
            }
            $iniciativa[] = [
                'token_id' => (int)$r['token_id'],
                'label' => (string)($r['label'] ?? '?'),
                'hod' => (int)$r['hod'],
                'modifikator' => (int)$r['modifikator'],
                'vysledek' => (int)$r['vysledek'],
                'akce_zbyvajici' => (int)$r['akce_zbyvajici'],
                'akce_celkem' => (int)$r['akce_celkem'],
            ];
        }
        $kolo = [
            'cislo_kola' => (int)$koloStav['cislo_kola'],
            'aktivni_token_id' => $aktivniTokenId ?: null,
        ];
    }

    // Postavy, které ještě nemají na téhle mapě token (PJ všechny ze světa,
    // hráč jen své).
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nazev FROM postavy p
         WHERE p.svet_id = ? AND p.id NOT IN (SELECT entita_id FROM tokeny WHERE mapa_id = ? AND typ_entity = "postava")
         ' . ($jePj ? '' : 'AND p.vlastnik_ucet_id = ?') . '
         ORDER BY p.nazev'
    );
    $stmt->execute($jePj ? [(int)$mapa['svet_id'], $mapaId] : [(int)$mapa['svet_id'], $mapaId, (int)$user['id']]);
    $volnePostavy = $stmt->fetchAll();

    return [
        'posledni_udalost_id' => $posledniUdalostId,
        'tokeny' => $tokeny,
        'kolo' => $kolo,
        'iniciativa' => $iniciativa,
        'ucastnici' => $ucastnici,
        'vyzvy' => $vyzvy,
        'kresby' => dracak_vtt_kresby_pro_mapu($pdo, $mapaId, (int)$user['id'], $jePj),
        'volne_postavy' => array_map(fn($p) => ['id' => (int)$p['id'], 'nazev' => (string)$p['nazev']], $volnePostavy),
    ];
}
