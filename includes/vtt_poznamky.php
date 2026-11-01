<?php
declare(strict_types=1);

require_once __DIR__ . '/vtt.php';

// Sdílené poznámky ve světě (viz database/migrations/0060_svet_poznamky.sql, 0105_poznamky_sveta.sql
// a konverzace) — libovolný člen světa napíše poznámku a vybere si, komu
// konkrétnímu ji nasdílí, ne "všem hráčům" jako jeden vypínač. Autor svou
// poznámku vidí vždycky. PJ světa NENÍ automaticky příjemce — je to jen
// další možný příjemce jako kterýkoliv hráč, takže jde udělat i čistě
// hráčské tajemství, o kterém se PJ nedozví. Jediná výjimka s dohledem je
// skutečný ucty.role='admin' (ne PJ světa) — ten vidí vše "v rámci
// bezpečnostní kontroly" (admin_poznamky.php).
//
// Rozšíření (migrace 0105, ruční SQL — bez něj fungují poznámky po staru): štítek (pevná sada níž), vazba na postavu/NPC,
// čas poslední úpravy a „přečteno“ pro odznak nepřečtených. Editace přepisuje poznámku na místě, bez historie; smí ji jen autor.

// Pevná sada štítků: klíč v DB -> název v rozhraní. Nový štítek = nový řádek tady, žádné DDL.
const DRACAK_VTT_POZNAMKY_STITKY = [
    'quest' => 'Úkol',
    'npc' => 'NPC',
    'loot' => 'Kořist',
    'lore' => 'Příběh',
    'misto' => 'Místo',
];

// Co z rozšíření je v DB k dispozici (ruční SQL ještě nemusel proběhnout): sloupce štítku/postavy/úpravy a tabulka „přečteno“.
function dracak_vtt_poznamky_funkce(PDO $pdo): array
{
    static $cache = [];
    $klic = spl_object_id($pdo);
    if (isset($cache[$klic])) {
        return $cache[$klic];
    }
    $f = ['rozsireno' => false, 'precteno' => false];
    try {
        $sloupce = $pdo->query(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "svet_poznamky" AND COLUMN_NAME IN ("stitek", "postava_id", "upraveno")'
        )->fetchAll(PDO::FETCH_COLUMN);
        $f['rozsireno'] = count($sloupce) === 3;
        $f['precteno'] = (int)$pdo->query(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "svet_poznamka_precteno"'
        )->fetchColumn() > 0;
    } catch (Throwable $e) {
        // information_schema nedostupné — rozšíření zůstane vypnuté
    }
    return $cache[$klic] = $f;
}

