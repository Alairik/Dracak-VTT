<?php
declare(strict_types=1);

// Tvorba postavy (content/pravidla-hrac.html, h104 "Tvorba postavy" a
// h2 "Kostky") — pomocné funkce pro wizard hra/postava_nova.php.
// Atributy jsou "stupeň" 1-21 (u nestvůr víc), ne přímo bonus; bonus/
// postih dává `opravy_za_atribut` (migrace 0007/0048), stejně jako
// všude jinde ve VTT — viz dracak_vtt_obratnost_bonus() níž
// (includes/vtt_iniciativa.php): jméno zmiňuje jen Obratnost, ale
// tabulka je univerzální stupeň->bonus pro libovolnou vlastnost, proto
// se znovupoužívá i pro Odolnost (HP) tady místo nové duplicitní verze.
require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_iniciativa.php';

// Pořadí vlastností podle h104 b587 a `vlastnosti.kod` — stabilní
// pořadí výstupu pro UI (wizard krok 3).
const DRACAK_VTT_VLASTNOSTI_KODY = ['Sil', 'Obr', 'Odl', 'Int', 'Chr'];

// =====================================================================
// h2 "Kostky" — rozsah -> kostky
// =====================================================================

// Rozloží rozsah (dolní-horní mez stupně) na kostky podle h2 ("Od horní
// meze rozsahu odečti dolní mez..."): šířka 5 -> 1k6, 10 -> 2k6,
// 15 -> 3k6 (obecně násobky 5); šířka 9/18 -> 1k10/2k10 (obecně násobky
// 9). Bonus = dolní mez - počet kostek (PŘÍKLAD h104 b13: rozsah 8-18,
// šířka 10 -> 2k6, bonus 8-2=6, tedy 2k6+6 — ověřeno přímo na příkladu
// z textu).
//
// Data v povolani_zakladni_vlastnosti/rasa_rozsahy_vlastnosti (migrace
// 0052) mají vždy šířku 5, 10 nebo 15 (jen k6), ale funkce je obecná
// (k10 větev, i degenerovaný případ šířky 0 = pevná hodnota bez hodu),
// ať jde znovupoužít i jinde (např. budoucí hody na životy při postupu
// na úroveň, mimo rozsah týhle dávky). Pro úzké rozsahy (1-3, 1-5 —
// h2 b18, dělení hodu kostkou dvěma) vrací null — v datech týhle dávky
// nenastává, radši hlasité null než tichá špatná hodnota.
function dracak_vtt_rozsah_na_kostky(int $dolniMez, int $horniMez): ?array
{
    if ($horniMez < $dolniMez) {
        return null;
    }
    $sirka = $horniMez - $dolniMez;
    if ($sirka === 0) {
        return ['pocet' => 0, 'typ' => 0, 'bonus' => $dolniMez];
    }
    if ($sirka % 5 === 0) {
        $pocet = intdiv($sirka, 5);
        return ['pocet' => $pocet, 'typ' => 6, 'bonus' => $dolniMez - $pocet];
    }
    if ($sirka % 9 === 0) {
        $pocet = intdiv($sirka, 9);
        return ['pocet' => $pocet, 'typ' => 10, 'bonus' => $dolniMez - $pocet];
    }
    return null;
}

// Skutečně hodí kostkami přes rozsah (dolní-horní mez) — vrací
// jednotlivé hody (aby šly ukázat v UI, stejně jako
// dracak_vtt_hod_kostkou() v includes/vtt.php) i součet. `pocet_kostek`/
// `typ_kostky` jsou 0 pro pevnou hodnotu (šířka rozsahu 0).
function dracak_vtt_hod_rozsahu(int $dolniMez, int $horniMez): ?array
{
    $kostky = dracak_vtt_rozsah_na_kostky($dolniMez, $horniMez);
    if ($kostky === null) {
        return null;
    }
    if ($kostky['pocet'] === 0) {
        return [
            'hody' => [], 'bonus' => 0, 'celkem' => $kostky['bonus'],
            'pocet_kostek' => 0, 'typ_kostky' => 0,
        ];
    }
    $hod = dracak_vtt_hod_kostkou($kostky['pocet'], $kostky['typ'], $kostky['bonus']);
    $hod['pocet_kostek'] = $kostky['pocet'];
    $hod['typ_kostky'] = $kostky['typ'];
    return $hod;
}

// =====================================================================
// h104 — rasová korekce rozsahu + rozsahy pro tvorbu postavy
// =====================================================================

