<?php
declare(strict_types=1);

// Osobní deník postavy — výpočty a sběr dat pro hra/denik.php a tiskovou
// verzi (docs/zadani-osobni-denik-design.md). Vše podle
// content/pravidla-hrac.html: kolonky deníku h1690, útok h1610/h1615,
// obrana h1611/h1616, bojeschopnost h1610, postřeh h1683, rodové zbraně
// h1609, zkušenost a úroveň h109/h110, nosnost a naložení h1681.
// Kde pravidla (nebo data) něco neurčují, funkce vrací null — nedomýšlí.
require_once __DIR__ . '/vtt_pohyblivost.php';
require_once __DIR__ . '/vtt_predmety.php';
require_once __DIR__ . '/vtt_kontejnery.php';

// TABULKA RODOVÝCH ZBRANÍ (h1609, b: "rodová zbraň dává postavě +1 k
// útočnému číslu, pokud ji používá"). Klíč = základní rasa; trpasličí
// klany a varianty ji dědí od rodiče (rodic_rasa_id). Homebrew rasy mají
// rodovou zbraň v řádku "rodová zbraň:" svého bloku (h37 Barnové b169, h42
// Caelové b198, h47 Furan b231, h51 Gnollové b276, h56 Kobold b304, h61
// Magiliw b330, h66 Naro'shai b360, h72 Skaven b395, h77 Tauren b420, h86
// Tiefling b460). Hodnoty jsou názvy zbraně v katalogu (malými písmeny).
const DRACAK_VTT_RODOVA_ZBRAN = [
    'Hobit' => ['lehká kuše'],
    'Kudůk' => ['sekera'],
    'Trpaslík' => ['válečná sekera'],
    'Elf' => ['dlouhý luk'],
    'Člověk' => ['široký meč'],
    'Barbar' => ['meč bastard', 'bastard'],
    'Kroll' => ['těžký kyj'],
    'Barnové' => ['kopí'],
    'Caelové' => ['dýka'],
    'Furan' => ['hůl okovaná'],
    'Gnollové' => ['hůl okovaná'],
    'Kobold' => ['krátký meč'],
    'Magiliw' => ['prak'],
    "Naro'shai" => ['šavle'],
    'Skaven' => ['krátký luk'],
    'Tauren' => ['sudlice'],
    'Tiefling' => ['palcát'],
];

// Velikost podle h1690 (kolonka Velikost): A hobit/kudůk/trpaslík, B
// elf/barbar/člověk, C kroll. Homebrew rasy mají v textu rozsah
// ("B-C"), proto se u nich bere hodnota z postavy (postavy.velikost).
const DRACAK_VTT_VELIKOST_RASY = [
    'Hobit' => 'A', 'Kudůk' => 'A', 'Trpaslík' => 'A',
    'Elf' => 'B', 'Barbar' => 'B', 'Člověk' => 'B',
    'Kroll' => 'C',
];

// Postřeh (h1683): na objekty = inteligence (%) a +5 % kroll/barbar/
// trpaslík; na mechanismy = inteligence/2 dolů a +5 % elf/hobit/kudůk.
const DRACAK_VTT_POSTREH_OBJEKTY_BONUS = ['Kroll', 'Barbar', 'Trpaslík'];
const DRACAK_VTT_POSTREH_MECHANISMY_BONUS = ['Elf', 'Hobit', 'Kudůk'];

// Existuje sloupec? Část sloupců přibývá ruční migrací (DDL), kterou web
// účet neprovede — deník proto musí fungovat i bez nich. Výsledek se
// drží po dobu requestu.
function dracak_vtt_ma_sloupec(PDO $pdo, string $tabulka, string $sloupec): bool
{
    static $cache = [];
    $klic = $tabulka . '.' . $sloupec;
    if (!array_key_exists($klic, $cache)) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$tabulka, $sloupec]);
        $cache[$klic] = (int)$stmt->fetchColumn() > 0;
    }
    return $cache[$klic];
}

// Hod "1k6+" — šestistěnná kostka s opakováním po šestce, výsledky se
// sčítají (content/pravidla-hrac.html, kap. Kostky: "1k6+ – hod šestistěnnou
// kostkou s opakováním po šestce"; používá se pro hod na útok i obranu,
// h1610/h1611). Vrací jednotlivé hody; pojistka na 30 hodů je jen proti
// nekonečné smyčce, ne pravidlo.
function dracak_vtt_hod_k6_plus(): array
{
    $hody = [];
    do {
        $h = random_int(1, 6);
        $hody[] = $h;
    } while ($h === 6 && count($hody) < 30);
    return $hody;
}

