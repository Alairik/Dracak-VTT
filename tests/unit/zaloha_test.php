<?php
declare(strict_types=1);
// Záloha: dump → import do prázdné DB → stejné počty řádků a CHECKSUM TABLE u všech tabulek.
require dirname(__DIR__, 2) . '/includes/db.php';
require dirname(__DIR__, 2) . '/includes/zaloha.php';
$pdo = dracak_db();
$cil = 'dracak_obnova_test';
// Binární sloupec (bitmapa mlhy) s bajty 00 / FF / uvozovkami, aby se ověřilo hexadecimální zapsání; řádek se po testu smaže.
$pdo->exec("DELETE FROM mlha_valky WHERE mapa_id = 1 AND ucet_id = 1");
$pdo->exec("INSERT INTO mlha_valky (mapa_id, ucet_id, bitmapa, sirka_bunky, sloupcu, radku) VALUES (1, 1, UNHEX('0001FF2722005C0A0DFE'), 70, 5, 2)");
register_shutdown_function(static function () use ($pdo): void { $pdo->exec("DELETE FROM mlha_valky WHERE mapa_id = 1 AND ucet_id = 1"); });
$soubor = sys_get_temp_dir() . '/dracak_zaloha_test.sql';
$f = fopen($soubor, 'wb');
$t0 = microtime(true);
$v = dracak_zaloha_proud($pdo, static function (string $c) use ($f): void { fwrite($f, $c); });
fclose($f);
printf("dump: %d tabulek, %d řádků, %.1f MB, %.1f s, špička paměti %.1f MB\n", $v['tabulek'], $v['radku'], filesize($soubor) / 1048576, microtime(true) - $t0, memory_get_peak_usage(true) / 1048576);
$text = file_get_contents($soubor);
echo (str_contains($text, 'DROP ') ? 'CHYBA' : 'OK   ') . " v dumpu není DROP\n";
echo (substr_count($text, 'CREATE TABLE IF NOT EXISTS') === $v['tabulek'] ? 'OK   ' : 'CHYBA') . " každá tabulka má CREATE TABLE IF NOT EXISTS\n";
shell_exec("mysql -e 'DROP DATABASE IF EXISTS $cil; CREATE DATABASE $cil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'");
$cmd = "mysql --default-character-set=utf8mb4 $cil < " . escapeshellarg($soubor) . " 2>&1";
$out = shell_exec($cmd);
echo ($out === null || trim($out) === '' ? 'OK   ' : 'CHYBA') . " import do prázdné DB" . ($out ? ': ' . trim($out) : '') . "\n";
$ok = true;
$orig = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();   // databáze, na kterou míří config.php
foreach ($v['radku_podle_tabulek'] as $t => $n) {
    $a = (int)shell_exec("mysql -N $orig -e 'SELECT COUNT(*) FROM `$t`'");
    $b = (int)shell_exec("mysql -N $cil -e 'SELECT COUNT(*) FROM `$t`'");
    $ca = trim((string)shell_exec("mysql -N $orig -e 'CHECKSUM TABLE `$t`'"));
    $cb = trim((string)shell_exec("mysql -N $cil -e 'CHECKSUM TABLE `$t`'"));
    $sa = explode("\t", $ca)[1] ?? 'a'; $sb = explode("\t", $cb)[1] ?? 'b';
    if ($a !== $b || $a !== $n || $sa !== $sb) { $ok = false; echo "CHYBA $t: původní $a, obnoveno $b, v dumpu $n, checksum $sa / $sb\n"; }
}
echo ($ok ? 'OK   ' : 'CHYBA') . " počty řádků a CHECKSUM TABLE sedí u všech {$v['tabulek']} tabulek\n";
// druhý import do téže DB: CREATE IF NOT EXISTS projde, INSERT spadne na duplicitách (nic se nepřepíše)
$out2 = shell_exec("mysql --default-character-set=utf8mb4 $cil < " . escapeshellarg($soubor) . " 2>&1");
echo (str_contains((string)$out2, 'Duplicate') ? 'OK   ' : 'CHYBA') . " druhý import do neprázdné DB skončí chybou duplicity, nic nepřepíše\n";
$bm = (int)shell_exec("mysql -N $cil -e 'SELECT COALESCE(SUM(LENGTH(bitmapa)),0) FROM mlha_valky'");
$bo = (int)shell_exec("mysql -N $orig -e 'SELECT COALESCE(SUM(LENGTH(bitmapa)),0) FROM mlha_valky'");
echo ($bm === $bo && $bo > 0 ? 'OK   ' : 'CHYBA') . " binární bitmapa mlhy: $bo bajtů = $bm\n";
$diak = trim((string)shell_exec("mysql --default-character-set=utf8mb4 -N $cil -e 'SELECT nazev FROM povolani ORDER BY id LIMIT 3'"));
$diakO = trim((string)shell_exec("mysql --default-character-set=utf8mb4 -N $orig -e 'SELECT nazev FROM povolani ORDER BY id LIMIT 3'"));
echo ($diak === $diakO && $diak !== '' ? 'OK   ' : 'CHYBA') . " diakritika přežila: " . str_replace("\n", ', ', $diak) . "\n";
shell_exec("mysql -e 'DROP DATABASE $cil'");
unlink($soubor);
