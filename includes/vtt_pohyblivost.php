<?php
declare(strict_types=1);

// Pohyblivost a únava postavy — Fáze 1 plánu "cestování, pohyblivost,
// únava, naložení" (docs/stav-projektu.md). Vše podle content/pravidla-hrac.html:
//   h1681 "Pohyb postav" — TABULKA POHYBLIVOSTI RAS, TABULKA NOSNOSTI,
//         TABULKA NALOŽENÍ, vzorec základní pohyblivosti (b11147)
//   h1634 "Pohyb pod širým nebem" — stejný vzorec (b10865), stupně únavy
//         podle vytrvalosti (b10869–b10870)
// Žádné číslo tu není odhad; kde pravidla (nebo jejich přepis v repu)
// něco neříkají, funkce to nevymýšlí, ale vrací null / nechává stav
// nevyhodnocený (viz komentáře u konkrétních funkcí).
require_once __DIR__ . '/vtt_iniciativa.php';

// =====================================================================
// Pohyblivost
// =====================================================================

// Rasový základ pohyblivosti (rasy.pohyblivost_zaklad = hodnota z TABULKY
// POHYBLIVOSTI RAS, h1681 b11140–b11146, naplněno migrací 0064). Trpasličí
// klany a "Kroll — domácí rozšíření" vlastní hodnotu nemají (jsou děti
// core ras, rodic_rasa_id) — dědí ji od rodiče. Hloubka je v datech 1,
// smyčka s horní mezí jen pojistka proti cyklu v ručně editovaných datech.
function dracak_vtt_rasovy_zaklad_pohyblivosti(PDO $pdo, int $rasaId): ?int
{
    $stmt = $pdo->prepare('SELECT pohyblivost_zaklad, rodic_rasa_id FROM rasy WHERE id = ?');
    $id = $rasaId;
    for ($hloubka = 0; $hloubka < 5; $hloubka++) {
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if ($row['pohyblivost_zaklad'] !== null) {
            return (int)$row['pohyblivost_zaklad'];
        }
        if ($row['rodic_rasa_id'] === null) {
            return null;
        }
        $id = (int)$row['rodic_rasa_id'];
    }
    return null;
}

// Nosnost v mincích (mn) podle bonusu za sílu — TABULKA NOSNOSTI (h1681
// b11121–b11132), v DB nosnost_podle_sily (opraveno migrací 0055; sloupec
// se jmenuje zakladni_nosnost_lb, ale drží mn). null mimo rozsah tabulky.
function dracak_vtt_nosnost_mn(PDO $pdo, int $silaBonus): ?int
{
    $stmt = $pdo->prepare('SELECT zakladni_nosnost_lb FROM nosnost_podle_sily WHERE oprava_sil = ?');
    $stmt->execute([$silaBonus]);
    $nosnost = $stmt->fetchColumn();
    return $nosnost !== false ? (int)$nosnost : null;
}

// TABULKA NALOŽENÍ (h1681 b11133–b11137): náklad v násobcích základní
// nosnosti -> naložení a část základní pohyblivosti (v čtvrtinách).
//   0–1  žádné  1     více než 1–2 mírné   3/4
//   více než 2–3 střední 1/2     více než 3–4 velké 1/4
// Horní hranice jsou včetně ("více než 1–2" = nad 1×, nejvýš 2×). Nad
// 4× nosnosti tabulka nic neříká — vrací se 'ctvrtiny' = null a kód
// 'prilis_velke', žádná vymyšlená hodnota.
function dracak_vtt_nalozeni(int|float $nakladMn, int $nosnostMn): array
{
    foreach ([
        1 => ['zadne', 4],
        2 => ['mirne', 3],
        3 => ['stredni', 2],
        4 => ['velke', 1],
    ] as $nasobek => [$kod, $ctvrtiny]) {
        if ($nakladMn <= $nosnostMn * $nasobek) {
            return ['kod' => $kod, 'ctvrtiny' => $ctvrtiny];
        }
    }
    return ['kod' => 'prilis_velke', 'ctvrtiny' => null];
}

// Okamžitá pohyblivost po naložení: "při počítání čtvrtin zaokrouhluj
// nahoru" (h1681 b11149).
function dracak_vtt_pohyblivost_po_nalozeni(int $zakladni, int $ctvrtiny): int
{
    return (int)ceil($zakladni * $ctvrtiny / 4);
}

