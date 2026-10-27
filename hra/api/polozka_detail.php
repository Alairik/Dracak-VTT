<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_denik.php';
require_once __DIR__ . '/../../includes/vtt_efekty.php';

// Detail položky z katalogu pravidel (předmět, lektvar) pro Osobní deník (hra/denik.php): po kliknutí na název
// v inventáři nebo v tabulce zbraní. Katalog je obsah pravidel viditelný všem přihlášeným (stejně jako hledání
// v hra/api/katalog_hledat.php), ne tajná data — proto jen přihlášení, bez vazby na konkrétní postavu.
// Zobrazují se hodnoty z katalogu tak, jak v něm jsou (prázdné se vynechají); nic se tu nepočítá.
//
// GET: typ = predmet|lektvar, id.
// Výstup: {nazev, podtitul, radky: [[popis, hodnota]], popis, efekty: [název]}
header('Content-Type: application/json; charset=utf-8');
dracak_require_login();

$typ = (string)($_GET['typ'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$pdo = dracak_db();

function polozka_konec(int $kod, array $data): void
{
    http_response_code($kod);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$cislo = static fn($v): string => rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ',');
// Cena je v katalogu ve zlatých (1 zl = 10 st = 100 md, kapitola Peníze).
$cena = static function ($zl): string {
    $md = (int)round((float)$zl * 100);
    $casti = [];
    if (intdiv($md, 100) > 0) { $casti[] = intdiv($md, 100) . ' zl'; }
    if (intdiv($md % 100, 10) > 0) { $casti[] = intdiv($md % 100, 10) . ' st'; }
    if ($md % 10 > 0) { $casti[] = ($md % 10) . ' md'; }
    return $casti ? implode(' ', $casti) : '0';
};

if ($typ === 'predmet') {
    $q = $pdo->prepare('SELECT * FROM predmety WHERE id = ?');
    $q->execute([$id]);
    $p = $q->fetch();
    if (!$p) {
        polozka_konec(404, ['error' => 'Předmět nenalezen.']);
    }
    $druhy = ['zbran' => 'zbraň', 'zbroj' => 'zbroj', 'surovina' => 'surovina', 'artefakt' => 'artefakt', 'vybaveni' => 'vybavení'];
    $kategorie = ['secna' => 'sečná', 'bodna' => 'bodná', 'tupa' => 'tupá', 'drtiva' => 'drtivá', 'vrhaci' => 'vrhací', 'strelna' => 'střelná'];
    $chvat = ['jednoruci' => 'jednoruční', 'obourucni' => 'obouruční', 'kombinace' => 'kombinace dvou zbraní'];
    $trida = ['lehka' => 'lehká', 'stredni' => 'střední', 'tezka' => 'těžká', 'supertezka' => 'supertěžká'];
    $r = [];
    $pridej = static function (string $nazev, $hodnota) use (&$r): void {
        if ($hodnota !== null && $hodnota !== '') {
            $r[] = [$nazev, (string)$hodnota];
        }
    };
    $pridej('Váha', $p['vaha'] !== null ? $cislo($p['vaha']) . ' mn' : null);
    $pridej('Cena', $p['cena'] !== null ? $cena($p['cena']) : null);
    $pridej('Kapacita (kontejner)', ($p['kapacita_mn'] ?? null) !== null ? $cislo($p['kapacita_mn']) . ' mn' : null);
    if ($p['typ'] === 'zbran') {
        $pridej('Síla zbraně', $p['sila_zbrane'] ?? null);
        $pridej('Útočnost', $p['utocnost'] !== null ? dracak_vtt_znam((int)$p['utocnost']) : null);
        $pridej('Obrana zbraně (OZ)', $p['oc'] !== null ? dracak_vtt_znam((int)$p['oc']) : null);
        $pridej('Délka', $p['delka_zbrane'] ?? null);
        $pridej('Dosah', $p['dosah'] ?? null);
        if ($p['dostrel_maly'] !== null) {
            $pridej('Dostřel malý / střední / velký', $p['dostrel_maly'] . ' / ' . $p['dostrel_stredni'] . ' / ' . $p['dostrel_velky'] . ' sáhů');
        }
        $pridej('Druh', implode(', ', array_map(static fn(string $k): string => $kategorie[$k] ?? $k, array_filter(explode(',', (string)$p['kategorie_zbrane'])))));
        $pridej('Držení', $chvat[$p['chvat'] ?? ''] ?? null);
        $pridej('Třída', $trida[$p['trida_zbrane'] ?? ''] ?? null);
        $pridej('Postih k iniciativě', ($p['ini_postih'] ?? null) !== null ? dracak_vtt_znam(-abs((int)$p['ini_postih'])) : null);
    } elseif ($p['typ'] === 'zbroj') {
        $pridej('Kvalita zbroje (KZ)', $p['oc']);
    }
    $pridej('Požadavek na sílu', $p['sil_pozadavek'] ?? null);
    $podtitul = ($druhy[$p['typ']] ?? $p['typ']) . (($p['kategorie_vybaveni'] ?? null) ? ' · ' . $p['kategorie_vybaveni'] : '');
    polozka_konec(200, ['nazev' => $p['nazev'], 'podtitul' => $podtitul, 'radky' => $r, 'popis' => (string)($p['popis'] ?? ''), 'efekty' => []]);
}

if ($typ === 'lektvar') {
    $q = $pdo->prepare(
        'SELECT l.*, pv.nazev AS vyrobce FROM lektvary l LEFT JOIN povolani pv ON pv.id = l.vyrobce_povolani_id WHERE l.id = ?'
    );
    $q->execute([$id]);
    $l = $q->fetch();
    if (!$l) {
        polozka_konec(404, ['error' => 'Lektvar nenalezen.']);
    }
    $r = [];
    if ($l['vyrobce']) { $r[] = ['Vyrábí', (string)$l['vyrobce']]; }
    if ($l['doba_pripravy']) { $r[] = ['Doba přípravy', (string)$l['doba_pripravy']]; }
    if ($l['pocet_kostek'] !== null && $l['typ_kostky'] !== null) {
        $r[] = ['Kostky', dracak_vtt_kostky_text((int)$l['pocet_kostek'], (string)$l['typ_kostky'], (int)($l['pevny_bonus'] ?? 0))];
    }
    if ($l['max_pouziti']) { $r[] = ['Nejvýše použití', (string)$l['max_pouziti']]; }
    if ($l['cena'] !== null) { $r[] = ['Cena', $cena($l['cena'])]; }
    $efekty = array_map(static fn(array $e): string => (string)$e['nazev'], dracak_vtt_polozka_efekty('lektvar', $id));
    polozka_konec(200, ['nazev' => $l['nazev'], 'podtitul' => 'lektvar', 'radky' => $r, 'popis' => (string)($l['popis'] ?? ''), 'efekty' => $efekty]);
}

polozka_konec(422, ['error' => 'Neplatný typ.']);
