<?php
declare(strict_types=1);

require_once __DIR__ . '/vtt_pohyblivost.php';

// Cestování po souši — Fáze 4 plánu (docs/stav-projektu.md). Postup podle
// PPP "Pohyb pod širým nebem" (content/pravidla-hrac.html h1634, h1635) a
// PJ pravidel "Náročnost cesty" (content/pravidla-pj.html h2366):
//
//   mil za hodinu = body chůze za hodinu (Tabulka rychlosti při chůzi,
//                   podle bonusu za pohyblivost a tempa) / náročnost
//   náročnost     = povrch × terén × ostatní vlivy (bodů na míli)
//   družina       = rychlost nejpomalejšího (pravidla-pj h2355)
//   únava         = body za čas podle tempa (b10869); vytrvalost = odolnost;
//                   stupně 1×/2×/3×/4× vytrvalosti (Tabulka únavy) — únava
//                   postavy ubírá bonus za sílu a obratnost, tedy zpomaluje
//                   (Hrun h1634 b10871: unavený 19 → 16)
//   na zvířeti    = jezdec dostane třetinu bodů únavy zvířete (h1635 b10874);
//                   do nákladu zvířete se počítá váha jezdce (h1681 b11168),
//                   jízdní zvíře se pohybuje svou pohyblivostí po naložení
//
// Ověřeno proti zjednodušeným tabulkám PPZ "Cestování na koních" (h1681:
// jezdecký kůň 750/1500/2250 mn, spěch 8 h po cestě 78/62/50 mil, "procházka
// 16 h" = chůze 112/88/72) — viz docs/stav-projektu.md.

// Tempa chůze a únava za hodinu (b10869): "2 h procházky = 1 bod, 1 h chůze =
// 1 bod, ⅓ h spěchu = 1 bod". Poklus/běh/sprint se počítají na kola, ne na
// hodiny — pro cestu přes hodiny tu nejsou.
const DRACAK_VTT_TEMPA_CHUZE = [
    'prochazka' => ['nazev' => 'procházka', 'unava_za_hodinu' => 0.5],
    'chuze' => ['nazev' => 'chůze', 'unava_za_hodinu' => 1.0],
    'spech' => ['nazev' => 'spěch', 'unava_za_hodinu' => 3.0],
];

// TABULKA POVRCHŮ, TABULKA TERÉNŮ, TABULKA OSTATNÍCH VLIVŮ (PJ h2366).
// [název, od, do]; do = null: "a více" (bez horní hranice). Všechno jsou
// rozsahy — kniha říká, že čísla jsou orientační a volí je PJ. Slouží jen
// jako nápověda ve formuláři, výpočet bere čísla, která PJ zadá.
const DRACAK_VTT_POVRCHY = [
    ['dobrá cesta', 10, 10],
    ['rozbahněná, nekvalitní cesta', 11, 16],
    ['louka, savana', 12, 14],
    ['podmáčená louka', 14, 15],
    ['vysoká tráva, obilí', 15, 16],
    ['zorané pole', 13, 16],
    ['řídký lesík', 12, 14],
    ['les', 13, 17],
    ['hustý les', 15, 25],
    ['prales, hvozd', 20, 40],
    ['skalnatý povrch', 13, 18],
    ['kamenitá pláň, štěrk', 18, 25],
    ['pevný písek', 11, 13],
    ['pískoviště s prolézačkou', 13, 50],
    ['sypký písek', 14, 20],
    ['křoviska', 20, 30],
    ['kleč', 15, 25],
    ['bažina', 30, 80],
    ['led', 15, 20],
    ['zasněžený led', 12, 15],
];
const DRACAK_VTT_TERENY = [
    ['rovina nebo mírné zvlnění', 1, 1],
    ['pahorkatina', 1.5, 1.5],
    ['vrchovina', 2, 2],
    ['hory', 3, 4],
    ['velehory', 5, null],
];
const DRACAK_VTT_VLIVY = [
    ['žádný zvláštní vliv', 1, 1],
    ['extrémní zima', 1, 1.5],
    ['extrémní vedro', 1, 2],
    ['šero, světlá měsíční noc', 1, 1.5],
    ['tma + vlastní zdroj světla', 1.5, 1.5],
    ['temná noc', 2, 2],
    ['úplná tma', 3, 3],
    ['mlha', 1, 2],
    ['déšť, sníh', 1, 1.5],
    ['bouře', 1, 3],
    ['silný vítr', 1, 1.5],
    ['větrná smršť', 1, 3],
    ['sněhová vánice', 1, 2.5],
    ['písečná bouře', 1, 3],
    ['hluboký sníh', 2, 4],
];

