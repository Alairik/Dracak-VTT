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

function dracak_login(string $email, string $password): bool
{
    $stmt = dracak_db()->prepare('SELECT id, heslo_hash FROM ucty WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['heslo_hash'])) {
        return false;
    }
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
