<?php
declare(strict_types=1);

// Zbraně u sebe a vytasení za akci (Deník v2, krok 2).
//
// Pravidla (content/pravidla-hrac.html): v souboji se čas měří v kolech, v jednom kole jde provést jeden úkon
// „například švihnout mečem, seslat kouzlo nebo vyměnit zbraň“ a nejde „vytasit meč a použít jej“ (h1606, b10619) —
// vytasení nebo výměna zbraně v boji stojí akci. Aby postava zbraň vůbec mohla vytasit, musí ji mít při sobě:
// „aby mohla postava pohodlně mít zbraně při sobě, potřebuje k nim pořídit pochvu či závěs“ (h2058, b14698).
// V ruce je vždy jen jedna zbraň (postavy.drzena_zbran_id).
//
// Model: postava_predmety.u_sebe = 1 značí zbraň připravenou při těle (opasek, pochva, závěs, toulec, holster) —
// v boji se dá vytasit, kdežto zbraň v batohu ne. Mimo boj se zbraň mění zdarma. Kolik se jich na opasek vejde
// kniha neříká (zatím bez limitu). Zbraň odložená z ruky přechází do „u sebe“ (zasune se do pochvy).
// Bez sloupce u_sebe (ruční DDL, docs/sql-k-rucnimu-spusteni.sql) se hlídá jen cena akce, ne „u sebe“.

require_once __DIR__ . '/vtt_denik.php';
require_once __DIR__ . '/vtt_souboj.php';
require_once __DIR__ . '/vtt_efekty.php';

// Je na serveru sloupec postava_predmety.u_sebe?
function dracak_vtt_zbrane_u_sebe_funkce(PDO $pdo): bool
{
    return dracak_vtt_ma_sloupec($pdo, 'postava_predmety', 'u_sebe');
}

// Boj, ve kterém postava právě je: token postavy na mapě světa, na které běží souboj a ve kterém
// postava je účastník. null = postava v boji není (zbraň se mění zdarma). Jinak
// ['mapa_id','token_id','faze','cislo_kola','na_tahu','akci_zbyva'].
function dracak_vtt_zbrane_boj_kontext(PDO $pdo, array $postava): ?array
{
    $svetId = (int)($postava['svet_id'] ?? 0);
    if ($svetId <= 0 || !dracak_vtt_souboj_funkce()) {
        return null;
    }
    $q = $pdo->prepare(
        'SELECT t.id AS token_id, t.mapa_id
         FROM mapy m
         JOIN tokeny t ON t.mapa_id = m.id AND t.typ_entity = "postava" AND t.entita_id = ?
         JOIN kolo_ucastnik ku ON ku.mapa_id = m.id AND ku.token_id = t.id
         JOIN kolo_stav ks ON ks.mapa_id = m.id
         WHERE m.svet_id = ?
         ORDER BY m.id
         LIMIT 1'
    );
    $q->execute([(int)$postava['id'], $svetId]);
    $t = $q->fetch();
    if (!$t) {
        return null;
    }
    $stav = dracak_vtt_souboj_nacti($pdo, (int)$t['mapa_id']);
    if ($stav === null) {   // pojistka: kolo_stav zmizel mezi dotazy
        return null;
    }
    $akci = 0;
    if ($stav['faze'] === 'kolo' && $stav['aktivni_token_id'] === (int)$t['token_id']) {
        $q = $pdo->prepare('SELECT akce_celkem, akce_zbyvajici FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ?');
        $q->execute([(int)$t['mapa_id'], (int)$t['token_id']]);
        $ki = $q->fetch();
        $akci = $ki ? dracak_vtt_souboj_kvota((int)$ki['akce_celkem'], (int)$ki['akce_zbyvajici'], $stav['polovina']) : 0;
    }
    return [
        'mapa_id' => (int)$t['mapa_id'],
        'token_id' => (int)$t['token_id'],
        'faze' => $stav['faze'],
        'cislo_kola' => $stav['cislo_kola'],
        'na_tahu' => $stav['faze'] === 'kolo' && $stav['aktivni_token_id'] === (int)$t['token_id'],
        'akci_zbyva' => $akci,
    ];
}

