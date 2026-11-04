<?php
declare(strict_types=1);
// Počet akcí kouzlení podle údaje „vyvolání“ (includes/vtt_kouzleni.php, dracak_vtt_kouzlo_akce): N kol = 2N akcí, „1 akce“ = 1, „ihned“ = 0 (nestojí akci, váže se k jiné
// akci), směny a vzorce se nepočítají (null). Spuštění: php tests/unit/kouzleni_unit.php
require_once __DIR__ . '/../../includes/vtt_kouzleni.php';
$chyb = 0;
function ok(bool $c, string $m): void { global $chyb; if (!$c) { $chyb++; } echo ($c ? 'OK    ' : 'CHYBA ') . $m . "\n"; }
$ocekavano = [
    '1 kolo' => 2, '2 kola' => 4, '3 kola' => 6, '4 kola' => 8, '5 kol' => 10, '6 kol' => 12, '10 kol' => 20,
    '1kolo' => 2, '3kola' => 6, '1 kolo.' => 2, '1 kolo, stále' => 2, ' 1 kolo ' => 2, '1 KOLO' => 2,
    '1 akce' => 1, '1 akce (1 bod únavy)' => 1, '1 akce (nedochází k ověřování)' => 1,
    'ihned' => 0, 'ihned - 1 bod únavy' => 0, 'ihned (seslání nestojí únavu)' => 0,
    '1 směna' => null, '2 směny' => null, '6 směn' => null, 'noc' => null, '—' => null, '' => null,
    '1 kolo za každých 5 sáhů výšky či délky - max 30…' => null, '4 kola +1 kolo za každý rozdíl úrovně' => null, '3kola +1 kolo za každých 10 sáhů' => null,
    '5 nebo 10 kol' => null, '(6-fanatismus) kol' => null, '1 kůň = 1 kolo' => null, '1 kolo, seslání 1 kolo' => null, '1 akce vyvolání, 1 akce seslání' => null,
    '1 akce za sáh vzdálenosti' => null, 'podle dodané magenergie' => null, '11 kol' => null, '0 kol' => null,
];
foreach ($ocekavano as $doba => $akci) {
    $v = dracak_vtt_kouzlo_akce((string)$doba);
    ok($v === $akci, '„' . $doba . '“ → ' . var_export($akci, true) . ($v === $akci ? '' : ' (je ' . var_export($v, true) . ')'));
}
ok(dracak_vtt_kouzlo_akce(null) === null, 'NULL → null');
echo $chyb ? "CHYB: $chyb\n" : "VŠE OK\n";
exit($chyb ? 1 : 0);
