<?php
declare(strict_types=1);
// Jednotkový test includes/log.php (zápis, čtení, slučování, ořez, maskování, obslužné funkce).
require dirname(__DIR__, 2) . '/includes/log.php';
$ok = true;
function over(string $nazev, bool $podminka, string $vic = ''): void {
    global $ok;
    echo ($podminka ? 'OK    ' : 'CHYBA ') . $nazev . ($vic !== '' ? ': ' . $vic : '') . "\n";
    if (!$podminka) { $ok = false; }
}
$cesta = dracak_log_cesta();
@unlink($cesta);

over('čtení bez souboru', dracak_log_nacti() === ['existuje' => false, 'velikost' => 0, 'zaznamy' => [], 'poskozenych' => 0]);

dracak_log_zapis('vyjimka', "Řádek 1\nřádek 2 s \x07 řídicím znakem", ['soubor' => '/home/user/Dracak-VTT/hra/api/x.php', 'radek' => 12, 'stopa' => ['a.php:1 f', 'b.php:2 g']]);
over('složka a .htaccess vznikly', is_file(dirname($cesta) . '/.htaccess'));
$text = file_get_contents($cesta);
over('záznam je jeden řádek', substr_count($text, "\n") === 1, json_encode($text, JSON_UNESCAPED_UNICODE));
$n = dracak_log_nacti();
over('přečten 1 záznam', count($n['zaznamy']) === 1 && $n['zaznamy'][0]['soubor'] === 'hra/api/x.php' && $n['zaznamy'][0]['radek'] === 12);
over('zpráva bez řídicích znaků', strpos($n['zaznamy'][0]['zprava'], "\x07") === false);

// dedup v rámci požadavku
dracak_log_zapis('vyjimka', 'stejná', ['soubor' => 'a', 'radek' => 1]);
dracak_log_zapis('vyjimka', 'stejná', ['soubor' => 'a', 'radek' => 1]);
over('stejný záznam v jednom požadavku jen jednou', substr_count(file_get_contents($cesta), 'stejná') === 1);

// slučování při čtení: ručně dopsané řádky
@unlink($cesta);
$radky = [];
foreach ([1, 2, 3] as $i) { $radky[] = json_encode(['t' => '2026-10-28T10:0' . $i . ':00+01:00', 'druh' => 'db', 'zprava' => 'Spadlo', 'soubor' => 'a.php', 'radek' => 5], JSON_UNESCAPED_UNICODE); }
$radky[] = json_encode(['t' => '2026-10-28T10:05:00+01:00', 'druh' => 'prohlizec', 'zprava' => 'x', 'misto' => 'mapa.js:1:1'], JSON_UNESCAPED_UNICODE);
$radky[] = 'rozbitý řádek {';
file_put_contents($cesta, implode("\n", $radky) . "\n");
$n = dracak_log_nacti();
over('sloučení opakování + počet poškozených', count($n['zaznamy']) === 2 && $n['zaznamy'][1]['pocet'] === 3 && $n['poskozenych'] === 1, json_encode($n['zaznamy'], JSON_UNESCAPED_UNICODE));
over('nejnovější první; první výskyt', $n['zaznamy'][0]['druh'] === 'prohlizec' && substr($n['zaznamy'][1]['prvni'], 11, 5) === '10:01' && substr($n['zaznamy'][1]['t'], 11, 5) === '10:03');
over('filtr podle druhu', count(dracak_log_nacti('db')['zaznamy']) === 1);

// maskování tajemství
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/hra/svet.php?id=3&pozvanka=abc123&token_x=zzz&heslo=tajne';
over('adresa bez tajných hodnot', dracak_log_adresa() === 'GET /hra/svet.php?id=3&pozvanka=***&token_x=***&heslo=***', dracak_log_adresa());
over('stránka z prohlížeče bez tajných hodnot', dracak_log_skryj_tajne('/hra/svet.php?kod=ABC&id=1') === '/hra/svet.php?kod=***&id=1');

// ořez přerostlého logu
@unlink($cesta);
$velky = str_repeat(json_encode(['t' => '2026-10-28T10:00:00+01:00', 'druh' => 'db', 'zprava' => str_repeat('x', 500)], JSON_UNESCAPED_UNICODE) . "\n", 2200);
file_put_contents($cesta, $velky);
over('přerostlý log je přes limit', filesize($cesta) > DRACAK_LOG_MAX_BAJTU, (string)filesize($cesta));
dracak_log_zapis('db', 'po ořezu');
clearstatcache();
over('ořezán na ~půl MB', filesize($cesta) <= DRACAK_LOG_ZUSTANE_BAJTU + 1024 && filesize($cesta) > 400000, (string)filesize($cesta));
$n = dracak_log_nacti();
over('po ořezu zůstal nový záznam a všechny řádky jsou celé', $n['zaznamy'][0]['zprava'] === 'po ořezu' && $n['poskozenych'] === 0);

// čtení jen z konce
@unlink($cesta);
file_put_contents($cesta, str_repeat(json_encode(['t' => 'x', 'druh' => 'db', 'zprava' => str_repeat('y', 200)]) . "\n", 6000));
$n = dracak_log_nacti();
over('čtení z konce velkého souboru nerozbije záznamy', $n['poskozenych'] === 0 && $n['velikost'] > DRACAK_LOG_CTENI_BAJTU);

over("smazání", dracak_log_smaz() && (clearstatcache() || true) && filesize($cesta) === 0 && dracak_log_nacti()['zaznamy'] === []);

// výjimka s řetězcem znaků mimo UTF-8
dracak_log_zapis('vyjimka', "špatné \xFF bajty");
over('neplatné UTF-8 se nahradí, záznam vznikne', count(dracak_log_nacti()['zaznamy']) === 1);
@unlink($cesta);
// limit na požadavek
@unlink($cesta);
for ($i = 0; $i < 40; $i++) { dracak_log_zapis('varovani', 'různé ' . $i); }
over('limit na požadavek (celkem 20 zápisů)', substr_count(file_get_contents($cesta), "\n") === 20 - 4, (string)substr_count(file_get_contents($cesta), "\n"));

@unlink($cesta);
echo $ok ? "\nVŠE OK\n" : "\nSELHALO\n";
exit($ok ? 0 : 1);
