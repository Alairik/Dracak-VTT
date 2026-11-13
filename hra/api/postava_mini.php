<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_souboj.php';

// Malá karta postavy po kliknutí na token (assets/js/postava_mini.js): jen to, co je při hře potřeba vidět hned —
// životy, magenergie, útočné číslo se zbraní v ruce a obranné číslo. Plný deník se otevírá dvojklikem nebo tlačítkem.
//   GET ?mapa_id=&token_id=
// Smí PJ světa a vlastník postavy (stejně jako deník); čísla se berou z deníku (dracak_vtt_souboj_bojovnik),
// takže karta ukazuje totéž, s čím počítá souboj.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function postava_mini_konec(int $kod, array $data): void
{
    http_response_code($kod);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$user = dracak_current_user();
if (!$user) {
    postava_mini_konec(401, ['error' => 'Nepřihlášeno.']);
}
$mapaId = (int)dracak_text($_GET['mapa_id'] ?? '');
$tokenId = (int)dracak_text($_GET['token_id'] ?? '');

$pdo = dracak_db();
$q = $pdo->prepare(
    'SELECT t.id, t.typ_entity, t.entita_id, m.svet_id
     FROM tokeny t JOIN mapy m ON m.id = t.mapa_id
     WHERE t.id = ? AND t.mapa_id = ?'
);
$q->execute([$tokenId, $mapaId]);
$token = $q->fetch();
if (!$token || !dracak_vtt_svet_access($user, (int)$token['svet_id']) || $token['typ_entity'] !== 'postava') {
    postava_mini_konec(404, ['error' => 'Postava nenalezena.']);
}
$q = $pdo->prepare(
    'SELECT p.*, po.nazev AS povolani_nazev
     FROM postavy p LEFT JOIN povolani po ON po.id = p.povolani_id
     WHERE p.id = ?'
);
$q->execute([(int)$token['entita_id']]);
$p = $q->fetch();
if (!$p) {
    postava_mini_konec(404, ['error' => 'Postava nenalezena.']);
}
if (!dracak_vtt_je_pj_sveta($user, (int)$token['svet_id']) && (int)($p['vlastnik_ucet_id'] ?? 0) !== (int)$user['id']) {
    postava_mini_konec(403, ['error' => 'Tuhle postavu nevedeš.']);
}

$b = dracak_vtt_souboj_bojovnik($pdo, [
    'typ_entity' => 'postava', 'entita_id' => (int)$p['id'], 'label' => (string)$p['nazev'],
]);
// Zbraň v ruce; když žádnou nedrží, karta to řekne (souboj pak bere boj beze zbraně).
$vRuce = null;
foreach ($b['zbrane'] as $z) {
    if ($z['drzena']) {
        $vRuce = ['nazev' => $z['nazev'], 'uc' => $z['uc']];
        break;
    }
}
// Zbraně, které má postava u sebe (v boji je jde vytasit) — nabídka „v ruce“ na kartě tahu.
$uSebe = [];
foreach (dracak_vtt_denik_data($pdo, $p, false)['zbrane'] as $z) {
    if ($z['u_sebe'] || $z['drzena']) {
        $uSebe[] = ['id' => (int)$z['id'], 'nazev' => (string)$z['nazev'], 'drzena' => (bool)$z['drzena'],
            'uc' => $z['uc_tvar_v_tvar'] !== null ? (int)$z['uc_tvar_v_tvar'] : null];
    }
}
$podtitul = trim((string)($p['povolani_nazev'] ?? ''));
if ($podtitul !== '' && ($p['uroven'] ?? null) !== null) {
    $podtitul .= ' · ' . (int)$p['uroven'] . '. úroveň';
}
postava_mini_konec(200, [
    'postava_id' => (int)$p['id'],
    'nazev' => (string)$p['nazev'],
    'podtitul' => $podtitul,
    'hp' => (int)$p['aktualni_hp'],
    'max_hp' => (int)$p['max_hp'],
    'mag' => $p['max_magenergie'] !== null ? (int)$p['aktualni_magenergie'] : null,
    'max_mag' => $p['max_magenergie'] !== null ? (int)$p['max_magenergie'] : null,
    'zbran' => $vRuce,
    'zbrane' => $uSebe,
    'oc' => $b['oc'] !== null ? (int)$b['oc'] : null,
    'stav' => $b['bojeschopnost'],
]);
