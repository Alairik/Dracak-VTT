<?php
declare(strict_types=1);

// VTT — souboj v rozšířeném (hex) systému: fáze kola, účastníci a úmysly,
// dvě půlky kola, tahy a akce. Rozhodnutí a průběh: docs/navrh-souboje-v1.md.
//
// Kolo má tři fáze (kolo_stav.faze):
//   umysly     — každý hlásí, co zamýšlí (kouzlení, útok…), kvůli bonusům k
//                iniciativě (content/pravidla-hrac.html, h1622)
//   iniciativa — každý hodí 1k6 (tlačítko -> 3D kostka, nebo ručně), PJ za
//                nestvůry; výsledek se přepočte na počet akcí (HOMEBREW, po třech)
//   kolo       — tahy podle iniciativy ve dvou půlkách (HOMEBREW, b10819):
//                v 1. půlce každý odehraje větší půlku svých akcí, ve 2. zbytek
//
// Potřebuje ručně spuštěné SQL 0085 (kolo_stav.faze/polovina/prekvapeni,
// tabulka kolo_ucastnik) — viz dracak_vtt_tokeny_funkce()['souboj']. Bez něj
// zůstává dnešní jednoduchá iniciativa (hra/api/iniciativa_hod.php,
// dalsi_tah.php).

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_iniciativa.php';
require_once __DIR__ . '/vtt_pohyblivost.php';
require_once __DIR__ . '/vtt_mapa_stav.php';
require_once __DIR__ . '/vtt_hexy.php';
require_once __DIR__ . '/vtt_souboj_pravidla.php';

const DRACAK_VTT_UMYSLY = [
    'kouzleni' => 'Kouzlení',
    'strelba' => 'Střelba',
    'utok' => 'Útok',
    'pohyb' => 'Pohyb',
    'jina' => 'Jiná činnost',
    'cekani' => 'Čekání',
    'utek' => 'Útěk',
];
const DRACAK_VTT_STRANY = ['druzina' => 'Družina', 'nepratele' => 'Nepřátelé'];
const DRACAK_VTT_PREKVAPENI = ['zadne', 'druzina', 'nepratele', 'obe'];
// Druhy akcí (h1623) — ve kroku 1 slouží jen jako popisek v logu, každá
// stojí jednu akci; skutečné vyhodnocení (pohyb, útok…) přijde v dalších krocích.
const DRACAK_VTT_AKCE_DRUHY = [
    'cekani' => 'čekání', 'utok' => 'útok', 'obrana' => 'obrana', 'krok' => 'krok', 'otocka' => 'otočka',
    'krok_utok' => 'krok a útok', 'otocka_utok' => 'otočka a útok', 'obrana_krok' => 'obrana a krok',
    'jina' => 'jiná činnost', 'kouzleni' => 'kouzlení', 'beh' => 'běh', 'sprint' => 'sprint',
];
function dracak_vtt_souboj_funkce(): bool
{
    return dracak_vtt_tokeny_funkce()['souboj'];
}

// Odpověď s chybou a konec skriptu (společné pro endpointy souboje).
function dracak_vtt_souboj_chyba(int $kod, string $zprava): void
{
    http_response_code($kod);
    echo json_encode(['error' => $zprava], JSON_UNESCAPED_UNICODE);
    exit;
}

// Společný začátek endpointu souboje: přihlášení, mapa, přístup do světa,
// případně jen PJ. Vrací [mapa, svetId, jePj, user].
function dracak_vtt_souboj_zacatek(int $mapaId, bool $jenPj = false): array
{
    header('Content-Type: application/json; charset=utf-8');
    $user = dracak_require_login();
    if (!dracak_vtt_souboj_funkce()) {
        dracak_vtt_souboj_chyba(409, 'Souboj s fázemi ještě není na serveru zapnutý (chybí ruční SQL 0085).');
    }
    $stmt = dracak_db()->prepare('SELECT * FROM mapy WHERE id = ?');
    $stmt->execute([$mapaId]);
    $mapa = $stmt->fetch();
    if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
        dracak_vtt_souboj_chyba(404, 'Mapa nenalezena.');
    }
    $svetId = (int)$mapa['svet_id'];
    $jePj = dracak_vtt_je_pj_sveta($user, $svetId);
    if ($jenPj && !$jePj) {
        dracak_vtt_souboj_chyba(403, 'Tohle smí jen PJ.');
    }
    return [$mapa, $svetId, $jePj, $user];
}

