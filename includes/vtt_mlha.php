<?php
declare(strict_types=1);

// Mlha války (viz database/migrations/0056_mlha_valky.sql a
// docs/vtt-datovy-model-navrh-v1.md) — per hráč, per mapa odhalená
// oblast. Buňky mlhy JSOU buňky herního gridu (čtverec i hex, se
// stejným posunem/orientací jako renderGrid()/gridCellCenter() v
// hra/mapa.php) — ne vlastní nezávislý rastr. Bez gridu (GRID_PX<=0)
// proto mlha nedává smysl vůbec (není podle čeho zarovnat buňky) a
// všude níž se chová jako "žádná mlha".
//
// Odhalování kolem postavy je "levné v1" stejně jako LoS
// (dracak_vtt_los_blokovana_zdi v includes/vtt.php): boolean paprsek
// ke středu každé kandidátní buňky v okruhu viditelnosti, ne plný
// výpočet viditelnostního polygonu (ten je "v2", viz návrhový dokument).

require_once __DIR__ . '/vtt.php';

// Poloměr odhalení kolem vlastní postavy. Není to pravidlová hodnota —
// DrD nedefinuje číslo "dohlednosti" pro VTT mlhu války, jde o
// inženýrský default pro tenhle engine (8 sáhů ~ rozumný dosah pohledu
// v místnosti/chodbě).
const DRACAK_VTT_MLHA_VIDITELNOST_SAHY = 8.0;

// Geometrie buněk gridu pro danou mapu — stejná matematika jako
// renderGrid()/gridCellCenter()/squareSnapCandidates()/hexSnapCandidates()
// v hra/mapa.php, jen v PHP (server počítá odhalování, klient jen
// kreslí). min_row/min_col/sloupcu/radku = rozsah potřebný k pokrytí
// CELÉ mapy jedním společným obdélníkovým rastrem (u hexu musí počítat
// s oběma variantami posunu sudé/liché řady, proto zvlášť smyčka přes
// [0, colStep/2] níž).
//
// Vrací sloupcu=radku=0 (= "mlha nedává smysl"), když mapa nemá grid
// nebo nemá známé rozměry (např. bez nahraného obrázku).
function dracak_vtt_mlha_rozmery(array $mapa): array
{
    $sirkaPx = (int)($mapa['sirka_px'] ?? 0);
    $vyskaPx = (int)($mapa['vyska_px'] ?? 0);
    $gridPx = (int)($mapa['grid_velikost_px'] ?? 0);
    $typ = ($mapa['grid_typ'] ?? 'ctverec') === 'hex' ? 'hex' : 'ctverec';
    if ($sirkaPx <= 0 || $vyskaPx <= 0 || $gridPx <= 0) {
        return ['bunka_px' => $gridPx, 'typ' => $typ, 'sloupcu' => 0, 'radku' => 0];
    }
    $offsetX = (int)($mapa['grid_posun_x'] ?? 0);
    $offsetY = (int)($mapa['grid_posun_y'] ?? 0);

    if ($typ === 'hex') {
        $colStep = sqrt(3) * $gridPx;
        $rowStep = 1.5 * $gridPx;
        $offX = fmod(fmod((float)$offsetX, $colStep) + $colStep, $colStep);
        $offY = fmod(fmod((float)$offsetY, $rowStep) + $rowStep, $rowStep);
        $minRow = (int)floor((0 - $offY) / $rowStep) - 1;
        $maxRow = (int)floor((($vyskaPx - 1) - $offY) / $rowStep) + 1;
        $minCol = PHP_INT_MAX;
        $maxCol = PHP_INT_MIN;
        foreach ([0, $colStep / 2] as $rowShift) {
            $minCol = min($minCol, (int)floor((0 - $offX - $rowShift) / $colStep) - 1);
            $maxCol = max($maxCol, (int)floor((($sirkaPx - 1) - $offX - $rowShift) / $colStep) + 1);
        }
        return [
            'bunka_px' => $gridPx, 'typ' => 'hex',
            'off_x' => $offX, 'off_y' => $offY, 'col_step' => $colStep, 'row_step' => $rowStep,
            'min_row' => $minRow, 'min_col' => $minCol,
            'sloupcu' => $maxCol - $minCol + 1, 'radku' => $maxRow - $minRow + 1,
        ];
    }

    $offX = fmod(fmod((float)$offsetX, $gridPx) + $gridPx, $gridPx);
    $offY = fmod(fmod((float)$offsetY, $gridPx) + $gridPx, $gridPx);
    $minCol = (int)floor((0 - $offX) / $gridPx);
    $maxCol = (int)floor((($sirkaPx - 1) - $offX) / $gridPx);
    $minRow = (int)floor((0 - $offY) / $gridPx);
    $maxRow = (int)floor((($vyskaPx - 1) - $offY) / $gridPx);
    return [
        'bunka_px' => $gridPx, 'typ' => 'ctverec',
        'off_x' => $offX, 'off_y' => $offY,
        'min_row' => $minRow, 'min_col' => $minCol,
        'sloupcu' => $maxCol - $minCol + 1, 'radku' => $maxRow - $minRow + 1,
    ];
}

