<?php
declare(strict_types=1);

// Vidění a světlo (docs/videni-a-svetlo-navrh.md; rozhodnutí uživatele 2026-11-02).
//
// Mapa má osvětlení „den“ (výchozí, nic se nemění — platí dosavadní mlha války) nebo „tma“. Ve tmě hráč vidí jen to, co je ZROVNA osvětlené
// a co vidí jeho postava (paprsek přes zdi a zavřené dveře); co už viděl, zůstává jako ztlumená paměť (bitmapa mlhy z includes/vtt_mlha.php),
// a nestvůry a cizí tokeny mimo dohled hráči nevidí. Družina (postavy hráčů) se vidí navzájem vždycky, ať je kolem světlo nebo ne.
//
// Zdrojem světla je token (svetlo_sahy: pochodeň 6, lucerna 8 sáhů… — čísla z knihy, h1685 b11183–b11185). Počítá se po buňkách gridu jako
// dosavadní mlha, bez plného viditelnostního polygonu:
//   1. osvětlené buňky = buňka v dosahu zdroje světla, kam od světla vede volný paprsek (zdi, zavřené dveře),
//   2. vidět teď = osvětlená buňka, kam vede volný paprsek od některého tokenu hráče, plus buňka, na které hráčův token stojí,
//   3. infravidění (tokeny.videni_infra_sahy) ukáže tokeny s teplou krví v dosahu a na přímý výhled — ne mapu,
//   4. zóny tmy (kouzla Tma a Temnota) potlačují světlo a infravidění; viz DRACAK_VTT_ZONY_TMY.
// Kdo smí vidět co, rozhoduje server; klient jen kreslí (mlha) a dostává jen tokeny, které smí.

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_mlha.php';

// Předvolby světel z knihy (h1685 „Světlo“, hůl b4601, kouzlo Světlo b3596): klíč => [název, dosah v sázích]. Kouzlo Světlo a vlastní dosah se zadávají ručně
// (kouzlo: 2 magy za sáh poloměru, tedy dosah = magy ÷ 2; svíčka v knize dosah nemá).
const DRACAK_VTT_SVETLO_PREDVOLBY = [
    'pochoden' => ['Pochodeň', 6.0],
    'lucerna' => ['Lucerna', 8.0],
    'lucerna_pul' => ['Lucerna (poloviční spotřeba)', 4.0],
    'lucerna_ctvrt' => ['Lucerna (čtvrtinová spotřeba)', 2.0],
    'hul_pochoden' => ['Čarodějova hůl (jako pochodeň)', 6.0],
    'hul_lucerna' => ['Čarodějova hůl (jako lucerna)', 8.0],
];

// Stavy osvětlení mapy (mapy.osvetleni, VARCHAR(8)): klíč => [název, živé vidění?, noční vidění funguje?]. Stupnice podle Lektvaru sovích očí (h3243 b3244–b3249; uživatel
// 2026-11-03: „tmu na 5“) od nejtmavší: naprostá tma, hustá tma, tma, řídká tma, pološero; k tomu „den“ (dosavadní mlha války).
// Živé vidění (hráč vidí jen osvětlené místo): všechny stupně tmy kromě pološera — soumrak je dost světla, aby se vidělo jako ve dne (mlha jako dosud).
// Noční vidění (lupič se Zlepšením zraku, h630 b5663: „potřebuje jen nepatrně světla, s absolutní tmou v podzemí či v kouzlem vytvořené tmě nepomáhá“) funguje
// v husté tmě, ve tmě i v řídké tmě, v naprosté tmě ne. Kniha u žádného stupně neříká, jak daleko se vidí — dosah nočního vidění zadává PJ u tvora; stupně se liší
// tím, co je z knihy známo (noční vidění, postihy lektvaru sovích očí: hustá tma −1 k boji, tma 0, řídká tma ostře, pološero −1).
const DRACAK_VTT_OSVETLENI = [
    'den' => ['Světlo', false, false],
    'polosero' => ['Pološero (soumrak, úsvit)', false, false],
    'ridka' => ['Řídká tma (měsíc v úplňku)', true, true],
    'noc' => ['Tma (slabě svítí hvězdy)', true, true],
    'husta' => ['Hustá tma (bezvětrná noc, hustý les)', true, true],
    'tma' => ['Naprostá tma (podzemí, kouzelná tma)', true, false],
];

