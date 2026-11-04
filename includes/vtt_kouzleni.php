<?php
declare(strict_types=1);

// Kouzlení v rozšířeném soubojovém systému (kapitola Kouzlení, b10842–b10847; rozhodnutí uživatele 2026-11-04).
//
// Pravidlo: kouzlo s vyvoláním N kol vyžaduje 2N po sobě jdoucích akcí kouzlení (kouzlo se smí dokončit v dalším kole). Působit začíná po předposlední
// akci, poslední je „odpočinek“. Kdo kouzlí, je pro útočníky nehybný; je-li zraněn aspoň za 1 život PŘED předposlední akcí, kouzlo se nezdaří
// a magenergie propadne; po ní už kouzlo uspěje (kouzlo s koncentrací by se přerušilo — to posoudí PJ). Přerušit kouzlo stojí jednu akci kouzlení.
//
// Kdo odehrál akci „kouzlení“, dostane štítek-efekt „Kouzlí“ (migrace 0112, složka `nehybny`). Odehraje-li ji s kouzlem (kouzlo_id; počet akcí zná hra z údaje
// „vyvolání“, migrace 0113 ukládá postup k štítku), hra akce počítá: štítek sama sundá po poslední akci, ohlásí, kdy kouzlo začíná působit, a zásah
// před předposlední akcí kouzlo zruší. Bez kouzla (nebo když vyvolání nejde přečíst — směny, „1 kolo za každých 5 sáhů“…) štítek nepočítá a sundá ho PJ.
// Jiná akce než kouzlení štítek sundá (akce kouzlení musí následovat těsně po sobě), konec souboje taky. Magenergii hra nestrhává ani nevrací
// (cena kouzel je často vzorec); u zrušeného kouzla jen napíše, kolik propadlo.

require_once __DIR__ . '/vtt_efekty.php';
require_once __DIR__ . '/vtt_predmety.php';

const DRACAK_VTT_EFEKT_KOUZLI = 'Kouzlí';

// Počet akcí kouzlení podle údaje „vyvolání“ kouzla: „N kolo/kola/kol“ = 2N akcí, „1 akce“ = 1, „ihned“ = 0 (rozhodnutí uživatele 2026-11-04: kouzlo s vyvoláním
// „ihned“ nestojí žádnou akci, sesílá se při jiné akci — pohyb, útok, obrana). Cokoli jiného (směny, vzorce s „za každých…“, prázdné) se nepočítá a vrací null —
// pravidla pro ně v rozšířeném systému nic neříkají nebo jde o dlouhé obřady mimo boj.
function dracak_vtt_kouzlo_akce(?string $doba): ?int
{
    if ($doba === null) {
        return null;
    }
    $d = mb_strtolower(trim($doba));
    if (preg_match('/^ihned(\s*[-–]\s*1 bod únavy|\s*\(.*\))?$/u', $d)) {
        return 0;
    }
    if (preg_match('/^(\d{1,2})\s*(kolo|kola|kol)\s*\.?(\s*,\s*stále)?$/u', $d, $m)) {
        $n = (int)$m[1];
        return $n >= 1 && $n <= 10 ? 2 * $n : null;
    }
    if (preg_match('/^1\s*akce(\s*\(.*\))?$/u', $d)) {
        return 1;
    }
    return null;
}

// Kouzlo z katalogu: id, nazev, doba_seslani, cena_magenergie, akci (počet akcí kouzlení, nebo null, když ho neumíme určit).
function dracak_vtt_kouzlo_info(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT id, nazev, doba_seslani, cena_magenergie FROM kouzla WHERE id = ?');
    $q->execute([$id]);
    $r = $q->fetch();
    if (!$r) {
        return null;
    }
    $r['id'] = (int)$r['id'];
    $r['akci'] = dracak_vtt_kouzlo_akce($r['doba_seslani'] !== null ? (string)$r['doba_seslani'] : null);
    return $r;
}

// Kouzla, která postava zná, pro výběr u akce kouzlení: [['id', 'nazev', 'doba', 'akci']] seřazená podle názvu.
function dracak_vtt_kouzla_postavy_pro_vyber(PDO $pdo, int $postavaId): array
{
    $q = $pdo->prepare(
        'SELECT k.id, k.nazev, k.doba_seslani FROM postava_zna_kouzlo pk JOIN kouzla k ON k.id = pk.kouzlo_id WHERE pk.postava_id = ? ORDER BY k.nazev'
    );
    $q->execute([$postavaId]);
    $vysledek = [];
    foreach ($q->fetchAll() as $r) {
        $vysledek[] = [
            'id' => (int)$r['id'], 'nazev' => (string)$r['nazev'], 'doba' => $r['doba_seslani'],
            'akci' => dracak_vtt_kouzlo_akce($r['doba_seslani'] !== null ? (string)$r['doba_seslani'] : null),
        ];
    }
    return $vysledek;
}

