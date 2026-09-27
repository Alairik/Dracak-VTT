<?php
declare(strict_types=1);

// Volá se přes HTTP z GitHub Actions po úspěšném FTP Deploy (poslední
// krok v deploy.yml), hlavička X-Migrate-Token nebo ?token=. Spustí jen
// nové soubory z database/migrations/, eviduje je v migrace_log. Tenhle
// soubor je JEDINÝ, co smí být v scripts/ na serveru — nahrává se přes
// FTP (viz deploy.yml), na rozdíl od zbytku database/ ke kterému patří.
//
// NIKDY nespouštěj migraci s DROP / TRUNCATE / jinak destruktivním
// příkazem bez výslovného schválení v konverzaci (viz CLAUDE.md) — níž
// je na to i automatická pojistka, co takový soubor odmítne spustit.
//
// DŮLEŽITÉ o transakcích: MySQL/MariaDB dělá u DDL (CREATE TABLE,
// ALTER TABLE...) implicitní COMMIT před i po příkazu — DDL NENÍ
// transakční jako v Postgresu. BEGIN/COMMIT/ROLLBACK tu reálně chrání
// jen INSERT/UPDATE/DELETE. Pokud má soubor víc ALTER příkazů a třetí
// selže, první dva zůstanou v DB aplikované i po rollbacku — to je
// limit databázového enginu, ne chyba tohohle skriptu. Soubor se v
// takovém případě nezapíše do migrace_log (takže se příště zkusí
// znovu — proto ať jsou migrace pokud možno malé a soustředěné).

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

// KRITICKÉ: bez bufferu tenhle skript loguje "tichý úspěch". Jakmile PHP
// jednou pošle klientovi byte těla (první echo o kus níž — "Přeskakuji
// ..."/"Spouštím ..."), HTTP hlavičky jsou nevratně odeslané s výchozím
// 200 — pozdější http_response_code(500) v migrate_fail() (nebo v
// register_shutdown_function níž) na tom už nic nezmění, jen tiše
// neuspěje. deploy.yml pak vidí HTTP 200 a označí krok za zelený, i když
// log těla jasně říká "Chyba v ...soubor.sql". ob_start() tady drží celé
// tělo v bufferu, dokud skript neskončí — teprve pak PHP hlavičky pošle,
// takže http_response_code() zůstane platný po celou dobu běhu.
ob_start();

function migrate_fail(int $httpCode, string $message): void
 {
  http_response_code($httpCode);
  echo $message . "\n";
  exit;
 }

// Poslední pojistka. Cokoliv, co unikne try/catch níž — TypeError,
// Error, fatální chyba za běhu — by jinak s display_errors=0 skončilo
// jako prázdné tělo + HTTP 500 bez jediné zprávy, a GitHub Actions log
// by neřekl vůbec nic (přesně tenhle případ: TypeError z `new PDO(...)`
// při netypovém configu níž NENÍ PDOException, takže tamní catch ho
// nechytí). Tohle zachytí i chyby mimo Exception hierarchii a vrátí je
// jako čitelný text místo ticha.
set_exception_handler(function (Throwable $e): void {
 if (!headers_sent()) {
  http_response_code(500);
 }
 echo 'Nezachycená chyba (' . get_class($e) . '): ' . $e->getMessage()
  . "\n  v " . $e->getFile() . ':' . $e->getLine() . "\n";
});
register_shutdown_function(function (): void {
 $err = error_get_last();
 if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
  if (!headers_sent()) {
   http_response_code(500);
  }
  echo 'Fatální PHP chyba: ' . $err['message']
   . "\n  v " . $err['file'] . ':' . $err['line'] . "\n";
 }
});

