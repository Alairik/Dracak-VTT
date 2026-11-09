<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_oblast.php';

// Import oblasti ze ZIPu. Dvoufázový — nejdřív kontrola, pak zápis (import bez kontroly by tiše vyrobil
// NPC bez pravidel a projevilo by se to až v boji). Jen PJ/admin.
//   POST multipart: svet_id, soubor=<zip>, akce=kontrola|zapis
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = dracak_require_login();

$svetId = (int)($_POST['svet_id'] ?? 0);
$akce = (string)($_POST['akce'] ?? 'kontrola');
if (!dracak_vtt_svet_access($user, $svetId)) {
    http_response_code(404);
    echo json_encode(['error' => 'Svět nenalezen.']);
    exit;
}
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Oblast přidává do světa jen PJ.']);
    exit;
}
if (!in_array($akce, ['kontrola', 'zapis'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Neznámá akce.']);
    exit;
}
if (!isset($_FILES['soubor']) || ($_FILES['soubor']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'Chybí soubor s oblastí (ZIP).']);
    exit;
}
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode(['error' => 'Server neumí číst ZIP (chybí rozšíření zip).']);
    exit;
}
$zip = new ZipArchive();
if ($zip->open($_FILES['soubor']['tmp_name']) !== true) {
    http_response_code(422);
    echo json_encode(['error' => 'Soubor není platný ZIP.']);
    exit;
}
$manifest = json_decode((string)$zip->getFromName('manifest.json'), true);
$data = json_decode((string)$zip->getFromName('data.json'), true);
if (!is_array($manifest) || !is_array($data) || !isset($data['mapy'])) {
    $zip->close();
    http_response_code(422);
    echo json_encode(['error' => 'V balíčku chybí popis oblasti — není to soubor vyvezený z Dračáku.']);
    exit;
}
if ((int)($manifest['format'] ?? 0) !== DRACAK_OBLAST_FORMAT) {
    $zip->close();
    http_response_code(422);
    echo json_encode(['error' => 'Balíček je z jiné verze (' . (int)($manifest['format'] ?? 0) . '), tahle umí ' . DRACAK_OBLAST_FORMAT . '.']);
    exit;
}
$pdo = dracak_db();
if ($akce === 'kontrola') {
    $zip->close();
    echo json_encode(['ok' => true, 'nazev' => (string)($manifest['nazev'] ?? ''), 'kontrola' => dracak_vtt_oblast_kontrola($pdo, $data)], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- zápis ---
// Soubory z balíčku se rozbalí do vlastní prázdné složky a dál se s nimi pracuje jen přes cestu.
$docasna = sys_get_temp_dir() . '/oblast_' . bin2hex(random_bytes(8));
@mkdir($docasna, 0700, true);
$soubory = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $jmenoVZipu = (string)$zip->getNameIndex($i);
    if (!preg_match('#^uploads/(mapy|obrazky)/[A-Za-z0-9._-]+$#', $jmenoVZipu)) {
        continue;   // nic mimo uploads a žádné cestičky s ../
    }
    $cil = $docasna . '/' . basename($jmenoVZipu);
    $obsah = $zip->getFromIndex($i);
    if ($obsah === false || strlen($obsah) > 16 * 1024 * 1024) {
        continue;
    }
    file_put_contents($cil, $obsah);
    // Jen skutečné obrázky — ZIP může nést cokoliv.
    if (@getimagesize($cil) === false) {
        @unlink($cil);
        continue;
    }
    $soubory[$jmenoVZipu] = $cil;
}
$zip->close();

$pdo->beginTransaction();
try {
    $v = dracak_vtt_oblast_import($pdo, $svetId, $data, $soubory);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    foreach (glob($docasna . '/*') ?: [] as $f) { @unlink($f); }
    @rmdir($docasna);
    throw $e;
}
foreach (glob($docasna . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($docasna);

dracak_vtt_log_event($svetId, $v['prvni_mapa'] ?? null, 'oblast_import', [
    'nazev' => (string)($manifest['nazev'] ?? ''),
    'map' => count($v['mapy']),
], (int)$user['id']);

echo json_encode(['ok' => true, 'map' => count($v['mapy']), 'prvni_mapa' => $v['prvni_mapa'], 'preskoceno' => $v['preskoceno']], JSON_UNESCAPED_UNICODE);