// Vstup JSON z těla požadavku.
function dracak_vtt_souboj_vstup(): array
{
    return json_decode((string)file_get_contents('php://input'), true) ?: [];
}

// Řádek kolo_stav (s výchozími hodnotami nových sloupců) nebo null, když na
// mapě žádný souboj neběží. $zamknout = SELECT … FOR UPDATE (uvnitř transakce),
// ať si hráč a PJ nepřepíšou tah navzájem.
function dracak_vtt_souboj_nacti(PDO $pdo, int $mapaId, bool $zamknout = false): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM kolo_stav WHERE mapa_id = ?' . ($zamknout ? ' FOR UPDATE' : ''));
    $stmt->execute([$mapaId]);
    $r = $stmt->fetch();
    if (!$r) {
        return null;
    }
    return [
        'cislo_kola' => (int)$r['cislo_kola'],
        'aktivni_token_id' => $r['aktivni_token_id'] !== null ? (int)$r['aktivni_token_id'] : null,
        'faze' => (string)($r['faze'] ?? 'kolo'),
        'polovina' => (int)($r['polovina'] ?? 1),
        'prekvapeni' => (string)($r['prekvapeni'] ?? 'zadne'),
    ];
}

// Smí v souboji token hýbat jen akcemi (ne tažením)? Ano, když na mapě běží
// souboj s fázemi a mapa je hexová — PJ si ale pořád hýbe volně (úprava
// postavení, odhození do zdi…).
function dracak_vtt_pohyb_pres_akce(PDO $pdo, array $mapa): bool
{
    if (!dracak_vtt_souboj_funkce() || dracak_vtt_hex_rozmery($mapa) === null) {
        return false;
    }
    return dracak_vtt_souboj_nacti($pdo, (int)$mapa['id']) !== null;
}

// Údaje o tokenech mapy, které souboj potřebuje: jméno, kdo je ovládá, zda
// jde o postavu hráče (její iniciativu smějí vidět všichni hráči) a výchozí
// strana. Klíč = id tokenu.
function dracak_vtt_souboj_tokeny_info(PDO $pdo, int $svetId, int $mapaId): array
{
    $stmt = $pdo->prepare('SELECT pj_ucet_id FROM svet WHERE id = ?');
    $stmt->execute([$svetId]);
    $pjUcetId = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT t.*, p.nazev AS postava_nazev, p.vlastnik_ucet_id, u.role AS vlastnik_role,
                ni.nazev_instance, ni.nestvura_id
         FROM tokeny t
         LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
         LEFT JOIN ucty u ON u.id = p.vlastnik_ucet_id
         LEFT JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = "nestvura_instance"
         WHERE t.mapa_id = ?'
    );
    $stmt->execute([$mapaId]);
    $info = [];
    foreach ($stmt->fetchAll() as $t) {
        $jePostava = $t['typ_entity'] === 'postava';
        $jeNestvura = $t['typ_entity'] === 'nestvura_instance';
        $nazevRucne = isset($t['nazev']) && $t['nazev'] !== null && $t['nazev'] !== '' ? (string)$t['nazev'] : null;
        $label = $nazevRucne ?? ($jePostava ? (string)$t['postava_nazev'] : ($jeNestvura ? (string)$t['nazev_instance'] : 'Token'));
        $vlastnik = $jePostava && $t['vlastnik_ucet_id'] !== null ? (int)$t['vlastnik_ucet_id'] : null;
        // Postava patří hráči, ne PJ: vlastník je někdo jiný než PJ světa a ne admin.
        $hrac = $vlastnik !== null && $vlastnik !== $pjUcetId && ($t['vlastnik_role'] ?? '') !== 'admin';
        $info[(int)$t['id']] = [
            'id' => (int)$t['id'],
            'label' => $label,
            'typ_entity' => (string)$t['typ_entity'],
            'entita_id' => (int)$t['entita_id'],
            'svet_id' => $svetId,
            'vlastnik' => $vlastnik,
            'hrac' => $hrac,
            'viditelny' => (bool)$t['viditelny_hracum'],
            'skupina' => $jeNestvura ? 'n' . (int)$t['nestvura_id'] : 't' . (int)$t['id'],
            'strana_vychozi' => $jePostava ? 'druzina' : 'nepratele',
        ];
    }
    return $info;
}

