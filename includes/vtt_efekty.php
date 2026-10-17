<?php
declare(strict_types=1);

// VTT — efekty s opravdovou mechanikou.
//
// Efekt už není jen štítek s popisem: ke každému patří "složky" (tabulka efekt_slozky z ručního SQL),
// tedy konkrétní věci, které boj udělá — zdvojí akce, přičte bonus k iniciativě, zakáže jednat, každé
// kolo ubere životy… Co z efektu boj vyhodnotí sám a co zůstává na PJ, říká efekty.mechanika_stav:
//   auto — boj vyhodnotí všechno sám
//   cast — čísla a akce vyhodnotí boj, zbytek (chování, podmínky) posoudí PJ podle popisu
//   pj   — jen štítek s popisem, PJ ho vyhodnotí
//   past — navazuje na pasti a jedy (hod proti pasti), které se zatím nevyhodnocují
//   ceka — v knize chybí údaj (nedohledané číslo / text), nehádá se
// Bez ručního SQL (dracak_vtt_tokeny_funkce()['efekty_mech']) všechno funguje jako dosud: efekty jsou štítky,
// jen „Běh na krátké vzdálenosti“ se pozná podle názvu.
//
// Pravidla, ze kterých se vychází (content/pravidla-hrac.html):
//   h1626 — Rychlost zdvojnásobí počet akcí, které tvor „v daném okamžiku“ má, Protoplazma ho sníží na polovinu
//           (dolů); v dalších kolech působí bonusem / postihem k iniciativě (řádky Tabulky bonusů a postihů
//           k iniciativě „Kouzlo Rychlost“ +6, „Kouzlo Protoplazma“ −3). Trvání kouzel se měří na kola, přičemž
//           kolo seslání se do trvání nepočítá.
//   h1613 — nehybný protivník (svázaný, kouzlící): v obraně místo bonusu za obratnost −5, dále −3 za obranu
//           bez zbraně, bez štítu, bez oprav za velikost a zezadu.

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_predmety.php';
require_once __DIR__ . '/vtt_souboj.php';

const DRACAK_VTT_EFEKT_STAVY = [
    'auto' => 'boj vyhodnotí sám',
    'cast' => 'čísla a akce vyhodnotí boj, zbytek PJ',
    'pj' => 'vyhodnotí PJ podle popisu',
    'past' => 'čeká na pasti a jedy',
    'ceka' => 'v knize chybí údaj',
];

// Druhy složek a co které znamená (hodnota = efekt_slozky.hodnota).
const DRACAK_VTT_EFEKT_SLOZKY = [
    'akce_zdvojit' => 'při nasazení zdvojnásobí akce, které tvor právě má',
    'akce_pulit' => 'při nasazení sníží akce, které tvor právě má, na polovinu (dolů)',
    'akce_zmena' => 'při nasazení změní počet akcí o hodnotu',
    'ini_radek' => 'k iniciativě přičte řádek Tabulky bonusů a postihů (parametr = jeho název)',
    'ini_bonus' => 'k iniciativě přičte hodnotu',
    'uc_bonus' => 'k útočnému číslu přičte hodnotu',
    'oc_bonus' => 'k obrannému číslu přičte hodnotu',
    'beh_hexu' => 'přidá hexy k běhu a sprintu',
    'nemuze_jednat' => 'tvor nemůže jednat (jeho tah se přeskočí)',
    'nemuze_branit' => 'tvor se nemůže bránit zbraní (jen „nebránit se“)',
    'nehybny' => 'v obraně platí jako nehybný protivník (h1613)',
    'zraneni_x_uroven' => 'zranění útoku na něj se násobí jeho úrovní (spící jako vyřazený, h1613 b10736; postava nejvýš ×9, nestvůra životaschopností)',
    'tik_zivoty' => 'na začátku každého kola hod kostkou: −1 ubírá životy, 1 léčí',
];

function dracak_vtt_efekty_funkce(): bool
{
    return dracak_vtt_tokeny_funkce()['efekty_mech'];
}

function dracak_vtt_znamenko_text(int $n): string
{
    return $n < 0 ? '−' . abs($n) : '+' . $n;
}

function dracak_vtt_kostky_text(int $pocet, string $typ, int $bonus): string
{
    return $pocet . $typ . ($bonus > 0 ? '+' . $bonus : ($bonus < 0 ? (string)$bonus : ''));
}

