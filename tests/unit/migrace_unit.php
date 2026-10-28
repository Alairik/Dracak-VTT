<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/migrace.php';
$koren = dirname(__DIR__, 2);
$chyb = 0;
function ok(bool $c, string $m): void { global $chyb; if (!$c) { $chyb++; } echo ($c ? 'OK   ' : 'CHYBA') . " $m\n"; }

// 1) splitter: každý soubor v database/migrations se rozdělí na neprázdné příkazy
$prazdne = [];
foreach (glob($koren . '/database/migrations/*.sql') as $f) {
    $prikazy = dracak_migrace_rozdel_sql((string)file_get_contents($f));
    if (!$prikazy || array_filter($prikazy, static fn($p) => trim($p) === '')) { $prazdne[] = basename($f); }
}
ok($prazdne === [], 'každá migrace se rozdělí na neprázdné příkazy: ' . json_encode($prazdne));
// 2) zakázané příkazy: žádná existující migrace nesmí obsahovat zakázaný příkaz (jinak by ji stránka Změny databáze odmítla)
$starý = '/\b(DROP\s+TABLE|DROP\s+COLUMN|DROP\s+DATABASE|TRUNCATE)\b/i';
$nalezy = [];
foreach (glob($koren . '/database/migrations/*.sql') as $f) {
    $sql = file_get_contents($f);
    $n = dracak_migrace_zakazany_v_souboru($sql);
    if ($n !== null || preg_match($starý, $sql)) { $nalezy[basename($f)] = [$n, (bool)preg_match($starý, $sql)]; }
}
echo '   soubory, které něco zachytilo (nový, starý vzor): ' . json_encode($nalezy, JSON_UNESCAPED_UNICODE) . "\n";
// 3) syntetické
$t = dracak_migrace_rozdel_sql("-- komentář\nINSERT INTO t (x) VALUES ('řádek 1\n-- není komentář, je to text\nřádek 3');\n  -- další komentář\nUPDATE t SET x = 1;\n");
ok(count($t) === 2 && str_contains($t[0], '-- není komentář, je to text') && !str_contains(implode('', $t), 'další komentář'), 'splitter: „--“ v řetězci zůstane, komentářový řádek mimo řetězec zmizí');
ok(count(dracak_migrace_rozdel_sql("INSERT INTO t VALUES ('a;b''c;d');SELECT 1")) === 2, 'splitter: středník v řetězci se zdvojenou uvozovkou');
foreach (['DROP TABLE x', 'drop  column y', 'TRUNCATE t', 'TRUNCATE TABLE t', 'DROP VIEW v', 'ALTER TABLE x DROP old_col', 'ALTER TABLE x DROP INDEX i', 'ALTER TABLE x ADD COLUMN a INT, DROP FOREIGN KEY fk', 'ALTER TABLE x DROP PRIMARY KEY'] as $z) {
    ok(dracak_migrace_zakazany_prikaz($z) !== null, "zakázané: $z → " . dracak_migrace_zakazany_prikaz($z));
}
foreach (["INSERT INTO t (text) VALUES ('Kouzlo truncate; drop table světa; ALTER TABLE x DROP y')", "INSERT INTO t VALUES ('it''s a drop table trap')", 'ALTER TABLE x ADD COLUMN a INT NULL', 'ALTER TABLE x MODIFY COLUMN t ENUM(\'a\',\'b\')', "UPDATE predmety SET popis = 'Drop table: nic' WHERE id = 1", 'CREATE TABLE IF NOT EXISTS t (id INT)', 'INSERT INTO `drop` (a) VALUES (1)'] as $z) {
    ok(dracak_migrace_zakazany_prikaz($z) === null, 'povolené: ' . mb_substr($z, 0, 70));
}
$k = dracak_migrace_rozdel_klauzule("ADD COLUMN a INT NULL, ADD COLUMN b DECIMAL(8,2) NOT NULL DEFAULT 1 COMMENT 'čárka, v textu', ADD INDEX i (a, b), ADD CONSTRAINT fk FOREIGN KEY (a) REFERENCES t (id)");
ok(count($k) === 4 && str_starts_with($k[1], 'ADD COLUMN b') && str_starts_with($k[3], 'ADD CONSTRAINT'), 'klauzule se dělí jen na čárkách mimo závorky a řetězce: ' . count($k));

echo $chyb ? "\nSELHALO $chyb\n" : "\nVŠE OK\n";
exit($chyb ? 1 : 0);