// Pohyblivost postavy: rasový základ + bonus za obratnost + 2× bonus za
// sílu (h1681 b11147, h1634 b10865); bonusy ze stupně atributu přes
// opravy_za_atribut (dracak_vtt_obratnost_bonus — jméno je historické,
// funkce je univerzální stupeň->bonus, stejně jako ji znovupoužívá
// vtt_postava.php pro Odolnost).
//
// $postava = řádek z `postavy` (potřebuje rasa_id, sila, obratnost).
// Vrací null, když chybí rasa, síla nebo obratnost (atributy jsou
// nullable) nebo rasa nemá pohyblivost (ani po rodiči) — nedopočítává se.
//
// $nakladMn = aktuální náklad v mincích (mn; může být desetinný, váha
// předmětů jde pod 1 mn); null = náklad se nevyhodnocuje (výsledek je
// základní pohyblivost "bez naložení", 'nalozeni' = null). Náklad
// postavy z inventáře dává dracak_vtt_postava_naklad_mn() níž.
//
// 'bonus_pohyblivosti' = bonus za (okamžitou) pohyblivost. Pravidla:
// "Nabývá hodnot od 1 do 21, bonusy a postihy se určují stejným způsobem
// jako u základních vlastností" (h1681 b11138) — proto se do tabulky
// bonusů vstupuje nejméně se stupněm 1; samotné 'pohyblivost' se
// neořezává (vzorec dává běžně i víc než 21 u silných postav).
// Nad 4× nosnosti tabulka naložení nic neříká: 'pohyblivost' i
// 'bonus_pohyblivosti' jsou pak null (postava to neunese / PJ rozhodne).
//
// Únava ($sUnavou = true, výchozí): bonus za sílu a obratnost klesá podle
// Tabulky únavy (DRACAK_VTT_UNAVA_STUPNE), takže klesá i základní
// pohyblivost: rasa + (OBR − p) + 2×(SIL − p) = o 3·p méně (p = postih;
// příklad Hrun h1634 b10871: unavený 19 → 16). Nosnost se počítá z
// původního bonusu za sílu — tabulka nosnosti zná jen −5…+5, kniha o vlivu
// únavy na nosnost mlčí. Vysílená postava (omdlévá) pohyblivost nemá:
// 'pohyblivost' null a 'omdleva' true. 'zakladni_bez_unavy' je základní
// pohyblivost, jaká by byla bez únavy.
function dracak_vtt_postava_pohyblivost_vypocet(PDO $pdo, array $postava, int|float|null $nakladMn, bool $sUnavou): ?array
{
    if (empty($postava['rasa_id']) || ($postava['sila'] ?? null) === null || ($postava['obratnost'] ?? null) === null) {
        return null;
    }
    $rasovyZaklad = dracak_vtt_rasovy_zaklad_pohyblivosti($pdo, (int)$postava['rasa_id']);
    if ($rasovyZaklad === null) {
        return null;
    }
    $bonusSil = dracak_vtt_obratnost_bonus($pdo, (int)$postava['sila']);
    $bonusObr = dracak_vtt_obratnost_bonus($pdo, (int)$postava['obratnost']);
    $zakladniBezUnavy = $rasovyZaklad + $bonusObr + 2 * $bonusSil;

    $unava = $sUnavou ? dracak_vtt_postava_unava_stupen($postava) : null;
    $omdleva = $unava !== null && $unava['omdleva'];
    $postih = $unava['postih_sil_obr'] ?? 0;
    $zakladni = $rasovyZaklad + ($bonusObr - $postih) + 2 * ($bonusSil - $postih);

    $nosnost = dracak_vtt_nosnost_mn($pdo, $bonusSil);
    $nalozeni = null;
    $pohyblivost = $omdleva ? null : $zakladni;
    if (!$omdleva && $nakladMn !== null && $nosnost !== null) {
        $nalozeni = dracak_vtt_nalozeni($nakladMn, $nosnost);
        if ($nalozeni['ctvrtiny'] !== null) {
            $pohyblivost = dracak_vtt_pohyblivost_po_nalozeni($zakladni, $nalozeni['ctvrtiny']);
        } else {
            $pohyblivost = null;
        }
    }

    return [
        'rasovy_zaklad' => $rasovyZaklad,
        'bonus_obratnost' => $bonusObr,
        'bonus_sila' => $bonusSil,
        'unava_postih' => $postih,
        'omdleva' => $omdleva,
        'zakladni_bez_unavy' => $zakladniBezUnavy,
        'zakladni' => $zakladni,
        'nosnost_mn' => $nosnost,
        'naklad_mn' => $nakladMn,
        'nalozeni' => $nalozeni,
        'pohyblivost' => $pohyblivost,
        'bonus_pohyblivosti' => $pohyblivost !== null
            ? dracak_vtt_obratnost_bonus($pdo, max(1, $pohyblivost))
            : null,
    ];
}

