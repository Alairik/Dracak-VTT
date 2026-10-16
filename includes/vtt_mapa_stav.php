<?php
declare(strict_types=1);

// Stav jedné mapy pro klienta (tokeny, efekty, pořadí tahů, postavy k
// položení). Jediný zdroj pravdy pro počáteční vykreslení v hra/mapa.php
// i pro obnovu "na místě" přes hra/api/mapa_stav.php — díky tomu se po
// přidání tokenu, hodu iniciativy, novém kole… stránka už nepřenačítá a
// hráčům nemizí otevřené panely, zoom ani vybraný token.
//
// Počítá se pro konkrétního uživatele (owned / na_tahu / skryté tokeny),
// takže se nikdy nesmí cachovat napříč účty.

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_iniciativa.php';
require_once __DIR__ . '/vtt_kresby.php';
require_once __DIR__ . '/vtt_souboj.php';

// Stav ručně spouštěného SQL (migrace 0084) — jedna sada dotazů na
// information_schema za požadavek. Mapa je napsaná tak, aby fungovala i
// před spuštěním SQL (nové funkce se pak jen schovají).
function dracak_vtt_ddl_mapa(): array
{
    static $stav = null;
    if ($stav !== null) {
        return $stav;
    }
    $stav = ['sloupce' => [], 'typ_entity' => '', 'udalosti_varchar' => false, 'tabulky' => [], 'kolo_sloupce' => []];
    try {
        $pdo = dracak_db();
        foreach ($pdo->query(
            'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "tokeny"'
        )->fetchAll(PDO::FETCH_NUM) as [$nazev, $typ]) {
            $stav['sloupce'][$nazev] = true;
            if ($nazev === 'typ_entity') {
                $stav['typ_entity'] = (string)$typ;
            }
        }
        $typUdalosti = $pdo->query(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "svet_udalosti" AND COLUMN_NAME = "typ"'
        )->fetchColumn();
        $stav['udalosti_varchar'] = $typUdalosti !== false && strtolower((string)$typUdalosti) === 'varchar';
        foreach ($pdo->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ("svet_obrazky", "mapa_kresby", "kolo_ucastnik", "souboj_vyzva", "nestvura_staty_sveta")'
        )->fetchAll(PDO::FETCH_COLUMN) as $tabulka) {
            $stav['tabulky'][$tabulka] = true;
        }
        foreach ($pdo->query(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "kolo_stav"'
        )->fetchAll(PDO::FETCH_COLUMN) as $sloupec) {
            $stav['kolo_sloupce'][$sloupec] = true;
        }
    } catch (Throwable $e) {
        // information_schema nedostupné — nové funkce zůstanou vypnuté
    }
    return $stav;
}

function dracak_vtt_tokeny_ma_sloupec(string $sloupec): bool
{
    return isset(dracak_vtt_ddl_mapa()['sloupce'][$sloupec]);
}

// Které z nových funkcí mapy jsou v DB k dispozici (UI podle toho nabízí
// nebo schovává ovládání, API je podle toho povolují).
function dracak_vtt_tokeny_funkce(): array
{
    $ddl = dracak_vtt_ddl_mapa();
    $sl = $ddl['sloupce'];
    $udalosti = $ddl['udalosti_varchar'];
    return [
        'smer' => isset($sl['smer']) && $udalosti,
        'velikost' => isset($sl['velikost_sahu']) && $udalosti,
        'barva' => isset($sl['barva']) && $udalosti,
        'zamek' => isset($sl['zamceno']) && $udalosti,
        'nazev' => isset($sl['nazev']) && $udalosti,
        'obrazek' => isset($sl['obrazek_id']) && $udalosti && isset($ddl['tabulky']['svet_obrazky']),
        'hexy' => isset($sl['hexu']) && $udalosti,
        'obecny' => str_contains($ddl['typ_entity'], 'obecny'),
        'kresby' => $udalosti && isset($ddl['tabulky']['mapa_kresby']),
        'udalosti' => $udalosti,
        // Souboj s fázemi a půlkami kola (migrace 0085) — bez ručního SQL
        // zůstává dnešní jednoduchá iniciativa.
        'souboj' => $udalosti && isset($ddl['tabulky']['kolo_ucastnik'])
            && isset($ddl['kolo_sloupce']['faze'], $ddl['kolo_sloupce']['polovina'], $ddl['kolo_sloupce']['prekvapeni']),
        // Výzvy k hodu (útok, obrana, past; tabulka souboj_vyzva z ručního SQL) — bez nich se útok
        // jen zapíše do logu.
        // Deník nestvůry (tabulka nestvura_staty_sveta z ručního SQL): čísla pro boj, která PJ doplní jednou za druh.
        'denik_nestvur' => isset($ddl['tabulky']['nestvura_staty_sveta']),
        'vyzvy' => $udalosti && isset($ddl['tabulky']['kolo_ucastnik'], $ddl['tabulky']['souboj_vyzva'])
            && isset($ddl['kolo_sloupce']['faze'], $ddl['kolo_sloupce']['polovina'], $ddl['kolo_sloupce']['prekvapeni']),
    ];
}