// Základní rasa postavy — projde rodic_rasa_id nahoru (klany -> trpaslík).
// Vrací název základní rasy, nebo null.
function dracak_vtt_zakladni_rasa(PDO $pdo, ?int $rasaId): ?string
{
    $stmt = $pdo->prepare('SELECT nazev, rodic_rasa_id FROM rasy WHERE id = ?');
    for ($i = 0; $i < 5 && $rasaId; $i++) {
        $stmt->execute([$rasaId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (isset(DRACAK_VTT_VELIKOST_RASY[$row['nazev']]) || $row['rodic_rasa_id'] === null) {
            return $row['nazev'];
        }
        $rasaId = (int)$row['rodic_rasa_id'];
    }
    return null;
}

// Bojeschopnost (h1610 TABULKA BOJESCHOPNOSTI): mez vyřazení = max. životů
// děleno 4/6/8 podle stupně odolnosti (1–5 / 6–11 / 12–16), od 17 jen 1
// život; vždy aspoň 1, zaokrouhluje se aritmeticky (12:8 = 1,5 -> 2).
// Postih k útoku i obraně, má-li postava MÉNĚ než třetinu životů: −3 / −2
// / −1 / 0 podle stejného pásma odolnosti.
function dracak_vtt_bojeschopnost(int $odolnost, int $maxHp, int $aktualniHp): array
{
    if ($odolnost <= 5) {
        $delitel = 4; $postih = -3;
    } elseif ($odolnost <= 11) {
        $delitel = 6; $postih = -2;
    } elseif ($odolnost <= 16) {
        $delitel = 8; $postih = -1;
    } else {
        $delitel = null; $postih = 0;
    }
    $mez = $delitel === null ? 1 : max(1, (int)floor($maxHp / $delitel + 0.5));
    $podTretinou = $maxHp > 0 && $aktualniHp * 3 < $maxHp;
    return [
        'mez_vyrazeni' => $mez,
        'vyrazena' => $maxHp > 0 && $aktualniHp <= $mez,
        'pod_tretinou' => $podTretinou,
        'postih_pod_tretinou' => $postih,
        'postih_aktualni' => $podTretinou ? $postih : 0,
    ];
}

// Postřeh (h1683): ['objekty' => %, 'mechanismy' => %].
function dracak_vtt_postrech(int $inteligence, ?string $zakladniRasa): array
{
    return [
        'objekty' => $inteligence + (in_array($zakladniRasa, DRACAK_VTT_POSTREH_OBJEKTY_BONUS, true) ? 5 : 0),
        'mechanismy' => intdiv($inteligence, 2) + (in_array($zakladniRasa, DRACAK_VTT_POSTREH_MECHANISMY_BONUS, true) ? 5 : 0),
    ];
}

// Je zbraň rodová zbraň dané rasy? Shoda názvu s předponou; varianty v
// závorce ("Sekera (jednoruční)") ano, vrhací a super těžké varianty ne
// (jiná zbraň).
function dracak_vtt_je_rodova_zbran(string $nazevZbrane, ?string $zakladniRasa): bool
{
    $rod = DRACAK_VTT_RODOVA_ZBRAN[$zakladniRasa] ?? null;
    if ($rod === null) {
        return false;
    }
    $n = mb_strtolower($nazevZbrane);
    if (preg_match('/vrhací|super těžk/u', $n)) {
        return false;
    }
    foreach ($rod as $prefix) {
        if ($n === $prefix || str_starts_with($n, $prefix . ' (')) {
            return true;
        }
    }
    return false;
}

// Útočná čísla zbraně (h1610, h1615): tváří v tvář = SZ + bonus za sílu,
// střelecký = SZ + bonus za obratnost (vrhací zbraň v boji tváří v tvář:
// opět síla); +1 rodová zbraň; nikdy méně než 0. Bez SZ (kouzelné a
// artefaktové zbraně) se ÚČ nepočítá (null).
//
// $zbran = řádek zbraně (sila_zbrane, dostrel_maly, kategorie_zbrane).
// $vrhaciVarianta = řádek vrhací varianty téže zbraně (katalog vede třeba
// "Dýka" a "Dýka (vrhací)" zvlášť), nebo null. Střelná zbraň (luk, kuše,
// prak) se tváří v tvář nepoužívá; zbraň s dostřelem bez uvedené kategorie
// (Arbalest…) má jen střelecké číslo — jak se chová tváří v tvář, pravidla
// neříkají.
function dracak_vtt_utocna_cisla(array $zbran, int $bonusSil, int $bonusObr, bool $rodova, ?array $vrhaciVarianta = null): array
{
    $bonusRod = $rodova ? 1 : 0;
    $uc = static fn(?int $sz, int $bonus) => $sz === null ? null : max(0, $sz + $bonus + $bonusRod);
    $sz = isset($zbran['sila_zbrane']) ? (int)$zbran['sila_zbrane'] : null;
    $kategorie = (string)($zbran['kategorie_zbrane'] ?? '');

    if ($zbran['dostrel_maly'] !== null) {
        return [
            'uc_tvar_v_tvar' => str_contains($kategorie, 'vrhaci') ? $uc($sz, $bonusSil) : null,
            'uc_strelecky' => $uc($sz, $bonusObr),
        ];
    }
    $szVrh = $vrhaciVarianta && $vrhaciVarianta['sila_zbrane'] !== null ? (int)$vrhaciVarianta['sila_zbrane'] : null;
    return [
        'uc_tvar_v_tvar' => $uc($sz, $bonusSil),
        'uc_strelecky' => $vrhaciVarianta ? $uc($szVrh, $bonusObr) : null,
    ];
}

// Zbraně z inventáře postavy s odvozenými čísly.
function dracak_vtt_zbrane_postavy(PDO $pdo, array $postava, int $bonusSil, int $bonusObr, ?string $zakladniRasa): array
{
    // sila_zbrane/delka_zbrane přibyly migrací 0071 (ruční DDL) — bez ní
    // zbraně fungují, jen bez ÚČ.
    $maSz = dracak_vtt_ma_sloupec($pdo, 'predmety', 'sila_zbrane');
    $szSql = $maSz ? 'p.sila_zbrane, p.delka_zbrane' : 'NULL AS sila_zbrane, NULL AS delka_zbrane';
    // u_sebe (zbraň při těle, includes/vtt_zbrane_u_sebe.php) přibyl ruční DDL; bez něj je u sebe vše (jen se nerozlišuje).
    $uSebeSql = dracak_vtt_ma_sloupec($pdo, 'postava_predmety', 'u_sebe') ? 'pp.u_sebe' : '1 AS u_sebe';
    $stmt = $pdo->prepare(
        "SELECT p.id, p.nazev, $szSql, p.utocnost, p.oc, p.vaha,
                p.dostrel_maly, p.dostrel_stredni, p.dostrel_velky, p.kategorie_zbrane, pp.mnozstvi, $uSebeSql
         FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
         WHERE pp.postava_id = ? AND p.typ = 'zbran' ORDER BY p.nazev"
    );
    $stmt->execute([(int)$postava['id']]);
    $out = [];
    $varianta = $pdo->prepare(
        "SELECT " . ($maSz ? 'sila_zbrane' : 'NULL AS sila_zbrane') . ", dostrel_maly, dostrel_stredni, dostrel_velky FROM predmety
         WHERE typ = 'zbran' AND nazev = ? AND dostrel_maly IS NOT NULL"
    );
    foreach ($stmt->fetchAll() as $z) {
        $rodova = dracak_vtt_je_rodova_zbran($z['nazev'], $zakladniRasa);
        // Vrhací varianta téže zbraně ("Dýka" -> "Dýka (vrhací)").
        $vrh = null;
        if ($z['dostrel_maly'] === null) {
            $varianta->execute([preg_replace('/\s*\(.*\)$/u', '', $z['nazev']) . ' (vrhací)']);
            $vrh = $varianta->fetch() ?: null;
        }
        $uc = dracak_vtt_utocna_cisla($z, $bonusSil, $bonusObr, $rodova, $vrh);
        $dostrelRadek = $z['dostrel_maly'] !== null ? $z : $vrh;
        $out[] = [
            'id' => (int)$z['id'],
            'nazev' => $z['nazev'],
            'mnozstvi' => (int)$z['mnozstvi'],
            'sz' => $z['sila_zbrane'] !== null ? (int)$z['sila_zbrane'] : null,
            'utocnost' => $z['utocnost'] !== null ? (int)$z['utocnost'] : null,
            'oz' => $z['oc'] !== null ? (int)$z['oc'] : null,
            'delka' => $z['delka_zbrane'],
            'dostrel' => $dostrelRadek !== null
                ? [(int)$dostrelRadek['dostrel_maly'], (int)$dostrelRadek['dostrel_stredni'], (int)$dostrelRadek['dostrel_velky']] : null,
            'rodova' => $rodova,
            'drzena' => isset($postava['drzena_zbran_id']) && (int)$postava['drzena_zbran_id'] === (int)$z['id'],
            'u_sebe' => (bool)(int)$z['u_sebe'],
            'kategorie' => $z['kategorie_zbrane'],
            'uc_tvar_v_tvar' => $uc['uc_tvar_v_tvar'],
            'uc_strelecky' => $uc['uc_strelecky'],
        ];
    }
    return $out;
}

// Obranné číslo (h1611): OČ = KZ zbroje + bonus za obratnost, nikdy < 0;
// bez zbroje KZ 1 ("v šatech s KZ 1", h1682 a příklad Sindor v h1690:
// "Bez brnění: KZ 1, OČ 1"). Štít +1 k hodu na obranu (ne k OČ).
// KZ zbroje je v katalogu ve sloupci predmety.oc.
function dracak_vtt_obrana(PDO $pdo, array $postava, int $bonusObr): array
{
    $kz = 1;
    $zbroj = null;
    if (!empty($postava['zbroj_id'])) {
        $stmt = $pdo->prepare('SELECT nazev, oc FROM predmety WHERE id = ?');
        $stmt->execute([(int)$postava['zbroj_id']]);
        $zbroj = $stmt->fetch() ?: null;
        if ($zbroj && $zbroj['oc'] !== null) {
            $kz = (int)$zbroj['oc'];
        }
    }
    $stit = null;
    if (!empty($postava['stit_id'])) {
        $stmt = $pdo->prepare('SELECT nazev FROM predmety WHERE id = ?');
        $stmt->execute([(int)$postava['stit_id']]);
        $stit = $stmt->fetchColumn() ?: null;
    }
    return [
        'kz' => $kz,
        'zbroj' => $zbroj['nazev'] ?? null,
        'bonus_obratnost' => $bonusObr,
        'oc' => max(0, $kz + $bonusObr),
        'stit' => $stit,
        'bonus_stit' => $stit !== null ? 1 : 0,
    ];
}

// Základní povolání, jehož tabulka zkušeností platí (větev zdědí od
// rodiče, pokud vlastní tabulku nemá).
function dracak_vtt_zkusenostni_tabulka_id(PDO $pdo, ?int $povolaniId): ?int
{
    $stmt = $pdo->prepare('SELECT zkusenostni_tabulka_id, rodic_povolani_id FROM povolani WHERE id = ?');
    for ($i = 0; $i < 5 && $povolaniId; $i++) {
        $stmt->execute([$povolaniId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if ($row['zkusenostni_tabulka_id'] !== null) {
            return (int)$row['zkusenostni_tabulka_id'];
        }
        $povolaniId = $row['rodic_povolani_id'] !== null ? (int)$row['rodic_povolani_id'] : null;
    }
    return null;
}

// Zkušenost a postup (h110): další úroveň a cena výcviku z tabulky
// zkušeností povolání. Postup není automatický — i po dosažení hranice
// postava postoupí, jen když chce a po výcviku (2 měsíce herního času,
// platba) — proto 'muze_postoupit' = jen "zt stačí".
function dracak_vtt_zkusenost(PDO $pdo, array $postava): array
{
    $zt = (int)($postava['zkusenost'] ?? 0);
    $uroven = (int)($postava['uroven'] ?? 1);
    $tabulkaId = dracak_vtt_zkusenostni_tabulka_id($pdo, !empty($postava['povolani_id']) ? (int)$postava['povolani_id'] : null);
    $dalsi = null;
    if ($tabulkaId !== null) {
        $stmt = $pdo->prepare('SELECT potrebne_zkusenosti, cena_za_vycvik FROM zkusenostni_urovne WHERE tabulka_id = ? AND uroven = ?');
        $stmt->execute([$tabulkaId, $uroven + 1]);
        $dalsi = $stmt->fetch() ?: null;
    }
    return [
        'zkusenost' => $zt,
        'uroven' => $uroven,
        'dalsi_uroven' => $dalsi ? $uroven + 1 : null,
        'potrebne' => $dalsi ? (int)$dalsi['potrebne_zkusenosti'] : null,
        'cena_vycviku_zl' => $dalsi ? (int)$dalsi['cena_za_vycvik'] : null,
        'muze_postoupit' => $dalsi ? $zt >= (int)$dalsi['potrebne_zkusenosti'] : false,
    ];
}

// Peníze (h1690: zvlášť zl/st/md). Počet mincí = váha v mn (h3), cena
// jednotlivých mincí je jiná věc (1 zl = 10 st = 100 md).
function dracak_vtt_penize(array $postava): ?array
{
    if (!array_key_exists('penize_zl', $postava)) {
        return null;
    }
    $zl = (int)$postava['penize_zl'];
    $st = (int)$postava['penize_st'];
    $md = (int)$postava['penize_md'];
    return ['zl' => $zl, 'st' => $st, 'md' => $md, 'hodnota_md' => $zl * 100 + $st * 10 + $md, 'vaha_mn' => $zl + $st + $md];
}

// Zvláštní schopnosti a dovednosti: rasové (včetně rodiče rasy) a
// povolání (včetně rodičovského), jen do úrovně postavy.
function dracak_vtt_schopnosti_postavy(PDO $pdo, array $postava): array
{
    $uroven = (int)($postava['uroven'] ?? 1);
    $stmt = $pdo->prepare(
        'SELECT DISTINCT zs.id, zs.nazev, zs.druh, zs.uroven_od, zs.mechanika, zs.pocet_kostek, zs.typ_kostky,
                zs.pevny_bonus, zs.max_pouziti, zs.popis, "povolání" AS zdroj
         FROM schopnost_povolani sp JOIN zvlastni_schopnosti zs ON zs.id = sp.schopnost_id
         WHERE sp.povolani_id IN (SELECT ? UNION SELECT rodic_povolani_id FROM povolani WHERE id = ? AND rodic_povolani_id IS NOT NULL)
           AND COALESCE(zs.uroven_od, 1) <= ?
         UNION
         SELECT DISTINCT zs.id, zs.nazev, zs.druh, zs.uroven_od, zs.mechanika, zs.pocet_kostek, zs.typ_kostky,
                zs.pevny_bonus, zs.max_pouziti, zs.popis, "rasa" AS zdroj
         FROM rasa_schopnosti rs JOIN zvlastni_schopnosti zs ON zs.id = rs.schopnost_id
         WHERE rs.rasa_id IN (SELECT ? UNION SELECT rodic_rasa_id FROM rasy WHERE id = ? AND rodic_rasa_id IS NOT NULL)
         ORDER BY zdroj, uroven_od, nazev'
    );
    $povolani = (int)($postava['povolani_id'] ?? 0);
    $rasa = (int)($postava['rasa_id'] ?? 0);
    $stmt->execute([$povolani, $povolani, $uroven, $rasa, $rasa]);
    return $stmt->fetchAll();
}

// Zraňuje položka (kouzlo/lektvar) — má efekt "Poškození" na životy? Podle
// toho deník při použití "na sebe" žádá potvrzení (bez zadaného cíle by
// zranění dopadlo na toho, kdo položku použil).
function dracak_vtt_polozka_skodi(string $typPolozky, int $polozkaId): bool
{
    foreach (dracak_vtt_polozka_efekty($typPolozky, $polozkaId) as $efekt) {
        if (dracak_vtt_hp_znamenko($efekt) === -1) {
            return true;
        }
    }
    return false;
}

// Možné cíle kouzel a lektvarů: ostatní postavy světa a nestvůry na aktivní
// mapě světa (hra/api/pouzij_predmet.php bere cil_typ_entity + cil_entita_id).
function dracak_vtt_mozne_cile(PDO $pdo, array $postava): array
{
    $svetId = (int)($postava['svet_id'] ?? 0);
    if ($svetId <= 0) {
        return [];
    }
    $cile = [];
    $stmt = $pdo->prepare('SELECT id, nazev FROM postavy WHERE svet_id = ? AND id <> ? ORDER BY nazev');
    $stmt->execute([$svetId, (int)$postava['id']]);
    foreach ($stmt->fetchAll() as $r) {
        $cile[] = ['hodnota' => 'postava:' . $r['id'], 'nazev' => $r['nazev'] . ' (postava)'];
    }
    $stmt = $pdo->prepare(
        'SELECT ni.id, ni.nazev_instance FROM nestvura_instance ni
         WHERE ni.mapa_id = (SELECT aktivni_mapa_id FROM svet WHERE id = ?) ORDER BY ni.nazev_instance'
    );
    $stmt->execute([$svetId]);
    foreach ($stmt->fetchAll() as $r) {
        $cile[] = ['hodnota' => 'nestvura_instance:' . $r['id'], 'nazev' => $r['nazev_instance'] . ' (nestvůra)'];
    }
    return $cile;
}

function dracak_vtt_kouzla_postavy(PDO $pdo, int $postavaId): array
{
    $stmt = $pdo->prepare(
        'SELECT k.id, k.nazev, k.uroven_kouzla, k.cena_magenergie, k.dosah, k.rozsah, k.doba_seslani, k.doba_trvani,
                k.typ_unavy, k.pocet_kostek, k.typ_kostky, k.pevny_bonus, k.popis, sk.nazev AS seznam
         FROM postava_zna_kouzlo pk JOIN kouzla k ON k.id = pk.kouzlo_id
         LEFT JOIN seznamy_kouzel sk ON sk.id = k.seznam_kouzel_id
         WHERE pk.postava_id = ? ORDER BY k.uroven_kouzla, k.nazev'
    );
    $stmt->execute([$postavaId]);
    $kouzla = $stmt->fetchAll();
    foreach ($kouzla as &$k) {
        $k['skodi'] = dracak_vtt_polozka_skodi('kouzlo', (int)$k['id']);
    }
    unset($k);
    return $kouzla;
}

function dracak_vtt_jazyky_postavy(PDO $pdo, array $postava): array
{
    $stmt = $pdo->prepare(
        'SELECT j.nazev FROM jazyky j WHERE j.id IN (
             SELECT jazyk_id FROM rasa_jazyky WHERE rasa_id = ?
             UNION SELECT jazyk_id FROM povolani_jazyky WHERE povolani_id = ?)
         ORDER BY j.nazev'
    );
    $stmt->execute([(int)($postava['rasa_id'] ?? 0), (int)($postava['povolani_id'] ?? 0)]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function dracak_vtt_aktivni_efekty_postavy(PDO $pdo, int $postavaId): array
{
    // Včetně toho, co boj z efektu sám dělá (složky) a zda zbytek zůstává na PJ (includes/vtt_efekty.php).
    require_once __DIR__ . '/vtt_efekty.php';
    $vystup = [];
    foreach (dracak_vtt_efekty_aktivni($pdo, 'postava', $postavaId) as $ae) {
        $vystup[] = [
            'nazev' => $ae['nazev'], 'typ' => $ae['typ'], 'tooltip_text' => $ae['tooltip_text'], 'zbyva_kol' => $ae['zbyva_kol'],
            'stav' => dracak_vtt_efekty_funkce() ? $ae['mechanika_stav'] : null,
            'stav_nazev' => dracak_vtt_efekty_funkce() ? (DRACAK_VTT_EFEKT_STAVY[$ae['mechanika_stav']] ?? null) : null,
            'shrnuti' => dracak_vtt_efekt_shrnuti($ae['slozky']),
        ];
    }
    return $vystup;
}

// Volný inventář s váhou (předměty a lektvary). Předměty uložené v kontejnerech
// tu nejsou — jsou v dracak_vtt_kontejnery_postavy(). 'kapacita_mn' je vyplněná
// jen u předmětu, který je kontejner (vak, truhla…) a leží ve volném inventáři
// (např. přidal ho PJ) — deník nabídne "rozbalit jako kontejner".
function dracak_vtt_inventar_s_vahou(PDO $pdo, int $postavaId): array
{
    $vysledek = [];
    $kapacita = dracak_vtt_ma_sloupec($pdo, 'predmety', 'kapacita_mn') ? 'p.kapacita_mn' : 'NULL';
    $stmt = $pdo->prepare(
        "SELECT p.id, p.nazev, p.typ, p.vaha, $kapacita AS kapacita_mn, pp.mnozstvi
         FROM postava_predmety pp JOIN predmety p ON p.id = pp.predmet_id
         WHERE pp.postava_id = ? ORDER BY FIELD(p.typ, 'zbran', 'zbroj', 'artefakt', 'surovina', 'vybaveni'), p.nazev"
    );
    $stmt->execute([$postavaId]);
    foreach ($stmt->fetchAll() as $r) {
        $vysledek[] = [
            'kategorie' => 'predmet', 'id' => (int)$r['id'], 'nazev' => $r['nazev'], 'typ' => $r['typ'],
            'kapacita_mn' => $r['kapacita_mn'] !== null ? (float)$r['kapacita_mn'] : null,
            'mnozstvi' => (int)$r['mnozstvi'],
            'vaha_ks' => $r['vaha'] !== null ? (float)$r['vaha'] : null,
            'vaha_celkem' => $r['vaha'] !== null ? (float)$r['vaha'] * (int)$r['mnozstvi'] : null,
        ];
    }
    $stmt = $pdo->prepare(
        'SELECT l.id, l.nazev, pl.mnozstvi FROM postava_lektvary pl JOIN lektvary l ON l.id = pl.lektvar_id
         WHERE pl.postava_id = ? ORDER BY l.nazev'
    );
    $stmt->execute([$postavaId]);
    foreach ($stmt->fetchAll() as $r) {
        // Lektvary váhu ve schématu nemají.
        $vysledek[] = [
            'kategorie' => 'lektvar', 'id' => (int)$r['id'], 'nazev' => $r['nazev'], 'typ' => 'lektvar',
            'mnozstvi' => (int)$r['mnozstvi'], 'vaha_ks' => null, 'vaha_celkem' => null,
            'skodi' => dracak_vtt_polozka_skodi('lektvar', (int)$r['id']),
        ];
    }
    return $vysledek;
}

// Všechno pro deník na jedno volání. $postava = řádek z `postavy` (SELECT *).
//
// $plne = false vynechá drahé části (schopnosti, kouzla, jazyky, efekty,
// inventář, cíle) — stačí pro přehledy více postav najednou
// (hra/druzina.php).
function dracak_vtt_denik_data(PDO $pdo, array $postava, bool $plne = true): array
{
    $stupne = [];
    $bonusy = [];
    foreach (['sila', 'obratnost', 'odolnost', 'inteligence', 'charisma'] as $atr) {
        $stupne[$atr] = ($postava[$atr] ?? null) !== null ? (int)$postava[$atr] : null;
        $bonusy[$atr] = $stupne[$atr] !== null ? dracak_vtt_obratnost_bonus($pdo, $stupne[$atr]) : null;
    }
    $zakladniRasa = dracak_vtt_zakladni_rasa($pdo, !empty($postava['rasa_id']) ? (int)$postava['rasa_id'] : null);

    // Únava snižuje bonus za sílu a obratnost (ÚČ, OČ, pohyblivost) a stupeň
    // inteligence a charismy (Tabulka únavy, h1634) — dál se počítá s
    // efektivními hodnotami; základní zůstávají v 'bonusy'/'stupne'.
    $unava = dracak_vtt_postava_unava_stupen($postava);
    $nastaveni = dracak_vtt_svet_cestovani_nastaveni($pdo, !empty($postava['svet_id']) ? (int)$postava['svet_id'] : null);
    $unavaAuto = $nastaveni['auto_unava'];
    $postihSilObr = $unavaAuto ? ($unava['postih_sil_obr'] ?? 0) : 0;
    $bonusyEf = $bonusy;
    foreach (['sila', 'obratnost'] as $atr) {
        if ($bonusy[$atr] !== null) {
            $bonusyEf[$atr] = $bonusy[$atr] - $postihSilObr;
        }
    }
    $stupneEf = $stupne;
    $stupneEf['inteligence'] = dracak_vtt_unava_efektivni_stupen($stupne['inteligence'], $unavaAuto ? ($unava['postih_int'] ?? 0) : 0, 2);
    $stupneEf['charisma'] = dracak_vtt_unava_efektivni_stupen($stupne['charisma'], $unavaAuto ? ($unava['postih_cha'] ?? 0) : 0, 1);

    $naklad = dracak_vtt_postava_naklad_mn($pdo, (int)$postava['id'], $postava);
    $pohyblivost = dracak_vtt_postava_pohyblivost($pdo, $postava, $naklad['naklad_mn']);
    $nosnost = $pohyblivost['nosnost_mn'] ?? null;

    $bojeschopnost = $stupne['odolnost'] !== null
        ? dracak_vtt_bojeschopnost($stupne['odolnost'], (int)$postava['max_hp'], (int)$postava['aktualni_hp'])
        : null;

    $velikost = $postava['velikost'] ?? null;
    if ($velikost === null && $zakladniRasa !== null) {
        $velikost = DRACAK_VTT_VELIKOST_RASY[$zakladniRasa] ?? null;
    }

    $zbrane = [];
    $obrana = null;
    if ($bonusyEf['sila'] !== null && $bonusyEf['obratnost'] !== null) {
        $zbrane = dracak_vtt_zbrane_postavy($pdo, $postava, $bonusyEf['sila'], $bonusyEf['obratnost'], $zakladniRasa);
    }
    if ($bonusyEf['obratnost'] !== null) {
        $obrana = dracak_vtt_obrana($pdo, $postava, $bonusyEf['obratnost']);
    }

    return [
        'zakladni_rasa' => $zakladniRasa,
        'velikost' => $velikost,
        'rodova_zbran' => $zakladniRasa !== null && isset(DRACAK_VTT_RODOVA_ZBRAN[$zakladniRasa]) ? DRACAK_VTT_RODOVA_ZBRAN[$zakladniRasa][0] : null,
        'stupne' => $stupne,
        'bonusy' => $bonusy,
        'stupne_ef' => $stupneEf,
        'bonusy_ef' => $bonusyEf,
        'bojeschopnost' => $bojeschopnost,
        'unava' => $unava,
        'nastaveni_pohybu' => $nastaveni,
        'spanek' => dracak_vtt_spanek($pdo, $postava),
        'pohyblivost' => $pohyblivost,
        'rychlost' => dracak_vtt_rychlost_pohybu($pdo, $pohyblivost['bonus_pohyblivosti'] ?? null),
        'naklad' => $naklad,
        'hranice_nalozeni' => $nosnost !== null ? [$nosnost, $nosnost * 2, $nosnost * 3, $nosnost * 4] : null,
        'postrech' => $stupneEf['inteligence'] !== null ? dracak_vtt_postrech($stupneEf['inteligence'], $zakladniRasa) : null,
        'zbrane' => $zbrane,
        'obrana' => $obrana,
        'zkusenost' => dracak_vtt_zkusenost($pdo, $postava),
        'penize' => dracak_vtt_penize($postava),
        'schopnosti' => $plne ? dracak_vtt_schopnosti_postavy($pdo, $postava) : [],
        'kouzla' => $plne ? dracak_vtt_kouzla_postavy($pdo, (int)$postava['id']) : [],
        'jazyky' => $plne ? dracak_vtt_jazyky_postavy($pdo, $postava) : [],
        'efekty' => $plne ? dracak_vtt_aktivni_efekty_postavy($pdo, (int)$postava['id']) : [],
        'inventar' => $plne ? dracak_vtt_inventar_s_vahou($pdo, (int)$postava['id']) : [],
        'kontejnery' => $plne ? dracak_vtt_kontejnery_postavy($pdo, (int)$postava['id']) : [],
        'kontejnery_dostupne' => dracak_vtt_kontejnery_dostupne($pdo),
        'doprava' => dracak_vtt_postava_doprava($pdo, $postava),
        'cile' => $plne ? dracak_vtt_mozne_cile($pdo, $postava) : [],
    ];
}

// Co únava a naložení postavy právě mění — přehled pro PJ (družina, panel
// tokenu na mapě). $d = výsledek dracak_vtt_denik_data(). Engine umí
// automaticky ÚČ, OČ, pohyblivost, INT/CHA, postřeh a iniciativu; zbytek (obtížnost
// dovedností) kniha nechává na PJ, proto je tu jako připomínka:
//   Plavání: past je o 1 těžší za každý stupeň únavy a každý stupeň naložení
//   (žádné 0, mírné 1, střední 2, velké 3); Skok o tyči: nejde při velkém
//   naložení; Tichý pohyb: šanci ovlivňuje naložení (výši určuje PJ).
// Vrací ['radky' => [text], 'pj_rozhoduje' => [text], 'ma_vliv' => bool].
function dracak_vtt_stav_pro_pj(array $d): array
{
    $radky = [];
    $pj = [];
    $unava = $d['unava'] ?? null;
    $stupenUnavy = $unava ? (int)$unava['stupen'] : 0;
    if ($unava && $stupenUnavy > 0) {
        $t = 'Únava ' . $unava['nazev'] . ' (' . (int)$unava['body'] . ' / ' . (int)$unava['vytrvalost'] . ')';
        if ($stupenUnavy < 4) {
            $t .= ': bonus za sílu a obratnost −' . (int)$unava['postih_sil_obr'];
            if ($unava['postih_int']) { $t .= ', inteligence −' . (int)$unava['postih_int']; }
            if ($unava['postih_cha']) { $t .= ', charisma −' . (int)$unava['postih_cha']; }
            $zak = [];
            if (in_array('beh', $unava['zakazy'], true)) { $zak[] = 'běh a sprint'; }
            elseif (in_array('sprint', $unava['zakazy'], true)) { $zak[] = 'sprint'; }
            if (in_array('kouzla', $unava['zakazy'], true)) { $zak[] = 'kouzla (i z předmětů)'; }
            if ($unava['jen_plazeni']) { $zak[] = 'chůze (jen plazení)'; }
            if ($zak) { $t .= '; nesmí: ' . implode(', ', $zak); }
            $t .= '; iniciativa ' . [1 => '−1', 2 => '−2', 3 => '−3'][$stupenUnavy];
        } else {
            $t .= ': omdlévá, bez pomoci umírá během 1k6 hodin';
        }
        if (!($d['nastaveni_pohybu']['auto_unava'] ?? true)) {
            $t .= ' [automatika únavy je ve světě vypnutá — postihy musíš uplatnit ručně]';
        }
        $radky[] = $t;
    }
    $nal = $d['pohyblivost']['nalozeni']['kod'] ?? null;
    $stupenNal = ['zadne' => 0, 'mirne' => 1, 'stredni' => 2, 'velke' => 3, 'prilis_velke' => 4][$nal] ?? 0;
    if ($stupenNal > 0) {
        $nazvy = ['mirne' => 'mírné', 'stredni' => 'střední', 'velke' => 'velké', 'prilis_velke' => 'nad 4× nosnosti'];
        $t = 'Naložení ' . $nazvy[$nal] . ': pohyblivost ' . ($d['pohyblivost']['pohyblivost'] ?? '—');
        if ($stupenNal <= 3) {
            $t .= ', iniciativa ' . [1 => '−1', 2 => '−3', 3 => '−5'][$stupenNal];
        }
        if ($stupenNal >= 3) { $t .= ', −1 k útoku i obraně'; }
        if (!($d['nastaveni_pohybu']['auto_naklad'] ?? true)) {
            $t .= ' [automatika nákladu je ve světě vypnutá]';
        }
        $radky[] = $t;
    }
    if ($stupenUnavy > 0 || $stupenNal > 0) {
        $pj[] = 'Plavání: past je o ' . (min(4, $stupenUnavy) + min(3, $stupenNal)) . ' těžší (stupeň únavy ' . $stupenUnavy . ' + stupeň naložení ' . min(3, $stupenNal) . ')';
    }
    if ($stupenNal >= 3) {
        $pj[] = 'Skok o tyči: při velkém naložení nelze';
    }
    if ($stupenNal > 0) {
        $pj[] = 'Tichý pohyb: šanci ovlivňuje naložení (výši určuješ ty)';
    }
    return ['radky' => $radky, 'pj_rozhoduje' => $pj, 'ma_vliv' => $radky !== []];
}

// ---------------------------------------------------------------------------
// Hlavička deníku v2 (docs/prototypy/denik-v2-predani.md): vlastnosti s
// bonusy, OČ a ÚČ aktivní zbraně i s postihy, a žebříček naložení. Vše se
// skládá z hodnot, které engine už počítá ($d = dracak_vtt_denik_data());
// nová pravidla tu nejsou. Čísla v hlavičce jsou stejná, jaká použije hod
// z deníku (hra/api/hod_postavy.php): základ + opravy za málo životů, velké
// naložení a štít — proto se nikdy nerozejdou.

// Znaménkové číslo: +2, −1, 0.
function dracak_vtt_znam(int $n): string
{
    return $n > 0 ? '+' . $n : ($n < 0 ? '−' . abs($n) : '0');
}

// Postih k hodu na útok a obranu: málo životů (h1610) a velké naložení (h1611).
// ['hodnota' => záporné nebo 0, 'text' => ' − …', 'zivoty' => int, 'nalozeni' => int]
function dracak_vtt_denik_postihy(array $d): array
{
    $zivoty = (int)($d['bojeschopnost']['postih_aktualni'] ?? 0);
    $velke = in_array($d['pohyblivost']['nalozeni']['kod'] ?? null, ['velke', 'prilis_velke'], true);
    $nalozeni = $velke ? -1 : 0;
    $casti = [];
    if ($zivoty !== 0) { $casti[] = 'méně než 1/3 životů ' . dracak_vtt_znam($zivoty); }
    if ($nalozeni !== 0) { $casti[] = 'velké naložení −1'; }
    return ['hodnota' => $zivoty + $nalozeni, 'text' => $casti ? ' (' . implode(', ', $casti) . ')' : '', 'zivoty' => $zivoty, 'nalozeni' => $nalozeni];
}

// Aktivní zbraň = držená, jinak jediná zbraň v inventáři; null, když žádná není.
function dracak_vtt_denik_aktivni_zbran(array $d): ?array
{
    foreach ($d['zbrane'] as $z) {
        if ($z['drzena']) { return $z; }
    }
    return count($d['zbrane']) === 1 ? $d['zbrane'][0] : null;
}

// Údaje pro záhlaví: ['atributy' => [...], 'oc' => [...], 'uc' => [...], 'postrech' => [...]|null].
function dracak_vtt_denik_hlavicka(array $d): array
{
    $nazvy = ['sila' => ['Sil', 'Síla'], 'obratnost' => ['Obr', 'Obratnost'], 'odolnost' => ['Odl', 'Odolnost'], 'inteligence' => ['Int', 'Inteligence'], 'charisma' => ['Cha', 'Charisma']];
    $atributy = [];
    foreach ($nazvy as $kl => [$zkratka, $nazev]) {
        $st = $d['stupne'][$kl] ?? null;
        $stEf = $d['stupne_ef'][$kl] ?? $st;
        $bo = $d['bonusy'][$kl] ?? null;
        $boEf = $d['bonusy_ef'][$kl] ?? $bo;
        $zmenaSt = $st !== null && $stEf !== null && $st !== $stEf;
        $zmenaBo = $bo !== null && $boEf !== null && $bo !== $boEf;
        $title = $nazev . ' ' . ($stEf ?? '—') . ($zmenaSt ? ' (základ ' . $st . ', únava ' . dracak_vtt_znam($stEf - $st) . ')' : '')
            . ($boEf !== null ? ', bonus ' . dracak_vtt_znam($boEf) . ($zmenaBo ? ' (bez únavy ' . dracak_vtt_znam($bo) . ')' : '') : '');
        $atributy[] = [
            'zkratka' => $zkratka, 'nazev' => $nazev,
            'stupen' => $stEf, 'stupen_zaklad' => $zmenaSt ? $st : null,
            'bonus' => $boEf, 'bonus_zaklad' => $zmenaBo ? $bo : null,
            'zmena' => $zmenaSt || $zmenaBo, 'title' => $title,
        ];
    }

    $postihy = dracak_vtt_denik_postihy($d);
    $zbran = dracak_vtt_denik_aktivni_zbran($d);

    // OČ: KZ zbroje + bonus za obratnost (h1611), štít +1 k hodu, postihy. OZ držené zbraně (nepovinné
    // pravidlo, h1611) se do čísla v záhlaví nepočítá — hod z deníku ji taky nepřičítá; je jen v tabulce Boj.
    $ob = $d['obrana'];
    $oc = ['hodnota' => null, 'zaklad' => null, 'title' => 'Doplň obratnost, ať jde spočítat obranné číslo.'];
    if ($ob) {
        $bezPostihu = (int)$ob['oc'] + (int)$ob['bonus_stit'];
        $oc = [
            'hodnota' => $bezPostihu + $postihy['hodnota'],
            'zaklad' => $postihy['hodnota'] !== 0 ? $bezPostihu : null,
            'title' => 'OČ = kvalita zbroje ' . (int)$ob['kz'] . ($ob['zbroj'] ? ' (' . $ob['zbroj'] . ')' : ' (bez zbroje)')
                . ' + bonus Obr ' . dracak_vtt_znam((int)$ob['bonus_obratnost']) . ' (nejméně 0 → ' . (int)$ob['oc'] . ')'
                . ($ob['bonus_stit'] ? ' + štít 1 (' . $ob['stit'] . ')' : '') . $postihy['text']
                . ' = ' . ($bezPostihu + $postihy['hodnota']) . '. Je to číslo, ke kterému se při hodu na obranu přičte 1k6+.',
        ];
    }

    // ÚČ aktivní zbraně: tváří v tvář, jinak střelecké (zbraň bez síly zbraně číslo nemá).
    $uc = ['hodnota' => null, 'zaklad' => null, 'popis' => 'vyber zbraň', 'dalsi' => false, 'title' => 'Žádná zbraň v inventáři.'];
    if ($zbran) {
        $naDalku = $zbran['uc_tvar_v_tvar'] === null && $zbran['uc_strelecky'] !== null;
        $cislo = $naDalku ? $zbran['uc_strelecky'] : $zbran['uc_tvar_v_tvar'];
        $jednoZbran = count($d['zbrane']) < 2;
        if ($cislo === null) {
            $uc = ['hodnota' => null, 'zaklad' => null, 'popis' => $zbran['nazev'], 'dalsi' => !$jednoZbran,
                   'title' => $zbran['nazev'] . ': útočné číslo se nedá spočítat (v katalogu chybí síla zbraně).'];
        } else {
            $uc = [
                'hodnota' => $cislo + $postihy['hodnota'],
                'zaklad' => $postihy['hodnota'] !== 0 ? $cislo : null,
                'popis' => $zbran['nazev'] . ' · ' . ($naDalku ? 'na dálku' : 'na blízko'),
                'dalsi' => !$jednoZbran,
                'title' => 'ÚČ ' . $zbran['nazev'] . ' (' . ($naDalku ? 'na dálku' : 'na blízko') . ') = síla zbraně ' . (int)$zbran['sz']
                    . ' + bonus ' . ($naDalku ? 'Obr' : 'Sil') . ' (únava započtena)' . ($zbran['rodova'] ? ' + 1 rodová zbraň' : '') . ' (nejméně 0 → ' . $cislo . ')'
                    . $postihy['text'] . ' = ' . ($cislo + $postihy['hodnota']) . '.'
                    . (!$jednoZbran ? ' Klepnutím přepneš na další zbraň (' . count($d['zbrane']) . ' v inventáři).' : ' Máš jen jednu zbraň.'),
            ];
        }
    }

    $postrech = null;
    if ($d['postrech']) {
        $int = $d['stupne_ef']['inteligence'];
        $postrech = [
            'objekty' => (int)$d['postrech']['objekty'], 'mechanismy' => (int)$d['postrech']['mechanismy'],
            'title' => 'Postřeh na objekty = Inteligence ' . $int . ' %' . ((int)$d['postrech']['objekty'] !== $int ? ' + 5 % (bonus rasy)' : '')
                . '. Na mechanismy = ½ Inteligence (' . intdiv((int)$int, 2) . ')' . ((int)$d['postrech']['mechanismy'] !== intdiv((int)$int, 2) ? ' + 5 % (bonus rasy)' : '') . ' %. Mění se s Inteligencí (únavou). Hledání: hoď k% a odečti postřeh (h1683).',
        ];
    }
    return ['atributy' => $atributy, 'oc' => $oc, 'uc' => $uc, 'postrech' => $postrech, 'postihy' => $postihy];
}

// Žebříček naložení (TABULKA NALOŽENÍ, h1681): pro každé pásmo horní mez nákladu, pohyblivost
// a její bonus — stejnými funkcemi jako zbytek enginu (zaokrouhlení nahoru, b11149).
// Vrací [] , když nejde spočítat nosnost nebo základní pohyblivost.
function dracak_vtt_denik_zebricek_nalozeni(PDO $pdo, array $d): array
{
    $poh = $d['pohyblivost'] ?? null;
    if (!$poh || ($poh['rucne'] ?? false) || $poh['nosnost_mn'] === null || ($poh['zakladni'] ?? null) === null) {
        return [];
    }
    $nazvy = [1 => 'žádné', 2 => 'mírné', 3 => 'střední', 4 => 'velké'];
    $ctvrtiny = [1 => 4, 2 => 3, 3 => 2, 4 => 1];
    $aktualni = $poh['nalozeni']['kod'] ?? null;
    $kody = [1 => 'zadne', 2 => 'mirne', 3 => 'stredni', 4 => 'velke'];
    $radky = [];
    foreach ($nazvy as $k => $nazev) {
        $pohyb = dracak_vtt_pohyblivost_po_nalozeni((int)$poh['zakladni'], $ctvrtiny[$k]);
        $radky[] = [
            'nazev' => $nazev, 'mez' => (int)$poh['nosnost_mn'] * $k, 'pohyblivost' => $pohyb,
            'bonus' => dracak_vtt_obratnost_bonus($pdo, max(1, $pohyb)), 'aktualni' => $aktualni === $kody[$k],
        ];
    }
    return $radky;
}

