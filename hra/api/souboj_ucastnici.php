<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Úprava účastníků probíhajícího souboje (jen PJ): přidat / odebrat tokeny,
// přehodit stranu, změnit překvapení (jen v 1. kole před tahy). Nový účastník
// uprostřed kola (ve fázi tahů) nemá akce — iniciativa se hází na začátku kola,
// takže nastoupí v příštím kole.
// Tělo: {mapa_id, pridat: [{token_id, strana}], odebrat: [token_id],
//        strany: {token_id: strana}, prekvapeni?}
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId, true);
$pdo = dracak_db();
$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Na téhle mapě žádný souboj neběží.');
    }
    $pridano = [];
    $odebrano = [];
    foreach ((is_array($data['pridat'] ?? null) ? $data['pridat'] : []) as $u) {
        $tokenId = (int)($u['token_id'] ?? 0);
        if (!isset($info[$tokenId]) || !in_array($info[$tokenId]['typ_entity'], ['postava', 'nestvura_instance'], true)) {
            continue;
        }
        $strana = (string)($u['strana'] ?? $info[$tokenId]['strana_vychozi']);
        $strana = isset(DRACAK_VTT_STRANY[$strana]) ? $strana : $info[$tokenId]['strana_vychozi'];
        $pdo->prepare('INSERT IGNORE INTO kolo_ucastnik (mapa_id, token_id, strana) VALUES (?, ?, ?)')->execute([$mapaId, $tokenId, $strana]);
        $pridano[] = $tokenId;
    }
    foreach ((is_array($data['strany'] ?? null) ? $data['strany'] : []) as $tokenId => $strana) {
        if (isset(DRACAK_VTT_STRANY[(string)$strana])) {
            $pdo->prepare('UPDATE kolo_ucastnik SET strana = ? WHERE mapa_id = ? AND token_id = ?')->execute([(string)$strana, $mapaId, (int)$tokenId]);
        }
    }
    foreach ((is_array($data['odebrat'] ?? null) ? $data['odebrat'] : []) as $tokenId) {
        $tokenId = (int)$tokenId;
        $pdo->prepare('DELETE FROM kolo_ucastnik WHERE mapa_id = ? AND token_id = ?')->execute([$mapaId, $tokenId]);
        $pdo->prepare('DELETE FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ?')->execute([$mapaId, $tokenId]);
        if ($stav['aktivni_token_id'] === $tokenId) {
            $pdo->prepare('UPDATE kolo_stav SET aktivni_token_id = NULL WHERE mapa_id = ?')->execute([$mapaId]);
            $stav['aktivni_token_id'] = null;
        }
        $odebrano[] = $tokenId;
    }
    if (isset($data['prekvapeni'])) {
        $prekvapeni = (string)$data['prekvapeni'];
        if (!in_array($prekvapeni, DRACAK_VTT_PREKVAPENI, true)) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Neplatné překvapení.');
        }
        if ($stav['faze'] === 'kolo' || $stav['cislo_kola'] !== 1) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Překvapení jde měnit jen v 1. kole před začátkem tahů.');
        }
        $pdo->prepare('UPDATE kolo_stav SET prekvapeni = ? WHERE mapa_id = ?')->execute([$prekvapeni, $mapaId]);
    }
    // Odebrali jsme toho, kdo byl na tahu — tah jde dál.
    if ($stav['faze'] === 'kolo' && $odebrano) {
        dracak_vtt_souboj_posun($pdo, $svetId, $mapaId, (int)$user['id']);
    }
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'souboj_ucastnici', [
        'pridano' => $pridano,
        'odebrano' => $odebrano,
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
echo json_encode(['ok' => true, 'udalost_id' => $eventId], JSON_UNESCAPED_UNICODE);