function dracak_vtt_kouzli_efekt_id(PDO $pdo): int
{
    $q = $pdo->prepare('SELECT id FROM efekty WHERE nazev = ?');
    $q->execute([DRACAK_VTT_EFEKT_KOUZLI]);
    return (int)$q->fetchColumn();
}

// Aktivní štítek „Kouzlí“ na tvorovi: ['id', 'kouzlo_id', 'akci_celkem', 'akci_hotovo'] (postup je null, když ho štítek nenese), nebo null.
function dracak_vtt_kouzli_radek(PDO $pdo, string $typEntity, int $entitaId): ?array
{
    if (!dracak_vtt_efekty_funkce()) {
        return null;
    }
    $postup = dracak_vtt_tokeny_funkce()['kouzleni_postup'];
    $ucinek = dracak_vtt_tokeny_funkce()['kouzleni_ucinek'];
    $q = $pdo->prepare(
        'SELECT ae.id' . ($postup ? ', ae.kouzlo_id, ae.akci_celkem, ae.akci_hotovo' : '') . ($ucinek ? ', ae.kouzleni_data' : '') . ' FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id
         WHERE ae.typ_entity = ? AND ae.entita_id = ? AND e.nazev = ? ORDER BY ae.id LIMIT 1'
    );
    $q->execute([$typEntity, $entitaId, DRACAK_VTT_EFEKT_KOUZLI]);
    $r = $q->fetch();
    if (!$r) {
        return null;
    }
    return [
        'id' => (int)$r['id'],
        'kouzlo_id' => isset($r['kouzlo_id']) ? ($r['kouzlo_id'] !== null ? (int)$r['kouzlo_id'] : null) : null,
        'akci_celkem' => isset($r['akci_celkem']) ? ($r['akci_celkem'] !== null ? (int)$r['akci_celkem'] : null) : null,
        'akci_hotovo' => isset($r['akci_hotovo']) ? ($r['akci_hotovo'] !== null ? (int)$r['akci_hotovo'] : null) : null,
        'data' => isset($r['kouzleni_data']) && $r['kouzleni_data'] !== null ? (json_decode((string)$r['kouzleni_data'], true) ?: []) : [],
    ];
}

// ID aktivního štítku (zachováno pro starší volání).
function dracak_vtt_kouzli_aktivni_id(PDO $pdo, string $typEntity, int $entitaId): ?int
{
    $r = dracak_vtt_kouzli_radek($pdo, $typEntity, $entitaId);
    return $r !== null ? $r['id'] : null;
}

// Cíl kouzla: cil_token_id z požadavku (token na téže mapě, tvor), jinak sám sesilatel. Vrací ['typ', 'id', 'token_id', 'sam'] nebo null, když cíl nejde určit.
function dracak_vtt_kouzli_cil(PDO $pdo, array $tvor, array $vstup): ?array
{
    $tokenId = (int)($vstup['cil_token_id'] ?? 0);
    if ($tokenId <= 0 || $tokenId === (int)$tvor['id']) {
        return ['typ' => $tvor['typ_entity'], 'id' => (int)$tvor['entita_id'], 'token_id' => (int)$tvor['id'], 'sam' => true];
    }
    $q = $pdo->prepare('SELECT typ_entity, entita_id FROM tokeny WHERE id = ? AND mapa_id = ?');
    $q->execute([$tokenId, (int)$tvor['mapa_id']]);
    $t = $q->fetch();
    if (!$t || !in_array($t['typ_entity'], ['postava', 'nestvura_instance'], true)) {
        return null;
    }
    return ['typ' => (string)$t['typ_entity'], 'id' => (int)$t['entita_id'], 'token_id' => $tokenId, 'sam' => false];
}

// Název stavového efektu kouzla, jehož trvání si volí sesilatel (Rychlost podle dodané magenergie, Protoplazma…): bez zadaných kol se zeptáme dřív, než se cokoli utratí.
// Stejné pravidlo jako v hra/api/pouzij_predmet.php. Vrací název efektu, nebo null, když trvání zadávat netřeba.
function dracak_vtt_kouzlo_potrebuje_trvani(PDO $pdo, array $katalog, int $kouzloId): ?string
{
    if (!dracak_vtt_efekty_funkce() || dracak_vtt_kouzlo_past_text($katalog) !== null) {
        return null;
    }
    foreach (dracak_vtt_polozka_efekty('kouzlo', $kouzloId) as $efekt) {
        if (dracak_vtt_hp_znamenko($efekt) !== null) {
            continue;
        }
        $radek = dracak_vtt_efekt_radek($pdo, (int)$efekt['id']);
        if ($radek && $radek['kolo_se_nepocita'] === 1 && $radek['trvani_kol'] === null && in_array($radek['mechanika_stav'], ['auto', 'cast'], true)) {
            return (string)$radek['nazev'];
        }
    }
    return null;
}

