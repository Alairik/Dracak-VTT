<?php
declare(strict_types=1);

// Hodiny světa (migrace 0109; plán C bod 17): čas během dne a hoření světel.
//
// Den už počítá svet.aktualni_den_offset (PJova ruční volba, svet_administrace.php, a cesta na pin). Tady přibývá čas ve dni jako počet směn
// (svet.aktualni_smena, 0–143): jedna směna trvá zhruba deset minut (b33), den má tedy 144 směn a 48 je 8:00. Čas posouvá výhradně PJ — nic se
// nedopočítává z pohybu po mapě ani z boje samo (kniha b11115 sice říká, že i krátký boj se počítá za celou směnu, ale kdy čas posunout, nechává na PJ).
// Posun času o N směn odečte N od tokenu.svetlo_zbyva u všech tokenů ve světě, které svítí (nebo drží zónu tmy) s omezenou dobou; na nule zhasnou.
// Doby z knihy: pochodeň 3 směny (h1685 b11183), lucerna 24 směn na láhev oleje (b11184), Temnota druida 3 směny (b1936).

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_mapa_stav.php';

const DRACAK_VTT_SMEN_ZA_DEN = 144;
const DRACAK_VTT_SMENA_VYCHOZI = 48;

// 'hh:mm' ze směny dne (směna = 10 minut).
function dracak_vtt_cas_text(int $smena): string
{
    $minut = max(0, min(DRACAK_VTT_SMEN_ZA_DEN - 1, $smena)) * 10;
    return intdiv($minut, 60) . ':' . str_pad((string)($minut % 60), 2, '0', STR_PAD_LEFT);
}

// Aktuální čas světa pro rozhraní: ['den' => offset, 'smena' => 0–143, 'text' => 'hh:mm', 'datum' => 'd. m. Y'], nebo null bez sloupce (migrace 0109).
function dracak_vtt_cas_stav(PDO $pdo, int $svetId): ?array
{
    if (empty(dracak_vtt_tokeny_funkce()['hodiny'])) {
        return null;
    }
    $q = $pdo->prepare('SELECT pocatecni_datum, aktualni_den_offset, aktualni_smena FROM svet WHERE id = ?');
    $q->execute([$svetId]);
    $s = $q->fetch();
    if (!$s) {
        return null;
    }
    $datum = (new DateTimeImmutable((string)$s['pocatecni_datum']))->modify('+' . (int)$s['aktualni_den_offset'] . ' days');
    return [
        'den' => (int)$s['aktualni_den_offset'],
        'smena' => (int)$s['aktualni_smena'],
        'text' => dracak_vtt_cas_text((int)$s['aktualni_smena']),
        'datum' => $datum->format('j. n. Y'),
    ];
}

// Posune čas o $smeny (1 a víc) dopředu, přelévá do dnů a odečte hoření světel. Vrací nový stav + seznam zhasnutých tokenů, nebo null bez sloupce.
function dracak_vtt_cas_posun(PDO $pdo, int $svetId, int $smeny, int $ucetId): ?array
{
    $f = dracak_vtt_tokeny_funkce();
    if (empty($f['hodiny']) || $smeny < 1) {
        return null;
    }
    $zhasla = [];
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT aktualni_smena FROM svet WHERE id = ? FOR UPDATE');
        $q->execute([$svetId]);
        $cur = $q->fetchColumn();
        if ($cur === false) {
            $pdo->rollBack();
            return null;
        }
        $celkem = (int)$cur + $smeny;
        $pdo->prepare('UPDATE svet SET aktualni_smena = ?, aktualni_den_offset = aktualni_den_offset + ? WHERE id = ?')
            ->execute([$celkem % DRACAK_VTT_SMEN_ZA_DEN, intdiv($celkem, DRACAK_VTT_SMEN_ZA_DEN), $svetId]);

        if (!empty($f['svetlo_zbyva'])) {
            $q = $pdo->prepare(
                'SELECT t.id, t.mapa_id, t.svetlo_sahy, t.svetlo_typ, t.svetlo_zbyva FROM tokeny t JOIN mapy m ON m.id = t.mapa_id
                 WHERE m.svet_id = ? AND t.svetlo_sahy IS NOT NULL AND t.svetlo_zbyva IS NOT NULL FOR UPDATE'
            );
            $q->execute([$svetId]);
            foreach ($q->fetchAll() as $t) {
                if ((int)$t['svetlo_zbyva'] <= $smeny) {
                    $pdo->prepare('UPDATE tokeny SET svetlo_sahy = NULL, svetlo_typ = NULL, svetlo_zbyva = NULL WHERE id = ?')->execute([(int)$t['id']]);
                    $zhasla[] = $t;
                } else {
                    $pdo->prepare('UPDATE tokeny SET svetlo_zbyva = svetlo_zbyva - ? WHERE id = ?')->execute([$smeny, (int)$t['id']]);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return dracak_vtt_cas_po_zmene($pdo, $svetId, $ucetId, $zhasla);
}

// Nastaví čas ve dni přesně (0–143), bez odečítání hoření (PJ opravuje čas, neuplynul).
function dracak_vtt_cas_nastav(PDO $pdo, int $svetId, int $smenaDne, int $ucetId): ?array
{
    if (empty(dracak_vtt_tokeny_funkce()['hodiny']) || $smenaDne < 0 || $smenaDne >= DRACAK_VTT_SMEN_ZA_DEN) {
        return null;
    }
    $pdo->prepare('UPDATE svet SET aktualni_smena = ? WHERE id = ?')->execute([$smenaDne, $svetId]);
    return dracak_vtt_cas_po_zmene($pdo, $svetId, $ucetId, []);
}

// Zapíše události po změně času (hodiny a zhasnutá světla) a vrátí odpověď pro API.
function dracak_vtt_cas_po_zmene(PDO $pdo, int $svetId, int $ucetId, array $zhasla): array
{
    foreach ($zhasla as $t) {
        dracak_vtt_log_event($svetId, (int)$t['mapa_id'], 'token_zmena', [
            'token_id' => (int)$t['id'],
            'zmeny' => ['svetlo' => null, 'svetlo_typ' => null, 'zbyva' => null],
            'pred' => ['svetlo' => $t['svetlo_sahy'] === null ? null : (float)$t['svetlo_sahy'], 'svetlo_typ' => $t['svetlo_typ'], 'zbyva' => (int)$t['svetlo_zbyva']],
        ], $ucetId);
    }
    $stav = dracak_vtt_cas_stav($pdo, $svetId) ?? [];
    $stav['zhasla'] = array_map(static fn($t) => (int)$t['id'], $zhasla);
    $stav['udalost_id'] = dracak_vtt_log_event($svetId, null, 'cas_zmena', $stav, $ucetId);
    return $stav;
}