// Změní zbraň v ruce (0 = prázdné ruce) a odloženou zbraň zasune k tělu. Tady se NEúčtuje akce — volající
// buď jde mimo boj, nebo ji odečetl dracak_vtt_souboj_proved_akci(). Hráč smí vytasit jen zbraň, kterou má u sebe;
// PJ (a postava mimo boj) kteroukoli z inventáře. Při porušení pravidel RuntimeException s textem pro uživatele.
// Vrací název nové zbraně (null = prázdné ruce).
function dracak_vtt_zbrane_vytasit(PDO $pdo, array $postava, int $zbranId, bool $kontrolaUSebe): ?string
{
    if (!array_key_exists('drzena_zbran_id', $postava)) {
        throw new RuntimeException('Zbraň v ruce se zatím nedá ukládat (chybí migrace 0070).');
    }
    $postavaId = (int)$postava['id'];
    $drzena = !empty($postava['drzena_zbran_id']) ? (int)$postava['drzena_zbran_id'] : 0;
    $maUSebe = dracak_vtt_zbrane_u_sebe_funkce($pdo);
    $nazev = null;
    if ($zbranId > 0) {
        $q = $pdo->prepare(
            'SELECT p.nazev' . ($maUSebe ? ', pp.u_sebe' : ', 1 AS u_sebe') . '
             FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
             WHERE pp.postava_id = ? AND pp.predmet_id = ? AND p.typ = "zbran"'
        );
        $q->execute([$postavaId, $zbranId]);
        $r = $q->fetch();
        if (!$r) {
            throw new RuntimeException('Tuhle zbraň postava nemá.');
        }
        if ($zbranId === $drzena) {
            throw new RuntimeException('Tuhle zbraň už postava drží.');
        }
        if ($kontrolaUSebe && !(int)$r['u_sebe']) {
            throw new RuntimeException($r['nazev'] . ' není u sebe (opasek, pochva, závěs) — v boji ji postava nevytasí. Mimo boj ji dej k tělu.');
        }
        $nazev = (string)$r['nazev'];
    } elseif ($drzena === 0) {
        throw new RuntimeException('Postava už má prázdné ruce.');
    }
    $pdo->prepare('UPDATE postavy SET drzena_zbran_id = ? WHERE id = ?')->execute([$zbranId > 0 ? $zbranId : null, $postavaId]);
    if ($maUSebe && $drzena > 0) {
        $pdo->prepare('UPDATE postava_predmety SET u_sebe = 1 WHERE postava_id = ? AND predmet_id = ?')->execute([$postavaId, $drzena]);
    }
    return $nazev;
}

// ---------------------------------------------------------------------------------------------------------------
// Vytasení bez akce (efekty se složkou `tasit_zdarma`, includes/vtt_efekty.php)
//
//   Erythen — Rychlé tasení (h1094, b7751–b7755): jednou za kolo bez akce vytasí 1–2 zbraně tváří v tvář (dvě zbraně
//     = jedna „kombinace“ v katalogu) nebo vytasí a hodí jednu vrhací zbraň. Zbraň musí mít volně dostupnou (pochva,
//     holster; ne batoh, ne z rukou). Trvalá schopnost: efekt „Rychlé tasení (Erythen)“ je připojený ke schopnosti
//     (schopnost_efekty) a má složku `pasivni`, takže ho má každý Erythen od 6. úrovně sám.
//   Dikobraz (Zlaté tetování Velkého lovce, h1411, b9098): na 1 kolo tasení vrhací zbraně nestojí akci — efekt
//     „Dikobraz (Zlaté tetování)“ nasadí PJ jako každý jiný aktivní efekt.
// Co je „volně dostupná“ střeží příznak u_sebe (viz výše); vržená zbraň +2 ÚČ a cizí zbraň v dosahu 1 (past Obr–7–Obr)
// zůstává na PJ (efekt je ve stavu „cast“).
// ---------------------------------------------------------------------------------------------------------------

// Druh zbraně podle katalogu: 'vrhaci', 'tvt' (tváří v tvář), nebo null (střelná zbraň, nebo druh v katalogu chybí).
function dracak_vtt_zbran_druh(?string $kategorie, string $nazev): ?string
{
    $kategorie = (string)$kategorie;
    if (str_contains($kategorie, 'vrhaci') || preg_match('/vrhací/u', $nazev)) {
        return 'vrhaci';
    }
    if ($kategorie === '' || str_contains($kategorie, 'strelna')) {
        return null;
    }
    return 'tvt';
}