// Cena kouzla v magech pro sesilatele-postavu se zásobou: ruční číslo z požadavku („dodaná magenergie“) má přednost, jinak první číslo z ceny kouzla; null = nestrhává se
// (nestvůra, povolání bez magenergie, cena je vzorec — pak magy upraví hráč nebo PJ ručně).
function dracak_vtt_kouzli_cena(array $tvor, ?array $entity, array $katalog, array $vstup): ?int
{
    if ($tvor['typ_entity'] !== 'postava' || $entity === null || !dracak_vtt_ma_magenergii($entity)) {
        return null;
    }
    if (isset($vstup['magy']) && $vstup['magy'] !== '' && $vstup['magy'] !== null) {
        return max(0, (int)$vstup['magy']);
    }
    return dracak_vtt_kouzlo_cena_magenergie($katalog['cena_magenergie'] ?? null);
}

// Podklady pro dracak_vtt_polozka_pouzij_ucinek: sesilatel, cíl a kouzlo. $volby: zbyva_kol (null = nezadáno, 0 = bez limitu), cena (magy, které se mají odečíst, nebo null).
// Vyhodí RuntimeException se srozumitelnou zprávou, když něco chybí (cíl zmizel, kouzlo není v katalogu).
function dracak_vtt_kouzli_kontext(PDO $pdo, int $svetId, int $mapaId, array $tvor, int $kouzloId, array $cil, array $volby): array
{
    $katalog = dracak_vtt_polozka_katalog('kouzlo', $kouzloId);
    $entity = dracak_vtt_entity_row($tvor['typ_entity'], (int)$tvor['entita_id']);
    $cilEntity = dracak_vtt_entity_row($cil['typ'], $cil['id']);
    if (!$katalog || !$entity || !$cilEntity) {
        throw new RuntimeException('Kouzlo, sesilatel nebo cíl už neexistuje.');
    }
    $kola = $volby['zbyva_kol'] ?? null;
    return [
        'typEntity' => $tvor['typ_entity'], 'entitaId' => (int)$tvor['entita_id'], 'entity' => $entity,
        'typPolozky' => 'kouzlo', 'polozkaId' => $kouzloId, 'katalog' => $katalog,
        'cilTypEntity' => $cil['typ'], 'cilEntitaId' => $cil['id'], 'cilEntity' => $cilEntity,
        'svetMapa' => ['svet_id' => $svetId, 'mapa_id' => $mapaId], 'cilSvetMapa' => ['svet_id' => $svetId, 'mapa_id' => $mapaId],
        'pozadovanaMapaId' => $mapaId,
        'zbyvaKolZadano' => $kola !== null, 'zbyvaKolVstup' => $kola !== null && (int)$kola > 0 ? (int)$kola : null,
        'magenergieCena' => $volby['cena'] ?? null,
    ];
}

// Seslání kouzla ve chvíli, kdy začíná působit: kostky, poškození / léčení, stavové efekty cíli, případně magenergie. Volat uvnitř transakce.
// $user = přihlášený účet (pro událost). Vrací výsledek dracak_vtt_polozka_pouzij_ucinek.
function dracak_vtt_kouzli_seslat(PDO $pdo, array $user, int $svetId, int $mapaId, array $tvor, int $kouzloId, array $cil, array $volby): array
{
    return dracak_vtt_polozka_pouzij_ucinek($pdo, $user, dracak_vtt_kouzli_kontext($pdo, $svetId, $mapaId, $tvor, $kouzloId, $cil, $volby));
}

// Zkontroluje vstup akce kouzlení před zápisem (kouzlo_id, cil_token_id, zbyva_kol, magy, preruseni, akci): chybová zpráva, nebo null. Hráč smí vybrat jen kouzlo, které jeho
// postava zná; ruční počet akcí (nestvůry a kouzla, která hra nezná) zadává jen PJ. Při zahájení kouzlení, které hra vyhodnotí sama (migrace 0114), se tu ověří i cíl
// (viditelnost, dosah), trvání a dostatek magenergie — dřív, než se cokoli zapíše nebo utratí.
function dracak_vtt_kouzli_over_vstup(PDO $pdo, array $tvor, array $vstup, bool $jePj): ?string
{
    $kouzloId = (int)($vstup['kouzlo_id'] ?? 0);
    $kouzloInfo = null;
    if ($kouzloId > 0) {
        $kouzloInfo = dracak_vtt_kouzlo_info($pdo, $kouzloId);
        if ($kouzloInfo === null) {
            return 'Kouzlo nenalezeno.';
        }
        if ($kouzloInfo['akci'] === 0) {
            return 'Kouzlo ' . $kouzloInfo['nazev'] . ' má vyvolání „ihned“ — nestojí žádnou akci kouzlení, sesílá se při jiné akci (pohyb, útok, obrana).';
        }
        if (!$jePj) {
            $zna = false;
            if ($tvor['typ_entity'] === 'postava') {
                $q = $pdo->prepare('SELECT 1 FROM postava_zna_kouzlo WHERE postava_id = ? AND kouzlo_id = ?');
                $q->execute([(int)$tvor['entita_id'], $kouzloId]);
                $zna = (bool)$q->fetchColumn();
            }
            if (!$zna) {
                return 'Tohle kouzlo postava nezná.';
            }
        }
    }
    if (isset($vstup['akci']) && $vstup['akci'] !== '' && $vstup['akci'] !== null) {
        $n = (int)$vstup['akci'];
        if (!$jePj) {
            return 'Počet akcí kouzla zadává PJ.';
        }
        if ($n < 1 || $n > 20) {
            return 'Počet akcí kouzlení je od 1 do 20.';
        }
    }
    if ($kouzloInfo !== null && empty($vstup['preruseni']) && dracak_vtt_tokeny_funkce()['kouzleni_ucinek']
        && dracak_vtt_kouzli_radek($pdo, $tvor['typ_entity'], (int)$tvor['entita_id']) === null) {
        return dracak_vtt_kouzli_over_zahajeni($pdo, $tvor, $vstup, $kouzloInfo);
    }
    return null;
}