// Všechny poznámky viditelné TOMUTO účtu v tomhle světě — autorovy
// vlastní + ty, co mu někdo nasdílel (admin: úplně všechny). Každá nese
// i seznam jmen, komu ji autor nasdílel (sdileno_s) — jen pro autora má
// smysl to ukazovat, viz hra/svet.php. S rozšířením nese i štítek, název postavy a čas úpravy.
function dracak_vtt_poznamky_viditelne(PDO $pdo, array $user, int $svetId, ?string $stitek = null): array
{
    $roz = dracak_vtt_poznamky_funkce($pdo)['rozsireno'];
    $sloupce = 'p.*, u.jmeno AS autor_jmeno, m.nazev AS mapa_nazev' . ($roz ? ', pv.nazev AS postava_nazev' : '');
    $spoj = 'JOIN ucty u ON u.id = p.autor_ucet_id LEFT JOIN mapy m ON m.id = p.mapa_id'
        . ($roz ? ' LEFT JOIN postavy pv ON pv.id = p.postava_id' : '');
    $filtr = '';
    $params = [$svetId];
    if ($roz && $stitek !== null && isset(DRACAK_VTT_POZNAMKY_STITKY[$stitek])) {
        $filtr = ' AND p.stitek = ?';
        $params[] = $stitek;
    }
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare("SELECT $sloupce FROM svet_poznamky p $spoj WHERE p.svet_id = ?$filtr ORDER BY p.vytvoreno DESC, p.id DESC");
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT $sloupce FROM svet_poznamky p $spoj
             LEFT JOIN svet_poznamka_sdileni s ON s.poznamka_id = p.id
             WHERE p.svet_id = ? AND (p.autor_ucet_id = ? OR s.ucet_id = ?)$filtr
             ORDER BY p.vytvoreno DESC, p.id DESC"
        );
        $stmt->execute(array_merge([$svetId, $user['id'], $user['id']], array_slice($params, 1)));
    }
    $poznamky = $stmt->fetchAll();
    if (!$poznamky) {
        return [];
    }
    $ids = array_column($poznamky, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT s.poznamka_id, u.id AS ucet_id, u.jmeno FROM svet_poznamka_sdileni s
         JOIN ucty u ON u.id = s.ucet_id WHERE s.poznamka_id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $sdilenoPodleId = [];
    $sdilenoIdPodleId = [];
    foreach ($stmt->fetchAll() as $row) {
        $sdilenoPodleId[(int)$row['poznamka_id']][] = $row['jmeno'];
        $sdilenoIdPodleId[(int)$row['poznamka_id']][] = (int)$row['ucet_id'];
    }
    foreach ($poznamky as &$p) {
        $p['sdileno_s'] = $sdilenoPodleId[(int)$p['id']] ?? [];
        $p['sdileno_ids'] = $sdilenoIdPodleId[(int)$p['id']] ?? [];
        $p['stitek'] = $roz && isset(DRACAK_VTT_POZNAMKY_STITKY[(string)($p['stitek'] ?? '')]) ? (string)$p['stitek'] : null;
    }
    unset($p);
    return $poznamky;
}

// Komu všemu JDE poznámku nasdílet — kdokoli s přístupem do světa (PJ +
// svet_hraci), kromě autora samotného (vlastní poznámky vidí tak jako tak).
function dracak_vtt_poznamky_moznosti_sdileni(PDO $pdo, int $svetId, int $autorUcetId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.jmeno FROM ucty u JOIN svet s ON s.pj_ucet_id = u.id WHERE s.id = ? AND u.id != ?
         UNION
         SELECT u.id, u.jmeno FROM ucty u JOIN svet_hraci sh ON sh.ucet_id = u.id WHERE sh.svet_id = ? AND u.id != ?
         ORDER BY jmeno'
    );
    $stmt->execute([$svetId, $autorUcetId, $svetId, $autorUcetId]);
    return $stmt->fetchAll();
}

// Příjemci jen z těch, komu jde ve světě poznámku nasdílet (id od klienta se nevěří: jinak by šlo „nasdílet“ účtu, který do světa nepatří).
function dracak_vtt_poznamky_platni_prijemci(PDO $pdo, int $svetId, int $autorUcetId, array $ucetIds): array
{
    $povolene = array_map('intval', array_column(dracak_vtt_poznamky_moznosti_sdileni($pdo, $svetId, $autorUcetId), 'id'));
    return array_values(array_unique(array_intersect(array_map('intval', $ucetIds), $povolene)));
}