// Přepínače automatiky pohybu světa (svet.auto_naklad / auto_pohyblivost /
// auto_unava, migrace 0077) — ve výchozím stavu zapnuté. Bez sloupců na DB
// (migrace ještě není provedená) nebo bez světa se bere všechno zapnuté.
function dracak_vtt_svet_cestovani_nastaveni(PDO $pdo, ?int $svetId): array
{
    static $cache = [];
    $vychozi = ['auto_naklad' => true, 'auto_pohyblivost' => true, 'auto_unava' => true];
    if (!$svetId) {
        return $vychozi;
    }
    if (!isset($cache[$svetId])) {
        try {
            $stmt = $pdo->prepare('SELECT auto_naklad, auto_pohyblivost, auto_unava FROM svet WHERE id = ?');
            $stmt->execute([$svetId]);
            $r = $stmt->fetch();
            $cache[$svetId] = $r ? ['auto_naklad' => (bool)$r['auto_naklad'], 'auto_pohyblivost' => (bool)$r['auto_pohyblivost'], 'auto_unava' => (bool)$r['auto_unava']] : $vychozi;
        } catch (PDOException $e) {
            $cache[$svetId] = $vychozi;
        }
    }
    return $cache[$svetId];
}

// Pohyblivost postavy s ohledem na přepínače světa (viz výpočet výš):
//   auto_naklad vypnuto      -> náklad se nevyhodnocuje (bez naložení)
//   auto_unava vypnuto       -> únava neubírá bonus za sílu/obratnost
//   auto_pohyblivost vypnuto -> 'pohyblivost' je hodnota zadaná ručně
//                               (postavy.pohyblivost_rucne; null = nezadáno),
//                               ostatní pole zůstávají informativně z výpočtu
// Výsledek má navíc 'rucne' (bool) a 'rucne_zadana' (bool).
function dracak_vtt_postava_pohyblivost(PDO $pdo, array $postava, int|float|null $nakladMn = null, bool $sUnavou = true): ?array
{
    $nast = dracak_vtt_svet_cestovani_nastaveni($pdo, !empty($postava['svet_id']) ? (int)$postava['svet_id'] : null);
    if (!$nast['auto_unava']) {
        $sUnavou = false;
    }
    if (!$nast['auto_naklad']) {
        $nakladMn = null;
    }
    $vypocet = dracak_vtt_postava_pohyblivost_vypocet($pdo, $postava, $nakladMn, $sUnavou);
    if ($nast['auto_pohyblivost']) {
        return $vypocet === null ? null : $vypocet + ['rucne' => false, 'rucne_zadana' => false];
    }
    $rucne = ($postava['pohyblivost_rucne'] ?? null) !== null ? (int)$postava['pohyblivost_rucne'] : null;
    $zaklad = $vypocet ?? [
        'rasovy_zaklad' => null, 'bonus_obratnost' => 0, 'bonus_sila' => 0, 'unava_postih' => 0, 'omdleva' => false,
        'zakladni_bez_unavy' => null, 'zakladni' => null, 'nosnost_mn' => null, 'naklad_mn' => $nakladMn, 'nalozeni' => null,
    ];
    $zaklad['pohyblivost'] = $rucne;
    $zaklad['bonus_pohyblivosti'] = $rucne !== null ? dracak_vtt_obratnost_bonus($pdo, max(1, $rucne)) : null;
    $zaklad['rucne'] = true;
    $zaklad['rucne_zadana'] = $rucne !== null;
    return $zaklad;
}