// Složky efektů po id efektu: [efekt_id => [slozka, …]]. Bez ručního SQL prázdné.
function dracak_vtt_efekt_slozky_pro(PDO $pdo, array $efektIds): array
{
    $efektIds = array_values(array_unique(array_map('intval', $efektIds)));
    if (!$efektIds || !dracak_vtt_efekty_funkce()) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($efektIds), '?'));
    $q = $pdo->prepare("SELECT * FROM efekt_slozky WHERE efekt_id IN ($marks) ORDER BY id");
    $q->execute($efektIds);
    $vystup = [];
    foreach ($q->fetchAll() as $s) {
        $vystup[(int)$s['efekt_id']][] = $s;
    }
    return $vystup;
}

// Řádek efektu (se složkami); u starší DB bez ručního SQL s výchozími hodnotami nových sloupců.
function dracak_vtt_efekt_radek(PDO $pdo, int $efektId): ?array
{
    $q = $pdo->prepare('SELECT * FROM efekty WHERE id = ?');
    $q->execute([$efektId]);
    $e = $q->fetch();
    if (!$e) {
        return null;
    }
    $e['mechanika_stav'] = (string)($e['mechanika_stav'] ?? 'pj');
    $e['trvani_kol'] = isset($e['trvani_kol']) && $e['trvani_kol'] !== null ? (int)$e['trvani_kol'] : null;
    $e['kolo_se_nepocita'] = (int)($e['kolo_se_nepocita'] ?? 0);
    $e['opakovani'] = (string)($e['opakovani'] ?? 'obnov');
    $e['slozky'] = dracak_vtt_efekt_slozky_pro($pdo, [$efektId])[$efektId] ?? [];
    return $e;
}

// Jedna věta o tom, co složka dělá (tooltipy, panel PJ).
function dracak_vtt_efekt_slozka_text(array $s): string
{
    $h = $s['hodnota'] !== null ? (int)$s['hodnota'] : 0;
    switch ($s['druh']) {
        case 'akce_zdvojit':
            return 'zdvojnásobí akce';
        case 'akce_pulit':
            return 'sníží akce na polovinu';
        case 'akce_zmena':
            return dracak_vtt_znamenko_text($h) . ' ' . (abs($h) === 1 ? 'akce' : 'akce');
        case 'ini_radek':
            return 'iniciativa: ' . (string)$s['parametr'];
        case 'ini_bonus':
            return 'iniciativa ' . dracak_vtt_znamenko_text($h);
        case 'uc_bonus':
            return 'ÚČ ' . dracak_vtt_znamenko_text($h);
        case 'oc_bonus':
            return 'OČ ' . dracak_vtt_znamenko_text($h);
        case 'beh_hexu':
            return dracak_vtt_znamenko_text($h) . ' hex k běhu a sprintu';
        case 'nemuze_jednat':
            return 'nemůže jednat';
        case 'nemuze_branit':
            return 'nemůže se bránit zbraní';
        case 'nehybny':
            return 'v obraně jako nehybný';
        case 'zraneni_x_uroven':
            return 'zranění na něj ×úroveň';
        case 'tik_zivoty':
            $k = $s['pocet_kostek'] !== null && $s['typ_kostky'] !== null
                ? dracak_vtt_kostky_text((int)$s['pocet_kostek'], (string)$s['typ_kostky'], (int)($s['pevny_bonus'] ?? 0)) : 'kostky ze zdroje';
            return ($h < 0 ? 'každé kolo −' : 'každé kolo +') . $k . ' životů';
    }
    return (string)$s['druh'];
}

// Aktivní efekty tvora (nejstarší první) se složkami a vlastnostmi efektu.
function dracak_vtt_efekty_aktivni(PDO $pdo, string $typ, int $entitaId): array
{
    $mech = dracak_vtt_efekty_funkce();
    $sloupce = $mech
        ? 'ae.nasazeno_kolo, ae.kostky, e.mechanika_stav, e.trvani_kol, e.kolo_se_nepocita, e.opakovani'
        : 'NULL AS nasazeno_kolo, NULL AS kostky, "pj" AS mechanika_stav, NULL AS trvani_kol, 0 AS kolo_se_nepocita, "obnov" AS opakovani';
    $q = $pdo->prepare(
        "SELECT ae.id, ae.efekt_id, ae.zbyva_kol, e.nazev, e.typ, e.cil, e.tooltip_text, e.stackovatelne, e.max_stack, e.mechanika_aplikace, $sloupce
         FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id
         WHERE ae.typ_entity = ? AND ae.entita_id = ? ORDER BY ae.id"
    );
    $q->execute([$typ, $entitaId]);
    $radky = $q->fetchAll();
    $slozky = dracak_vtt_efekt_slozky_pro($pdo, array_column($radky, 'efekt_id'));
    foreach ($radky as &$r) {
        $r['id'] = (int)$r['id'];
        $r['efekt_id'] = (int)$r['efekt_id'];
        $r['zbyva_kol'] = $r['zbyva_kol'] !== null ? (int)$r['zbyva_kol'] : null;
        $r['nasazeno_kolo'] = $r['nasazeno_kolo'] !== null ? (int)$r['nasazeno_kolo'] : null;
        $r['slozky'] = $slozky[$r['efekt_id']] ?? [];
    }
    unset($r);
    return $radky;
}

