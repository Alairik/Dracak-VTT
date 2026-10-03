<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// pj/admin mají ke všem světům stejný přístup jako ke zbytku appky
// (identické právo jako dnešní dracak_can_edit pro pj). hráč potřebuje
// být zapsaný v svet_hraci pro konkrétní svet.
function dracak_vtt_svet_access(array $user, int $svetId): bool
{
    if (in_array($user['role'], ['admin', 'pj'], true)) {
        return true;
    }
    $stmt = dracak_db()->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
    $stmt->execute([$svetId, $user['id']]);
    return (bool)$stmt->fetchColumn();
}

function dracak_vtt_require_svet(array $user, int $svetId): array
{
    $stmt = dracak_db()->prepare('SELECT * FROM svet WHERE id = ?');
    $stmt->execute([$svetId]);
    $svet = $stmt->fetch();
    if (!$svet || !dracak_vtt_svet_access($user, $svetId)) {
        http_response_code(404);
        die('Svět nenalezen nebo k němu nemáš přístup.');
    }
    return $svet;
}

// Kdo smí hýbat konkrétním tokenem: pj/admin vždy, hráč jen svou vlastní
// postavou (tokeny.entita_id -> postavy.vlastnik_ucet_id). Instance
// nestvůry smí hýbat jen pj/admin.
function dracak_vtt_can_move_token(array $user, array $token): bool
{
    if (in_array($user['role'], ['admin', 'pj'], true)) {
        return true;
    }
    if ($token['typ_entity'] !== 'postava') {
        return false;
    }
    $stmt = dracak_db()->prepare('SELECT vlastnik_ucet_id FROM postavy WHERE id = ?');
    $stmt->execute([$token['entita_id']]);
    $vlastnik = $stmt->fetchColumn();
    return $vlastnik !== false && (int)$vlastnik === (int)$user['id'];
}

// Vynucení pořadí tahů v boji (includes/vtt_iniciativa.php, kolo_stav/
// kolo_iniciativa, content/pravidla-hrac.html h1619-h1625 "Rozšířený
// soubojový systém"). pj/admin smí jednat kdykoliv za kohokoliv (stejná
// výjimka jako dracak_vtt_can_move_token výš) — typicky nestvůry nebo
// oprava stavu. Hráč smí jednat tokenem jen když je zrovna
// kolo_stav.aktivni_token_id PRO DANOU MAPU. Když pro mapu kolo_stav
// vůbec neexistuje (boj na ní neprobíhá), vrací true — dnešní volné
// chování se neomezuje jen proto, že existuje boj JINDE/JINDE NA MAPĚ.
//
// Záměrně NEváže se na kolo_iniciativa.akce_zbyvajici (ubývání akcí) —
// to číslo dnes neznamená "kolik API volání smí token ještě udělat",
// ale kolikrát PJ ještě musí zavolat hra/api/dalsi_tah.php, než se
// přesune na dalšího (viz tam: nově aktivní token má na začátku svého
// tahu VŽDY plné akce_zbyvajici, až teprve DALŠÍ zavolání dalsi_tah.php
// mu je sníží) — vázat pohyb/použití předmětu na tohle číslo by
// vyžadovalo nový mechanismus (odečet při KAŽDÉ hráčské akci), který
// dalsi_tah.php/kolo_konec.php dnes neimplementují a zadání týhle dávky
// o něj nežádá. Enforcement je tak čistě "je tenhle token zrovna na
// řadě", ne kolik mu zbývá akcí v rámci tahu.
function dracak_vtt_je_na_tahu(array $user, int $mapaId, int $tokenId): bool
{
    if (in_array($user['role'], ['admin', 'pj'], true)) {
        return true;
    }
    $stmt = dracak_db()->prepare('SELECT aktivni_token_id FROM kolo_stav WHERE mapa_id = ?');
    $stmt->execute([$mapaId]);
    $koloStav = $stmt->fetch();
    if (!$koloStav) {
        return true;
    }
    return (int)($koloStav['aktivni_token_id'] ?? 0) === $tokenId;
}

