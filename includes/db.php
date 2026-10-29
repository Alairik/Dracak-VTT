<?php
declare(strict_types=1);

// Produkční hosting: nikdy nevypisovat chyby (cesty na serveru, SQL, ...)
// do prohlížeče. Reálná chyba jde do PHP error logu, uživatel vidí jen
// obecnou hlášku. db.php se includuje skoro na každém requestu (přes
// auth.php), takže tohle platí prakticky v celé appce.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header_remove('X-Powered-By');

// Čitelný log pro admina (admin_log.php) vedle PHP error logu hostingu: výjimky, chyby DB, varování a fatální chyby.
require_once __DIR__ . '/log.php';
dracak_log_registruj();

// Text z požadavku: skalár jako řetězec, cokoli jiného (pole, objekt z podvrženého JSON) jako prázdný řetězec. Holé (string) na poli
// zapíše varování „Array to string conversion“ a vrátí „Array“.
function dracak_text(mixed $v): string
{
    return is_scalar($v) ? (string)$v : '';
}

function dracak_fail_safely(string $logMessage): void
{
    error_log('[dracak-vtt] ' . $logMessage);
    dracak_log_zapis('db', $logMessage);
    http_response_code(500);
    die('Nastala chyba na serveru. Zkus to prosím znovu později.');
}

// Zachytí i PDOException vyhozenou později při dotazech (PDO::ATTR_ERRMODE
// je nastavený na ERRMODE_EXCEPTION) — bez tohohle by neošetřená výjimka
// z libovolného SELECT/INSERT jinde v appce vypsala do prohlížeče dotaz
// i cestu k souboru. Uživatel vidí jen obecnou hlášku, podrobnosti jdou do logů.
set_exception_handler(function (Throwable $e): void {
    dracak_log_vyjimka($e);
    error_log('[dracak-vtt] ' . ($e instanceof PDOException ? 'DB chyba' : 'Neošetřená výjimka') . ': ' . $e->getMessage()
        . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
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