// Rozdělí soubor na jednotlivé příkazy. Řádky s "--" komentářem se
// zahodí celé, zbytek se prochází znak po znaku a sleduje se, jestli
// jsme uvnitř řetězcového literálu — středník uvnitř řetězce (běžné u
// narativního textu z pravidel, viz např. 0016/0017: "...nezíská žádné
// další životy; naopak...") se nesmí počítat jako konec příkazu.
// Rozpozná obě obvyklá zapsání uvozovky uvnitř řetězce: zdvojenou ('')
// i zpětným lomítkem (\') — ale spoléhá se čistě na dvojici uvozovek/
// lomítko v textu, ne na skutečný SQL_MODE serveru. Pořád to není
// obecný SQL parser (neřeší např. dvojité uvozovky, komentáře /* */
// uvnitř řádku ani vícebajtové sekvence, které by náhodou obsahovaly
// byte 0x27) — jen tolik, kolik naše migrace reálně potřebují.
function migrate_split_sql(string $sql): array
 {
  $lines = array_filter(
   explode("\n", $sql),
   fn($line) => strpos(trim($line), '--') !== 0
   );
  $clean = implode("\n", $lines);

  $statements = [];
  $current = '';
  $len = strlen($clean);
  $inString = false;
  $backslashes = 0;
  for ($i = 0; $i < $len; $i++) {
   $ch = $clean[$i];
   if ($ch === '\\') {
    $backslashes++;
    $current .= $ch;
    continue;
   }
   if ($ch === "'") {
    $escaped = $inString && ($backslashes % 2) === 1;
    $backslashes = 0;
    if ($escaped) {
     $current .= $ch;
     continue;
    }
    if ($inString && ($i + 1) < $len && $clean[$i + 1] === "'") {
     $current .= "''";
     $i++;
     continue;
    }
    $inString = !$inString;
    $current .= $ch;
    continue;
   }
   $backslashes = 0;
   if ($ch === ';' && !$inString) {
    $trimmed = trim($current);
    if ($trimmed !== '') $statements[] = $trimmed;
    $current = '';
    continue;
   }
   $current .= $ch;
  }
  $trimmed = trim($current);
  if ($trimmed !== '') $statements[] = $trimmed;
  return $statements;
 }

// Stejný princip jako fallback pro migrace_log níž, jen obecně pro
// libovolnou migraci: "CREATE TABLE IF NOT EXISTS" pod web účtem
// (DML-only) spadne na oprávnění (1142) i když tabulka už existuje —
// IF NOT EXISTS potlačí jen chybu "already exists", ne kontrolu
// oprávnění. Když se to stane, ověříme přes SELECT, jestli je tabulka
// použitelná (typicky proto, že ji admin mezitím ručně založil přes
// phpMyAdmin) — pokud ano, bereme to jako "už hotovo" a jedeme dál na
// zbytek souboru (typicky INSERTy), místo abychom po nasazení museli
// každou takovou migraci ještě ručně zapisovat do migrace_log zvlášť.
function migrate_je_create_table_if_not_exists(string $stmt, ?string &$tabulka): bool
 {
  if (preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', trim($stmt), $m)) {
   $tabulka = $m[1];
   return true;
  }
  return false;
 }

// Stejný princip jako výš, pro "ALTER TABLE ... ADD/MODIFY" — pokryje
// jen vzory, co CLAUDE.md u migrací povoluje (ALTER ADD, plus MODIFY
// COLUMN ... ENUM(...) jako rozšíření výčtu, což je taky čistě
// přídavné, nic nemaže). Cokoliv jiného (např. MODIFY měnící typ na
// něco, co by mohlo zahodit data) tenhle fallback záměrně NEPOZNÁ a
// nechá to spadnout normálně — radši hlasitá chyba než tiché
// přeskočení něčeho, co jsme neověřili.
function migrate_alter_uz_hotovo(PDO $pdo, string $stmt, ?string &$popis): bool
 {
  if (!preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+(.*)$/is', trim($stmt), $m)) {
   return false;
  }
  $tabulka = $m[1];
  $zbytek = trim($m[2]);

  // ADD [COLUMN] jméno ...
  if (preg_match('/^ADD\s+(?:COLUMN\s+)?`?(\w+)`?/i', $zbytek, $mm)) {
   $sloupec = $mm[1];
   $q = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS'
    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
   $q->execute([$tabulka, $sloupec]);
   if ((int)$q->fetchColumn() > 0) {
    $popis = "sloupec $tabulka.$sloupec už existuje";
    return true;
   }
   return false;
  }

  // ADD [CONSTRAINT jméno] [UNIQUE] (KEY|INDEX) jméno ...
  if (preg_match('/^ADD\s+(?:CONSTRAINT\s+`?\w+`?\s+)?(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?(\w+)`?/i', $zbytek, $mm)) {
   $jmeno = $mm[1];
   $q = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.STATISTICS'
    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
   $q->execute([$tabulka, $jmeno]);
   if ((int)$q->fetchColumn() > 0) {
    $popis = "index $tabulka.$jmeno už existuje";
    return true;
   }
   return false;
  }

  // ADD CONSTRAINT jméno FOREIGN KEY ...
  if (preg_match('/^ADD\s+CONSTRAINT\s+`?(\w+)`?\s+FOREIGN\s+KEY/i', $zbytek, $mm)) {
   $jmeno = $mm[1];
   $q = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS'
    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
   $q->execute([$tabulka, $jmeno]);
   if ((int)$q->fetchColumn() > 0) {
    $popis = "cizí klíč $tabulka.$jmeno už existuje";
    return true;
   }
   return false;
  }

  // MODIFY [COLUMN] jméno ... ENUM('a','b',...) — jen rozšiřování
  // výčtu se dá bezpečně ověřit: pokud tam všechny požadované hodnoty
  // už jsou, je to hotovo (netestujeme, jestli tam náhodou nechybí
  // starší hodnota, kterou by ALTER odebíral — to by nebyl přídavný
  // ALTER a do migrace by nepatřil vůbec).
  if (preg_match('/^MODIFY\s+(?:COLUMN\s+)?`?(\w+)`?.*?ENUM\s*\((.*?)\)/is', $zbytek, $mm)) {
   $sloupec = $mm[1];
   preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $mm[2], $mmValues);
   $pozadovane = $mmValues[1];
   if (!$pozadovane) {
    return false;
   }
   $q = $pdo->prepare(
    'SELECT COLUMN_TYPE FROM information_schema.COLUMNS'
    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
   $q->execute([$tabulka, $sloupec]);
   $aktualniTyp = $q->fetchColumn();
   if ($aktualniTyp === false) {
    return false;
   }
   preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", (string)$aktualniTyp, $aktualniValues);
   $aktualni = $aktualniValues[1];
   $chybi = array_diff($pozadovane, $aktualni);
   if (!$chybi) {
    $popis = "$tabulka.$sloupec už má všechny požadované hodnoty výčtu";
    return true;
   }
   return false;
  }

  return false;
 }

