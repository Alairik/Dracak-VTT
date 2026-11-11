<?php
declare(strict_types=1);

// Přechod mezi lokacemi (plán C bod 15): PJ klikne u pinu s cílovou mapou na „Vstoupit s družinou“ — postavy družiny z aktuální mapy se přenesou
// na vstupní bod cílové mapy, aktivní mapa světa se přepne a hráči se přepnou za nimi (klient při události aktivni_mapa_zmena s příznakem vstup).
// Družina = postavy, které vlastní někdo jiný než PJ světa (stejně jako ve vidění); nestvůry, NPC postavy PJ a obecné tokeny zůstávají, kde jsou.
// Vstupní bod: pin na cílové mapě, který vede zpátky na zdrojovou mapu (dveře zpátky); jinak střed cílové mapy. Postavy se rozmístí na nejbližší volné
// buňky gridu kolem vstupu (hex i čtverec), bez gridu vedle sebe po 60 px. Zdi se nekontrolují — PJ případně doladí tažením.

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_mlha.php';
require_once __DIR__ . '/vtt_hexy.php';
require_once __DIR__ . '/vtt_mapa_body.php';

// Vstupní bod na cílové mapě: [x, y, 'pin'|'stred', id zpátečního pinu nebo null].
//
// Pin, který z cílové mapy vede zpátky na zdrojovou, je cesta ven. Když chybí, družina by se objevila
// uprostřed mapy — což uživateli vadilo (2026-11-11: „chci, aby se mi družina neobjevovala uprostřed
// mapy, pokud zapomenu, že jsem tam nedal pin zpět“). Proto se takový pin ZALOŽÍ SÁM
// (rozhodnutí uživatele: „měl by se zakládat automaticky; ten prompt by měl jít zrušit, aby pin
// nevznikl“) a PJ ho pak může jedním klepnutím zrušit nebo posunout jinam.
function dracak_vtt_prechod_vstupni_bod(PDO $pdo, array $cil, int $zdrojMapaId): array
{
    $q = $pdo->prepare('SELECT id, x, y FROM mapa_body WHERE mapa_id = ? AND cilova_mapa_id = ? ORDER BY id LIMIT 1');
    $q->execute([(int)$cil['id'], $zdrojMapaId]);
    $b = $q->fetch();
    if ($b) {
        return [(float)$b['x'], (float)$b['y'], 'pin', null];
    }
    return [((int)$cil['sirka_px']) / 2, ((int)$cil['vyska_px']) / 2, 'stred', null];
}

// Založí na cílové mapě pin zpátky na zdrojovou. Vrací [id, nazev, x, y] nebo null, když se to nepovede.
function dracak_vtt_prechod_zalozit_zpatecni(PDO $pdo, int $svetId, array $cil, int $zdrojMapaId, float $x, float $y, int $ucetId): ?array
{
    $q = $pdo->prepare('SELECT nazev FROM mapy WHERE id = ?');
    $q->execute([$zdrojMapaId]);
    $zdrojNazev = (string)($q->fetchColumn() ?: 'předchozí mapu');
    $nazev = mb_substr('Zpět: ' . $zdrojNazev, 0, 150);
    $xi = (int)round($x);
    $yi = (int)round($y);
    $bodId = dracak_vtt_mapa_bod_pridat($pdo, $svetId, (int)$cil['id'], $nazev, 'prechod', $xi, $yi, $zdrojMapaId, null, true);
    if ($bodId <= 0) {
        return null;
    }
    dracak_vtt_log_event($svetId, (int)$cil['id'], 'mapa_bod_pridan', [
        'id' => $bodId, 'nazev' => $nazev, 'typ' => 'prechod', 'x' => $xi, 'y' => $yi,
        'cilova_mapa_id' => $zdrojMapaId, 'viditelny_hracum' => true,
    ], $ucetId);
    return ['id' => $bodId, 'nazev' => $nazev, 'x' => $xi, 'y' => $yi];
}