const DRACAK_VTT_CESTA_EPS = 1e-9;

// Nejvyšší stupeň únavy, na který postava bodovým stavem právě dosáhla
// (hranice patří už do vyššího stupně — tak se přepíná v simulaci, ve
// které jde o to, co platí HNED PO hranici).
function dracak_vtt_cesta_stupen_po_hranici(float $body, int $vytrvalost): int
{
    $stupen = 0;
    foreach ([1, 2, 3, 4] as $k) {
        if ($body >= $k * $vytrvalost - DRACAK_VTT_CESTA_EPS) {
            $stupen = $k;
        }
    }
    return $stupen;
}

// Čistá simulace jednoho úseku cesty (bez DB). Čas běží souvisle; rychlost
// družiny se přepočítá pokaždé, když někdo přejde na vyšší stupeň únavy
// (to mění pohyblivost, tedy rychlost).
//
// $subjekty — pole tvaru:
//   ['klic' => string, 'nazev' => string,
//    'vytrvalost' => int (>0),
//    'body' => float            body únavy na začátku,
//    'sazba' => float           body únavy za hodinu,
//    'rychlost' => ?array       body chůze za hodinu podle stupně únavy
//                               [0 => ?int, 1 => ?int, 2 => ?int, 3 => ?int];
//                               null = subjekt rychlost družiny neomezuje
//                               (zvíře u jezdce: rychlost jezdce je jeho)]
// $vzdalenostMil, $narocnost — viz hlavička; $zastavPriStupni 1–3: úsek
// končí v okamžiku, kdy by kdokoli přešel na tenhle stupeň únavy (tedy
// těsně před ním).
//
// Vrací:
//   'duvod'   cil | unava | nelze | hotovo_na_zacatku
//   'kdo'     klíč subjektu, který zastavil (unava) / znemožnil (nelze)
//   'hodin', 'mil', 'zbyva_mil'
//   'segmenty' [[od_h, do_h, body_za_hodinu, mil_za_hodinu, omezuje(klic)], ...]
//   'konec'   klic => ['body', 'stupen' (přesný, "nad" hranicí)]
function dracak_vtt_cesta_simulace(array $subjekty, float $vzdalenostMil, float $narocnost, int $zastavPriStupni, int $maxSegmentu = 400): array
{
    $t = 0.0;
    $ujeto = 0.0;
    $segmenty = [];
    $body = [];
    foreach ($subjekty as $s) {
        $body[$s['klic']] = (float)$s['body'];
    }
    $vysledek = static function (string $duvod, ?string $kdo) use (&$t, &$ujeto, &$segmenty, &$body, $subjekty, $vzdalenostMil): array {
        $konec = [];
        foreach ($subjekty as $s) {
            $b = $body[$s['klic']];
            $stupen = 0;
            foreach ([1, 2, 3, 4] as $k) {
                if ($b > $k * $s['vytrvalost'] + DRACAK_VTT_CESTA_EPS) {
                    $stupen = $k;
                }
            }
            $konec[$s['klic']] = ['body' => $b, 'stupen' => $stupen];
        }
        return [
            'duvod' => $duvod, 'kdo' => $kdo,
            'hodin' => $t, 'mil' => $ujeto, 'zbyva_mil' => max(0.0, $vzdalenostMil - $ujeto),
            'segmenty' => $segmenty, 'konec' => $konec,
        ];
    };

    if ($vzdalenostMil <= 0 || $narocnost <= 0 || !$subjekty) {
        return $vysledek('nelze', null);
    }
    foreach ($subjekty as $s) {
        if ($body[$s['klic']] >= $zastavPriStupni * $s['vytrvalost'] - DRACAK_VTT_CESTA_EPS) {
            return $vysledek('hotovo_na_zacatku', $s['klic']);
        }
    }

    for ($i = 0; $i < $maxSegmentu; $i++) {
        // Rychlost družiny = nejpomalejší.
        $rychlost = null;
        $omezuje = null;
        foreach ($subjekty as $s) {
            if ($s['rychlost'] === null) {
                continue;
            }
            $st = min(3, dracak_vtt_cesta_stupen_po_hranici($body[$s['klic']], $s['vytrvalost']));
            $r = $s['rychlost'][$st] ?? null;
            if ($r === null) {
                return $vysledek('nelze', $s['klic']);
            }
            if ($rychlost === null || $r < $rychlost) {
                $rychlost = $r;
                $omezuje = $s['klic'];
            }
        }
        if ($rychlost === null || $rychlost <= 0) {
            return $vysledek('nelze', $omezuje);
        }
        $milZaHodinu = $rychlost / $narocnost;

        // Nejbližší událost: dojetí do cíle, dosažení zastavovacího stupně,
        // změna stupně únavy někoho, kdo ovlivňuje rychlost.
        $dt = ($vzdalenostMil - $ujeto) / $milZaHodinu;
        $udalost = 'cil';
        $udalostKdo = null;
        foreach ($subjekty as $s) {
            if ($s['sazba'] <= 0) {
                continue;
            }
            $b = $body[$s['klic']];
            $v = $s['vytrvalost'];
            $dalsi = [$zastavPriStupni * $v => 'unava'];
            if ($s['rychlost'] !== null) {
                foreach ([1, 2, 3] as $k) {
                    $dalsi[$k * $v] = $dalsi[$k * $v] ?? 'zmena';
                }
            }
            foreach ($dalsi as $hranice => $druh) {
                if ($hranice > $b + DRACAK_VTT_CESTA_EPS) {
                    $cas = ($hranice - $b) / $s['sazba'];
                    // Při shodě času vyhrává dojetí do cíle (kdo dorazí přesně na
                    // hranici únavy, dorazil).
                    if ($cas < $dt - DRACAK_VTT_CESTA_EPS) {
                        $dt = $cas;
                        $udalost = $druh;
                        $udalostKdo = $s['klic'];
                    }
                }
            }
        }

        if ($dt > DRACAK_VTT_CESTA_EPS) {
            $segmenty[] = [$t, $t + $dt, $rychlost, $milZaHodinu, $omezuje];
        }
        $t += $dt;
        $ujeto += $milZaHodinu * $dt;
        foreach ($subjekty as $s) {
            $body[$s['klic']] += $s['sazba'] * $dt;
        }
        if ($udalost === 'cil') {
            $ujeto = $vzdalenostMil;
            return $vysledek('cil', null);
        }
        if ($udalost === 'unava') {
            return $vysledek('unava', $udalostKdo);
        }
    }
    return $vysledek('nelze', null);
}