function migrate_exec_statement(PDO $pdo, string $stmt): string
 {
  try {
   $pdo->exec($stmt);
   return '';
  } catch (PDOException $e) {
   if (migrate_je_create_table_if_not_exists($stmt, $tabulka)) {
    try {
     $pdo->query('SELECT 1 FROM `' . $tabulka . '` LIMIT 1');
    } catch (PDOException $eSelect) {
     throw $e;
    }
    return "  (CREATE TABLE $tabulka přeskočeno — tenhle účet nemá CREATE, ale tabulka už existuje a je použitelná.)\n";
   }
   if (migrate_alter_uz_hotovo($pdo, $stmt, $popis)) {
    return "  (ALTER přeskočen — tenhle účet nemá ALTER, ale $popis.)\n";
   }
   throw $e;
  }
 }

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
 migrate_fail(500, 'Chybí config.php.');
}
$config = require $configPath;

$expectedToken = $config['migrate_token'] ?? '';
$providedToken = $_SERVER['HTTP_X_MIGRATE_TOKEN'] ?? $_GET['token'] ?? '';

// config.php vzniká z getenv() v deploy.yml — chybějící/prázdný GitHub
// Secret se zapíše jako bool false, ne jako "". hash_equals() se
// striktními typy na boolu spadne s TypeError (neošetřená = prázdné
// tělo + 500, bez vysvětlení) — is_string() to napřed odchytí a vrátí
// čitelnou chybu místo pádu.
if (
 !is_string($expectedToken) || $expectedToken === ''
 || !is_string($providedToken) || $providedToken === ''
 || !hash_equals($expectedToken, $providedToken)
 ) {
 $reason = !is_string($expectedToken) || $expectedToken === ''
  ? ' (config.php nemá migrate_token — zkontroluj GitHub Secret MIGRATE_TOKEN)'
  : '';
 migrate_fail(403, 'Neplatný nebo chybějící token.' . $reason);
}

// Stejný důvod jako u tokenu výš: chybějící/prázdný GitHub Secret se
// do config.php zapíše jako bool false. `new PDO(...)` má od PHP 8.1
// typované parametry (?string) a se strict_types=1 na bool spadne
// TypeErrorem — TypeError ale NEDĚDÍ z PDOException, takže by ho
// catch níž vůbec nechytil a skončilo by to jako tichá prázdná 500.
// Tohle to odchytí napřed a řekne rovnou, který secret chybí.
foreach (['db_host', 'db_name', 'db_user', 'db_pass'] as $klic) {
 $hodnota = $config[$klic] ?? null;
 if (!is_string($hodnota) || $hodnota === '') {
  migrate_fail(
   500,
   "config.php nemá platnou hodnotu pro '$klic' (je to " . gettype($hodnota) . ") "
   . '— zkontroluj GitHub Secret ' . strtoupper($klic) . '.'
   );
 }
}

// Zakázané vzory — soubor obsahující cokoliv z tohohle se vůbec
// nespustí, ani jeho neškodná část před zakázaným příkazem.
$zakazanyVzor = '/\b(DROP\s+TABLE|DROP\s+COLUMN|DROP\s+DATABASE|TRUNCATE)\b/i';

