<?php
declare(strict_types=1);

// Chybový log aplikace (logs/aplikace.log, čte ho admin_log.php). Důvod: PHP chyby jinak zmizí v logu hostingu, ke kterému
// se admin nedostane, a chyby v JavaScriptu hráčů (nic se nestalo po kliknutí) nevidí nikdo. Do logu jdou neošetřené výjimky,
// chyby databáze, varování a fatální chyby PHP a chyby z prohlížeče (hra/api/klient_chyba.php). Každý záznam je jeden řádek JSON.
// Složka logs/ je zavřená .htaccess (stejně jako includes/); zápis nikdy nesmí shodit stránku, tak všechno chytá Throwable.

const DRACAK_LOG_MAX_BAJTU = 1048576;      // nad 1 MB se začátek odřízne
const DRACAK_LOG_ZUSTANE_BAJTU = 524288;   // po ořezu zůstane posledního půl MB
const DRACAK_LOG_NA_POZADAVEK = 20;        // jeden požadavek nezapíše víc záznamů (cyklus s varováním by log zaplavil)
const DRACAK_LOG_CTENI_BAJTU = 786432;     // admin_log.php čte z konce souboru nejvýš tolik
const DRACAK_LOG_DRUHY = [
    'vyjimka' => 'Neošetřená výjimka',
    'db' => 'Databáze',
    'varovani' => 'Varování PHP',
    'fatalni' => 'Fatální chyba PHP',
    'prohlizec' => 'Chyba v prohlížeči',
];

function dracak_log_cesta(): string
{
    return dirname(__DIR__) . '/logs/aplikace.log';
}

// Založí složku a v ní .htaccess, kdyby je deploy nenahrál. Vrací, jestli se do složky dá psát.
function dracak_log_priprav_slozku(): bool
{
    $slozka = dirname(dracak_log_cesta());
    if (!is_dir($slozka) && !@mkdir($slozka, 0750, true) && !is_dir($slozka)) {
        return false;
    }
    $zamek = $slozka . '/.htaccess';
    if (!is_file($zamek)) {
        @file_put_contents($zamek, "# Log aplikace nesmí jít stáhnout přes HTTP.\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    return is_writable($slozka);
}

function dracak_log_oriznout(string $text, int $delka): string
{
    $text = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $text);
    return mb_strlen($text) > $delka ? mb_substr($text, 0, $delka - 1) . '…' : $text;
}

// Adresa bez hodnot, které vypadají jako tajné (token, heslo, kód pozvánky).
function dracak_log_skryj_tajne(string $adresa): string
{
    return (string)preg_replace('/((?:token|heslo|password|pozvanka|kod|secret)[a-z_]*=)[^&\s]*/i', '$1***', $adresa);
}

function dracak_log_adresa(): string
{
    $metoda = (string)($_SERVER['REQUEST_METHOD'] ?? '');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri === '') {
        return PHP_SAPI === 'cli' ? 'cli' : '';
    }
    return trim($metoda . ' ' . dracak_log_oriznout(dracak_log_skryj_tajne($uri), 240));
}

function dracak_log_relativni_cesta(string $soubor): string
{
    $koren = dirname(__DIR__) . '/';
    return str_starts_with($soubor, $koren) ? substr($soubor, strlen($koren)) : basename($soubor);
}

// Zapíše záznam. $podrobnosti: soubor, radek, stopa (pole řádků), uzivatel; u chyb z prohlížeče stranka (adresa stránky),
// misto (skript:řádek:sloupec) a ua (prohlížeč).
function dracak_log_zapis(string $druh, string $zprava, array $podrobnosti = []): void
{
    static $pocet = 0;
    static $videno = [];
    try {
        $klic = $druh . '|' . $zprava . '|' . ($podrobnosti['soubor'] ?? '') . '|' . ($podrobnosti['radek'] ?? '') . '|' . ($podrobnosti['misto'] ?? '');
        if (isset($videno[$klic]) || $pocet >= DRACAK_LOG_NA_POZADAVEK || !dracak_log_priprav_slozku()) {
            return;
        }
        $videno[$klic] = true;
        $pocet++;

        $zaznam = ['t' => date('c'), 'druh' => $druh, 'zprava' => dracak_log_oriznout($zprava, 600)];
        if (isset($podrobnosti['soubor'])) {
            $zaznam['soubor'] = dracak_log_oriznout(dracak_log_relativni_cesta((string)$podrobnosti['soubor']), 160);
        }
        if (isset($podrobnosti['radek'])) {
            $zaznam['radek'] = (int)$podrobnosti['radek'];
        }
        if (!empty($podrobnosti['stopa']) && is_array($podrobnosti['stopa'])) {
            $zaznam['stopa'] = array_map(static fn($r): string => dracak_log_oriznout((string)$r, 200), array_slice(array_values($podrobnosti['stopa']), 0, 8));
        }
        foreach (['stranka' => 240, 'misto' => 240, 'ua' => 140] as $pole => $delka) {
            if (isset($podrobnosti[$pole]) && $podrobnosti[$pole] !== '') {
                $zaznam[$pole] = dracak_log_oriznout($pole === 'stranka' ? dracak_log_skryj_tajne((string)$podrobnosti[$pole]) : (string)$podrobnosti[$pole], $delka);
            }
        }
        $adresa = dracak_log_adresa();
        if ($adresa !== '') {
            $zaznam['adresa'] = $adresa;
        }
        $uzivatel = $podrobnosti['uzivatel'] ?? (session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['ucet_id'] ?? null) : null);
        if ($uzivatel !== null) {
            $zaznam['uzivatel'] = (int)$uzivatel;
        }

        $radek = json_encode($zaznam, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($radek === false) {
            return;
        }
        $cesta = dracak_log_cesta();
        if (@file_put_contents($cesta, $radek . "\n", FILE_APPEND | LOCK_EX) === false) {
            return;
        }
        clearstatcache(true, $cesta);
        if ((int)@filesize($cesta) > DRACAK_LOG_MAX_BAJTU) {
            dracak_log_oriznout_soubor($cesta);
        }
    } catch (Throwable $e) {
        // Log nesmí shodit stránku, kvůli které se zapisuje.
    }
}