// Aplikuje rasovou korekci (rasa_bonusy_vlastnosti.modifikator, text
// "+1"/"-2"/"0") na OBĚ meze rozsahu (h104 b92001: "Číslo příslušející
// rase tvé postavy přičti ... k dolní a horní mezi rozsahu"). Stupeň
// vlastnosti nejde pod 1 (h104 b593: "číslo od 1 do 21 včetně") — ořez
// dolů; horní mez strop nemá (b593: "u nestvůr může být i vyšší"),
// takže se nahoru neořezává.
function dracak_vtt_rasova_korekce_rozsahu(int $dolniMez, int $horniMez, string $modifikator): array
{
    $m = (int)$modifikator;
    return [max(1, $dolniMez + $m), max(1, $horniMez + $m)];
}

// Rasový modifikátor pro danou vlastnost (kod: Sil/Obr/Odl/Int/Chr) —
// 0, když řádek v rasa_bonusy_vlastnosti chybí. Defenzivní fallback:
// tahle tabulka už existuje nezávisle na týhle dávce (viz CLAUDE.md),
// takže by měla mít řádek pro každou rasu×vlastnost, ale radši tichá 0
// (bez korekce) než tvrdý pád wizardu na chybějícím datovém řádku.
function dracak_vtt_rasovy_modifikator(PDO $pdo, int $rasaId, string $vlastnostKod): int
{
    $stmt = $pdo->prepare(
        'SELECT rbv.modifikator FROM rasa_bonusy_vlastnosti rbv
         JOIN vlastnosti v ON v.id = rbv.vlastnost_id
         WHERE rbv.rasa_id = ? AND v.kod = ?'
    );
    $stmt->execute([$rasaId, $vlastnostKod]);
    $modifikator = $stmt->fetchColumn();
    return $modifikator !== false ? (int)$modifikator : 0;
}

// Rozsah (lidský, BEZ rasové korekce) 2 "základních" vlastností
// povolání podle povolani_zakladni_vlastnosti, klíčováno kódem
// vlastnosti. Kouzelník má v tabulce jen 1 řádek (Inteligence) — viz
// otevřená otázka v migraci 0052; Charisma u Kouzelníka proto níž
// spadne do druhé větve (rasový rozsah), přesně jako kterákoliv jiná
// vlastnost označená v h104 tabulce "X".
function dracak_vtt_zakladni_vlastnosti_povolani(PDO $pdo, int $povolaniId): array
{
    $stmt = $pdo->prepare(
        'SELECT v.kod, pzv.stupen_od, pzv.stupen_do FROM povolani_zakladni_vlastnosti pzv
         JOIN vlastnosti v ON v.id = pzv.vlastnost_id
         WHERE pzv.povolani_id = ?'
    );
    $stmt->execute([$povolaniId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['kod']] = [(int)$row['stupen_od'], (int)$row['stupen_do']];
    }
    return $out;
}

// Finální (rasový) rozsah všech 5 vlastností, klíčováno kódem —
// rasa_rozsahy_vlastnosti, beze změny (h104 "TABULKA VLASTNOSTÍ PODLE
// RASY" už je finální, na rozdíl od povolání se neopravuje).
function dracak_vtt_rozsahy_rasy(PDO $pdo, int $rasaId): array
{
    $stmt = $pdo->prepare(
        'SELECT v.kod, rrv.stupen_od, rrv.stupen_do FROM rasa_rozsahy_vlastnosti rrv
         JOIN vlastnosti v ON v.id = rrv.vlastnost_id
         WHERE rrv.rasa_id = ?'
    );
    $stmt->execute([$rasaId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['kod']] = [(int)$row['stupen_od'], (int)$row['stupen_do']];
    }
    return $out;
}

// Pro všech 5 vlastností spočítá rozsah, ze kterého se má házet: pro
// vlastnosti základní pro dané povolání vezme lidský rozsah z
// povolani_zakladni_vlastnosti + rasovou korekci (h104 b602); pro
// zbylé (nebo chybějící, viz Kouzelník výš) rovnou finální rozsah rasy.
// Vrací [kod => ['dolni'=>int,'horni'=>int,'zdroj'=>'povolani'|'rasa']].
function dracak_vtt_navrzene_rozsahy_atributu(PDO $pdo, int $rasaId, int $povolaniId): array
{
    $zakladniPovolani = dracak_vtt_zakladni_vlastnosti_povolani($pdo, $povolaniId);
    $rasaRozsahy = dracak_vtt_rozsahy_rasy($pdo, $rasaId);

    $vysledek = [];
    foreach (DRACAK_VTT_VLASTNOSTI_KODY as $kod) {
        if (isset($zakladniPovolani[$kod])) {
            [$dolni, $horni] = $zakladniPovolani[$kod];
            $modifikator = dracak_vtt_rasovy_modifikator($pdo, $rasaId, $kod);
            [$dolni, $horni] = dracak_vtt_rasova_korekce_rozsahu($dolni, $horni, (string)$modifikator);
            $vysledek[$kod] = ['dolni' => $dolni, 'horni' => $horni, 'zdroj' => 'povolani'];
        } elseif (isset($rasaRozsahy[$kod])) {
            [$dolni, $horni] = $rasaRozsahy[$kod];
            $vysledek[$kod] = ['dolni' => $dolni, 'horni' => $horni, 'zdroj' => 'rasa'];
        }
        // Chybí-li OBOJÍ (nemělo by po migraci 0052 nastat), vlastnost
        // se ve výsledku prostě neobjeví — wizard pak nechá pole
        // prázdné k ručnímu vyplnění místo pádu.
    }
    return $vysledek;
}

