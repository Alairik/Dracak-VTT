<?php
declare(strict_types=1);

// Mapová část akcí v souboji: krok / běh / sprint, otočka, útok (cíl před tvorem)
// a jejich spojení. Pravidla (content/pravidla-hrac.html h1621, h1623, h1625,
// h1628, h1698 + domácí úpravy HB, data v includes/vtt_souboj_pravidla.php):
//  - na jednom hexu nejvýš jeden tvor; tvor zabírá 1, 2, 3 nebo 5 hexů podle
//    tvaru (includes/vtt_hexy.php), každý je obrácen čelem k jedné ze 6 stran
//  - KROK = přesun o 1 hex, BĚH o 2, SPRINT o 3 (běh a sprint při úmyslu Pohyb,
//    +1 hex za dovednost Běh na krátké vzdálenosti), celé tělo postupuje hex po
//    hexu přes volná místa; při pohybu se tvor smí otáčet před posuny, mezi nimi i po nich,
//    dohromady nejvýš o 270° za akci (sprint navíc stojí bod únavy, viz tah_akce.php)
//  - OTOČKA = pootočení kolem otočného hexu o libovolný úhel
//  - ÚTOK = jen na tvora v hexech před útočníkem (tvar tvora určuje, které to jsou)
// Potřebuje hexovou mapu s gridem; na čtvercové mapě se akce jen odečtou
// (viz hra/api/tah_akce.php).
//
// Nic tu nepíše do databáze: vrací, co se má stát, a volající (tah_akce.php)
// to uloží a zapíše do logu.

require_once __DIR__ . '/vtt_hexy.php';
require_once __DIR__ . '/vtt_souboj.php';
require_once __DIR__ . '/vtt_souboj_pravidla.php';

const DRACAK_VTT_AKCE_S_KROKEM = ['krok', 'krok_utok', 'obrana_krok', 'beh', 'sprint', 'beh_utok', 'sprint_utok'];
const DRACAK_VTT_AKCE_S_OTOCKOU = ['otocka', 'otocka_utok'];
const DRACAK_VTT_AKCE_S_UTOKEM = ['utok', 'krok_utok', 'otocka_utok', 'beh_utok', 'sprint_utok'];
// Jak se pohyb jmenuje v tabulce souboj_pohyb (krok / běh / sprint).
const DRACAK_VTT_AKCE_REZIM_POHYBU = [
    'krok' => 'krok', 'krok_utok' => 'krok', 'obrana_krok' => 'krok',
    'beh' => 'beh', 'beh_utok' => 'beh', 'sprint' => 'sprint', 'sprint_utok' => 'sprint',
];
const DRACAK_VTT_EFEKT_BEH_NA_KRATKO = 'Běh na krátké vzdálenosti';

// Tělo tvora na mapě: tvar podle počtu hexů (tokeny.hexu), otočný hex = hex, na
// kterém leží střed tokenu, orientace z tokeny.smer. Vrací
// ['tvar', 'pivot' => [col,row], 'orientace' => 0..5, 'bunky' => [[col,row]…]].
function dracak_vtt_token_telo(array $roz, array $token): array
{
    $tvar = dracak_vtt_souboj_tvar(max(1, (int)($token['hexu'] ?? 1)));
    $pivot = dracak_vtt_hex_bunka($roz, (float)$token['x'], (float)$token['y']);
    $orientace = dracak_vtt_hex_orientace($tvar, (float)($token['smer'] ?? 0));
    return [
        'tvar' => $tvar, 'pivot' => $pivot, 'orientace' => $orientace,
        'bunky' => dracak_vtt_hex_bunky_tvaru($tvar, $pivot, $orientace),
    ];
}

// Obsazené hexy na mapě (kromě tvora $krome): klíč "col,row" => id tokenu.
// Počítají se jen postavy a nestvůry (celým tělem); vlastní (obecné) tokeny jsou
// jen značky a překážku nedělají.
function dracak_vtt_souboj_obsazene(PDO $pdo, int $mapaId, array $roz, int $krome): array
{
    $stmt = $pdo->prepare('SELECT * FROM tokeny WHERE mapa_id = ? AND typ_entity IN ("postava", "nestvura_instance") AND id <> ?');
    $stmt->execute([$mapaId, $krome]);
    $obsazene = [];
    foreach ($stmt->fetchAll() as $t) {
        foreach (dracak_vtt_token_telo($roz, $t)['bunky'] as $b) {
            $obsazene[$b[0] . ',' . $b[1]] = (int)$t['id'];
        }
    }
    return $obsazene;
}