// Souhrn mechaniky všech aktivních efektů tvora — to, co boj ze složek skutečně čte.
//   uc_bonus / oc_bonus / ini_bonus / beh_hexu  součty čísel
//   opravy_uc / opravy_oc                       [{nazev, hodnota}] do rozpisu hodu
//   ini_radky                                   názvy řádků Tabulky bonusů a postihů k iniciativě
//   ini_duvody                                  texty do logu iniciativy
//   nemuze_jednat / nemuze_branit / nehybny / zraneni_x_uroven   název efektu, který to způsobuje, jinak null
//   tiky                                        [{aktivni_id, nazev, znamenko, kostky: "1k4"}] na začátku kola
// Nestackovatelný efekt se počítá jednou, i kdyby ve starých datech byl nasazený víckrát.
function dracak_vtt_efekty_souhrn(PDO $pdo, string $typ, int $entitaId): array
{
    $s = [
        'uc_bonus' => 0, 'oc_bonus' => 0, 'ini_bonus' => 0, 'beh_hexu' => 0,
        'opravy_uc' => [], 'opravy_oc' => [], 'ini_radky' => [], 'ini_duvody' => [],
        'nemuze_jednat' => null, 'nemuze_branit' => null, 'nehybny' => null, 'zraneni_x_uroven' => null, 'tiky' => [],
    ];
    if (!in_array($typ, ['postava', 'nestvura_instance'], true) || $entitaId <= 0) {
        return $s;
    }
    $videno = [];
    foreach (dracak_vtt_efekty_aktivni($pdo, $typ, $entitaId) as $ae) {
        if (!dracak_vtt_efekty_funkce()) {
            // Starší DB: jediný efekt, který boj čte, je Běh na krátké vzdálenosti (podle názvu).
            if ($ae['nazev'] === DRACAK_VTT_EFEKT_BEH_NA_KRATKO) {
                $s['beh_hexu'] = 1;
            }
            continue;
        }
        if (!(int)$ae['stackovatelne']) {
            if (isset($videno[$ae['efekt_id']])) {
                continue;
            }
            $videno[$ae['efekt_id']] = true;
        }
        $h = static fn(array $sl): int => $sl['hodnota'] !== null ? (int)$sl['hodnota'] : 0;
        foreach ($ae['slozky'] as $sl) {
            switch ($sl['druh']) {
                case 'uc_bonus':
                    $s['uc_bonus'] += $h($sl);
                    $s['opravy_uc'][] = ['nazev' => 'efekt ' . $ae['nazev'], 'hodnota' => $h($sl)];
                    break;
                case 'oc_bonus':
                    $s['oc_bonus'] += $h($sl);
                    $s['opravy_oc'][] = ['nazev' => 'efekt ' . $ae['nazev'], 'hodnota' => $h($sl)];
                    break;
                case 'beh_hexu':
                    $s['beh_hexu'] += $h($sl);
                    break;
                case 'ini_bonus':
                    $s['ini_bonus'] += $h($sl);
                    $s['ini_duvody'][] = 'efekt ' . $ae['nazev'] . ' (' . dracak_vtt_znamenko_text($h($sl)) . ')';
                    break;
                case 'ini_radek':
                    if ($sl['parametr'] !== null && !in_array($sl['parametr'], $s['ini_radky'], true)) {
                        $s['ini_radky'][] = (string)$sl['parametr'];
                    }
                    break;
                case 'nemuze_jednat':
                case 'nemuze_branit':
                case 'nehybny':
                case 'zraneni_x_uroven':
                    $s[$sl['druh']] ??= $ae['nazev'];
                    break;
                case 'tik_zivoty':
                    $kostky = null;
                    if ($sl['pocet_kostek'] !== null && $sl['typ_kostky'] !== null) {
                        $kostky = dracak_vtt_kostky_text((int)$sl['pocet_kostek'], (string)$sl['typ_kostky'], (int)($sl['pevny_bonus'] ?? 0));
                    } elseif ($ae['kostky'] !== null && $ae['kostky'] !== '') {
                        $kostky = (string)$ae['kostky'];
                    }
                    if ($kostky !== null && dracak_vtt_parsuj_kostky($kostky) !== null) {
                        $s['tiky'][] = [
                            'aktivni_id' => $ae['id'], 'efekt_id' => $ae['efekt_id'], 'nazev' => $ae['nazev'],
                            'znamenko' => $h($sl) < 0 ? -1 : 1, 'kostky' => $kostky,
                        ];
                    }
                    break;
            }
        }
    }
    return $s;
}

