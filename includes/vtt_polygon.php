<?php
declare(strict_types=1);

// Oblast viditelná z bodu mezi zdmi (visibility polygon) — podklad pro stíny a světlo na mapě „Noc“ / „Tma“ (docs/videni-a-svetlo-navrh.md, fáze 6).
//
// Čistá geometrie bez databáze: vstup jsou úsečky zdí (zavřené dveře jsou taky úsečky) a bod pohledu, výstup je mnohoúhelník hvězdicový kolem toho bodu,
// jehož hrany jsou buď kus zdi, nebo okraj čtverce kolem bodu (půlstrana $dosah). Klient mnohoúhelník použije jako ořez pro kruhový přechod světla,
// takže čtverec kolem bodu stačí a kruh se neřeže. Zeď, která bod „dotýká“ (leží na paprsku těsně vedle), nic neřeže — zdi jsou tenké čáry.
//
// Postup: úhly ke všem koncům zdí v dosahu (a těsně vedle nich, ±1e-5 rad, ať paprsek proklouzne za roh), z bodu se vyšle paprsek, nejbližší zásah
// (zeď nebo okraj čtverce) je vrchol. Vrcholy seřazené podle úhlu jsou mnohoúhelník.

// Průsečík paprsku z [ox, oy] ve směru [dx, dy] s úsečkou [x1, y1, x2, y2]: vzdálenost po paprsku (v násobcích směru), nebo null.
function dracak_vtt_paprsek_usecka(float $ox, float $oy, float $dx, float $dy, float $x1, float $y1, float $x2, float $y2): ?float
{
    $sx = $x2 - $x1;
    $sy = $y2 - $y1;
    $jmenovatel = $dx * $sy - $dy * $sx;
    if (abs($jmenovatel) < 1e-12) {
        return null; // rovnoběžně
    }
    $t = (($x1 - $ox) * $sy - ($y1 - $oy) * $sx) / $jmenovatel;
    $u = (($x1 - $ox) * $dy - ($y1 - $oy) * $dx) / $jmenovatel;
    if ($t < 1e-9 || $u < -1e-9 || $u > 1 + 1e-9) {
        return null;
    }
    return $t;
}

// $zdi: [[x1, y1, x2, y2, ...], ...] (další prvky se ignorují, takže jde použít přímo výstup dracak_vtt_videni_zdi()). $dosah: půlstrana čtverce kolem bodu v px.
// Vrací seznam vrcholů [[x, y], ...] proti směru hodinových ručiček (ve směru rostoucího úhlu), zaokrouhlených na desetiny px.
function dracak_vtt_videni_polygon(array $zdi, float $ox, float $oy, float $dosah): array
{
    $dosah = max(1.0, $dosah);
    $levy = $ox - $dosah; $pravy = $ox + $dosah; $horni = $oy - $dosah; $dolni = $oy + $dosah;
    // Úsečky v dosahu (obal zdi proti čtverci) + čtyři strany čtverce jako zdi.
    $useky = [];
    foreach ($zdi as $z) {
        if (max($z[0], $z[2]) < $levy || min($z[0], $z[2]) > $pravy || max($z[1], $z[3]) < $horni || min($z[1], $z[3]) > $dolni) {
            continue;
        }
        $useky[] = [(float)$z[0], (float)$z[1], (float)$z[2], (float)$z[3]];
    }
    $ramecek = [
        [$levy, $horni, $pravy, $horni], [$pravy, $horni, $pravy, $dolni],
        [$pravy, $dolni, $levy, $dolni], [$levy, $dolni, $levy, $horni],
    ];
    $vse = array_merge($useky, $ramecek);

    // Úhly: ke všem koncům úseček a rohům čtverce, plus těsně vedle nich.
    $uhly = [];
    foreach ($vse as $u) {
        foreach ([[$u[0], $u[1]], [$u[2], $u[3]]] as [$px, $py]) {
            if (abs($px - $ox) < 1e-9 && abs($py - $oy) < 1e-9) {
                continue;
            }
            $a = atan2($py - $oy, $px - $ox);
            $uhly[] = $a - 1e-5;
            $uhly[] = $a;
            $uhly[] = $a + 1e-5;
        }
    }
    sort($uhly);

    $body = [];
    foreach ($uhly as $a) {
        $dx = cos($a);
        $dy = sin($a);
        $nej = null;
        foreach ($vse as $u) {
            $t = dracak_vtt_paprsek_usecka($ox, $oy, $dx, $dy, $u[0], $u[1], $u[2], $u[3]);
            if ($t !== null && ($nej === null || $t < $nej)) {
                $nej = $t;
            }
        }
        if ($nej === null) {
            continue;
        }
        $x = round($ox + $dx * $nej, 1);
        $y = round($oy + $dy * $nej, 1);
        $posledni = $body ? $body[count($body) - 1] : null;
        if ($posledni !== null && $posledni[0] === $x && $posledni[1] === $y) {
            continue;
        }
        $body[] = [$x, $y];
    }
    return $body;
}

// Leží bod [x, y] uvnitř mnohoúhelníku? (sudo-lichá paritní zkouška, pro kontroly a testy)
function dracak_vtt_bod_v_polygonu(array $polygon, float $x, float $y): bool
{
    $uvnitr = false;
    $n = count($polygon);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        [$xi, $yi] = $polygon[$i];
        [$xj, $yj] = $polygon[$j];
        if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
            $uvnitr = !$uvnitr;
        }
    }
    return $uvnitr;
}
