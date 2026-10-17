<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Oprava počtu akcí tvora (jen PJ): ruční +/−, nebo kouzla, která mění počet
// akcí, které má tvor "v daném okamžiku k dispozici" (h1626): Rychlost je
// zdvojí, protoplazma sníží na polovinu (dolů).
// Tělo: {mapa_id, token_id, op: "delta" | "rychlost" | "protoplazma" | "zavazek_zrusit", delta?: celé číslo}
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
    if (!in_array($op, ['delta', 'rychlost', 'protoplazma', 'zavazek_zrusit'], true)) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Neplatná úprava.');
    }
    if ($op === 'zavazek_zrusit') {
        // PJ zruší slíbený úkon (iniciativa v2): tvor už nemusí první akci a polovinu akcí věnovat kouzlení / útoku.
        if (!dracak_vtt_iniciativa_v2()) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Slíbený úkon se hlídá až po ručním SQL iniciativy v2.');
        }
        $pdo->prepare('UPDATE kolo_iniciativa SET zavazek = NULL, zavazek_akci = 0 WHERE mapa_id = ? AND token_id = ?')
            ->execute([$mapaId, $tokenId]);
        $celkem = (int)$ki['akce_celkem'];
        $zbyva = (int)$ki['akce_zbyvajici'];
    } else {
        [$celkem, $zbyva] = dracak_vtt_souboj_akce_prepocet((int)$ki['akce_celkem'], (int)$ki['akce_zbyvajici'], $op, (int)($data['delta'] ?? 0));
        $pdo->prepare('UPDATE kolo_iniciativa SET akce_celkem = ?, akce_zbyvajici = ? WHERE mapa_id = ? AND token_id = ?')
            ->execute([$celkem, $zbyva, $mapaId, $tokenId]);
    }
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