// Zóny tmy (kouzla) — token se svetlo_typ 'tma' nebo 'temnota' NENÍ zdroj světla, ale kruh o poloměru svetlo_sahy (sáhů), který světlo a infravidění potlačuje.
// Bez nových sloupců (žádné ruční SQL): typ i poloměr se drží ve stejných dvou sloupcích jako světlo, jen se v dracak_vtt_videni_zdroje() vynechávají.
//   'tma'     — Tma (Obscurum, h318 b3600–b3601): 4 magy za sáh poloměru, dokonalá tma. Uvnitř nic nesvítí (zdroj světla uvnitř nesvítí vůbec)
//               a trpasličí infravidění nefunguje (ani zevnitř ven, ani zvenku dovnitř). Variantu za 6 magů (šero jako od malé pochodně, infravidění
//               funguje) kniha nezměřila — dosah „malé pochodně“ v ní není — proto se zóna nezakládá, PJ dá na střed světlo s vlastním dosahem.
//   'temnota' — Temnota druida (h193 b1936–b1939): okruh 300 sáhů, pohlcuje veškeré světlo (pochodeň není vidět déle než 30 coulů = 0,3 sáhu,
//               1 sáh = 100 coulů) a infravidění v ní má desetinový dosah.
const DRACAK_VTT_ZONY_TMY = ['tma', 'temnota'];
const DRACAK_VTT_TEMNOTA_SVETLO_SAHY = 0.3;
const DRACAK_VTT_TEMNOTA_INFRA_PODIL = 0.1;

// Boj ve tmě (h1613 b10738 „Útok na neviditelného protivníka“, lupič b5663): postih k hodu na útok i na obranu pro toho, kdo protivníka nevidí.
// klíč => [text oprav, postih]. nevidi −5 (bez zpráv o protivníkovi), tusi −4 (mlhavá představa, kde stojí), nocni −2 (lupič s nočním viděním ve tmě).
// Zasáhne-li útočník s tímto postihem, zranění se počítá, jako by postih nebyl (viz dracak_vtt_utok_vyhodnot).
const DRACAK_VTT_TMA_POSTIHY = [
    'nevidi' => ['nevidí protivníka (tma)', -5],
    'tusi' => ['tuší, kde protivník stojí (tma)', -4],
    'nocni' => ['noční vidění (boj ve tmě)', -2],
];

// Jsou v DB všechny sloupce vidění (migrace 0107)? Bez nich se vidění nepoužívá.
function dracak_vtt_videni_funkce(PDO $pdo): bool
{
    static $cache = [];
    $klic = spl_object_id($pdo);
    if (!isset($cache[$klic])) {
        try {
            $cache[$klic] = (int)$pdo->query(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                   AND ((TABLE_NAME = "mapy" AND COLUMN_NAME = "osvetleni")
                     OR (TABLE_NAME = "tokeny" AND COLUMN_NAME IN ("svetlo_sahy", "svetlo_typ", "videni_infra_sahy", "studeny")))'
            )->fetchColumn() === 5;
        } catch (Throwable $e) {
            $cache[$klic] = false;
        }
    }
    return $cache[$klic];
}

