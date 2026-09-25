<?php
// Zkopíruj tenhle soubor jako config.php (mimo git, viz .gitignore)
// a doplň skutečné přihlašovací údaje k MySQL databázi na WEDOSu.

return [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    // Sdílený tajný token pro scripts/migrate.php — musí sedět s GitHub
    // Secret MIGRATE_TOKEN. Lokálně nepotřebuješ, jen na serveru.
    'migrate_token' => '',
];
