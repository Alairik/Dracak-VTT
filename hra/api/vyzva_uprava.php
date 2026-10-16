<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Úprava výzvy k hodu PŘED hodem (includes/vtt_souboj_utok.php):
//  - modifikator   (PJ)  oprava čísla, obtížnosti (−30..30), vidí ji všichni
//  - zaklad        (PJ)  ÚČ / OČ, které se z deníku ani z textu bestiáře nedá spočítat
//  - utocnost      (PJ)  útočnost zbraně u útoku
//  - nebezpecnost  (PJ)  obtížnost hodu proti pasti (hráči ji nevidí)
//  - zbran_id      (kdo ovládá útočníka) jiná zbraň / jiný útok nestvůry
//  - volba         (kdo ovládá obránce) 'branit' (zbraní, stojí akci) / 'nebranit' (jen brněním, −3)
// Po hodu už se výzva nemění.
// Tělo: {mapa_id, vyzva_id, ...pole výše}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId);
$pdo = dracak_db();
$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);

$pdo->beginTransaction();
try {
    $v = dracak_vtt_vyzva_nacti($pdo, (int)($data['vyzva_id'] ?? 0), true);
    if (!$v || $v['mapa_id'] !== $mapaId) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(404, 'Výzva nenalezena.');
    }
    if ($v['stav'] !== 'ceka') {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Tahle výzva už je hozená nebo skončila.');
    }
    $ovlada = isset($info[$v['token_id']]) && dracak_vtt_souboj_ovlada($user, $jePj, $info[$v['token_id']]);
    $d = $v['data'];
    $zmeneno = [];

    foreach (['modifikator', 'zaklad', 'utocnost', 'nebezpecnost'] as $pole) {
        if (!array_key_exists($pole, $data)) {
            continue;
        }
        if (!$jePj) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(403, 'Tohle smí měnit jen PJ.');
        }
        if ($pole === 'utocnost' && $v['druh'] !== 'utok') {
            continue;
        }
        if ($pole === 'nebezpecnost' && $v['druh'] !== 'past') {
            continue;
        }
        $hodnota = $data[$pole];
        if ($hodnota === null || $hodnota === '') {
            if ($pole === 'modifikator' || $pole === 'utocnost') {
                $hodnota = 0;
            } else {
                $d[$pole] = null;
                $zmeneno[] = $pole;
                continue;
            }
        }
        $hodnota = (int)$hodnota;
        if ($pole === 'modifikator') {
            $hodnota = max(-30, min(30, $hodnota));
        } elseif ($pole === 'zaklad') {
            $hodnota = max(0, min(60, $hodnota));
        } elseif ($pole === 'utocnost') {
            $hodnota = max(-20, min(20, $hodnota));
        } else {
            $hodnota = max(-20, min(60, $hodnota));
        }
        $d[$pole] = $hodnota;
        $zmeneno[] = $pole;
    }

    if (array_key_exists('zbran_id', $data)) {
        if ($v['druh'] !== 'utok') {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Zbraň se vybírá jen u útoku.');
        }
        if (!$ovlada) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(403, 'Zbraň za tohohle tvora vybírat nesmíš.');
        }
        $vybrana = null;
        foreach ($d['varianty'] ?? [] as $z) {
            if ((int)$z['id'] === (int)$data['zbran_id']) {
                $vybrana = $z;
            }
        }
        if ($vybrana === null) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Tuhle zbraň tvor nemá.');
        }
        $d['zbran_id'] = $vybrana['id'];
        $d['zaklad'] = $vybrana['uc'];
        $d['zaklad_nazev'] = 'ÚČ ' . $vybrana['nazev'];
        $d['utocnost'] = $vybrana['utocnost'];
        $zmeneno[] = 'zbran_id';
    }

    if (array_key_exists('volba', $data)) {
        if ($v['druh'] !== 'obrana') {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Způsob obrany se vybírá jen u obrany.');
        }
        if (!$ovlada) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(403, 'Za tohohle tvora se rozhodovat nesmíš.');
        }
        $volba = (string)$data['volba'];
        if (!in_array($volba, ['branit', 'nebranit'], true)) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Obrana je buď "bránit se", nebo "nebránit se".');
        }
        if ($volba === 'branit' && !empty($d['duvod_nebrani'])) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Tvor se nemůže bránit zbraní (efekt ' . $d['duvod_nebrani'] . ') — jen nebránit se. PJ může efekt sundat.');
        }
        if ($volba === 'branit' && empty($d['muze_branit']) && !$jePj) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Bránit se akcí nejde — tvor už nemá žádnou akci. Zbývá jen nebránit se (−3).');
        }
        $d['volba'] = $volba;
        $zmeneno[] = 'volba';
    }

    if (!$zmeneno) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Není co měnit.');
    }
    dracak_vtt_vyzva_uloz($pdo, $v['id'], $d);
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'vyzva_zmena',
        dracak_vtt_vyzva_payload($info, $v['druh'], $v['id'], $v['token_id'], $v['cil_token_id'], ['zmeneno' => $zmeneno]), (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
echo json_encode(['ok' => true, 'udalost_id' => $eventId], JSON_UNESCAPED_UNICODE);
