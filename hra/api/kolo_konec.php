<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();
if (!in_array($user['role'], ['admin', 'pj'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může ukončit kolo.']);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}

// Odpočítá trvání efektů jen entitám, co mají na téhle mapě token —
// entita bez tokenu (např. postava mimo aktuální scénu) se netiká.
$stmt = $pdo->prepare('SELECT DISTINCT typ_entity, entita_id FROM tokeny WHERE mapa_id = ?');
$stmt->execute([$mapaId]);
$entityPairs = $stmt->fetchAll();

$expirovalo = 0;
foreach ($entityPairs as $pair) {
    $stmt = $pdo->prepare(
        'SELECT ae.id, ae.zbyva_kol, e.nazev FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id
         WHERE ae.typ_entity = ? AND ae.entita_id = ? AND ae.zbyva_kol IS NOT NULL'
    );
    $stmt->execute([$pair['typ_entity'], $pair['entita_id']]);
    foreach ($stmt->fetchAll() as $ae) {
        $noveKolo = (int)$ae['zbyva_kol'] - 1;
        if ($noveKolo <= 0) {
            $del = $pdo->prepare('DELETE FROM aktivni_efekty WHERE id = ?');
            $del->execute([$ae['id']]);
            dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'efekt_konci', [
                'typ_entity' => $pair['typ_entity'],
                'entita_id' => $pair['entita_id'],
                'efekt_nazev' => $ae['nazev'],
            ], (int)$user['id']);
            $expirovalo++;
        } else {
            $upd = $pdo->prepare('UPDATE aktivni_efekty SET zbyva_kol = ? WHERE id = ?');
            $upd->execute([$noveKolo, $ae['id']]);
        }
    }
}

echo json_encode(['ok' => true, 'expirovalo' => $expirovalo]);