try {
 $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
 $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
} catch (PDOException $e) {
 migrate_fail(500, 'Připojení k DB selhalo: ' . $e->getMessage());
}

// Omezený web účet (DML-only, viz CLAUDE.md) nemá CREATE. MySQL ale
// vyžaduje CREATE oprávnění na "CREATE TABLE IF NOT EXISTS" i když
// tabulka už existuje — IF NOT EXISTS potlačí jen chybu "already
// exists", ne kontrolu oprávnění. Tenhle příkaz proto pod web účtem
// spadne pokaždé (1142 CREATE command denied), i když je migrace_log
// dávno založená (ručně, admin účtem). Zachytíme to a ověříme přes
// SELECT, jestli je tabulka použitelná — pokud ano, jede se dál.
try {
 $pdo->exec(
  'CREATE TABLE IF NOT EXISTS migrace_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  soubor VARCHAR(255) NOT NULL UNIQUE,
  spusteno_v TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
  );
} catch (PDOException $e) {
 try {
  $pdo->query('SELECT 1 FROM migrace_log LIMIT 1');
 } catch (PDOException $eSelect) {
  migrate_fail(
   500,
   'CREATE TABLE migrace_log selhalo (' . $e->getMessage() . ') a tabulka '
   . 'zároveň není čitelná ani přes SELECT (' . $eSelect->getMessage() . ') — '
   . 'založ migrace_log ručně (admin DB účtem), tenhle web účet nemá CREATE.'
   );
 }
 // Tabulka existuje a je čitelná/zapisovatelná (DML), jen ji tenhle
// účet nesmí zakládat — v pořádku, jede se dál.
}

echo 'PHP ' . PHP_VERSION . "\n";

$applied = array_flip($pdo->query('SELECT soubor FROM migrace_log')->fetchAll(PDO::FETCH_COLUMN));

$dir = __DIR__ . '/../database/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files);

if (!$files) {
 echo "Žádné migrace ve složce database/migrations/.\n";
 exit;
}

$ranAny = false;
foreach ($files as $path) {
 $filename = basename($path);
 if (isset($applied[$filename])) {
  echo "Přeskakuji $filename (už aplikováno).\n";
  continue;
 }

$sql = file_get_contents($path);
 if ($sql === false) {
  migrate_fail(500, "Nejde přečíst $filename.");
 }

if (preg_match($zakazanyVzor, $sql, $m)) {
 migrate_fail(500, "Migrace $filename obsahuje zakázaný příkaz ({$m[1]}) — DROP/TRUNCATE se sem nikdy nepíší bez výslovného schválení v konverzaci. Zastaveno, tenhle soubor se vůbec nespustil.");
}

echo "Spouštím $filename ...\n";
 $pdo->beginTransaction();
 try {
  foreach (migrate_split_sql($sql) as $stmt) {
   $note = migrate_exec_statement($pdo, $stmt);
   if ($note !== '') echo $note;
  }
  $ins = $pdo->prepare('INSERT INTO migrace_log (soubor) VALUES (?)');
  $ins->execute([$filename]);
  // Stejný důvod jako u rollBack() níž: pokud soubor obsahoval
  // CREATE/ALTER, MySQL transakci už dávno sám implicitně
  // commitnul (a INSERT výš proto taky běžel v autocommitu) —
  // volat commit() znovu by zbytečně spadlo na "no active
  // transaction", i když je všechno v pořádku uložené.
  if ($pdo->inTransaction()) {
   $pdo->commit();
  }
  echo "Hotovo: $filename\n";
  $ranAny = true;
 } catch (PDOException $e) {
  // Po CREATE/ALTER (implicitní commit v MySQL) už PDO často
 // hlásí, že žádná transakce neběží — rollBack() by na to spadl
 // vlastní výjimkou. Volat ho jen když PDO ještě transakci vidí.
 if ($pdo->inTransaction()) {
  $pdo->rollBack();
 }
  migrate_fail(
   500,
   "Chyba v $filename: " . $e->getMessage() . "\n"
   . "Zastaveno, další soubory v téhle dávce se nespustily. "
   . "POZOR: pokud soubor obsahoval víc CREATE/ALTER příkazů, "
   . "ty už mohly proběhnout natrvalo i přes rollback — MySQL "
   . "DDL se transakcí vzít zpět nedá, viz komentář nahoře."
   );
 }
}

if (!$ranAny) {
 echo "Všechny migrace už byly aplikované dřív, nic k dělání.\n";
}
