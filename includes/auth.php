<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function dracak_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Cookie nesmí číst JS (httponly), nesmí se poslat z cizí stránky
        // (SameSite=Lax — obrana proti CSRF, viz dracak_csrf_guard níž) a
        // po HTTPS jen přes HTTPS. strict_mode: server nepřijme session id,
        // které sám nevystavil (session fixation).
        $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

// Obrana proti CSRF bez tokenů: každý požadavek, který něco mění (POST a
// jiné než GET/HEAD/OPTIONS), musí přijít z téhož webu. Prohlížeč u POST
// vždycky pošle hlavičku Origin (nebo aspoň Sec-Fetch-Site / Referer) a
// stránka z cizího webu ji podvrhnout neumí. Chybí-li všechny tři (curl,
// velmi staré prohlížeče), požadavek projde — CSRF je útok přes prohlížeč
// přihlášené oběti, ten hlavičku vždy pošle. Běží při každém načtení
// auth.php, takže kryje všechny stránky i hra/api/*.php bez zásahu do JS.
function dracak_csrf_guard(): void
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (PHP_SAPI === 'cli' || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $hostHeader = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
    $host = strtolower((string)preg_replace('/:\d+$/', '', trim(explode(',', $hostHeader)[0])));
    if ($host === '') {
        return;
    }

    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $fetchSite = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $ok = null;
    if ($origin !== '') {
        $h = parse_url($origin, PHP_URL_HOST);
        $ok = is_string($h) && strtolower($h) === $host;
    } elseif ($fetchSite !== '') {
        $ok = in_array($fetchSite, ['same-origin', 'none'], true);
    } elseif ($referer !== '') {
        $h = parse_url($referer, PHP_URL_HOST);
        $ok = is_string($h) && strtolower($h) === $host;
    }
    if ($ok === false) {
        error_log('[dracak-vtt] CSRF guard odmítl ' . $method . ' ' . ($_SERVER['REQUEST_URI'] ?? '')
            . ' (Origin=' . $origin . ', Sec-Fetch-Site=' . $fetchSite . ', Host=' . $host . ')');
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['error' => 'Požadavek z cizího webu byl odmítnut.'], JSON_UNESCAPED_UNICODE));
    }
}
dracak_csrf_guard();

