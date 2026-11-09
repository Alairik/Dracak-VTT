<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_oblast.php';

// Export oblasti do ZIPu: mapa (nebo mapa, na kterou vede pin) a všechno pod ní. Jen PJ/admin.
//   GET ?mapa_id=…  nebo  ?bod_id=…   → soubor ke stažení
//   GET ?…&nahled=1                   → jen JSON souhrn (co by se vyvezlo), nic se nestahuje
$user = dracak_require_login();
$pdo = dracak_db();

$mapaId = (int)($_GET['mapa_id'] ?? 0);
$bodId = (int)($_GET['bod_id'] ?? 0);
if ($bodId > 0) {
    $q = $pdo->prepare('SELECT cilova_mapa_id FROM mapa_body WHERE id = ?');
    $q->execute([$bodId]);
    $mapaId = (int)($q->fetchColumn() ?: 0);
}
$q = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$q->execute([$mapaId]);
$mapa = $q->fetch(PDO::FETCH_ASSOC);
$jeNahled = !empty($_GET['nahled']);
if ($jeNahled) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
$svetId = (int)$mapa['svet_id'];
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Oblast vyváží jen PJ.']);
    exit;
}
$v = dracak_vtt_oblast_export($pdo, $svetId, $mapaId);
if (isset($v['chyba'])) {
    http_response_code(422);
    echo json_encode(['error' => $v['chyba']]);
    exit;
}
if ($jeNahled) {
    echo json_encode(['ok' => true, 'nazev' => $v['nazev'], 'souhrn' => $v['souhrn']], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server neumí vyrobit ZIP (chybí rozšíření zip).']);
    exit;
}
$manifest = [
    'format' => DRACAK_OBLAST_FORMAT,
    'nazev' => $v['nazev'],
    'vyvezeno' => date('c'),
    'souhrn' => $v['souhrn'],
];
$tmp = tempnam(sys_get_temp_dir(), 'oblast');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ZIP se nepodařilo vyrobit.']);
    exit;
}
$zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$zip->addFromString('data.json', json_encode($v['data'], JSON_UNESCAPED_UNICODE));
foreach ($v['soubory'] as $vZipu => $naDisku) {
    $zip->addFile($naDisku, $vZipu);
}
$zip->close();

// Název mapy bývá s diakritikou; prohlížeč rozumí UTF-8 jen přes filename* (RFC 5987), prosté filename
// musí zůstat v ASCII, jinak se jméno souboru rozsype („TestovacÃ­-mapa“).
$cisty = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', (string)$v['nazev']);
$cisty = trim((string)$cisty, '-') ?: 'oblast';
$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $cisty);
$ascii = preg_replace('/[^A-Za-z0-9_-]+/', '-', $ascii === false ? 'oblast' : $ascii);
$ascii = trim((string)$ascii, '-') ?: 'oblast';
header('Content-Type: application/zip');
header("Content-Disposition: attachment; filename=\"oblast-$ascii.zip\"; filename*=UTF-8''" . rawurlencode('oblast-' . $cisty . '.zip'));
header('Content-Length: ' . (string)filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);
@unlink($tmp);
