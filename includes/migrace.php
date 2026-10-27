<?php
declare(strict_types=1);

// Spouštění změn databáze (database/migrations/*.sql) z přihlášené administrace — admin_migrace.php.
//
// Dřív to dělal scripts/migrate.php, který po nasazení volal GitHub přes HTTP s tajným tokenem. Hosting ale
// od 22. 10. 2026 volání z GitHubu odmítá, a tak se změny pouští tlačítkem v administraci (rozhodnutí uživatele
// 2026-10-27, varianta A). Pravidla zůstala stejná jako u migrate.php:
//  - spouští se jen nové soubory, popořadě, každý jednou; co proběhlo, eviduje tabulka migrace_log;
//  - soubor se zakázaným příkazem (DROP, TRUNCATE, ALTER … DROP) se vůbec nespustí;
//  - DB účet webu (Wedos) má jen SELECT/INSERT/UPDATE/DELETE, takže CREATE a ALTER neprovede — ruční SQL admin
//    účtem v phpMyAdminu; tady se poznají a přeskočí jen tehdy, když už jsou provedené (sloupec / tabulka / index existuje);
//  - MySQL/MariaDB DDL není transakční: soubor s víc ALTER příkazy, který spadne uprostřed, nechá hotové příkazy
//    v databázi i po chybě (limit enginu).
// Tabulku migrace_log si web účet založit nemůže — musí být založená ručně (viz dracak_migrace_sql_log()).

// Složka s migracemi; jen názvy NNNN_popis.sql (do spouštění se nedostane nic jiného).
function dracak_migrace_slozka(): string
{
    return dirname(__DIR__) . '/database/migrations';
}

// Příkaz pro založení evidence (provede se ručně admin DB účtem; web účet nemá CREATE).
function dracak_migrace_sql_log(): string
{
    return "CREATE TABLE IF NOT EXISTS migrace_log (\n"
        . "  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n"
        . "  soubor VARCHAR(255) NOT NULL UNIQUE,\n"
        . "  spusteno_v TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
}

// Rozdělí soubor na jednotlivé příkazy. Řádky začínající "--" jsou komentář a zahodí se — ale jen mimo řetězcový literál:
// víceřádkový text z pravidel, jehož řádek začíná "-- " (odrážka, dialog), se nesmí tiše ořezat. Středník uvnitř řetězce
// se nepočítá jako konec příkazu. Rozpozná zdvojenou ('') i zpětným lomítkem (\') zapsanou uvozovku; není to obecný SQL
// parser (neřeší dvojité uvozovky ani komentáře /* */ a komentáře za příkazem na řádku — ty nechává databázi).
function dracak_migrace_rozdel_sql(string $sql): array
{
    $prikazy = [];
    $aktualni = '';
    $delka = strlen($sql);
    $vRetezci = false;
    $zpetna = 0;
    $zacatekRadku = true;   // od začátku řádku byly jen bílé znaky
    for ($i = 0; $i < $delka; $i++) {
        $ch = $sql[$i];
        if (!$vRetezci && $zacatekRadku && $ch === '-' && ($sql[$i + 1] ?? '') === '-') {
            $konec = strpos($sql, "\n", $i);
            $i = $konec === false ? $delka : $konec;   // celý komentářový řádek včetně konce řádku pryč
            $aktualni = rtrim($aktualni, " \t\r");     // i odsazení, které před ním už bylo načtené
            $zacatekRadku = true;
            continue;
        }
        if ($ch === "\n") {
            $zacatekRadku = true;
        } elseif ($ch !== ' ' && $ch !== "\t" && $ch !== "\r") {
            $zacatekRadku = false;
        }
        if ($ch === '\\') {
            $zpetna++;
            $aktualni .= $ch;
            continue;
        }
        if ($ch === "'") {
            $escapovana = $vRetezci && ($zpetna % 2) === 1;
            $zpetna = 0;
            if ($escapovana) {
                $aktualni .= $ch;
                continue;
            }
            if ($vRetezci && ($i + 1) < $delka && $sql[$i + 1] === "'") {
                $aktualni .= "''";
                $i++;
                continue;
            }
            $vRetezci = !$vRetezci;
            $aktualni .= $ch;
            continue;
        }
        $zpetna = 0;
        if ($ch === ';' && !$vRetezci) {
            $orez = trim($aktualni);
            if ($orez !== '') {
                $prikazy[] = $orez;
            }
            $aktualni = '';
            continue;
        }
        $aktualni .= $ch;
    }
    $orez = trim($aktualni);
    if ($orez !== '') {
        $prikazy[] = $orez;
    }
    return $prikazy;
}

