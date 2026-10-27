<?php
declare(strict_types=1);

// Kontrola nasazení — admin_nasazeni.php. Nahrazuje kroky deploye, které volaly web z GitHubu (smoke test a kontrola
// zabezpečení): hosting je od 22. 10. 2026 odmítá, a tak se kontroluje zevnitř serveru.
//
// 1) Soubory: deploy před nahráním vyrobí deploy-manifest.json (.github/scripts/manifest.php) — seznam nahrávaných souborů
//    s SHA-256. Tady se porovná s tím, co na serveru opravdu leží: chybějící soubor = FTP ho nenahrálo (na tomhle hostingu
//    se to už stalo), jiný součet = nahrálo se jen částečně nebo ho někdo změnil, soubor navíc = zbytek po starém nasazení
//    nebo cizí kód (po „php2shell“ nejdůležitější).
// 2) Zavřené cesty a hlavičky: server si zkusí vyžádat vlastní adresy, které musí být zvenku zavřené (config.php,
//    složky s daty…), a zkontroluje bezpečnostní hlavičky. Když hosting nepustí server k vlastní doméně, napíše „nelze ověřit“.

const DRACAK_NASAZENI_MAX_VYPIS = 200;

function dracak_nasazeni_koren(): string
{
    return dirname(__DIR__);
}

// Porovná manifest s diskem. Vrací
// ['manifest' => null|['commit','vygenerovano','pocet'], 'chyba' => ?string,
//  'ok' => int, 'chybi' => [cesta], 'jine' => [cesta], 'navic' => [cesta], 'uploads_spustitelne' => [cesta]].
function dracak_nasazeni_kontrola(): array
{
    $koren = dracak_nasazeni_koren();
    $vysledek = ['manifest' => null, 'chyba' => null, 'ok' => 0, 'chybi' => [], 'jine' => [], 'navic' => [], 'uploads_spustitelne' => []];

    $cesta = $koren . '/deploy-manifest.json';
    if (!is_file($cesta)) {
        $vysledek['chyba'] = 'Na serveru není deploy-manifest.json — nahraje ho až další nasazení po téhle změně.';
        return $vysledek;
    }
    $manifest = json_decode((string)file_get_contents($cesta), true);
    if (!is_array($manifest) || !isset($manifest['soubory']) || !is_array($manifest['soubory'])) {
        $vysledek['chyba'] = 'deploy-manifest.json je poškozený (nejde přečíst).';
        return $vysledek;
    }
    $soubory = $manifest['soubory'];
    $vysledek['manifest'] = [
        'commit' => (string)($manifest['commit'] ?? ''),
        'vygenerovano' => (string)($manifest['vygenerovano'] ?? ''),
        'pocet' => count($soubory),
    ];

    foreach ($soubory as $rel => $soucet) {
        $rel = (string)$rel;
        $plna = $koren . '/' . $rel;
        if (!is_file($plna)) {
            $vysledek['chybi'][] = $rel;
        } elseif (!hash_equals((string)$soucet, (string)hash_file('sha256', $plna))) {
            $vysledek['jine'][] = $rel;
        } else {
            $vysledek['ok']++;
        }
    }

    // Co leží na serveru navíc. Nahraná data (uploads/), konfigurace, evidence FTP a samotný manifest se přeskakují;
    // v uploads/ se hledají jen spustitelné soubory (tam smí být jen obrázky).
    $preskocit = ['config.php', 'deploy-manifest.json', '.ftp-deploy-sync-state.json'];
    $spustitelne = '/\.(php\d?|phtml|phar|pht|cgi|pl|py|sh|htaccess)$/i';
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($koren, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $soubor) {
        if (!$soubor->isFile()) {
            continue;
        }
        $rel = ltrim(substr($soubor->getPathname(), strlen($koren)), '/');
        if (str_starts_with($rel, '.git/') || str_starts_with($rel, 'node_modules/') || in_array($rel, $preskocit, true)) {
            continue;
        }
        if (isset($soubory[$rel])) {
            continue;
        }
        if (str_starts_with($rel, 'uploads/')) {
            if (preg_match($spustitelne, $rel)) {
                $vysledek['uploads_spustitelne'][] = $rel;
            }
            continue;
        }
        $vysledek['navic'][] = $rel;
    }
    sort($vysledek['chybi']);
    sort($vysledek['jine']);
    sort($vysledek['navic']);
    sort($vysledek['uploads_spustitelne']);
    return $vysledek;
}

