<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';

// Úprava vzhledu a polohy tokenu: otočení, velikost, barva, zámek, název,
// obrázek, viditelnost pro hráče, pořadí vrstev. PJ/admin smí všechno;
// vlastník postavy jen otočit token (směr čelem je v rozšířeném soubojovém
// systému součást pravidel, h1621) a nastavit mu obrázek — a to u
// nezamčeného tokenu a jen když je na tahu.
header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

function token_uprava_chyba(int $kod, string $zprava): void
{
    http_response_code($kod);
    echo json_encode(['error' => $zprava]);
    exit;
}

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$tokenId = (int)($data['token_id'] ?? 0);
$zmeny = is_array($data['zmeny'] ?? null) ? $data['zmeny'] : [];
if (!$zmeny) {
    token_uprava_chyba(422, 'Není co měnit.');
}

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT t.*, m.svet_id FROM tokeny t JOIN mapy m ON m.id = t.mapa_id WHERE t.id = ?');
$stmt->execute([$tokenId]);
$token = $stmt->fetch();
if (!$token || !dracak_vtt_svet_access($user, (int)$token['svet_id'])) {
    token_uprava_chyba(404, 'Token nenalezen.');
}
$svetId = (int)$token['svet_id'];
$jePj = dracak_vtt_je_pj_sveta($user, $svetId);
$funkce = dracak_vtt_tokeny_funkce();
if (!$funkce['udalosti']) {
    token_uprava_chyba(409, 'Tahle funkce se zapne po jednorázovém kroku správce (ruční SQL, viz docs/sql-k-rucnimu-spusteni.sql).');
}

$smiVlastnik = ['smer', 'obrazek_id'];
$jeVlastnik = !$jePj && dracak_vtt_can_move_token($user, $token);
if (!$jePj) {
    if (!$jeVlastnik) {
        token_uprava_chyba(403, 'Tímhle tokenem hýbat nesmíš.');
    }
    foreach (array_keys($zmeny) as $klic) {
        if (!in_array($klic, $smiVlastnik, true)) {
            token_uprava_chyba(403, 'Tuhle vlastnost smí měnit jen PJ.');
        }
    }
    if (!empty($token['zamceno'])) {
        token_uprava_chyba(403, 'Token je zamčený.');
    }
    if (!dracak_vtt_je_na_tahu($user, (int)$token['mapa_id'], $tokenId)) {
        token_uprava_chyba(403, 'Teď nejsi na tahu.');
    }
}

$sloupce = [];   // sloupec => hodnota pro UPDATE
$vysledek = [];  // co se pošle klientům
foreach ($zmeny as $klic => $hodnota) {
    switch ($klic) {
        case 'smer':
            if (!$funkce['smer']) token_uprava_chyba(409, 'Otáčení tokenů ještě není zapnuté (chybí ruční SQL).');
            $smer = ((int)round((float)$hodnota) % 360 + 360) % 360;
            $sloupce['smer'] = $smer;
            $vysledek['smer'] = $smer;
            break;
        case 'velikost':
            if (!$funkce['velikost']) token_uprava_chyba(409, 'Velikost tokenů ještě není zapnutá (chybí ruční SQL).');
            $v = round((float)$hodnota * 4) / 4;
            if ($v < 0.25 || $v > 8) token_uprava_chyba(422, 'Velikost musí být od 0,25 do 8 sáhů.');
            $sloupce['velikost_sahu'] = $v;
            $vysledek['velikost'] = $v;
            break;
        case 'barva':
            if (!$funkce['barva']) token_uprava_chyba(409, 'Barvy tokenů ještě nejsou zapnuté (chybí ruční SQL).');
            if ($hodnota === null || $hodnota === '') {
                $sloupce['barva'] = null;
                $vysledek['barva'] = null;
            } elseif (is_string($hodnota) && preg_match('/^#[0-9a-fA-F]{6}$/', $hodnota)) {
                $sloupce['barva'] = strtolower($hodnota);
                $vysledek['barva'] = strtolower($hodnota);
            } else {
                token_uprava_chyba(422, 'Barva musí být ve tvaru #rrggbb.');
            }
            break;
        case 'zamceno':
            if (!$funkce['zamek']) token_uprava_chyba(409, 'Zámek tokenů ještě není zapnutý (chybí ruční SQL).');
            $sloupce['zamceno'] = $hodnota ? 1 : 0;
            $vysledek['zamceno'] = (bool)$hodnota;
            break;
        case 'nazev':
            if (!$funkce['nazev']) token_uprava_chyba(409, 'Přejmenování tokenů ještě není zapnuté (chybí ruční SQL).');
            if ($token['typ_entity'] === 'postava') token_uprava_chyba(422, 'Postava se jmenuje podle deníku, přejmenuj ji tam.');
            $nazev = trim((string)$hodnota);
            if (mb_strlen($nazev) > 100) token_uprava_chyba(422, 'Název je moc dlouhý (nejvíc 100 znaků).');
            $sloupce['nazev'] = $nazev === '' ? null : $nazev;
            $vysledek['nazev'] = $nazev === '' ? null : $nazev;
            break;
        case 'obrazek_id':
            if (!$funkce['obrazek']) token_uprava_chyba(409, 'Obrázky tokenů ještě nejsou zapnuté (chybí ruční SQL).');
            if ($hodnota === null || $hodnota === '' || (int)$hodnota === 0) {
                $sloupce['obrazek_id'] = null;
                $vysledek['obrazek_id'] = null;
            } else {
                $q = $pdo->prepare('SELECT id FROM svet_obrazky WHERE id = ? AND svet_id = ?');
                $q->execute([(int)$hodnota, $svetId]);
                if (!$q->fetchColumn()) token_uprava_chyba(404, 'Obrázek v knihovně světa nenalezen.');
                $sloupce['obrazek_id'] = (int)$hodnota;
                $vysledek['obrazek_id'] = (int)$hodnota;
            }
            break;
        case 'viditelny':
            $sloupce['viditelny_hracum'] = $hodnota ? 1 : 0;
            $vysledek['viditelny'] = (bool)$hodnota;
            break;
        case 'z':
            $z = max(-100, min(100, (int)$hodnota));
            $sloupce['z_poradi'] = $z;
            $vysledek['z'] = $z;
            break;
        default:
            token_uprava_chyba(422, 'Neznámá vlastnost „' . $klic . '“.');
    }
}

$sety = [];
$params = [];
foreach ($sloupce as $sloupec => $hodnota) {
    $sety[] = "`$sloupec` = ?";
    $params[] = $hodnota;
}
$params[] = $tokenId;
$pdo->prepare('UPDATE tokeny SET ' . implode(', ', $sety) . ' WHERE id = ?')->execute($params);

// Hodnoty před změnou — podle nich jde událost vrátit tlačítkem "Krok zpět".
$pred = [];
foreach (array_keys($vysledek) as $klic) {
    $pred[$klic] = dracak_vtt_token_hodnota_klice($token, $klic);
}
$udalostId = dracak_vtt_log_event($svetId, (int)$token['mapa_id'], 'token_zmena', [
    'token_id' => $tokenId,
    'zmeny' => $vysledek,
    'pred' => $pred,
], (int)$user['id']);

echo json_encode(['ok' => true, 'udalost_id' => $udalostId, 'zmeny' => $vysledek]);
