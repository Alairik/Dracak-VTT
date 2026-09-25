<?php
declare(strict_types=1);

// Spouští se z GitHub Actions (deploy.yml, krok "Run pending DB migrations").
// Aplikuje na produkční DB jen soubory z database/migrations/*.sql, které
// ještě nejsou zapsané v tabulce schema_migrations — takže je bezpečné
// pouštět tenhle skript na každém deployi, ne jen jednou.
//
// database/drd-db-schema-v1.sql, drd-db-full-v1.sql a database/0002_*.sql
// SEM ZÁMĚRNĚ NEPATŘÍ — ty se importovaly ručně přes phpMyAdmin ještě před
// tím, než tenhle runner vznikl, a znovu by se spustit neměly.

$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

if (!$dbHost || !$dbName || !$dbUser) {
    fwrite(STDERR, "Chybí DB_HOST/DB_NAME/DB_USER v prostředí — zkontroluj GitHub Secrets.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli === false) {
    fwrite(STDERR, 'Připojení k DB selhalo: ' . mysqli_connect_error() . "\n");
    exit(1);
}
// Bez tohohle běží spojení na charsetu, co si server usmyslí (často
// latin1) — pak UPDATE/WHERE porovnávající českou diakritiku (třeba
// "WHERE nazev = 'Trpaslík'") tiše nenajde nic, žádná chyba, jen 0
// změněných řádků. Objevilo se to přesně takhle při testu migrace 0007.
if (!$mysqli->set_charset('utf8mb4')) {
    fwrite(STDERR, 'Nepodařilo se nastavit utf8mb4: ' . $mysqli->error . "\n");
    exit(1);
}

if (!$mysqli->query(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
)) {
    fwrite(STDERR, 'Nepodařilo se založit schema_migrations: ' . $mysqli->error . "\n");
    exit(1);
}

$dir = __DIR__ . '/../database/migrations';
$files = glob($dir . '/*.sql');
sort($files);

if (!$files) {
    echo "Žádné migrace ve složce database/migrations/.\n";
    exit(0);
}

$applied = [];
$result = $mysqli->query('SELECT filename FROM schema_migrations');
while ($row = $result->fetch_assoc()) {
    $applied[$row['filename']] = true;
}

$ranSomething = false;
foreach ($files as $path) {
    $filename = basename($path);
    if (isset($applied[$filename])) {
        echo "Přeskakuji $filename (už aplikováno).\n";
        continue;
    }

    echo "Spouštím $filename ...\n";
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Nejde přečíst $filename\n");
        exit(1);
    }

    if (!$mysqli->multi_query($sql)) {
        fwrite(STDERR, "Chyba v $filename: " . $mysqli->error . "\n");
        exit(1);
    }
    // Je potřeba projít všechny výsledky multi_query, jinak se chyba u
    // pozdějšího příkazu v souboru neprojeví a skript by ji tiše přešel.
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        if ($mysqli->errno) {
            fwrite(STDERR, "Chyba v $filename: " . $mysqli->error . "\n");
            exit(1);
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    $stmt = $mysqli->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
    $stmt->bind_param('s', $filename);
    $stmt->execute();
    $stmt->close();

    echo "Hotovo: $filename\n";
    $ranSomething = true;
}

if (!$ranSomething) {
    echo "Všechny migrace už byly aplikované dřív, nic k dělání.\n";
}

$mysqli->close();