// Postavy a NPC, na které jde poznámku navázat: jen z tohoto světa.
function dracak_vtt_poznamky_postava_ve_svete(PDO $pdo, int $svetId, ?int $postavaId): ?int
{
    if ($postavaId === null || $postavaId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM postavy WHERE id = ? AND svet_id = ?');
    $stmt->execute([$postavaId, $svetId]);
    return $stmt->fetchColumn() ? $postavaId : null;
}

// Text bez diakritiky a malými písmeny, ať se klíčová slova hledají bez ohledu na „ú/u“, „ž/z“.
function dracak_vtt_poznamka_bez_diakritiky(string $text): string
{
    return strtr(mb_strtolower($text), [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r',
        'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
    ]);
}

// Auto-návrh štítku, když ho autor nezvolí: nejdřív klíčová slova v textu (Úkol, Kořist, NPC, Příběh, Místo — v tomhle pořadí důležitosti),
// pak vazba na postavu (NPC) a vazba na místo (Místo). Nic nesedí = bez štítku. Je to jen návrh — autor ho v úpravě změní.
function dracak_vtt_poznamka_stitek_navrh(string $text, ?int $mapaId, ?int $postavaId): ?string
{
    $t = ' ' . dracak_vtt_poznamka_bez_diakritiky($text) . ' ';
    $slova = [
        'quest' => ['ukol', 'quest', 'zakazk', 'odmen', 'mise ', 'slibil', 'zachran', 'je treba', 'musime '],
        'loot' => ['korist', 'poklad', 'zlato', 'zlata', 'zlatak', 'stribrn', 'drahokam', 'svitek', 'lektvar', 'kouzelny predmet', 'nasli jsme', 'vzali jsme'],
        'npc' => [' npc', 'hostinsk', 'kupec', 'obchodnik', 'starosta', 'straznik', 'kral ', 'kralovn', 'kneze', 'knez ', 'jmenuje se', 'zvou ho', 'zvou ji'],
        'lore' => ['legend', 'povest', 'historie', 'dejiny', 'proroctv', 'mytus', 'pribeh', 'bohov', 'kdysi', 'drive se'],
        'misto' => ['vesnic', 'mesto', 'mesta ', 'hrad', 'jeskyn', 'hospod', 'taverna', 'cesta ', 'les ', 'lese', 'hory', 'brana', 'chram', 'sklep', 'dungeon'],
    ];
    foreach ($slova as $stitek => $klicova) {
        foreach ($klicova as $k) {
            if (str_contains($t, $k)) {
                return $stitek;
            }
        }
    }
    if ($postavaId !== null && $postavaId > 0) {
        return 'npc';
    }
    if ($mapaId !== null && $mapaId > 0) {
        return 'misto';
    }
    return null;
}

// Štítek z formuláře: platný klíč ze sady, prázdné = „podle textu“ (auto-návrh), cokoli jiného = bez štítku.
function dracak_vtt_poznamka_stitek_z_formulare(?string $vstup, string $text, ?int $mapaId, ?int $postavaId): ?string
{
    $vstup = trim((string)$vstup);
    if ($vstup === '' || $vstup === 'auto') {
        return dracak_vtt_poznamka_stitek_navrh($text, $mapaId, $postavaId);
    }
    return isset(DRACAK_VTT_POZNAMKY_STITKY[$vstup]) ? $vstup : null;
}

// $denPriVytvoreni = snapshot svet.aktualni_den_offset v okamžiku zápisu
// (volající ho musí dodat — tahle funkce svet nedočítá, ať nedojde k
// nekonzistenci mezi tím, co PJ viděl na obrazovce, a tím, co se
// skutečně uloží, viz hra/svet.php). $mapaId nepovinné — vazba na
// konkrétní místo, ke kterému se poznámka váže.
function dracak_vtt_poznamka_pridat(PDO $pdo, int $svetId, int $autorUcetId, string $text, array $sdilenoSUcetId, int $denPriVytvoreni, ?int $mapaId = null, ?string $stitek = null, ?int $postavaId = null): int
{
    $sdilenoSUcetId = dracak_vtt_poznamky_platni_prijemci($pdo, $svetId, $autorUcetId, $sdilenoSUcetId);
    if (dracak_vtt_poznamky_funkce($pdo)['rozsireno']) {
        $postavaId = dracak_vtt_poznamky_postava_ve_svete($pdo, $svetId, $postavaId);
        $stmt = $pdo->prepare(
            'INSERT INTO svet_poznamky (svet_id, autor_ucet_id, text, den_pri_vytvoreni, mapa_id, stitek, postava_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$svetId, $autorUcetId, $text, $denPriVytvoreni, $mapaId, $stitek, $postavaId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO svet_poznamky (svet_id, autor_ucet_id, text, den_pri_vytvoreni, mapa_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$svetId, $autorUcetId, $text, $denPriVytvoreni, $mapaId]);
    }
    $poznamkaId = (int)$pdo->lastInsertId();
    if ($sdilenoSUcetId) {
        $ins = $pdo->prepare('INSERT IGNORE INTO svet_poznamka_sdileni (poznamka_id, ucet_id) VALUES (?, ?)');
        foreach ($sdilenoSUcetId as $ucetId) {
            $ins->execute([$poznamkaId, (int)$ucetId]);
        }
    }
    return $poznamkaId;
}

// Úprava poznámky na místě (bez historie): text, místo, štítek, postava a sdílení. Smí jen autor — ani admin nepřepisuje cizí slova
// (smazat cizí poznámku smí, jako dřív). Komu se sdílení nově přidá, a všem příjemcům po změně textu, se poznámka zase ukáže jako nová.
// Vrací false, když poznámka neexistuje, nepatří do světa nebo ji uživatel nenapsal.
function dracak_vtt_poznamka_upravit(PDO $pdo, int $svetId, int $poznamkaId, array $user, string $text, array $sdilenoSUcetId, ?int $mapaId, ?string $stitek, ?int $postavaId): bool
{
    $stmt = $pdo->prepare('SELECT text FROM svet_poznamky WHERE id = ? AND svet_id = ? AND autor_ucet_id = ? FOR UPDATE');
    $f = dracak_vtt_poznamky_funkce($pdo);
    $pdo->beginTransaction();
    try {
        $stmt->execute([$poznamkaId, $svetId, (int)$user['id']]);
        $puvodni = $stmt->fetchColumn();
        if ($puvodni === false) {
            $pdo->rollBack();
            return false;
        }
        $prijemci = dracak_vtt_poznamky_platni_prijemci($pdo, $svetId, (int)$user['id'], $sdilenoSUcetId);
        if ($f['rozsireno']) {
            $postavaId = dracak_vtt_poznamky_postava_ve_svete($pdo, $svetId, $postavaId);
            $pdo->prepare('UPDATE svet_poznamky SET text = ?, mapa_id = ?, stitek = ?, postava_id = ?, upraveno = NOW() WHERE id = ?')
                ->execute([$text, $mapaId, $stitek, $postavaId, $poznamkaId]);
        } else {
            $pdo->prepare('UPDATE svet_poznamky SET text = ?, mapa_id = ? WHERE id = ?')->execute([$text, $mapaId, $poznamkaId]);
        }
        // Sdílení: kdo zůstal, zůstává; kdo je nový, přibude; kdo vypadl, o poznámku přijde.
        $stmt = $pdo->prepare('SELECT ucet_id FROM svet_poznamka_sdileni WHERE poznamka_id = ?');
        $stmt->execute([$poznamkaId]);
        $drive = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $pridat = array_diff($prijemci, $drive);
        $odebrat = array_diff($drive, $prijemci);
        $ins = $pdo->prepare('INSERT IGNORE INTO svet_poznamka_sdileni (poznamka_id, ucet_id) VALUES (?, ?)');
        foreach ($pridat as $ucetId) {
            $ins->execute([$poznamkaId, $ucetId]);
        }
        $del = $pdo->prepare('DELETE FROM svet_poznamka_sdileni WHERE poznamka_id = ? AND ucet_id = ?');
        foreach ($odebrat as $ucetId) {
            $del->execute([$poznamkaId, $ucetId]);
        }
        if ($f['precteno']) {
            if ($text !== (string)$puvodni) {
                // Změněný text si má každý příjemce přečíst znovu.
                $pdo->prepare('DELETE FROM svet_poznamka_precteno WHERE poznamka_id = ?')->execute([$poznamkaId]);
            } else {
                $dl = $pdo->prepare('DELETE FROM svet_poznamka_precteno WHERE poznamka_id = ? AND ucet_id = ?');
                foreach ($odebrat as $ucetId) {
                    $dl->execute([$poznamkaId, $ucetId]);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return true;
}

// Smazat smí jen autor, nebo skutečný admin — PJ světa na to žádné
// zvláštní právo nemá, se sdílením poznámek nijak nedohlíží.
function dracak_vtt_poznamka_smazat(PDO $pdo, int $poznamkaId, array $user): bool
{
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare('DELETE FROM svet_poznamky WHERE id = ?');
        $stmt->execute([$poznamkaId]);
        return $stmt->rowCount() > 0;
    }
    $stmt = $pdo->prepare('DELETE FROM svet_poznamky WHERE id = ? AND autor_ucet_id = ?');
    $stmt->execute([$poznamkaId, $user['id']]);
    return $stmt->rowCount() > 0;
}

// Id poznámek v tomhle světě, které účtu někdo nasdílel a on je ještě nečetl (autorovy vlastní a admin-dohled se nepočítají).
function dracak_vtt_poznamky_nove_ids(PDO $pdo, int $ucetId, int $svetId): array
{
    if (!dracak_vtt_poznamky_funkce($pdo)['precteno']) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT p.id FROM svet_poznamky p
         JOIN svet_poznamka_sdileni s ON s.poznamka_id = p.id AND s.ucet_id = ?
         LEFT JOIN svet_poznamka_precteno r ON r.poznamka_id = p.id AND r.ucet_id = ?
         WHERE p.svet_id = ? AND p.autor_ucet_id <> ? AND r.poznamka_id IS NULL'
    );
    $stmt->execute([$ucetId, $ucetId, $svetId, $ucetId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// Počty nepřečtených poznámek po světech (pro odznak v seznamu světů): [svet_id => počet], jen nenulové.
function dracak_vtt_poznamky_nove_pocty(PDO $pdo, int $ucetId): array
{
    if (!dracak_vtt_poznamky_funkce($pdo)['precteno']) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT p.svet_id, COUNT(*) FROM svet_poznamky p
         JOIN svet_poznamka_sdileni s ON s.poznamka_id = p.id AND s.ucet_id = ?
         LEFT JOIN svet_poznamka_precteno r ON r.poznamka_id = p.id AND r.ucet_id = ?
         WHERE p.autor_ucet_id <> ? AND r.poznamka_id IS NULL
         GROUP BY p.svet_id'
    );
    $stmt->execute([$ucetId, $ucetId, $ucetId]);
    $pocty = [];
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$svet, $n]) {
        $pocty[(int)$svet] = (int)$n;
    }
    return $pocty;
}

// Označí všechny nasdílené poznámky světa jako přečtené (volá se po zobrazení poznámek světa; „nové“ se spočítá dřív).
function dracak_vtt_poznamky_oznac_prectene(PDO $pdo, int $ucetId, int $svetId): void
{
    if (!dracak_vtt_poznamky_funkce($pdo)['precteno']) {
        return;
    }
    $pdo->prepare(
        'INSERT IGNORE INTO svet_poznamka_precteno (poznamka_id, ucet_id)
         SELECT p.id, ? FROM svet_poznamky p
         JOIN svet_poznamka_sdileni s ON s.poznamka_id = p.id AND s.ucet_id = ?
         WHERE p.svet_id = ?'
    )->execute([$ucetId, $ucetId, $svetId]);
}

// Odebrání hráče ze světa: poznámky tohoto světa, které mu kdokoli nasdílel, se mu přestanou ukazovat (jeho vlastní poznámky zůstávají
// a patří dál jemu a těm, komu je nasdílel).
function dracak_vtt_poznamky_zrus_sdileni_hrace(PDO $pdo, int $svetId, int $ucetId): int
{
    $stmt = $pdo->prepare(
        'DELETE s FROM svet_poznamka_sdileni s JOIN svet_poznamky p ON p.id = s.poznamka_id WHERE p.svet_id = ? AND s.ucet_id = ?'
    );
    $stmt->execute([$svetId, $ucetId]);
    return $stmt->rowCount();
}

// "dnes" / "před 1 dnem" / "před 2 dny" / "před 5 dny" — $n je HERNÍCH
// dní (aktualni_den_offset teď minus v okamžiku vzniku), ne reálný čas.
function dracak_vtt_pocet_dni_text(int $n): string
{
    if ($n <= 0) {
        return 'dnes';
    }
    if ($n === 1) {
        return 'před 1 dnem';
    }
    if ($n <= 4) {
        return "před $n dny";
    }
    return "před $n dní";
}
