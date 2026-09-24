<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function dracak_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

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
