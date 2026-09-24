<?php
declare(strict_types=1);

// Produkční hosting: nikdy nevypisovat chyby (cesty na serveru, SQL, ...)
// do prohlížeče. Reálná chyba jde do PHP error logu, uživatel vidí jen
// obecnou hlášku. db.php se includuje skoro na každém requestu (přes
// auth.php), takže tohle platí prakticky v celé appce.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function dracak_fail_safely(string $logMessage): void
{
    error_log('[dracak-vtt] ' . $logMessage);
    http_response_code(500);
    die('Nastala chyba na serveru. Zkus to prosím znovu později.');
}

// Zachytí i PDOException vyhozenou později při dotazech (PDO::ATTR_ERRMODE
// je nastavený na ERRMODE_EXCEPTION) — bez tohohle by neošetřená výjimka
// z libovolného SELECT/INSERT jinde v appce vypsala do prohlížeče dotaz
// i cestu k souboru. Jiné výjimky necháváme projít normálně.
set_exception_handler(function (Throwable $e): void {
    if ($e instanceof PDOException) {
        dracak_fail_safely('DB chyba: ' . $e->getMessage());
    }
    error_log('[dracak-vtt] Neošetřená výjimka: ' . $e->getMessage());
    http_response_code(500);
    die('Nastala chyba na serveru. Zkus to prosím znovu později.');
});

function dracak_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $configPath = __DIR__ . '/../config.php';
    if (!file_exists($configPath)) {
        dracak_fail_safely('Chybí config.php.');
    }
    $config = require $configPath;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        dracak_fail_safely('Připojení k DB selhalo: ' . $e->getMessage());
    }
    return $pdo;
}
