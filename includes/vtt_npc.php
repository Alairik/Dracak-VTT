<?php
declare(strict_types=1);

// NPC jako bytost světa a zásoba tokenů (migrace 0115, ruční SQL blok J).
//
// Dřív nestvůra/NPC vznikla až položením tokenu na mapu. Od 0115 žije NPC ve světě (nestvura_instance.svet_id)
// a mapa je jen místo, kde právě stojí (mapa_id smí být prázdné = v zásobě). Z toho plyne:
//  * PJ si vyrobí zásobu dopředu („10× Goblin“) a pak je jen klade tažením na mapu;
//  * seznam všech NPC ve světě včetně nepoužitých má kde být;
//  * dvě úrovně NPC (rozhodnutí uživatele 2026-11-09): jedinecny = 1 má vlastní čísla pro boj v nestvura_instance.data,
//    jedinecny = 0 bere čísla svého druhu z nestvura_staty_sveta jako dosud (město s 50 měšťany se nelandí po jednom).
//
// Sejmutí tokenu z mapy NPC nemaže — vrátí ho do zásoby (mapa_id = NULL), takže PJ nepřijde o nastavená čísla
// a může ho položit znovu. Smazat NPC nadobro jde v liště zásoby.

require_once __DIR__ . '/vtt.php';
require_once __DIR__ . '/vtt_mapa_stav.php';
require_once __DIR__ . '/vtt_tokeny.php';

// Nejvíc kusů na jedno zmáčknutí „Vyrobit“ — strop proti překlepu v počtu (a proti tisícům řádků z jednoho požadavku).
const DRACAK_VTT_NPC_DAVKA_MAX = 50;

function dracak_vtt_npc_funkce(): bool
{
    return dracak_vtt_tokeny_funkce()['npc_zasoba'];
}

// Další jméno kusu v rámci SVĚTA (dřív se číslovalo po mapě): Goblin, Goblin 2, Goblin 3…
// Zásoba je světová, takže čísla musí být jednoznačná napříč mapami, jinak by na mapě skončili dva „Goblin 2“.
function dracak_vtt_npc_dalsi_nazev(PDO $pdo, int $svetId, int $nestvuraId, string $zaklad): string
{
    $q = $pdo->prepare('SELECT nazev_instance FROM nestvura_instance WHERE svet_id = ? AND nestvura_id = ?');
    $q->execute([$svetId, $nestvuraId]);
    return dracak_vtt_dalsi_nazev($q->fetchAll(PDO::FETCH_COLUMN), $zaklad);
}

// Vyrobí $pocet kusů druhu do zásoby světa (mapa_id = NULL, tedy nikde nepoložené). Vrací vyrobené řádky.
// $lokaceMapaId: místo, kam NPC patří (ukáže se ve správě u téhle lokace), nebo null = bez místa (zásoba pro
// mapy, které ještě nevznikly) — zadání uživatele 2026-11-09.
function dracak_vtt_npc_vyrobit(PDO $pdo, int $svetId, array $nestvura, int $pocet, bool $jedinecny, ?int $lokaceMapaId = null): array
{
    $pocet = max(1, min(DRACAK_VTT_NPC_DAVKA_MAX, $pocet));
    // Životaschopnost je v bestiáři volný text („20“, „20-25“, „viz text“) — vezmi první číslo, PJ si HP opraví.
    $hp = dracak_vtt_prvni_cislo($nestvura['zivotaschopnost']);
    $ins = $pdo->prepare(
        'INSERT INTO nestvura_instance (svet_id, mapa_id, lokace_mapa_id, nestvura_id, nazev_instance, aktualni_hp, max_hp, jedinecny)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?)'
    );
    $vyrobene = [];
    for ($i = 0; $i < $pocet; $i++) {
        $nazev = dracak_vtt_npc_dalsi_nazev($pdo, $svetId, (int)$nestvura['id'], (string)$nestvura['nazev']);
        $ins->execute([$svetId, $lokaceMapaId, (int)$nestvura['id'], $nazev, $hp, $hp, $jedinecny ? 1 : 0]);
        $vyrobene[] = [
            'instance_id' => (int)$pdo->lastInsertId(),
            'nazev' => $nazev,
            'nestvura_id' => (int)$nestvura['id'],
            'druh' => (string)$nestvura['nazev'],
            'hp' => $hp,
            'jedinecny' => $jedinecny,
            'lokace_mapa_id' => $lokaceMapaId,
        ];
    }
    return $vyrobene;
}

