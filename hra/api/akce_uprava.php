<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Oprava počtu akcí tvora (jen PJ): ruční +/−, nebo kouzla, která mění počet
// akcí, které má tvor "v daném okamžiku k dispozici" (h1626): Rychlost je
// zdvojí, protoplazma sníží na polovinu (dolů).
// Tělo: {mapa_id, token_id, op: "delta" | "rychlost" | "protoplazma", delta?: celé číslo}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId, true);
$pdo = dracak_db();
$tokenId = (int)($data['token_id'] ?? 0);
$op = (string)($data['op'] ?? 'delta');
$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
if (!isset($info[$tokenId])) {
    dracak_vtt_souboj_chyba(404, 'Token nenalezen.');
}

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null || $stav['faze'] !== 'kolo') {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Počet akcí jde měnit jen během tahů.');
    }
    $q = $pdo->prepare('SELECT akce_celkem, akce_zbyvajici FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ? FOR UPDATE');
    $q->execute([$mapaId, $tokenId]);
    $ki = $q->fetch();
    if (!$ki) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Tenhle tvor nemá hozenou iniciativu.');
    }
    $celkem = (int)$ki['akce_celkem'];
    $zbyva = (int)$ki['akce_zbyvajici'];
    if ($op === 'rychlost') {
        $celkem += $zbyva;
        $zbyva *= 2;
    } elseif ($op === 'protoplazma') {
        $nove = intdiv($zbyva, 2);
        $celkem -= $zbyva - $nove;
        $zbyva = $nove;
    } elseif ($op === 'delta') {
        $d = max(-9, min(9, (int)($data['delta'] ?? 0)));
        $zbyva = max(0, $zbyva + $d);
        $celkem = max($zbyva, $celkem + $d);
    } else {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Neplatná úprava.');
    }
    $celkem = max(0, min(200, $celkem));
    $zbyva = max(0, min($celkem, $zbyva));
    $pdo->prepare('UPDATE kolo_iniciativa SET akce_celkem = ?, akce_zbyvajici = ? WHERE mapa_id = ? AND token_id = ?')
        ->execute([$celkem, $zbyva, $mapaId, $tokenId]);
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'akce_uprava', [
        'token_id' => $tokenId,
        'label' => $info[$tokenId]['label'],
        'op' => $op,
        'akce_celkem' => $celkem,
        'akce_zbyvajici' => $zbyva,
        'skryta' => !dracak_vtt_souboj_vidi_hrac($info[$tokenId]),
    ], (int)$user['id']);
    $posun = dracak_vtt_souboj_posun($pdo, $svetId, $mapaId, (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
echo json_encode(['ok' => true, 'udalost_id' => $eventId, 'posun' => $posun], JSON_UNESCAPED_UNICODE);
