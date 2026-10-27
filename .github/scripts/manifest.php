<?php
declare(strict_types=1);

// Vytvoří deploy-manifest.json: seznam souborů, které deploy nahraje na FTP, s kontrolním součtem SHA-256.
// Spouští se v deploy.yml před FTP Deploy; manifest se nahraje spolu se soubory a stránka admin_nasazeni.php
// podle něj na serveru pozná, že nějaký soubor chybí nebo je jiný (FTP se na tomhle hostingu už jednou tvářilo
// úspěšně, ale soubor na serveru nebyl) a že na serveru leží něco navíc.
//
// Co se nahrává, je dané seznamem `exclude` u kroku FTP Deploy v deploy.yml — tenhle skript ho z deploy.yml
// čte, takže se nikdy nerozejdou. Do manifestu jdou jen soubory z gitu (config.php ani nahraná data tam nejsou).
// Spuštění: php .github/scripts/manifest.php  (z kořene repa, volitelně s argumentem = výstupní soubor)

$koren = getcwd();
$vystup = $argv[1] ?? $koren . '/deploy-manifest.json';

// --- vzory vyloučení z deploy.yml ---
$yml = (string)file_get_contents($koren . '/.github/workflows/deploy.yml');
if (!preg_match('/^\s*exclude:\s*\|\s*\n((?:[ \t]+\S.*\n?)+)/m', $yml, $m)) {
    fwrite(STDERR, "V deploy.yml nenalezen blok exclude.\n");
    exit(1);
}
$vzory = array_values(array_filter(array_map('trim', explode("\n", $m[1])), static fn(string $r): bool => $r !== ''));

// Glob jako v minimatch (s dot: true): ** přes složky, * a ? uvnitř jednoho názvu.
function glob_na_regex(string $vzor): string
{
    $out = '';
    $delka = strlen($vzor);
    for ($i = 0; $i < $delka; $i++) {
        $ch = $vzor[$i];
        if ($ch === '*' && ($vzor[$i + 1] ?? '') === '*') {
            $dalsi = $vzor[$i + 2] ?? '';
            if ($dalsi === '/') {
                $out .= '(?:.*/)?';
                $i += 2;
            } else {
                $out .= '.*';
                $i += 1;
            }
        } elseif ($ch === '*') {
            $out .= '[^/]*';
        } elseif ($ch === '?') {
            $out .= '[^/]';
        } else {
            $out .= preg_quote($ch, '#');
        }
    }
    return '#^' . $out . '$#u';
}
$regexy = array_map('glob_na_regex', $vzory);
$vyloucen = static function (string $cesta) use ($regexy): bool {
    foreach ($regexy as $re) {
        if (preg_match($re, $cesta)) {
            return true;
        }
    }
    return false;
};

// --- soubory z gitu ---
$vypis = shell_exec('git ls-files -z');
if (!is_string($vypis) || $vypis === '') {
    fwrite(STDERR, "git ls-files nic nevrátil.\n");
    exit(1);
}
$soubory = [];
foreach (explode("\0", $vypis) as $cesta) {
    if ($cesta === '' || $vyloucen($cesta) || !is_file($koren . '/' . $cesta)) {
        continue;
    }
    $soubory[$cesta] = hash_file('sha256', $koren . '/' . $cesta);
}
ksort($soubory, SORT_STRING);

$commit = trim((string)shell_exec('git rev-parse HEAD'));
$manifest = [
    'vygenerovano' => gmdate('c'),
    'commit' => $commit,
    'soubory' => $soubory,
];
file_put_contents($vystup, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
fwrite(STDOUT, 'Manifest: ' . count($soubory) . ' souborů, commit ' . substr($commit, 0, 8) . ', vyloučeno vzorů: ' . count($vzory) . "\n");
