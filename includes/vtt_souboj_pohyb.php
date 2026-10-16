<?php
declare(strict_types=1);

// Mapová část akcí v souboji: krok, otočka, útok (cíl před tvorem) a jejich
// spojení. Pravidla (content/pravidla-hrac.html h1621, h1623, h1625, h1628):
//  - na jednom hexu nejvýš jeden tvor, každý je čelem k jedné ze šesti stran
//  - KROK = přesun na libovolný volný sousední hex a otočení až o 120°
//    (při úmyslu "pohyb" smí krok vést až o dva hexy, h1625)
//  - OTOČKA = pootočení o libovolný úhel doleva či doprava
//  - ÚTOK = jen na tvora na jednom ze 3 hexů, ke kterým stojí útočník čelem
// Potřebuje hexovou mapu s gridem (includes/vtt_hexy.php); na čtvercové mapě
// se akce jen odečtou (viz hra/api/tah_akce.php).
//
// Nic tu nepíše do databáze: vrací, co se má stát, a volající (tah_akce.php)
// to uloží a zapíše do logu.

require_once __DIR__ . '/vtt_hexy.php';
require_once __DIR__ . '/vtt_souboj.php';

const DRACAK_VTT_AKCE_S_KROKEM = ['krok', 'krok_utok', 'obrana_krok'];
const DRACAK_VTT_AKCE_S_OTOCKOU = ['otocka', 'otocka_utok'];
const DRACAK_VTT_AKCE_S_UTOKEM = ['utok', 'krok_utok', 'otocka_utok'];

// Obsazené hexy na mapě (kromě tvora $krome): klíč "col,row" => id tokenu.
// Počítají se jen postavy a nestvůry; vlastní (obecné) tokeny jsou značky a
// překážku nedělají.
function dracak_vtt_souboj_obsazene(PDO $pdo, int $mapaId, array $roz, int $krome): array
{
    $stmt = $pdo->prepare('SELECT id, x, y FROM tokeny WHERE mapa_id = ? AND typ_entity IN ("postava", "nestvura_instance") AND id <> ?');
    $stmt->execute([$mapaId, $krome]);
    $obsazene = [];
    foreach ($stmt->fetchAll() as $t) {
        $b = dracak_vtt_hex_bunka($roz, (float)$t['x'], (float)$t['y']);
        $obsazene[$b[0] . ',' . $b[1]] = (int)$t['id'];
    }
    return $obsazene;
}