// Je v DB sloupec nočního vidění (migrace 0108)? Bez něj stav „noc“ funguje jako „tma“ a noční vidění tvorů se nepočítá.
function dracak_vtt_videni_noc_funkce(PDO $pdo): bool
{
    static $cache = [];
    $klic = spl_object_id($pdo);
    if (!isset($cache[$klic])) {
        try {
            $cache[$klic] = (int)$pdo->query(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "tokeny" AND COLUMN_NAME = "videni_noc_sahy"'
            )->fetchColumn() === 1;
        } catch (Throwable $e) {
            $cache[$klic] = false;
        }
    }
    return $cache[$klic];
}

function dracak_vtt_videni_mapa(PDO $pdo, int $mapaId): ?array
{
    $q = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
    $q->execute([$mapaId]);
    return $q->fetch() ?: null;
}

// Platí na téhle mapě živé vidění? Jen mapa „Noc“ nebo „Tma“ se zapnutou mlhou a s mřížkou (bez ní nejsou buňky, podle kterých se vidění počítá).
function dracak_vtt_videni_aktivni(PDO $pdo, array $mapa): bool
{
    return dracak_vtt_videni_funkce($pdo)
        && (DRACAK_VTT_OSVETLENI[$mapa['osvetleni'] ?? 'den'][1] ?? false)
        && !empty($mapa['mlha_aktivni'])
        && (int)($mapa['grid_velikost_px'] ?? 0) > 0
        && (int)($mapa['sirka_px'] ?? 0) > 0
        && (int)($mapa['vyska_px'] ?? 0) > 0;
}

// Zdi, které blokují výhled (zavřené dveře ano, otevřené ne), s obalovou obdélníkem pro rychlé vyřazení: [x1, y1, x2, y2, minX, maxX, minY, maxY].
function dracak_vtt_videni_zdi(PDO $pdo, int $mapaId): array
{
    $q = $pdo->prepare('SELECT x1, y1, x2, y2 FROM zdi WHERE mapa_id = ? AND blokuje_vystrel = 1' . dracak_vtt_zdi_bez_otevrenych_dveri($pdo));
    $q->execute([$mapaId]);
    $zdi = [];
    foreach ($q->fetchAll() as $z) {
        $x1 = (float)$z['x1']; $y1 = (float)$z['y1']; $x2 = (float)$z['x2']; $y2 = (float)$z['y2'];
        $zdi[] = [$x1, $y1, $x2, $y2, min($x1, $x2), max($x1, $x2), min($y1, $y2), max($y1, $y2)];
    }
    return $zdi;
}

// Blokuje výhled z A do B nějaká zeď? (stejný průsečík úseček jako dracak_vtt_los_blokovana_zdi, jen v paměti a s vyřazením podle obalu)
function dracak_vtt_videni_blokovano(array $zdi, float $ax, float $ay, float $bx, float $by): bool
{
    $minX = min($ax, $bx) - 1e-6; $maxX = max($ax, $bx) + 1e-6;
    $minY = min($ay, $by) - 1e-6; $maxY = max($ay, $by) + 1e-6;
    foreach ($zdi as $z) {
        if ($z[5] < $minX || $z[4] > $maxX || $z[7] < $minY || $z[6] > $maxY) {
            continue;
        }
        if (dracak_vtt_useky_se_protinaji($ax, $ay, $bx, $by, $z[0], $z[1], $z[2], $z[3])) {
            return true;
        }
    }
    return false;
}

