<?php
declare(strict_types=1);

// Knihovna obrázků světa (portréty tokenů). Nahraný obrázek se zmenší na
// nejvýš 256 px a uloží jako PNG (průhlednost zůstane) pod náhodným názvem
// do uploads/obrazky/ — složka je zavřená přímému přístupu (uploads/.htaccess),
// obrázky se vydávají jen přes hra/obrazek.php po kontrole přístupu ke světu.

const DRACAK_VTT_OBRAZEK_MAX_VSTUP = 4 * 1024 * 1024; // 4 MB nahrávaný soubor
const DRACAK_VTT_OBRAZEK_MAX_PX = 256;
const DRACAK_VTT_OBRAZKU_NA_SVET = 300;

function dracak_vtt_obrazky_adresar(): string
{
    $dir = __DIR__ . '/../uploads/obrazky';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

// Vrací ['id' => int, 'nazev' => string] nebo ['chyba' => string].
function dracak_vtt_obrazek_ulozit(PDO $pdo, int $svetId, int $ucetId, array $soubor, string $nazev): array
{
    if (($soubor['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($soubor['tmp_name']) || !is_uploaded_file($soubor['tmp_name'])) {
        return ['chyba' => 'Soubor se nepodařilo nahrát (je moc velký nebo se přenos přerušil).'];
    }
    if ((int)$soubor['size'] > DRACAK_VTT_OBRAZEK_MAX_VSTUP) {
        return ['chyba' => 'Obrázek je větší než 4 MB — zmenši ho.'];
    }
    $q = $pdo->prepare('SELECT COUNT(*) FROM svet_obrazky WHERE svet_id = ?');
    $q->execute([$svetId]);
    if ((int)$q->fetchColumn() >= DRACAK_VTT_OBRAZKU_NA_SVET) {
        return ['chyba' => 'Knihovna světa je plná (' . DRACAK_VTT_OBRAZKU_NA_SVET . ' obrázků).'];
    }
    $info = @getimagesize($soubor['tmp_name']);
    $povolene = ['image/png', 'image/jpeg', 'image/webp'];
    if ($info === false || !in_array($info['mime'], $povolene, true)) {
        return ['chyba' => 'Obrázek musí být PNG, JPG nebo WEBP.'];
    }
    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($w < 1 || $h < 1 || $w * $h > 40_000_000) {
        return ['chyba' => 'Obrázek má neplatné rozměry.'];
    }

    $dir = dracak_vtt_obrazky_adresar();
    $nazevSouboru = bin2hex(random_bytes(16)) . '.png';
    $cil = $dir . '/' . $nazevSouboru;
    $ulozeno = false;

    if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
        $zdroj = @imagecreatefromstring((string)file_get_contents($soubor['tmp_name']));
        if ($zdroj !== false) {
            $meritko = min(1.0, DRACAK_VTT_OBRAZEK_MAX_PX / max($w, $h));
            $nw = max(1, (int)round($w * $meritko));
            $nh = max(1, (int)round($h * $meritko));
            $cilObr = imagecreatetruecolor($nw, $nh);
            imagealphablending($cilObr, false);
            imagesavealpha($cilObr, true);
            imagefill($cilObr, 0, 0, imagecolorallocatealpha($cilObr, 0, 0, 0, 127));
            imagecopyresampled($cilObr, $zdroj, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ulozeno = imagepng($cilObr, $cil, 6);
            imagedestroy($zdroj);
            imagedestroy($cilObr);
            $w = $nw;
            $h = $nh;
        }
    }
    if (!$ulozeno) {
        // Bez GD: bere se jen malý soubor beze změny (PNG s příponou .png by
        // lhalo o typu, proto jen PNG; jinak chyba s radou).
        if ($info['mime'] === 'image/png' && (int)$soubor['size'] <= 600 * 1024) {
            $ulozeno = move_uploaded_file($soubor['tmp_name'], $cil);
        } else {
            return ['chyba' => 'Server neumí obrázek zmenšit — nahraj PNG do 600 kB.'];
        }
    }
    if (!$ulozeno) {
        return ['chyba' => 'Obrázek se nepodařilo uložit na server.'];
    }

    $nazev = trim($nazev);
    if ($nazev === '') {
        $nazev = pathinfo((string)($soubor['name'] ?? 'obrázek'), PATHINFO_FILENAME);
    }
    $nazev = mb_substr($nazev, 0, 100);
    $pdo->prepare('INSERT INTO svet_obrazky (svet_id, nazev, soubor, sirka, vyska, ucet_id) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$svetId, $nazev, $nazevSouboru, $w, $h, $ucetId]);
    return ['id' => (int)$pdo->lastInsertId(), 'nazev' => $nazev];
}