// Který (row, col) buňka obsahuje bod (x, y) — čtverec: přímý výpočet;
// hex: hledání nejbližšího středu v okolí (stejný princip jako
// gridCellCenter() hex větev v hra/mapa.php), protože hex sousedy nejde
// spočítat přímým dělením kvůli posunu lichých řad.
function dracak_vtt_mlha_bunka_index(array $rozmery, float $x, float $y): ?array
{
    if ($rozmery['sloupcu'] <= 0 || $rozmery['radku'] <= 0) {
        return null;
    }
    if ($rozmery['typ'] === 'hex') {
        $rowApprox = (int)round(($y - $rozmery['off_y']) / $rozmery['row_step']);
        $best = null;
        $bestDist = INF;
        for ($row = $rowApprox - 1; $row <= $rowApprox + 1; $row++) {
            $rowShift = (abs($row % 2) === 1) ? $rozmery['col_step'] / 2 : 0;
            $colApprox = (int)round(($x - $rozmery['off_x'] - $rowShift) / $rozmery['col_step']);
            for ($col = $colApprox - 1; $col <= $colApprox + 1; $col++) {
                $cx = $rozmery['off_x'] + $col * $rozmery['col_step'] + $rowShift;
                $cy = $rozmery['off_y'] + $row * $rozmery['row_step'];
                $d = hypot($cx - $x, $cy - $y);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $best = ['row' => $row, 'col' => $col];
                }
            }
        }
        return $best;
    }
    return [
        'row' => (int)floor(($y - $rozmery['off_y']) / $rozmery['bunka_px']),
        'col' => (int)floor(($x - $rozmery['off_x']) / $rozmery['bunka_px']),
    ];
}

// Střed buňky (row, col) v px — inverzní funkce k dracak_vtt_mlha_bunka_index().
function dracak_vtt_mlha_bunka_stred(array $rozmery, int $row, int $col): array
{
    if ($rozmery['typ'] === 'hex') {
        $rowShift = (abs($row % 2) === 1) ? $rozmery['col_step'] / 2 : 0;
        return [
            'x' => $rozmery['off_x'] + $col * $rozmery['col_step'] + $rowShift,
            'y' => $rozmery['off_y'] + $row * $rozmery['row_step'],
        ];
    }
    return [
        'x' => $rozmery['off_x'] + $col * $rozmery['bunka_px'] + $rozmery['bunka_px'] / 2,
        'y' => $rozmery['off_y'] + $row * $rozmery['bunka_px'] + $rozmery['bunka_px'] / 2,
    ];
}