// Zdroje světla na mapě: [x, y, r (px)]. Svítí i skrytý token (např. pochodeň na zdi, kterou hráči nemají vidět jako předmět).
function dracak_vtt_videni_zdroje(PDO $pdo, int $mapaId, float $pxSah): array
{
    $q = $pdo->prepare('SELECT x, y, svetlo_sahy FROM tokeny WHERE mapa_id = ? AND svetlo_sahy IS NOT NULL AND svetlo_sahy > 0
                          AND (svetlo_typ IS NULL OR svetlo_typ NOT IN ("tma", "temnota"))');
    $q->execute([$mapaId]);
    $zdroje = [];
    foreach ($q->fetchAll() as $r) {
        $zdroje[] = ['x' => (float)$r['x'], 'y' => (float)$r['y'], 'r' => (float)$r['svetlo_sahy'] * $pxSah];
    }
    return $zdroje;
}

// Zóny tmy na mapě: [x, y, r (px), typ]. Zóna působí i jako skrytý token (PJ ji obvykle před hráči skrývá).
function dracak_vtt_videni_zony(PDO $pdo, int $mapaId, float $pxSah): array
{
    $q = $pdo->prepare('SELECT x, y, svetlo_sahy, svetlo_typ FROM tokeny WHERE mapa_id = ? AND svetlo_sahy IS NOT NULL AND svetlo_sahy > 0
                          AND svetlo_typ IN ("tma", "temnota")');
    $q->execute([$mapaId]);
    $zony = [];
    foreach ($q->fetchAll() as $r) {
        $zony[] = ['x' => (float)$r['x'], 'y' => (float)$r['y'], 'r' => (float)$r['svetlo_sahy'] * $pxSah, 'typ' => (string)$r['svetlo_typ']];
    }
    return $zony;
}

// Leží místo [x, y] v nějaké zóně (daného typu)?
function dracak_vtt_videni_v_zone(array $zony, float $x, float $y, string $typ): bool
{
    foreach ($zony as $z) {
        if ($z['typ'] === $typ && hypot($x - $z['x'], $y - $z['y']) <= $z['r']) {
            return true;
        }
    }
    return false;
}

// Tokeny, kterýma hráč vidí: postavy, jejichž vlastníkem je jeho účet.
function dracak_vtt_videni_divaci(PDO $pdo, int $mapaId, int $ucetId): array
{
    $q = $pdo->prepare(
        'SELECT t.id, t.x, t.y, t.videni_infra_sahy' . (dracak_vtt_videni_noc_funkce($pdo) ? ', t.videni_noc_sahy' : '') . ' FROM tokeny t
         JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
         WHERE t.mapa_id = ? AND p.vlastnik_ucet_id = ?'
    );
    $q->execute([$mapaId, $ucetId]);
    return $q->fetchAll();
}

// Spočítá vidění jednoho hráče na mapě „Tma“. Vrací null, když vidění na mapě neplatí.
// ['rozmery', 'osvetleno' (bitmapa), 'videno' (bitmapa), 'divaci', 'zdi', 'px_sah', 'zony']
function dracak_vtt_videni_vypocet(PDO $pdo, array $mapa, int $ucetId): ?array
{
    if (!dracak_vtt_videni_aktivni($pdo, $mapa)) {
        return null;
    }
    $roz = dracak_vtt_mlha_rozmery($mapa);
    if ($roz['sloupcu'] <= 0 || $roz['radku'] <= 0) {
        return null;
    }
    $mapaId = (int)$mapa['id'];
    $pxSah = dracak_vtt_px_na_sah($mapa) ?? (float)$roz['bunka_px'];
    $zdi = dracak_vtt_videni_zdi($pdo, $mapaId);
    $n = $roz['sloupcu'] * $roz['radku'];

    // 1. osvětlené buňky
    $osvetleno = str_repeat("\x00", $n);
    $osvetleneSeznam = [];
    $zony = dracak_vtt_videni_zony($pdo, $mapaId, $pxSah);
    $rTemnota = DRACAK_VTT_TEMNOTA_SVETLO_SAHY * $pxSah;
    foreach (dracak_vtt_videni_zdroje($pdo, $mapaId, $pxSah) as $z) {
        $r = $z['r'];
        // Zdroj uvnitř Tmy nesvítí vůbec, zdroj uvnitř Temnoty svítí nejdál 0,3 sáhu.
        if ($zony && dracak_vtt_videni_v_zone($zony, $z['x'], $z['y'], 'tma')) {
            continue;
        }
        if ($zony && dracak_vtt_videni_v_zone($zony, $z['x'], $z['y'], 'temnota')) {
            $r = min($r, $rTemnota);
        }
        foreach (dracak_vtt_mlha_bunky_v_obdelniku($roz, $z['x'] - $r, $z['y'] - $r, $z['x'] + $r, $z['y'] + $r) as $b) {
            $idx = dracak_vtt_mlha_bunka_idx($roz, $b['row'], $b['col']);
            if ($idx === null || $osvetleno[$idx] === "\x01") {
                continue;
            }
            $vzdal = hypot($b['x'] - $z['x'], $b['y'] - $z['y']);
            if ($vzdal > $r) {
                continue;
            }
            // Buňka uvnitř Tmy se neosvětlí nikdy, uvnitř Temnoty jen do 0,3 sáhu od zdroje.
            if ($zony && (dracak_vtt_videni_v_zone($zony, $b['x'], $b['y'], 'tma')
                || ($vzdal > $rTemnota && dracak_vtt_videni_v_zone($zony, $b['x'], $b['y'], 'temnota')))) {
                continue;
            }
            if (dracak_vtt_videni_blokovano($zdi, $z['x'], $z['y'], $b['x'], $b['y'])) {
                continue;
            }
            $osvetleno[$idx] = "\x01";
            $osvetleneSeznam[] = [$idx, $b['x'], $b['y']];
        }
    }

    // 2. vidět teď
    $videno = str_repeat("\x00", $n);
    $divaci = dracak_vtt_videni_divaci($pdo, $mapaId, $ucetId);
    foreach ($divaci as $d) {
        $dx = (float)$d['x']; $dy = (float)$d['y'];
        $vlastni = dracak_vtt_mlha_bunka_index($roz, $dx, $dy);
        if ($vlastni !== null) {
            $i = dracak_vtt_mlha_bunka_idx($roz, $vlastni['row'], $vlastni['col']);
            if ($i !== null) {
                $videno[$i] = "\x01";
            }
        }
        foreach ($osvetleneSeznam as [$idx, $bx, $by]) {
            if ($videno[$idx] === "\x01") {
                continue;
            }
            if (!dracak_vtt_videni_blokovano($zdi, $dx, $dy, $bx, $by)) {
                $videno[$idx] = "\x01";
            }
        }
        // Noční vidění (stupně tmy, kde funguje — viz DRACAK_VTT_OSVETLENI): tvor vidí všechny buňky do svého dosahu, kam vede volný paprsek, i bez světla. Uvnitř kouzelné tmy (Tma, Temnota) nepomáhá
        // ani zevnitř, ani dovnitř (b5663: „v kouzlem vytvořené tmě mu noční vidění nepomáhá“).
        $nocDosah = (DRACAK_VTT_OSVETLENI[$mapa['osvetleni'] ?? 'den'][2] ?? false) ? (int)($d['videni_noc_sahy'] ?? 0) : 0;
        if ($nocDosah > 0 && !dracak_vtt_videni_v_zone($zony, $dx, $dy, 'tma') && !dracak_vtt_videni_v_zone($zony, $dx, $dy, 'temnota')) {
            $r = $nocDosah * $pxSah;
            foreach (dracak_vtt_mlha_bunky_v_obdelniku($roz, $dx - $r, $dy - $r, $dx + $r, $dy + $r) as $b) {
                $idx = dracak_vtt_mlha_bunka_idx($roz, $b['row'], $b['col']);
                if ($idx === null || $videno[$idx] === "\x01" || hypot($b['x'] - $dx, $b['y'] - $dy) > $r) {
                    continue;
                }
                if ($zony && (dracak_vtt_videni_v_zone($zony, $b['x'], $b['y'], 'tma') || dracak_vtt_videni_v_zone($zony, $b['x'], $b['y'], 'temnota'))) {
                    continue;
                }
                if (!dracak_vtt_videni_blokovano($zdi, $dx, $dy, $b['x'], $b['y'])) {
                    $videno[$idx] = "\x01";
                }
            }
        }
    }
    return ['rozmery' => $roz, 'osvetleno' => $osvetleno, 'videno' => $videno, 'divaci' => $divaci, 'zdi' => $zdi, 'px_sah' => $pxSah, 'zony' => $zony];
}

// Přidá vidět teď do trvalé paměti hráče (mlha_valky) a vrátí výpočet. Volá se při pohybu hráčova tokenu a při načtení mlhy.
function dracak_vtt_videni_zaznamenej(PDO $pdo, array $mapa, int $ucetId): ?array
{
    $v = dracak_vtt_videni_vypocet($pdo, $mapa, $ucetId);
    if ($v === null) {
        return null;
    }
    $stav = dracak_vtt_mlha_nacti($pdo, $mapa, $ucetId);
    $pamet = $stav['bitmapa'];
    $zmeneno = false;
    $n = strlen($v['videno']);
    for ($i = 0; $i < $n; $i++) {
        if ($v['videno'][$i] === "\x01" && ($pamet[$i] ?? "\x01") !== "\x01") {
            $pamet[$i] = "\x01";
            $zmeneno = true;
        }
    }
    if ($zmeneno) {
        dracak_vtt_mlha_uloz($pdo, (int)$mapa['id'], $ucetId, $pamet, $v['rozmery']);
    }
    return $v;
}

// Vidí hráč (podle výpočtu) token na místě [x, y]? $token: typ_entity, x, y, studeny, a pro družinu příznak 'druzina'.
function dracak_vtt_videni_token_viden(array $v, array $token): bool
{
    if (!empty($token['druzina'])) {
        return true;
    }
    $x = (float)$token['x']; $y = (float)$token['y'];
    $bunka = dracak_vtt_mlha_bunka_index($v['rozmery'], $x, $y);
    if ($bunka !== null) {
        $idx = dracak_vtt_mlha_bunka_idx($v['rozmery'], $bunka['row'], $bunka['col']);
        if ($idx !== null && $v['videno'][$idx] === "\x01") {
            return true;
        }
    }
    // Infravidění: tvor s teplou krví v dosahu a na přímý výhled. Obyčejné předměty a značky (obecný token) ani studenokrevní tvorové se tak nevidí.
    if (in_array($token['typ_entity'], ['postava', 'nestvura_instance'], true) && empty($token['studeny'])) {
        foreach ($v['divaci'] as $d) {
            $dosah = (int)($d['videni_infra_sahy'] ?? 0);
            if ($dosah <= 0) {
                continue;
            }
            $dx = (float)$d['x']; $dy = (float)$d['y'];
            $dosahPx = $dosah * $v['px_sah'];
            $zony = $v['zony'] ?? [];
            if ($zony) {
                // Tma (Obscurum) infravidění vyřazuje; v Temnotě má jen desetinový dosah. Stačí, aby v zóně stál pozorovatel nebo cíl.
                if (dracak_vtt_videni_v_zone($zony, $dx, $dy, 'tma') || dracak_vtt_videni_v_zone($zony, $x, $y, 'tma')) {
                    continue;
                }
                if (dracak_vtt_videni_v_zone($zony, $dx, $dy, 'temnota') || dracak_vtt_videni_v_zone($zony, $x, $y, 'temnota')) {
                    $dosahPx *= DRACAK_VTT_TEMNOTA_INFRA_PODIL;
                }
            }
            if (hypot($x - $dx, $y - $dy) <= $dosahPx
                && !dracak_vtt_videni_blokovano($v['zdi'], $dx, $dy, $x, $y)) {
                return true;
            }
        }
    }
    return false;
}

// Neviditelní tvorové na mapě (aktivní efekt „Neviditelnost…“, lektvar a kouzlo neviditelnosti, h3237 b3237–b3241): token_id => true. Neviditelný je „absolutně nezjistitelný
// žádným vizuálním prostředkem (zrakem, infravidění atd.)“, takže ho hráč nevidí ani na osvětlené mapě; vlastník své postavy ji vidí vždycky (viz skryte_tokeny).
// Kdy neviditelnost končí (útok, řeč, psychická schopnost — b3238), rozhoduje PJ: efekt prostě sundá.
function dracak_vtt_videni_neviditelne_tokeny(PDO $pdo, int $mapaId): array
{
    // Jeden dotaz na požadavek a mapu (mapa_stav i filtr tokenů ho volají za sebou); efekty se v rámci jednoho požadavku na čtení nemění.
    static $cache = [];
    if (isset($cache[$mapaId])) {
        return $cache[$mapaId];
    }
    try {
        $q = $pdo->prepare(
            'SELECT DISTINCT t.id FROM tokeny t
             JOIN aktivni_efekty ae ON ae.typ_entity = t.typ_entity AND ae.entita_id = t.entita_id
             JOIN efekty e ON e.id = ae.efekt_id
             WHERE t.mapa_id = ? AND e.nazev LIKE "Neviditelnost%"'
        );
        $q->execute([$mapaId]);
        return $cache[$mapaId] = array_fill_keys(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)), true);
    } catch (Throwable $e) {
        return [];
    }
}