// Příkaz bez obsahu řetězců a identifikátorů v zpětných apostrofech — pro kontroly, které se nesmí splést o text z pravidel.
function dracak_migrace_bez_retezcu(string $prikaz): string
{
    $bez = preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/s", "''", $prikaz);
    return (string)preg_replace('/`[^`]*`/', '``', (string)$bez);
}

// Zakázaný příkaz (CLAUDE.md: žádný DROP, TRUNCATE ani jinak destruktivní ALTER bez výslovného schválení)? Vrací název
// toho, co se našlo, nebo null. Kontroluje se každý příkaz zvlášť a bez textu v řetězcích, takže věta z pravidel se slovem
// „truncate“ soubor neshodí, a naopak `ALTER TABLE … DROP sloupec` (i bez slova COLUMN), DROP INDEX / FOREIGN KEY / VIEW projde ven.
function dracak_migrace_zakazany_prikaz(string $prikaz): ?string
{
    $bez = dracak_migrace_bez_retezcu($prikaz);
    if (preg_match('/^\s*(DROP\s+\w+|TRUNCATE)\b/i', $bez, $m)) {
        return strtoupper((string)preg_replace('/\s+/', ' ', $m[1]));
    }
    if (preg_match('/^\s*ALTER\b/i', $bez) && preg_match('/\bDROP\b/i', $bez)) {
        return 'ALTER … DROP';
    }
    return null;
}

// První zakázaný příkaz v souboru, nebo null.
function dracak_migrace_zakazany_v_souboru(string $sql): ?string
{
    foreach (dracak_migrace_rozdel_sql($sql) as $prikaz) {
        $nalez = dracak_migrace_zakazany_prikaz($prikaz);
        if ($nalez !== null) {
            return $nalez;
        }
    }
    return null;
}

// Obsahuje soubor CREATE nebo ALTER (DDL)? Web účet ho neprovede — musí se spustit ručně.
function dracak_migrace_ma_ddl(string $sql): bool
{
    foreach (dracak_migrace_rozdel_sql($sql) as $prikaz) {
        if (preg_match('/^(CREATE|ALTER)\s/i', $prikaz)) {
            return true;
        }
    }
    return false;
}

// "CREATE TABLE IF NOT EXISTS" pod web účtem spadne na oprávnění (1142) i když tabulka existuje (IF NOT EXISTS potlačí
// jen chybu "already exists", ne kontrolu oprávnění). Když tabulka už je a jde číst, bereme příkaz jako hotový.
function dracak_migrace_je_create_if_not_exists(string $prikaz, ?string &$tabulka): bool
{
    if (preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', trim($prikaz), $m)) {
        $tabulka = $m[1];
        return true;
    }
    return false;
}