// Orientace trojice bodů: 0 kolineární, 1/2 po/proti směru hod. ručiček.
function dracak_vtt_orientace(float $ax, float $ay, float $bx, float $by, float $cx, float $cy): int
{
    $val = ($by - $ay) * ($cx - $bx) - ($bx - $ax) * ($cy - $by);
    if (abs($val) < 1e-9) return 0;
    return $val > 0 ? 1 : 2;
}

// Bod C leží (kolineárně) na úsečce AB — pomocná funkce jen pro
// degenerované (kolineární) případy v dracak_vtt_useky_se_protinaji().
function dracak_vtt_bod_na_usecce(float $ax, float $ay, float $bx, float $by, float $cx, float $cy): bool
{
    return $cx <= max($ax, $bx) + 1e-9 && $cx >= min($ax, $bx) - 1e-9
        && $cy <= max($ay, $by) + 1e-9 && $cy >= min($ay, $by) - 1e-9;
}

// Standardní test průniku dvou úseček (orientation-based, viz běžný
// computational-geometry algoritmus) — používá se pro pohyb tokenu přes
// zeď (dracak_vtt_pohyb_prochazi_zdi) i budoucí LoS (blokuje_vystrel).
function dracak_vtt_useky_se_protinaji(
    float $p1x, float $p1y, float $q1x, float $q1y,
    float $p2x, float $p2y, float $q2x, float $q2y
): bool {
    $o1 = dracak_vtt_orientace($p1x, $p1y, $q1x, $q1y, $p2x, $p2y);
    $o2 = dracak_vtt_orientace($p1x, $p1y, $q1x, $q1y, $q2x, $q2y);
    $o3 = dracak_vtt_orientace($p2x, $p2y, $q2x, $q2y, $p1x, $p1y);
    $o4 = dracak_vtt_orientace($p2x, $p2y, $q2x, $q2y, $q1x, $q1y);

    if ($o1 !== $o2 && $o3 !== $o4) {
        return true;
    }
    if ($o1 === 0 && dracak_vtt_bod_na_usecce($p1x, $p1y, $q1x, $q1y, $p2x, $p2y)) return true;
    if ($o2 === 0 && dracak_vtt_bod_na_usecce($p1x, $p1y, $q1x, $q1y, $q2x, $q2y)) return true;
    if ($o3 === 0 && dracak_vtt_bod_na_usecce($p2x, $p2y, $q2x, $q2y, $p1x, $p1y)) return true;
    if ($o4 === 0 && dracak_vtt_bod_na_usecce($p2x, $p2y, $q2x, $q2y, $q1x, $q1y)) return true;
    return false;
}

// Blokuje některá zeď (blokuje_pohyb=1) na týhle mapě cestu z bodu A do
// bodu B? Volá se jen pro roli 'hrac' (viz hra/api/token_presun.php) —
// PJ/admin token na/přes zeď položit smí (postava odhozená do zdi,
// vylézání po zdi apod. — zeď blokuje běžný pohyb hráče, ne PJovo
// úmyslné umístění tokenu).
function dracak_vtt_pohyb_prochazi_zdi(PDO $pdo, int $mapaId, float $x1, float $y1, float $x2, float $y2): bool
{
    $stmt = $pdo->prepare('SELECT x1, y1, x2, y2 FROM zdi WHERE mapa_id = ? AND blokuje_pohyb = 1');
    $stmt->execute([$mapaId]);
    foreach ($stmt->fetchAll() as $z) {
        if (dracak_vtt_useky_se_protinaji(
            $x1, $y1, $x2, $y2,
            (float)$z['x1'], (float)$z['y1'], (float)$z['x2'], (float)$z['y2']
        )) {
            return true;
        }
    }
    return false;
}

