<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Tvor na tahu odehrál akci (nebo víc) — ubude mu z "akce zbývající". Vyčerpá-li
// kvótu tahu (větší půlka akcí v 1. půlce kola, zbytek ve 2.), tah jde dál.
// Smí postava na tahu (její hráč) nebo PJ. Druh akce (h1623) je ve kroku 1 jen
// popisek v logu; vyhodnocení pohybu a útoků přijde v dalších krocích.
// Tělo: {mapa_id, token_id, druh?, pocet? (výchozí 1)}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId);
$pdo = dracak_db();
$tokenId = (int)($data['token_id'] ?? 0);
$druh = (string)($data['druh'] ?? 'jina');
if (!isset(DRACAK_VTT_AKCE_DRUHY[$druh])) {
    $druh = 'jina';
}
$pocet = max(1, min(9, (int)($data['pocet'] ?? 1)));

$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
if (!isset($info[$tokenId]) || !dracak_vtt_souboj_ovlada($user, $jePj, $info[$tokenId])) {
    dracak_vtt_souboj_chyba(403, 'Za tenhle token jednat nesmíš.');
}

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null || $stav['faze'] !== 'kolo') {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Teď neběží tahy.');
    }
    if ($stav['aktivni_token_id'] !== $tokenId) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Tenhle tvor teď není na tahu.');
    }
    $q = $pdo->prepare('SELECT akce_celkem, akce_zbyvajici FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ? FOR UPDATE');
    $q->execute([$mapaId, $tokenId]);
    $ki = $q->fetch();
    $kvota = $ki ? dracak_vtt_souboj_kvota((int)$ki['akce_celkem'], (int)$ki['akce_zbyvajici'], $stav['polovina']) : 0;
    if ($pocet > $kvota) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'V tomhle tahu smíš odehrát už jen ' . $kvota . ' ' . ($kvota === 1 ? 'akci' : 'akcí') . '.');
    }
    $pdo->prepare('UPDATE kolo_iniciativa SET akce_zbyvajici = akce_zbyvajici - ? WHERE mapa_id = ? AND token_id = ?')
        ->execute([$pocet, $mapaId, $tokenId]);
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'akce_provedena', [
        'token_id' => $tokenId,
        'label' => $info[$tokenId]['label'],
        'druh' => $druh,
        'druh_nazev' => DRACAK_VTT_AKCE_DRUHY[$druh],
        'pocet' => $pocet,
        'akce_zbyvajici' => (int)$ki['akce_zbyvajici'] - $pocet,
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