function dracak_current_user(): ?array
{
    dracak_session_start();
    if (empty($_SESSION['ucet_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $stmt = dracak_db()->prepare('SELECT id, email, jmeno, role FROM ucty WHERE id = ?');
    $stmt->execute([$_SESSION['ucet_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        dracak_logout();
        return null;
    }
    $cached = $user;
    return $user;
}

// Omezení pokusů o přihlášení (hádání hesel). Bez databázové tabulky (web účet nesmí vytvářet tabulky): počítadla jsou malé soubory
// ve složce logs/login/ (zavřená .htaccess). Dva klíče: IP adresa (hodně pokusů z jedné adresy) a IP + e-mail (hádání jednoho účtu).
// Blokuje se jen ta adresa, která chybuje, takže cizí člověk nemůže zamknout cizí účet. Po úspěšném přihlášení se počítadla smažou.
const DRACAK_LOGIN_OKNO_S = 900;          // pamatují se selhání za posledních 15 minut
const DRACAK_LOGIN_LIMIT_EMAIL = 6;       // selhání na jeden e-mail z jedné adresy
const DRACAK_LOGIN_LIMIT_IP = 30;         // selhání z jedné adresy celkem

function dracak_login_soubor(string $klic): string
{
    return dirname(__DIR__) . '/logs/login/' . substr(hash('sha256', $klic), 0, 24) . '.json';
}

// Časy selhání v okně, nebo [] (bez souboru, bez zápisu do složky, poškozený soubor).
function dracak_login_cteni(string $soubor, int $ted): array
{
    $json = @file_get_contents($soubor);
    $casy = $json !== false ? json_decode($json, true) : null;
    return is_array($casy) ? array_values(array_filter($casy, static fn($t): bool => is_int($t) && $t > $ted - DRACAK_LOGIN_OKNO_S)) : [];
}

// Kolik vteřin ještě musí čekat; 0 = smí zkusit.
function dracak_login_cekani(string $email, ?int $ted = null): int
{
    $ted ??= time();
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $cekani = 0;
    foreach ([[$ip, DRACAK_LOGIN_LIMIT_IP], [$ip . '|' . strtolower($email), DRACAK_LOGIN_LIMIT_EMAIL]] as [$klic, $limit]) {
        $casy = dracak_login_cteni(dracak_login_soubor($klic), $ted);
        if (count($casy) >= $limit) {
            // uvolní se, až vyprší nejstarší selhání, které drží počet nad limitem
            sort($casy);
            $cekani = max($cekani, $casy[count($casy) - $limit] + DRACAK_LOGIN_OKNO_S - $ted);
        }
    }
    return max(0, $cekani);
}

function dracak_login_selhani(string $email, ?int $ted = null): void
{
    $ted ??= time();
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!dracak_log_priprav_slozku() || (!is_dir(dirname(__DIR__) . '/logs/login') && !@mkdir(dirname(__DIR__) . '/logs/login', 0750))) {
        return;   // bez možnosti zapisovat se omezení tiše vypne, přihlášení kvůli tomu nesmí přestat fungovat
    }
    foreach ([$ip, $ip . '|' . strtolower($email)] as $klic) {
        $soubor = dracak_login_soubor($klic);
        $f = @fopen($soubor, 'c+');
        if (!$f) {
            continue;
        }
        if (flock($f, LOCK_EX)) {
            $casy = dracak_login_cteni($soubor, $ted);
            $casy[] = $ted;
            ftruncate($f, 0);
            rewind($f);
            fwrite($f, (string)json_encode(array_slice($casy, -100)));
            flock($f, LOCK_UN);
        }
        fclose($f);
    }
    // Úklid starých počítadel (občas, ať složka neroste).
    if (random_int(1, 50) === 1) {
        foreach (glob(dirname(__DIR__) . '/logs/login/*.json') ?: [] as $stary) {
            if (@filemtime($stary) < time() - DRACAK_LOGIN_OKNO_S) {   // skutečný čas souboru, ne $ted (ten se v testech vymýšlí)
                @unlink($stary);
            }
        }
    }
}

function dracak_login_vymaz(string $email): void
{
    @unlink(dracak_login_soubor((string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower($email)));
}

function dracak_login(string $email, string $password): bool
{
    // Zablokovaná adresa se k ověřování hesla vůbec nedostane (stejná odpověď jako při špatném hesle by schovala důvod; hláška říká, že se má počkat).
    if (dracak_login_cekani($email) > 0) {
        return false;
    }
    $stmt = dracak_db()->prepare('SELECT id, heslo_hash FROM ucty WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    // password_verify i pro neexistující e-mail, ať doba odpovědi neprozradí, jestli účet existuje.
    $hash = $row ? (string)$row['heslo_hash'] : '$2y$10$.vGA1O9wmRjrwAVXD98HNOgsNpDczlqm3Jq7KnEd1rVAGv3Fykk1a';
    if (!password_verify($password, $hash) || !$row) {
        dracak_login_selhani($email);
        return false;
    }
    dracak_login_vymaz($email);
    dracak_session_start();
    session_regenerate_id(true);
    $_SESSION['ucet_id'] = $row['id'];
    return true;
}

function dracak_logout(): void
{
    dracak_session_start();
    $_SESSION = [];
    session_destroy();
}

// Přesměruje na login, pokud uživatel není přihlášený. Vrací data uživatele.
function dracak_require_login(): array
{
    $user = dracak_current_user();
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function dracak_require_role(string ...$allowedRoles): array
{
    $user = dracak_require_login();
    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        die('Nemáš oprávnění vidět tuhle stránku.');
    }
    return $user;
}

// Tabulky (klíče z ENTITY_CONFIG), které smí daný hráč sám editovat.
function dracak_editable_tables(array $user): array
{
    if (in_array($user['role'], ['admin', 'pj'], true)) {
        return array_keys(require __DIR__ . '/entities.php');
    }
    $stmt = dracak_db()->prepare('SELECT tabulka FROM ucet_opravneni WHERE ucet_id = ?');
    $stmt->execute([$user['id']]);
    return array_column($stmt->fetchAll(), 'tabulka');
}

function dracak_can_edit(array $user, string $table): bool
{
    if (in_array($user['role'], ['admin', 'pj'], true)) {
        return true;
    }
    return in_array($table, dracak_editable_tables($user), true);
}

// Row-level vlastnictví (viz docs/zadani-redesign-ui.md, rozhodnutí #5 a
// migrace database/migrations/0009_row_ownership.sql). admin/pj beze
// změny edituje vše; hráč smí i s table-level oprávněním upravovat jen
// svoje vlastní záznamy (created_by === jeho id). Tabulky bez row_owned
// (číselníky, bestiář...) se řídí jen dracak_can_edit() jako dřív.
function dracak_can_edit_row(array $user, string $table, array $config, ?array $row): bool
{
    if (!dracak_can_edit($user, $table)) {
        return false;
    }
    if (empty($config['row_owned']) || in_array($user['role'], ['admin', 'pj'], true)) {
        return true;
    }
    // Nový záznam (row === null): hráč ho zakládá, vlastníkem bude on sám.
    if ($row === null) {
        return true;
    }
    return isset($row['created_by']) && (int)$row['created_by'] === (int)$user['id'];
}
