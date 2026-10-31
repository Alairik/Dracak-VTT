<?php
declare(strict_types=1);
// Kontrola zavřených cest (includes/nasazeni.php): aplikace v podsložce domény a doména, která vydává cizí obsah.
// Spustí si vlastní vestavěný server s dočasným kořenem: rodič (cizí robots.txt) + podsložka vtt = repo.
require dirname(__DIR__, 2) . '/includes/nasazeni.php';
$chyb = 0;
function over(string $nazev, bool $podminka, string $vic = ''): void
{
    global $chyb;
    echo ($podminka ? 'OK    ' : 'CHYBA ') . $nazev . ($vic !== '' ? ': ' . $vic : '') . "\n";
    if (!$podminka) {
        $chyb++;
    }
}
$koren = sys_get_temp_dir() . '/dracak_nasazeni_unit_' . getmypid();
@mkdir($koren);
symlink(dirname(__DIR__, 2), $koren . '/vtt');
file_put_contents($koren . '/robots.txt', "User-agent: *\nAllow: /\n");
$port = 18000 + getmypid() % 1000;
$proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $koren], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $roury);
usleep(600000);
try {
    $v = dracak_nasazeni_zavrene_cesty("127.0.0.1:$port", false, '127.0.0.1', '');
    over('kořen s cizím robots.txt: hlášena jiná složka', is_string($v['chyba']) && str_contains($v['chyba'], 'jiný obsah') && $v['cesty'] === [], (string)$v['chyba']);

    $v = dracak_nasazeni_zavrene_cesty("127.0.0.1:$port", false, '127.0.0.1', '/vtt');
    over('podsložka /vtt: robots.txt sedí, cesty se zkoušejí', $v['chyba'] === null && count($v['cesty']) >= 6 && str_ends_with($v['zakladna'], '/vtt'), (string)$v['zakladna']);

    $v = dracak_nasazeni_zavrene_cesty("127.0.0.1:$port", false, '127.0.0.1', '/vtt/../../etc');
    over('podivná základní cesta se zahodí (adresa se nesestaví mimo kořen)', !str_contains($v['zakladna'], '..'), (string)$v['zakladna']);

    $v = dracak_nasazeni_zavrene_cesty("127.0.0.1:$port", false, '8.8.8.8', '/vtt');
    over('IP adresa z cizí adresy se dál odmítne (SSRF)', is_string($v['chyba']) && $v['cesty'] === []);
} finally {
    if (is_resource($proc)) {
        proc_terminate($proc);
    }
    @unlink($koren . '/vtt');
    @unlink($koren . '/robots.txt');
    @rmdir($koren);
}
echo $chyb ? "\nSELHALO $chyb\n" : "\nVŠE OK\n";
exit($chyb ? 1 : 0);
