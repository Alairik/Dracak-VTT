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