// Cíl, dosah a výhled, trvání: společné ověření zahájeného kouzlení i kouzla „ihned“. Chybová zpráva, nebo null.
function dracak_vtt_kouzli_over_cil_trvani(PDO $pdo, array $tvor, array $vstup, array $kouzloInfo, array $katalog): ?string
{
    $cil = dracak_vtt_kouzli_cil($pdo, $tvor, $vstup);
    if ($cil === null) {
        return 'Cíl kouzla nenalezen (musí to být tvor na téhle mapě).';
    }
    $mapaId = (int)$tvor['mapa_id'];
    if (!$cil['sam']) {
        if (dracak_vtt_los_blokovana($pdo, $mapaId, $tvor['typ_entity'], (int)$tvor['entita_id'], $cil['typ'], $cil['id']) === true) {
            return 'Na cíl nevidíš — v cestě je zeď.';
        }
        $dosah = dracak_vtt_polozka_dosah_sahy('kouzlo', $katalog);
        if ($dosah !== null) {
            $vzdalenost = dracak_vtt_vzdalenost_tokenu_sahy($pdo, $mapaId, $tvor['typ_entity'], (int)$tvor['entita_id'], $cil['typ'], $cil['id']);
            if ($vzdalenost !== null && $vzdalenost > $dosah + 0.01) {
                return sprintf('Cíl je mimo dosah — %.1f sáhů daleko, %s má dosah %.1f sáhů.', $vzdalenost, $kouzloInfo['nazev'], $dosah);
            }
        }
    }
    $kolaZadano = array_key_exists('zbyva_kol', $vstup) && $vstup['zbyva_kol'] !== '' && $vstup['zbyva_kol'] !== null;
    if (!$kolaZadano && dracak_vtt_kouzlo_potrebuje_trvani($pdo, $katalog, $kouzloInfo['id']) !== null) {
        return 'Kouzlo ' . $kouzloInfo['nazev'] . ' potřebuje trvání — zadej počet kol (0 = bez limitu).';
    }
    return null;
}

// Ověření zahájení kouzlení, které hra vyhodnotí sama: cíl, dosah a výhled, trvání, magenergie.
function dracak_vtt_kouzli_over_zahajeni(PDO $pdo, array $tvor, array $vstup, array $kouzloInfo): ?string
{
    $katalog = dracak_vtt_polozka_katalog('kouzlo', $kouzloInfo['id']);
    if (!$katalog) {
        return 'Kouzlo nenalezeno v katalogu.';
    }
    $chyba = dracak_vtt_kouzli_over_cil_trvani($pdo, $tvor, $vstup, $kouzloInfo, $katalog);
    if ($chyba !== null) {
        return $chyba;
    }
    $entity = dracak_vtt_entity_row($tvor['typ_entity'], (int)$tvor['entita_id']);
    $cena = dracak_vtt_kouzli_cena($tvor, $entity, $katalog, $vstup);
    if ($cena !== null && (int)$entity['aktualni_magenergie'] < $cena) {
        return 'Nedostatek magenergie (máš ' . (int)$entity['aktualni_magenergie'] . ', kouzlo stojí ' . $cena . ').';
    }
    return null;
}

// Kouzla „ihned“ připojená k jiné akci (pohyb, útok, obrana…): vyvolání „ihned“ = 0 akcí (rozhodnutí uživatele 2026-11-04), kouzlo se vyhodnotí ve chvíli té akce.
// Jedna akce jich unese víc (nejvýš DRACAK_VTT_IHNED_MAX). Seznam: [['kouzlo_id', 'cil_token_id'?, 'zbyva_kol'?, 'magy'?], …].
const DRACAK_VTT_IHNED_MAX = 5;