// +hexů za dovednost Běh na krátké vzdálenosti: PJ po úspěšném ověření nasadí
// postavě stejnojmenný efekt (aktivni_efekty).
function dracak_vtt_souboj_bonus_behu(PDO $pdo, array $token): int
{
    if (!in_array($token['typ_entity'], ['postava', 'nestvura_instance'], true)) {
        return 0;
    }
    $q = $pdo->prepare(
        'SELECT COUNT(*) FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id
         WHERE ae.typ_entity = ? AND ae.entita_id = ? AND e.nazev = ?'
    );
    $q->execute([$token['typ_entity'], (int)$token['entita_id'], DRACAK_VTT_EFEKT_BEH_NA_KRATKO]);
    return $q->fetchColumn() > 0 ? 1 : 0;
}

// Všechny buňky těla jsou volné (kromě těch, které obsazují jiní)?
function dracak_vtt_souboj_telo_volne(array $bunky, array $obsazene): bool
{
    foreach ($bunky as $b) {
        if (isset($obsazene[$b[0] . ',' . $b[1]])) {
            return false;
        }
    }
    return true;
}

// Vyhodnotí mapovou část akce. $token = řádek tokenu, $mapa = řádek mapy (grid),
// $umysl = úmysl tvora v tomhle kole, $vstup = tělo požadavku
// ({cil: {col,row} = nový otočný hex, smer: stupně, cil_token_id}). Vrací
//   ['mapove' => false]                           akce nemá mapovou část (nebo čtvercová mapa)
//   ['mapove' => true, 'x', 'y', 'smer' (stupně), 'odkud' => [col,row], 'kam' => [col,row],
//    'smer_pred' (stupně), 'cil_token_id' => ?int, 'cil_label' => ?string]
// a při porušení pravidel skončí chybou 422 (dracak_vtt_souboj_chyba).
function dracak_vtt_souboj_pohyb(PDO $pdo, array $mapa, array $token, array $info, ?string $umysl, string $druh, array $vstup, bool $jePj): array
{
    $maKrok = in_array($druh, DRACAK_VTT_AKCE_S_KROKEM, true);
    $maOtocku = in_array($druh, DRACAK_VTT_AKCE_S_OTOCKOU, true);
    $maUtok = in_array($druh, DRACAK_VTT_AKCE_S_UTOKEM, true);
    $roz = dracak_vtt_hex_rozmery($mapa);
    if ($roz === null || !($maKrok || $maOtocku || $maUtok)) {
        return ['mapove' => false];
    }
    $mapaId = (int)$mapa['id'];
    $tokenId = (int)$token['id'];
    $pravidla = dracak_vtt_souboj_pravidla();
    $telo = dracak_vtt_token_telo($roz, $token);
    $tvar = $telo['tvar'];
    $odkud = $telo['pivot'];
    $orientacePred = $telo['orientace'];
    $kam = $odkud;
    $orientace = $orientacePred;
    $obsazene = dracak_vtt_souboj_obsazene($pdo, $mapaId, $roz, $tokenId);
    $otoceniMax = 270;

    if ($maKrok) {
        $rezim = $pravidla['pohyb'][DRACAK_VTT_AKCE_REZIM_POHYBU[$druh]];
        $otoceniMax = (int)$rezim['otoceni_max'];
        if ($rezim['jen_pri_umyslu'] !== null && $umysl !== $rezim['jen_pri_umyslu']) {
            dracak_vtt_souboj_chyba(422, $rezim['nazev'] . ' je jen při úmyslu „Pohyb“ (ohlašuje se před hodem na iniciativu).');
        }
        $dosah = (int)$rezim['hexu'] + ((int)$rezim['bonus_dovednost'] > 0 ? dracak_vtt_souboj_bonus_behu($pdo, $token) * (int)$rezim['bonus_dovednost'] : 0);
        $popisDosahu = $dosah === 1
            ? 'Krok vede na sousední hex (o víc hexů jen při Běhu a Sprintu).'
            : $rezim['nazev'] . ' vede nejvýš o ' . $dosah . ' ' . ($dosah >= 5 ? 'hexů' : 'hexy') . '.';
        // Zdi hráčům do cesty nevstoupí (PJ smí všude, jako při tažení).
        $zedFn = null;
        if (!$jePj) {
            $q = $pdo->prepare('SELECT x1, y1, x2, y2 FROM zdi WHERE mapa_id = ? AND blokuje_pohyb = 1');
            $q->execute([$mapaId]);
            $zdi = $q->fetchAll();
            $pamet = [];
            $zedFn = function (array $a, array $b) use ($roz, $zdi, &$pamet): bool {
                $klic = $a[0] . ',' . $a[1] . '>' . $b[0] . ',' . $b[1];
                if (!isset($pamet[$klic])) {
                    [$x1, $y1] = dracak_vtt_hex_stred($roz, $a);
                    [$x2, $y2] = dracak_vtt_hex_stred($roz, $b);
                    $pamet[$klic] = false;
                    foreach ($zdi as $z) {
                        if (dracak_vtt_useky_se_protinaji($x1, $y1, $x2, $y2, (float)$z['x1'], (float)$z['y1'], (float)$z['x2'], (float)$z['y2'])) {
                            $pamet[$klic] = true;
                            break;
                        }
                    }
                }
                return $pamet[$klic];
            };
        }
        $orientaceCil = isset($vstup['smer']) ? dracak_vtt_hex_orientace($tvar, (float)$vstup['smer']) : $orientacePred;

        if (isset($vstup['cesta']) && is_array($vstup['cesta'])) {
            // Cesta zadaná po bodech A, B, C… (plánování cesty): každý bod je hex vedle předchozího a "smer"
            // je čelo, se kterým do něj tvor vstupuje (neuvedené = čelo se nemění); "smer" těla požadavku je čelo
            // po posledním bodu (neuvedené = čelo posledního bodu). Otočení se počítají do rozpočtu akce.
            $cesta = [];
            $oCur = $orientacePred;
            foreach (array_values($vstup['cesta']) as $bod) {
                if (!is_array($bod) || !isset($bod['col'], $bod['row'])) {
                    dracak_vtt_souboj_chyba(422, 'Bod cesty potřebuje hex (col, row).');
                }
                $oCur = isset($bod['smer']) ? dracak_vtt_hex_orientace($tvar, (float)$bod['smer']) : $oCur;
                $cesta[] = [(int)$bod['col'], (int)$bod['row'], $oCur];
            }
            if (count($cesta) > $dosah) {
                dracak_vtt_souboj_chyba(422, $popisDosahu);
            }
            $oKonec = isset($vstup['smer']) ? $orientaceCil : $oCur;
            $v = dracak_vtt_hex_over_cestu($tvar, $odkud, $orientacePred, $cesta, $oKonec, $obsazene, $zedFn, $dosah, intdiv($otoceniMax, 60));
            if (!$v['ok']) {
                $bod = isset($cesta[$v['krok']]) ? 'bod ' . chr(65 + $v['krok']) : 'konec cesty';
                $chyby = [
                    'prazdna' => $rezim['nazev'] . ' potřebuje aspoň jeden bod cesty.',
                    'dlouha' => $popisDosahu,
                    'beze_zmeny' => 'Cesta končí tam, kde začala — to není pohyb.',
                    'nesousedi' => 'Cesta musí jít hex po hexu — ' . $bod . ' neleží vedle předchozího.',
                    'telo' => 'Na cestě (' . $bod . ') už někdo stojí — tělo by zasahovalo do cizího hexu.',
                    'zed' => 'Na cestě (' . $bod . ') stojí zeď, kterou nejde projít.',
                    'otoceni_blokovane' => 'U místa „' . $bod . '“ se tělo nemůže otočit — překáží cizí tvor.',
                    'otoceni_rozpocet' => 'Tvor se při pohybu otočí celkem nejvýš o ' . $otoceniMax . '° (součet všech otočení před pohybem, během něj i po něm) — víc je k ' . $bod . '.',
                ];
                dracak_vtt_souboj_chyba(422, $chyby[$v['chyba']] ?? 'Cesta není přípustná.');
            }
            $kam = $v['pivot'];
            $orientace = $v['orientace'];
        } else {
            $cil = $vstup['cil'] ?? null;
            if (!is_array($cil) || !isset($cil['col'], $cil['row'])) {
                dracak_vtt_souboj_chyba(422, $rezim['nazev'] . ' potřebuje cílový hex.');
            }
            $kam = [(int)$cil['col'], (int)$cil['row']];
            $dist = dracak_vtt_hex_vzdalenost($odkud, $kam);
            if ($dist < 1 || $dist > $dosah) {
                dracak_vtt_souboj_chyba(422, $popisDosahu);
            }
            // Bez zadané cesty (jen cíl a čelo) server cestu najde sám: tělo postupuje hex po hexu
            // přes volná místa a na cestě se smí otáčet — celkem nejvýš $otoceniMax stupňů za akci.
            $stavy = dracak_vtt_hex_dosazitelne_stavy($tvar, $odkud, $orientacePred, $dosah, intdiv($otoceniMax, 60), $obsazene, $zedFn);
            if (!isset($stavy[$kam[0] . ',' . $kam[1] . ',' . $orientaceCil])) {
                // Proč ne: cíl (tělem) obsazený, v cestě tvor či zeď, nebo by to vyžadovalo víc otočení, než akce dovolí.
                $polohaJde = false;
                for ($o = 0; $o < 6 && !$polohaJde; $o++) {
                    $polohaJde = isset($stavy[$kam[0] . ',' . $kam[1] . ',' . $o]);
                }
                $vsechnyObsazene = true;
                for ($o = 0; $o < 6; $o++) {
                    if (dracak_vtt_souboj_telo_volne(dracak_vtt_hex_bunky_tvaru($tvar, $kam, $o), $obsazene)) {
                        $vsechnyObsazene = false;
                        break;
                    }
                }
                if ($polohaJde) {
                    if (!dracak_vtt_souboj_telo_volne(dracak_vtt_hex_bunky_tvaru($tvar, $kam, $orientaceCil), $obsazene)) {
                        dracak_vtt_souboj_chyba(422, 'Na tom hexu už někdo stojí (tělo by v tomhle natočení zasahovalo do cizího hexu).');
                    }
                    dracak_vtt_souboj_chyba(422, 'Tvor se při pohybu otočí celkem nejvýš o ' . $otoceniMax . '° (součet všech otočení před pohybem, během něj i po něm).');
                }
                if (!dracak_vtt_souboj_telo_volne(dracak_vtt_hex_bunky_tvaru($tvar, $kam, $orientacePred), $obsazene) || $vsechnyObsazene) {
                    dracak_vtt_souboj_chyba(422, 'Na tom hexu už někdo stojí' . ($tvar['bunky'] && count($tvar['bunky']) > 1 ? ' (tělo by zasahovalo do cizího hexu).' : '.'));
                }
                dracak_vtt_souboj_chyba(422, 'Tam se po cestě nedostaneš — stojí v ní tvor nebo zeď, kterou nejde projít.');
            }
            $orientace = $orientaceCil;
        }
    }

    if ($maOtocku) {
        if (!isset($vstup['smer'])) {
            dracak_vtt_souboj_chyba(422, 'Otočka potřebuje směr, kam se tvor otočí.');
        }
        $orientace = dracak_vtt_hex_orientace($tvar, (float)$vstup['smer']);
        if ($orientace === $orientacePred) {
            dracak_vtt_souboj_chyba(422, 'Tvor už tímhle směrem hledí — otočka by byla zbytečná.');
        }
    }
    // Po pohybu a otočení musí tělo v nové poloze a orientaci zůstat na volných hexech.
    $noveBunky = dracak_vtt_hex_bunky_tvaru($tvar, $kam, $orientace);
    if (!dracak_vtt_souboj_telo_volne($noveBunky, $obsazene)) {
        dracak_vtt_souboj_chyba(422, 'Na tom hexu už někdo stojí (tělo by zasahovalo do cizího hexu).');
    }

    $cilTokenId = null;
    $cilLabel = null;
    if ($maUtok && !empty($vstup['cil_token_id'])) {
        $cilTokenId = (int)$vstup['cil_token_id'];
        $cilInfo = $info[$cilTokenId] ?? null;
        if ($cilInfo === null || $cilTokenId === $tokenId) {
            dracak_vtt_souboj_chyba(422, 'Neplatný cíl útoku.');
        }
        $q = $pdo->prepare('SELECT * FROM tokeny WHERE id = ? AND mapa_id = ?');
        $q->execute([$cilTokenId, $mapaId]);
        $cilToken = $q->fetch();
        if (!$cilToken) {
            dracak_vtt_souboj_chyba(422, 'Cíl útoku na mapě není.');
        }
        $dosahUtoku = [];
        foreach (dracak_vtt_hex_dosah_utoku($tvar, $kam, $orientace) as $b) {
            $dosahUtoku[$b[0] . ',' . $b[1]] = true;
        }
        $zasazeno = false;
        foreach (dracak_vtt_token_telo($roz, $cilToken)['bunky'] as $b) {
            if (isset($dosahUtoku[$b[0] . ',' . $b[1]])) {
                $zasazeno = true;
                break;
            }
        }
        if (!$zasazeno) {
            dracak_vtt_souboj_chyba(422, 'Cíl útoku musí stát v hexech před útočníkem (3 hexy před ním, u velkých tvorů hexy u čelní hrany).');
        }
        $cilLabel = $cilInfo['label'];
    }

    [$x, $y] = dracak_vtt_hex_stred($roz, $kam);
    return [
        'mapove' => true,
        'x' => (int)round($x),
        'y' => (int)round($y),
        'smer' => dracak_vtt_hex_smer_z_orientace($tvar, $orientace),
        'smer_pred' => dracak_vtt_hex_smer_z_orientace($tvar, $orientacePred),
        'odkud' => $odkud,
        'kam' => $kam,
        'cil_token_id' => $cilTokenId,
        'cil_label' => $cilLabel,
    ];
}
