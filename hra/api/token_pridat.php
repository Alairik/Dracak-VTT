<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';
require_once __DIR__ . '/../../includes/vtt_mlha.php';
require_once __DIR__ . '/../../includes/vtt_mapa_stav.php';
require_once __DIR__ . '/../../includes/vtt_tokeny.php';

header('Content-Type: application/json; charset=utf-8');
$user = dracak_require_login();

$data = json_decode((string)file_get_contents('php://input'), true) ?: [];
$mapaId = (int)($data['mapa_id'] ?? 0);
$typEntity = in_array($data['typ_entity'] ?? 'postava', ['nestvura', 'obecny'], true) ? $data['typ_entity'] : 'postava';
$x = (int)($data['x'] ?? 0);
$y = (int)($data['y'] ?? 0);

$pdo = dracak_db();
$stmt = $pdo->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa || !dracak_vtt_svet_access($user, (int)$mapa['svet_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Mapa nenalezena.']);
    exit;
}
$isPjOrAdmin = dracak_vtt_je_pj_sveta($user, (int)$mapa['svet_id']);

$obecnyNazev = null;
$obecnyBarva = null;
$obecnaVelikost = 1.0;
if ($typEntity === 'obecny') {
    // Obecný token (NPC bez statistik, překážka, předmět na zemi, značka) —
    // žádná entita za ním není, drží se jen na mapě. Jen PJ/admin.
    if (!$isPjOrAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Jen PJ/admin může přidat vlastní token.']);
        exit;
    }
    if (!dracak_vtt_tokeny_funkce()['obecny']) {
        http_response_code(409);
        echo json_encode(['error' => 'Vlastní tokeny se zapnou po jednorázovém kroku správce (ruční SQL).']);
        exit;
    }
    $obecnyNazev = trim((string)($data['nazev'] ?? ''));
    if ($obecnyNazev === '' || mb_strlen($obecnyNazev) > 100) {
        http_response_code(422);
        echo json_encode(['error' => 'Vlastní token potřebuje název (nejvíc 100 znaků).']);
        exit;
    }
    if (isset($data['barva']) && is_string($data['barva']) && preg_match('/^#[0-9a-fA-F]{6}$/', $data['barva'])) {
        $obecnyBarva = strtolower($data['barva']);
    }
    $obecnaVelikost = max(0.25, min(8.0, round((float)($data['velikost'] ?? 1) * 4) / 4));
    $entitaId = 0;
    $nazev = $obecnyNazev;
} elseif ($typEntity === 'nestvura') {
    if (!$isPjOrAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Jen PJ/admin může přidat nestvůru.']);
        exit;
    }
    $nestvuraId = (int)($data['nestvura_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM nestvury WHERE id = ?');
    $stmt->execute([$nestvuraId]);
    $nestvura = $stmt->fetch();
    if (!$nestvura) {
        http_response_code(404);
        echo json_encode(['error' => 'Nestvůra nenalezena.']);
        exit;
    }
    // Životaschopnost je v katalogu volný text ("20", "20-25", "viz text"...) —
    // vezmi první číslo, PJ si HP hned poté může ručně opravit.
    $hp = dracak_vtt_prvni_cislo($nestvura['zivotaschopnost']);
    // Víc kusů téhož druhu se číslují (Goblin, Goblin 2, Goblin 3…), ať jdou
    // na mapě i v pořadí tahů rozeznat.
    $nazev = dracak_vtt_dalsi_nazev_instance($pdo, $mapaId, $nestvuraId, (string)$nestvura['nazev']);
    $ins = $pdo->prepare(
        'INSERT INTO nestvura_instance (mapa_id, nestvura_id, nazev_instance, aktualni_hp, max_hp) VALUES (?, ?, ?, ?, ?)'
    );
    $ins->execute([$mapaId, $nestvuraId, $nazev, $hp, $hp]);
    $entitaId = (int)$pdo->lastInsertId();
} else {
    $postavaId = (int)($data['postava_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM postavy WHERE id = ? AND svet_id = ?');
    $stmt->execute([$postavaId, $mapa['svet_id']]);
    $postava = $stmt->fetch();
    if (!$postava) {
        http_response_code(404);
        echo json_encode(['error' => 'Postava nenalezena v tomhle světě.']);
        exit;
    }
    if (!$isPjOrAdmin && (int)$postava['vlastnik_ucet_id'] !== (int)$user['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Tohle není tvoje postava.']);
        exit;
    }
    $entitaId = $postavaId;
    $nazev = $postava['nazev'];
}

$dbTypEntity = $typEntity === 'nestvura' ? 'nestvura_instance' : ($typEntity === 'obecny' ? 'obecny' : 'postava');
$stmt = $pdo->prepare(
    'INSERT INTO tokeny (mapa_id, typ_entity, entita_id, x, y) VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$mapaId, $dbTypEntity, $entitaId, $x, $y]);
$tokenId = (int)$pdo->lastInsertId();

// Vzhled, který se dědí, aby ho PJ nemusel nastavovat u každého kusu:
// postava si nese obrázek (a velikost, barvu) z poslední mapy, nestvůra od
// posledního tokenu téhož druhu ve světě; vlastní token má to, co zadal PJ.
// Sloupce existují až po ručním SQL, proto se zjišťují.
$funkceVzhled = dracak_vtt_tokeny_funkce();
$vzhled = [];
if ($dbTypEntity === 'obecny') {
    if ($funkceVzhled['nazev']) $vzhled['nazev'] = $obecnyNazev;
    if ($funkceVzhled['barva'] && $obecnyBarva !== null) $vzhled['barva'] = $obecnyBarva;
    if ($funkceVzhled['velikost']) $vzhled['velikost_sahu'] = $obecnaVelikost;
    if ($funkceVzhled['obrazek'] && (int)($data['obrazek_id'] ?? 0) > 0) {
        $q = $pdo->prepare('SELECT id FROM svet_obrazky WHERE id = ? AND svet_id = ?');
        $q->execute([(int)$data['obrazek_id'], (int)$mapa['svet_id']]);
        if ($q->fetchColumn()) $vzhled['obrazek_id'] = (int)$data['obrazek_id'];
    }
} elseif ($funkceVzhled['obrazek'] || $funkceVzhled['velikost'] || $funkceVzhled['barva']) {
    $sloupce = array_filter([
        'obrazek_id' => $funkceVzhled['obrazek'],
        'velikost_sahu' => $funkceVzhled['velikost'],
        'barva' => $funkceVzhled['barva'],
    ]);
    $sel = implode(', ', array_map(fn($c) => "t.`$c`", array_keys($sloupce)));
    if ($dbTypEntity === 'postava') {
        $q = $pdo->prepare("SELECT $sel FROM tokeny t WHERE t.typ_entity = 'postava' AND t.entita_id = ? AND t.id <> ? ORDER BY t.id DESC LIMIT 1");
        $q->execute([$entitaId, $tokenId]);
    } else {
        $q = $pdo->prepare(
            "SELECT $sel FROM tokeny t
             JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = 'nestvura_instance'
             JOIN mapy mm ON mm.id = ni.mapa_id
             WHERE ni.nestvura_id = ? AND mm.svet_id = ? AND t.id <> ? ORDER BY t.id DESC LIMIT 1"
        );
        $q->execute([$nestvuraId, (int)$mapa['svet_id'], $tokenId]);
    }
    $predchozi = $q->fetch(PDO::FETCH_ASSOC);
    if ($predchozi) {
        foreach ($predchozi as $sloupec => $hodnota) {
            if ($hodnota !== null) $vzhled[$sloupec] = $hodnota;
        }
    }
}
if ($vzhled) {
    $sety = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($vzhled)));
    $pdo->prepare("UPDATE tokeny SET $sety WHERE id = ?")->execute([...array_values($vzhled), $tokenId]);
}