// Ověření celého seznamu před jakýmkoli zápisem: kouzlo „ihned“ (ne s akcemi kouzlení), které postava zná, cíl, dosah, trvání a dost magenergie na všechna dohromady.
function dracak_vtt_kouzli_ihned_over(PDO $pdo, array $tvor, $seznam, bool $jePj): ?string
{
    if ($seznam === null || $seznam === [] || $seznam === '') {
        return null;
    }
    if (!is_array($seznam) || !array_is_list($seznam)) {
        return 'Kouzla ihned mají být seznam.';
    }
    if (count($seznam) > DRACAK_VTT_IHNED_MAX) {
        return 'K jedné akci jde připojit nejvýš ' . DRACAK_VTT_IHNED_MAX . ' kouzel „ihned“.';
    }
    if (!in_array($tvor['typ_entity'], ['postava', 'nestvura_instance'], true)) {
        return 'Kouzlit smí jen postava nebo nestvůra.';
    }
    $entity = dracak_vtt_entity_row($tvor['typ_entity'], (int)$tvor['entita_id']);
    $celkemMagu = 0;
    foreach ($seznam as $k) {
        if (!is_array($k)) {
            return 'Neplatné kouzlo „ihned“.';
        }
        $id = (int)($k['kouzlo_id'] ?? 0);
        $info = $id > 0 ? dracak_vtt_kouzlo_info($pdo, $id) : null;
        if ($info === null) {
            return 'Kouzlo nenalezeno.';
        }
        if ($info['akci'] !== 0) {
            return 'Kouzlo ' . $info['nazev'] . ' nemá vyvolání „ihned“ — sesílá se akcemi kouzlení, ne připojené k jiné akci.';
        }
        if (!$jePj) {
            $q = $pdo->prepare('SELECT 1 FROM postava_zna_kouzlo WHERE postava_id = ? AND kouzlo_id = ?');
            $q->execute([(int)$tvor['entita_id'], $id]);
            if ($tvor['typ_entity'] !== 'postava' || !$q->fetchColumn()) {
                return 'Tohle kouzlo postava nezná.';
            }
        }
        $katalog = dracak_vtt_polozka_katalog('kouzlo', $id);
        if (!$katalog) {
            return 'Kouzlo nenalezeno v katalogu.';
        }
        $chyba = dracak_vtt_kouzli_over_cil_trvani($pdo, $tvor, $k, $info, $katalog);
        if ($chyba !== null) {
            return $chyba;
        }
        $celkemMagu += (int)dracak_vtt_kouzli_cena($tvor, $entity, $katalog, $k);
    }
    if ($celkemMagu > 0 && $entity !== null && dracak_vtt_ma_magenergii($entity) && (int)$entity['aktualni_magenergie'] < $celkemMagu) {
        return 'Nedostatek magenergie na všechna kouzla „ihned“ (máš ' . (int)$entity['aktualni_magenergie'] . ', stojí dohromady ' . $celkemMagu . ').';
    }
    return null;
}

// Vyhodnotí kouzla „ihned“ ve chvíli akce (uvnitř transakce): každé zaplatí magy, hodí kostky, zraní nebo uzdraví cíl a nasadí efekty (stejně jako „Použít“ v deníku).
// $info = tvorové na mapě (pro jména cílů v logu). Vrací [['kouzlo', 'cil_label', 'hp', 'ceka_na_past']].
function dracak_vtt_kouzli_ihned_proved(PDO $pdo, array $user, int $svetId, int $mapaId, array $tvor, $seznam, array $info = []): array
{
    $vysledky = [];
    if (!is_array($seznam)) {
        return $vysledky;
    }
    foreach ($seznam as $k) {
        $id = (int)$k['kouzlo_id'];
        $kouzlo = dracak_vtt_kouzlo_info($pdo, $id);
        $cil = dracak_vtt_kouzli_cil($pdo, $tvor, $k);
        $katalog = dracak_vtt_polozka_katalog('kouzlo', $id);
        if ($kouzlo === null || $cil === null || !$katalog) {
            continue;
        }
        $entity = dracak_vtt_entity_row($tvor['typ_entity'], (int)$tvor['entita_id']);
        $cena = dracak_vtt_kouzli_cena($tvor, $entity, $katalog, $k);
        $kolaZadano = array_key_exists('zbyva_kol', $k) && $k['zbyva_kol'] !== '' && $k['zbyva_kol'] !== null;
        $v = dracak_vtt_kouzli_seslat($pdo, $user, $svetId, $mapaId, $tvor, $id, $cil, ['zbyva_kol' => $kolaZadano ? (int)$k['zbyva_kol'] : null, 'cena' => $cena]);
        $cilLabel = isset($info[$cil['token_id']]) ? (string)$info[$cil['token_id']]['label'] : null;
        dracak_vtt_kouzli_udalost($svetId, $mapaId, (int)$user['id'], $tvor, 'ihned', $kouzlo, null, null,
            ['magy' => $cena, 'cil_label' => $cilLabel, 'cil_sam' => $cil['sam'], 'hp' => $v['hp'], 'ceka_na_past' => count($v['ceka_na_past']) > 0]);
        $vysledky[] = ['kouzlo' => $kouzlo['nazev'], 'cil_label' => $cilLabel, 'hp' => $v['hp'], 'ceka_na_past' => $v['ceka_na_past']];
    }
    return $vysledky;
}

