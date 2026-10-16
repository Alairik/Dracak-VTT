<?php
declare(strict_types=1);

// Pravidla pohybu a tvarů v souboji (HB, content/pravidla-hrac.html h1623,
// h1625) jako DATA, ze kterých počítá server i klient. Zdroj pravdy jsou
// tabulky souboj_pohyb a tvary_tvoru (ruční SQL 0086, docs/sql-k-rucnimu-
// spusteni.sql); dokud neexistují, platí stejné hodnoty z těchhle výchozích
// polí (shodu hlídá test při změně obou).
//
// POHYB (jedna akce Krok / Běh / Sprint):
//  - krok 1 hex, běh 2 hexy (kniha h1625), sprint 3 hexy (HB); běh a sprint kdykoli, kromě únavy
//    (rozhodnutí uživatele 2026-10-16; jen_pri_umyslu v tabulce zůstává pro případné omezení)
//  - dovednost Běh na krátké vzdálenosti (h1698) přidá +1 hex při běhu a sprintu
//  - otočení v rámci jedné pohybové akce celkem až o 270° (HB; kniha měla 120°): tvor se smí
//    otáčet před posuny, mezi nimi i po nich, součet všech otočení nejvýš 270° (4 kroky po 60°)
//  - sprint stojí 1 bod únavy za každé použití (HB, sloupec unava_bodu)
// TVARY (kolik hexů tvor zabírá): viz includes/vtt_hexy.php, "Tvorové na více hexech".

const DRACAK_VTT_POHYB_VYCHOZI = [
    'krok' => ['nazev' => 'Krok', 'hexu' => 1, 'jen_pri_umyslu' => null, 'bonus_dovednost' => 0, 'otoceni_max' => 270, 'unava_bodu' => 0],
    'beh' => ['nazev' => 'Běh', 'hexu' => 2, 'jen_pri_umyslu' => null, 'bonus_dovednost' => 1, 'otoceni_max' => 270, 'unava_bodu' => 0],
    'sprint' => ['nazev' => 'Sprint', 'hexu' => 3, 'jen_pri_umyslu' => null, 'bonus_dovednost' => 1, 'otoceni_max' => 270, 'unava_bodu' => 1],
];

const DRACAK_VTT_TVARY_VYCHOZI = [
    1 => [
        'popis' => 'Tvor na jednom hexu; čelem k jedné ze šesti stran.',
        'typ_otaceni' => 'smer', 'utok' => 'predni3',
        'bunky' => [[0, 0]], 'predni' => [[0, 0]],
    ],
    2 => [
        'popis' => 'Zvířecí C: dva hexy za sebou; otáčí se kolem zadního hexu, hlava je před ním.',
        'typ_otaceni' => 'smer', 'utok' => 'predni3',
        'bunky' => [[0, 0], [1, 0]], 'predni' => [[1, 0]],
    ],
    3 => [
        'popis' => 'D: trojúhelník, nahoře dva hexy a pod nimi otočný hex; čelo je hrana obou horních hexů.',
        'typ_otaceni' => 'hrana', 'utok' => 'okoli',
        'bunky' => [[0, 0], [0, -1], [1, -1]], 'predni' => [[0, -1], [1, -1]],
    ],
    5 => [
        'popis' => 'E: nahoře dva hexy, pod nimi řada tří; otáčí se kolem prostředního hexu; čelo je hrana obou horních hexů.',
        'typ_otaceni' => 'hrana', 'utok' => 'okoli',
        'bunky' => [[-1, 0], [0, 0], [1, 0], [0, -1], [1, -1]], 'predni' => [[0, -1], [1, -1]],
    ],
];

// Pravidla pro server i klienta: ['pohyb' => [kod => řádek], 'tvary' => [hexu => řádek]].
// Z databáze, když tabulky existují; jinak výchozí hodnoty výš.
function dracak_vtt_souboj_pravidla(?PDO $pdo = null): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = ['pohyb' => DRACAK_VTT_POHYB_VYCHOZI, 'tvary' => DRACAK_VTT_TVARY_VYCHOZI, 'zdroj' => 'vychozi'];
    try {
        $pdo = $pdo ?? dracak_db();
        $pohyb = [];
        // Sloupec unava_bodu přibyl dodatečně — bez něj (starší ruční SQL) se bere z výchozích hodnot.
        try {
            $radky = $pdo->query('SELECT kod, nazev, hexu, jen_pri_umyslu, bonus_dovednost, otoceni_max, unava_bodu FROM souboj_pohyb')->fetchAll();
        } catch (Throwable $e) {
            $radky = $pdo->query('SELECT kod, nazev, hexu, jen_pri_umyslu, bonus_dovednost, otoceni_max FROM souboj_pohyb')->fetchAll();
        }
        foreach ($radky as $r) {
            $kod = (string)$r['kod'];
            $pohyb[$kod] = [
                'nazev' => (string)$r['nazev'], 'hexu' => (int)$r['hexu'],
                'jen_pri_umyslu' => $r['jen_pri_umyslu'] !== null && $r['jen_pri_umyslu'] !== '' ? (string)$r['jen_pri_umyslu'] : null,
                'bonus_dovednost' => (int)$r['bonus_dovednost'], 'otoceni_max' => (int)$r['otoceni_max'],
                'unava_bodu' => array_key_exists('unava_bodu', $r) ? (int)$r['unava_bodu'] : (int)(DRACAK_VTT_POHYB_VYCHOZI[$kod]['unava_bodu'] ?? 0),
            ];
        }
        $tvary = [];
        foreach ($pdo->query('SELECT hexu, popis, typ_otaceni, utok, bunky, predni FROM tvary_tvoru')->fetchAll() as $r) {
            $bunky = json_decode((string)$r['bunky'], true);
            $predni = json_decode((string)$r['predni'], true);
            if (!is_array($bunky) || !is_array($predni)) {
                continue;
            }
            $tvary[(int)$r['hexu']] = [
                'popis' => (string)$r['popis'], 'typ_otaceni' => (string)$r['typ_otaceni'], 'utok' => (string)$r['utok'],
                'bunky' => $bunky, 'predni' => $predni,
            ];
        }
        if (isset($pohyb['krok'], $pohyb['beh'], $pohyb['sprint']) && isset($tvary[1])) {
            $cache = ['pohyb' => $pohyb, 'tvary' => $tvary, 'zdroj' => 'db'];
        }
    } catch (Throwable $e) {
        // tabulky ještě nejsou (ruční SQL) — platí výchozí hodnoty
    }
    return $cache;
}

// Tvar pro daný počet hexů (neznámý počet = jeden hex).
function dracak_vtt_souboj_tvar(int $hexu): array
{
    $tvary = dracak_vtt_souboj_pravidla()['tvary'];
    return $tvary[$hexu] ?? $tvary[1];
}