// Rozdělí část ALTER TABLE za názvem tabulky na jednotlivé klauzule (oddělené čárkou mimo závorky a řetězce).
function dracak_migrace_rozdel_klauzule(string $text): array
{
    $klauzule = [];
    $aktualni = '';
    $hloubka = 0;
    $vRetezci = false;
    $zpetna = 0;
    $delka = strlen($text);
    for ($i = 0; $i < $delka; $i++) {
        $ch = $text[$i];
        if ($ch === '\\') {
            $zpetna++;
            $aktualni .= $ch;
            continue;
        }
        if ($ch === "'") {
            $escapovana = $vRetezci && ($zpetna % 2) === 1;
            $zpetna = 0;
            if (!$escapovana) {
                if ($vRetezci && ($text[$i + 1] ?? '') === "'") {
                    $aktualni .= "''";
                    $i++;
                    continue;
                }
                $vRetezci = !$vRetezci;
            }
            $aktualni .= $ch;
            continue;
        }
        $zpetna = 0;
        if (!$vRetezci) {
            if ($ch === '(') {
                $hloubka++;
            } elseif ($ch === ')') {
                $hloubka--;
            } elseif ($ch === ',' && $hloubka === 0) {
                $klauzule[] = trim($aktualni);
                $aktualni = '';
                continue;
            }
        }
        $aktualni .= $ch;
    }
    if (trim($aktualni) !== '') {
        $klauzule[] = trim($aktualni);
    }
    return $klauzule;
}

// Je jedna klauzule ALTER TABLE už provedená? Jen vzory, které pravidla repa u migrací povolují (ADD sloupec / index /
// cizí klíč / primární klíč, MODIFY jako rozšíření výčtu ENUM nebo uvolnění na VARCHAR). Cokoli jiného se záměrně nepozná:
// radši hlasitá chyba než tiché přeskočení něčeho, co jsme neověřili.
function dracak_migrace_klauzule_hotova(PDO $pdo, string $tabulka, string $klauzule, ?string &$popis): bool
{
    $zbytek = trim($klauzule);

    // Indexy a klíče napřed — obecné "ADD <jméno>" níž by jinak za sloupec považovalo slovo INDEX / KEY / CONSTRAINT.
    if (preg_match('/^ADD\s+PRIMARY\s+KEY\b/i', $zbytek)) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = "PRIMARY"');
        $q->execute([$tabulka]);
        if ((int)$q->fetchColumn() > 0) {
            $popis = "tabulka $tabulka už má primární klíč";
            return true;
        }
        return false;
    }
    if (preg_match('/^ADD\s+CONSTRAINT\s+`?(\w+)`?\s+FOREIGN\s+KEY/i', $zbytek, $mm)) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
        $q->execute([$tabulka, $mm[1]]);
        if ((int)$q->fetchColumn() > 0) {
            $popis = "cizí klíč $tabulka.{$mm[1]} už existuje";
            return true;
        }
        return false;
    }
    if (preg_match('/^ADD\s+(?:CONSTRAINT\s+`?\w+`?\s+)?(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?(?:KEY|INDEX)\s+`?(\w+)`?/i', $zbytek, $mm)) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $q->execute([$tabulka, $mm[1]]);
        if ((int)$q->fetchColumn() > 0) {
            $popis = "index $tabulka.{$mm[1]} už existuje";
            return true;
        }
        return false;
    }

    if (preg_match('/^ADD\s+(?:COLUMN\s+)?`?(\w+)`?/i', $zbytek, $mm)
        && !preg_match('/^(CONSTRAINT|INDEX|KEY|UNIQUE|PRIMARY|FULLTEXT|SPATIAL|FOREIGN|CHECK|PERIOD)$/i', $mm[1])) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $q->execute([$tabulka, $mm[1]]);
        if ((int)$q->fetchColumn() > 0) {
            $popis = "sloupec $tabulka.{$mm[1]} už existuje";
            return true;
        }
        return false;
    }

    if (preg_match('/^MODIFY\s+(?:COLUMN\s+)?`?(\w+)`?\s+VARCHAR\s*\(\s*(\d+)\s*\)/i', $zbytek, $mm)) {
        $q = $pdo->prepare('SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $q->execute([$tabulka, $mm[1]]);
        $radek = $q->fetch(PDO::FETCH_ASSOC);
        if ($radek && strtolower((string)$radek['DATA_TYPE']) === 'varchar' && (int)$radek['CHARACTER_MAXIMUM_LENGTH'] >= (int)$mm[2]) {
            $popis = "$tabulka.{$mm[1]} už je VARCHAR({$radek['CHARACTER_MAXIMUM_LENGTH']})";
            return true;
        }
        return false;
    }

    // MODIFY ... ENUM(...): bezpečně se dá ověřit jen rozšíření výčtu — hotovo, když tam všechny požadované hodnoty už jsou.
    if (preg_match('/^MODIFY\s+(?:COLUMN\s+)?`?(\w+)`?.*?ENUM\s*\((.*?)\)/is', $zbytek, $mm)) {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $mm[2], $pozadovane);
        if (!$pozadovane[1]) {
            return false;
        }
        $q = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $q->execute([$tabulka, $mm[1]]);
        $typ = $q->fetchColumn();
        if ($typ === false) {
            return false;
        }
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", (string)$typ, $aktualni);
        if (!array_diff($pozadovane[1], $aktualni[1])) {
            $popis = "$tabulka.{$mm[1]} už má všechny požadované hodnoty výčtu";
            return true;
        }
        return false;
    }

    return false;
}