// Px na 1 sáh pro danou mapu (řádek z `mapy`, ne jen ID — ať volající
// nemusí dělat druhý dotaz, když mapu už má načtenou). Čtverec:
// grid_velikost_px přímo. Hex: grid_velikost_px je "size" (circumradius,
// střed->vrchol, viz hra/mapa.php renderGrid()), skutečná velikost
// sáhu (h1621: "jeden hex vždy odpovídá jednomu sáhu") je vzdálenost
// STŘED-STŘED sousedních hexů = √3×circumradius — stejná oprava jako
// pxNaSah() v hra/mapa.php. Vrací null bez nastaveného gridu (bez
// gridu není měřítko px->sáh).
function dracak_vtt_px_na_sah(array $mapa): ?float
{
    $gridPx = (int)($mapa['grid_velikost_px'] ?? 0);
    if ($gridPx <= 0) {
        return null;
    }
    return ($mapa['grid_typ'] ?? 'ctverec') === 'hex' ? sqrt(3) * $gridPx : (float)$gridPx;
}

// LoS (blokuje_vystrel) mezi dvěma body na týhle mapě — stejný princip
// jako dracak_vtt_pohyb_prochazi_zdi() výš, jen jiný sloupec zdi a bez
// výjimky pro PJ/admin: výhled buď je, nebo není blokovaný fyzickým
// překážkou, nezávisle na tom, kdo se ptá (na rozdíl od pohybu, kde PJ
// token na zeď/přes ni smí položit záměrně). Použito z
// includes/vtt_predmety.php (kontrola dosahu kouzel/zbraní) —
// content/pravidla-hrac.html h1621 řeší LoS jako boolean průsečík
// paprsku se zdí, ne plný viditelnostní polygon (viz
// docs/vtt-datovy-model-navrh-v1.md, "Rozhodnutí, která už platí").
function dracak_vtt_los_blokovana_zdi(PDO $pdo, int $mapaId, float $x1, float $y1, float $x2, float $y2): bool
{
    $stmt = $pdo->prepare('SELECT x1, y1, x2, y2 FROM zdi WHERE mapa_id = ? AND blokuje_vystrel = 1');
    $stmt->execute([$mapaId]);
    foreach ($stmt->fetchAll() as $z) {
        if (dracak_vtt_useky_se_protinaji(
            $x1, $y1, $x2, $y2,
            (float)$z['x1'], (float)$z['y1'], (float)$z['x2'], (float)$z['y2']
        )) {
            return true;
        }
    }
    return false;
}