// Shrnutí efektu pro klienta: co boj sám dělá (z jeho složek).
function dracak_vtt_efekt_shrnuti(array $slozky): array
{
    return array_map('dracak_vtt_efekt_slozka_text', $slozky);
}

// Efekty tvorů s tokenem na mapě pro klienta (jeden dotaz na efekty, jeden na složky):
// ['typ:id' => [{id, nazev, zbyva_kol, stav, stav_nazev, shrnuti[], text}]].
function dracak_vtt_efekty_mapy(PDO $pdo, array $entity): array
{
    if (!$entity) {
        return [];
    }
    $mech = dracak_vtt_efekty_funkce();
    $where = implode(' OR ', array_fill(0, count($entity), '(ae.typ_entity = ? AND ae.entita_id = ?)'));
    $params = [];
    foreach ($entity as [$te, $ei]) {
        $params[] = $te;
        $params[] = $ei;
    }
    $q = $pdo->prepare(
        'SELECT ae.id, ae.typ_entity, ae.entita_id, ae.efekt_id, ae.zbyva_kol, e.nazev, e.tooltip_text'
        . ($mech ? ', e.mechanika_stav' : ', "pj" AS mechanika_stav')
        . " FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id WHERE $where ORDER BY ae.id"
    );
    $q->execute($params);
    $radky = $q->fetchAll();
    $slozky = dracak_vtt_efekt_slozky_pro($pdo, array_column($radky, 'efekt_id'));
    $vystup = [];
    foreach ($radky as $r) {
        $stav = isset(DRACAK_VTT_EFEKT_STAVY[$r['mechanika_stav']]) ? $r['mechanika_stav'] : 'pj';
        $vystup[$r['typ_entity'] . ':' . $r['entita_id']][] = [
            'id' => (int)$r['id'],
            'nazev' => (string)$r['nazev'],
            'zbyva_kol' => $r['zbyva_kol'] !== null ? (int)$r['zbyva_kol'] : null,
            'stav' => $mech ? $stav : null,
            'stav_nazev' => $mech ? DRACAK_VTT_EFEKT_STAVY[$stav] : null,
            'shrnuti' => dracak_vtt_efekt_shrnuti($slozky[(int)$r['efekt_id']] ?? []),
            'text' => (string)($r['tooltip_text'] ?? ''),
        ];
    }
    return $vystup;
}

// Číslo kola, ve kterém se právě nasazuje efekt (null mimo souboj).
function dracak_vtt_efekt_kolo_nasazeni(PDO $pdo, ?int $mapaId): ?int
{
    if ($mapaId === null || !dracak_vtt_souboj_funkce()) {
        return null;
    }
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId);
    return $stav !== null ? $stav['cislo_kola'] : null;
}

// Má kouzlo hod proti pasti (cíl se brání hodem)? Vrací krátký popis pasti („Obr ~ 8 ~ 0“), jinak null.
// Stavový efekt takového kouzla se při sesílání sám nenasadí — o neúspěchu cíle rozhoduje hod, který zatím
// nemá vlastní výzvu: PJ efekt přidá ručně v panelu tokenu.
function dracak_vtt_kouzlo_past_text(array $kouzlo): ?string
{
    $text = (string)($kouzlo['popis'] ?? '');
    if (preg_match('/Past:\s*(.{0,60}?)(?:\.\s|\.$|$)/us', $text, $m)) {
        return trim($m[1]) !== '' ? trim($m[1]) : 'hod proti pasti';
    }
    return !empty($kouzlo['past_id']) ? 'hod proti pasti' : null;
}

