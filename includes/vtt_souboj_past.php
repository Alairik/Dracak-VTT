<?php
declare(strict_types=1);

// Hod proti pasti v souboji (kniha h1686 "Pasti a jedy"): 1k10 + bonus za vlastnost >= nebezpečnost = štěstí.
// Teď jediné použití: čtvrtý hex sprintu — dovednost Běh na krátké vzdálenosti (h1698, zvlastni_schopnosti 229):
// vlastnost síla, obtížnost střední, v boji se ověřuje jednou za 3 kola. Úspěch = tvor dostane efekt
// "Běh na krátké vzdálenosti" na 3 kola (+1 hex k běhu a sprintu) a uložený pohyb se provede; neúspěch =
// rychlost zůstává, pohyb se neprovede a akce se neutratí (sprint nejvýš o 3 hexy).
//
// Obtížnost "střední" kniha v textu nepřevádí na číslo — nebezpečnost nastavuje PJ před hodem (hráč ji nevidí)
// a tohle číslo se příště předvyplní z posledního hodu stejné obtížnosti ve světě. Čtyřstupňová škála
// (fatální neúspěch / úspěch / fatální úspěch) se zatím nevyhodnocuje — chybí v přepisu pravidel (viz
// docs/kontrolni-seznam-neuplnych-mist.md); platí úspěch / neúspěch.

require_once __DIR__ . '/vtt_souboj_utok.php';
require_once __DIR__ . '/vtt_souboj_akce.php';

const DRACAK_VTT_PAST_BEH = [
    'nazev' => 'Běh na krátké vzdálenosti', 'vlastnost' => 'síla', 'obtiznost' => 'střední', 'trvani_kol' => 3,
];

// Poslední nastavená nebezpečnost pro danou obtížnost ve světě (z dřívějších hodů proti pasti), nebo null.
function dracak_vtt_past_posledni_nebezpecnost(PDO $pdo, int $svetId, string $obtiznost): ?int
{
    $q = $pdo->prepare(
        'SELECT v.data FROM souboj_vyzva v JOIN mapy m ON m.id = v.mapa_id
         WHERE m.svet_id = ? AND v.druh = "past" AND v.stav IN ("hozeno", "hotovo") AND v.data LIKE ?
         ORDER BY v.id DESC LIMIT 10'
    );
    $q->execute([$svetId, '%"nebezpecnost_nazev":' . json_encode($obtiznost, JSON_UNESCAPED_UNICODE) . '%']);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $d = json_decode((string)$json, true);
        if (is_array($d) && isset($d['nebezpecnost']) && is_numeric($d['nebezpecnost'])) {
            return (int)$d['nebezpecnost'];
        }
    }
    return null;
}

// Založí výzvu k hodu proti pasti a uloží požadavek na pohyb, který se po úspěchu provede.
function dracak_vtt_past_zaloz(PDO $pdo, array $mapa, array $info, array $token, array $pozadavek, int $ucetId): int
{
    $tokenId = (int)$token['id'];
    $b = dracak_vtt_souboj_bojovnik($pdo, $info[$tokenId]);
    $bonus = null;
    if ($info[$tokenId]['typ_entity'] === 'postava') {
        $q = $pdo->prepare('SELECT * FROM postavy WHERE id = ?');
        $q->execute([$info[$tokenId]['entita_id']]);
        $p = $q->fetch();
        if ($p) {
            $d = dracak_vtt_denik_data($pdo, $p, false);
            $bonus = $d['bonusy_ef']['sila'] ?? null;
        }
    }
    $upozorneni = [];
    if ($bonus === null) {
        $upozorneni[] = $b['typ'] === 'postava'
            ? 'Bonus za sílu postavy se nedá spočítat (chybí síla) — doplň ho.'
            : 'Bonus za sílu nestvůry není v bestiáři jako číslo — doplň ho.';
    }
    $q = $pdo->prepare('SELECT id, role, jmeno FROM ucty WHERE id = ?');
    $q->execute([$ucetId]);
    $d = [
        'popis' => 'Čtvrtý hex sprintu: ověření dovednosti ' . DRACAK_VTT_PAST_BEH['nazev'],
        'vlastnost' => DRACAK_VTT_PAST_BEH['vlastnost'],
        'nebezpecnost_nazev' => DRACAK_VTT_PAST_BEH['obtiznost'],
        'nebezpecnost' => dracak_vtt_past_posledni_nebezpecnost($pdo, (int)$mapa['svet_id'], DRACAK_VTT_PAST_BEH['obtiznost']),
        'zaklad' => $bonus,
        'zaklad_nazev' => 'bonus za sílu',
        'opravy' => [],
        'modifikator' => 0,
        'upozorneni' => $upozorneni,
        'efekt_nazev' => DRACAK_VTT_PAST_BEH['nazev'],
        'trvani_kol' => DRACAK_VTT_PAST_BEH['trvani_kol'],
        'pozadavek' => $pozadavek,
        'zadal_ucet_id' => $ucetId,
        'hody' => null, 'soucet_hodu' => null, 'celkem' => null,
    ];
    $id = dracak_vtt_vyzva_vloz($pdo, (int)$mapa['id'], 'past', $tokenId, null, null, $d);
    dracak_vtt_log_event((int)$mapa['svet_id'], (int)$mapa['id'], 'vyzva_nova',
        dracak_vtt_vyzva_payload($info, 'past', $id, $tokenId, null, ['popis' => $d['popis']]), $ucetId);
    return $id;
}

