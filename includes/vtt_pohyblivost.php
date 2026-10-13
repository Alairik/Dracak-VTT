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
function dracak_vtt_nalozeni(int $nakladMn, int $nosnostMn): array
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
// $nakladMn = aktuální náklad v mincích; null = náklad se nevyhodnocuje
// (výsledek je základní pohyblivost "bez naložení", 'nalozeni' = null).
// Náklad se ZÁMĚRNĚ nesčítá z inventáře: postava_predmety -> predmety.vaha
// nemá ověřené jednotky pro všechny typy (zbraně mn, suroviny zlomky),
// lektvary váhu nemají vůbec, takže by součet byl odhad. Volající, který
// náklad ví jistě (PJ zadá ručně), ho předá.
//
// 'bonus_pohyblivosti' = bonus za (okamžitou) pohyblivost. Pravidla:
// "Nabývá hodnot od 1 do 21, bonusy a postihy se určují stejným způsobem
// jako u základních vlastností" (h1681 b11138) — proto se do tabulky
// bonusů vstupuje nejméně se stupněm 1; samotné 'pohyblivost' se
// neořezává (vzorec dává běžně i víc než 21 u silných postav).
// Nad 4× nosnosti tabulka naložení nic neříká: 'pohyblivost' i
// 'bonus_pohyblivosti' jsou pak null (postava to neunese / PJ rozhodne).
function dracak_vtt_postava_pohyblivost(PDO $pdo, array $postava, ?int $nakladMn = null): ?array
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
    $zakladni = $rasovyZaklad + $bonusObr + 2 * $bonusSil;

    $nosnost = dracak_vtt_nosnost_mn($pdo, $bonusSil);
    $nalozeni = null;
    $pohyblivost = $zakladni;
    if ($nakladMn !== null && $nosnost !== null) {
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

// =====================================================================
// Únava
// =====================================================================

// Stupně únavy podle h1634 b10870 (vytrvalost = odolnost, b10869): nad
// vytrvalost unavená, nad 2× vyčerpaná, nad 3× úplně vyčerpaná, nad 4×
// vysílená. Prahy jsou ostré ("nad"): body == vytrvalost je ještě bez
// únavy (příklad Hrun b10871: odolnost 15, 90 kol běhu = 15 bodů, unavený
// je až dál).
//
// 'popis' je doslovný text ze shrnutí v b10870. Strukturované postihy
// (číslo po atributech) tu ZÁMĚRNĚ nejsou: přepis v repu uvádí cíl
// postihu výslovně jen u "unavená" (síla/obratnost) a int/chr jen u
// "úplně vyčerpaná"; plná Tabulka únavy (postihy ke všem čtyřem
// vlastnostem u každého stupně) v content/ není. Dokud ji někdo
// nepřepíše, engine nemá co z postihů automaticky aplikovat.
// (DB tabulka stupne_unavy z migrace 0008 byla jiný, v textu
// nedohledatelný model — hranice 10+oprava za ODO, 3 stupně; pryč z
// editoru, tabulka se na DB dropne ručně.)
const DRACAK_VTT_UNAVA_STUPNE = [
    0 => ['nazev' => 'bez únavy', 'popis' => null],
    1 => ['nazev' => 'unavená', 'popis' => 'postih −1 k síle/obratnosti, žádný sprint'],
    2 => ['nazev' => 'vyčerpaná', 'popis' => 'postih −2, bez běhu/sprintu/kouzel/meditace/zaostření vůle u kouzelníka/hraničáře'],
    3 => ['nazev' => 'úplně vyčerpaná', 'popis' => 'postih −3, stupeň inteligence −4, stupeň charismy −2, jen plazení s postihem −1'],
    4 => ['nazev' => 'vysílená', 'popis' => 'postava omdlévá, bez pomoci umírá během 1k6 hodin'],
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
    return [
        'body' => $body,
        'vytrvalost' => $vytrvalost,
        'stupen' => $stupen,
        'nazev' => DRACAK_VTT_UNAVA_STUPNE[$stupen]['nazev'],
        'popis' => DRACAK_VTT_UNAVA_STUPNE[$stupen]['popis'],
    ];
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
