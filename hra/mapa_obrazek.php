<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

// Servíruje nahraný obrázek mapy až po kontrole přístupu ke světu — nikdy
// přímá statická URL do uploads/mapy/ (ta je Require-all-denied, viz
// uploads/.htaccess). Stejný vzor jako pravidla.php čte content/.

$user = dracak_require_login();
$mapaId = (int)($_GET['id'] ?? 0);

$stmt = dracak_db()->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !$mapa['obrazek_cesta']) {
    http_response_code(404);
    die('Mapa nenalezena.');
}
if (!dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(403);
    die('Nemáš přístup k téhle mapě.');
}

$cesta = __DIR__ . '/../uploads/mapy/' . basename($mapa['obrazek_cesta']);
if (!is_file($cesta)) {
    http_response_code(404);
    die('Soubor obrázku chybí.');
}

$mime = match (true) {
    str_ends_with($cesta, '.png') => 'image/png',
    str_ends_with($cesta, '.webp') => 'image/webp',
    default => 'image/jpeg',
};
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
readfile($cesta);