// Id tokenů na mapě, které hráč NEVIDÍ: neviditelní tvorové (kromě jeho vlastních postav) a — na mapě „Noc“ / „Tma“ — to, co mu nedovolí vidění.
// Vlastní tokeny a družina se viděním nikdy neskrývají.
function dracak_vtt_videni_skryte_tokeny(PDO $pdo, array $mapa, int $ucetId): array
{
    $nevidi = [];
    $neviditelne = dracak_vtt_videni_neviditelne_tokeny($pdo, (int)$mapa['id']);
    if ($neviditelne) {
        $q = $pdo->prepare('SELECT t.id FROM tokeny t JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava" WHERE t.mapa_id = ? AND p.vlastnik_ucet_id = ?');
        $q->execute([(int)$mapa['id'], $ucetId]);
        $vlastni = array_flip(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)));
        foreach (array_keys($neviditelne) as $id) {
            if (!isset($vlastni[$id])) {
                $nevidi[$id] = true;
            }
        }
    }
    $v = dracak_vtt_videni_vypocet($pdo, $mapa, $ucetId);
    if ($v === null) {
        return $nevidi;
    }
    $pjId = (int)($pdo->query('SELECT pj_ucet_id FROM svet WHERE id = ' . (int)$mapa['svet_id'])->fetchColumn() ?: 0);
    $q = $pdo->prepare(
        'SELECT t.id, t.typ_entity, t.x, t.y, t.studeny, p.vlastnik_ucet_id
         FROM tokeny t LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
         WHERE t.mapa_id = ?'
    );
    $q->execute([(int)$mapa['id']]);
    $skryte = $nevidi;
    foreach ($q->fetchAll() as $t) {
        // Družina = postava, kterou vlastní někdo jiný než PJ světa (NPC postavy PJ do družiny nepatří).
        $t['druzina'] = $t['typ_entity'] === 'postava' && (int)($t['vlastnik_ucet_id'] ?? 0) !== $pjId && (int)($t['vlastnik_ucet_id'] ?? 0) > 0;
        if (!dracak_vtt_videni_token_viden($v, $t)) {
            $skryte[(int)$t['id']] = true;
        }
    }
    return $skryte;
}