// Kolik hexů nestvůra zabírá (HB, h1623): stejně jako poslední kus téhož druhu ve
// světě (PJ to nastavuje jednou), jinak podle třídy velikosti z bestiáře — D = 3,
// E = 5, ostatní 1 (zvířecí C má 2 hexy; to z textu bestiáře nepoznám, PJ
// si ho přepne u tokenu). Čelo musí odpovídat tvaru (tvary s čelní hranou hledí nahoru).
if ($dbTypEntity === 'nestvura_instance' && $funkceVzhled['hexy']) {
    $q = $pdo->prepare(
        "SELECT t.hexu FROM tokeny t
         JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = 'nestvura_instance'
         JOIN mapy mm ON mm.id = ni.mapa_id
         WHERE ni.nestvura_id = ? AND mm.svet_id = ? AND t.id <> ? ORDER BY t.id DESC LIMIT 1"
    );
    $q->execute([$nestvuraId, (int)$mapa['svet_id'], $tokenId]);
    $predchoziHexu = $q->fetchColumn();
    if ($predchoziHexu !== false) {
        $hexu = max(1, (int)$predchoziHexu);
    } else {
        $trida = preg_match('/^\s*(A0|A|B|C|D|E)(?![A-Za-z0-9])/u', (string)($nestvura['velikost'] ?? ''), $m) ? $m[1] : '';
        $hexu = ['D' => 3, 'E' => 5][$trida] ?? 1;
    }
    if ($hexu > 1) {
        $tvar = dracak_vtt_souboj_tvar($hexu);
        $sety = ['hexu = ?'];
        $hodnoty = [$hexu];
        if (dracak_vtt_tokeny_ma_sloupec('smer')) {
            $sety[] = 'smer = ?';
            $hodnoty[] = dracak_vtt_hex_smer_z_orientace($tvar, 0);
        }
        $hodnoty[] = $tokenId;
        $pdo->prepare('UPDATE tokeny SET ' . implode(', ', $sety) . ' WHERE id = ?')->execute($hodnoty);
    }
}

// Mlha se odhaluje jen pro postavy (viz dracak_vtt_mlha_odhal_kolem_bodu)
// — nestvůra hráčům vidění nepřidává.
if ($dbTypEntity === 'postava') {
    dracak_vtt_mlha_odhal_kolem_bodu($pdo, $mapa, (int)$postava['vlastnik_ucet_id'], (float)$x, (float)$y);
}

$eventId = dracak_vtt_log_event((int)$mapa['svet_id'], $mapaId, 'token_pridan', [
    'token_id' => $tokenId,
    'typ_entity' => $dbTypEntity,
    'entita_id' => $entitaId,
    'nazev' => $nazev,
    'x' => $x,
    'y' => $y,
], (int)$user['id']);

echo json_encode(['ok' => true, 'token_id' => $tokenId, 'udalost_id' => $eventId, 'nazev' => $nazev]);

