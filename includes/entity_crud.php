<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Vrátí definici pole s výchozími hodnotami doplněnými.
function dracak_normalize_field(array $f): array
{
    return $f + ['required' => false, 'quick' => false, 'options' => [], 'ref_table' => null, 'ref_label' => null];
}

// Pro číselníky bez ručně psaného 'fields' pole: odvodí sloupce z DESCRIBE.
// Název tabulky musí být klíč z ENTITY_CONFIG (whitelist), nikdy přímo z requestu.
function dracak_auto_fields(string $table): array
{
    $stmt = dracak_db()->query('DESCRIBE `' . $table . '`');
    $fields = [];
    foreach ($stmt->fetchAll() as $col) {
        if ($col['Extra'] === 'auto_increment') {
            continue;
        }
        $type = str_contains($col['Type'], 'text') ? 'textarea'
            : (preg_match('/^(int|tinyint|smallint|decimal)/', $col['Type']) ? 'number' : 'text');
        $fields[] = dracak_normalize_field([
            'name' => $col['Field'],
            'label' => $col['Field'],
            'type' => $type,
            'required' => $col['Null'] === 'NO' && $col['Key'] !== 'PRI',
            'quick' => true,
        ]);
    }
    return $fields;
}

function dracak_entity_fields(string $table, array $config): array
{
    if (!empty($config['fields'])) {
        return array_map('dracak_normalize_field', $config['fields']);
    }
    return dracak_auto_fields($table);
}

function dracak_entity_list(string $table, array $config): array
{
    $orderBy = $config['order_by'] ?? 'id';
    return dracak_db()->query("SELECT * FROM `$table` ORDER BY `$orderBy`")->fetchAll();
}

function dracak_entity_get(string $table, int $id): ?array
{
    $stmt = dracak_db()->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function dracak_fk_options(string $refTable, string $refLabel): array
{
    return dracak_db()->query("SELECT id, `$refLabel` AS label FROM `$refTable` ORDER BY `$refLabel`")->fetchAll();
}

// Uloží entitu (insert když $id je null, jinak update). Vrací nové/stávající id.
// $input = $_POST. Sloupce se berou jen z definice polí (whitelist) - nikdy
// se neinterpoluje klíč z requestu přímo do SQL.
function dracak_entity_save(string $table, array $fields, ?int $id, array $input): int
{
    $data = [];
    foreach ($fields as $f) {
        $name = $f['name'];
        if ($f['type'] === 'checkbox') {
            $data[$name] = isset($input[$name]) ? 1 : 0;
            continue;
        }
        $value = trim((string)($input[$name] ?? ''));
        if ($value === '' && $f['type'] === 'select_fk') {
            $data[$name] = null;
            continue;
        }
        if ($value === '' && !$f['required']) {
            $data[$name] = null;
            continue;
        }
        $data[$name] = $value;
    }

    $pdo = dracak_db();
    if ($id === null) {
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = "INSERT INTO `$table` (" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ')'
            . ' VALUES (' . implode(',', $placeholders) . ')';
        $pdo->prepare($sql)->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    }

    $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
    $sql = "UPDATE `$table` SET $set WHERE id = ?";
    $pdo->prepare($sql)->execute([...array_values($data), $id]);
    return $id;
}

function dracak_entity_delete(string $table, int $id): void
{
    $stmt = dracak_db()->prepare("DELETE FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
}