// Až $kolik volných míst kolem [$x, $y] v pořadí od nejbližšího: [[x, y], …]. $obsazene = [[x, y], …] středy tokenů, které tam už stojí.
function dracak_vtt_prechod_volna_mista(array $cil, float $x, float $y, array $obsazene, int $kolik): array
{
    $roz = dracak_vtt_mlha_rozmery($cil);
    $sirka = (float)($cil['sirka_px'] ?? 0);
    $vyska = (float)($cil['vyska_px'] ?? 0);
    if ($roz['sloupcu'] <= 0 || $roz['radku'] <= 0) {
        // Bez gridu: v řadě po 60 px od vstupu.
        $mista = [];
        for ($i = 0; $i < $kolik; $i++) {
            $mista[] = [$x + $i * 60.0, $y];
        }
        return $mista;
    }
    $start = dracak_vtt_mlha_bunka_index($roz, $x, $y);
    if ($start === null) {
        return [];
    }
    $obsazeneBunky = [];
    foreach ($obsazene as [$ox, $oy]) {
        $b = dracak_vtt_mlha_bunka_index($roz, (float)$ox, (float)$oy);
        if ($b !== null) {
            $obsazeneBunky[$b['col'] . ',' . $b['row']] = true;
        }
    }
    $soused = static function (int $col, int $row) use ($roz): array {
        if ($roz['typ'] === 'hex') {
            $out = [];
            foreach ([0, 1, 2, 3, 4, 5] as $smer) {
                [$c, $r] = dracak_vtt_hex_soused([$col, $row], $smer);
                $out[] = [$c, $r];
            }
            return $out;
        }
        $out = [];
        foreach ([[1, 0], [0, 1], [-1, 0], [0, -1], [1, 1], [-1, 1], [-1, -1], [1, -1]] as [$dc, $dr]) {
            $out[] = [$col + $dc, $row + $dr];
        }
        return $out;
    };
    $fronta = [[(int)$start['col'], (int)$start['row']]];
    $videno = [$fronta[0][0] . ',' . $fronta[0][1] => true];
    $mista = [];
    for ($i = 0; $i < count($fronta) && count($mista) < $kolik && $i < 400; $i++) {
        [$col, $row] = $fronta[$i];
        $s = dracak_vtt_mlha_bunka_stred($roz, $row, $col);
        if ($s['x'] < 0 || $s['y'] < 0 || $s['x'] > $sirka || $s['y'] > $vyska) {
            continue;
        }
        if (!isset($obsazeneBunky[$col . ',' . $row])) {
            $mista[] = [(float)$s['x'], (float)$s['y']];
        }
        foreach ($soused($col, $row) as [$c, $r]) {
            $klic = $c . ',' . $r;
            if (!isset($videno[$klic])) {
                $videno[$klic] = true;
                $fronta[] = [$c, $r];
            }
        }
    }
    return $mista;
}