function dracak_vtt_entity_row(string $typEntity, int $entitaId): ?array
{
    $table = $typEntity === 'nestvura_instance' ? 'nestvura_instance' : 'postavy';
    $stmt = dracak_db()->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$entitaId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// pj/admin upraví život komukoliv, hráč jen svojí vlastní postavě —
// instance nestvůry je vždy v gesci PJ.
function dracak_vtt_can_edit_hp(array $user, string $typEntity, array $entity): bool
{
    if (in_array($user['role'], ['admin', 'pj'], true)) {
        return true;
    }
    return $typEntity === 'postava' && (int)($entity['vlastnik_ucet_id'] ?? 0) === (int)$user['id'];
}

// Vytáhne první číslo z volného textu jako "20", "20-25", "viz text" —
// zdrojová data bestiáře jsou často nečistá (viz
// docs/kontrolni-seznam-neuplnych-mist.md), radši 0 než pád na chybě.
function dracak_vtt_prvni_cislo(?string $text): int
{
    if ($text !== null && preg_match('/(\d+)/', $text, $m)) {
        return (int)$m[1];
    }
    return 0;
}

// Vytáhne první ČÍSLO SE ZNAMÉNKEM z volného textu jako "(+2 + 6) = 8"
// nebo "(−1 + 7) = 6" — nestvury.oc/uc jsou zápis vzorce, ne čisté číslo
// (viz database/drd-db-full-v1.sql), první člen bývá bonus za
// manévrovací schopnost/rychlost (blízký ekvivalent Obratnosti u
// postav, byť to formálně není totéž — bestiář nemá vlastní atribut
// Obratnost). Zdroj používá unicode mínus (−), ne ASCII pomlčku, proto
// normalizace. Vrací null, když text žádné číslo neobsahuje (např.
// "Obr + kvalita zbroje" u humanoidních šablon) — tam se hodnota musí
// dopočítat jinak, radši null než tiše hádané 0.
function dracak_vtt_prvni_cislo_se_znamenkem(?string $text): ?int
{
    if ($text === null) {
        return null;
    }
    $normalizovano = str_replace(["\u{2212}", '–', '—'], '-', $text);
    if (preg_match('/([+-]?\d+)/', $normalizovano, $m)) {
        return (int)$m[1];
    }
    return null;
}

// Zápis události do logu — vždy stejný tvar, ať se na to nezapomíná
// u jednotlivých endpointů (viz docs/vtt-datovy-model-navrh-v1.md).
function dracak_vtt_log_event(int $svetId, ?int $mapaId, string $typ, array $payload, int $ucetId): int
{
    $stmt = dracak_db()->prepare(
        'INSERT INTO svet_udalosti (svet_id, mapa_id, typ, payload, ucet_id) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$svetId, $mapaId, $typ, json_encode($payload, JSON_UNESCAPED_UNICODE), $ucetId]);
    return (int)dracak_db()->lastInsertId();
}

// Parsování jednoduchého zápisu kostek "2k6+2" / "1k20-1" / "k6".
// Vrací null při neplatném vstupu — endpoint pak vrátí chybu, nikdy
// nehádá "asi myslel(a) tohle".
function dracak_vtt_parsuj_kostky(string $notace): ?array
{
    $notace = strtolower(trim($notace));
    if (!preg_match('/^(\d*)k(\d+)([+-]\d+)?$/', $notace, $m)) {
        return null;
    }
    $pocet = $m[1] === '' ? 1 : (int)$m[1];
    $typ = (int)$m[2];
    $bonus = isset($m[3]) ? (int)$m[3] : 0;
    if ($pocet < 1 || $pocet > 100 || $typ < 2 || $typ > 100) {
        return null;
    }
    return ['pocet' => $pocet, 'typ' => $typ, 'bonus' => $bonus];
}

function dracak_vtt_hod_kostkou(int $pocet, int $typ, int $bonus): array
{
    $hody = [];
    for ($i = 0; $i < $pocet; $i++) {
        $hody[] = random_int(1, $typ);
    }
    return ['hody' => $hody, 'bonus' => $bonus, 'celkem' => array_sum($hody) + $bonus];
}

// Společná hlavička/patička pro hra/*.php stránky — ať se neopakuje na
// třech místech to samé <head> a horní lišta co dashboard.php.
function dracak_vtt_page_start(string $title, array $user): void
{
    ?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — <?= htmlspecialchars($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/organic.css?v=<?= filemtime(__DIR__ . '/../assets/css/organic.css') ?>">
</head>
<body>
<div class="dash-shell">
  <div class="dash-header">
    <div>
      <a class="dash-brand" href="../dashboard.php" style="text-decoration:none;color:inherit;">🐉 Dračák VTT</a>
      <div class="dash-user">Přihlášen: <strong><?= htmlspecialchars($user['jmeno']) ?></strong><span class="role-badge"><?= htmlspecialchars($user['role']) ?></span></div>
    </div>
    <a class="btn btn-secondary" href="../dashboard.php">← Dashboard</a>
  </div>
    <?php
}

function dracak_vtt_page_end(): void
{
    ?>
</div>
</body>
</html>
    <?php
}

// Vypíše vendorovanou Lucide ikonu (assets/icons/<name>.svg) inline, ne
// přes <img src>, protože ikony jsou stroke="currentColor" a barvu tak
// dědí z CSS obalujícího tlačítka (hover/active stav zdarma).
function dracak_icon(string $name, int $size = 20): void
{
    static $cache = [];
    if (!array_key_exists($name, $cache)) {
        $path = __DIR__ . '/../assets/icons/' . basename($name) . '.svg';
        $cache[$name] = is_file($path) ? (string)file_get_contents($path) : '';
    }
    if ($cache[$name] === '') {
        return;
    }
    $svg = preg_replace('/(width|height)="24"/', '$1="' . $size . '"', $cache[$name]);
    echo $svg;
}

