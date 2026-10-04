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

function dracak_vtt_mapa_bod_pridat(PDO $pdo, int $mapaId, string $nazev, string $typ, int $x, int $y, ?int $cilovaMapaId, ?string $popis, bool $viditelnyHracum): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO mapa_body (mapa_id, cilova_mapa_id, nazev, typ, x, y, popis, viditelny_hracum)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$mapaId, $cilovaMapaId, $nazev, $typ, $x, $y, $popis, $viditelnyHracum ? 1 : 0]);
    return (int)$pdo->lastInsertId();
}

function dracak_vtt_mapa_bod_smazat(PDO $pdo, int $bodId, int $mapaId): bool
{
    $stmt = $pdo->prepare('DELETE FROM mapa_body WHERE id = ? AND mapa_id = ?');
    $stmt->execute([$bodId, $mapaId]);
    return $stmt->rowCount() > 0;
}
