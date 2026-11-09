<?php
declare(strict_types=1);

// Malý deník nestvůry: čísla, která boj potřebuje (útoky s ÚČ a útočností, OČ, OZ, velikost, odolnost).
//
// Bestiář (tabulka nestvury) drží ÚČ a OČ jako volný text a u řady nestvůr jen jako vzorec bez výsledku
// („+1 + síla zbraně“, „+3 + kvalita zbroje + štít“) — to se nedá spočítat bez výbavy. PJ proto čísla jednou
// doplní a uloží (tabulka nestvura_staty_sveta z ručního SQL): platí pro všechny nestvůry stejného druhu v jeho
// světě a hody je berou samy. Bez uložených čísel se vezme, co jde z textu bestiáře (číslo za „=“, za lomítkem
// útočnost), zbytek doplní PJ u hodu jako dřív. Bestiář je tajný — hráč deník nevidí.

require_once __DIR__ . '/vtt_mapa_stav.php';
require_once __DIR__ . '/vtt_souboj_utok.php';

const DRACAK_VTT_NESTVURA_VELIKOSTI = ['A0', 'A', 'B', 'C', 'D', 'E'];

function dracak_vtt_denik_nestvur_funkce(): bool
{
    return dracak_vtt_tokeny_funkce()['denik_nestvur'];
}

// Čísla vyčtená z textu bestiáře: ['utoky' => [[nazev, uc, utocnost]], 'oc', 'oz' (vždy null — bestiář OZ nerozepisuje),
// 'velikost' (jen když je jednoznačná), 'odolnost']; 'nespocteno' = co se z textu nepodařilo (pro PJ).
function dracak_vtt_nestvura_vychozi(array $n): array
{
    $utoky = [];
    foreach (dracak_vtt_nestvura_uc($n['uc'] ?? null) as $v) {
        $utoky[] = ['nazev' => $v['popis'] !== '' ? $v['popis'] : 'útok', 'uc' => $v['uc'], 'utocnost' => $v['utocnost']];
    }
    $velikost = trim(dracak_vtt_text_cisla((string)($n['velikost'] ?? '')));
    $odolnost = preg_match('/^\d+$/', trim((string)($n['odolnost'] ?? ''))) ? (int)$n['odolnost'] : null;
    $oc = dracak_vtt_nestvura_oc($n['oc'] ?? null);
    $velikost = in_array($velikost, DRACAK_VTT_NESTVURA_VELIKOSTI, true) ? $velikost : null;
    $nespocteno = [];
    if (!$utoky) {
        $nespocteno[] = 'utoky';
    }
    if ($oc === null) {
        $nespocteno[] = 'oc';
    }
    if ($velikost === null) {
        $nespocteno[] = 'velikost';
    }
    if ($odolnost === null) {
        $nespocteno[] = 'odolnost';
    }
    return [
        'staty' => ['utoky' => $utoky, 'oc' => $oc, 'oz' => null, 'velikost' => $velikost, 'odolnost' => $odolnost],
        'nespocteno' => $nespocteno,
    ];
}

function dracak_vtt_nestvura_ulozene(PDO $pdo, int $svetId, int $nestvuraId): ?array
{
    if (!dracak_vtt_denik_nestvur_funkce()) {
        return null;
    }
    $q = $pdo->prepare('SELECT data FROM nestvura_staty_sveta WHERE svet_id = ? AND nestvura_id = ?');
    $q->execute([$svetId, $nestvuraId]);
    $json = $q->fetchColumn();
    if ($json === false) {
        return null;
    }
    $d = json_decode((string)$json, true);
    return is_array($d) ? $d : null;
}

// Jedinečné NPC (migrace 0115): vlastní čísla jen pro tenhle kus (hostinský se jménem), místo čísel druhu.
// Vrací ['jedinecny' => bool, 'data' => ?array]; null, když zásoba NPC ještě není v DB nebo instance neexistuje.
function dracak_vtt_nestvura_instance_vlastni(PDO $pdo, int $instanceId): ?array
{
    if ($instanceId <= 0 || !dracak_vtt_tokeny_funkce()['npc_zasoba']) {
        return null;
    }
    $q = $pdo->prepare('SELECT jedinecny, data FROM nestvura_instance WHERE id = ?');
    $q->execute([$instanceId]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return null;
    }
    $d = $r['data'] !== null ? json_decode((string)$r['data'], true) : null;
    return ['jedinecny' => (bool)$r['jedinecny'], 'data' => is_array($d) ? $d : null];
}