// Smí hráč vidět iniciativu a úmysly tohohle tokenu? Jen postavy hráčů, které
// jsou na mapě vidět; nestvůry a postavy ovládané PJ jsou tajné.
function dracak_vtt_souboj_vidi_hrac(array $info): bool
{
    return $info['hrac'] && $info['viditelny'];
}

// Smí tenhle účet za token jednat (hodit iniciativu, zadat úmysl, odehrát tah)?
function dracak_vtt_souboj_ovlada(array $user, bool $jePj, array $info): bool
{
    return $jePj || ($info['typ_entity'] === 'postava' && $info['vlastnik'] === (int)$user['id']);
}

// Kolik akcí smí tvor odehrát v tahu v dané půlce kola (domácí pravidlo, b10819):
// v 1. půlce tolik, aby mu na 2. půlku zbylo nejvýš floor(celkem / 2) — tedy
// "větší půlku"; ve 2. půlce všechno, co zbývá. Počítá se z aktuálního stavu,
// takže Rychlost (zdvojí zbývající akce) a protoplazma (sníží na polovinu)
// fungují samy.
function dracak_vtt_souboj_kvota(int $celkem, int $zbyva, int $polovina): int
{
    if ($zbyva <= 0) {
        return 0;
    }
    if ($polovina >= 2) {
        return $zbyva;
    }
    return max(0, $zbyva - intdiv($celkem, 2));
}

// Odpočítá trvání efektů všem entitám, které mají na mapě token; efekt, kterému
// došlo trvání, se smaže. Vrací počet skončených efektů. Společné pro konec
// kola v souboji a ruční tlačítko "Konec kola" (hra/api/kolo_konec.php).
function dracak_vtt_efekty_odpocet(PDO $pdo, int $svetId, int $mapaId, int $ucetId): int
{
    $stmt = $pdo->prepare('SELECT DISTINCT typ_entity, entita_id FROM tokeny WHERE mapa_id = ?');
    $stmt->execute([$mapaId]);
    $expirovalo = 0;
    foreach ($stmt->fetchAll() as $pair) {
        $q = $pdo->prepare(
            'SELECT ae.id, ae.zbyva_kol, e.nazev FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id
             WHERE ae.typ_entity = ? AND ae.entita_id = ? AND ae.zbyva_kol IS NOT NULL'
        );
        $q->execute([$pair['typ_entity'], $pair['entita_id']]);
        foreach ($q->fetchAll() as $ae) {
            $noveKolo = (int)$ae['zbyva_kol'] - 1;
            if ($noveKolo <= 0) {
                $pdo->prepare('DELETE FROM aktivni_efekty WHERE id = ?')->execute([$ae['id']]);
                dracak_vtt_log_event($svetId, $mapaId, 'efekt_konci', [
                    'typ_entity' => $pair['typ_entity'],
                    'entita_id' => $pair['entita_id'],
                    'efekt_nazev' => $ae['nazev'],
                ], $ucetId);
                $expirovalo++;
            } else {
                $pdo->prepare('UPDATE aktivni_efekty SET zbyva_kol = ? WHERE id = ?')->execute([$noveKolo, $ae['id']]);
            }
        }
    }
    return $expirovalo;
}