// Vyhodnotí mapovou část akce. $token = řádek tokenu, $mapa = řádek mapy (grid),
// $umysl = úmysl tvora v tomhle kole, $vstup = tělo požadavku
// ({cil: {col,row}, smer: stupně, cil_token_id}). Vrací
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
    $odkud = dracak_vtt_hex_bunka($roz, (float)$token['x'], (float)$token['y']);
    $smerPred = dracak_vtt_hex_smer_idx((float)($token['smer'] ?? 0));
    $kam = $odkud;
    $smer = $smerPred;

    if ($maKrok) {
        $cil = $vstup['cil'] ?? null;
        if (!is_array($cil) || !isset($cil['col'], $cil['row'])) {
            dracak_vtt_souboj_chyba(422, 'Krok potřebuje cílový hex.');
        }
        $kam = [(int)$cil['col'], (int)$cil['row']];
        $dosah = $umysl === 'pohyb' ? 2 : 1;
        $dist = dracak_vtt_hex_vzdalenost($odkud, $kam);
        if ($dist < 1 || $dist > $dosah) {
            dracak_vtt_souboj_chyba(422, $dosah === 2
                ? 'Krok při pohybu vede nejvýš o dva hexy.'
                : 'Krok vede na sousední hex (o dva hexy jen při úmyslu „Pohyb“).');
        }
        $obsazene = dracak_vtt_souboj_obsazene($pdo, $mapaId, $roz, $tokenId);
        if (isset($obsazene[$kam[0] . ',' . $kam[1]])) {
            dracak_vtt_souboj_chyba(422, 'Na tom hexu už někdo stojí.');
        }
        // Cesta: přímo, nebo (při dvou hexech) přes volný hex mezi nimi; hráčům
        // do cesty nesmí vstoupit zeď (PJ smí všude, stejně jako při tažení).
        $cesty = [];
        if ($dist === 1) {
            $cesty[] = [$odkud, $kam];
        } else {
            for ($k = 0; $k < 6; $k++) {
                $mezi = dracak_vtt_hex_soused($odkud, $k);
                if (dracak_vtt_hex_vzdalenost($mezi, $kam) === 1 && !isset($obsazene[$mezi[0] . ',' . $mezi[1]])) {
                    $cesty[] = [$odkud, $mezi, $kam];
                }
            }
        }
        $projde = false;
        foreach ($cesty as $cesta) {
            $zed = false;
            if (!$jePj) {
                for ($i = 0; $i + 1 < count($cesta); $i++) {
                    [$x1, $y1] = dracak_vtt_hex_stred($roz, $cesta[$i]);
                    [$x2, $y2] = dracak_vtt_hex_stred($roz, $cesta[$i + 1]);
                    if (dracak_vtt_pohyb_prochazi_zdi($pdo, $mapaId, $x1, $y1, $x2, $y2)) {
                        $zed = true;
                        break;
                    }
                }
            }
            if (!$zed) {
                $projde = true;
                break;
            }
        }
        if (!$projde) {
            dracak_vtt_souboj_chyba(422, $cesty ? 'Cesta vede přes zeď, kterou nejde projít.' : 'Cesta je zablokovaná jiným tvorem.');
        }
    }

    if ($maKrok && isset($vstup['smer'])) {
        $smer = dracak_vtt_hex_smer_idx((float)$vstup['smer']);
        if (dracak_vtt_hex_rozdil_smeru($smerPred, $smer) > 2) {
            dracak_vtt_souboj_chyba(422, 'Při kroku se tvor otočí nejvýš o 120°.');
        }
    }
    if ($maOtocku) {
        if (!isset($vstup['smer'])) {
            dracak_vtt_souboj_chyba(422, 'Otočka potřebuje směr, kam se tvor otočí.');
        }
        $smer = dracak_vtt_hex_smer_idx((float)$vstup['smer']);
        if ($smer === $smerPred) {
            dracak_vtt_souboj_chyba(422, 'Tvor už tímhle směrem hledí — otočka by byla zbytečná.');
        }
    }

    $cilTokenId = null;
    $cilLabel = null;
    if ($maUtok && !empty($vstup['cil_token_id'])) {
        $cilTokenId = (int)$vstup['cil_token_id'];
        $cilInfo = $info[$cilTokenId] ?? null;
        if ($cilInfo === null || $cilTokenId === $tokenId) {
            dracak_vtt_souboj_chyba(422, 'Neplatný cíl útoku.');
        }
        $q = $pdo->prepare('SELECT x, y FROM tokeny WHERE id = ? AND mapa_id = ?');
        $q->execute([$cilTokenId, $mapaId]);
        $pozice = $q->fetch();
        if (!$pozice) {
            dracak_vtt_souboj_chyba(422, 'Cíl útoku na mapě není.');
        }
        $cilBunka = dracak_vtt_hex_bunka($roz, (float)$pozice['x'], (float)$pozice['y']);
        $predni = array_map(fn($b) => $b[0] . ',' . $b[1], dracak_vtt_hex_predni($kam, $smer));
        if (!in_array($cilBunka[0] . ',' . $cilBunka[1], $predni, true)) {
            dracak_vtt_souboj_chyba(422, 'Cíl útoku musí stát na jednom ze 3 hexů před útočníkem.');
        }
        $cilLabel = $cilInfo['label'];
    }

    [$x, $y] = dracak_vtt_hex_stred($roz, $kam);
    return [
        'mapove' => true,
        'x' => (int)round($x),
        'y' => (int)round($y),
        'smer' => $smer * 60,
        'smer_pred' => $smerPred * 60,
        'odkud' => $odkud,
        'kam' => $kam,
        'cil_token_id' => $cilTokenId,
        'cil_label' => $cilLabel,
    ];
}