// Událost „kouzleni_stav“ do logu (klient ji vypíše; skrytým tvorům patří mezi tajné události).
function dracak_vtt_kouzli_udalost(int $svetId, int $mapaId, int $ucetId, array $tvor, string $stav, ?array $kouzlo, ?int $hotovo, ?int $celkem, array $extra = []): void
{
    dracak_vtt_log_event($svetId, $mapaId, 'kouzleni_stav', [
        'token_id' => isset($tvor['id']) ? (int)$tvor['id'] : null,
        'label' => (string)($tvor['label'] ?? ''),
        'typ_entity' => $tvor['typ_entity'],
        'entita_id' => (int)$tvor['entita_id'],
        'stav' => $stav,
        'kouzlo' => $kouzlo['nazev'] ?? null,
        'cena' => $kouzlo['cena_magenergie'] ?? null,
        'hotovo' => $hotovo,
        'celkem' => $celkem,
        'skryta' => isset($tvor['viditelny']) ? !$tvor['viditelny'] : false,
    ] + $extra, $ucetId);
}

// Uloží údaje o sesílaném kouzlu ke štítku (cíl, trvání, zaplacené magy, příznak „účinek proběhl“).
function dracak_vtt_kouzli_uloz_data(PDO $pdo, int $aktivniId, array $data): void
{
    if (!dracak_vtt_tokeny_funkce()['kouzleni_ucinek']) {
        return;
    }
    $pdo->prepare('UPDATE aktivni_efekty SET kouzleni_data = ? WHERE id = ?')->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $aktivniId]);
}