// Konec kola: čísla kola +1, další kolo začíná úmysly, iniciativa a úmysly se
// smažou (h1622 — hází se na začátku KAŽDÉHO kola), překvapení platilo jen v
// prvním kole, odpočítá se trvání efektů. Volat uvnitř transakce.
function dracak_vtt_souboj_nove_kolo(PDO $pdo, int $svetId, int $mapaId, int $ucetId, array $stav): int
{
    $nove = $stav['cislo_kola'] + 1;
    $pdo->prepare(
        'UPDATE kolo_stav SET cislo_kola = ?, aktivni_token_id = NULL, faze = "umysly", polovina = 1, prekvapeni = "zadne"
         WHERE mapa_id = ?'
    )->execute([$nove, $mapaId]);
    $pdo->prepare('DELETE FROM kolo_iniciativa WHERE mapa_id = ?')->execute([$mapaId]);
    $pdo->prepare('UPDATE kolo_ucastnik SET umysl = NULL, umysl_cil_token_id = NULL WHERE mapa_id = ?')->execute([$mapaId]);
    $efekty = dracak_vtt_efekty_odpocet($pdo, $svetId, $mapaId, $ucetId);
    dracak_vtt_log_event($svetId, $mapaId, 'kolo_nove', [
        'predchozi_kolo' => $stav['cislo_kola'],
        'nove_kolo' => $nove,
        'efekty_konci' => $efekty,
    ], $ucetId);
    return $nove;
}

// Posune tah tam, kam patří, poté co někdo spotřeboval akci nebo ukončil tah:
//  - aktivní tvor má v téhle půlce ještě kvótu -> zůstává na tahu,
//  - jinak jde na řadu další v pořadí s kvótou,
//  - nikdo další v 1. půlce -> začíná 2. půlka (znovu od nejvyšší iniciativy),
//  - nikdo další ve 2. půlce -> konec kola.
// Volat uvnitř transakce se zamčeným kolo_stav. Vrací
// ['kolo_konec' => bool, 'aktivni_token_id' => ?int, 'polovina' => int, 'cislo_kola' => int].
function dracak_vtt_souboj_posun(PDO $pdo, int $svetId, int $mapaId, int $ucetId): array
{
    for ($pokus = 0; $pokus < 3; $pokus++) {
        $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
        $poradi = dracak_vtt_iniciativa_poradi($pdo, $mapaId);
        $polovina = $stav['polovina'];
        $aktivni = $stav['aktivni_token_id'];
        $idx = -1;
        foreach ($poradi as $i => $r) {
            if ($r['token_id'] === $aktivni) {
                $idx = $i;
                break;
            }
        }
        if ($aktivni !== null && $idx >= 0
            && dracak_vtt_souboj_kvota($poradi[$idx]['akce_celkem'], $poradi[$idx]['akce_zbyvajici'], $polovina) > 0) {
            return ['kolo_konec' => false, 'aktivni_token_id' => $aktivni, 'polovina' => $polovina, 'cislo_kola' => $stav['cislo_kola']];
        }
        for ($i = $idx + 1; $i < count($poradi); $i++) {
            if (dracak_vtt_souboj_kvota($poradi[$i]['akce_celkem'], $poradi[$i]['akce_zbyvajici'], $polovina) > 0) {
                $novy = $poradi[$i]['token_id'];
                $pdo->prepare('UPDATE kolo_stav SET aktivni_token_id = ? WHERE mapa_id = ?')->execute([$novy, $mapaId]);
                dracak_vtt_log_event($svetId, $mapaId, 'tah_zmena', [
                    'predchozi_token_id' => $aktivni,
                    'novy_token_id' => $novy,
                    'cislo_kola' => $stav['cislo_kola'],
                    'polovina' => $polovina,
                ], $ucetId);
                return ['kolo_konec' => false, 'aktivni_token_id' => $novy, 'polovina' => $polovina, 'cislo_kola' => $stav['cislo_kola']];
            }
        }
        if ($polovina < 2) {
            $pdo->prepare('UPDATE kolo_stav SET polovina = 2, aktivni_token_id = NULL WHERE mapa_id = ?')->execute([$mapaId]);
            dracak_vtt_log_event($svetId, $mapaId, 'tah_zmena', [
                'predchozi_token_id' => $aktivni,
                'novy_token_id' => null,
                'cislo_kola' => $stav['cislo_kola'],
                'polovina' => 2,
            ], $ucetId);
            continue;
        }
        $nove = dracak_vtt_souboj_nove_kolo($pdo, $svetId, $mapaId, $ucetId, $stav);
        return ['kolo_konec' => true, 'aktivni_token_id' => null, 'polovina' => 1, 'cislo_kola' => $nove];
    }
    return ['kolo_konec' => false, 'aktivni_token_id' => null, 'polovina' => 2, 'cislo_kola' => 0];
}