// Index (row, col) -> pozice v bitmapě (row-major, posunuto o min_row/
// min_col, ať je index vždycky nezáporný). Null = mimo uložený rastr.
function dracak_vtt_mlha_bunka_idx(array $rozmery, int $row, int $col): ?int
{
    $r = $row - $rozmery['min_row'];
    $c = $col - $rozmery['min_col'];
    if ($r < 0 || $r >= $rozmery['radku'] || $c < 0 || $c >= $rozmery['sloupcu']) {
        return null;
    }
    return $r * $rozmery['sloupcu'] + $c;
}

// Všechny buňky, jejichž STŘED leží uvnitř obdélníku (x1,y1)-(x2,y2) —
// použito pro obdélníkový výběr (PJ tažení) i jako první (bounding-box)
// filtr pro kruhové odhalení kolem postavy.
function dracak_vtt_mlha_bunky_v_obdelniku(array $rozmery, float $x1, float $y1, float $x2, float $y2): array
{
    $minX = min($x1, $x2);
    $maxX = max($x1, $x2);
    $minY = min($y1, $y2);
    $maxY = max($y1, $y2);
    $vysledek = [];
    if ($rozmery['typ'] === 'hex') {
        $rowOd = (int)floor(($minY - $rozmery['off_y']) / $rozmery['row_step']) - 1;
        $rowDo = (int)floor(($maxY - $rozmery['off_y']) / $rozmery['row_step']) + 1;
        for ($row = $rowOd; $row <= $rowDo; $row++) {
            $rowShift = (abs($row % 2) === 1) ? $rozmery['col_step'] / 2 : 0;
            $colOd = (int)floor(($minX - $rozmery['off_x'] - $rowShift) / $rozmery['col_step']) - 1;
            $colDo = (int)floor(($maxX - $rozmery['off_x'] - $rowShift) / $rozmery['col_step']) + 1;
            for ($col = $colOd; $col <= $colDo; $col++) {
                $stred = dracak_vtt_mlha_bunka_stred($rozmery, $row, $col);
                if ($stred['x'] >= $minX && $stred['x'] <= $maxX && $stred['y'] >= $minY && $stred['y'] <= $maxY) {
                    $vysledek[] = ['row' => $row, 'col' => $col, 'x' => $stred['x'], 'y' => $stred['y']];
                }
            }
        }
        return $vysledek;
    }
    $colOd = (int)floor(($minX - $rozmery['off_x']) / $rozmery['bunka_px']) - 1;
    $colDo = (int)floor(($maxX - $rozmery['off_x']) / $rozmery['bunka_px']) + 1;
    $rowOd = (int)floor(($minY - $rozmery['off_y']) / $rozmery['bunka_px']) - 1;
    $rowDo = (int)floor(($maxY - $rozmery['off_y']) / $rozmery['bunka_px']) + 1;
    for ($row = $rowOd; $row <= $rowDo; $row++) {
        for ($col = $colOd; $col <= $colDo; $col++) {
            $stred = dracak_vtt_mlha_bunka_stred($rozmery, $row, $col);
            if ($stred['x'] >= $minX && $stred['x'] <= $maxX && $stred['y'] >= $minY && $stred['y'] <= $maxY) {
                $vysledek[] = ['row' => $row, 'col' => $col, 'x' => $stred['x'], 'y' => $stred['y']];
            }
        }
    }
    return $vysledek;
}

