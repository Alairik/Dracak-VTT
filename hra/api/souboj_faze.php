<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Posun fáze kola (jen PJ): úmysly -> iniciativa -> kolo (tahy).
// Tělo: {mapa_id, kam: "iniciativa" | "kolo"}
// "kolo" začne 1. půlku: na tahu je první v pořadí podle iniciativy. Kdo
// nehodil, nehraje (0 akcí) — PJ je na to v panelu upozorněn.
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId, true);
$pdo = dracak_db();
$kam = (string)($data['kam'] ?? '');

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Na téhle mapě žádný souboj neběží.');
    }
    if ($kam === 'iniciativa' && $stav['faze'] === 'umysly') {
        $pdo->prepare('UPDATE kolo_stav SET faze = "iniciativa" WHERE mapa_id = ?')->execute([$mapaId]);
    } elseif ($kam === 'kolo' && $stav['faze'] === 'iniciativa') {
        $pdo->prepare('UPDATE kolo_stav SET faze = "kolo", polovina = 1, aktivni_token_id = NULL WHERE mapa_id = ?')->execute([$mapaId]);
        dracak_vtt_souboj_posun($pdo, $svetId, $mapaId, (int)$user['id']);
    } else {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Z téhle fáze se tam jít nedá.');
    }
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'souboj_faze', [
        'faze' => $kam,
        'cislo_kola' => $stav['cislo_kola'],
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
echo json_encode(['ok' => true, 'udalost_id' => $eventId], JSON_UNESCAPED_UNICODE);
