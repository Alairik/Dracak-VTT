<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

// Vydává obrázek z knihovny světa až po kontrole přístupu ke světu (složka
// uploads/ je přímému přístupu zavřená, viz uploads/.htaccess).
$user = dracak_require_login();
$id = (int)($_GET['id'] ?? 0);
try {
    $stmt = dracak_db()->prepare('SELECT svet_id, soubor FROM svet_obrazky WHERE id = ?');
    $stmt->execute([$id]);
    $obr = $stmt->fetch();
} catch (Throwable $e) {
    $obr = false;
}
if (!$obr || !dracak_vtt_svet_access($user, (int)$obr['svet_id'])) {
    http_response_code(404);
    exit;
}
$cesta = __DIR__ . '/../uploads/obrazky/' . basename((string)$obr['soubor']);
if (!is_file($cesta)) {
    http_response_code(404);
    exit;
}
$etag = '"' . md5((string)filemtime($cesta) . $obr['soubor']) . '"';
header('ETag: ' . $etag);
header('Cache-Control: private, max-age=86400');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: image/png');
header('Content-Length: ' . filesize($cesta));
readfile($cesta);