// Klíče, kterými se mění vzhled tokenu (token_uprava.php, událost token_zmena),
// a sloupce v tabulce tokeny, které jim odpovídají.
const DRACAK_VTT_TOKEN_KLIC_NA_SLOUPEC = [
    'smer' => 'smer',
    'velikost' => 'velikost_sahu',
    'hexu' => 'hexu',
    'barva' => 'barva',
    'zamceno' => 'zamceno',
    'nazev' => 'nazev',
    'obrazek_id' => 'obrazek_id',
    'viditelny' => 'viditelny_hracum',
    'z' => 'z_poradi',
];

// Hodnota klíče z řádku tabulky tokeny ve tvaru, v jakém ji nese token_zmena.
function dracak_vtt_token_hodnota_klice(array $radek, string $klic)
{
    $sloupec = DRACAK_VTT_TOKEN_KLIC_NA_SLOUPEC[$klic] ?? null;
    if ($sloupec === null || !array_key_exists($sloupec, $radek)) {
        return null;
    }
    $h = $radek[$sloupec];
    return match ($klic) {
        'smer', 'z', 'hexu' => (int)$h,
        'velikost' => (float)$h,
        'zamceno', 'viditelny' => (bool)$h,
        'obrazek_id' => $h === null ? null : (int)$h,
        default => $h === null ? null : (string)$h,
    };
}

