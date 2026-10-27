<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

// Chyba JavaScriptu z prohlížeče přihlášeného uživatele (posílá ji assets/js/chyby.js) do logu aplikace, který čte admin
// v admin_log.php. Hráč o chybě v prohlížeči většinou neví nebo ji nedokáže popsat. Vstup je nedůvěryhodný: zkrácený,
// zapsaný jako JSON (nejde do něj dostat nový řádek ani HTML) a počet záznamů na relaci je omezený.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Jen POST.']);
    exit;
}
$user = dracak_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Nepřihlášeno.']);
    exit;
}
const KLIENT_CHYBA_NA_RELACI = 60;
$pocet = (int)($_SESSION['klient_chyby'] ?? 0);
if ($pocet >= KLIENT_CHYBA_NA_RELACI) {
    http_response_code(429);
    echo json_encode(['error' => 'Dost chyb z jedné relace.']);
    exit;
}
$_SESSION['klient_chyby'] = $pocet + 1;
session_write_close();

$data = json_decode((string)file_get_contents('php://input', false, null, 0, 8192), true);
if (!is_array($data) || !is_string($data['zprava'] ?? null) || trim($data['zprava']) === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Chybí zpráva.']);
    exit;
}
$text = static fn($v, int $delka): string => dracak_log_oriznout(is_scalar($v) ? (string)$v : '', $delka);
$misto = dracak_log_skryj_tajne($text($data['zdroj'] ?? '', 160));
if ($misto !== '' && isset($data['radek'])) {
    $misto .= ':' . (int)$data['radek'] . (isset($data['sloupec']) ? ':' . (int)$data['sloupec'] : '');
}
$stopa = array_slice(array_filter(array_map('trim', explode("\n", $text($data['stopa'] ?? '', 1200))), static fn(string $r): bool => $r !== ''), 0, 6);
dracak_log_zapis('prohlizec', $text($data['zprava'], 400), [
    'misto' => $misto,
    'stopa' => $stopa,
    'stranka' => $text($data['stranka'] ?? '', 240),
    'ua' => $text($_SERVER['HTTP_USER_AGENT'] ?? '', 140),
    'uzivatel' => (int)$user['id'],
]);
echo json_encode(['ok' => true]);
