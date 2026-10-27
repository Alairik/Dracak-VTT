<?php
declare(strict_types=1);

// Záloha databáze z administrace (admin_zaloha.php). Důvod: v ostré hře jsou v databázi postavy, mapy, tokeny a průběh hry a
// jde o jediná data, která se z repa obnovit nedají. Záloha nesmí záviset na tom, co hosting nabízí, a nesmí chtít víc než
// DML účet webu: stačí SELECT a SHOW CREATE TABLE.
//
// Soubor je obyčejný SQL dump (struktura + data všech tabulek), vkládá se do PRÁZDNÉ databáze (phpMyAdmin → Import, admin účet).
// Záměrně v něm NENÍ DROP TABLE: omylem pustěný na ostrou databázi nic nesmaže (CREATE TABLE IF NOT EXISTS + INSERT na
// existující tabulky by skončil chybou duplicitního klíče). Obnova do rozbité databáze = nejdřív ji vyprázdnit (viz docs/zalohy.md).

const DRACAK_ZALOHA_RADKU_V_DOTAZU = 200;     // tolik řádků najednou v jednom INSERTu
const DRACAK_ZALOHA_DAVKA = 1000;             // tolik řádků najednou se čte z tabulky
const DRACAK_ZALOHA_DAVKA_S_BLOBEM = 25;      // tabulka s velkými binárními daty (bitmapa mlhy) se čte po malých dávkách kvůli paměti

// Tabulky (ne pohledy) aktuální databáze, podle abecedy.
function dracak_zaloha_tabulky(PDO $pdo): array
{
    $tabulky = [];
    foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as $r) {
        $tabulky[] = (string)$r[0];
    }
    sort($tabulky);
    return $tabulky;
}

function dracak_zaloha_nazev(string $tabulka): string
{
    return '`' . str_replace('`', '``', $tabulka) . '`';
}

// Sloupce, které je potřeba zapsat jako hexadecimální literál (BLOB / BINARY), jinak by dump poškodil bajty.
function dracak_zaloha_binarni_sloupce(PDO $pdo, string $tabulka): array
{
    $q = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND data_type IN ("blob","tinyblob","mediumblob","longblob","binary","varbinary")'
    );
    $q->execute([$tabulka]);
    return array_map('strval', $q->fetchAll(PDO::FETCH_COLUMN));
}

// Primární klíč tabulky pro ORDER BY (stránkování bez něj by při souběžném zápisu mohlo řádek vynechat nebo zdvojit).
function dracak_zaloha_razeni(PDO $pdo, string $tabulka): string
{
    $q = $pdo->prepare(
        'SELECT column_name FROM information_schema.key_column_usage
         WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = "PRIMARY" ORDER BY ordinal_position'
    );
    $q->execute([$tabulka]);
    $sloupce = $q->fetchAll(PDO::FETCH_COLUMN);
    return $sloupce ? ' ORDER BY ' . implode(', ', array_map(static fn($c): string => dracak_zaloha_nazev((string)$c), $sloupce)) : '';
}

// Pošle dump po částech do $zapis(string). Vrací ['tabulek' => int, 'radku' => int, 'radku_podle_tabulek' => [tabulka => počet]].
function dracak_zaloha_proud(PDO $pdo, callable $zapis): array
{
    $vysledek = ['tabulek' => 0, 'radku' => 0, 'radku_podle_tabulek' => []];
    $zapis("-- Dračák VTT — záloha databáze\n-- Vytvořeno: " . date('c') . "\n"
        . "-- Vkládá se do PRÁZDNÉ databáze (phpMyAdmin → Import, admin účet). Soubor obsahuje i hashe hesel — nikomu ho neposílej.\n\n"
        . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

    foreach (dracak_zaloha_tabulky($pdo) as $tabulka) {
        $nazev = dracak_zaloha_nazev($tabulka);
        $definice = (string)($pdo->query('SHOW CREATE TABLE ' . $nazev)->fetch(PDO::FETCH_NUM)[1] ?? '');
        if ($definice === '') {
            throw new RuntimeException('Tabulku ' . $tabulka . ' se nepodařilo přečíst.');
        }
        $zapis("-- --------------------------------------------------\n-- Tabulka " . $tabulka . "\n\n"
            . preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $definice, 1) . ";\n\n");

        $binarni = array_flip(dracak_zaloha_binarni_sloupce($pdo, $tabulka));
        $razeni = dracak_zaloha_razeni($pdo, $tabulka);
        $velikostDavky = $binarni ? DRACAK_ZALOHA_DAVKA_S_BLOBEM : DRACAK_ZALOHA_DAVKA;
        $sloupce = null;
        $pocet = 0;
        $radky = [];
        $vypis = static function () use (&$radky, &$sloupce, $nazev, $zapis): void {
            if ($radky) {
                $zapis('INSERT INTO ' . $nazev . ' (' . $sloupce . ") VALUES\n" . implode(",\n", $radky) . ";\n");
                $radky = [];
            }
        };
        for ($od = 0; ; $od += $velikostDavky) {
            $davka = $pdo->query('SELECT * FROM ' . $nazev . $razeni . ' LIMIT ' . $velikostDavky . ' OFFSET ' . $od)->fetchAll(PDO::FETCH_ASSOC);
            if (!$davka) {
                break;
            }
            foreach ($davka as $r) {
                if ($sloupce === null) {
                    $sloupce = implode(', ', array_map('dracak_zaloha_nazev', array_keys($r)));
                }
                $hodnoty = [];
                foreach ($r as $sloupec => $hodnota) {
                    if ($hodnota === null) {
                        $hodnoty[] = 'NULL';
                    } elseif (isset($binarni[$sloupec])) {
                        $hodnoty[] = $hodnota === '' ? "''" : '0x' . bin2hex((string)$hodnota);
                    } else {
                        $hodnoty[] = $pdo->quote((string)$hodnota);
                    }
                }
                $radky[] = '(' . implode(', ', $hodnoty) . ')';
                $pocet++;
                if (count($radky) >= DRACAK_ZALOHA_RADKU_V_DOTAZU) {
                    $vypis();
                }
            }
            if (count($davka) < $velikostDavky) {
                break;
            }
        }
        $vypis();
        $zapis($pocet > 0 ? "\n" : '');
        $vysledek['tabulek']++;
        $vysledek['radku'] += $pocet;
        $vysledek['radku_podle_tabulek'][$tabulka] = $pocet;
    }
    $zapis("SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n-- Konec zálohy: " . $vysledek['tabulek'] . ' tabulek, ' . $vysledek['radku'] . " řádků.\n");
    return $vysledek;
}