// Načte aktuální (uloženou, nebo prázdnou "vše skryté") bitmapu hráčovy
// mlhy pro tuhle mapu. Pokud se uložený rozměr/velikost buňky neshoduje
// s aktuálním přepočtem (jiný obrázek, jiný grid — velikost nebo typ),
// začíná se znovu od nuly — nemigrovat starý rastr na nový grid.
// (Samotná změna POSUNU gridu se stejnou velikostí/typem tenhle
// fingerprint nemusí vždycky zachytit — okrajový případ, co se
// neřeší, viz diskuze u dracak_vtt_mlha_rozmery().)
function dracak_vtt_mlha_nacti(PDO $pdo, array $mapa, int $ucetId): array
{
    $rozmery = dracak_vtt_mlha_rozmery($mapa);
    $stmt = $pdo->prepare('SELECT bitmapa, sirka_bunky, sloupcu, radku FROM mlha_valky WHERE mapa_id = ? AND ucet_id = ?');
    $stmt->execute([(int)$mapa['id'], $ucetId]);
    $radek = $stmt->fetch();
    if ($radek
        && (int)$radek['sloupcu'] === $rozmery['sloupcu']
        && (int)$radek['radku'] === $rozmery['radku']
        && (int)$radek['sirka_bunky'] === (int)$rozmery['bunka_px']
    ) {
        $rozmery['bitmapa'] = (string)$radek['bitmapa'];
    } else {
        $rozmery['bitmapa'] = str_repeat("\x00", max(0, $rozmery['sloupcu'] * $rozmery['radku']));
    }
    return $rozmery;
}

function dracak_vtt_mlha_uloz(PDO $pdo, int $mapaId, int $ucetId, string $bitmapa, array $rozmery): void
{
    $ins = $pdo->prepare(
        'INSERT INTO mlha_valky (mapa_id, ucet_id, bitmapa, sirka_bunky, sloupcu, radku) VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE bitmapa = VALUES(bitmapa), sirka_bunky = VALUES(sirka_bunky), sloupcu = VALUES(sloupcu), radku = VALUES(radku)'
    );
    $ins->execute([$mapaId, $ucetId, $bitmapa, $rozmery['bunka_px'], $rozmery['sloupcu'], $rozmery['radku']]);
}

// Odhalí (trvale, mlha se nikdy znovu nezatáhne) buňky gridu v okruhu
// viditelnosti kolem bodu (x, y) pro daný účet na týhle mapě — voláno
// z hra/api/token_presun.php a hra/api/token_pridat.php, pokaždé, když
// se objeví/pohne token hráčovy VLASTNÍ postavy (ne nestvůry, ty
// hráčům vidění nepřidávají). Respektuje LoS (zdi.blokuje_vystrel) —
// stejná bool paprsek-zeď kontrola jako u seslání kouzla/útoku.
function dracak_vtt_mlha_odhal_kolem_bodu(PDO $pdo, array $mapa, int $ucetId, float $x, float $y): void
{
    $rozmery = dracak_vtt_mlha_rozmery($mapa);
    if ($rozmery['sloupcu'] <= 0 || $rozmery['radku'] <= 0) {
        return;
    }
    $existujici = dracak_vtt_mlha_nacti($pdo, $mapa, $ucetId);
    $bitmapa = $existujici['bitmapa'];

    $pxSah = dracak_vtt_px_na_sah($mapa);
    $radiusPx = $pxSah !== null ? DRACAK_VTT_MLHA_VIDITELNOST_SAHY * $pxSah : (float)$rozmery['bunka_px'] * 4.0;

    $mapaId = (int)$mapa['id'];
    $kandidati = dracak_vtt_mlha_bunky_v_obdelniku($rozmery, $x - $radiusPx, $y - $radiusPx, $x + $radiusPx, $y + $radiusPx);
    $zmeneno = false;
    foreach ($kandidati as $bunka) {
        $idx = dracak_vtt_mlha_bunka_idx($rozmery, $bunka['row'], $bunka['col']);
        if ($idx === null || $bitmapa[$idx] === "\x01") {
            continue;
        }
        if (hypot($bunka['x'] - $x, $bunka['y'] - $y) > $radiusPx) {
            continue;
        }
        if (dracak_vtt_los_blokovana_zdi($pdo, $mapaId, $x, $y, $bunka['x'], $bunka['y'])) {
            continue;
        }
        $bitmapa[$idx] = "\x01";
        $zmeneno = true;
    }
    if (!$zmeneno) {
        return;
    }
    dracak_vtt_mlha_uloz($pdo, $mapaId, $ucetId, $bitmapa, $rozmery);
}

