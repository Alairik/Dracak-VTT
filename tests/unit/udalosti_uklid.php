<?php
declare(strict_types=1);

// Úklid tabulky svet_udalosti (dracak_vtt_udalosti_uklid v includes/vtt.php).
//
// Proč test: tabulka se od začátku jen plnila — každý hod, pohyb i tik efektu je řádek — a nic ji nikdy
// nezkracovalo. Úklid běží opportunisticky při zápisu události, takže musí být nepochybné, že nesmaže nic,
// na čem stojí zpětná čtení v souboji (posledních 200 událostí mapy od „souboj_start“, posledních 80
// „akce_provedena“). Ověřuje se proto obojí: že staré řádky nad stropem zmizí, a že nové ani řádky
// v rámci stropu nezmizí nikdy.
//
// Spuštění: php tests/unit/udalosti_uklid.php   (potřebuje lokální DB podle config.php)

require dirname(__DIR__, 2) . '/includes/vtt.php';

$chyb = 0;
function ok(bool $c, string $m): void { global $chyb; if (!$c) { $chyb++; } echo ($c ? 'OK   ' : 'CHYBA') . " $m\n"; }

$pdo = dracak_db();
$pdo->beginTransaction();   // všechno se na konci vrátí zpět, ostrá ani testovací data se nezmění

try {
    // Svět, který v datech není, ať se test nepotká s ničím skutečným.
    $svetId = (int)$pdo->query('SELECT COALESCE(MAX(svet_id), 0) + 1000 FROM svet_udalosti')->fetchColumn();
    $jinySvet = $svetId + 1;

    $vloz = $pdo->prepare('INSERT INTO svet_udalosti (svet_id, mapa_id, typ, payload, ucet_id, vytvoreno) VALUES (?, NULL, ?, NULL, NULL, ?)');
    $stare = date('Y-m-d H:i:s', time() - 30 * 86400);
    $nove = date('Y-m-d H:i:s');

    // o 50 víc, než je strop, a všechno staré
    $celkem = DRACAK_VTT_UDALOSTI_STROP + 50;
    for ($i = 0; $i < $celkem; $i++) {
        $vloz->execute([$svetId, 'test_udalost', $stare]);
    }
    // cizí svět se nesmí dotknout
    for ($i = 0; $i < 5; $i++) {
        $vloz->execute([$jinySvet, 'test_udalost', $stare]);
    }

    $pocet = static fn(int $s): int => (int)$pdo->query('SELECT COUNT(*) FROM svet_udalosti WHERE svet_id = ' . $s)->fetchColumn();
    ok($pocet($svetId) === $celkem, 'připraveno ' . $celkem . ' událostí: ' . $pocet($svetId));

    // 1) úklid po dávkách smaže přesně přebytek nad stropem, ne víc
    $smazano = 0;
    for ($i = 0; $i < 10; $i++) {
        $n = dracak_vtt_udalosti_uklid($svetId, 20);
        $smazano += $n;
        if ($n === 0) { break; }
    }
    ok($smazano === 50, 'smazal se přesně přebytek nad stropem (50): ' . $smazano);
    ok($pocet($svetId) === DRACAK_VTT_UDALOSTI_STROP, 've světě zůstal přesně strop: ' . $pocet($svetId));
    ok(dracak_vtt_udalosti_uklid($svetId, 20) === 0, 'další úklid už nemá co mazat');
    ok($pocet($jinySvet) === 5, 'cizí svět zůstal nedotčený: ' . $pocet($jinySvet));

    // 2) co je mladší než lhůta, se nesmaže ani nad stropem
    $svetNovy = $svetId + 2;
    for ($i = 0; $i < DRACAK_VTT_UDALOSTI_STROP + 30; $i++) {
        $vloz->execute([$svetNovy, 'test_udalost', $nove]);
    }
    ok(dracak_vtt_udalosti_uklid($svetNovy, 100) === 0, 'čerstvé události úklid nemaže, i když je jich nad strop');
    ok($pocet($svetNovy) === DRACAK_VTT_UDALOSTI_STROP + 30, 'zůstaly všechny: ' . $pocet($svetNovy));

    // 3) svět pod stropem se nemaže vůbec
    $svetMaly = $svetId + 3;
    for ($i = 0; $i < 10; $i++) {
        $vloz->execute([$svetMaly, 'test_udalost', $stare]);
    }
    ok(dracak_vtt_udalosti_uklid($svetMaly, 100) === 0, 'malý svět se nemaže, i když je starý');
    ok($pocet($svetMaly) === 10, 'v malém světě zůstalo všech 10: ' . $pocet($svetMaly));

    // 4) prázdný svět nespadne
    ok(dracak_vtt_udalosti_uklid($svetId + 4, 100) === 0, 'svět bez událostí úklid jen přejde');
} finally {
    $pdo->rollBack();
}

echo $chyb ? "CHYB: $chyb\n" : "VŠE OK\n";
exit($chyb ? 1 : 0);