// Jeden požadavek na vlastní adresu: ['kod' => ?int, 'hlavicky' => string, 'chyba' => ?string]. Bez přesměrování a s krátkým limitem.
function dracak_nasazeni_pozadavek(string $url): array
{
    $vystup = ['kod' => null, 'hlavicky' => '', 'chyba' => null];
    if (!function_exists('curl_init')) {
        $vystup['chyba'] = 'na serveru chybí rozšíření curl';
        return $vystup;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'dracak-vtt-kontrola-nasazeni',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $odpoved = curl_exec($ch);
    if ($odpoved === false) {
        $vystup['chyba'] = curl_error($ch) ?: 'požadavek se nepovedl';
    } else {
        $vystup['kod'] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $vystup['hlavicky'] = substr((string)$odpoved, 0, (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    }
    curl_close($ch);
    return $vystup;
}

// Zavřené cesty a bezpečnostní hlavičky, ověřené požadavky ze serveru na vlastní doménu.
// Vrací ['zakladna' => string, 'cesty' => [[cesta, kod, ok, poznamka]], 'hlavicky' => [[nazev, ok, poznamka]], 'chyba' => ?string].
function dracak_nasazeni_zavrene_cesty(string $hostHlavicka, bool $https): array
{
    $host = strtolower(trim(explode(',', $hostHlavicka)[0]));
    $vysledek = ['zakladna' => '', 'cesty' => [], 'hlavicky' => [], 'chyba' => null];
    // Jen běžné jméno hostitele (písmena, číslice, tečky, pomlčky, případně port) — nic, co by požadavek odvedlo jinam.
    if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?$/', $host)) {
        $vysledek['chyba'] = 'Nepodařilo se zjistit adresu webu.';
        return $vysledek;
    }
    $zakladna = ($https ? 'https://' : 'http://') . $host;
    $vysledek['zakladna'] = $zakladna;

    $migrace = glob(dracak_nasazeni_koren() . '/database/migrations/*.sql') ?: [];
    sort($migrace);
    $cesty = ['config.php', '.ftp-deploy-sync-state.json', 'deploy-manifest.json', 'includes/auth.php', 'content/pravidla-pj.html', 'uploads/'];
    if ($migrace) {
        $cesty[] = 'database/migrations/' . basename((string)end($migrace));
    }

    $zkusilo = 0;
    foreach ($cesty as $cesta) {
        $r = dracak_nasazeni_pozadavek($zakladna . '/' . $cesta);
        if ($r['kod'] === null) {
            $vysledek['cesty'][] = [$cesta, null, null, $r['chyba']];
            continue;
        }
        $zkusilo++;
        $zavrene = in_array($r['kod'], [403, 404], true);
        $vysledek['cesty'][] = [$cesta, $r['kod'], $zavrene, $zavrene ? 'zavřeno' : 'DOSTUPNÉ ZVENKU (čekáno 403 nebo 404)'];
    }
    if ($zkusilo === 0) {
        $vysledek['chyba'] = 'Server se na vlastní doménu nedovolá (' . ($vysledek['cesty'][0][3] ?? 'bez odpovědi') . ') — tohle zevnitř ověřit nejde.';
        return $vysledek;
    }

    $r = dracak_nasazeni_pozadavek($zakladna . '/index.php');
    if ($r['kod'] !== null) {
        foreach (['x-frame-options', 'x-content-type-options', 'strict-transport-security'] as $h) {
            $ma = (bool)preg_match('/^' . preg_quote($h, '/') . ':/mi', $r['hlavicky']);
            $vysledek['hlavicky'][] = [$h, $ma, $ma ? 'je' : 'CHYBÍ (mod_headers / .htaccess se neuplatnil)'];
        }
        $prozrazuje = (bool)preg_match('/^x-powered-by:/mi', $r['hlavicky']);
        $vysledek['hlavicky'][] = ['x-powered-by', !$prozrazuje, $prozrazuje ? 'server prozrazuje verzi PHP' : 'není (dobře)'];
    }
    return $vysledek;
}
