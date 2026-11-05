<?php
declare(strict_types=1);
// Oblast viditelná z bodu mezi zdmi (includes/vtt_polygon.php): bez zdí je to celý čtverec, zeď vrhá stín, roh se obejde, zavřená místnost drží světlo uvnitř,
// výkon na velké mapě. Spuštění: php tests/unit/videni_polygon_unit.php
require_once __DIR__ . '/../../includes/vtt_polygon.php';
$chyb = 0;
function ok(bool $c, string $m): void { global $chyb; if (!$c) { $chyb++; } echo ($c ? 'OK    ' : 'CHYBA ') . $m . "\n"; }

// plocha mnohoúhelníku (Gauss)
function plocha(array $p): float { $s = 0.0; $n = count($p); for ($i = 0; $i < $n; $i++) { $j = ($i + 1) % $n; $s += $p[$i][0] * $p[$j][1] - $p[$j][0] * $p[$i][1]; } return abs($s) / 2; }

// 1) bez zdí: celý čtverec 2D × 2D
$p = dracak_vtt_videni_polygon([], 500, 500, 100);
ok(abs(plocha($p) - 200 * 200) < 1.0, 'bez zdí je viditelný celý čtverec (plocha ' . round(plocha($p)) . ' z 40000)');
ok(dracak_vtt_bod_v_polygonu($p, 590, 410) && !dracak_vtt_bod_v_polygonu($p, 650, 500), 'bod uvnitř čtverce ano, venku ne');

// 2) svislá zeď napravo od bodu (x = 550, od y = 450 do 550): za ní je stín
$zdi = [[550, 450, 550, 550]];
$p = dracak_vtt_videni_polygon($zdi, 500, 500, 100);
ok(dracak_vtt_bod_v_polygonu($p, 540, 500), 'před zdí je vidět');
ok(!dracak_vtt_bod_v_polygonu($p, 580, 500), 'přímo za zdí je stín');
ok(!dracak_vtt_bod_v_polygonu($p, 590, 520), 'šikmo za zdí je stín');
ok(dracak_vtt_bod_v_polygonu($p, 580, 410), 'nad zdí (mimo stín) je vidět');
ok(dracak_vtt_bod_v_polygonu($p, 580, 590), 'pod zdí (mimo stín) je vidět');
ok(plocha($p) < 200 * 200 - 1500, 'stín zmenšil oblast (plocha ' . round(plocha($p)) . ')');

// 3) roh: světlo za rohem zdi (L) se zalomí — bod za rohem je vidět, bod za zdí ne
$zdi = [[550, 400, 550, 520], [550, 520, 620, 520]];
$p = dracak_vtt_videni_polygon($zdi, 500, 560, 150);
ok(dracak_vtt_bod_v_polygonu($p, 580, 560), 'pod rohem se světlo dostane dál');
ok(!dracak_vtt_bod_v_polygonu($p, 580, 480), 'za zdí nad rohem je stín');

// 4) uzavřená místnost 100×100: z jejího středu je vidět jen místnost
$zdi = [[450, 450, 550, 450], [550, 450, 550, 550], [550, 550, 450, 550], [450, 550, 450, 450]];
$p = dracak_vtt_videni_polygon($zdi, 500, 500, 300);
ok(abs(plocha($p) - 10000) < 120, 'v zavřené místnosti je vidět jen místnost (plocha ' . round(plocha($p)) . ' z 10000)');
ok(!dracak_vtt_bod_v_polygonu($p, 560, 500) && dracak_vtt_bod_v_polygonu($p, 540, 460), 'venku stín, uvnitř vidět');
// dveře = jedna strana otevřená (mezera) → paprsek ven
$dvere = [[450, 450, 550, 450], [550, 450, 550, 490], [550, 510, 550, 550], [550, 550, 450, 550], [450, 550, 450, 450]];
$p = dracak_vtt_videni_polygon($dvere, 500, 500, 300);
ok(dracak_vtt_bod_v_polygonu($p, 700, 500), 'mezerou ve zdi světlo vyjde ven (vidět za dveřmi)');
ok(!dracak_vtt_bod_v_polygonu($p, 700, 600), 'mimo paprsek zůstává stín');

// 5) zdi mimo dosah se nepočítají; bod přesně na konci zdi nespadne
$p = dracak_vtt_videni_polygon([[5000, 5000, 5100, 5000]], 500, 500, 100);
ok(abs(plocha($p) - 40000) < 1.0, 'zeď daleko mimo dosah nic neřeže');
$p = dracak_vtt_videni_polygon([[500, 500, 560, 500]], 500, 500, 100);
ok(count($p) > 4, 'zeď začínající v bodě pohledu nic nerozbije (' . count($p) . ' vrcholů)');

// 6) výkon: 300 zdí, dosah 1500 px (30 sáhů po 50 px)
mt_srand(3);
$zdi = [];
for ($i = 0; $i < 300; $i++) { $x = mt_rand(0, 3800); $y = mt_rand(0, 2800); $zdi[] = [$x, $y, $x + mt_rand(50, 400), $y + mt_rand(-200, 200)]; }
$t = microtime(true);
for ($i = 0; $i < 10; $i++) { $p = dracak_vtt_videni_polygon($zdi, 1500 + $i * 10, 1500, 1500); }
$ms = (microtime(true) - $t) / 10 * 1000;
ok($ms < 150, sprintf('divák s dosahem 30 sáhů na mapě s 300 zdmi: %.1f ms, %d vrcholů', $ms, count($p)));