// Rychlost pohybu pro bonus za pohyblivost: TABULKA RYCHLOSTI PŘI CHŮZI
// (body chůze za hodinu) a PŘI BĚHU (body běhu za kolo), h1634 + Bestiář
// h2393 pro +6…+10; tabulky rychlost_chuze/rychlost_behu (migrace 0074).
// null, když je bonus mimo tabulku (pod −5 nebo nad +10 — pravidla tam
// nic neříkají) nebo tabulky v DB ještě nejsou (migrace čeká na ruční
// DDL). Výsledek:
//   ['bonus' => int,
//    'chuze' => ['prochazka' => int, 'chuze' => int, 'spech' => int],
//    'beh'   => ['poklus' => int, 'beh' => int, 'sprint' => int]]
function dracak_vtt_rychlost_pohybu(PDO $pdo, ?int $bonus): ?array
{
    if ($bonus === null) {
        return null;
    }
    static $cache = null;
    if ($cache === null) {
        $cache = ['chuze' => [], 'beh' => []];
        try {
            foreach ($pdo->query('SELECT bonus_pohyblivosti, prochazka, chuze, spech FROM rychlost_chuze') as $r) {
                $cache['chuze'][(int)$r['bonus_pohyblivosti']] = [
                    'prochazka' => (int)$r['prochazka'], 'chuze' => (int)$r['chuze'], 'spech' => (int)$r['spech'],
                ];
            }
            foreach ($pdo->query('SELECT bonus_pohyblivosti, poklus, beh, sprint FROM rychlost_behu') as $r) {
                $cache['beh'][(int)$r['bonus_pohyblivosti']] = [
                    'poklus' => (int)$r['poklus'], 'beh' => (int)$r['beh'], 'sprint' => (int)$r['sprint'],
                ];
            }
        } catch (PDOException $e) {
            $cache = ['chuze' => [], 'beh' => []];
        }
    }
    if (!isset($cache['chuze'][$bonus], $cache['beh'][$bonus])) {
        return null;
    }
    return ['bonus' => $bonus, 'chuze' => $cache['chuze'][$bonus], 'beh' => $cache['beh'][$bonus]];
}

// Náklad postavy z inventáře (postava_predmety x predmety.vaha) a z peněz.
// Váha je v mincích (mn) — oficiální jednotka hmotnosti, h3 "Míry a váhy";
// seed to má jako konvenci sloupce predmety.vaha. Automatický součet, žádný
// ruční vstup.
//
// Peníze: každá mince váží 1 mn bez ohledu na kov (h3), takže zl + st + md
// (postavy.penize_*, migrace 0070) se přičítají k nákladu. Aby šly peníze
// započítat, musí volající předat řádek postavy ($postava) — bez něj
// (nebo před migrací 0070) se peníze nepočítají a 'penize_pocitany' je false.
//
// Položka bez uvedené váhy (vaha NULL, a všechny lektvary — ty váhu ve
// schématu nemají vůbec) se NEPOČÍTÁ jako 0 mn: skončí v 'bez_vahy' a
// výsledek je 'neuplny' = true. Volající to musí dát najevo (součet je
// pak jen dolní odhad). Kouzla nic nevážijí, nepočítají se.
//
// Zatím plochý součet = všechno, co postava má, nese sama. Kontejnery
// (vaky, truhly) a nosiče (zvíře, povoz) jsou další krok, viz
// docs/stav-projektu.md. Do nákladu zatím nepatří oblečení ani jídlo —
// nejsou v modelu inventáře.
function dracak_vtt_postava_naklad_mn(PDO $pdo, int $postavaId, ?array $postava = null): array
{
    $naklad = 0.0;
    $bezVahy = [];

    $stmt = $pdo->prepare(
        'SELECT p.nazev, p.vaha, pp.mnozstvi
         FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
         WHERE pp.postava_id = ? ORDER BY p.nazev'
    );
    $stmt->execute([$postavaId]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['vaha'] === null) {
            $bezVahy[] = $row['nazev'];
        } else {
            $naklad += (float)$row['vaha'] * (int)$row['mnozstvi'];
        }
    }

    $stmt = $pdo->prepare(
        'SELECT l.nazev FROM postava_lektvary pl JOIN lektvary l ON l.id = pl.lektvar_id
         WHERE pl.postava_id = ? ORDER BY l.nazev'
    );
    $stmt->execute([$postavaId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $nazev) {
        $bezVahy[] = $nazev;
    }

    $penizeMn = 0;
    $penizePocitany = $postava !== null && array_key_exists('penize_zl', $postava);
    if ($penizePocitany) {
        $penizeMn = (int)$postava['penize_zl'] + (int)$postava['penize_st'] + (int)$postava['penize_md'];
        $naklad += $penizeMn;
    }

    return [
        'naklad_mn' => $naklad, 'neuplny' => $bezVahy !== [], 'bez_vahy' => $bezVahy,
        'penize_mn' => $penizeMn, 'penize_pocitany' => $penizePocitany,
    ];
}

