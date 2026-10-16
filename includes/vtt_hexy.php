<?php
declare(strict_types=1);

// Hexová geometrie pro souboj (content/pravidla-hrac.html h1621, h1623):
// sousedé, směry čelem, vzdálenost, 3 hexy před tvorem. Mapa kreslí hexy jako
// "pointy-top" v rozložení odd-r (liché řady posunuté doprava o půl buňky),
// viz renderGrid() v assets/js/mapa.js a dracak_vtt_mlha_rozmery()
// (includes/vtt_mlha.php), odkud se berou rozměry gridu. Buňka = [col, row].
//
// Směr čelem (tokeny.smer) je ve stupních, 0 = doprava, po směru hodinových
// ručiček (jako na obrazovce); šest stran hexu jsou násobky 60°. Číslujeme je
// 0..5: 0 východ, 1 jihovýchod, 2 jihozápad, 3 západ, 4 severozápad,
// 5 severovýchod.
//
// Stejná matematika je v assets/js/hexy.js — drží se spolu (test:
// scripts mimo repo porovnávají obě na mřížce buněk, viz docs/stav-projektu.md).

require_once __DIR__ . '/vtt_mlha.php';

// Osové souřadnice (q, r) pro daný směr 0..5 — pořadí sedí na úhly 0°, 60°, … .
const DRACAK_VTT_HEX_SMERY = [[1, 0], [0, 1], [-1, 1], [-1, 0], [0, -1], [1, -1]];

// Rozměry gridu mapy, jen když je mapa hexová a má grid; jinak null (hexové
// akce souboje na ní nedávají smysl).
function dracak_vtt_hex_rozmery(array $mapa): ?array
{
    if (($mapa['grid_typ'] ?? 'ctverec') !== 'hex' || (int)($mapa['grid_velikost_px'] ?? 0) <= 0) {
        return null;
    }
    $roz = dracak_vtt_mlha_rozmery($mapa);
    return $roz['typ'] === 'hex' && isset($roz['col_step']) ? $roz : null;
}

// Do které buňky patří bod (x, y): [col, row].
function dracak_vtt_hex_bunka(array $roz, float $x, float $y): array
{
    $b = dracak_vtt_mlha_bunka_index($roz, $x, $y);
    return [(int)$b['col'], (int)$b['row']];
}

// Střed buňky v px: [x, y].
function dracak_vtt_hex_stred(array $roz, array $bunka): array
{
    $s = dracak_vtt_mlha_bunka_stred($roz, (int)$bunka[1], (int)$bunka[0]);
    return [(float)$s['x'], (float)$s['y']];
}

// [col, row] -> osové [q, r] a zpět.
function dracak_vtt_hex_na_osy(array $b): array
{
    return [$b[0] - intdiv($b[1] - ($b[1] & 1), 2), $b[1]];
}
function dracak_vtt_hex_z_os(int $q, int $r): array
{
    return [$q + intdiv($r - ($r & 1), 2), $r];
}

// Soused v daném směru (0..5).
function dracak_vtt_hex_soused(array $b, int $smer): array
{
    [$q, $r] = dracak_vtt_hex_na_osy($b);
    $d = DRACAK_VTT_HEX_SMERY[(($smer % 6) + 6) % 6];
    return dracak_vtt_hex_z_os($q + $d[0], $r + $d[1]);
}

// Počet kroků mezi dvěma buňkami (jeden hex = jeden sáh).
function dracak_vtt_hex_vzdalenost(array $a, array $b): int
{
    [$q1, $r1] = dracak_vtt_hex_na_osy($a);
    [$q2, $r2] = dracak_vtt_hex_na_osy($b);
    $dq = $q1 - $q2;
    $dr = $r1 - $r2;
    return (int)max(abs($dq), abs($dr), abs($dq + $dr));
}

// Nejbližší ze šesti stran pro úhel ve stupních: 0..5.
function dracak_vtt_hex_smer_idx(float $stupne): int
{
    return ((int)round($stupne / 60.0) % 6 + 6) % 6;
}

// O kolik šedesátistupňových kroků se liší dva směry: 0..3 (3 = přesně opačně).
function dracak_vtt_hex_rozdil_smeru(int $a, int $b): int
{
    $d = abs((($a - $b) % 6 + 6) % 6);
    return min($d, 6 - $d);
}

