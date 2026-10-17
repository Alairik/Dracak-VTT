<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Úmysl tvora v tomhle kole (h1622: před hodem na iniciativu každý oznámí, co
// zamýšlí, aby šlo určit bonusy a postihy). Ve fázi úmyslů smí zadat vlastní
// postava (nebo PJ za kohokoliv), ve fázi iniciativy už jen PJ jako opravu.
// Tělo: {mapa_id, token_id | token_ids: [..], umysl: kouzleni|strelba|utok|pohyb|jina|cekani|utek|null, cil_token_id?,
//        utoku_za_2_kola?: 2–8 (kolik útoků za 2 kola tvor umí; bonus k iniciativě 3 × (počet − 2), počítá se jen při
//        úmyslu Útok nebo Střelba; zůstává mezi koly — iniciativa v2)}
// (token_ids = PJ nastaví úmysl celé skupině stejných nestvůr najednou)
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId);
$pdo = dracak_db();
$tokenIds = array_values(array_unique(array_map('intval', is_array($data['token_ids'] ?? null) ? $data['token_ids'] : [$data['token_id'] ?? 0])));
$umysl = isset($data['umysl']) && $data['umysl'] !== '' ? (string)$data['umysl'] : null;
if ($umysl !== null && !isset(DRACAK_VTT_UMYSLY[$umysl])) {
    dracak_vtt_souboj_chyba(422, 'Neplatný úmysl.');
}
$cilId = !empty($data['cil_token_id']) ? (int)$data['cil_token_id'] : null;
// Počet útoků za 2 kola (jen s ručním SQL iniciativy v2): prázdné / 2 = bez bonusu.
$utoku = null;
$utokuZadano = dracak_vtt_iniciativa_v2() && array_key_exists('utoku_za_2_kola', $data);
if ($utokuZadano && $data['utoku_za_2_kola'] !== null && $data['utoku_za_2_kola'] !== '') {
    $utoku = (int)$data['utoku_za_2_kola'];
    if ($utoku < 2 || $utoku > DRACAK_VTT_INI_UTOKY_MAX) {
        dracak_vtt_souboj_chyba(422, 'Útoků za 2 kola je 2 až ' . DRACAK_VTT_INI_UTOKY_MAX . ' (tabulka vícenásobných útoků končí čtyřmi útoky za kolo).');
    }
    $utoku = $utoku === 2 ? null : $utoku;
}

$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
foreach ($tokenIds as $tokenId) {
    if (!isset($info[$tokenId]) || !dracak_vtt_souboj_ovlada($user, $jePj, $info[$tokenId])) {
        dracak_vtt_souboj_chyba(403, 'Za tenhle token úmysl zadat nesmíš.');
    }
}
if ($cilId !== null && !isset($info[$cilId])) {
    $cilId = null;
}

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Na téhle mapě žádný souboj neběží.');
    }
    if ($stav['faze'] === 'kolo' || ($stav['faze'] === 'iniciativa' && !$jePj)) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Úmysly se zadávají před hodem na iniciativu.');
    }
    $upd = $pdo->prepare('UPDATE kolo_ucastnik SET umysl = ?, umysl_cil_token_id = ?' . ($utokuZadano ? ', utoku_za_2_kola = ?' : '') . ' WHERE mapa_id = ? AND token_id = ?');
    $existuje = $pdo->prepare('SELECT 1 FROM kolo_ucastnik WHERE mapa_id = ? AND token_id = ?');
    foreach ($tokenIds as $tokenId) {
        $upd->execute(array_merge([$umysl, $umysl === null ? null : $cilId], $utokuZadano ? [$utoku] : [], [$mapaId, $tokenId]));
        if ($upd->rowCount() === 0) {
            $existuje->execute([$mapaId, $tokenId]);
            if (!$existuje->fetchColumn()) {
                $pdo->rollBack();
                dracak_vtt_souboj_chyba(422, 'Token se souboje neúčastní.');
            }
        }
    }
    $skryta = true;
    foreach ($tokenIds as $tokenId) {
        if (dracak_vtt_souboj_vidi_hrac($info[$tokenId])) {
            $skryta = false;
        }
    }
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'umysl_nastaven', [
        'token_id' => $tokenIds[0],
        'token_ids' => $tokenIds,
        'label' => count($tokenIds) > 1 ? $info[$tokenIds[0]]['label'] . ' ×' . count($tokenIds) : $info[$tokenIds[0]]['label'],
        'umysl' => $umysl,
        'utoku_za_2_kola' => $utokuZadano ? $utoku : null,
        'cil_token_id' => $umysl === null ? null : $cilId,
        'skryta' => $skryta,
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
echo json_encode(['ok' => true, 'udalost_id' => $eventId], JSON_UNESCAPED_UNICODE);