// Nasadí efekt tvoru. Jediné místo, které zapisuje do aktivni_efekty — používá ho PJ (panel efektů), použití
// kouzla / lektvaru i hod proti pasti. Neloguje (každý volající loguje svou událost) a nespravuje transakci.
//   $volby: zbyva_kol (null = bez limitu; chybí = výchozí trvání efektu), kostky ("1k6+5", ze zdroje),
// Vrací ['aktivni_efekt_id', 'nazev', 'stav', 'zbyva_kol', 'obnoveno' (jen se prodloužilo trvání),
//   'shrnuti', 'akce' (['token_id', 'pred' => [celkem, zbývá], 'po' => […]] nebo null), 'upozorneni'].
// Okamžité složky (akce) se při obnovení nestackovatelného efektu NEopakují: „trvání se sčítá, účinek ne“.
function dracak_vtt_efekt_aplikuj(PDO $pdo, ?int $mapaId, string $typ, int $entitaId, int $efektId, array $volby = []): array
{
    $e = dracak_vtt_efekt_radek($pdo, $efektId);
    if ($e === null) {
        throw new InvalidArgumentException('Efekt nenalezen.');
    }
    $mech = dracak_vtt_efekty_funkce();
    $upozorneni = [];
    $zbyva = array_key_exists('zbyva_kol', $volby) ? $volby['zbyva_kol'] : $e['trvani_kol'];
    $zbyva = $zbyva !== null ? max(1, min(9999, (int)$zbyva)) : null;
    $kostky = isset($volby['kostky']) && $volby['kostky'] !== '' && dracak_vtt_parsuj_kostky((string)$volby['kostky']) !== null
        ? (string)$volby['kostky'] : null;
    $kolo = $mech ? dracak_vtt_efekt_kolo_nasazeni($pdo, $mapaId) : null;

    $q = $pdo->prepare('SELECT id, zbyva_kol FROM aktivni_efekty WHERE typ_entity = ? AND entita_id = ? AND efekt_id = ? ORDER BY id');
    $q->execute([$typ, $entitaId, $efektId]);
    $existujici = $q->fetchAll();
    $stackuje = (int)$e['stackovatelne'] === 1;
    $obnoveno = false;
    $zbyvaPred = null;
    $aktivniId = 0;

    if ($existujici && !$stackuje) {
        // Už na tvoru je: změní se jen trvání. „secti“ = trvání se sčítá, jinak vyhrává delší.
        $stary = $existujici[0];
        $staryZbyva = $stary['zbyva_kol'] !== null ? (int)$stary['zbyva_kol'] : null;
        if ($staryZbyva === null || $zbyva === null) {
            $nove = null;
        } else {
            $nove = $e['opakovani'] === 'secti' ? $staryZbyva + $zbyva : max($staryZbyva, $zbyva);
        }
        $pdo->prepare('UPDATE aktivni_efekty SET zbyva_kol = ? WHERE id = ?')->execute([$nove, $stary['id']]);
        $aktivniId = (int)$stary['id'];
        $zbyvaPred = $staryZbyva;
        $zbyva = $nove;
        $obnoveno = true;
    } elseif ($existujici && $stackuje && $e['max_stack'] !== null && count($existujici) >= (int)$e['max_stack']) {
        $stary = $existujici[0];
        $pdo->prepare('UPDATE aktivni_efekty SET zbyva_kol = ? WHERE id = ?')->execute([$zbyva, $stary['id']]);
        $aktivniId = (int)$stary['id'];
        $zbyvaPred = $stary['zbyva_kol'] !== null ? (int)$stary['zbyva_kol'] : null;
        $obnoveno = true;
        $upozorneni[] = 'Efekt se už nedá dál kumulovat (nejvýš ' . (int)$e['max_stack'] . '×) — jen se obnovilo trvání.';
    } else {
        if ($mech) {
            $pdo->prepare('INSERT INTO aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol, nasazeno_kolo, kostky) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$typ, $entitaId, $efektId, $zbyva, $kolo, $kostky]);
        } else {
            $pdo->prepare('INSERT INTO aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol) VALUES (?, ?, ?, ?)')
                ->execute([$typ, $entitaId, $efektId, $zbyva]);
        }
        $aktivniId = (int)$pdo->lastInsertId();
    }

    $akce = null;
    if ($mech && !$obnoveno && $mapaId !== null && dracak_vtt_souboj_funkce()) {
        $akce = dracak_vtt_efekt_okamzite_akce($pdo, $mapaId, $typ, $entitaId, $e['slozky']);
    }
    if ($mech && in_array($e['mechanika_stav'], ['pj', 'cast', 'past', 'ceka'], true)) {
        $upozorneni[] = match ($e['mechanika_stav']) {
            'pj' => 'Boj tenhle efekt sám nevyhodnotí — posuď ho podle popisu.',
            'cast' => 'Boj vyhodnotí jen čísla a akce — zbytek (chování, podmínky) posuď podle popisu.',
            'past' => 'Efekt navazuje na pasti a jedy, které se zatím nevyhodnocují — posuď ho podle popisu.',
            default => 'V knize chybí údaj k tomuhle efektu — posuď ho podle popisu.',
        };
    }
    return [
        'aktivni_efekt_id' => $aktivniId,
        'efekt_id' => $efektId,
        'nazev' => (string)$e['nazev'],
        'stav' => $mech ? $e['mechanika_stav'] : null,
        'zbyva_kol' => $zbyva,
        'zbyva_pred' => $zbyvaPred,
        'obnoveno' => $obnoveno,
        'shrnuti' => dracak_vtt_efekt_shrnuti($e['slozky']),
        'akce' => $akce,
        'upozorneni' => $upozorneni,
    ];
}

// Okamžité složky efektu, které mění počet akcí tvora v rozehraném kole (Rychlost ×2, Protoplazma ½, −2 akce…).
// Vrací null, když se nic nezměnilo (tvor nemá hozenou iniciativu, efekt nemá takovou složku).
function dracak_vtt_efekt_okamzite_akce(PDO $pdo, int $mapaId, string $typ, int $entitaId, array $slozky): ?array
{
    $ops = [];
    foreach ($slozky as $s) {
        if ($s['druh'] === 'akce_zdvojit') {
            $ops[] = ['rychlost', 0];
        } elseif ($s['druh'] === 'akce_pulit') {
            $ops[] = ['protoplazma', 0];
        } elseif ($s['druh'] === 'akce_zmena') {
            $ops[] = ['delta', (int)$s['hodnota']];
        }
    }
    if (!$ops) {
        return null;
    }
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId);
    if ($stav === null) {
        return null;
    }
    $q = $pdo->prepare('SELECT id FROM tokeny WHERE mapa_id = ? AND typ_entity = ? AND entita_id = ? ORDER BY id LIMIT 1');
    $q->execute([$mapaId, $typ, $entitaId]);
    $tokenId = $q->fetchColumn();
    if ($tokenId === false) {
        return null;
    }
    $q = $pdo->prepare('SELECT akce_celkem, akce_zbyvajici FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ? FOR UPDATE');
    $q->execute([$mapaId, (int)$tokenId]);
    $ki = $q->fetch();
    if (!$ki) {
        return null;
    }
    $pred = [(int)$ki['akce_celkem'], (int)$ki['akce_zbyvajici']];
    $po = $pred;
    foreach ($ops as [$op, $delta]) {
        $po = dracak_vtt_souboj_akce_prepocet($po[0], $po[1], $op, $delta);
    }
    if ($po === $pred) {
        return null;
    }
    $pdo->prepare('UPDATE kolo_iniciativa SET akce_celkem = ?, akce_zbyvajici = ? WHERE mapa_id = ? AND token_id = ?')
        ->execute([$po[0], $po[1], $mapaId, (int)$tokenId]);
    return ['token_id' => (int)$tokenId, 'pred' => $pred, 'po' => $po];
}

