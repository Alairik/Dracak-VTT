<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/vtt.php';

// Hledání v katalogu pravidel podle názvu — pro výběr položky do inventáře
// postavy v Osobním deníku (hra/denik.php). Katalog předmětů, lektvarů a
// kouzel je obsah pravidel (viditelný všem přihlášeným), ne tajná data.
// GET: typ = predmet|lektvar|kouzlo, q = hledaný text (min. 2 znaky).
header('Content-Type: application/json; charset=utf-8');
dracak_require_login();

$typ = (string)($_GET['typ'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['vysledky' => []]);
    exit;
}
$like = '%' . addcslashes($q, '%_\\') . '%';
$pdo = dracak_db();

if ($typ === 'predmet') {
    $stmt = $pdo->prepare('SELECT id, nazev, typ, vaha FROM predmety WHERE nazev LIKE ? ORDER BY nazev LIMIT 15');
    $stmt->execute([$like]);
    $vysledky = array_map(fn($r) => [
        'id' => (int)$r['id'], 'nazev' => $r['nazev'],
        'info' => $r['typ'] . ($r['vaha'] !== null ? ' · ' . rtrim(rtrim(number_format((float)$r['vaha'], 2, ',', ''), '0'), ',') . ' mn' : ' · bez váhy'),
    ], $stmt->fetchAll());
} elseif ($typ === 'lektvar') {
    $stmt = $pdo->prepare('SELECT id, nazev FROM lektvary WHERE nazev LIKE ? ORDER BY nazev LIMIT 15');
    $stmt->execute([$like]);
    $vysledky = array_map(fn($r) => ['id' => (int)$r['id'], 'nazev' => $r['nazev'], 'info' => 'lektvar'], $stmt->fetchAll());
} elseif ($typ === 'kouzlo') {
    $stmt = $pdo->prepare(
        'SELECT k.id, k.nazev, k.uroven_kouzla, sk.nazev AS seznam FROM kouzla k
         LEFT JOIN seznamy_kouzel sk ON sk.id = k.seznam_kouzel_id
         WHERE k.nazev LIKE ? ORDER BY k.nazev LIMIT 15'
    );
    $stmt->execute([$like]);
    $vysledky = array_map(fn($r) => [
        'id' => (int)$r['id'], 'nazev' => $r['nazev'],
        'info' => trim(($r['uroven_kouzla'] !== null ? $r['uroven_kouzla'] . '. úroveň' : '') . ($r['seznam'] ? ' · ' . $r['seznam'] : ''), ' ·'),
    ], $stmt->fetchAll());
} else {
    http_response_code(422);
    echo json_encode(['error' => 'Neplatný typ.']);
    exit;
}
echo json_encode(['vysledky' => $vysledky], JSON_UNESCAPED_UNICODE);