// 7) shoda s původním (pomalým) postupem „každý paprsek proti všem zdem“ na náhodných scénách, včetně města se sdílenými rohy a zdí přes bod pohledu
function polygon_pomaly(array $zdi, float $ox, float $oy, float $dosah): array
{
    $dosah = max(1.0, $dosah);
    $levy = $ox - $dosah; $pravy = $ox + $dosah; $horni = $oy - $dosah; $dolni = $oy + $dosah;
    $vse = [];
    foreach ($zdi as $z) {
        if (max($z[0], $z[2]) < $levy || min($z[0], $z[2]) > $pravy || max($z[1], $z[3]) < $horni || min($z[1], $z[3]) > $dolni) { continue; }
        $vse[] = [(float)$z[0], (float)$z[1], (float)$z[2], (float)$z[3]];
    }
    $vse = array_merge($vse, [[$levy, $horni, $pravy, $horni], [$pravy, $horni, $pravy, $dolni], [$pravy, $dolni, $levy, $dolni], [$levy, $dolni, $levy, $horni]]);
    $uhly = [];
    foreach ($vse as $u) {
        foreach ([[$u[0], $u[1]], [$u[2], $u[3]]] as [$px, $py]) {
            if (abs($px - $ox) < 1e-9 && abs($py - $oy) < 1e-9) { continue; }
            $a = atan2($py - $oy, $px - $ox);
            array_push($uhly, $a - 1e-5, $a, $a + 1e-5);
        }
    }
    sort($uhly);
    $body = [];
    foreach ($uhly as $a) {
        $nej = null;
        foreach ($vse as $u) {
            $t = dracak_vtt_paprsek_usecka($ox, $oy, cos($a), sin($a), $u[0], $u[1], $u[2], $u[3]);
            if ($t !== null && ($nej === null || $t < $nej)) { $nej = $t; }
        }
        if ($nej === null) { continue; }
        $x = round($ox + cos($a) * $nej, 1); $y = round($oy + sin($a) * $nej, 1);
        if ($body && $body[count($body) - 1][0] === $x && $body[count($body) - 1][1] === $y) { continue; }
        $body[] = [$x, $y];
    }
    return $body;
}
mt_srand(11);
$shoda = 0; $scen = 0;
for ($k = 0; $k < 40; $k++) {
    $zdi = [];
    $n = mt_rand(0, 60);
    for ($i = 0; $i < $n; $i++) { $x = mt_rand(0, 1000); $y = mt_rand(0, 1000); $zdi[] = [$x, $y, $x + mt_rand(-250, 250), $y + mt_rand(-250, 250)]; }
    if ($k % 4 === 0) { $zdi[] = [400, 500, 600, 500]; $zdi[] = [500, 500, 500, 700]; }   // zdi přes bod pohledu a končící v něm
    $ox = (float)(($k % 4 === 0) ? 500 : mt_rand(100, 900)); $oy = (float)(($k % 4 === 0) ? 500 : mt_rand(100, 900));
    $scen++;
    if (dracak_vtt_videni_polygon($zdi, $ox, $oy, 400) === polygon_pomaly($zdi, $ox, $oy, 400)) { $shoda++; }
}
// město: domy 210 px s ulicemi 70 px (sdílené rohy a rovnoběžné zdi)
$mesto = [];
for ($i = 0; $i < 6; $i++) for ($j = 0; $j < 5; $j++) { $x = 200 + $i * 280; $y = 200 + $j * 280; $s = 210; array_push($mesto, [$x, $y, $x + $s, $y], [$x + $s, $y, $x + $s, $y + $s], [$x + $s, $y + $s, $x, $y + $s], [$x, $y + $s, $x, $y]); }
foreach ([[375, 345, 420], [340, 175, 700], [655, 655, 500]] as [$ox, $oy, $d]) { $scen++; if (dracak_vtt_videni_polygon($mesto, (float)$ox, (float)$oy, (float)$d) === polygon_pomaly($mesto, (float)$ox, (float)$oy, (float)$d)) { $shoda++; } }
ok($shoda === $scen, "rychlý polygon dává stejný výsledek jako původní postup ($shoda z $scen náhodných scén a měst)");

// 8) výkon ve městě: 480 zdí (12 × 10 domů po čtyřech zdech), divák s dohledem 30 sáhů
$mesto = [];
for ($i = 0; $i < 12; $i++) for ($j = 0; $j < 10; $j++) { $x = 200 + $i * 280; $y = 200 + $j * 280; $s = 210; array_push($mesto, [$x, $y, $x + $s, $y], [$x + $s, $y, $x + $s, $y + $s], [$x + $s, $y + $s, $x, $y + $s], [$x, $y + $s, $x, $y]); }
$t = microtime(true);
for ($i = 0; $i < 10; $i++) { $p = dracak_vtt_videni_polygon($mesto, 375.0 + $i * 280, 345.0, 1500.0); }
$ms = (microtime(true) - $t) / 10 * 1000;
ok($ms < 60, sprintf('divák s dosahem 30 sáhů ve městě s %d zdmi: %.1f ms, %d vrcholů', count($mesto), $ms, count($p)));

echo $chyb ? "CHYB: $chyb\n" : "VŠE OK\n";
exit($chyb ? 1 : 0);