// Provede přechod. Vrací ['mapa_id', 'nazev', 'presunuto' => [token_id…], 'vstup' => 'pin'|'stred'], nebo ['chyba' => text, 'kod' => http].
function dracak_vtt_prechod_vstoupit(PDO $pdo, array $bod, int $ucetId): array
{
    $zdrojId = (int)$bod['mapa_id'];
    $svetId = (int)$bod['svet_id'];
    $cilId = (int)($bod['cilova_mapa_id'] ?? 0);
    if ($cilId <= 0) {
        return ['chyba' => 'Tenhle pin nevede na žádnou mapu.', 'kod' => 422];
    }
    $q = $pdo->prepare('SELECT * FROM mapy WHERE id = ? AND svet_id = ?');
    $q->execute([$cilId, $svetId]);
    $cil = $q->fetch();
    if (!$cil) {
        return ['chyba' => 'Cílová mapa nenalezena.', 'kod' => 404];
    }
    if ($cilId === $zdrojId) {
        return ['chyba' => 'Pin vede na tutéž mapu.', 'kod' => 422];
    }
    $q = $pdo->prepare('SELECT 1 FROM kolo_stav WHERE mapa_id = ?');
    $q->execute([$zdrojId]);
    if ($q->fetchColumn()) {
        return ['chyba' => 'Na téhle mapě běží souboj — nejdřív ho ukonči, pak družinu přenes.', 'kod' => 409];
    }
    $pjId = (int)($pdo->query('SELECT pj_ucet_id FROM svet WHERE id = ' . $svetId)->fetchColumn() ?: 0);
    $q = $pdo->prepare(
        'SELECT t.id, t.entita_id FROM tokeny t JOIN postavy p ON p.id = t.entita_id
         WHERE t.mapa_id = ? AND t.typ_entity = "postava" AND p.vlastnik_ucet_id IS NOT NULL AND p.vlastnik_ucet_id <> ? ORDER BY t.id'
    );
    $q->execute([$zdrojId, $pjId]);
    $druzina = $q->fetchAll();

    [$vx, $vy, $zpusob] = dracak_vtt_prechod_vstupni_bod($pdo, $cil, $zdrojId);
    // Cesta zpátky na cílové mapě chybí — založí se sama, ať družina nestojí uprostřed mapy.
    // PJ ji pak v hlášce může zrušit („pin nedělej“) nebo posunout jinam.
    $zpatecni = $zpusob === 'stred'
        ? dracak_vtt_prechod_zalozit_zpatecni($pdo, $svetId, $cil, $zdrojId, $vx, $vy, $ucetId)
        : null;
    $q = $pdo->prepare('SELECT id, typ_entity, entita_id, x, y FROM tokeny WHERE mapa_id = ?');
    $q->execute([$cilId]);
    $naCili = $q->fetchAll();
    $obsazene = [];
    $uzNaCili = [];   // postava => token, který už na cílové mapě stojí (vrátila se) — přesune se, ne zdvojí
    $druzinaPostavy = array_flip(array_map(static fn($t) => (int)$t['entita_id'], $druzina));
    foreach ($naCili as $t) {
        if ($t['typ_entity'] === 'postava' && isset($druzinaPostavy[(int)$t['entita_id']])) {
            $uzNaCili[(int)$t['entita_id']] = (int)$t['id'];   // postava družiny, která tu už stojí: přesune se, pole nezabírá
        } else {
            $obsazene[] = [(int)$t['x'], (int)$t['y']];        // nestvůra, obecný token i NPC postava PJ pole zabírají
        }
    }
    $mista = dracak_vtt_prechod_volna_mista($cil, $vx, $vy, $obsazene, count($druzina));

    $presunuto = [];
    $udalosti = [];
    $pdo->beginTransaction();
    try {
        foreach ($druzina as $i => $t) {
            if (!isset($mista[$i])) {
                break;   // na cílové mapě nezbylo místo — zbylé postavy zůstanou, kde jsou
            }
            [$nx, $ny] = $mista[$i];
            $nx = (int)round($nx);
            $ny = (int)round($ny);
            $postavaId = (int)$t['entita_id'];
            if (isset($uzNaCili[$postavaId])) {
                $pdo->prepare('UPDATE tokeny SET x = ?, y = ? WHERE id = ?')->execute([$nx, $ny, $uzNaCili[$postavaId]]);
                $pdo->prepare('DELETE FROM tokeny WHERE id = ?')->execute([(int)$t['id']]);
                $novyId = $uzNaCili[$postavaId];
            } else {
                $pdo->prepare('UPDATE tokeny SET mapa_id = ?, x = ?, y = ? WHERE id = ?')->execute([$cilId, $nx, $ny, (int)$t['id']]);
                $novyId = (int)$t['id'];
            }
            $presunuto[] = $novyId;
            $udalosti[] = [(int)$t['id'], $novyId, $nx, $ny, $postavaId];
        }
        $pdo->prepare('UPDATE svet SET aktivni_mapa_id = ? WHERE id = ?')->execute([$cilId, $svetId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    foreach ($udalosti as [$staryId, $novyId, $nx, $ny, $postavaId]) {
        dracak_vtt_log_event($svetId, $zdrojId, 'token_smazan', ['token_id' => $staryId], $ucetId);
        dracak_vtt_log_event($svetId, $cilId, 'token_pridan', [
            'token_id' => $novyId, 'typ_entity' => 'postava', 'entita_id' => $postavaId, 'nazev' => '', 'x' => $nx, 'y' => $ny,
        ], $ucetId);
    }
    $eventId = dracak_vtt_log_event($svetId, $cilId, 'aktivni_mapa_zmena', [
        'mapa_id' => $cilId, 'nazev' => $cil['nazev'], 'vstup' => true, 'z_mapy' => $zdrojId, 'tokeny' => $presunuto,
    ], $ucetId);
    return [
        'mapa_id' => $cilId, 'nazev' => (string)$cil['nazev'], 'presunuto' => $presunuto,
        'vstup' => $zpusob, 'udalost_id' => $eventId,
        'zpatecni_pin' => $zpatecni,   // null = cesta zpátky už existovala
    ];
}