// Je celý ALTER TABLE už proveden? Musí být hotová KAŽDÁ klauzule — příkaz s více klauzulemi, z nichž jen první už existuje,
// se nesmí zapsat jako hotový (druhá by v databázi chyběla).
function dracak_migrace_alter_uz_hotovo(PDO $pdo, string $prikaz, ?string &$popis): bool
{
    if (!preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+(.*)$/is', trim($prikaz), $m)) {
        return false;
    }
    $popisy = [];
    foreach (dracak_migrace_rozdel_klauzule($m[2]) as $klauzule) {
        $p = null;
        if (!dracak_migrace_klauzule_hotova($pdo, $m[1], $klauzule, $p)) {
            return false;
        }
        $popisy[] = $p;
    }
    $popis = implode('; ', $popisy);
    return $popisy !== [];
}

// Provede jeden příkaz. Vrací poznámku (prázdná, nebo "přeskočeno, protože už hotovo"); při chybě vyhodí PDOException.
// Přeskočit se smí jen při odepřeném oprávnění (1142 / 1044) — jiná chyba (třeba špatná syntaxe) musí být vidět.
function dracak_migrace_proved_prikaz(PDO $pdo, string $prikaz): string
{
    try {
        $pdo->exec($prikaz);
        return '';
    } catch (PDOException $e) {
        if (!in_array((int)($e->errorInfo[1] ?? 0), [1142, 1044], true)) {
            throw $e;
        }
        if (dracak_migrace_je_create_if_not_exists($prikaz, $tabulka)) {
            try {
                $pdo->query('SELECT 1 FROM `' . $tabulka . '` LIMIT 1');
            } catch (PDOException $eSelect) {
                throw $e;
            }
            return "tabulka $tabulka už existuje (CREATE se přeskočil, web účet nemá právo ho provést)";
        }
        if (dracak_migrace_alter_uz_hotovo($pdo, $prikaz, $popis)) {
            return "$popis (ALTER se přeskočil, web účet nemá právo ho provést)";
        }
        throw $e;
    }
}

// Stav evidence a všech souborů s migracemi:
// ['log_ok' => bool, 'log_chyba' => ?string, 'soubory' => [['soubor', 'pouzito' => bool, 'kdy' => ?string, 'struktura' => ?bool (jen u čekajících)]]].
function dracak_migrace_stav(PDO $pdo): array
{
    $stav = ['log_ok' => true, 'log_chyba' => null, 'soubory' => []];
    $pouzite = [];
    try {
        foreach ($pdo->query('SELECT soubor, spusteno_v FROM migrace_log')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pouzite[$r['soubor']] = $r['spusteno_v'];
        }
    } catch (PDOException $e) {
        $stav['log_ok'] = false;
        $stav['log_chyba'] = $e->getMessage();
    }
    $soubory = glob(dracak_migrace_slozka() . '/*.sql') ?: [];
    sort($soubory);
    foreach ($soubory as $cesta) {
        $nazev = basename($cesta);
        if (!preg_match('/^\d{4}_[A-Za-z0-9_]+\.sql$/', $nazev)) {
            continue;
        }
        $pouzito = isset($pouzite[$nazev]);
        $stav['soubory'][] = [
            'soubor' => $nazev,
            'pouzito' => $pouzito,
            'kdy' => $pouzite[$nazev] ?? null,
            // soubor se rozebírá jen u čekajících (migrace mají dohromady přes 1 MB)
            'struktura' => $pouzito ? null : dracak_migrace_ma_ddl((string)file_get_contents($cesta)),
        ];
    }
    return $stav;
}

