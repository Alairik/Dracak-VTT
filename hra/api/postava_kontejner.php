<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_kontejnery.php';

// Kontejnery postavy (vaky, truhly…) z Osobního deníku (hra/denik.php).
// Vstup JSON: {postava_id, akce, ...}. Akce:
//   vloz {kontejner_id, predmet_id, mnozstvi, potvrdit} — z volného inventáře do kontejneru;
//        při přeplnění vrátí {vyzaduje_potvrzeni: true, upozorneni}, klient po
//        potvrzení pošle znovu s potvrdit: true (upozornění dostane každý, kdo
//        s předmětem manipuluje — hráč i PJ)
//   vyndej {kontejner_id, predmet_id, mnozstvi|null} — zpět do volného inventáře
//   odebrat {kontejner_id} — kontejner zmizí, obsah se vrátí do inventáře
//   rozbalit {predmet_id} — vak/truhla ležící ve volném inventáři se změní na kontejner
//   nosic {kontejner_id, nosic: postava|zvire} · prejmenovat {kontejner_id, nazev}
// Oprávnění stejné jako u inventáře (vlastník, PJ/admin).
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

function odpoved_kont(int $kod, array $data): void
{
    http_response_code($kod);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$postavaId = (int)($data['postava_id'] ?? 0);
$akce = (string)($data['akce'] ?? '');

$pdo = dracak_db();
$postava = dracak_vtt_entity_row('postava', $postavaId);
if (!$postava) {
    odpoved_kont(404, ['error' => 'Postava nenalezena.']);
}
if (!dracak_vtt_can_edit_hp($user, 'postava', $postava)) {
    odpoved_kont(403, ['error' => 'Tuhle postavu upravovat nesmíš.']);
}
if (!dracak_vtt_kontejnery_dostupne($pdo)) {
    odpoved_kont(409, ['error' => 'Kontejnery se zatím nedají používat (čeká se na databázi).']);
}

$kontejnerId = (int)($data['kontejner_id'] ?? 0);
switch ($akce) {
    case 'vloz':
        $v = dracak_vtt_kontejner_vloz($pdo, $postavaId, $kontejnerId, (int)($data['predmet_id'] ?? 0), (int)($data['mnozstvi'] ?? 1), !empty($data['potvrdit']));
        break;
    case 'vyndej':
        $mnozstvi = isset($data['mnozstvi']) && $data['mnozstvi'] !== null ? (int)$data['mnozstvi'] : null;
        $v = dracak_vtt_kontejner_vyndej($pdo, $postavaId, $kontejnerId, (int)($data['predmet_id'] ?? 0), $mnozstvi);
        break;
    case 'odebrat':
        $v = dracak_vtt_kontejner_odebrat($pdo, $postavaId, $kontejnerId);
        break;
    case 'rozbalit':
        $v = dracak_vtt_kontejner_rozbalit($pdo, $postavaId, (int)($data['predmet_id'] ?? 0));
        break;
    case 'nosic':
        $v = dracak_vtt_kontejner_nosic($pdo, $postava, $kontejnerId, (string)($data['nosic'] ?? ''));
        break;
    case 'prejmenovat':
        $v = dracak_vtt_kontejner_prejmenovat($pdo, $postavaId, $kontejnerId, (string)($data['nazev'] ?? ''));
        break;
    default:
        odpoved_kont(422, ['error' => 'Neznámá akce.']);
}
if (empty($v['ok'])) {
    if (!empty($v['vyzaduje_potvrzeni'])) {
        odpoved_kont(200, ['ok' => false, 'vyzaduje_potvrzeni' => true, 'upozorneni' => $v['upozorneni']]);
    }
    odpoved_kont(422, ['error' => $v['chyba'] ?? 'Nepovedlo se.']);
}
odpoved_kont(200, ['ok' => true, 'upozorneni' => $v['upozorneni'] ?? null]);