// Zdroje vytasení bez akce, které postava má: [['efekt' => název, 'limit' => ?int za kolo, 'rozsah' => ['tvt', 'vrhaci']]].
// Aktivní efekty (Dikobraz) a trvalé schopnosti (Rychlé tasení); neomezené jdou první, ať se nespotřebuje omezený.
function dracak_vtt_zbrane_zdarma_zdroje(PDO $pdo, array $postava): array
{
    if (!dracak_vtt_efekty_funkce()) {
        return [];
    }
    $zdroje = dracak_vtt_efekty_souhrn($pdo, 'postava', (int)$postava['id'])['tasit_zdarma'];
    $ids = array_column(dracak_vtt_schopnosti_postavy($pdo, $postava), 'id');
    if ($ids) {
        $q = $pdo->prepare(
            'SELECT DISTINCT e.id, e.nazev
             FROM schopnost_efekty se
             JOIN efekty e ON e.id = se.efekt_id
             JOIN efekt_slozky p ON p.efekt_id = e.id AND p.druh = "pasivni"
             WHERE se.schopnost_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
        );
        $q->execute(array_map('intval', $ids));
        $efekty = $q->fetchAll();
        $slozky = dracak_vtt_efekt_slozky_pro($pdo, array_column($efekty, 'id'));
        foreach ($efekty as $e) {
            foreach ($slozky[(int)$e['id']] ?? [] as $sl) {
                if ($sl['druh'] === 'tasit_zdarma') {
                    $zdroje[] = [
                        'efekt' => (string)$e['nazev'],
                        'limit' => $sl['hodnota'] !== null ? (int)$sl['hodnota'] : null,
                        'rozsah' => array_values(array_filter(array_map('trim', explode(',', (string)$sl['parametr'])))),
                    ];
                }
            }
        }
    }
    usort($zdroje, static fn(array $a, array $b): int => ($a['limit'] === null ? 0 : 1) <=> ($b['limit'] === null ? 0 : 1));
    return $zdroje;
}

// Kolikrát tvor v tomhle kole využil vytasení bez akce z daného efektu (z událostí akce_provedena). Události se čtou od nejnovějších
// po stránkách, dokud se nenarazí na dřívější kolo — počet akcí v kole proto nic neusekne.
function dracak_vtt_zbrane_zdarma_pouzito(PDO $pdo, int $mapaId, int $tokenId, int $cisloKola, string $efekt): int
{
    // Nic z dřívějšího souboje na téže mapě (kola se v novém souboji počítají znovu od 1).
    $q = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM svet_udalosti WHERE mapa_id = ? AND typ = "souboj_start"');
    $q->execute([$mapaId]);
    $odStartu = (int)$q->fetchColumn();
    $q = $pdo->prepare('SELECT id, payload FROM svet_udalosti WHERE mapa_id = ? AND typ = "akce_provedena" AND id > ? AND id < ? ORDER BY id DESC LIMIT 200');
    $pocet = 0;
    $hranice = PHP_INT_MAX;
    while (true) {
        $q->execute([$mapaId, $odStartu, $hranice]);
        $radky = $q->fetchAll();
        if (!$radky) {
            return $pocet;
        }
        foreach ($radky as $r) {
            $hranice = (int)$r['id'];
            $p = json_decode((string)$r['payload'], true);
            if (!is_array($p) || (int)($p['token_id'] ?? 0) !== $tokenId) {
                continue;
            }
            if ((int)($p['cislo_kola'] ?? 0) < $cisloKola) {
                return $pocet;
            }
            if ((int)($p['cislo_kola'] ?? 0) === $cisloKola && ($p['zdarma_efekt'] ?? null) === $efekt) {
                $pocet++;
            }
        }
    }
}

// Který efekt dovolí tomuhle tvoru vytasit zbraň daného druhu bez akce právě teď (null = bez akce to nejde).
// $kontext = ['mapa_id', 'token_id', 'cislo_kola'] boje, ve kterém tvor je (kvůli „jednou za kolo“).
function dracak_vtt_zbrane_zdarma_efekt(PDO $pdo, array $zdroje, ?string $druh, array $kontext): ?string
{
    if ($druh === null) {
        return null;
    }
    foreach ($zdroje as $z) {
        if (!in_array($druh, $z['rozsah'], true)) {
            continue;
        }
        if ($z['limit'] === null
            || dracak_vtt_zbrane_zdarma_pouzito($pdo, (int)$kontext['mapa_id'], (int)$kontext['token_id'], (int)$kontext['cislo_kola'], $z['efekt']) < $z['limit']) {
            return $z['efekt'];
        }
    }
    return null;
}

// Totéž pro konkrétní zbraň z inventáře (id předmětu): zbraň musí být u sebe („volně dostupná“).
function dracak_vtt_zbrane_zdarma(PDO $pdo, array $postava, int $zbranId, array $kontext): ?string
{
    if ($zbranId <= 0) {
        return null;
    }
    $maUSebe = dracak_vtt_zbrane_u_sebe_funkce($pdo);
    $q = $pdo->prepare(
        'SELECT p.nazev, p.kategorie_zbrane' . ($maUSebe ? ', pp.u_sebe' : ', 1 AS u_sebe') . '
         FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
         WHERE pp.postava_id = ? AND pp.predmet_id = ? AND p.typ = "zbran"'
    );
    $q->execute([(int)$postava['id'], $zbranId]);
    $r = $q->fetch();
    if (!$r || !(int)$r['u_sebe']) {
        return null;
    }
    return dracak_vtt_zbrane_zdarma_efekt($pdo, dracak_vtt_zbrane_zdarma_zdroje($pdo, $postava), dracak_vtt_zbran_druh($r['kategorie_zbrane'], (string)$r['nazev']), $kontext);
}
