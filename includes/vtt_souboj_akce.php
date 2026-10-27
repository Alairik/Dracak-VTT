<?php
declare(strict_types=1);

// Provedení akce tvora na tahu (společné pro hra/api/tah_akce.php a pro pokračování po úspěšném hodu
// proti pasti u čtvrtého hexu, includes/vtt_souboj_past.php).
//
// Tvor na tahu odehrál akci — ubude mu z "akce zbývající" a (na hexové mapě) provede se její mapová část:
// krok / běh / sprint po cestě, otočka, útok na tvora před sebou (pravidla viz includes/vtt_souboj_pohyb.php).
// Útok založí výzvu k hodu (includes/vtt_souboj_utok.php): tah se přeruší, útočník hází na útok, napadený se
// rozhodne, jak se bude bránit, a po výsledku tah pokračuje. Bez tabulky souboj_vyzva (ruční SQL) se útok
// jen zapíše, na koho se útočí. Vyčerpá-li tvor kvótu tahu (větší půlka akcí v 1. půlce kola, zbytek ve 2.),
// tah jde dál.
//
// Čtvrtý hex sprintu bez dovednosti Běh na krátké vzdálenosti: cesta se ověří, ale NEprovede — založí se výzva
// k hodu proti pasti (síla, střední obtížnost, h1698 a h1686) a požadavek se uloží; po úspěšném hodu se tvoru
// nasadí efekt Běh na krátké vzdálenosti (3 kola) a uložený požadavek se provede, po neúspěchu zůstane akce nevyčerpaná.
//
// Vrací ['odpoved' => pole pro JSON, 'po_commitu' => ?callable]; při porušení pravidel skončí chybou (exit).
// $vTransakci = volající už drží transakci (nezačíná ani nepotvrzuje vlastní).

require_once __DIR__ . '/vtt_souboj_pohyb.php';
require_once __DIR__ . '/vtt_tokeny.php';
require_once __DIR__ . '/vtt_zbrane_u_sebe.php';

