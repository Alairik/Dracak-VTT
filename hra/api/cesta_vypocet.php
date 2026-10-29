<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_cestovani.php';

// Kalkulačka cesty (hra/cesta.php) — jen PJ/admin světa. Spočítá úsek cesty
// hodinovou simulací (includes/vtt_cestovani.php) a volitelně ZAPÍŠE
// výsledné body únavy postavám (`zapsat: true`). Zapisuje se vždy znovu
// přepočtená hodnota ze serveru, ne to, co poslal klient.
//
// Vstup JSON:
//   svet_id, vzdalenost (míle), povrch, teren, vliv (násobky; náročnost =
//   povrch × teren × vliv, PJ pravidla h2366), tempo (prochazka|chuze|spech),
//   zastav (1–3: úsek končí těsně před tímto stupněm únavy),
//   ucastnici [{postava_id, vaha_jezdce, na_povoze}], zvirata [{zvire_id, naklad}],
//   povoz {povoz_id, vaha_vozu, nasobek, zvire_id, pocet (1/2/3/4/6), naklad} | null,
//   zapsat (bool)
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

function odpoved_cesta(int $kod, array $data): void
{
    http_response_code($kod);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$svetId = (int)($data['svet_id'] ?? 0);
if (!dracak_vtt_svet_access($user, $svetId)) {
    odpoved_cesta(404, ['error' => 'Svět nenalezen.']);
}
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    odpoved_cesta(403, ['error' => 'Kalkulačku cesty smí použít jen PJ/admin světa.']);
}

$vzdalenost = (float)($data['vzdalenost'] ?? 0);
$povrch = (float)($data['povrch'] ?? 0);
$teren = (float)($data['teren'] ?? 1);
$vliv = (float)($data['vliv'] ?? 1);
$tempo = dracak_text($data['tempo'] ?? '');
$zastav = (int)($data['zastav'] ?? 2);
if ($vzdalenost <= 0 || $vzdalenost > 5000) {
    odpoved_cesta(422, ['error' => 'Vzdálenost musí být v mílích, větší než 0 (nejvýš 5000).']);
}
if ($povrch < 1 || $povrch > 200 || $teren < 1 || $teren > 20 || $vliv < 1 || $vliv > 20) {
    odpoved_cesta(422, ['error' => 'Náročnost povrchu (1–200 bodů/míli) a násobky terénu a vlivů (1–20×) jsou mimo rozsah.']);
}
if (!isset(DRACAK_VTT_TEMPA_CHUZE[$tempo])) {
    odpoved_cesta(422, ['error' => 'Neznámé tempo (procházka, chůze, spěch).']);
}
if ($zastav < 1 || $zastav > 3) {
    odpoved_cesta(422, ['error' => 'Zastavit před stupněm únavy 1–3 (unavená, vyčerpaná, úplně vyčerpaná).']);
}
$narocnost = $povrch * $teren * $vliv;

$pdo = dracak_db();
$ucastnici = [];
$ids = [];
foreach ((array)($data['ucastnici'] ?? []) as $u) {
    $pid = (int)($u['postava_id'] ?? 0);
    if ($pid <= 0 || isset($ids[$pid]) || count($ids) >= 40) {
        continue;
    }
    $stmt = $pdo->prepare('SELECT * FROM postavy WHERE id = ? AND svet_id = ?');
    $stmt->execute([$pid, $svetId]);
    $postava = $stmt->fetch();
    if (!$postava) {
        odpoved_cesta(422, ['error' => 'Postava ' . $pid . ' není v tomhle světě.']);
    }
    $ids[$pid] = true;
    $ucastnici[] = [
        'postava' => $postava, 'vaha_jezdce' => max(0, min(100000, (float)($u['vaha_jezdce'] ?? 0))),
        'na_povoze' => !empty($u['na_povoze']),
    ];
}
$zvirata = [];
foreach (array_slice((array)($data['zvirata'] ?? []), 0, 12) as $z) {
    $stmt = $pdo->prepare('SELECT * FROM jizdni_zvirata WHERE id = ?');
    $stmt->execute([(int)($z['zvire_id'] ?? 0)]);
    $zvire = $stmt->fetch();
    if ($zvire) {
        $zvirata[] = ['zvire' => $zvire, 'naklad' => max(0, min(1000000, (float)($z['naklad'] ?? 0)))];
    }
}

