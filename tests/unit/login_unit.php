<?php
declare(strict_types=1);
// Omezení pokusů o přihlášení (includes/auth.php): limity, okno, nezamykání cizích účtů, vymazání po úspěchu.
require dirname(__DIR__, 2) . '/includes/auth.php';
$chyb = 0;
ob_start();   // přihlášení startuje relaci, výpis testu před tím by poslal hlavičky
function over(string $nazev, bool $podminka, string $vic = ''): void
{
    global $chyb;
    echo ($podminka ? 'OK    ' : 'CHYBA ') . $nazev . ($vic !== '' ? ': ' . $vic : '') . "\n";
    if (!$podminka) {
        $chyb++;
    }
}
$slozka = dirname(__DIR__, 2) . '/logs/login';
$uklid = static function () use ($slozka): void { foreach (glob($slozka . '/*.json') ?: [] as $f) { @unlink($f); } };
$uklid();
$t = 1_800_000_000;

$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
over('čistý stav: smí zkusit', dracak_login_cekani('a@b.cz', $t) === 0);
for ($i = 0; $i < DRACAK_LOGIN_LIMIT_EMAIL - 1; $i++) { dracak_login_selhani('a@b.cz', $t + $i); }
over('těsně pod limitem: pořád smí', dracak_login_cekani('a@b.cz', $t + 10) === 0);
dracak_login_selhani('A@B.cz', $t + 6);   // velikost písmen se nerozlišuje
$c = dracak_login_cekani('a@b.cz', $t + 10);
over('po dosažení limitu se čeká do vypršení nejstaršího selhání', $c === DRACAK_LOGIN_OKNO_S - 10, (string)$c);
over('jiný e-mail ze stejné adresy smí', dracak_login_cekani('jiny@b.cz', $t + 10) === 0);
$_SERVER['REMOTE_ADDR'] = '203.0.113.99';
over('stejný e-mail z jiné adresy smí (cizí člověk nezamkne cizí účet)', dracak_login_cekani('a@b.cz', $t + 10) === 0);
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
over('po uplynutí okna se odblokuje', dracak_login_cekani('a@b.cz', $t + DRACAK_LOGIN_OKNO_S + 6) === 0);
dracak_login_vymaz('a@b.cz');
over('po úspěšném přihlášení (vymazání) smí zase', dracak_login_cekani('a@b.cz', $t + 10) === 0);
// limit na adresu celkem
$uklid();
for ($i = 0; $i < DRACAK_LOGIN_LIMIT_IP; $i++) { dracak_login_selhani('u' . $i . '@b.cz', $t + $i); }
over('limit na adresu celkem: zablokuje i nový e-mail', dracak_login_cekani('uplne.novy@b.cz', $t + 40) > 0);
$_SERVER['REMOTE_ADDR'] = '203.0.113.6';
over('jiná adresa není zablokovaná', dracak_login_cekani('uplne.novy@b.cz', $t + 40) === 0);
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
// poškozený soubor nesmí shodit přihlášení
$uklid();
file_put_contents(dracak_login_soubor('203.0.113.5|poskozeny@b.cz'), '{nesmysl');
over('poškozené počítadlo = bez omezení', dracak_login_cekani('poskozeny@b.cz', $t) === 0);
// skutečné přihlášení proti DB: špatné heslo ×6 a pak i správné je odmítnuto, jiná adresa projde
$pdo = dracak_db();
$uklid();
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
$ok = 0;
for ($i = 0; $i < DRACAK_LOGIN_LIMIT_EMAIL; $i++) { $ok += dracak_login('test@local', 'spatne-heslo-' . $i) ? 1 : 0; }
over('špatná hesla neprojdou', $ok === 0);
over('po limitu je zablokované i správné heslo', dracak_login('test@local', 'test1234') === false && dracak_login_cekani('test@local') > 0);
$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
over('jiná adresa se správným heslem projde', dracak_login('test@local', 'test1234') === true);
$uklid();
echo $chyb ? "\nSELHALO $chyb\n" : "\nVŠE OK\n";
exit($chyb ? 1 : 0);
