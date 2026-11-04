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

echo $chyb ? "CHYB: $chyb\n" : "VŠE OK\n";
exit($chyb ? 1 : 0);