$povoz = null;
$pd = $data['povoz'] ?? null;
if (is_array($pd) && !empty($pd['povoz_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM povozy WHERE id = ?');
    try {
        $stmt->execute([(int)$pd['povoz_id']]);
        $pv = $stmt->fetch();
    } catch (PDOException $e) {
        $pv = false;
    }
    $stmt = $pdo->prepare('SELECT * FROM jizdni_zvirata WHERE id = ?');
    $stmt->execute([(int)($pd['zvire_id'] ?? 0)]);
    $tazne = $stmt->fetch();
    $pocet = (int)($pd['pocet'] ?? 2);
    $vahaVozu = (float)($pd['vaha_vozu'] ?? 0);
    $nasobek = (float)($pd['nasobek'] ?? 0);
    if (!$pv || !$tazne) {
        odpoved_cesta(422, ['error' => 'Povoz nebo tažné zvíře nenalezeno.']);
    }
    if (!in_array($pocet, [1, 2, 3, 4, 6], true)) {
        odpoved_cesta(422, ['error' => 'Spřežení: 1 zvíře, pár, trojka, 2 páry nebo 3 páry (1, 2, 3, 4, 6).']);
    }
    if ($vahaVozu < 500 || $vahaVozu > 30000 || $nasobek < 0.5 || $nasobek > 5) {
        odpoved_cesta(422, ['error' => 'Váha vozu (500–30000 mn) nebo násobek nosnosti (0,5–5) je mimo rozsah.']);
    }
    $povoz = [
        'povoz' => $pv, 'vaha_vozu' => $vahaVozu, 'nasobek' => $nasobek, 'zvire' => $tazne, 'pocet' => $pocet,
        'naklad' => max(0, min(1000000, (float)($pd['naklad'] ?? 0))),
    ];
}

$vypocet = dracak_vtt_cesta_vypocet($pdo, $ucastnici, $zvirata, $vzdalenost, $narocnost, $tempo, $zastav, $povoz);
$stupne = [0 => 'bez únavy', 1 => 'unavená', 2 => 'vyčerpaná', 3 => 'úplně vyčerpaná', 4 => 'vysílená'];

$sim = $vypocet['simulace'];
$vysledek = null;
$zapsano = [];
if ($sim) {
    $nazvy = [];
    foreach ($vypocet['subjekty_sim'] as $s) {
        $nazvy[$s['klic']] = $s['nazev'];
    }
    $konec = [];
    foreach ($vypocet['subjekty_sim'] as $s) {
        $k = $sim['konec'][$s['klic']];
        $konec[] = [
            'klic' => $s['klic'], 'nazev' => $s['nazev'], 'vytrvalost' => $s['vytrvalost'],
            'body_zacatek' => $s['body'], 'body_konec' => round($k['body'], 2),
            'stupen' => $k['stupen'], 'stupen_nazev' => $stupne[$k['stupen']],
        ];
    }
    $segmenty = [];
    foreach ($sim['segmenty'] as [$od, $do, $body, $mil, $omezuje]) {
        $segmenty[] = [
            'od_h' => round($od, 3), 'do_h' => round($do, 3), 'body_za_hodinu' => $body,
            'mil_za_hodinu' => round($mil, 2), 'omezuje' => $nazvy[$omezuje] ?? '',
        ];
    }
    $vysledek = [
        'duvod' => $sim['duvod'], 'kdo' => $sim['kdo'], 'kdo_nazev' => $sim['kdo'] !== null ? ($nazvy[$sim['kdo']] ?? null) : null,
        'hodin' => round($sim['hodin'], 3), 'mil' => round($sim['mil'], 2), 'zbyva_mil' => round($sim['zbyva_mil'], 2),
        'segmenty' => $segmenty, 'konec' => $konec,
    ];

    if (!empty($data['zapsat'])) {
        if ($ucastnici && !array_key_exists('unava', $ucastnici[0]['postava'])) {
            odpoved_cesta(409, ['error' => 'Únavu zatím nejde ukládat (čeká na migraci 0065).']);
        }
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare('UPDATE postavy SET unava = ? WHERE id = ? AND svet_id = ?');
            foreach ($vypocet['subjekty'] as $popis) {
                if (($popis['postava_id'] ?? null) === null) {
                    continue;
                }
                // Body únavy se počítají v celých bodech (2 h procházky = 1 bod):
                // desetinná část se zahazuje.
                $nove = (int)floor($sim['konec'][$popis['klic']]['body'] + 1e-9);
                $upd->execute([$nove, $popis['postava_id'], $svetId]);
                $zapsano[] = ['postava_id' => $popis['postava_id'], 'nazev' => $popis['nazev'], 'unava' => $nove];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            odpoved_cesta(500, ['error' => 'Zápis únavy selhal.']);
        }
    }
}

odpoved_cesta(200, [
    'ok' => true,
    'narocnost' => round($narocnost, 2),
    'tempo' => DRACAK_VTT_TEMPA_CHUZE[$tempo]['nazev'],
    'chyby' => $vypocet['chyby'],
    'subjekty' => $vypocet['subjekty'],
    'povoz' => $vypocet['povoz'] ?? null,
    'vysledek' => $vysledek,
    'zapsano' => $zapsano,
]);