function dracak_vtt_mapa_stav(PDO $pdo, array $user, array $mapa, bool $jePj): array
{
    $mapaId = (int)$mapa['id'];

    // Pořadí je důležité: nejdřív poslední událost, teprve potom snímek
    // tokenů — jinak by mezi nimi mohla vzniknout mezera, ve které se
    // událost ztratí (stejná úvaha jako v hra/api/stav.php).
    $posledniUdalostId = (int)($pdo->query('SELECT MAX(id) FROM svet_udalosti WHERE svet_id = ' . (int)$mapa['svet_id'])->fetchColumn() ?: 0);

    $stmt = $pdo->prepare(
        'SELECT t.*,
                p.nazev AS postava_nazev, p.vlastnik_ucet_id, p.aktualni_hp AS postava_hp, p.max_hp AS postava_max_hp,
                p.aktualni_magenergie AS postava_magenergie, p.max_magenergie AS postava_max_magenergie,
                ni.nazev_instance, ni.aktualni_hp AS nestvura_hp, ni.max_hp AS nestvura_max_hp
         FROM tokeny t
         LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
         LEFT JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = "nestvura_instance"
         WHERE t.mapa_id = ?
         ORDER BY t.z_poradi, t.id'
    );
    $stmt->execute([$mapaId]);
    $radky = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM kolo_stav WHERE mapa_id = ?');
    $stmt->execute([$mapaId]);
    $koloStav = $stmt->fetch() ?: null;
    $aktivniTokenId = $koloStav ? (int)($koloStav['aktivni_token_id'] ?? 0) : 0;

    $tokeny = [];
    foreach ($radky as $t) {
        if (!$jePj && !(bool)$t['viditelny_hracum']) {
            continue;
        }
        $jePostava = $t['typ_entity'] === 'postava';
        $jeNestvura = $t['typ_entity'] === 'nestvura_instance';
        $owned = $jePj || ($jePostava && (int)($t['vlastnik_ucet_id'] ?? 0) === (int)$user['id']);
        $nazevRucne = isset($t['nazev']) && $t['nazev'] !== null && $t['nazev'] !== '' ? (string)$t['nazev'] : null;
        $label = $jePostava ? (string)$t['postava_nazev'] : ($jeNestvura ? (string)$t['nazev_instance'] : '');
        if ($nazevRucne !== null) {
            $label = $nazevRucne;
        }
        $hp = $jePostava ? $t['postava_hp'] : ($jeNestvura ? $t['nestvura_hp'] : null);
        $maxHp = $jePostava ? $t['postava_max_hp'] : ($jeNestvura ? $t['nestvura_max_hp'] : null);
        $maxMag = $jePostava ? $t['postava_max_magenergie'] : null;
        $tokeny[] = [
            'id' => (int)$t['id'],
            'typ_entity' => (string)$t['typ_entity'],
            'entita_id' => (int)$t['entita_id'],
            'x' => (int)$t['x'],
            'y' => (int)$t['y'],
            'z' => (int)$t['z_poradi'],
            'viditelny' => (bool)$t['viditelny_hracum'],
            'label' => $label,
            'hp' => $hp !== null ? (int)$hp : null,
            'max_hp' => $maxHp !== null ? (int)$maxHp : null,
            'magenergie' => $jePostava && $t['postava_magenergie'] !== null ? (int)$t['postava_magenergie'] : null,
            'max_magenergie' => ($maxMag !== null && (int)$maxMag > 0) ? (int)$maxMag : null,
            'owned' => $owned,
            'na_tahu' => $jePj || !$koloStav || $aktivniTokenId === (int)$t['id'],
            'efekty' => [],
            'smer' => isset($t['smer']) ? (int)$t['smer'] : 0,
            'velikost' => isset($t['velikost_sahu']) ? (float)$t['velikost_sahu'] : 1.0,
            'hexu' => isset($t['hexu']) ? max(1, (int)$t['hexu']) : 1,
            'barva' => isset($t['barva']) && $t['barva'] !== null && $t['barva'] !== '' ? (string)$t['barva'] : null,
            'zamceno' => isset($t['zamceno']) ? (bool)$t['zamceno'] : false,
            'obrazek_id' => isset($t['obrazek_id']) && $t['obrazek_id'] !== null ? (int)$t['obrazek_id'] : null,
        ];
    }

    // Aktivní efekty všech entit s tokenem na téhle mapě — jeden dotaz.
    $entity = [];
    foreach ($tokeny as $t) {
        if ($t['typ_entity'] === 'postava' || $t['typ_entity'] === 'nestvura_instance') {
            $entity[$t['typ_entity'] . ':' . $t['entita_id']] = [$t['typ_entity'], $t['entita_id']];
        }
    }
    $efekty = [];
    if ($entity) {
        $where = implode(' OR ', array_fill(0, count($entity), '(ae.typ_entity = ? AND ae.entita_id = ?)'));
        $params = [];
        foreach ($entity as [$te, $ei]) {
            $params[] = $te;
            $params[] = $ei;
        }
        $stmt = $pdo->prepare(
            "SELECT ae.typ_entity, ae.entita_id, ae.zbyva_kol, e.nazev FROM aktivni_efekty ae
             JOIN efekty e ON e.id = ae.efekt_id WHERE $where"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $ae) {
            $efekty[$ae['typ_entity'] . ':' . $ae['entita_id']][] =
                $ae['nazev'] . ($ae['zbyva_kol'] !== null ? ' (' . $ae['zbyva_kol'] . ' kol)' : '');
        }
    }
    foreach ($tokeny as &$t) {
        $t['efekty'] = $efekty[$t['typ_entity'] . ':' . $t['entita_id']] ?? [];
    }
    unset($t);

    // Pořadí tahů: hráč vidí jen tokeny, které na mapě vidí (skrytý token
    // PJ nesmí prozradit ani přes iniciativu).
    $kolo = null;
    $iniciativa = [];
    $ucastnici = [];
    $vyzvy = [];
    if ($koloStav && dracak_vtt_tokeny_funkce()['souboj']) {
        // Souboj s fázemi (migrace 0085): hráč nevidí iniciativu a úmysly
        // nestvůr ani postav ovládaných PJ.
        $sb = dracak_vtt_souboj_klient($pdo, $user, $mapa, $jePj, dracak_vtt_souboj_nacti($pdo, $mapaId));
        $kolo = $sb['kolo'];
        $iniciativa = $sb['iniciativa'];
        $ucastnici = $sb['ucastnici'];
        $vyzvy = $sb['vyzvy'];
    } elseif ($koloStav) {
        $viditelneId = array_flip(array_column($tokeny, 'id'));
        foreach (dracak_vtt_iniciativa_poradi($pdo, $mapaId) as $r) {
            if (!$jePj && !isset($viditelneId[(int)$r['token_id']])) {
                continue;
            }
            $iniciativa[] = [
                'token_id' => (int)$r['token_id'],
                'label' => (string)($r['label'] ?? '?'),
                'hod' => (int)$r['hod'],
                'modifikator' => (int)$r['modifikator'],
                'vysledek' => (int)$r['vysledek'],
                'akce_zbyvajici' => (int)$r['akce_zbyvajici'],
                'akce_celkem' => (int)$r['akce_celkem'],
            ];
        }
        $kolo = [
            'cislo_kola' => (int)$koloStav['cislo_kola'],
            'aktivni_token_id' => $aktivniTokenId ?: null,
        ];
    }

    // Postavy, které ještě nemají na téhle mapě token (PJ všechny ze světa,
    // hráč jen své).
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nazev FROM postavy p
         WHERE p.svet_id = ? AND p.id NOT IN (SELECT entita_id FROM tokeny WHERE mapa_id = ? AND typ_entity = "postava")
         ' . ($jePj ? '' : 'AND p.vlastnik_ucet_id = ?') . '
         ORDER BY p.nazev'
    );
    $stmt->execute($jePj ? [(int)$mapa['svet_id'], $mapaId] : [(int)$mapa['svet_id'], $mapaId, (int)$user['id']]);
    $volnePostavy = $stmt->fetchAll();

    return [
        'posledni_udalost_id' => $posledniUdalostId,
        'tokeny' => $tokeny,
        'kolo' => $kolo,
        'iniciativa' => $iniciativa,
        'ucastnici' => $ucastnici,
        'vyzvy' => $vyzvy,
        'kresby' => dracak_vtt_kresby_pro_mapu($pdo, $mapaId, (int)$user['id'], $jePj),
        'volne_postavy' => array_map(fn($p) => ['id' => (int)$p['id'], 'nazev' => (string)$p['nazev']], $volnePostavy),
    ];
}
