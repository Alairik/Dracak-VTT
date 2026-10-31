<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/log.php';

// Příjem hlášení o porušení Content-Security-Policy (report-uri). Volá ho prohlížeč bez přihlášení a bez cookies, proto: jen POST, tělo nejvýš 4 kB,
// jen známá pole, zápis do chybového logu s omezením počtu (společný strop 60 hlášení za minutu pro celý web, ať to nikdo nemůže zaplavit log).
http_response_code(204);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
$telo = (string)file_get_contents('php://input', false, null, 0, 4096);
$data = json_decode($telo, true);
$r = is_array($data) && is_array($data['csp-report'] ?? null) ? $data['csp-report'] : null;
if ($r === null) {
    exit;
}
$minuta = intdiv(time(), 60);
$pocitadlo = __DIR__ . '/logs/csp-' . $minuta . '.cnt';
if (dracak_log_priprav_slozku()) {
    $n = (int)@file_get_contents($pocitadlo);
    if ($n >= 60) {
        exit;
    }
    @file_put_contents($pocitadlo, (string)($n + 1), LOCK_EX);
    foreach (glob(__DIR__ . '/logs/csp-*.cnt') ?: [] as $stary) {
        if (!str_ends_with($stary, '-' . $minuta . '.cnt') && @filemtime($stary) < time() - 300) {
            @unlink($stary);
        }
    }
}
$pole = static fn(string $k): string => is_scalar($r[$k] ?? null) ? (string)$r[$k] : '';
$cil = $pole('blocked-uri') ?: $pole('effective-directive');
dracak_log_zapis('csp', $pole('violated-directive') . ' → ' . $cil, [
    'stranka' => $pole('document-uri'),
    'misto' => trim($pole('source-file') . ($pole('line-number') !== '' ? ':' . $pole('line-number') : '')),
]);