// Nevidí ve tmě pozorovatel (token) cíl (token)? Vrací 'nevidi' (výchozí postih −5), 'nocni' (−2, lupič s nočním viděním v noci), nebo null — vidí, nebo se to nedá zjistit.
// Automaticky se počítá jen za postavy hráčů (vidí jejich účtem, tedy světlem družiny a infraviděním); za nestvůry a NPC rozhoduje PJ v okně výzvy
// (kniha neříká, kdo z nestvůr vidí ve tmě). Na mapě „Světlo“ se nic nezjišťuje.
function dracak_vtt_videni_tma_volba(PDO $pdo, array $mapa, int $pozorovatelTokenId, int $cilTokenId): ?string
{
    $plna = dracak_vtt_videni_mapa($pdo, (int)$mapa['id']);
    if ($plna === null) {
        return null;
    }
    // Neviditelný cíl (b3237–b3239: platí pravidla pro boj s neviditelným protivníkem, h1613 b10738) se nevidí ani na osvětlené mapě — postih −5, jako když není vidět ve tmě.
    $neviditelne = dracak_vtt_videni_neviditelne_tokeny($pdo, (int)$plna['id']);
    $cilNeviditelny = isset($neviditelne[$cilTokenId]) && !isset($neviditelne[$pozorovatelTokenId]);
    if (!$cilNeviditelny && !dracak_vtt_videni_aktivni($pdo, $plna)) {
        return null;
    }
    $q = $pdo->prepare(
        'SELECT t.id, t.typ_entity, t.x, t.y, t.studeny, p.vlastnik_ucet_id FROM tokeny t
         LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava" WHERE t.mapa_id = ? AND t.id IN (?, ?)'
    );
    $q->execute([(int)$plna['id'], $pozorovatelTokenId, $cilTokenId]);
    $radky = [];
    foreach ($q->fetchAll() as $r) {
        $radky[(int)$r['id']] = $r;
    }
    if (!isset($radky[$pozorovatelTokenId], $radky[$cilTokenId])) {
        return null;
    }
    $pj = (int)($pdo->query('SELECT pj_ucet_id FROM svet WHERE id = ' . (int)$plna['svet_id'])->fetchColumn() ?: 0);
    $pozorovatel = $radky[$pozorovatelTokenId];
    $ucet = (int)($pozorovatel['vlastnik_ucet_id'] ?? 0);
    if ($pozorovatel['typ_entity'] !== 'postava' || $ucet <= 0 || $ucet === $pj) {
        return null;
    }
    if ($cilNeviditelny) {
        return 'nevidi';
    }
    $v = dracak_vtt_videni_vypocet($pdo, $plna, $ucet);
    if ($v === null) {
        return null;
    }
    $cil = $radky[$cilTokenId];
    $cil['druzina'] = $cil['typ_entity'] === 'postava' && (int)($cil['vlastnik_ucet_id'] ?? 0) > 0 && (int)$cil['vlastnik_ucet_id'] !== $pj;
    if (dracak_vtt_videni_token_viden($v, $cil)) {
        return null;
    }
    // Lupič s nočním viděním (b5663) bojuje ve tmě s postihem −2 místo −5 a ví, kde protivník stojí — ale jen v „noci“; v absolutní tmě ani v kouzelné tmě mu zrak nepomáhá.
    if (DRACAK_VTT_OSVETLENI[$plna['osvetleni'] ?? 'den'][2] ?? false) {
        foreach ($v['divaci'] as $d) {
            if ((int)$d['id'] === $pozorovatelTokenId && (int)($d['videni_noc_sahy'] ?? 0) > 0
                && !dracak_vtt_videni_v_zone($v['zony'], (float)$pozorovatel['x'], (float)$pozorovatel['y'], 'tma')
                && !dracak_vtt_videni_v_zone($v['zony'], (float)$pozorovatel['x'], (float)$pozorovatel['y'], 'temnota')
                && !dracak_vtt_videni_v_zone($v['zony'], (float)$cil['x'], (float)$cil['y'], 'tma')
                && !dracak_vtt_videni_v_zone($v['zony'], (float)$cil['x'], (float)$cil['y'], 'temnota')) {
                return 'nocni';
            }
        }
    }
    return 'nevidi';
}