// Po nasazení efektu uprostřed tahů: jestli tvor na tahu přišel o akce (Spánek, −2 akce…), tah se posune dál.
// Volat uvnitř transakce. Vrací výsledek dracak_vtt_souboj_posun, nebo null, když se nic neposouvá.
function dracak_vtt_efekt_posun_po_nasazeni(PDO $pdo, int $svetId, ?int $mapaId, int $ucetId): ?array
{
    if ($mapaId === null || !dracak_vtt_souboj_funkce()) {
        return null;
    }
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null || $stav['faze'] !== 'kolo' || $stav['aktivni_token_id'] === null) {
        return null;
    }
    return dracak_vtt_souboj_posun($pdo, $svetId, $mapaId, $ucetId);
}

// Sundá nasazený efekt (PJ). Vrací název efektu, nebo null, když už neexistuje.
function dracak_vtt_efekt_odstran(PDO $pdo, int $svetId, ?int $mapaId, int $ucetId, int $aktivniId, string $duvod = 'odebran'): ?string
{
    $q = $pdo->prepare('SELECT ae.typ_entity, ae.entita_id, e.nazev FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id WHERE ae.id = ?');
    $q->execute([$aktivniId]);
    $r = $q->fetch();
    if (!$r) {
        return null;
    }
    $pdo->prepare('DELETE FROM aktivni_efekty WHERE id = ?')->execute([$aktivniId]);
    dracak_vtt_log_event($svetId, $mapaId, 'efekt_konci', [
        'typ_entity' => $r['typ_entity'], 'entita_id' => (int)$r['entita_id'], 'efekt_nazev' => $r['nazev'], 'duvod' => $duvod,
    ], $ucetId);
    return (string)$r['nazev'];
}

