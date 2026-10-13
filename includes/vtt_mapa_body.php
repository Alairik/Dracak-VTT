<?php
declare(strict_types=1);

require_once __DIR__ . '/vtt.php';

// Piny na světové mapě (viz database/migrations/0061_mapa_body.sql).
// Hráč vidí jen piny s viditelny_hracum=1 na mapě, kterou zrovna má
// otevřenou — PJ/admin vidí všechny (stejná logika jako zdi/tokeny
// jinde v enginu).
function dracak_vtt_mapa_body_pro_mapu(PDO $pdo, int $mapaId, bool $isPjOrAdmin): array
{
    if ($isPjOrAdmin) {
        $stmt = $pdo->prepare(
            'SELECT b.*, m.nazev AS cilova_mapa_nazev FROM mapa_body b
             LEFT JOIN mapy m ON m.id = b.cilova_mapa_id
             WHERE b.mapa_id = ? ORDER BY b.nazev'
        );
        $stmt->execute([$mapaId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT b.*, m.nazev AS cilova_mapa_nazev FROM mapa_body b
             LEFT JOIN mapy m ON m.id = b.cilova_mapa_id
             WHERE b.mapa_id = ? AND b.viditelny_hracum = 1 ORDER BY b.nazev'
        );
        $stmt->execute([$mapaId]);
    }
    return $stmt->fetchAll();
}

// NPC "co se tam můžou nacházet" = tokeny typu postava na pinově cílové
// mapě, jejichž postava patří PJ/adminovi (hráčovy vlastní postavy na
// scéně NEJSOU "NPC pro tohle místo", i kdyby tam token náhodou měly).
function dracak_vtt_mapa_bod_npc(PDO $pdo, int $cilovaMapaId): array
{
    $stmt = $pdo->prepare(
        "SELECT p.id, p.nazev, p.uroven, p.aktualni_hp, p.max_hp, r.nazev AS rasa_nazev, pv.nazev AS povolani_nazev
         FROM tokeny t
         JOIN postavy p ON p.id = t.entita_id
         JOIN ucty u ON u.id = p.vlastnik_ucet_id
         LEFT JOIN rasy r ON r.id = p.rasa_id
         LEFT JOIN povolani pv ON pv.id = p.povolani_id
         WHERE t.mapa_id = ? AND t.typ_entity = 'postava' AND u.role IN ('pj', 'admin')
         ORDER BY p.nazev"
    );
    $stmt->execute([$cilovaMapaId]);
    return $stmt->fetchAll();
}

// Jméno vendorované ikony (assets/icons/) pro daný typ pinu — na jednom
// místě, ať se nerozjíždí mezi šablonou ikon a markerem na mapě (viz
// hra/mapa.php).
function dracak_vtt_mapa_bod_ikona(string $typ): string
{
    return match ($typ) {
        'mesto' => 'castle',
        'vesnice' => 'home',
        'tajne_misto' => 'eye-off',
        'budova' => 'building-2',
        'mistnost' => 'door-open',
        'sklep' => 'warehouse',
        'prechod' => 'signpost',
        default => 'map-pin',
    };
}

// Sdílená validace cílové mapy pinu — stejná pro založení i editaci
// (hra/api/mapa_bod_pridat.php, mapa_bod_upravit.php), ať se nerozjede
// mezi oběma endpointy. Vrací chybovou hlášku, nebo null když je vše OK.
function dracak_vtt_mapa_bod_validuj_cil(PDO $pdo, int $svetId, int $mapaId, ?int $cilovaMapaId): ?string
{
    if ($cilovaMapaId === null) {
        return null;
    }
    if ($cilovaMapaId === $mapaId) {
        return 'Pin nemůže vést na mapu, na které sám je.';
    }
    $stmt = $pdo->prepare('SELECT 1 FROM mapy WHERE id = ? AND svet_id = ?');
    $stmt->execute([$cilovaMapaId, $svetId]);
    if (!$stmt->fetchColumn()) {
        return 'Cílová mapa nepatří do tohohle světa.';
    }
    return null;
}

function dracak_vtt_mapa_bod_pridat(PDO $pdo, int $svetId, int $mapaId, string $nazev, string $typ, int $x, int $y, ?int $cilovaMapaId, ?string $popis, bool $viditelnyHracum): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO mapa_body (svet_id, mapa_id, cilova_mapa_id, nazev, typ, x, y, popis, viditelny_hracum)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$svetId, $mapaId, $cilovaMapaId, $nazev, $typ, $x, $y, $popis, $viditelnyHracum ? 1 : 0]);
    return (int)$pdo->lastInsertId();
}

// Upravitelné: popisné pole + kam pin vede + pozice (x/y) na TÉŽE mapě
// (přetažení v editačním módu, viz hra/mapa.php). mapa_id (na které
// konkrétní mapě pin je) se pořád nemění — přesun na jinou mapu nikdo
// nepožadoval, jen posun po mapě, na které už je.
function dracak_vtt_mapa_bod_upravit(PDO $pdo, int $bodId, int $svetId, string $nazev, string $typ, ?int $cilovaMapaId, ?string $popis, bool $viditelnyHracum, int $x, int $y): bool
{
    $stmt = $pdo->prepare(
        'UPDATE mapa_body SET nazev = ?, typ = ?, cilova_mapa_id = ?, popis = ?, viditelny_hracum = ?, x = ?, y = ?
         WHERE id = ? AND svet_id = ?'
    );
    $stmt->execute([$nazev, $typ, $cilovaMapaId, $popis, $viditelnyHracum ? 1 : 0, $x, $y, $bodId, $svetId]);
    return $stmt->rowCount() > 0;
}