// Modifikátor k hodu na iniciativu = součet vybraných řádků Tabulky bonusů a
// postihů (str. 78, iniciativa_bonusy) + automatické (únava a naložení
// postavy, překvapení, úmysl kouzlení) + ruční úprava. Popisy jdou do logu,
// ať je vidět, PROČ vyšlo dané číslo. $navic = další popisy řádků tabulky,
// které se přidají automaticky (Překvapení, Kouzlení).
function dracak_vtt_iniciativa_modifikator(PDO $pdo, array $token, array $bonusIds, int $jinyBonus, array $navic = []): array
{
    $auto = ['popisy' => [], 'skupiny' => ['unava' => false, 'nalozeni' => false], 'upozorneni' => []];
    if ($token['typ_entity'] === 'postava') {
        $stmt = $pdo->prepare('SELECT * FROM postavy WHERE id = ?');
        $stmt->execute([(int)$token['entita_id']]);
        $postavaRow = $stmt->fetch();
        if ($postavaRow) {
            $auto = dracak_vtt_iniciativa_auto_bonusy($pdo, $postavaRow);
        }
    }
    $vybrane = [];
    $ignorovane = [];
    if ($bonusIds) {
        $placeholders = implode(',', array_fill(0, count($bonusIds), '?'));
        $stmt = $pdo->prepare("SELECT id, popis, bonus FROM iniciativa_bonusy WHERE id IN ($placeholders)");
        $stmt->execute($bonusIds);
        foreach ($stmt->fetchAll() as $b) {
            $pokryto = ($auto['skupiny']['unava'] && in_array($b['popis'], DRACAK_VTT_INICIATIVA_UNAVA, true))
                || ($auto['skupiny']['nalozeni'] && in_array($b['popis'], DRACAK_VTT_INICIATIVA_NALOZENI, true))
                || in_array($b['popis'], $navic, true);
            if ($pokryto) {
                $ignorovane[] = $b['popis'];
            } else {
                $vybrane[] = $b;
            }
        }
    }
    $popisyAuto = array_values(array_unique(array_merge($auto['popisy'], $navic)));
    $automaticke = [];
    if ($popisyAuto) {
        $placeholders = implode(',', array_fill(0, count($popisyAuto), '?'));
        $stmt = $pdo->prepare("SELECT id, popis, bonus FROM iniciativa_bonusy WHERE popis IN ($placeholders)");
        $stmt->execute($popisyAuto);
        $automaticke = $stmt->fetchAll();
    }
    $vsechny = array_merge($vybrane, $automaticke);
    $popisy = array_merge(array_column($vybrane, 'popis'), array_map(fn($b) => $b['popis'] . ' (automaticky)', $automaticke));
    // TINYINT sloupec (-128..127) — jen ochrana proti přetečení, ne pravidlová hranice.
    $modifikator = max(-100, min(100, (int)array_sum(array_column($vsechny, 'bonus')) + $jinyBonus));
    return [
        'modifikator' => $modifikator,
        'popisy' => $popisy,
        'automaticke' => array_column($automaticke, 'popis'),
        'ignorovano' => $ignorovane,
        'upozorneni' => $auto['upozorneni'],
    ];
}

