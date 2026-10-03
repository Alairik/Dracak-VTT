<?php
declare(strict_types=1);

// Mlha války (viz database/migrations/0056_mlha_valky.sql a
// docs/vtt-datovy-model-navrh-v1.md) — per hráč, per mapa odhalená
// oblast. Odhalování je "levné v1" stejně jako LoS (dracak_vtt_los_blokovana_zdi
// v includes/vtt.php): boolean paprsek od hráčovy postavy ke středu
// každé kandidátní buňky v okruhu viditelnosti, ne plný výpočet
// viditelnostního polygonu (ten je "v2", viz návrhový dokument).

require_once __DIR__ . '/vtt.php';

// Velikost buňky mlhy v px — nezávislá na herním gridu (ten může být
// vypnutý, nebo hex), mlha potřebuje vlastní jemnost rastru. 32 px je
// kompromis mezi plynulostí odhalování a velikostí bitmapy.
const DRACAK_VTT_MLHA_BUNKA_PX = 32;

// Poloměr odhalení kolem vlastní postavy. Není to pravidlová hodnota —
// DrD nedefinuje číslo "dohlednosti" pro VTT mlhu války, jde o
// inženýrský default pro tenhle engine (8 sáhů ~ rozumný dosah pohledu
// v místnosti/chodbě). Bez gridu (není měřítko px->sáh) spadá na pevný
// px poloměr místo sáhů, stejná degradace jako jinde v appce.
const DRACAK_VTT_MLHA_VIDITELNOST_SAHY = 8.0;
const DRACAK_VTT_MLHA_VIDITELNOST_PX_BEZ_GRIDU = 320.0;

// Rozměry rastru (buňky) pro mapu — čistě z mapy.sirka_px/vyska_px,
// žádný DB dotaz. sloupcu/radku 0, když mapa nemá známé rozměry (např.
// bez nahraného obrázku) — volající to má kontrolovat a mlhu přeskočit.
function dracak_vtt_mlha_rozmery(array $mapa): array
{
    $sirkaPx = (int)($mapa['sirka_px'] ?? 0);
    $vyskaPx = (int)($mapa['vyska_px'] ?? 0);
    return [
        'bunka_px' => DRACAK_VTT_MLHA_BUNKA_PX,
        'sloupcu' => $sirkaPx > 0 ? (int)ceil($sirkaPx / DRACAK_VTT_MLHA_BUNKA_PX) : 0,
        'radku' => $vyskaPx > 0 ? (int)ceil($vyskaPx / DRACAK_VTT_MLHA_BUNKA_PX) : 0,
    ];
}

// Načte aktuální (uloženou, nebo prázdnou "vše skryté") bitmapu hráčovy
// mlhy pro tuhle mapu. Pokud se rozměry uložené mlhy neshodují s
// aktuálním přepočtem (mapa dostala jiný obrázek jiné velikosti),
// začíná se znovu od nuly — nemigrovat starý rastr na nový rozměr.
function dracak_vtt_mlha_nacti(PDO $pdo, array $mapa, int $ucetId): array
{
    $rozmery = dracak_vtt_mlha_rozmery($mapa);
    $stmt = $pdo->prepare('SELECT bitmapa, sloupcu, radku FROM mlha_valky WHERE mapa_id = ? AND ucet_id = ?');
    $stmt->execute([(int)$mapa['id'], $ucetId]);
    $radek = $stmt->fetch();
    if ($radek && (int)$radek['sloupcu'] === $rozmery['sloupcu'] && (int)$radek['radku'] === $rozmery['radku']) {
        $rozmery['bitmapa'] = (string)$radek['bitmapa'];
    } else {
        $rozmery['bitmapa'] = str_repeat("\x00", $rozmery['sloupcu'] * $rozmery['radku']);
    }
    return $rozmery;
}

// Odhalí (trvale, mlha se nikdy znovu nezatáhne) buňky v okruhu
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
    $bunkaPx = $rozmery['bunka_px'];
    $sloupcu = $rozmery['sloupcu'];
    $radku = $rozmery['radku'];

    $pxSah = dracak_vtt_px_na_sah($mapa);
    $radiusPx = $pxSah !== null ? DRACAK_VTT_MLHA_VIDITELNOST_SAHY * $pxSah : DRACAK_VTT_MLHA_VIDITELNOST_PX_BEZ_GRIDU;

    $sloupecOd = max(0, (int)floor(($x - $radiusPx) / $bunkaPx));
    $sloupecDo = min($sloupcu - 1, (int)floor(($x + $radiusPx) / $bunkaPx));
    $radekOd = max(0, (int)floor(($y - $radiusPx) / $bunkaPx));
    $radekDo = min($radku - 1, (int)floor(($y + $radiusPx) / $bunkaPx));

    $mapaId = (int)$mapa['id'];
    $zmeneno = false;
    for ($radekI = $radekOd; $radekI <= $radekDo; $radekI++) {
        for ($sloupecI = $sloupecOd; $sloupecI <= $sloupecDo; $sloupecI++) {
            $idx = $radekI * $sloupcu + $sloupecI;
            if ($bitmapa[$idx] === "\x01") {
                continue;
            }
            $cx = ($sloupecI + 0.5) * $bunkaPx;
            $cy = ($radekI + 0.5) * $bunkaPx;
            if (hypot($cx - $x, $cy - $y) > $radiusPx) {
                continue;
            }
            if (dracak_vtt_los_blokovana_zdi($pdo, $mapaId, $x, $y, $cx, $cy)) {
                continue;
            }
            $bitmapa[$idx] = "\x01";
            $zmeneno = true;
        }
    }
    if (!$zmeneno) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO mlha_valky (mapa_id, ucet_id, bitmapa, sirka_bunky, sloupcu, radku) VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE bitmapa = VALUES(bitmapa), sirka_bunky = VALUES(sirka_bunky), sloupcu = VALUES(sloupcu), radku = VALUES(radku)'
    );
    $ins->execute([$mapaId, $ucetId, $bitmapa, $bunkaPx, $sloupcu, $radku]);
}