function dracak_vtt_souboj_proved_akci(PDO $pdo, array $mapa, bool $jePj, array $user, array $data, bool $vTransakci = false): array
{
    // Interní pole nesmí přijít od klienta.
    unset($data['predpokladej_beh']);
    $mapaId = (int)$mapa['id'];
    $svetId = (int)$mapa['svet_id'];
    $tokenId = (int)($data['token_id'] ?? 0);
    $druh = (string)($data['druh'] ?? 'jina');
    if (!isset(DRACAK_VTT_AKCE_DRUHY[$druh])) {
        $druh = 'jina';
    }
    // Každé provedení stojí jednu akci (i krok, běh nebo sprint spojený s útokem).
    $opakovani = max(1, min(9, (int)($data['pocet'] ?? 1)));
    $pocet = $opakovani;

    $info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
    if (!isset($info[$tokenId]) || !dracak_vtt_souboj_ovlada($user, $jePj, $info[$tokenId])) {
        dracak_vtt_souboj_chyba(403, 'Za tenhle token jednat nesmíš.');
    }
    $vlastniTx = !$vTransakci;
    $konec = function (int $kod, string $text) use ($pdo, $vlastniTx): void {
        if ($vlastniTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        dracak_vtt_souboj_chyba($kod, $text);
    };

    if ($vlastniTx) {
        $pdo->beginTransaction();
    }
    try {
        $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
        if ($stav === null || $stav['faze'] !== 'kolo') {
            $konec(422, 'Teď neběží tahy.');
        }
        if ($stav['aktivni_token_id'] !== $tokenId) {
            $konec(422, 'Tenhle tvor teď není na tahu.');
        }
        // Při pokračování po hodu proti pasti čeká v tabulce právě tahle výzva (stav "hozeno"), jinak nesmí čekat žádná.
        if (!$vTransakci && dracak_vtt_vyzva_otevrene($pdo, $mapaId, true, true)) {
            $konec(422, 'Nejdřív se musí dohrát rozehraný hod (nebo ho PJ zruší).');
        }
        if (!$jePj && dracak_vtt_souboj_vyrazen($pdo, $info[$tokenId])) {
            $konec(422, 'Vyřazený tvor už nemůže jednat.');
        }
        if (!$jePj && in_array($info[$tokenId]['typ_entity'], ['postava', 'nestvura_instance'], true)) {
            $blok = dracak_vtt_efekty_souhrn($pdo, $info[$tokenId]['typ_entity'], (int)$info[$tokenId]['entita_id'])['nemuze_jednat'];
            if ($blok !== null) {
                $konec(422, 'Tvor nemůže jednat (efekt ' . $blok . ').');
            }
        }
        $v2 = dracak_vtt_iniciativa_v2();
        $q = $pdo->prepare('SELECT akce_celkem, akce_zbyvajici' . ($v2 ? ', zavazek, zavazek_akci, zavazek_hotovo, akce_hotovo' : '')
            . ' FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ? FOR UPDATE');
        $q->execute([$mapaId, $tokenId]);
        $ki = $q->fetch();
        // Vytasení zbraně bez akce (efekt se složkou tasit_zdarma: Erythen — Rychlé tasení, Dikobraz): neubere akci a není
        // „akce kola“ (nemění slib úkonu ani pořadí tahu), jen se zapíše do logu a započítá do limitu „jednou za kolo“.
        $zdarmaEfekt = null;
        if ($druh === 'vytasit' && $info[$tokenId]['typ_entity'] === 'postava') {
            $postavaZdarma = dracak_vtt_entity_row('postava', (int)$info[$tokenId]['entita_id']);
            if ($postavaZdarma) {
                $zdarmaEfekt = dracak_vtt_zbrane_zdarma($pdo, $postavaZdarma, (int)($data['zbran_id'] ?? 0), [
                    'mapa_id' => $mapaId, 'token_id' => $tokenId, 'cislo_kola' => $stav['cislo_kola'],
                ]);
                if ($zdarmaEfekt !== null) {
                    $pocet = 0;
                }
            }
        }
        $kvota = $ki ? dracak_vtt_souboj_kvota((int)$ki['akce_celkem'], (int)$ki['akce_zbyvajici'], $stav['polovina']) : 0;
        if ($pocet > $kvota) {
            $konec(422, 'V tomhle tahu smíš odehrát už jen ' . $kvota . ' ' . ($kvota === 1 ? 'akci' : 'akcí') . '.');
        }
        // Slíbený úkon (h1622): kdo dostal bonus k iniciativě za kouzlení / vícenásobné útoky, ten mu věnuje první akci
        // kola a aspoň polovinu akcí kola. Nic se nezakazuje (rozhodnutí uživatele 2026-10-17): kdo první akci kola
        // věnuje něčemu jinému, nebo na konci kola nemá splněnou polovinu, dostane v příštím kole −3 k iniciativě
        // (efekt „Ztráta iniciativy“, dracak_vtt_iniciativa_ztrata_zaloz). Obrana před vlastním tahem se do první akce
        // nepočítá (jde o vynucenou akci), k polovině úkonu se počítá jako bojová akce. PJ slib zruší („Zrušit slib“).
        $splniUkon = false;
        $prvniPorusena = false;
        $ukonNazev = '';
        if ($ki && $v2 && !empty($ki['zavazek'])) {
            $splniUkon = in_array($druh, DRACAK_VTT_ZAVAZEK_AKCE[$ki['zavazek']] ?? [], true);
            $ukonNazev = DRACAK_VTT_ZAVAZEK_NAZVY[$ki['zavazek']] ?? (string)$ki['zavazek'];
            $prvniPorusena = $pocet > 0 && !$splniUkon && (int)$ki['akce_hotovo'] === 0;
        }

        // Mapová část akce — pravidla pohybu, čela a dosahu; při porušení skončí chybou.
        $q = $pdo->prepare('SELECT * FROM tokeny WHERE id = ? AND mapa_id = ?');
        $q->execute([$tokenId, $mapaId]);
        $token = $q->fetch();
        $q = $pdo->prepare('SELECT umysl FROM kolo_ucastnik WHERE mapa_id = ? AND token_id = ?');
        $q->execute([$mapaId, $tokenId]);
        $umysl = $q->fetchColumn();
        // Únava: unavená postava nesmí sprintovat, vyčerpaná ani běhat (hráče to zastaví, PJ smí rozhodnout jinak);
        // sprint navíc stojí bod únavy za každé použití (HB, souboj_pohyb.unava_bodu).
        $rezimPohybu = DRACAK_VTT_AKCE_REZIM_POHYBU[$druh] ?? null;
        $unavaPlus = 0;
        $unavaNova = null;
        $postavaRadek = null;
        if ($rezimPohybu !== null && $info[$tokenId]['typ_entity'] === 'postava') {
            $q = $pdo->prepare('SELECT * FROM postavy WHERE id = ? FOR UPDATE');
            $q->execute([(int)$info[$tokenId]['entita_id']]);
            $postavaRadek = $q->fetch() ?: null;
            $stupen = $postavaRadek ? dracak_vtt_postava_unava_stupen($postavaRadek) : null;
            if ($stupen !== null && !$jePj && in_array($rezimPohybu, $stupen['zakazy'], true)) {
                $konec(422, 'Postava je ' . $stupen['nazev'] . ' — ' . ($rezimPohybu === 'sprint' ? 'sprintovat' : 'běhat') . ' nesmí.');
            }
        }
        // Vytasení / výměna zbraně (h1606): stojí akci jako každý úkon; zbraň se změní hned, v téže transakci.
        $vytasenaZbran = null;
        if ($druh === 'vytasit') {
            if ($info[$tokenId]['typ_entity'] !== 'postava') {
                $konec(422, 'Zbraň vytasí jen postava.');
            }
            $q = $pdo->prepare('SELECT * FROM postavy WHERE id = ? FOR UPDATE');
            $q->execute([(int)$info[$tokenId]['entita_id']]);
            $postavaZbran = $q->fetch();
            if (!$postavaZbran) {
                $konec(404, 'Postava nenalezena.');
            }
            try {
                $vytasenaZbran = dracak_vtt_zbrane_vytasit($pdo, $postavaZbran, (int)($data['zbran_id'] ?? 0), !$jePj) ?? 'prázdné ruce';
            } catch (RuntimeException $ex) {
                $konec(422, $ex->getMessage());
            }
        }
        // Čtvrtý hex sprintu bez dovednosti: cesta se ověří, jako by dovednost platila; provede se až po úspěšném hodu.
        $vstupPohyb = $data;
        $potrebujePast = false;
        $sprintHexu = (int)(dracak_vtt_souboj_pravidla()['pohyb']['sprint']['hexu'] ?? 3);
        if ($rezimPohybu === 'sprint' && dracak_vtt_vyzva_funkce() && count($data['cesta'] ?? []) === $sprintHexu + 1
            && dracak_vtt_souboj_bonus_behu($pdo, $token) === 0) {
            $potrebujePast = true;
            $vstupPohyb['predpokladej_beh'] = true;
        }
        $pohyb = dracak_vtt_souboj_pohyb($pdo, $mapa, $token, $info, $umysl !== false ? $umysl : null, $druh, $vstupPohyb, $jePj);
        if ($potrebujePast) {
            require_once __DIR__ . '/vtt_souboj_past.php';
            $vyzvaId = dracak_vtt_past_zaloz($pdo, $mapa, $info, $token, $data, (int)$user['id']);
            if ($vlastniTx) {
                $pdo->commit();
            }
            return ['odpoved' => ['ok' => true, 'vyzva_id' => $vyzvaId, 'ceka_na_hod' => true], 'po_commitu' => null];
        }
        if ($rezimPohybu !== null && $postavaRadek !== null && array_key_exists('unava', $postavaRadek)) {
            $bodu = (int)(dracak_vtt_souboj_pravidla()['pohyb'][$rezimPohybu]['unava_bodu'] ?? 0) * $opakovani;
            if ($bodu > 0) {
                $unavaNova = min(65535, (int)$postavaRadek['unava'] + $bodu);
                $pdo->prepare('UPDATE postavy SET unava = ? WHERE id = ?')->execute([$unavaNova, (int)$postavaRadek['id']]);
                $unavaPlus = $bodu;
            }
        }

        $pohnul = false;
        if ($pohyb['mapove']) {
            $maSmer = dracak_vtt_tokeny_ma_sloupec('smer');
            if ($pohyb['x'] !== (int)$token['x'] || $pohyb['y'] !== (int)$token['y']) {
                $pdo->prepare('UPDATE tokeny SET x = ?, y = ? WHERE id = ?')->execute([$pohyb['x'], $pohyb['y'], $tokenId]);
                dracak_vtt_log_event($svetId, $mapaId, 'token_presun', [
                    'token_id' => $tokenId,
                    'x_pred' => (int)$token['x'],
                    'y_pred' => (int)$token['y'],
                    'x_po' => $pohyb['x'],
                    'y_po' => $pohyb['y'],
                ], (int)$user['id']);
                $pohnul = true;
            }
            if ($maSmer && (int)($token['smer'] ?? 0) !== $pohyb['smer']) {
                $pdo->prepare('UPDATE tokeny SET smer = ? WHERE id = ?')->execute([$pohyb['smer'], $tokenId]);
                dracak_vtt_log_event($svetId, $mapaId, 'token_zmena', [
                    'token_id' => $tokenId,
                    'zmeny' => ['smer' => $pohyb['smer']],
                    'pred' => ['smer' => (int)($token['smer'] ?? 0)],
                ], (int)$user['id']);
            }
        }

        if ($pocet === 0) {
            // vytasení bez akce — počítadla akcí se nemění
        } elseif ($v2) {
            $pdo->prepare('UPDATE kolo_iniciativa SET akce_zbyvajici = akce_zbyvajici - ?, akce_hotovo = akce_hotovo + ?, zavazek_hotovo = zavazek_hotovo + ? WHERE mapa_id = ? AND token_id = ?')
                ->execute([$pocet, $pocet, $splniUkon ? $pocet : 0, $mapaId, $tokenId]);
        } else {
            $pdo->prepare('UPDATE kolo_iniciativa SET akce_zbyvajici = akce_zbyvajici - ? WHERE mapa_id = ? AND token_id = ?')
                ->execute([$pocet, $mapaId, $tokenId]);
        }

        // Útok: založí výzvu k hodu. Rozběh (HB): dvě akce sprintu za sebou a pak útok, který smí být spojený
        // s druhým sprintem — počítá se z akcí před touhle.
        $vyzvaId = null;
        if ($pohyb['mapove'] && $pohyb['cil_token_id'] !== null && dracak_vtt_vyzva_funkce()) {
            $predSprinty = dracak_vtt_souboj_sprinty_v_rade($pdo, $mapaId, $tokenId, $stav['cislo_kola']);
            $rozbeh = ($druh === 'sprint_utok' ? $predSprinty + 1 : $predSprinty) >= 2;
            $q = $pdo->prepare('SELECT * FROM tokeny WHERE id = ?');
            $q->execute([$tokenId]);
            $vyzvaId = dracak_vtt_utok_zahaj($pdo, $mapa, $info, $q->fetch(), (int)$pohyb['cil_token_id'], $rozbeh, (int)$user['id']);
        }
        $eventId = dracak_vtt_log_event($svetId, $mapaId, 'akce_provedena', [
            'token_id' => $tokenId,
            'cislo_kola' => $stav['cislo_kola'],
            'label' => $info[$tokenId]['label'],
            'druh' => $druh,
            'druh_nazev' => DRACAK_VTT_AKCE_DRUHY[$druh],
            'zbran_nazev' => $vytasenaZbran,
            'zdarma_efekt' => $zdarmaEfekt,
            'pocet' => $pocet,
            'akce_zbyvajici' => (int)$ki['akce_zbyvajici'] - $pocet,
            'cil_token_id' => $pohyb['mapove'] ? $pohyb['cil_token_id'] : null,
            'cil_label' => $pohyb['mapove'] ? $pohyb['cil_label'] : null,
            'unava_plus' => $unavaPlus,
            'unava_nova' => $unavaNova,
            'skryta' => !dracak_vtt_souboj_vidi_hrac($info[$tokenId]),
        ], (int)$user['id']);
        // První akce kola nebyla slíbený úkon: příští kolo −3 (před posunem tahu, ať se to započítá do tohoto kola).
        if ($prvniPorusena) {
            dracak_vtt_iniciativa_ztrata_zaloz($pdo, $svetId, $mapaId, (int)$user['id'], $info[$tokenId],
                'první akce kola měla být ' . $ukonNazev . ', byla ' . DRACAK_VTT_AKCE_DRUHY[$druh]);
        }
        // Útok přeruší tah: dokud se nehodí a nevyhodnotí, tah se neposouvá (posune ho vyhodnocení).
        $posun = $vyzvaId !== null
            ? ['kolo_konec' => false, 'aktivni_token_id' => $tokenId, 'polovina' => $stav['polovina'], 'cislo_kola' => $stav['cislo_kola']]
            : dracak_vtt_souboj_posun($pdo, $svetId, $mapaId, (int)$user['id']);
        if ($vlastniTx) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($vlastniTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // Hráčova mlha se odhaluje kolem jeho postavy, která se pohnula.
    $poCommitu = empty($pohnul) ? null : function () use ($pdo, $token, $mapa, $pohyb): void {
        dracak_vtt_token_odhal_mlhu($pdo, array_merge($token, [
            'sirka_px' => $mapa['sirka_px'], 'vyska_px' => $mapa['vyska_px'], 'grid_velikost_px' => $mapa['grid_velikost_px'],
            'grid_typ' => $mapa['grid_typ'], 'grid_posun_x' => $mapa['grid_posun_x'], 'grid_posun_y' => $mapa['grid_posun_y'],
        ]), $pohyb['x'], $pohyb['y']);
    };
    return [
        'odpoved' => ['ok' => true, 'udalost_id' => $eventId, 'posun' => $posun, 'vyzva_id' => $vyzvaId, 'zdarma_efekt' => $zdarmaEfekt],
        'po_commitu' => $poCommitu,
    ];
}