// Vyhodnotí hozenou past. Při úspěchu nasadí efekt a provede uložený pohyb. Volat v transakci (vyzva_hod.php).
// Vrací ['uspech' => bool, 'akce' => ?pole odpovědi pohybu, 'po_commitu' => ?callable].
function dracak_vtt_past_vyhodnot(PDO $pdo, array $mapa, array $info, array $vyzva, array $user): array
{
    $d = $vyzva['data'];
    $uspech = (int)$d['celkem'] >= (int)$d['nebezpecnost'];
    $d['vysledek'] = ['uspech' => $uspech, 'celkem' => (int)$d['celkem'], 'nebezpecnost' => (int)$d['nebezpecnost']];
    dracak_vtt_vyzva_uloz($pdo, $vyzva['id'], $d, 'hotovo');
    if (!$uspech) {
        return ['uspech' => false, 'akce' => null, 'po_commitu' => null];
    }
    $tokenId = (int)$vyzva['token_id'];
    $t = $info[$tokenId];
    $q = $pdo->prepare('SELECT id FROM efekty WHERE nazev = ? LIMIT 1');
    $q->execute([DRACAK_VTT_PAST_BEH['nazev']]);
    $efektId = $q->fetchColumn();
    if ($efektId !== false) {
        $q = $pdo->prepare(
            'SELECT id FROM aktivni_efekty WHERE typ_entity = ? AND entita_id = ? AND efekt_id = ? LIMIT 1'
        );
        $q->execute([$t['typ_entity'], $t['entita_id'], $efektId]);
        $existujici = $q->fetchColumn();
        if ($existujici !== false) {
            $pdo->prepare('UPDATE aktivni_efekty SET zbyva_kol = ? WHERE id = ?')->execute([DRACAK_VTT_PAST_BEH['trvani_kol'], $existujici]);
            $aktivniId = (int)$existujici;
        } else {
            $pdo->prepare('INSERT INTO aktivni_efekty (typ_entity, entita_id, efekt_id, zbyva_kol) VALUES (?, ?, ?, ?)')
                ->execute([$t['typ_entity'], $t['entita_id'], $efektId, DRACAK_VTT_PAST_BEH['trvani_kol']]);
            $aktivniId = (int)$pdo->lastInsertId();
        }
        dracak_vtt_log_event((int)$mapa['svet_id'], (int)$mapa['id'], 'efekt_aplikovan', [
            'typ_entity' => $t['typ_entity'], 'entita_id' => $t['entita_id'], 'aktivni_efekt_id' => $aktivniId,
            'efekt_id' => (int)$efektId, 'efekt_nazev' => DRACAK_VTT_PAST_BEH['nazev'], 'zbyva_kol' => DRACAK_VTT_PAST_BEH['trvani_kol'],
        ], (int)$user['id']);
    }
    // Pohyb se provede jako od toho, kdo ho zadal (únava, práva).
    $q = $pdo->prepare('SELECT * FROM ucty WHERE id = ?');
    $q->execute([(int)($d['zadal_ucet_id'] ?? $user['id'])]);
    $zadal = $q->fetch() ?: $user;
    $zadalJePj = dracak_vtt_je_pj_sveta($zadal, (int)$mapa['svet_id']);
    $vysledek = dracak_vtt_souboj_proved_akci($pdo, $mapa, $zadalJePj, $zadal, $d['pozadavek'], true);
    return ['uspech' => true, 'akce' => $vysledek['odpoved'], 'po_commitu' => $vysledek['po_commitu']];
}
