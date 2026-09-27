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

// Společná hlavička/patička pro vtt/*.php stránky — ať se neopakuje na
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