// Popisy řádků tabulky bonusů, které se u daného účastníka přičtou samy:
// překvapení (jen v 1. kole a jen překvapené straně) a kouzlení (úmysl z
// fáze úmyslů — h1622: bonus jen tomu, kdo to před hodem ohlásil).
function dracak_vtt_souboj_auto_popisy(array $stav, ?array $ucastnik): array
{
    $popisy = [];
    if ($ucastnik !== null) {
        $strana = (string)$ucastnik['strana'];
        if ($stav['cislo_kola'] === 1 && ($stav['prekvapeni'] === 'obe' || $stav['prekvapeni'] === $strana)) {
            $popisy[] = 'Překvapení';
        }
        if (($ucastnik['umysl'] ?? null) === 'kouzleni') {
            $popisy[] = 'Kouzlení';
        }
    }
    return $popisy;
}

// Seřazený seznam účastníků pro klienta. Ve fázích úmyslů a iniciativy je
// seznam jen výčet (kdo se účastní, úmysly, kdo už hodil); ve fázi kola je to
// pořadí tahů. Hráč nesmí vidět nic o skrytých účastnících (nestvůry, postavy
// PJ) — ani že existují v pořadí; PJ vidí všechno.
// Vrací ['kolo' => …, 'iniciativa' => [pořadí tahů], 'ucastnici' => [výčet]].
function dracak_vtt_souboj_klient(PDO $pdo, array $user, array $mapa, bool $jePj, array $stav): array
{
    $mapaId = (int)$mapa['id'];
    $svetId = (int)$mapa['svet_id'];
    $info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);

    $stmt = $pdo->prepare('SELECT * FROM kolo_ucastnik WHERE mapa_id = ?');
    $stmt->execute([$mapaId]);
    $ucastniciDb = [];
    foreach ($stmt->fetchAll() as $r) {
        $ucastniciDb[(int)$r['token_id']] = $r;
    }
    $poradi = dracak_vtt_iniciativa_poradi($pdo, $mapaId);
    $kiPoTokenu = [];
    foreach ($poradi as $r) {
        $kiPoTokenu[$r['token_id']] = $r;
    }
    $polovina = $stav['polovina'];

    $viditelny = function (int $tokenId) use ($info, $jePj): bool {
        return isset($info[$tokenId]) && ($jePj || dracak_vtt_souboj_vidi_hrac($info[$tokenId]));
    };
    $sestav = function (int $tokenId) use ($info, $ucastniciDb, $kiPoTokenu, $user, $jePj, $polovina): array {
        $i = $info[$tokenId];
        $u = $ucastniciDb[$tokenId] ?? null;
        $ki = $kiPoTokenu[$tokenId] ?? null;
        $cilId = $u && $u['umysl_cil_token_id'] !== null ? (int)$u['umysl_cil_token_id'] : null;
        return [
            'token_id' => $tokenId,
            'label' => $i['label'],
            'strana' => $u ? (string)$u['strana'] : $i['strana_vychozi'],
            'skupina' => $i['skupina'],
            'ovladam' => dracak_vtt_souboj_ovlada($user, $jePj, $i),
            'skryta' => !dracak_vtt_souboj_vidi_hrac($i),
            'umysl' => $u ? $u['umysl'] : null,
            'umysl_cil_token_id' => $cilId,
            'umysl_cil_label' => $cilId !== null && isset($info[$cilId]) ? $info[$cilId]['label'] : null,
            'hozeno' => $ki !== null,
            'hod' => $ki ? $ki['hod'] : null,
            'modifikator' => $ki ? $ki['modifikator'] : null,
            'vysledek' => $ki ? $ki['vysledek'] : null,
            'akce_celkem' => $ki ? $ki['akce_celkem'] : null,
            'akce_zbyvajici' => $ki ? $ki['akce_zbyvajici'] : null,
            'kvota' => $ki ? dracak_vtt_souboj_kvota($ki['akce_celkem'], $ki['akce_zbyvajici'], $polovina) : null,
        ];
    };

    // Výčet: všichni z kolo_ucastnik + (pro stará kola bez zahájení) všichni s hodem.
    $idsUcastniku = array_unique(array_merge(array_keys($ucastniciDb), array_keys($kiPoTokenu)));
    $ucastnici = [];
    foreach ($info as $tokenId => $_) {
        if (in_array($tokenId, $idsUcastniku, true) && $viditelny($tokenId)) {
            $ucastnici[] = $sestav($tokenId);
        }
    }
    $iniciativa = [];
    foreach ($poradi as $r) {
        if ($viditelny($r['token_id'])) {
            $iniciativa[] = $sestav($r['token_id']);
        }
    }

    $aktivniId = $stav['aktivni_token_id'];
    $aktivniViditelny = $aktivniId !== null && isset($info[$aktivniId]) && ($jePj || $info[$aktivniId]['viditelny']);
    $kvotaAktivniho = null;
    if ($aktivniId !== null && isset($kiPoTokenu[$aktivniId])) {
        $k = $kiPoTokenu[$aktivniId];
        $kvotaAktivniho = dracak_vtt_souboj_kvota($k['akce_celkem'], $k['akce_zbyvajici'], $polovina);
    }
    $kolo = [
        'cislo_kola' => $stav['cislo_kola'],
        'aktivni_token_id' => $aktivniViditelny ? $aktivniId : null,
        'aktivni_label' => $aktivniViditelny ? $info[$aktivniId]['label'] : null,
        'aktivni_skryty' => $aktivniId !== null && !$aktivniViditelny,
        'ovladam_aktivniho' => $aktivniId !== null && isset($info[$aktivniId])
            && dracak_vtt_souboj_ovlada($user, $jePj, $info[$aktivniId]),
        'kvota' => $kvotaAktivniho,
        'faze' => $stav['faze'],
        'polovina' => $polovina,
        'prekvapeni' => $stav['prekvapeni'],
        'novy' => true,
        // Hráč se v souboji na hexové mapě hýbe akcemi (Krok, Otočka), ne tažením.
        'pohyb_akcemi' => dracak_vtt_hex_rozmery($mapa) !== null,
        // Pohybové akce, které únava aktivní postavě zakazuje (běh, sprint) — tlačítka se zamknou.
        'aktivni_zakazy' => $aktivniId !== null && isset($info[$aktivniId])
            ? dracak_vtt_souboj_zakazy_pohybu($pdo, $info[$aktivniId]['typ_entity'], $info[$aktivniId]['entita_id']) : [],
    ];
    return ['kolo' => $kolo, 'iniciativa' => $iniciativa, 'ucastnici' => $ucastnici];
}

// Pohybové akce, které únava postavě zakazuje (tabulka únavy h1634: unavená nesmí
// sprintovat, vyčerpaná ani běhat). Prázdný seznam u nestvůr a postav bez odolnosti
// (vytrvalost se nehádá). Klíče "beh" / "sprint" jako v tabulce souboj_pohyb.
function dracak_vtt_souboj_zakazy_pohybu(PDO $pdo, string $typEntity, int $entitaId): array
{
    if ($typEntity !== 'postava') {
        return [];
    }
    $q = $pdo->prepare('SELECT * FROM postavy WHERE id = ?');
    $q->execute([$entitaId]);
    $postava = $q->fetch();
    $stupen = $postava ? dracak_vtt_postava_unava_stupen($postava) : null;
    return $stupen === null ? [] : array_values(array_intersect(['beh', 'sprint'], $stupen['zakazy']));
}