// =====================================================================
// Doprava (jízdní zvířata)
// =====================================================================

// Číselník jízdních zvířat (jizdni_zvirata, migrace 0067 — TABULKA
// JEZDECKÝCH ZVÍŘAT, h1635), v pořadí tabulky.
function dracak_vtt_jizdni_zvirata(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM jizdni_zvirata ORDER BY id')->fetchAll();
}

// Dopravní prostředek postavy: ['typ' => 'chuze'|'zvire', 'zvire' => řádek
// z jizdni_zvirata|null]. null = migrace 0068 ještě není na DB (sloupce
// doprava_* v řádku postavy nejsou) — volající pak dopravu vůbec
// nenabízí. Zvíře si postava vybírá z číselníku, vlastnictví se
// nevynucuje (půjčené/ukradené zvíře je v pořádku).
function dracak_vtt_postava_doprava(PDO $pdo, array $postava): ?array
{
    if (!array_key_exists('doprava_typ', $postava)) {
        return null;
    }
    if ($postava['doprava_typ'] === 'zvire' && !empty($postava['doprava_zvire_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM jizdni_zvirata WHERE id = ?');
        $stmt->execute([(int)$postava['doprava_zvire_id']]);
        $zvire = $stmt->fetch();
        if ($zvire) {
            return ['typ' => 'zvire', 'zvire' => $zvire];
        }
    }
    return ['typ' => 'chuze', 'zvire' => null];
}

// =====================================================================
// Únava
// =====================================================================

// Stupně únavy: prahy podle h1634 b10870 (vytrvalost = odolnost, b10869) —
// nad vytrvalost unavená, nad 2× vyčerpaná, nad 3× úplně vyčerpaná, nad 4×
// vysílená. Prahy jsou ostré ("nad"): body == vytrvalost je ještě bez
// únavy (příklad Hrun b10871: odolnost 15, 90 kol běhu = 15 bodů, unavený
// je až dál). Postihy a omezení v každém stupni: TABULKA ÚNAVY (h1634,
// přepsáno z fotografie knihy).
//
//   postih_sil_obr  o kolik klesá BONUS za sílu i za obratnost (celkem, ne
//                   přírůstek proti předchozímu stupni; "postih může být i
//                   horší než −5")
//   postih_int/cha  o kolik klesá STUPEŇ inteligence/charismy (inteligence
//                   nikdy pod 2 — viz dracak_vtt_unava_efektivni_stupen)
//   zakazy          co postava nesmí; omezení nižšího stupně platí i ve
//                   vyšším ("vyčerpaný či hůře", h950): sprint, beh, kouzla
//                   (i z předmětů), zaostrovani_vule (kouzelník), meditace a
//                   mimosmyslove (hraničář)
//   jen_plazeni     může se jen plazit (procházka s postihem −1)
//   omdleva         vysílená: omdlévá, bez pomoci umírá během 1k6 hodin —
//                   tabulka u ní žádné číselné postihy neuvádí (null)
// Postih k bonusu platí všude, kde se bonus za sílu/obratnost používá:
// pohyblivost (příklad Hrun b10871: 19 → 16 = −1 OBR −2 za 2× SIL), ÚČ, OČ.
// Nosnost ze síly se NEMĚNÍ (tabulka nosnosti zná jen bonus −5…+5 a text
// nic neříká) — viz dracak_vtt_postava_pohyblivost.
// (DB tabulka stupne_unavy z migrace 0008 byla jiný, v textu
// nedohledatelný model — hranice 10+oprava za ODO, 3 stupně; nepoužívá se.)
const DRACAK_VTT_UNAVA_STUPNE = [
    0 => ['nazev' => 'bez únavy', 'popis' => null,
        'postih_sil_obr' => 0, 'postih_int' => 0, 'postih_cha' => 0,
        'zakazy' => [], 'jen_plazeni' => false, 'omdleva' => false, 'dusledky' => []],
    1 => ['nazev' => 'unavená', 'popis' => 'bonus za sílu a obratnost −1, nesmí sprintovat',
        'postih_sil_obr' => 1, 'postih_int' => 0, 'postih_cha' => 0,
        'zakazy' => ['sprint'], 'jen_plazeni' => false, 'omdleva' => false,
        'dusledky' => ['Bonus za sílu a obratnost klesá o 1', 'Nesmí používat sprint']],
    2 => ['nazev' => 'vyčerpaná', 'popis' => 'bonus za sílu a obratnost −2, stupeň inteligence −2, bez běhu a sprintu (poklus smí), bez kouzel (i z předmětů); kouzelník nezaostří vůli, hraničář nemeditují ani nepoužívá mimosmyslové schopnosti',
        'postih_sil_obr' => 2, 'postih_int' => 2, 'postih_cha' => 0,
        'zakazy' => ['sprint', 'beh', 'kouzla', 'zaostrovani_vule', 'meditace', 'mimosmyslove'], 'jen_plazeni' => false, 'omdleva' => false,
        'dusledky' => ['Bonus za sílu a obratnost klesá o 2', 'Stupeň inteligence se snižuje o 2', 'Nesmí používat běh ani sprint (ale smí poklus)', 'Nemůže sesílat kouzla (ani z předmětů)', 'Kouzelník nedokáže zaostřit vůli, hraničář nemůže meditovat ani používat mimosmyslové schopnosti']],
    3 => ['nazev' => 'úplně vyčerpaná', 'popis' => 'bonus za sílu a obratnost −3, stupeň inteligence −4, stupeň charismy −2, může se jen plazit (procházka s postihem −1); platí i omezení vyčerpané postavy',
        'postih_sil_obr' => 3, 'postih_int' => 4, 'postih_cha' => 2,
        'zakazy' => ['sprint', 'beh', 'kouzla', 'zaostrovani_vule', 'meditace', 'mimosmyslove'], 'jen_plazeni' => true, 'omdleva' => false,
        'dusledky' => ['Bonus za sílu a obratnost klesá o 3', 'Stupeň inteligence se snižuje o 4', 'Stupeň charismy se snižuje o 2', 'Může se jen plazit (procházka s postihem −1)']],
    4 => ['nazev' => 'vysílená', 'popis' => 'postava omdlévá, bez pomoci umírá během 1k6 hodin',
        'postih_sil_obr' => null, 'postih_int' => null, 'postih_cha' => null,
        'zakazy' => ['sprint', 'beh', 'kouzla', 'zaostrovani_vule', 'meditace', 'mimosmyslove'], 'jen_plazeni' => false, 'omdleva' => true,
        'dusledky' => ['Postava omdlévá, a pokud jí někdo neposkytne pomoc, umírá během 1k6 hodin']],
];

// Čistá funkce: body únavy + vytrvalost -> stupeň 0–4.
function dracak_vtt_unava_stupen(int $body, int $vytrvalost): array
{
    $stupen = 0;
    foreach ([1, 2, 3, 4] as $nasobek) {
        if ($body > $vytrvalost * $nasobek) {
            $stupen = $nasobek;
        }
    }
    return ['body' => $body, 'vytrvalost' => $vytrvalost, 'stupen' => $stupen] + DRACAK_VTT_UNAVA_STUPNE[$stupen];
}

// Efektivní STUPEŇ vlastnosti po únavě (inteligence/charisma). Postih se
// odečte, ale vlastnost se nikdy nezvýší: "inteligence nemůže nikdy být
// nižší než 2" znamená spodní mez pro snížení, ne zvednutí postavy, která
// už nižší inteligenci má. Charisma pod 2 tabulka neřeší — nejnižší stupeň
// vlastnosti je 1. null postih (vysílená) = beze změny (postava je bez
// vědomí, číselný postih tabulka neuvádí).
function dracak_vtt_unava_efektivni_stupen(?int $stupen, ?int $postih, int $minimum): ?int
{
    if ($stupen === null || $postih === null || $postih === 0) {
        return $stupen;
    }
    return min($stupen, max($minimum, $stupen - $postih));
}

// Stupeň únavy konkrétní postavy ($postava = řádek z `postavy`, sloupce
// unava a odolnost). null, když postava nemá vyplněnou Odolnost —
// vytrvalost se nehádá. Chybějící sloupec unava (migrace 0065 ještě
// není ručně spuštěná) se bere jako 0.
function dracak_vtt_postava_unava_stupen(array $postava): ?array
{
    if (($postava['odolnost'] ?? null) === null) {
        return null;
    }
    return dracak_vtt_unava_stupen((int)($postava['unava'] ?? 0), (int)$postava['odolnost']);
}
