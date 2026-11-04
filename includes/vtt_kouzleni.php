<?php
declare(strict_types=1);

// Kouzlení v rozšířeném soubojovém systému (kapitola Kouzlení, b10842–b10847; rozhodnutí uživatele 2026-11-04).
//
// Kdo odehrál akci „kouzlení“, dostane štítek-efekt „Kouzlí“ (migrace 0112): pro útočníky je nehybný (složka `nehybny`, h1613).
// Akce kouzlení musí následovat těsně po sobě, proto štítek zmizí, jakmile tvor provede jinou akci (kouzlení se přerušilo),
// a na konci souboje. Po poslední akci kouzlení ho sundá PJ — boj nezná počet akcí kouzla. Zásah kouzlícího boj jen ohlásí.

require_once __DIR__ . '/vtt_efekty.php';

const DRACAK_VTT_EFEKT_KOUZLI = 'Kouzlí';

function dracak_vtt_kouzli_efekt_id(PDO $pdo): int
{
    $q = $pdo->prepare('SELECT id FROM efekty WHERE nazev = ?');
    $q->execute([DRACAK_VTT_EFEKT_KOUZLI]);
    return (int)$q->fetchColumn();
}

// ID aktivního štítku „Kouzlí“ na tvorovi, nebo null.
function dracak_vtt_kouzli_aktivni_id(PDO $pdo, string $typEntity, int $entitaId): ?int
{
    if (!dracak_vtt_efekty_funkce()) {
        return null;
    }
    $q = $pdo->prepare(
        'SELECT ae.id FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id
         WHERE ae.typ_entity = ? AND ae.entita_id = ? AND e.nazev = ? ORDER BY ae.id LIMIT 1'
    );
    $q->execute([$typEntity, $entitaId, DRACAK_VTT_EFEKT_KOUZLI]);
    $id = $q->fetchColumn();
    return $id !== false ? (int)$id : null;
}

// Po provedené akci: kouzlení nasadí štítek, jakákoli jiná akce (která stála akci) ho sundá. Volat uvnitř transakce.
// $tvor = řádek z dracak_vtt_souboj_tokeny_info(). Vrací 'nasazen' | 'prerusen' | null.
function dracak_vtt_kouzli_po_akci(PDO $pdo, int $svetId, int $mapaId, int $ucetId, array $tvor, string $druh, int $pocet): ?string
{
    if ($pocet < 1 || !dracak_vtt_efekty_funkce() || !in_array($tvor['typ_entity'], ['postava', 'nestvura_instance'], true)) {
        return null;
    }
    $efektId = dracak_vtt_kouzli_efekt_id($pdo);
    if ($efektId <= 0) {
        return null;
    }
    $entitaId = (int)$tvor['entita_id'];
    $aktivni = dracak_vtt_kouzli_aktivni_id($pdo, $tvor['typ_entity'], $entitaId);
    if ($druh === 'kouzleni') {
        if ($aktivni !== null) {
            return null;
        }
        $v = dracak_vtt_efekt_aplikuj($pdo, $mapaId, $tvor['typ_entity'], $entitaId, $efektId, ['zbyva_kol' => null]);
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
            'duvod_text' => 'kouzlí — pro útočníky nehybný; po poslední akci kouzlení ho sundá PJ',
        ], $ucetId);
        return 'nasazen';
    }
    if ($aktivni !== null) {
        dracak_vtt_efekt_odstran($pdo, $svetId, $mapaId, $ucetId, $aktivni, 'kouzleni_prerusene');
        return 'prerusen';
    }
    return null;
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
