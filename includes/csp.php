<?php
declare(strict_types=1);

// Content-Security-Policy: jediná plošná obrana proti vloženému skriptu (XSS), kdyby někde unikl neescapovaný text. Skript smí běžet jen ze
// souborů naší domény a z inline bloků s jednorázovým `nonce` (dracak_csp_nonce() v `<script nonce="…">`); inline atributy typu onclick se nepouští,
// proto jsou nahrazené obsluhou v assets/js/obecne.js.
//
// Režim (DRACAK_CSP_REZIM): 'report' = jen hlásí porušení do chybového logu (admin_log.php, druh „Porušení CSP“) a nic neblokuje — tak se nasazuje, dokud se
// na ostrém provozu neukáže, že nic nehlásí; 'vynutit' = blokuje; 'vypnout' = bez hlavičky. Přepnutí je změna téhle jedné konstanty.
const DRACAK_CSP_REZIM = 'report';

function dracak_csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
}

// Cesta k aplikaci od kořene domény ('' nebo '/vtt'): aplikace nemusí běžet v kořeni (na Wedosu je v podsložce), adresa pro hlášení musí být absolutní.
function dracak_csp_zaklad(): string
{
    $koren = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $app = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $cesta = $koren !== '' && str_starts_with($app, $koren) ? substr($app, strlen($koren)) : '';
    return preg_match('#^(/[A-Za-z0-9._~-]+)*$#', $cesta) ? $cesta : '';
}

function dracak_csp_hodnota(): string
{
    return implode('; ', [
        "default-src 'self'",
        // 'wasm-unsafe-eval': 3D kostky (Babylon + fyzika ve WebAssembly) bez něj nespustí fyziku; neumožňuje eval textu.
        "script-src 'self' 'nonce-" . dracak_csp_nonce() . "' 'wasm-unsafe-eval'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: blob:",
        "media-src 'self' blob:",
        "connect-src 'self' blob: data:",
        "frame-src 'self'",
        "worker-src 'self' blob:",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        'report-uri ' . dracak_csp_zaklad() . '/csp-report.php',
    ]);
}

function dracak_csp_hlavicka(): void
{
    if (DRACAK_CSP_REZIM === 'vypnout' || PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header((DRACAK_CSP_REZIM === 'vynutit' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only') . ': ' . dracak_csp_hodnota());
}
