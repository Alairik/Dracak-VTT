<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}

$svetId = (int)($_GET['svet_id'] ?? 0);
$od = (int)($_GET['od'] ?? 0);
if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Bez přístupu.']);
    exit;
}

$stmt = dracak_db()->prepare(
    'SELECT id, mapa_id, typ, payload, ucet_id, vytvoreno FROM svet_udalosti
     WHERE svet_id = ? AND id > ? ORDER BY id LIMIT 200'
);
$stmt->execute([$svetId, $od]);
$udalosti = $stmt->fetchAll();
$isPjOrAdmin = dracak_vtt_je_pj_sveta($user, $svetId);
foreach ($udalosti as &$u) {
    $u['payload'] = json_decode((string)$u['payload'], true);
}
unset($u);
// Skrytá zeď (viditelna_hracum = false) je tajemství PJ — i přes polling
// smí dojít jen PJ/adminovi, jinak by unikla hráčům obcházející filtr,
// který jim mapa.php jinak aplikuje na počáteční SELECT.
if (!$isPjOrAdmin) {
    $udalosti = array_values(array_filter($udalosti, function (array $u): bool {
        if ($u['typ'] !== 'zed_pridana') {
            return true;
        }
        return !empty($u['payload']['viditelna_hracum']);
    }));
}

// Stejně tak skrytý token (viditelny_hracum = 0): jeho pohyb, změny vzhledu
// a životy nesmí hráčům prosáknout přes polling. Výjimka je událost, která
// právě mění viditelnost (token_zmena s klíčem "viditelny") — hráč se z ní
// dozví, že má token odstranit, resp. načíst.
if (!$isPjOrAdmin && $udalosti) {
    $pdo = dracak_db();
    $tokenIds = [];
    $entity = [];
    foreach ($udalosti as $u) {
        if (in_array($u['typ'], ['token_presun', 'token_zmena'], true) && !empty($u['payload']['token_id'])) {
            $tokenIds[(int)$u['payload']['token_id']] = true;
        }
        if ($u['typ'] === 'tokeny_presun' && is_array($u['payload']['presuny'] ?? null)) {
            foreach ($u['payload']['presuny'] as $pr) {
                if (!empty($pr['token_id'])) $tokenIds[(int)$pr['token_id']] = true;
            }
        }
        if ($u['typ'] === 'hp_zmena' && !empty($u['payload']['entita_id']) && $u['mapa_id'] !== null) {
            $entity[] = [$u['payload']['typ_entity'] ?? '', (int)$u['payload']['entita_id'], (int)$u['mapa_id']];
        }
    }
    $skryteTokeny = [];
    if ($tokenIds) {
        $in = implode(',', array_fill(0, count($tokenIds), '?'));
        $q = $pdo->prepare("SELECT id FROM tokeny WHERE viditelny_hracum = 0 AND id IN ($in)");
        $q->execute(array_keys($tokenIds));
        $skryteTokeny = array_flip($q->fetchAll(PDO::FETCH_COLUMN));
    }
    $skryteEntity = [];
    if ($entity) {
        $q = $pdo->prepare('SELECT typ_entity, entita_id, mapa_id FROM tokeny WHERE viditelny_hracum = 0 AND mapa_id IN (' . implode(',', array_fill(0, count($entity), '?')) . ')');
        $q->execute(array_column($entity, 2));
        foreach ($q->fetchAll() as $r) {
            $skryteEntity[$r['typ_entity'] . ':' . $r['entita_id'] . ':' . $r['mapa_id']] = true;
        }
    }
    // Společný přesun: ze seznamu se vyřadí skryté tokeny, událost zůstane,
    // jen když v ní zbude aspoň jeden viditelný.
    foreach ($udalosti as &$u) {
        if ($u['typ'] === 'tokeny_presun' && is_array($u['payload']['presuny'] ?? null)) {
            $u['payload']['presuny'] = array_values(array_filter(
                $u['payload']['presuny'],
                fn($pr) => !isset($skryteTokeny[(int)($pr['token_id'] ?? 0)])
            ));
        }
    }
    unset($u);
    $udalosti = array_values(array_filter($udalosti, function (array $u) use ($skryteTokeny, $skryteEntity): bool {
        if ($u['typ'] === 'tokeny_presun') {
            return !empty($u['payload']['presuny']);
        }
        if (in_array($u['typ'], ['token_presun', 'token_zmena'], true)) {
            $id = (int)($u['payload']['token_id'] ?? 0);
            if (isset($skryteTokeny[$id])) {
                return $u['typ'] === 'token_zmena' && is_array($u['payload']['zmeny'] ?? null)
                    && array_key_exists('viditelny', $u['payload']['zmeny']);
            }
        }
        if ($u['typ'] === 'hp_zmena' && $u['mapa_id'] !== null) {
            return !isset($skryteEntity[($u['payload']['typ_entity'] ?? '') . ':' . (int)($u['payload']['entita_id'] ?? 0) . ':' . (int)$u['mapa_id']]);
        }
        return true;
    }));
}

echo json_encode(['udalosti' => $udalosti], JSON_UNESCAPED_UNICODE);

