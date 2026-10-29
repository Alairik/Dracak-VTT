<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_denik.php';

// Hody z Osobního deníku (hra/denik.php): hod na útok, na obranu a obecné
// "1k6+". Číslo, ke kterému se hod přičítá, počítá SERVER z dat postavy
// (stejné funkce jako deník), klient posílá jen co chce hodit — tím se
// zobrazené ÚČ/OČ a skutečně hozené nemůžou rozejít.
//
// Pravidla (content/pravidla-hrac.html): "1k6+" = hod šestistěnnou kostkou
// s opakováním po šestce, výsledky se sčítají (b58 v kap. Kostky). Hod na
// útok = 1k6+ + ÚČ (h1610/h1615), na obranu = 1k6+ + OČ (h1611). Opravy:
// postih za méně než třetinu životů (podle odolnosti, h1610 Tabulka
// bojeschopnosti), −1 za velké naložení (h1610/h1611/h1616), +1 za štít k
// hodu na obranu (h1611). Rozdíl velikostí, dostřel, úkryt a další
// okolnosti určuje hráč/PJ ručně v poli `modifikator` (pravidla je řeší
// podle situace u stolu).
//
// Vstup JSON: {postava_id, druh: utok|strelba|obrana|k6plus, zbran_id?, modifikator?}
// Výstup: {ok, popis, hody[], zaklad, opravy[{nazev,hodnota}], celkem}
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

function odpoved_hod(int $kod, array $data): void
{
    http_response_code($kod);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$postavaId = (int)($data['postava_id'] ?? 0);
$druh = dracak_text($data['druh'] ?? '');
$modifikator = max(-30, min(30, (int)($data['modifikator'] ?? 0)));

$pdo = dracak_db();
$postava = dracak_vtt_entity_row('postava', $postavaId);
if (!$postava) {
    odpoved_hod(404, ['error' => 'Postava nenalezena.']);
}
if (!dracak_vtt_can_edit_hp($user, 'postava', $postava)) {
    odpoved_hod(403, ['error' => 'Za tuhle postavu házet nesmíš.']);
}
$svetId = (int)($postava['svet_id'] ?? 0);

$d = dracak_vtt_denik_data($pdo, $postava);
$hody = dracak_vtt_hod_k6_plus();
$kostky = array_sum($hody);

$zaklad = 0;
$popis = '';
$opravy = [];
$postihZivoty = $d['bojeschopnost']['postih_aktualni'] ?? 0;
$velkeNalozeni = in_array($d['pohyblivost']['nalozeni']['kod'] ?? null, ['velke', 'prilis_velke'], true);

switch ($druh) {
    case 'utok':
    case 'strelba': {
        $zbranId = (int)($data['zbran_id'] ?? 0);
        $zbran = null;
        foreach ($d['zbrane'] as $z) {
            if ($z['id'] === $zbranId) { $zbran = $z; }
        }
        if (!$zbran) {
            odpoved_hod(422, ['error' => 'Tuhle zbraň postava nemá.']);
        }
        $uc = $druh === 'strelba' ? $zbran['uc_strelecky'] : $zbran['uc_tvar_v_tvar'];
        if ($uc === null) {
            odpoved_hod(422, ['error' => 'Útočné číslo téhle zbraně se nedá spočítat (chybí síla zbraně nebo vlastnosti postavy).']);
        }
        $zaklad = $uc;
        $popis = ($druh === 'strelba' ? 'Střelba: ' : 'Útok: ') . $zbran['nazev'] . ' (ÚČ ' . $uc . ')';
        if ($postihZivoty !== 0) { $opravy[] = ['nazev' => 'méně než 1/3 životů', 'hodnota' => $postihZivoty]; }
        if ($velkeNalozeni) { $opravy[] = ['nazev' => 'velké naložení', 'hodnota' => -1]; }
        break;
    }
    case 'obrana': {
        if (!$d['obrana']) {
            odpoved_hod(422, ['error' => 'Doplň obratnost postavy.']);
        }
        $zaklad = $d['obrana']['oc'];
        $popis = 'Obrana (OČ ' . $zaklad . ')';
        if ($d['obrana']['bonus_stit']) { $opravy[] = ['nazev' => 'štít', 'hodnota' => 1]; }
        if ($postihZivoty !== 0) { $opravy[] = ['nazev' => 'méně než 1/3 životů', 'hodnota' => $postihZivoty]; }
        if ($velkeNalozeni) { $opravy[] = ['nazev' => 'velké naložení', 'hodnota' => -1]; }
        break;
    }
    case 'k6plus':
        $popis = 'Hod 1k6+';
        break;
    default:
        odpoved_hod(422, ['error' => 'Neznámý druh hodu.']);
}
if ($modifikator !== 0) {
    $opravy[] = ['nazev' => 'ruční oprava', 'hodnota' => $modifikator];
}
$celkem = $kostky + $zaklad + array_sum(array_column($opravy, 'hodnota'));

$eventId = null;
if ($svetId > 0) {
    $stmt = $pdo->prepare('SELECT aktivni_mapa_id FROM svet WHERE id = ?');
    $stmt->execute([$svetId]);
    $mapaId = $stmt->fetchColumn();
    $eventId = dracak_vtt_log_event($svetId, $mapaId !== false && $mapaId !== null ? (int)$mapaId : null, 'kostka_hod', [
        'notace' => '1k6+',
        'typ_kostky' => 6,
        'hody' => $hody,
        'bonus' => $zaklad + array_sum(array_column($opravy, 'hodnota')),
        'celkem' => $celkem,
        'hodil' => $user['jmeno'],
        'popis' => $popis . ' — ' . $postava['nazev'],
    ], (int)$user['id']);
}

echo json_encode([
    'ok' => true,
    'udalost_id' => $eventId,
    'popis' => $popis,
    'hody' => $hody,
    'zaklad' => $zaklad,
    'opravy' => $opravy,
    'celkem' => $celkem,
], JSON_UNESCAPED_UNICODE);