// Po provedené akci (uvnitř transakce): kouzlení nasadí štítek a počítá akce, jiná akce ho sundá. Kouzlo vybrané při zahájení (kouzlo_id) hra sama sešle ve chvíli,
// kdy začíná působit (po předposlední akci; kouzlo na jednu akci hned): zaplatí magenergii při zahájení (propadne i při přerušení), pak hodí kostky, zraní nebo uzdraví
// cíl a nasadí efekty (dracak_vtt_polozka_pouzij_ucinek — stejně jako „Použít“ v deníku). $tvor = řádek z dracak_vtt_souboj_tokeny_info(), $user = přihlášený účet,
// $vstup = tělo požadavku (kouzlo_id, cil_token_id, zbyva_kol, magy, preruseni, akci — dřív ověřené dracak_vtt_kouzli_over_vstup). Vrací stav pro testy, nebo null.
function dracak_vtt_kouzli_po_akci(PDO $pdo, array $user, int $svetId, int $mapaId, array $tvor, string $druh, int $pocet, array $vstup = []): ?string
{
    $ucetId = (int)$user['id'];
    if ($pocet < 1 || !dracak_vtt_efekty_funkce() || !in_array($tvor['typ_entity'], ['postava', 'nestvura_instance'], true)) {
        return null;
    }
    $efektId = dracak_vtt_kouzli_efekt_id($pdo);
    if ($efektId <= 0) {
        return null;
    }
    $entitaId = (int)$tvor['entita_id'];
    $radek = dracak_vtt_kouzli_radek($pdo, $tvor['typ_entity'], $entitaId);
    $postup = dracak_vtt_tokeny_funkce()['kouzleni_postup'];
    $ucinek = dracak_vtt_tokeny_funkce()['kouzleni_ucinek'];
    $kouzloRadku = ($radek !== null && $radek['kouzlo_id'] !== null) ? dracak_vtt_kouzlo_info($pdo, $radek['kouzlo_id']) : null;
    $dataRadku = $radek['data'] ?? [];
    $seslano = !empty($dataRadku['ap']);
    $zaplaceno = isset($dataRadku['cena']) ? (int)$dataRadku['cena'] : null;

    if ($druh !== 'kouzleni') {
        if ($radek === null) {
            return null;
        }
        dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, $radek['id'], 'kouzleni_prerusene');
        if ($radek['akci_celkem'] !== null) {
            dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'prerusene_jinou_akci', $kouzloRadku, $radek['akci_hotovo'], $radek['akci_celkem'],
                ['seslano' => $seslano, 'magy' => $zaplaceno]);
        }
        return 'prerusen';
    }

    // Výslovné přerušení kouzla (jedna akce kouzlení, magenergie propadne).
    if (!empty($vstup['preruseni'])) {
        if ($radek === null) {
            return null;
        }
        dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, $radek['id'], 'kouzleni_preruseno');
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'preruseno', $kouzloRadku, $radek['akci_hotovo'], $radek['akci_celkem'],
            ['seslano' => $seslano, 'magy' => $zaplaceno]);
        return 'preruseno';
    }

    // Pokračování v kouzlení, které hra počítá.
    if ($radek !== null && $radek['akci_celkem'] !== null) {
        $celkem = $radek['akci_celkem'];
        $hotovo = min($celkem, (int)$radek['akci_hotovo'] + $pocet);
        $extra = [];
        if ($ucinek && !$seslano && $hotovo >= $celkem - 1 && $radek['kouzlo_id'] !== null && isset($dataRadku['ci'])) {
            // Předposlední (nebo poslední) akce: kouzlo začíná působit.
            $cil = ['typ' => (string)$dataRadku['ct'], 'id' => (int)$dataRadku['ci'], 'token_id' => (int)($dataRadku['ti'] ?? 0), 'sam' => false];
            try {
                $v = dracak_vtt_kouzli_seslat($pdo, $user, $svetId, $mapaId, $tvor, $radek['kouzlo_id'], $cil,
                    ['zbyva_kol' => array_key_exists('kola', $dataRadku) ? $dataRadku['kola'] : null, 'cena' => null]);
                $extra['seslano'] = true;
                $extra['hp'] = $v['hp'];
                $dataRadku['ap'] = 1;
            } catch (RuntimeException $e) {
                $extra['seslano'] = false;
                $extra['chyba'] = $e->getMessage();
            }
        }
        if ($hotovo >= $celkem) {
            dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, $radek['id'], 'kouzleni_dokonceno');
            dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'dokonceno', $kouzloRadku, $celkem, $celkem, $extra);
            return 'dokonceno';
        }
        $pdo->prepare('UPDATE aktivni_efekty SET akci_hotovo = ? WHERE id = ?')->execute([$hotovo, $radek['id']]);
        if (!empty($dataRadku['ap']) && empty($radek['data']['ap'])) {
            dracak_vtt_kouzli_uloz_data($pdo, $radek['id'], $dataRadku);
        }
        $stav = $hotovo === $celkem - 1 ? 'ucinkuje' : 'pokracuje';
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, $stav, $kouzloRadku, $hotovo, $celkem, $extra);
        return $stav;
    }
    if ($radek !== null) {
        return null;   // ruční štítek (bez kouzla): počítá a sundává ho PJ
    }

    // Nové kouzlení.
    $kouzloId = (int)($vstup['kouzlo_id'] ?? 0);
    $kouzlo = $kouzloId > 0 ? dracak_vtt_kouzlo_info($pdo, $kouzloId) : null;
    $akci = $kouzlo['akci'] ?? null;
    if ($akci === 0) {
        $akci = null;   // „ihned“ sem nedojde (vstup se odmítá dřív), pojistka
    }
    if (isset($vstup['akci']) && $vstup['akci'] !== '' && $vstup['akci'] !== null) {
        $akci = (int)$vstup['akci'];   // ruční počet (PJ), má přednost
    }
    if (!$postup) {
        $akci = null;   // bez sloupců z migrace 0113 se akce nepočítají
    }
    // Hra kouzlo vyhodnotí sama, když zná kouzlo, počet akcí, cíl a má sloupec z migrace 0114.
    $cil = $ucinek && $kouzlo !== null && $akci !== null ? dracak_vtt_kouzli_cil($pdo, $tvor, $vstup) : null;
    $automaticky = $cil !== null;
    $kolaZadano = array_key_exists('zbyva_kol', $vstup) && $vstup['zbyva_kol'] !== '' && $vstup['zbyva_kol'] !== null;
    $kola = $kolaZadano ? (int)$vstup['zbyva_kol'] : null;
    $cena = null;
    $entity = null;
    if ($automaticky) {
        $katalog = dracak_vtt_polozka_katalog('kouzlo', $kouzlo['id']);
        $entity = dracak_vtt_entity_row($tvor['typ_entity'], $entitaId);
        $cena = dracak_vtt_kouzli_cena($tvor, $entity, $katalog ?: [], $vstup);
    }
    if ($akci !== null && $pocet >= $akci) {
        // Jedna akce kouzlení stačí — kouzlo je sesláno hned a štítek nevzniká.
        $extra = [];
        if ($automaticky) {
            $v = dracak_vtt_kouzli_seslat($pdo, $user, $svetId, $mapaId, $tvor, $kouzlo['id'], $cil, ['zbyva_kol' => $kola, 'cena' => $cena]);
            $extra = ['seslano' => true, 'hp' => $v['hp'], 'magy' => $cena];
        }
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'dokonceno', $kouzlo, $akci, $akci, $extra);
        return 'dokonceno';
    }
    $v = dracak_vtt_efekt_aplikuj($pdo, $mapaId, $tvor['typ_entity'], $entitaId, $efektId, ['zbyva_kol' => null]);
    $hotovo = null;
    if ($akci !== null) {
        $hotovo = $pocet;
        $pdo->prepare('UPDATE aktivni_efekty SET kouzlo_id = ?, akci_celkem = ?, akci_hotovo = ? WHERE id = ?')
            ->execute([$kouzlo !== null ? $kouzlo['id'] : null, $akci, $hotovo, $v['aktivni_efekt_id']]);
    }
    dracak_vtt_log_event($svetId, $mapaId, 'efekt_aplikovan', [
        'typ_entity' => $tvor['typ_entity'],
        'entita_id' => $entitaId,
        'aktivni_efekt_id' => $v['aktivni_efekt_id'],
        'efekt_id' => $efektId,
        'efekt_nazev' => $v['nazev'],
        'zbyva_kol' => $v['zbyva_kol'],
        'obnoveno' => $v['obnoveno'],
        'zbyva_pred' => $v['zbyva_pred'],
        'shrnuti' => $v['shrnuti'],
        'akce' => null,
        'duvod_text' => $akci !== null
            ? 'kouzlí' . ($kouzlo !== null ? ' ' . $kouzlo['nazev'] : '') . ' (akce 1 z ' . $akci . ') — pro útočníky nehybný'
            : 'kouzlí — pro útočníky nehybný; počet akcí kouzla hra neví (vyvolání ' . ($kouzlo['doba_seslani'] ?? 'neuvedeno') . '), štítek po poslední akci sundá PJ',
    ], $ucetId);
    $extra = [];
    if ($automaticky) {
        // Magenergie se platí při zahájení (propadne i při přerušení nebo zásahu); kouzlo se sešle, až začne působit.
        if ($cena !== null) {
            dracak_vtt_odecti_magenergii($entity, $cena);
        }
        $data = ['ct' => $cil['typ'], 'ci' => $cil['id'], 'ti' => $cil['token_id'], 'kola' => $kola, 'cena' => $cena, 'ap' => 0];
        $extra = ['magy' => $cena, 'seslano' => false];
        if ($hotovo >= $akci - 1) {
            try {
                $vs = dracak_vtt_kouzli_seslat($pdo, $user, $svetId, $mapaId, $tvor, $kouzlo['id'], $cil, ['zbyva_kol' => $kola, 'cena' => null]);
                $data['ap'] = 1;
                $extra['seslano'] = true;
                $extra['hp'] = $vs['hp'];
            } catch (RuntimeException $e) {
                $extra['chyba'] = $e->getMessage();
            }
        }
        dracak_vtt_kouzli_uloz_data($pdo, $v['aktivni_efekt_id'], $data);
    }
    if ($akci !== null) {
        $stav = $hotovo === $akci - 1 ? 'ucinkuje' : 'zahajeno';
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, $stav, $kouzlo, $hotovo, $akci, $extra);
        return $stav;
    }
    return 'nasazen';
}