// Ruční zásah PJ do JEDNÉ buňky (odhalit/zatáhnout) — na rozdíl od
// dracak_vtt_mlha_odhal_kolem_bodu() výš žádná LoS kontrola, žádné
// "nikdy znovu nezatáhnout". Explicitní PJ override (předem odhalená
// místnost, oprava chyby v odhalení...), ne simulace postavina vidění
// — proto umí i ZATÁHNOUT, což automatické odhalení úmyslně neumí.
function dracak_vtt_mlha_nastav_bod(PDO $pdo, array $mapa, int $ucetId, float $x, float $y, bool $odhalit): void
{
    $rozmery = dracak_vtt_mlha_rozmery($mapa);
    if ($rozmery['sloupcu'] <= 0 || $rozmery['radku'] <= 0) {
        return;
    }
    $idxInfo = dracak_vtt_mlha_bunka_index($rozmery, $x, $y);
    if ($idxInfo === null) {
        return;
    }
    $idx = dracak_vtt_mlha_bunka_idx($rozmery, $idxInfo['row'], $idxInfo['col']);
    if ($idx === null) {
        return;
    }
    $existujici = dracak_vtt_mlha_nacti($pdo, $mapa, $ucetId);
    $bitmapa = $existujici['bitmapa'];
    $bitmapa[$idx] = $odhalit ? "\x01" : "\x00";
    dracak_vtt_mlha_uloz($pdo, (int)$mapa['id'], $ucetId, $bitmapa, $rozmery);
}

// Ruční zásah PJ do CELÉHO obdélníku najednou (tažení myší přes víc
// buněk) — stejná sémantika jako dracak_vtt_mlha_nastav_bod(), jen
// hromadně. Souřadnice jsou libovolné (ne nutně zarovnané na grid) —
// vybírá se podle STŘEDU buňky, viz dracak_vtt_mlha_bunky_v_obdelniku().
function dracak_vtt_mlha_nastav_obdelnik(PDO $pdo, array $mapa, int $ucetId, float $x1, float $y1, float $x2, float $y2, bool $odhalit): void
{
    $rozmery = dracak_vtt_mlha_rozmery($mapa);
    if ($rozmery['sloupcu'] <= 0 || $rozmery['radku'] <= 0) {
        return;
    }
    $existujici = dracak_vtt_mlha_nacti($pdo, $mapa, $ucetId);
    $bitmapa = $existujici['bitmapa'];
    foreach (dracak_vtt_mlha_bunky_v_obdelniku($rozmery, $x1, $y1, $x2, $y2) as $bunka) {
        $idx = dracak_vtt_mlha_bunka_idx($rozmery, $bunka['row'], $bunka['col']);
        if ($idx !== null) {
            $bitmapa[$idx] = $odhalit ? "\x01" : "\x00";
        }
    }
    dracak_vtt_mlha_uloz($pdo, (int)$mapa['id'], $ucetId, $bitmapa, $rozmery);
}

// Celou mapu najednou odhalit/zatáhnout jednomu účtu — použito z
// hra/api/mlha_hromadne.php pro "odhalit/zatáhnout všem hráčům".
function dracak_vtt_mlha_nastav_vse(PDO $pdo, array $mapa, int $ucetId, bool $odhalit): void
{
    $rozmery = dracak_vtt_mlha_rozmery($mapa);
    if ($rozmery['sloupcu'] <= 0 || $rozmery['radku'] <= 0) {
        return;
    }
    $bitmapa = str_repeat($odhalit ? "\x01" : "\x00", $rozmery['sloupcu'] * $rozmery['radku']);
    dracak_vtt_mlha_uloz($pdo, (int)$mapa['id'], $ucetId, $bitmapa, $rozmery);
}