// Čísla pro boj: uložená PJ mají přednost před textem bestiáře (po polích). Vrací
// ['staty' => …, 'ulozeno' => bool, 'nespocteno' => [pole, která zůstala prázdná], 'vychozi' => staty z textu].
// $instanceId: u jedinečného NPC (migrace 0115) se místo čísel druhu vezmou jeho vlastní.
function dracak_vtt_nestvura_staty(PDO $pdo, int $svetId, int $nestvuraId, array $radekNestvury, ?int $instanceId = null): array
{
    $vychozi = dracak_vtt_nestvura_vychozi($radekNestvury);
    $staty = $vychozi['staty'];
    $vlastni = $instanceId !== null ? dracak_vtt_nestvura_instance_vlastni($pdo, $instanceId) : null;
    $jedinecny = $vlastni !== null && $vlastni['jedinecny'];
    $ulozene = $jedinecny ? $vlastni['data'] : dracak_vtt_nestvura_ulozene($pdo, $svetId, $nestvuraId);
    if ($ulozene !== null) {
        foreach (['utoky', 'oc', 'oz', 'velikost', 'odolnost'] as $k) {
            if (array_key_exists($k, $ulozene)) {
                $staty[$k] = $ulozene[$k];
            }
        }
    }
    $nespocteno = [];
    if (!$staty['utoky']) {
        $nespocteno[] = 'utoky';
    }
    foreach (['oc', 'velikost', 'odolnost'] as $k) {
        if ($staty[$k] === null) {
            $nespocteno[] = $k;
        }
    }
    return [
        'staty' => $staty,
        'ulozeno' => $ulozene !== null,
        'nespocteno' => $nespocteno,
        'vychozi' => $vychozi['staty'],
        // true = čísla platí jen pro tenhle kus (jedinečné NPC), false = pro celý druh ve světě
        'jedinecny' => $jedinecny,
    ];
}

function dracak_vtt_nestvura_radek(PDO $pdo, int $nestvuraId): ?array
{
    $q = $pdo->prepare('SELECT * FROM nestvury WHERE id = ?');
    $q->execute([$nestvuraId]);
    return $q->fetch() ?: null;
}

// Ověří a očistí čísla z formuláře. Vrací [staty, null] nebo [null, text chyby].
function dracak_vtt_nestvura_over_staty($vstup): array
{
    if (!is_array($vstup)) {
        return [null, 'Chybí čísla k uložení.'];
    }
    $cislo = static function ($v, int $min, int $max): ?int {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            throw new InvalidArgumentException('Číslo mimo rozsah.');
        }
        $n = (int)$v;
        if ($n < $min || $n > $max) {
            throw new InvalidArgumentException('Číslo mimo rozsah (' . $min . ' až ' . $max . ').');
        }
        return $n;
    };
    try {
        $utoky = [];
        foreach (is_array($vstup['utoky'] ?? null) ? $vstup['utoky'] : [] as $u) {
            if (!is_array($u)) {
                continue;
            }
            $uc = $cislo($u['uc'] ?? null, 0, 60);
            if ($uc === null) {
                continue;
            }
            $nazev = trim((string)($u['nazev'] ?? ''));
            $utoky[] = [
                'nazev' => $nazev !== '' ? mb_substr($nazev, 0, 60) : 'útok',
                'uc' => $uc,
                'utocnost' => $cislo($u['utocnost'] ?? 0, -20, 20) ?? 0,
            ];
        }
        if (count($utoky) > 8) {
            return [null, 'Nejvýš 8 druhů útoku.'];
        }
        $velikost = (string)($vstup['velikost'] ?? '');
        if ($velikost !== '' && !in_array($velikost, DRACAK_VTT_NESTVURA_VELIKOSTI, true)) {
            return [null, 'Velikost je A0, A, B, C, D nebo E.'];
        }
        return [[
            'utoky' => $utoky,
            'oc' => $cislo($vstup['oc'] ?? null, 0, 60),
            'oz' => $cislo($vstup['oz'] ?? null, -10, 10),
            'velikost' => $velikost !== '' ? $velikost : null,
            'odolnost' => $cislo($vstup['odolnost'] ?? null, 0, 99),
        ], null];
    } catch (InvalidArgumentException $e) {
        return [null, $e->getMessage()];
    }
}

