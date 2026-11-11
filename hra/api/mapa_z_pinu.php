<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_body.php';

// Nová mapa rovnou z okna pinu (zadání uživatele 2026-11-11: „v pinu na mapě mi chybí možnost přidat
// mapu odtamtud. musím nejdřív zvenku vytvořit mapu a pin pak napojit. líbilo by se mi při vytváření
// nebo úpravě pinu moct nahrát mapu“).
//
// Multipart: svet_id, nazev, obrazek, volitelně bod_id (existující pin, na který se mapa rovnou napojí),
// grid_typ (ctverec|hex) a grid_velikost_px. Mřížku si PJ obvykle doladí až na nové mapě, proto je
// nepovinná — bez ní se mapa chová stejně jako mapa založená ve správě světa bez mřížky.
// Právo: jen PJ světa (mapy zakládá jen on).

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

function mapa_z_pinu_chyba(int $kod, string $text): void
{
    http_response_code($kod);
    echo json_encode(['error' => $text]);
    exit;
}

$svetId = (int)($_POST['svet_id'] ?? 0);
$nazev = mb_substr(trim(dracak_text($_POST['nazev'] ?? '')), 0, 150);
$bodId = (int)($_POST['bod_id'] ?? 0);
$gridTyp = ($_POST['grid_typ'] ?? 'ctverec') === 'hex' ? 'hex' : 'ctverec';
$gridPx = trim(dracak_text($_POST['grid_velikost_px'] ?? ''));
$gridVelikostPx = $gridPx !== '' ? max(1, (int)$gridPx) : null;

if (!dracak_vtt_svet_access($user, $svetId)) {
    mapa_z_pinu_chyba(404, 'Svět nenalezen.');
}
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    mapa_z_pinu_chyba(403, 'Mapy zakládá jen Pán jeskyně.');
}
if ($nazev === '') {
    mapa_z_pinu_chyba(422, 'Mapa musí mít název.');
}

$soubor = $_FILES['obrazek'] ?? [];
if (empty($soubor['tmp_name']) || !is_uploaded_file($soubor['tmp_name'])) {
    mapa_z_pinu_chyba(422, 'Vyber obrázek mapy.');
}
$povoleneTypy = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
$info = @getimagesize($soubor['tmp_name']);
if ($info === false || !isset($povoleneTypy[$info['mime']])) {
    mapa_z_pinu_chyba(422, 'Obrázek mapy musí být PNG, JPG nebo WEBP.');
}
$nazevSouboru = bin2hex(random_bytes(16)) . '.' . $povoleneTypy[$info['mime']];
$cil = __DIR__ . '/../../uploads/mapy/' . $nazevSouboru;
if (!move_uploaded_file($soubor['tmp_name'], $cil)) {
    mapa_z_pinu_chyba(500, 'Nepodařilo se uložit nahraný obrázek mapy.');
}

$pdo = dracak_db();
$stmt = $pdo->prepare(
    'INSERT INTO mapy (svet_id, nazev, typ_mapy, obrazek_cesta, sirka_px, vyska_px, grid_velikost_px, grid_typ, grid_posun_x, grid_posun_y)
     VALUES (?, ?, "zona", ?, ?, ?, ?, ?, 0, 0)'
);
$stmt->execute([$svetId, $nazev, $nazevSouboru, (int)$info[0], (int)$info[1], $gridVelikostPx, $gridTyp]);
$mapaId = (int)$pdo->lastInsertId();

// Pin, ze kterého se mapa zakládala, se na ni rovnou napojí — to je celý smysl téhle cesty.
$napojeno = false;
if ($bodId > 0) {
    $q = $pdo->prepare('SELECT b.*, m.svet_id FROM mapa_body b JOIN mapy m ON m.id = b.mapa_id WHERE b.id = ?');
    $q->execute([$bodId]);
    $bod = $q->fetch();
    if ($bod && (int)$bod['svet_id'] === $svetId) {
        $chyba = dracak_vtt_mapa_bod_validuj_cil($pdo, $svetId, (int)$bod['mapa_id'], $mapaId);
        if ($chyba === null) {
            $pdo->prepare('UPDATE mapa_body SET cilova_mapa_id = ? WHERE id = ?')->execute([$mapaId, $bodId]);
            $napojeno = true;
            dracak_vtt_log_event($svetId, (int)$bod['mapa_id'], 'mapa_bod_pridan', [
                'id' => $bodId, 'nazev' => (string)$bod['nazev'], 'typ' => (string)$bod['typ'],
                'cilova_mapa_id' => $mapaId, 'viditelny_hracum' => (bool)$bod['viditelny_hracum'],
                'x' => (int)$bod['x'], 'y' => (int)$bod['y'], 'akce' => 'upraven',
            ], (int)$user['id']);
        }
    }
}

echo json_encode([
    'ok' => true, 'mapa_id' => $mapaId, 'nazev' => $nazev,
    'sirka_px' => (int)$info[0], 'vyska_px' => (int)$info[1], 'napojeno' => $napojeno,
], JSON_UNESCAPED_UNICODE);
