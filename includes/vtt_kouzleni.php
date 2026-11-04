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

const DRACAK_VTT_EFEKT_KOUZLI = 'Kouzlí';

// Počet akcí kouzlení podle údaje „vyvolání“ kouzla: „N kolo/kola/kol“ = 2N akcí, „1 akce“ = 1. Cokoli jiného (směny, „ihned“, vzorce s „za každých…“,
// prázdné) se nepočítá a vrací null — pravidla pro ně v rozšířeném systému nic neříkají nebo jde o dlouhé obřady mimo boj.
function dracak_vtt_kouzlo_akce(?string $doba): ?int
{
    if ($doba === null) {
        return null;
    }
    $d = mb_strtolower(trim($doba));
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
    $q = $pdo->prepare(
        'SELECT ae.id' . ($postup ? ', ae.kouzlo_id, ae.akci_celkem, ae.akci_hotovo' : '') . ' FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id
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
    ];
}

// ID aktivního štítku (zachováno pro starší volání).
function dracak_vtt_kouzli_aktivni_id(PDO $pdo, string $typEntity, int $entitaId): ?int
{
    $r = dracak_vtt_kouzli_radek($pdo, $typEntity, $entitaId);
    return $r !== null ? $r['id'] : null;
}

// Zkontroluje vstup akce kouzlení před zápisem (kouzlo_id, preruseni, akci): chybová zpráva, nebo null. Hráč smí vybrat jen kouzlo, které jeho postava zná;
// ruční počet akcí (nestvůry a kouzla, která hra nezná) zadává jen PJ.
function dracak_vtt_kouzli_over_vstup(PDO $pdo, array $tvor, array $vstup, bool $jePj): ?string
{
    $kouzloId = (int)($vstup['kouzlo_id'] ?? 0);
    if ($kouzloId > 0) {
        if (dracak_vtt_kouzlo_info($pdo, $kouzloId) === null) {
            return 'Kouzlo nenalezeno.';
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
    return null;
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

// Po provedené akci (uvnitř transakce): kouzlení nasadí štítek a počítá akce, jiná akce ho sundá. $tvor = řádek z dracak_vtt_souboj_tokeny_info(),
// $vstup = tělo požadavku (kouzlo_id, preruseni, akci — dřív ověřené dracak_vtt_kouzli_over_vstup). Vrací stav pro testy, nebo null.
function dracak_vtt_kouzli_po_akci(PDO $pdo, int $svetId, int $mapaId, int $ucetId, array $tvor, string $druh, int $pocet, array $vstup = []): ?string
{
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
    $kouzloRadku = ($radek !== null && $radek['kouzlo_id'] !== null) ? dracak_vtt_kouzlo_info($pdo, $radek['kouzlo_id']) : null;

    if ($druh !== 'kouzleni') {
        if ($radek === null) {
            return null;
        }
        dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, $radek['id'], 'kouzleni_prerusene');
        if ($radek['akci_celkem'] !== null) {
            dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'prerusene_jinou_akci', $kouzloRadku, $radek['akci_hotovo'], $radek['akci_celkem']);
        }
        return 'prerusen';
    }

    // Výslovné přerušení kouzla (jedna akce kouzlení, magenergie propadne).
    if (!empty($vstup['preruseni'])) {
        if ($radek === null) {
            return null;
        }
        dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, $radek['id'], 'kouzleni_preruseno');
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'preruseno', $kouzloRadku, $radek['akci_hotovo'], $radek['akci_celkem']);
        return 'preruseno';
    }

    // Pokračování v kouzlení, které hra počítá.
    if ($radek !== null && $radek['akci_celkem'] !== null) {
        $celkem = $radek['akci_celkem'];
        $hotovo = min($celkem, (int)$radek['akci_hotovo'] + $pocet);
        if ($hotovo >= $celkem) {
            dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, $radek['id'], 'kouzleni_dokonceno');
            dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'dokonceno', $kouzloRadku, $celkem, $celkem);
            return 'dokonceno';
        }
        $pdo->prepare('UPDATE aktivni_efekty SET akci_hotovo = ? WHERE id = ?')->execute([$hotovo, $radek['id']]);
        $stav = $hotovo === $celkem - 1 ? 'ucinkuje' : 'pokracuje';
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, $stav, $kouzloRadku, $hotovo, $celkem);
        return $stav;
    }
    if ($radek !== null) {
        return null;   // ruční štítek (bez kouzla): počítá a sundává ho PJ
    }

    // Nové kouzlení.
    $kouzloId = (int)($vstup['kouzlo_id'] ?? 0);
    $kouzlo = $kouzloId > 0 ? dracak_vtt_kouzlo_info($pdo, $kouzloId) : null;
    $akci = $kouzlo['akci'] ?? null;
    if (isset($vstup['akci']) && $vstup['akci'] !== '' && $vstup['akci'] !== null) {
        $akci = (int)$vstup['akci'];   // ruční počet (PJ), má přednost
    }
    if (!$postup) {
        $akci = null;   // bez sloupců z migrace 0113 se akce nepočítají
    }
    if ($akci !== null && $pocet >= $akci) {
        // Jedna akce kouzlení stačí — kouzlo je sesláno hned a štítek nevzniká.
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'dokonceno', $kouzlo, $akci, $akci);
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
    if ($akci !== null) {
        $stav = $hotovo === $akci - 1 ? 'ucinkuje' : 'zahajeno';
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, $stav, $kouzlo, $hotovo, $akci);
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
        dracak_vtt_kouzli_udalost($svetId, $mapaId, $ucetId, $tvor, 'selhalo', $kouzlo, $radek['akci_hotovo'], $radek['akci_celkem']);
        return ['stav' => 'selhalo', 'kouzlo' => $kouzlo['nazev'] ?? null, 'cena' => $kouzlo['cena_magenergie'] ?? null];
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