function dracak_vtt_mapa_bod_smazat(PDO $pdo, int $bodId, int $mapaId): bool
{
    $stmt = $pdo->prepare('DELETE FROM mapa_body WHERE id = ? AND mapa_id = ?');
    $stmt->execute([$bodId, $mapaId]);
    return $stmt->rowCount() > 0;
}

// Cesta od kořene (světová mapa) k zadané mapě, pro breadcrumb nahoře
// na hra/mapa.php. mapa_body.cilova_mapa_id nemá UNIQUE (viz migrace
// 0062 — jedna mapa smí mít dva piny, co na ni vedou, např. dva vchody
// do sklepení), takže rodič není vždycky jednoznačný — bereme
// deterministicky ten s nejnižším id. $navstivene brání nekonečné
// smyčce, kdyby někdy vznikl cyklus (A vede na B, B vede na A) —
// mapa_bod_pridat.php hlídá jen přímý self-loop, ne tenhle dvoukrokový.
function dracak_vtt_mapa_cesta(PDO $pdo, int $mapaId): array
{
    $cesta = [];
    $aktualni = $mapaId;
    $navstivene = [];
    while ($aktualni !== null && !isset($navstivene[$aktualni])) {
        $navstivene[$aktualni] = true;
        $stmt = $pdo->prepare('SELECT id, nazev FROM mapy WHERE id = ?');
        $stmt->execute([$aktualni]);
        $mapa = $stmt->fetch();
        if (!$mapa) {
            break;
        }
        array_unshift($cesta, ['id' => (int)$mapa['id'], 'nazev' => (string)$mapa['nazev']]);
        $stmt = $pdo->prepare('SELECT mapa_id FROM mapa_body WHERE cilova_mapa_id = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$aktualni]);
        $rodic = $stmt->fetchColumn();
        $aktualni = $rodic !== false ? (int)$rodic : null;
    }
    return $cesta;
}

// Celý strom míst ve světě (pro postranní panel na hra/mapa.php) —
// jeden dotaz na mapy, jeden na piny, strom poskládaný v PHP (ne
// WITH RECURSIVE — neznámá MariaDB verze na produkčním Wedos
// hostingu, radši bez závislosti na konkrétní verzi). $navstivene se
// předává PO HODNOTĚ, ne odkazem — zabrání to jen cyklu po jedné
// větvi (ancestor), ne opakovanému výskytu stejné mapy pod dvěma
// různými piny (to je žádoucí, viz komentář u dracak_vtt_mapa_cesta).
function dracak_vtt_mapa_strom(PDO $pdo, int $svetId): array
{
    $stmt = $pdo->prepare("SELECT id, nazev, typ_mapy FROM mapy WHERE svet_id = ?");
    $stmt->execute([$svetId]);
    $mapyById = [];
    foreach ($stmt->fetchAll() as $m) {
        $mapyById[(int)$m['id']] = $m;
    }

    $stmt = $pdo->prepare(
        'SELECT b.mapa_id, b.cilova_mapa_id, b.nazev, b.typ FROM mapa_body b
         JOIN mapy m ON m.id = b.mapa_id WHERE m.svet_id = ? ORDER BY b.nazev'
    );
    $stmt->execute([$svetId]);
    $pinyPodleMapy = [];
    foreach ($stmt->fetchAll() as $p) {
        $pinyPodleMapy[(int)$p['mapa_id']][] = $p;
    }

    $sestav = function (int $mapaId, array $navstivene) use (&$sestav, $mapyById, $pinyPodleMapy): ?array {
        if (isset($navstivene[$mapaId]) || !isset($mapyById[$mapaId])) {
            return null;
        }
        $navstivene[$mapaId] = true;
        $uzel = ['id' => $mapaId, 'nazev' => (string)$mapyById[$mapaId]['nazev'], 'deti' => []];
        foreach ($pinyPodleMapy[$mapaId] ?? [] as $pin) {
            if ($pin['cilova_mapa_id'] === null) {
                continue;
            }
            $podstrom = $sestav((int)$pin['cilova_mapa_id'], $navstivene);
            if ($podstrom !== null) {
                $podstrom['pin_nazev'] = (string)$pin['nazev'];
                $podstrom['pin_typ'] = (string)$pin['typ'];
                $uzel['deti'][] = $podstrom;
            }
        }
        return $uzel;
    };

    $koreny = [];
    foreach ($mapyById as $id => $m) {
        if ($m['typ_mapy'] === 'svet') {
            $uzel = $sestav((int)$id, []);
            if ($uzel !== null) {
                $koreny[] = $uzel;
            }
        }
    }
    return $koreny;
}