// Odpočítá trvání efektů všem entitám, které mají na mapě token; efekt, kterému došlo trvání, se smaže.
// Vrací počet skončených efektů. Společné pro konec kola v souboji a ruční tlačítko „Konec kola“
// (hra/api/kolo_konec.php). $konciciKolo = číslo kola, které právě končí: efekt, jehož trvání se kolo
// nasazení nepočítá (kouzla, h1626), v kole, kdy byl nasazen, neubývá.
function dracak_vtt_efekty_odpocet(PDO $pdo, int $svetId, int $mapaId, int $ucetId, ?int $konciciKolo = null): int
{
    $mech = dracak_vtt_efekty_funkce();
    $stmt = $pdo->prepare('SELECT DISTINCT typ_entity, entita_id FROM tokeny WHERE mapa_id = ?');
    $stmt->execute([$mapaId]);
    $expirovalo = 0;
    foreach ($stmt->fetchAll() as $pair) {
        $q = $pdo->prepare(
            'SELECT ae.id, ae.zbyva_kol, e.nazev' . ($mech ? ', ae.nasazeno_kolo, e.kolo_se_nepocita' : '') . '
             FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id
             WHERE ae.typ_entity = ? AND ae.entita_id = ? AND ae.zbyva_kol IS NOT NULL'
        );
        $q->execute([$pair['typ_entity'], $pair['entita_id']]);
        foreach ($q->fetchAll() as $ae) {
            if ($mech && $konciciKolo !== null && (int)$ae['kolo_se_nepocita'] === 1
                && $ae['nasazeno_kolo'] !== null && (int)$ae['nasazeno_kolo'] === $konciciKolo) {
                continue;
            }
            $noveKolo = (int)$ae['zbyva_kol'] - 1;
            if ($noveKolo <= 0) {
                $pdo->prepare('DELETE FROM aktivni_efekty WHERE id = ?')->execute([$ae['id']]);
                dracak_vtt_log_event($svetId, $mapaId, 'efekt_konci', [
                    'typ_entity' => $pair['typ_entity'],
                    'entita_id' => $pair['entita_id'],
                    'efekt_nazev' => $ae['nazev'],
                ], $ucetId);
                $expirovalo++;
            } else {
                $pdo->prepare('UPDATE aktivni_efekty SET zbyva_kol = ? WHERE id = ?')->execute([$noveKolo, $ae['id']]);
            }
        }
    }
    return $expirovalo;
}

// ---------------------------------------------------------------------------------------------
// Tiky: krvácení, regenerace… (složka tik_zivoty)
// ---------------------------------------------------------------------------------------------
//
// Na začátku každého kola souboje vznikne tvoru s takovým efektem výzva k hodu „tik“ — hodí se kostkami
// stejně jako u útoku (tlačítko → 3D kostka, nebo hod z vlastní kostky; nic se neházi samo), z výsledku
// se strhnou nebo přidají životy. PJ ji smí zrušit (tvor třeba mezitím zemřel).

// Hod kostek tiku: přesně tolik hodů, kolik je kostek, každý 1 až počet stěn.
function dracak_vtt_hody_kostky_over($hody, int $pocet, int $typ): ?array
{
    if (!is_array($hody) || count($hody) !== $pocet) {
        return null;
    }
    $hody = array_values(array_map('intval', $hody));
    foreach ($hody as $h) {
        if ($h < 1 || $h > $typ) {
            return null;
        }
    }
    return $hody;
}

// Ruční zápis součtu z vlastních kostek (jedno číslo): od „samé jedničky“ po „samé nejvyšší“.
function dracak_vtt_hody_kostky_soucet(int $soucet, int $pocet, int $typ): ?array
{
    return $soucet >= $pocet && $soucet <= $pocet * $typ ? [$soucet] : null;
}

