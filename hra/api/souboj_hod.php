<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Hod na iniciativu v souboji (h1622): 1k6 + bonusy z tabulky (části si server
// přičte sám: únava a naložení postavy, překvapení, úmysl kouzlení) -> počet
// akcí. HODY SE NEHÁZEJÍ AUTOMATICKY: hráč klikne na "Hodit", ukáže se 3D
// kostka a číslo, které ukáže, přijde sem jako "hod" (nebo ho hráč zapíše
// ručně, "rucne": true, když hodil fyzickou kostkou). Server jen hlídá rozsah.
// Tělo: {mapa_id, token_ids: [..] | token_id, hod: 1..6, rucne?, bonus_ids?, jiny_bonus?}
// PJ smí hodit jedním hodem za víc stejných nestvůr (h2336: nestvůry stejného
// druhu házejí jednou) — token_ids pak musí být ze stejného druhu.
// Hází se jen ve fázi iniciativy; uprostřed kola už ne (nový účastník nastoupí
// v příštím kole).
$data = dracak_vtt_souboj_vstup();
$mapaId = (int)($data['mapa_id'] ?? 0);
[$mapa, $svetId, $jePj, $user] = dracak_vtt_souboj_zacatek($mapaId);
$pdo = dracak_db();

$tokenIds = array_values(array_unique(array_map('intval', is_array($data['token_ids'] ?? null) ? $data['token_ids'] : [$data['token_id'] ?? 0])));
$hod = (int)($data['hod'] ?? 0);
if ($hod < 1 || $hod > 6) {
    dracak_vtt_souboj_chyba(422, 'Hod na iniciativu je 1k6, tedy číslo 1 až 6.');
}
$rucne = !empty($data['rucne']);
$bonusIds = array_map('intval', is_array($data['bonus_ids'] ?? null) ? $data['bonus_ids'] : []);
$jinyBonus = (int)($data['jiny_bonus'] ?? 0);

$info = dracak_vtt_souboj_tokeny_info($pdo, $svetId, $mapaId);
if (!$tokenIds) {
    dracak_vtt_souboj_chyba(422, 'Chybí token.');
}
foreach ($tokenIds as $tokenId) {
    if (!isset($info[$tokenId]) || !dracak_vtt_souboj_ovlada($user, $jePj, $info[$tokenId])) {
        dracak_vtt_souboj_chyba(403, 'Iniciativu za tenhle token hodit nesmíš.');
    }
}
if (count($tokenIds) > 1) {
    $skupiny = array_unique(array_map(fn($id) => $info[$id]['skupina'], $tokenIds));
    if (count($skupiny) !== 1 || $info[$tokenIds[0]]['typ_entity'] !== 'nestvura_instance') {
        dracak_vtt_souboj_chyba(422, 'Jedním hodem lze házet jen za nestvůry stejného druhu.');
    }
}

$pdo->beginTransaction();
try {
    $stav = dracak_vtt_souboj_nacti($pdo, $mapaId, true);
    if ($stav === null) {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Na téhle mapě žádný souboj neběží.');
    }
    if ($stav['faze'] === 'umysly') {
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Nejdřív se zadávají úmysly; PJ pak otevře hody na iniciativu.');
    }
    if ($stav['faze'] === 'kolo') {
        // Akce se dostávají z iniciativy, a ta se hází na začátku kola: kdo se
        // přidal uprostřed kola, nemá akce, dokud se v dalším kole nezačne házet.
        $pdo->rollBack();
        dracak_vtt_souboj_chyba(422, 'Iniciativa se hází na začátku kola — kdo se přidal uprostřed, nastoupí v příštím kole.');
    }
    $existuje = $pdo->prepare('SELECT 1 FROM kolo_iniciativa WHERE mapa_id = ? AND token_id = ?');
    $ucastnik = $pdo->prepare('SELECT * FROM kolo_ucastnik WHERE mapa_id = ? AND token_id = ?');
    $upsert = $pdo->prepare(
        'INSERT INTO kolo_iniciativa (mapa_id, token_id, hod, modifikator, vysledek, akce_celkem, akce_zbyvajici)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE hod = VALUES(hod), modifikator = VALUES(modifikator),
             vysledek = VALUES(vysledek), akce_celkem = VALUES(akce_celkem), akce_zbyvajici = VALUES(akce_zbyvajici)'
    );
    $vysledky = [];
    $bonusyPopis = [];
    $upozorneni = [];
    $automaticke = [];
    $ignorovano = [];
    foreach ($tokenIds as $tokenId) {
        $ucastnik->execute([$mapaId, $tokenId]);
        $u = $ucastnik->fetch();
        if (!$u) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Token se souboje neúčastní.');
        }
        $existuje->execute([$mapaId, $tokenId]);
        $uzHozeno = (bool)$existuje->fetchColumn();
        if ($uzHozeno && !$jePj) {
            $pdo->rollBack();
            dracak_vtt_souboj_chyba(422, 'Iniciativa už je pro ' . $info[$tokenId]['label'] . ' hozená.');
        }
        $m = dracak_vtt_iniciativa_modifikator($pdo, $info[$tokenId], $bonusIds, $jinyBonus, dracak_vtt_souboj_auto_popisy($stav, $u));
        $vysledek = $hod + $m['modifikator'];
        $akce = dracak_vtt_iniciativa_akce($vysledek);
        $upsert->execute([$mapaId, $tokenId, $hod, $m['modifikator'], $vysledek, $akce, $akce]);
        $vysledky[] = [
            'token_id' => $tokenId,
            'label' => $info[$tokenId]['label'],
            'modifikator' => $m['modifikator'],
            'vysledek' => $vysledek,
            'akce_celkem' => $akce,
        ];
        $bonusyPopis = $m['popisy'];
        $upozorneni = array_merge($upozorneni, $m['upozorneni']);
        $automaticke = $m['automaticke'];
        $ignorovano = $m['ignorovano'];
    }
    $skryta = true;
    foreach ($tokenIds as $tokenId) {
        if (dracak_vtt_souboj_vidi_hrac($info[$tokenId])) {
            $skryta = false;
        }
    }
    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'iniciativa_hozena', [
        'token_id' => $tokenIds[0],
        'token_ids' => $tokenIds,
        'label' => count($tokenIds) > 1 ? $info[$tokenIds[0]]['label'] . ' ×' . count($tokenIds) : $info[$tokenIds[0]]['label'],
        'hod' => $hod,
        'modifikator' => $vysledky[0]['modifikator'],
        'bonusy' => $bonusyPopis,
        'jiny_bonus' => $jinyBonus,
        'vysledek' => $vysledky[0]['vysledek'],
        'akce_celkem' => $vysledky[0]['akce_celkem'],
        'akce_zbyvajici' => $vysledky[0]['akce_celkem'],
        'hodil' => $user['jmeno'],
        'rucne' => $rucne,
        'skryta' => $skryta,
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo json_encode([
    'ok' => true,
    'udalost_id' => $eventId,
    'hod' => $hod,
    'vysledky' => $vysledky,
    'bonusy' => $bonusyPopis,
    'automaticky' => $automaticke,
    'ignorovano_rucne' => $ignorovano,
    'upozorneni' => $upozorneni,
], JSON_UNESCAPED_UNICODE);