// Rychlost v bodech chůze za hodinu pro dané tempo a bonus za pohyblivost
// (Tabulka rychlosti při chůzi); null mimo tabulku.
function dracak_vtt_cesta_rychlost_tempa(PDO $pdo, ?int $bonus, string $tempo): ?int
{
    $r = dracak_vtt_rychlost_pohybu($pdo, $bonus);
    return $r !== null ? ($r['chuze'][$tempo] ?? null) : null;
}

// Pohyblivost jízdního zvířete po naložení (stejná Tabulka naložení jako u
// postav, zaokrouhlení nahoru; nosnost zvířete = nosnost na hřbetě).
// Vrací ['pohyblivost' => ?int, 'bonus' => ?int, 'nalozeni' => [...]];
// pohyblivost null = nad 4× nosnosti (zvíře to neunese).
function dracak_vtt_zvire_pohyblivost(PDO $pdo, array $zvire, float $nakladMn): array
{
    $nalozeni = dracak_vtt_nalozeni($nakladMn, (int)$zvire['nosnost_mn']);
    if ($nalozeni['ctvrtiny'] === null) {
        return ['pohyblivost' => null, 'bonus' => null, 'nalozeni' => $nalozeni];
    }
    $poh = dracak_vtt_pohyblivost_po_nalozeni((int)$zvire['pohyblivost'], $nalozeni['ctvrtiny']);
    return ['pohyblivost' => $poh, 'bonus' => dracak_vtt_obratnost_bonus($pdo, max(1, $poh)), 'nalozeni' => $nalozeni];
}