function dracak_vtt_nestvura_uloz(PDO $pdo, int $svetId, int $nestvuraId, array $staty): void
{
    $pdo->prepare(
        'INSERT INTO nestvura_staty_sveta (svet_id, nestvura_id, data) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE data = VALUES(data)'
    )->execute([$svetId, $nestvuraId, json_encode($staty, JSON_UNESCAPED_UNICODE)]);
}

function dracak_vtt_nestvura_vrat(PDO $pdo, int $svetId, int $nestvuraId): void
{
    $pdo->prepare('DELETE FROM nestvura_staty_sveta WHERE svet_id = ? AND nestvura_id = ?')->execute([$svetId, $nestvuraId]);
}

// Uloží čísla jen pro JEDEN kus (migrace 0115) a tím ho udělá jedinečným: vyplněný deník = jedinečné NPC
// (zadání uživatele 2026-11-09). Ostatní kusy téhož druhu zůstanou na číslech druhu.
function dracak_vtt_nestvura_uloz_instanci(PDO $pdo, int $instanceId, array $staty): void
{
    $pdo->prepare('UPDATE nestvura_instance SET jedinecny = 1, data = ? WHERE id = ?')
        ->execute([json_encode($staty, JSON_UNESCAPED_UNICODE), $instanceId]);
}

// Zahodí vlastní čísla kusu — zase platí čísla druhu.
function dracak_vtt_nestvura_vrat_instanci(PDO $pdo, int $instanceId): void
{
    $pdo->prepare('UPDATE nestvura_instance SET jedinecny = 0, data = NULL WHERE id = ?')->execute([$instanceId]);
}

// Smysly nestvůry podle bestiáře (migrace 0116): dosah infravidění v sázích a jestli ji infravidění naopak nerozezná.
// Zdroj jsou pravidla, ne ruční zadání na tokenu — proto se tím předvyplňuje nový token (hra/api/token_pridat.php).
// ['infra' => ?int, 'infra_ma' => bool, 'studeny' => bool]; 'infra_ma' bez 'infra' = kniha říká, že tvor infravidění má,
// ale dosah neuvádí (Bazilišek, Krysodlak, Skřet) — dosah doplní PJ.
function dracak_vtt_nestvura_smysly(PDO $pdo, int $nestvuraId): array
{
    $out = ['infra' => null, 'infra_ma' => false, 'studeny' => false];
    try {
        $q = $pdo->prepare(
            'SELECT z.nazev, ns.hodnota FROM nestvura_schopnosti ns
             JOIN zvlastni_schopnosti z ON z.id = ns.schopnost_id
             WHERE ns.nestvura_id = ? AND z.nazev IN ("Infravidění", "Nerozezná ho infravidění")'
        );
        $q->execute([$nestvuraId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['nazev'] === 'Infravidění') {
                $out['infra_ma'] = true;
                if ($r['hodnota'] !== null && preg_match('/^\d+$/', trim((string)$r['hodnota']))) {
                    $out['infra'] = (int)$r['hodnota'];
                }
            } else {
                $out['studeny'] = true;
            }
        }
    } catch (Throwable $e) {
        // sloupec hodnota ještě není (ruční SQL nespuštěné) — token se nepředvyplní, zadá se ručně jako dřív
    }
    return $out;
}

// Smysly rasy podle pravidel (migrace 0117): dosah infravidění v sázích. Trpaslík 20 sáhů (hrac b790);
// ostatní hratelné rasy infravidění nemají. Předvyplňuje nový token postavy (hra/api/token_pridat.php).
function dracak_vtt_rasa_smysly(PDO $pdo, int $rasaId): array
{
    $out = ['infra' => null];
    if ($rasaId <= 0) {
        return $out;
    }
    try {
        $q = $pdo->prepare(
            'SELECT rs.hodnota FROM rasa_schopnosti rs
             JOIN zvlastni_schopnosti z ON z.id = rs.schopnost_id
             WHERE rs.rasa_id = ? AND z.nazev = "Infravidění" LIMIT 1'
        );
        $q->execute([$rasaId]);
        $h = $q->fetchColumn();
        if ($h !== false && $h !== null && preg_match('/^\d+$/', trim((string)$h))) {
            $out['infra'] = (int)$h;
        }
    } catch (Throwable $e) {
        // sloupec hodnota ještě není (ruční SQL nespuštěné) — token se nepředvyplní, zadá se ručně jako dřív
    }
    return $out;
}
