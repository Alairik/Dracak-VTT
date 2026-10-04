<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt_iniciativa.php';

// Posune aktivního na tahu na téhle mapě o jednu akci dál (viz
// includes/vtt_iniciativa.php a report — zjednodušený v1 model
// "každé zavolání = jedna spotřebovaná akce aktuálně aktivního, pak
// předání dalšímu v pořadí"; "až polovina akcí najednou" z h1623 se
// netrackuje, je to dokumentované zjednodušení). PJ/admin only — stejné
// oprávnění jako dnešní hra/api/kolo_konec.php.

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

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
$svetId = (int)$mapa['svet_id'];
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Jen PJ/admin může posunout tah.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM kolo_stav WHERE mapa_id = ?');
$stmt->execute([$mapaId]);
$koloStav = $stmt->fetch();
if (!$koloStav) {
    http_response_code(422);
    echo json_encode(['error' => 'Na téhle mapě ještě nikdo nehodil iniciativu.']);
    exit;
}

$poradi = dracak_vtt_iniciativa_poradi($pdo, $mapaId);
$maUAkce = array_values(array_filter($poradi, fn(array $r): bool => $r['akce_zbyvajici'] > 0));
if (!$maUAkce) {
    http_response_code(422);
    echo json_encode(['error' => 'Nikdo nemá zbývající akce — hoďte iniciativu na nové kolo.']);
    exit;
}

$puvodniAktivniTokenId = $koloStav['aktivni_token_id'] !== null ? (int)$koloStav['aktivni_token_id'] : null;

$pdo->beginTransaction();
try {
    if ($puvodniAktivniTokenId === null) {
        // Začátek kola — ještě nikdo netahal (aktivni_token_id je NULL,
        // typicky hned po prvních hodech iniciativy). Na řadu jde první
        // v pořadí, bez odečtu akce — ta se odečte až při DALŠÍM
        // zavolání téhle funkce (viz komentář výš).
        $novyAktivniTokenId = $maUAkce[0]['token_id'];
    } else {
        // "Spotřebuje" jednu akci aktuálně aktivního.
        $pdo->prepare('UPDATE kolo_iniciativa SET akce_zbyvajici = GREATEST(0, akce_zbyvajici - 1) WHERE mapa_id = ? AND token_id = ?')
            ->execute([$mapaId, $puvodniAktivniTokenId]);

        $poradi = dracak_vtt_iniciativa_poradi($pdo, $mapaId);
        $maUAkce = array_values(array_filter($poradi, fn(array $r): bool => $r['akce_zbyvajici'] > 0));

        if (!$maUAkce) {
            // Konec kola — právě došla poslední akce poslednímu
            // účastníkovi. Nové kolo = nový hod na iniciativu pro
            // všechny (h1622, "na začátku kola"), takže kolo_iniciativa
            // se pro tuhle mapu smaže a čeká se na nové hody přes
            // iniciativa_hod.php — viz report, proč není samostatný
            // endpoint "nové kolo".
            $noveCisloKola = (int)$koloStav['cislo_kola'] + 1;
            $pdo->prepare('UPDATE kolo_stav SET cislo_kola = ?, aktivni_token_id = NULL WHERE mapa_id = ?')
                ->execute([$noveCisloKola, $mapaId]);
            $pdo->prepare('DELETE FROM kolo_iniciativa WHERE mapa_id = ?')->execute([$mapaId]);

            $eventId = dracak_vtt_log_event($svetId, $mapaId, 'kolo_nove', [
                'predchozi_kolo' => (int)$koloStav['cislo_kola'],
                'nove_kolo' => $noveCisloKola,
            ], (int)$user['id']);
            $pdo->commit();

            echo json_encode([
                'ok' => true,
                'udalost_id' => $eventId,
                'kolo_konec' => true,
                'cislo_kola' => $noveCisloKola,
                'aktivni_token_id' => null,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Další v pořadí za právě aktivním (cyklicky přes CELÉ pořadí,
        // přeskočí ty s 0 akcemi) — nikdy hned zpátky na stejného, dokud
        // je v pořadí někdo jiný s akcí navíc.
        $idx = null;
        foreach ($poradi as $i => $r) {
            if ($r['token_id'] === $puvodniAktivniTokenId) {
                $idx = $i;
                break;
            }
        }
        $novyAktivniTokenId = null;
        if ($idx !== null) {
            $n = count($poradi);
            for ($k = 1; $k <= $n; $k++) {
                $kandidat = $poradi[($idx + $k) % $n];
                if ($kandidat['akce_zbyvajici'] > 0) {
                    $novyAktivniTokenId = $kandidat['token_id'];
                    break;
                }
            }
        }
        if ($novyAktivniTokenId === null) {
            $novyAktivniTokenId = $maUAkce[0]['token_id'];
        }
    }

    $pdo->prepare('UPDATE kolo_stav SET aktivni_token_id = ? WHERE mapa_id = ?')
        ->execute([$novyAktivniTokenId, $mapaId]);

    $eventId = dracak_vtt_log_event($svetId, $mapaId, 'tah_zmena', [
        'predchozi_token_id' => $puvodniAktivniTokenId,
        'novy_token_id' => $novyAktivniTokenId,
        'cislo_kola' => (int)$koloStav['cislo_kola'],
    ], (int)$user['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo json_encode([
    'ok' => true,
    'udalost_id' => $eventId,
    'kolo_konec' => false,
    'cislo_kola' => (int)$koloStav['cislo_kola'],
    'aktivni_token_id' => $novyAktivniTokenId,
], JSON_UNESCAPED_UNICODE);