// Zásoba: NPC světa, která nestojí na žádné mapě (ani nemají token). Nejnovější první.
// Každý záznam nese místo, kam patří (lokace_mapa_id + jméno mapy), aby šlo v liště filtrovat na „tady“ /
// „bez místa“ / „vše“ — NPC bez místa je zásoba pro mapy, které ještě nevznikly.
function dracak_vtt_npc_zasoba(PDO $pdo, int $svetId): array
{
    $q = $pdo->prepare(
        'SELECT ni.id, ni.nazev_instance, ni.nestvura_id, ni.aktualni_hp, ni.max_hp, ni.jedinecny,
                ni.lokace_mapa_id, lm.nazev AS lokace_nazev, n.nazev AS druh, n.velikost
         FROM nestvura_instance ni
         JOIN nestvury n ON n.id = ni.nestvura_id
         LEFT JOIN mapy lm ON lm.id = ni.lokace_mapa_id
         LEFT JOIN tokeny t ON t.typ_entity = "nestvura_instance" AND t.entita_id = ni.id
         WHERE ni.svet_id = ? AND ni.mapa_id IS NULL AND t.id IS NULL
         ORDER BY ni.id DESC LIMIT 300'
    );
    $q->execute([$svetId]);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = [
            'instance_id' => (int)$r['id'],
            'nazev' => (string)$r['nazev_instance'],
            'nestvura_id' => (int)$r['nestvura_id'],
            'druh' => (string)$r['druh'],
            'hp' => (int)$r['aktualni_hp'],
            'max_hp' => (int)$r['max_hp'],
            'jedinecny' => (bool)$r['jedinecny'],
            'lokace_mapa_id' => $r['lokace_mapa_id'] !== null ? (int)$r['lokace_mapa_id'] : null,
            'lokace_nazev' => $r['lokace_nazev'] !== null ? (string)$r['lokace_nazev'] : null,
        ];
    }
    return $out;
}

// Naposledy použité druhy ve světě (podle nejnovějších instancí) — rychlé opakování toho, co PJ právě pokládá.
function dracak_vtt_npc_naposledy(PDO $pdo, int $svetId, int $limit = 12): array
{
    $q = $pdo->prepare(
        'SELECT ni.nestvura_id, n.nazev AS druh, MAX(ni.id) AS posledni, COUNT(*) AS kusu
         FROM nestvura_instance ni
         JOIN nestvury n ON n.id = ni.nestvura_id
         WHERE ni.svet_id = ?
         GROUP BY ni.nestvura_id, n.nazev
         ORDER BY posledni DESC LIMIT ' . max(1, min(50, $limit))
    );
    $q->execute([$svetId]);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['nestvura_id' => (int)$r['nestvura_id'], 'druh' => (string)$r['druh'], 'kusu' => (int)$r['kusu']];
    }
    return $out;
}

// Vrátí NPC z mapy do zásoby (po sejmutí tokenu). Čísla, jméno a výbava zůstanou.
function dracak_vtt_npc_do_zasoby(PDO $pdo, int $instanceId): void
{
    if (!dracak_vtt_npc_funkce()) {
        return;
    }
    // Místo (lokace_mapa_id) zůstává — NPC pořád patří do svého města, jen tam právě nestojí.
    $pdo->prepare('UPDATE nestvura_instance SET mapa_id = NULL WHERE id = ?')->execute([$instanceId]);
}

// NPC navázaná na jedno místo (lokace), i ta, co tam právě stojí jako token — „co všechno v téhle lokaci existuje“.
function dracak_vtt_npc_lokace(PDO $pdo, int $svetId, int $mapaId): array
{
    $q = $pdo->prepare(
        'SELECT ni.id, ni.nazev_instance, ni.nestvura_id, ni.aktualni_hp, ni.max_hp, ni.jedinecny, ni.mapa_id,
                n.nazev AS druh
         FROM nestvura_instance ni
         JOIN nestvury n ON n.id = ni.nestvura_id
         WHERE ni.svet_id = ? AND ni.lokace_mapa_id = ?
         ORDER BY ni.jedinecny DESC, ni.id DESC LIMIT 300'
    );
    $q->execute([$svetId, $mapaId]);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = [
            'instance_id' => (int)$r['id'],
            'nazev' => (string)$r['nazev_instance'],
            'nestvura_id' => (int)$r['nestvura_id'],
            'druh' => (string)$r['druh'],
            'hp' => (int)$r['aktualni_hp'],
            'max_hp' => (int)$r['max_hp'],
            'jedinecny' => (bool)$r['jedinecny'],
            'na_mape' => $r['mapa_id'] !== null,
        ];
    }
    return $out;
}

// Navázání NPC na místo (nebo jeho uvolnění: $mapaId = null). Ověření práv dělá endpoint.
function dracak_vtt_npc_lokace_nastav(PDO $pdo, int $instanceId, ?int $mapaId): void
{
    $pdo->prepare('UPDATE nestvura_instance SET lokace_mapa_id = ? WHERE id = ?')->execute([$mapaId, $instanceId]);
}