// Tři hexy před tvorem, který stojí na buňce $b a hledí na stranu $smer:
// ten přímo před ním a oba vedle něj (h1623 — útok jen na 3 sousední hexy,
// ke kterým stojí útočník čelem).
function dracak_vtt_hex_predni(array $b, int $smer): array
{
    return [
        dracak_vtt_hex_soused($b, $smer - 1),
        dracak_vtt_hex_soused($b, $smer),
        dracak_vtt_hex_soused($b, $smer + 1),
    ];
}

// --- Tvorové na více hexech (HB, content/pravidla-hrac.html h1623) ---
// Tvar je seznam osových offsetů [q, r] vůči "otočnému hexu" (hex, kolem
// kterého se tvor otáčí a kde stojí jeho token) při orientaci 0. Orientace
// 0..5 je počet šedesátistupňových otočení po směru hodinových ručiček.
//  - typ_otaceni "smer" (1 a 2 hexy): orientace = směr čelem 0..5, tvar je
//    nakreslený čelem na východ (zvířecí C: otočný hex = zadek, hlava před ním)
//  - typ_otaceni "hrana" (3 a 5 hexů): čelo je hrana obou horních hexů,
//    tvar je nakreslený čelem nahoru; směr v tokeny.smer je pak úhel středu
//    čelní hrany (270° = nahoru, každá orientace +60°)
// "predni" jsou pohledové hexy (hexy čelní hrany; u 1 a 2 hexů hlava).

// Otočí osový offset o $kroky šedesátistupňových kroků po směru hodinových ručiček.
function dracak_vtt_hex_otoc_os(array $qr, int $kroky): array
{
    $k = (($kroky % 6) + 6) % 6;
    [$q, $r] = $qr;
    for ($i = 0; $i < $k; $i++) {
        [$q, $r] = [-$r, $q + $r];
    }
    return [$q, $r];
}

// Buňky tvaru ($klic = "bunky" celé tělo, "predni" pohledové hexy) pro daný
// otočný hex a orientaci: seznam [col, row].
function dracak_vtt_hex_bunky_tvaru(array $tvar, array $pivot, int $orientace, string $klic = 'bunky'): array
{
    [$pq, $pr] = dracak_vtt_hex_na_osy($pivot);
    $vysledek = [];
    foreach ($tvar[$klic] as $o) {
        [$dq, $dr] = dracak_vtt_hex_otoc_os([(int)$o[0], (int)$o[1]], $orientace);
        $vysledek[] = dracak_vtt_hex_z_os($pq + $dq, $pr + $dr);
    }
    return $vysledek;
}

// Základní úhel tvaru: kolik stupňů má tokeny.smer při orientaci 0.
function dracak_vtt_hex_zaklad_smeru(array $tvar): int
{
    return ($tvar['typ_otaceni'] ?? 'smer') === 'hrana' ? 270 : 0;
}

// tokeny.smer (stupně) -> orientace 0..5, a zpět.
function dracak_vtt_hex_orientace(array $tvar, float $smerStupne): int
{
    return dracak_vtt_hex_smer_idx($smerStupne - dracak_vtt_hex_zaklad_smeru($tvar));
}
function dracak_vtt_hex_smer_z_orientace(array $tvar, int $orientace): int
{
    return (((dracak_vtt_hex_zaklad_smeru($tvar) + 60 * $orientace) % 360) + 360) % 360;
}

// Hexy, na které smí tvor útočit (h1623): "predni3" = 3 hexy před pohledovým
// hexem (u 1 a 2 hexů), "okoli" = všechny hexy sousedící s pohledovými hexy
// (u tvorů s čelní hranou). Vlastní tělo je vyloučené.
function dracak_vtt_hex_dosah_utoku(array $tvar, array $pivot, int $orientace): array
{
    $telo = [];
    foreach (dracak_vtt_hex_bunky_tvaru($tvar, $pivot, $orientace, 'bunky') as $b) {
        $telo[$b[0] . ',' . $b[1]] = true;
    }
    $dosah = [];
    foreach (dracak_vtt_hex_bunky_tvaru($tvar, $pivot, $orientace, 'predni') as $c) {
        $smery = ($tvar['utok'] ?? 'predni3') === 'okoli' ? [0, 1, 2, 3, 4, 5] : [$orientace - 1, $orientace, $orientace + 1];
        foreach ($smery as $k) {
            $n = dracak_vtt_hex_soused($c, $k);
            $klic = $n[0] . ',' . $n[1];
            if (!isset($telo[$klic])) {
                $dosah[$klic] = $n;
            }
        }
    }
    return array_values($dosah);
}
