<?php
declare(strict_types=1);

require_once __DIR__ . '/vtt.php';

// Sdílené poznámky ve světě (viz database/migrations/0060_svet_poznamky.sql
// a konverzace) — libovolný člen světa napíše poznámku a vybere si, komu
// konkrétnímu ji nasdílí, ne "všem hráčům" jako jeden vypínač. Autor svou
// poznámku vidí vždycky. PJ světa NENÍ automaticky příjemce — je to jen
// další možný příjemce jako kterýkoliv hráč, takže jde udělat i čistě
// hráčské tajemství, o kterém se PJ nedozví. Jediná výjimka s dohledem je
// skutečný ucty.role='admin' (ne PJ světa) — ten vidí vše "v rámci
// bezpečnostní kontroly".

// Všechny poznámky viditelné TOMUTO účtu v tomhle světě — autorovy
// vlastní + ty, co mu někdo nasdílel (admin: úplně všechny). Každá nese
// i seznam jmen, komu ji autor nasdílel (sdileno_s) — jen pro autora má
// smysl to ukazovat, viz hra/svet.php.
function dracak_vtt_poznamky_viditelne(PDO $pdo, array $user, int $svetId): array
{
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare(
            'SELECT p.*, u.jmeno AS autor_jmeno FROM svet_poznamky p
             JOIN ucty u ON u.id = p.autor_ucet_id
             WHERE p.svet_id = ? ORDER BY p.vytvoreno DESC'
        );
        $stmt->execute([$svetId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT p.*, u.jmeno AS autor_jmeno FROM svet_poznamky p
             JOIN ucty u ON u.id = p.autor_ucet_id
             LEFT JOIN svet_poznamka_sdileni s ON s.poznamka_id = p.id
             WHERE p.svet_id = ? AND (p.autor_ucet_id = ? OR s.ucet_id = ?)
             ORDER BY p.vytvoreno DESC'
        );
        $stmt->execute([$svetId, $user['id'], $user['id']]);
    }
    $poznamky = $stmt->fetchAll();
    if (!$poznamky) {
        return [];
    }
    $ids = array_column($poznamky, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT s.poznamka_id, u.jmeno FROM svet_poznamka_sdileni s
         JOIN ucty u ON u.id = s.ucet_id WHERE s.poznamka_id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $sdilenoPodleId = [];
    foreach ($stmt->fetchAll() as $row) {
        $sdilenoPodleId[(int)$row['poznamka_id']][] = $row['jmeno'];
    }
    foreach ($poznamky as &$p) {
        $p['sdileno_s'] = $sdilenoPodleId[(int)$p['id']] ?? [];
    }
    unset($p);
    return $poznamky;
}

// Komu všemu JDE poznámku nasdílet — kdokoli s přístupem do světa (PJ +
// svet_hraci), kromě autora samotného (vlastní poznámky vidí tak jako tak).
function dracak_vtt_poznamky_moznosti_sdileni(PDO $pdo, int $svetId, int $autorUcetId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.jmeno FROM ucty u JOIN svet s ON s.pj_ucet_id = u.id WHERE s.id = ? AND u.id != ?
         UNION
         SELECT u.id, u.jmeno FROM ucty u JOIN svet_hraci sh ON sh.ucet_id = u.id WHERE sh.svet_id = ? AND u.id != ?
         ORDER BY jmeno'
    );
    $stmt->execute([$svetId, $autorUcetId, $svetId, $autorUcetId]);
    return $stmt->fetchAll();
}

function dracak_vtt_poznamka_pridat(PDO $pdo, int $svetId, int $autorUcetId, string $text, array $sdilenoSUcetId): int
{
    $stmt = $pdo->prepare('INSERT INTO svet_poznamky (svet_id, autor_ucet_id, text) VALUES (?, ?, ?)');
    $stmt->execute([$svetId, $autorUcetId, $text]);
    $poznamkaId = (int)$pdo->lastInsertId();
    if ($sdilenoSUcetId) {
        $ins = $pdo->prepare('INSERT IGNORE INTO svet_poznamka_sdileni (poznamka_id, ucet_id) VALUES (?, ?)');
        foreach ($sdilenoSUcetId as $ucetId) {
            $ins->execute([$poznamkaId, (int)$ucetId]);
        }
    }
    return $poznamkaId;
}

// Smazat smí jen autor, nebo skutečný admin — PJ světa na to žádné
// zvláštní právo nemá, se sdílením poznámek nijak nedohlíží.
function dracak_vtt_poznamka_smazat(PDO $pdo, int $poznamkaId, array $user): bool
{
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare('DELETE FROM svet_poznamky WHERE id = ?');
        $stmt->execute([$poznamkaId]);
        return $stmt->rowCount() > 0;
    }
    $stmt = $pdo->prepare('DELETE FROM svet_poznamky WHERE id = ? AND autor_ucet_id = ?');
    $stmt->execute([$poznamkaId, $user['id']]);
    return $stmt->rowCount() > 0;
}