// Sestaví subjekty simulace z postav a zvířat a spustí ji.
//
// $ucastnici: [['postava' => řádek z postavy, 'vaha_jezdce' => float]]
//   Postava na zvířeti (postavy.doprava_typ = zvire) jede na zvířeti vybraném
//   v doprava_zvire_id; zvíře nese váhu jezdce + náklad postavy z inventáře.
//   Pěší postava nese svůj náklad sama.
// $zvirata: [['zvire' => řádek z jizdni_zvirata, 'naklad' => float]] —
//   doprovodná zvířata s nákladem (bez jezdce), jdou s družinou.
// $zastavPriStupni: 1–3.
//
// Vrací ['simulace' => výsledek dracak_vtt_cesta_simulace, 'subjekty' => popis
// každého (název, typ, vytrvalost, rychlost podle stupně, poznámky),
// 'chyby' => [text], 'tempo' => ..., 'narocnost' => ...] — když něco chybí
// (postava bez odolnosti/rasy/síly, rychlost mimo tabulku, zvíře
// přetížené), simulace se nespustí a 'chyby' říkají co.
function dracak_vtt_cesta_vypocet(PDO $pdo, array $ucastnici, array $zvirata, float $vzdalenostMil, float $narocnost, string $tempo, int $zastavPriStupni): array
{
    $chyby = [];
    $popisy = [];
    $subjekty = [];
    $sazbaTempa = DRACAK_VTT_TEMPA_CHUZE[$tempo]['unava_za_hodinu'] ?? null;
    if ($sazbaTempa === null) {
        return ['simulace' => null, 'subjekty' => [], 'chyby' => ['Neznámé tempo.']];
    }

    $rychlostiPodleStupne = static function (callable $bonusPodleStupne) use ($pdo, $tempo): array {
        $r = [];
        foreach ([0, 1, 2, 3] as $k) {
            $bonus = $bonusPodleStupne($k);
            $r[$k] = dracak_vtt_cesta_rychlost_tempa($pdo, $bonus, $tempo);
        }
        return $r;
    };

    foreach ($ucastnici as $u) {
        $p = $u['postava'];
        $nazev = (string)$p['nazev'];
        if (($p['odolnost'] ?? null) === null || (int)$p['odolnost'] <= 0) {
            $chyby[] = $nazev . ': chybí odolnost (vytrvalost = odolnost), únavu nelze počítat.';
            continue;
        }
        $vyt = (int)$p['odolnost'];
        $naklad = dracak_vtt_postava_naklad_mn($pdo, (int)$p['id'], $p);
        $nastaveni = dracak_vtt_svet_cestovani_nastaveni($pdo, !empty($p['svet_id']) ? (int)$p['svet_id'] : null);
        // Vypnutá automatika nákladu: inventář se do naložení nepočítá (ani na zvíře).
        $nakladMn = $nastaveni['auto_naklad'] ? (float)$naklad['naklad_mn'] : 0.0;
        $doprava = dracak_vtt_postava_doprava($pdo, $p);
        $naZvireti = $doprava !== null && $doprava['typ'] === 'zvire';
        $poznamky = [];
        if ($naklad['neuplny']) {
            $poznamky[] = 'náklad je neúplný součet (položky bez váhy)';
        }

        if ($naZvireti) {
            $zv = $doprava['zvire'];
            $vahaJezdce = max(0.0, (float)($u['vaha_jezdce'] ?? 0));
            $nakladZvirete = $vahaJezdce + $nakladMn;
            $zp = dracak_vtt_zvire_pohyblivost($pdo, $zv, $nakladZvirete);
            if ($zp['pohyblivost'] === null) {
                $chyby[] = $nazev . ': ' . $zv['nazev'] . ' nese ' . round($nakladZvirete) . ' mn, to je nad 4× jeho nosnosti — neunese to.';
                continue;
            }
            if ($vahaJezdce <= 0) {
                $poznamky[] = 'váha jezdce není zadaná — zvíře nese jen jeho náklad';
            }
            $rychlost = dracak_vtt_cesta_rychlost_tempa($pdo, $zp['bonus'], $tempo);
            if ($rychlost === null) {
                $chyby[] = $nazev . ': bonus pohyblivosti zvířete (' . $zp['bonus'] . ') je mimo Tabulky rychlosti.';
                continue;
            }
            $subjekty[] = [
                'klic' => 'z:' . $p['id'], 'nazev' => $zv['nazev'] . ' (' . $nazev . ')',
                'vytrvalost' => (int)$zv['vytrvalost'], 'body' => 0.0, 'sazba' => $sazbaTempa,
                'rychlost' => [0 => $rychlost, 1 => $rychlost, 2 => $rychlost, 3 => $rychlost],
            ];
            $subjekty[] = [
                'klic' => 'p:' . $p['id'], 'nazev' => $nazev . ' (jezdec)',
                'vytrvalost' => $vyt, 'body' => (float)($p['unava'] ?? 0), 'sazba' => $sazbaTempa / 3,
                'rychlost' => null,
            ];
            $popisy[] = [
                'klic' => 'p:' . $p['id'], 'nazev' => $nazev, 'druh' => 'jezdec', 'postava_id' => (int)$p['id'],
                'zvire' => $zv['nazev'], 'naklad_zvirete' => $nakladZvirete, 'nalozeni' => $zp['nalozeni']['kod'],
                'pohyblivost' => $zp['pohyblivost'], 'bonus' => $zp['bonus'], 'rychlost' => $rychlost, 'poznamky' => $poznamky,
            ];
            $popisy[] = ['klic' => 'z:' . $p['id'], 'nazev' => $zv['nazev'] . ' (' . $nazev . ')', 'druh' => 'zvire', 'postava_id' => null];
            continue;
        }

        // Pěšky: pohyblivost se s únavou mění (ubírá bonus za sílu/obratnost).
        $rychlosti = $rychlostiPodleStupne(static function (int $k) use ($pdo, $p, $vyt, $naklad) {
            $kopie = $p;
            $kopie['unava'] = $k === 0 ? 0 : $k * $vyt + 1;
            $poh = dracak_vtt_postava_pohyblivost($pdo, $kopie, $naklad['naklad_mn']);
            return $poh['bonus_pohyblivosti'] ?? null;
        });
        $pohNyni = dracak_vtt_postava_pohyblivost($pdo, $p, $naklad['naklad_mn']);
        if ($pohNyni === null) {
            $chyby[] = $nazev . ': chybí rasa, síla nebo obratnost — pohyblivost nejde spočítat.';
            continue;
        }
        if ($rychlosti[0] === null) {
            if ($pohNyni['rucne'] && !$pohNyni['rucne_zadana']) {
                $chyby[] = $nazev . ': pohyblivost se ve světě zadává ručně a není zadaná (deník postavy).';
            } else {
                $chyby[] = $nazev . ': pohyblivost' . ($pohNyni['pohyblivost'] === null ? ' neexistuje (náklad nad 4× nosnosti)' : ' (bonus ' . $pohNyni['bonus_pohyblivosti'] . ') je mimo Tabulky rychlosti') . '.';
            }
            continue;
        }
        $subjekty[] = [
            'klic' => 'p:' . $p['id'], 'nazev' => $nazev, 'vytrvalost' => $vyt,
            'body' => (float)($p['unava'] ?? 0), 'sazba' => $sazbaTempa, 'rychlost' => $rychlosti,
        ];
        $popisy[] = [
            'klic' => 'p:' . $p['id'], 'nazev' => $nazev, 'druh' => 'pesky', 'postava_id' => (int)$p['id'],
            'naklad' => $nakladMn, 'nalozeni' => $pohNyni['nalozeni']['kod'] ?? null,
            'pohyblivost' => $pohNyni['pohyblivost'], 'bonus' => $pohNyni['bonus_pohyblivosti'],
            'rychlost' => $rychlosti[0], 'rychlosti_podle_stupne' => $rychlosti, 'poznamky' => $poznamky,
        ];
    }

    foreach ($zvirata as $idx => $z) {
        $zv = $z['zvire'];
        $naklad = max(0.0, (float)$z['naklad']);
        $zp = dracak_vtt_zvire_pohyblivost($pdo, $zv, $naklad);
        $nazev = $zv['nazev'] . ' (náklad ' . round($naklad) . ' mn)';
        if ($zp['pohyblivost'] === null) {
            $chyby[] = $nazev . ': nad 4× nosnosti zvířete — neunese to.';
            continue;
        }
        $rychlost = dracak_vtt_cesta_rychlost_tempa($pdo, $zp['bonus'], $tempo);
        if ($rychlost === null) {
            $chyby[] = $nazev . ': bonus pohyblivosti zvířete (' . $zp['bonus'] . ') je mimo Tabulky rychlosti.';
            continue;
        }
        $klic = 'n:' . $idx;
        $subjekty[] = [
            'klic' => $klic, 'nazev' => $nazev, 'vytrvalost' => (int)$zv['vytrvalost'],
            'body' => 0.0, 'sazba' => $sazbaTempa,
            'rychlost' => [0 => $rychlost, 1 => $rychlost, 2 => $rychlost, 3 => $rychlost],
        ];
        $popisy[] = [
            'klic' => $klic, 'nazev' => $nazev, 'druh' => 'nosic', 'postava_id' => null,
            'naklad_zvirete' => $naklad, 'nalozeni' => $zp['nalozeni']['kod'],
            'pohyblivost' => $zp['pohyblivost'], 'bonus' => $zp['bonus'], 'rychlost' => $rychlost, 'poznamky' => [],
        ];
    }

    if ($chyby || !$subjekty) {
        if (!$subjekty && !$chyby) {
            $chyby[] = 'Nikdo necestuje.';
        }
        return ['simulace' => null, 'subjekty' => $popisy, 'chyby' => $chyby];
    }
    return [
        'simulace' => dracak_vtt_cesta_simulace($subjekty, $vzdalenostMil, $narocnost, $zastavPriStupni),
        'subjekty' => $popisy,
        'subjekty_sim' => $subjekty,
        'chyby' => [],
    ];
}