// Spustí všechny čekající migrace popořadě; při první chybě se zastaví.
// Vrací ['radky' => [text], 'spusteno' => int, 'chyba' => ?string].
function dracak_migrace_spust(PDO $pdo): array
{
    $vysledek = ['radky' => [], 'spusteno' => 0, 'chyba' => null];
    // Větší datové migrace (stovky kB) můžou trvat déle než výchozí limit hostingu a zavřená záložka nesmí spuštění
    // utnout uprostřed souboru.
    ignore_user_abort(true);
    @set_time_limit(300);

    // Dvě současná spuštění (dvojklik, dva admini) by se mohla přetahovat o stejný soubor.
    $zamek = (int)$pdo->query("SELECT GET_LOCK('dracak_migrace', 0)")->fetchColumn();
    if ($zamek !== 1) {
        $vysledek['chyba'] = 'Změny právě pouští někdo jiný — počkej chvíli a načti stránku znovu.';
        return $vysledek;
    }
    try {
        $stav = dracak_migrace_stav($pdo);
        if (!$stav['log_ok']) {
            $vysledek['chyba'] = 'Chybí evidence proběhlých změn (tabulka migrace_log). Založ ji ručně (viz stránka).';
            return $vysledek;
        }
        foreach ($stav['soubory'] as $s) {
            if ($s['pouzito']) {
                continue;
            }
            $nazev = $s['soubor'];
            $sql = file_get_contents(dracak_migrace_slozka() . '/' . $nazev);
            if ($sql === false) {
                $vysledek['chyba'] = "Nejde přečíst $nazev.";
                return $vysledek;
            }
            $zakazany = dracak_migrace_zakazany_v_souboru($sql);
            if ($zakazany !== null) {
                $vysledek['chyba'] = "Změna $nazev obsahuje zakázaný příkaz ($zakazany). DROP, TRUNCATE ani destruktivní ALTER se sem nikdy nepíší bez výslovného schválení — soubor se vůbec nespustil.";
                return $vysledek;
            }
            $vysledek['radky'][] = "Spouštím $nazev …";
            $pdo->beginTransaction();
            try {
                foreach (dracak_migrace_rozdel_sql($sql) as $prikaz) {
                    $poznamka = dracak_migrace_proved_prikaz($pdo, $prikaz);
                    if ($poznamka !== '') {
                        $vysledek['radky'][] = '   ' . $poznamka;
                    }
                }
                $pdo->prepare('INSERT INTO migrace_log (soubor) VALUES (?)')->execute([$nazev]);
                // Po CREATE/ALTER už MySQL transakci samo ukončilo — commit jen když ještě běží.
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                $vysledek['radky'][] = "Hotovo: $nazev";
                $vysledek['spusteno']++;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $text = $e->getMessage();
                if ((int)($e->errorInfo[1] ?? 0) === 1142) {
                    $text = "tahle změna potřebuje ruční SQL (CREATE nebo ALTER — web účet na ně nemá právo). Pošle ti ho Claude. ($text)";
                }
                $vysledek['chyba'] = "Chyba v $nazev: $text. Další změny se nespustily. Pokud soubor obsahoval víc CREATE/ALTER příkazů, mohly některé proběhnout natrvalo — MySQL je nevrací zpět.";
                return $vysledek;
            }
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('dracak_migrace')")->fetchAll();
    }
    return $vysledek;
}
