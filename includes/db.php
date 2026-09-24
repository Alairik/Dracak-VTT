<?php
declare(strict_types=1);

function dracak_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $configPath = __DIR__ . '/../config.php';
    if (!file_exists($configPath)) {
        http_response_code(500);
        die('Chybí config.php — zkopíruj config.example.php na config.php a doplň přístupy k databázi.');
    }
    $config = require $configPath;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