// Založí tiky na začátku nového kola všem tvorům na mapě. Vrací počet založených výzev.
function dracak_vtt_efekty_tiky_zaloz(PDO $pdo, int $svetId, int $mapaId, int $ucetId): int
{
    if (!dracak_vtt_efekty_funkce() || !dracak_vtt_vyzva_funkce()) {
        return 0;
    }
    $info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
    $pocet = 0;
    foreach ($info as $tokenId => $t) {
        if (!in_array($t['typ_entity'], ['postava', 'nestvura_instance'], true)) {
            continue;
        }
        foreach (dracak_vtt_efekty_souhrn($pdo, $t['typ_entity'], (int)$t['entita_id'])['tiky'] as $tik) {
            $k = dracak_vtt_parsuj_kostky($tik['kostky']);
            if ($k === null) {
                continue;
            }
            $d = [
                'popis' => ($tik['znamenko'] < 0 ? 'Zranění od efektu ' : 'Léčení od efektu ') . $tik['nazev'],
                'efekt_nazev' => $tik['nazev'],
                'aktivni_efekt_id' => $tik['aktivni_id'],
                'znamenko' => $tik['znamenko'],
                'kostky' => $tik['kostky'],
                'pocet' => $k['pocet'], 'typ' => $k['typ'],
                'zaklad' => $k['bonus'], 'zaklad_nazev' => 'pevný bonus',
                'opravy' => [], 'modifikator' => 0, 'upozorneni' => [],
                'hody' => null, 'soucet_hodu' => null, 'celkem' => null,
            ];
            $id = dracak_vtt_vyzva_vloz($pdo, $mapaId, 'tik', (int)$tokenId, null, null, $d);
            dracak_vtt_log_event($svetId, $mapaId, 'vyzva_nova',
                dracak_vtt_vyzva_payload($info, 'tik', $id, (int)$tokenId, null, ['popis' => $d['popis']]), $ucetId);
            $pocet++;
        }
    }
    return $pocet;
}

// Vyhodnotí hozený tik: životy se odečtou / přidají, zapíše se hp_zmena a událost efekt_tik.
// Volat v transakci (vyzva_hod.php). Vrací výsledek pro odpověď.
function dracak_vtt_tik_vyhodnot(PDO $pdo, array $mapa, array $info, array $vyzva, int $ucetId): array
{
    $svetId = (int)$mapa['svet_id'];
    $mapaId = (int)$mapa['id'];
    $d = $vyzva['data'];
    $tokenId = (int)$vyzva['token_id'];
    $t = $info[$tokenId];
    $castka = max(0, (int)$d['celkem']);
    $znamenko = (int)$d['znamenko'] < 0 ? -1 : 1;
    if ($znamenko < 0) {
        $hp = dracak_vtt_souboj_zranit($pdo, $svetId, $mapaId, $t, $castka, $ucetId);
    } else {
        $entita = dracak_vtt_entity_row($t['typ_entity'], (int)$t['entita_id']);
        $pred = (int)($entita['aktualni_hp'] ?? 0);
        [$nove, $max] = dracak_vtt_aplikuj_hp_deltu($t['typ_entity'], $entita, $castka);
        dracak_vtt_log_event($svetId, $mapaId, 'hp_zmena', [
            'typ_entity' => $t['typ_entity'], 'entita_id' => (int)$t['entita_id'], 'nove_hp' => $nove, 'max_hp' => $max, 'zdroj' => 'efekt',
        ], $ucetId);
        $hp = ['hp_pred' => $pred, 'hp_nove' => $nove, 'max_hp' => $max, 'vyrazen' => false];
    }
    $vysledek = [
        'efekt_nazev' => $d['efekt_nazev'], 'znamenko' => $znamenko, 'castka' => $castka,
        'hp_pred' => $hp['hp_pred'], 'hp_nove' => $hp['hp_nove'], 'max_hp' => $hp['max_hp'], 'vyrazen' => $hp['vyrazen'],
    ];
    $d['vysledek'] = $vysledek;
    dracak_vtt_vyzva_uloz($pdo, $vyzva['id'], $d, 'hotovo');
    $viditelny = (bool)$t['viditelny'];
    dracak_vtt_log_event($svetId, $mapaId, 'efekt_tik', [
        'token_id' => $tokenId,
        'label' => $viditelny ? $t['label'] : null,
        'efekt_nazev' => $d['efekt_nazev'],
        'znamenko' => $znamenko,
        'castka' => $castka,
        'hp_nove' => $viditelny ? $hp['hp_nove'] : null,
        'max_hp' => $viditelny ? $hp['max_hp'] : null,
        'vyrazen' => $viditelny ? $hp['vyrazen'] : false,
        'skryta' => !$viditelny,
    ], $ucetId);
    return $vysledek;
}