// Odřízne začátek přerostlého logu, zůstane posledních DRACAK_LOG_ZUSTANE_BAJTU od celého řádku.
function dracak_log_oriznout_soubor(string $cesta): void
{
    $f = @fopen($cesta, 'c+');
    if (!$f) {
        return;
    }
    if (flock($f, LOCK_EX)) {
        $velikost = (int)(fstat($f)['size'] ?? 0);
        if ($velikost > DRACAK_LOG_MAX_BAJTU) {
            fseek($f, -DRACAK_LOG_ZUSTANE_BAJTU, SEEK_END);
            $konec = (string)stream_get_contents($f);
            $zalomeni = strpos($konec, "\n");
            $konec = $zalomeni === false ? '' : substr($konec, $zalomeni + 1);
            ftruncate($f, 0);
            rewind($f);
            fwrite($f, $konec);
        }
        flock($f, LOCK_UN);
    }
    fclose($f);
}

// Přečte konec logu. Vrací ['existuje' => bool, 'velikost' => int, 'zaznamy' => [záznam…] od nejnovějšího, 'poskozenych' => int].
// Stejné záznamy (druh, zpráva, místo) se sloučí: 'pocet' = kolikrát se opakoval, 'prvni' = čas prvního výskytu v přečtené části.
function dracak_log_nacti(?string $druh = null): array
{
    $vysledek = ['existuje' => false, 'velikost' => 0, 'zaznamy' => [], 'poskozenych' => 0];
    $cesta = dracak_log_cesta();
    clearstatcache(true, $cesta);
    if (!is_file($cesta)) {
        return $vysledek;
    }
    $vysledek['existuje'] = true;
    $vysledek['velikost'] = (int)filesize($cesta);
    $f = @fopen($cesta, 'rb');
    if (!$f) {
        return $vysledek;
    }
    $zacatek = max(0, $vysledek['velikost'] - DRACAK_LOG_CTENI_BAJTU);
    fseek($f, $zacatek);
    $text = (string)stream_get_contents($f);
    fclose($f);
    if ($zacatek > 0) {
        $zalomeni = strpos($text, "\n");
        $text = $zalomeni === false ? '' : substr($text, $zalomeni + 1);
    }

    $skupiny = [];
    foreach (array_reverse(explode("\n", $text)) as $radek) {
        if ($radek === '') {
            continue;
        }
        $z = json_decode($radek, true);
        if (!is_array($z) || !isset($z['t'], $z['druh'], $z['zprava'])) {
            $vysledek['poskozenych']++;
            continue;
        }
        if ($druh !== null && $z['druh'] !== $druh) {
            continue;
        }
        $klic = $z['druh'] . '|' . $z['zprava'] . '|' . ($z['soubor'] ?? '') . '|' . ($z['radek'] ?? '') . '|' . ($z['misto'] ?? '');
        if (isset($skupiny[$klic])) {
            $skupiny[$klic]['pocet']++;
            $skupiny[$klic]['prvni'] = $z['t'];
            continue;
        }
        $z['pocet'] = 1;
        $z['prvni'] = $z['t'];
        $skupiny[$klic] = $z;
    }
    $vysledek['zaznamy'] = array_values($skupiny);
    return $vysledek;
}

function dracak_log_smaz(): bool
{
    $cesta = dracak_log_cesta();
    return !is_file($cesta) || @file_put_contents($cesta, '', LOCK_EX) !== false;
}

function dracak_log_stopa(Throwable $e): array
{
    $stopa = [];
    foreach ($e->getTrace() as $krok) {
        if (isset($krok['file'])) {
            $stopa[] = dracak_log_relativni_cesta((string)$krok['file']) . ':' . ($krok['line'] ?? '?') . ' ' . (isset($krok['class']) ? $krok['class'] . '::' : '') . ($krok['function'] ?? '');
        }
    }
    return $stopa;
}

function dracak_log_vyjimka(Throwable $e): void
{
    dracak_log_zapis($e instanceof PDOException ? 'db' : 'vyjimka', get_class($e) . ': ' . $e->getMessage(), [
        'soubor' => $e->getFile(), 'radek' => $e->getLine(), 'stopa' => dracak_log_stopa($e),
    ]);
}

// Varování a fatální chyby PHP. Obsluha varování vrací false, takže PHP dál zapisuje do vlastního logu jako dřív.
function dracak_log_registruj(): void
{
    set_error_handler(static function (int $cislo, string $zprava, string $soubor, int $radek): bool {
        // Chyby potlačené operátorem @ nebo vypnuté v error_reporting se nezapisují.
        if ((error_reporting() & $cislo) && in_array($cislo, [E_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR, E_USER_ERROR], true)) {
            dracak_log_zapis('varovani', $zprava, ['soubor' => $soubor, 'radek' => $radek]);
        }
        return false;
    });
    register_shutdown_function(static function (): void {
        $chyba = error_get_last();
        if ($chyba && in_array($chyba['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            dracak_log_zapis('fatalni', $chyba['message'], ['soubor' => $chyba['file'], 'radek' => $chyba['line']]);
        }
    });
}