// Zásah kouzlícího tvora (zranění aspoň za 1 život): před předposlední akcí kouzlo selže (štítek zmizí, magenergie propadne), po ní kouzlo uspěje
// a tvor se jen nemohl bránit; štítek bez postupu hra nevyhodnotí. Vrací null (tvor nekouzlí) nebo ['stav' => 'selhalo'|'zdarilo'|'rucne', 'kouzlo', 'cena'].
function dracak_vtt_kouzli_zasah(PDO $pdo, int $svetId, int $mapaId, int $ucetId, array $tvor): ?array
{
    $radek = dracak_vtt_kouzli_radek($pdo, $tvor['typ_entity'], (int)$tvor['entita_id']);
    if ($radek === null) {
        return null;
    }
    if ($radek['akci_celkem'] === null) {
        return ['stav' => 'rucne', 'kouzlo' => null, 'cena' => null];
    }
    $kouzlo = $radek['kouzlo_id'] !== null ? dracak_vtt_kouzlo_info($pdo, $radek['kouzlo_id']) : null;
    if ((int)$radek['akci_hotovo'] < $radek['akci_celkem'] - 1) {
        dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, $radek['id'], 'kouzleni_selhalo');
        $zaplaceno = isset($radek['data']['cena']) ? (int)$radek['data']['cena'] : null;
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'selhalo', $kouzlo, $radek['akci_hotovo'], $radek['akci_celkem'], ['magy' => $zaplaceno]);
        return ['stav' => 'selhalo', 'kouzlo' => $kouzlo['nazev'] ?? null, 'cena' => $zaplaceno !== null ? $zaplaceno . ' magů' : ($kouzlo['cena_magenergie'] ?? null)];
    }
    return ['stav' => 'zdarilo', 'kouzlo' => $kouzlo['nazev'] ?? null, 'cena' => null];
}

// Konec souboje: štítky „Kouzlí“ na tvorech z mapy zmizí. Vrací počet sundaných.
function dracak_vtt_kouzli_konec_souboje(PDO $pdo, int $svetId, int $mapaId, int $ucetId): int
{
    if (!dracak_vtt_efekty_funkce()) {
        return 0;
    }
    $q = $pdo->prepare(
        'SELECT DISTINCT ae.id FROM aktivni_efekty ae
         JOIN efekty e ON e.id = ae.efekt_id AND e.nazev = ?
         JOIN tokeny t ON t.typ_entity = ae.typ_entity AND t.entita_id = ae.entita_id AND t.mapa_id = ?'
    );
    $q->execute([DRACAK_VTT_EFEKT_KOUZLI, $mapaId]);
    $pocet = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
        dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, (int)$id, 'souboj_konec');
        $pocet++;
    }
    return $pocet;
}