// Hodí za všech 5 vlastností najednou (viz dracak_vtt_hod_rozsahu) —
// wizard krok 3, tlačítko "Hodit". Vrací stejnou strukturu jako
// dracak_vtt_navrzene_rozsahy_atributu(), doplněnou o 'hod' (pole
// jednotlivých kostek, pro zobrazení) a 'navrh' (celkový výsledek,
// předvyplní se do editovatelného inputu — hráč ho může přepsat).
function dracak_vtt_hod_atributu(PDO $pdo, int $rasaId, int $povolaniId): array
{
    $rozsahy = dracak_vtt_navrzene_rozsahy_atributu($pdo, $rasaId, $povolaniId);
    foreach ($rozsahy as &$info) {
        $hod = dracak_vtt_hod_rozsahu($info['dolni'], $info['horni']);
        $info['hod'] = $hod['hody'] ?? [];
        $info['navrh'] = $hod['celkem'] ?? $info['dolni'];
    }
    unset($info);
    return $rozsahy;
}

// =====================================================================
// h106 "TABULKA ŽIVOTŮ"
// =====================================================================

// Základní počet životů na 1. úrovni podle povolání. Tahle dávka pro to
// nezakládá DB tabulku (migrace 0052 má jen 2 tabulky podle zadání) —
// je to malá, pevná sada čísel vázaná na JEDNU pasáž pravidel, proto
// natvrdo tady, klíčováno přesně `povolani.nazev` (musí sedět 1:1 s
// hodnotami v drd-db-full-v1.sql). Druhý prvek (kostka pro další
// úrovně, např. "1k10") se v tomhle kole nepoužívá (level-up je mimo
// rozsah zadání), ale je uložená pro budoucí znovupoužití.
const DRACAK_VTT_ZIVOTY_ZAKLAD = [
    'Válečník' => ['zaklad' => 10, 'kostka' => '1k10'],
    'Hraničář' => ['zaklad' => 8, 'kostka' => '1k6+2'],
    'Alchymista' => ['zaklad' => 7, 'kostka' => '1k6+1'],
    'Kouzelník' => ['zaklad' => 6, 'kostka' => '1k6'],
    'Zloděj' => ['zaklad' => 6, 'kostka' => '1k6'],
    'Tulák' => ['zaklad' => 6, 'kostka' => '1k6'],
    'Stopař stínů' => ['zaklad' => 8, 'kostka' => '1k6+2'],
    'Novic' => ['zaklad' => 8, 'kostka' => '1k6+1'],
    'Panoš' => ['zaklad' => 9, 'kostka' => '1k6+3'],
    'Střelec' => ['zaklad' => 10, 'kostka' => '1k6+1'],
    'Divoch' => ['zaklad' => 8, 'kostka' => '1k6+2'],
    'Šaman' => ['zaklad' => 6, 'kostka' => '1k6'],
];

// Navržený max. život na 1. úrovni: základ podle povolání (h106) +
// bonus/postih za Odolnost (opravy_za_atribut, přes
// dracak_vtt_obratnost_bonus() — viz komentář v hlavičce souboru,
// funkce je navzdory jménu univerzální pro libovolnou vlastnost).
// Vrací null, když `$povolaniNazev` není v DRACAK_VTT_ZIVOTY_ZAKLAD
// (neznámé povolání) — wizard pak nechá pole prázdné k ručnímu
// vyplnění místo tichého odhadu. max(1, ...) je jen defenzivní pojistka
// (nejnižší základ 6 + nejnižší možný postih -5 dá 1, nikdy míň), ne
// citace konkrétního pravidla pro 1. úroveň.
function dracak_vtt_navrzene_hp(PDO $pdo, string $povolaniNazev, ?int $odolnostStupen): ?int
{
    if (!isset(DRACAK_VTT_ZIVOTY_ZAKLAD[$povolaniNazev])) {
        return null;
    }
    $zaklad = DRACAK_VTT_ZIVOTY_ZAKLAD[$povolaniNazev]['zaklad'];
    $bonus = dracak_vtt_obratnost_bonus($pdo, $odolnostStupen) ?? 0;
    return max(1, $zaklad + $bonus);
}
