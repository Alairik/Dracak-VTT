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

// $filters = ['search' => string, 'where' => [sql fragmenty s ? placeholdery], 'params' => [...]]
// Volající (editor.php) sestaví $filters jen z hodnot ověřených proti
// $config['fields']/definici filtru — nikdy přímo z $_GET do SQL.
function dracak_entity_list(string $table, array $config, array $filters = []): array
{
    $orderBy = $config['order_by'] ?? 'id';
    $sql = "SELECT * FROM `$table`";
    $where = $filters['where'] ?? [];
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY `$orderBy`";
    $stmt = dracak_db()->prepare($sql);
    $stmt->execute($filters['params'] ?? []);
    return $stmt->fetchAll();
}

// Distinct hodnoty sloupce pro select-filtr (jen když pole nemá pevné 'options').
function dracak_distinct_values(string $table, string $column): array
{
    $stmt = dracak_db()->query("SELECT DISTINCT `$column` AS v FROM `$table` WHERE `$column` IS NOT NULL AND `$column` != '' ORDER BY `$column`");
    return array_column($stmt->fetchAll(), 'v');
}

function dracak_entity_get(string $table, int $id): ?array
{
    $stmt = dracak_db()->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Napojení pravidel na DB (content/edit-links.json, content/content-links.json)
// nesmí ukládat natvrdo číselné id — auto_increment se mezi prostředími (moje
// testovací DB vs. produkce) liší podle toho, co se kde kdy vložilo/smazalo,
// takže stejné id v jiném prostředí klidně míří na úplně jiný řádek, nebo na
// žádný. Proto tyhle soubory ukládají {"table":"rasy","nazev":"Hobit"} — a
// teprve tady, při každém requestu, se název přeloží na aktuální id v tomhle
// konkrétním prostředí. Když název v tabulce vůbec není (řádek smazán, ještě
// nevytvořen), link se tiše přeskočí — nikdy neukazovat tužku/kartu na
// neexistující řádek.
//
// U kouzel a zvláštních schopností `nazev` sám o sobě nestačí — stejný
// název používá víc povolání (desítky kouzel, dvě dovednosti: Stopování,
// Tichý pohyb). Link proto u nich smí nést i "povolani" (název povolání,
// nebo chybí/null pro řádek bez napojení na žádné povolání) — použije se
// JEN když je nazev v tabulce nejednoznačný, jinak se ignoruje.
function dracak_build_nazev_index(string $table): array
{
    if ($table === 'kouzla') {
        $sql = "SELECT k.id, k.nazev, p.nazev AS povolani FROM kouzla k
                LEFT JOIN seznamy_kouzel sk ON sk.id = k.seznam_kouzel_id
                LEFT JOIN povolani p ON p.id = sk.povolani_id
                WHERE k.nazev != ''";
    } elseif ($table === 'zvlastni_schopnosti') {
        $sql = "SELECT z.id, z.nazev,
                (SELECT p.nazev FROM schopnost_povolani sp JOIN povolani p ON p.id = sp.povolani_id
                 WHERE sp.schopnost_id = z.id ORDER BY p.id LIMIT 1) AS povolani
                FROM zvlastni_schopnosti z";
    } else {
        $sql = "SELECT id, nazev, NULL AS povolani FROM `$table`";
    }
    $index = [];
    foreach (dracak_db()->query($sql) as $r) {
        $index[$r['nazev']][] = ['id' => (int)$r['id'], 'povolani' => $r['povolani']];
    }
    return $index;
}

function dracak_resolve_content_link(array $link, array &$nazevIndexCache): ?int
{
    if (isset($link['id'])) {
        return (int)$link['id'];
    }
    if (!isset($link['nazev'])) {
        return null;
    }
    $table = $link['table'];
    if (!isset($nazevIndexCache[$table])) {
        $nazevIndexCache[$table] = dracak_build_nazev_index($table);
    }
    $candidates = $nazevIndexCache[$table][$link['nazev']] ?? [];
    if (count($candidates) === 1) {
        return $candidates[0]['id'];
    }
    if (count($candidates) > 1) {
        $wantPovolani = $link['povolani'] ?? null;
        foreach ($candidates as $c) {
            if ($c['povolani'] === $wantPovolani) {
                return $c['id'];
            }
        }
    }
    return null;
}

function dracak_fk_options(string $refTable, string $refLabel): array
{
    return dracak_db()->query("SELECT id, `$refLabel` AS label FROM `$refTable` ORDER BY `$refLabel`")->fetchAll();
}

// Uloží entitu (insert když $id je null, jinak update). Vrací nové/stávající id.
// $input = $_POST. Sloupce se berou jen z definice polí (whitelist) - nikdy
// se neinterpoluje klíč z requestu přímo do SQL.
// $config a $currentUserId volitelné: když $config['row_owned'] a jde o
// insert, nastaví se created_by na aktuálního uživatele (viz auth.php
// dracak_can_edit_row() — hráč pak smí upravovat jen svoje vlastní řádky).
function dracak_entity_save(string $table, array $fields, ?int $id, array $input, ?array $config = null, ?int $currentUserId = null): int
{
    $data = [];
    foreach ($fields as $f) {
        // Core kostky sloupce (pocet_kostek/typ_kostky/pevny_bonus) se v
        // UI needitují jednotlivě — nahrazuje je syntetické pole
        // 'kostky_zapis' zpracované zvlášť níž, viz dracak_kostky_zapis_parse().
        if (!empty($f['kostky_core'])) {
            continue;
        }
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

    if (array_filter($fields, fn($f) => !empty($f['kostky_core']))) {
        $data = array_merge($data, dracak_kostky_zapis_parse((string)($input['kostky_zapis'] ?? '')));
    }

    $pdo = dracak_db();
    if ($id === null) {
        if (!empty($config['row_owned']) && $currentUserId !== null) {
            $data['created_by'] = $currentUserId;
        }
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = "INSERT INTO `$table` (" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ')'
            . ' VALUES (' . implode(',', $placeholders) . ')';
        $pdo->prepare($sql)->execute(array_values($data));
        $newId = (int)$pdo->lastInsertId();
        dracak_relations_save($table, $newId, $config['relations'] ?? [], $input);
        if (!empty($config['vysledky_testu'])) dracak_schopnost_vysledky_save($newId, $input);
        return $newId;
    }

    $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
    $sql = "UPDATE `$table` SET $set WHERE id = ?";
    $pdo->prepare($sql)->execute([...array_values($data), $id]);
    dracak_relations_save($table, $id, $config['relations'] ?? [], $input);
    if (!empty($config['vysledky_testu'])) dracak_schopnost_vysledky_save($id, $input);
    return $id;
}

// Efekty dovednosti podle 4 stupňů výsledku testu (viz migrace 0014/0015)
// — načte pro edit formulář, seskupené podle vysledek_testu_id a kontextu.
function dracak_schopnost_vysledky_load(int $schopnostId): array
{
    $stmt = dracak_db()->prepare(
        'SELECT vt.id AS vysledek_testu_id, vt.kod, vt.nazev, vt.poradi, sv.kontext, sv.efekt_text
         FROM vysledky_testu vt
         LEFT JOIN schopnost_vysledky sv ON sv.vysledek_testu_id = vt.id AND sv.schopnost_id = ?
         ORDER BY vt.poradi'
    );
    $stmt->execute([$schopnostId]);
    $tiers = [];
    foreach ($stmt->fetchAll() as $row) {
        $tid = (int)$row['vysledek_testu_id'];
        if (!isset($tiers[$tid])) {
            $tiers[$tid] = ['id' => $tid, 'kod' => $row['kod'], 'nazev' => $row['nazev'], 'obecny' => '', 'boj' => '', 'mimo_boj' => ''];
        }
        if ($row['kontext'] !== null) {
            $tiers[$tid][$row['kontext']] = $row['efekt_text'];
        }
    }
    return array_values($tiers);
}

// Uloží z $_POST (klíč 'vysledek' => [tier_id => [kontext => text]]) —
// smaže staré a zapíše jen neprázdné, stejný "replace-all" vzor jako
// dracak_relations_save().
function dracak_schopnost_vysledky_save(int $schopnostId, array $input): void
{
    $pdo = dracak_db();
    $pdo->prepare('DELETE FROM schopnost_vysledky WHERE schopnost_id = ?')->execute([$schopnostId]);
    $data = (array)($input['vysledek'] ?? []);
    $ins = $pdo->prepare('INSERT INTO schopnost_vysledky (schopnost_id, vysledek_testu_id, kontext, efekt_text) VALUES (?, ?, ?, ?)');
    foreach ($data as $tierId => $byContext) {
        foreach ((array)$byContext as $kontext => $text) {
            $text = trim((string)$text);
            if ($text === '' || !in_array($kontext, ['obecny', 'boj', 'mimo_boj'], true)) continue;
            $ins->execute([$schopnostId, (int)$tierId, $kontext, $text]);
        }
    }
}

// Zápis "2k6+2" / "1k6" / "" -> sloupce pocet_kostek/typ_kostky/pevny_bonus.
// Neplatný/prázdný zápis = všechny tři na NULL (žádná kostka u záznamu).
function dracak_kostky_zapis_parse(string $zapis): array
{
    $zapis = trim($zapis);
    if ($zapis === '' || !preg_match('/^(\d+)\s*(k\d+)\s*([+-]\s*\d+)?$/i', $zapis, $m)) {
        return ['pocet_kostek' => null, 'typ_kostky' => null, 'pevny_bonus' => null];
    }
    return [
        'pocet_kostek' => (int)$m[1],
        'typ_kostky' => strtolower($m[2]),
        'pevny_bonus' => isset($m[3]) ? (int)str_replace(' ', '', $m[3]) : null,
    ];
}

// Opak dracak_kostky_zapis_parse() — sloupce -> "2k6+2" pro edit formulář.
function dracak_kostky_zapis_format(array $row): string
{
    if (empty($row['pocet_kostek']) || empty($row['typ_kostky'])) {
        return '';
    }
    $text = $row['pocet_kostek'] . $row['typ_kostky'];
    if (!empty($row['pevny_bonus'])) {
        $bonus = (int)$row['pevny_bonus'];
        $text .= $bonus >= 0 ? "+$bonus" : (string)$bonus;
    }
    return $text;
}

// Přepíše M:N vztahy záznamu podle definice v entities.php ('relations').
// $input = $_POST, klíče 'rel_{join_table}' (pole ID) a volitelně
// 'rel_{join_table}_extra' (pole ID => extra hodnota). Vždy smaže staré
// vazby a zapíše nové (jednoduché, bezpečné proti duplicitám i mazání).
function dracak_relations_save(string $table, int $id, array $relations, array $input): void
{
    if (!$relations) {
        return;
    }
    $pdo = dracak_db();
    foreach ($relations as $rel) {
        $join = $rel['join_table'];
        $ownFk = $rel['own_fk'];
        $otherFk = $rel['other_fk'];
        $pdo->prepare("DELETE FROM `$join` WHERE `$ownFk` = ?")->execute([$id]);

        $ids = array_map('intval', (array)($input['rel_' . $join] ?? []));
        $extra = (array)($input['rel_' . $join . '_extra'] ?? []);
        if (!$ids) {
            continue;
        }
        $hasExtra = !empty($rel['extra_column']);
        $sql = $hasExtra
            ? "INSERT INTO `$join` (`$ownFk`, `$otherFk`, `{$rel['extra_column']}`) VALUES (?, ?, ?)"
            : "INSERT INTO `$join` (`$ownFk`, `$otherFk`) VALUES (?, ?)";
        $ins = $pdo->prepare($sql);
        foreach (array_unique($ids) as $otherId) {
            if ($hasExtra) {
                $val = trim((string)($extra[$otherId] ?? ''));
                $ins->execute([$id, $otherId, $val !== '' ? $val : null]);
            } else {
                $ins->execute([$id, $otherId]);
            }
        }
    }
}

// Aktuálně přiřazené položky vztahu (s labelem + extra hodnotou), pro
// vykreslení tagů v edit formuláři.
function dracak_relation_current(string $join, string $ownFk, string $otherFk, string $otherTable, string $otherLabel, int $id, ?string $extraColumn = null): array
{
    $extraSql = $extraColumn ? ", j.`$extraColumn` AS extra" : '';
    $sql = "SELECT o.id, o.`$otherLabel` AS label$extraSql FROM `$join` j
            JOIN `$otherTable` o ON o.id = j.`$otherFk`
            WHERE j.`$ownFk` = ? ORDER BY o.`$otherLabel`";
    $stmt = dracak_db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

// Všechny možné položky druhé strany vztahu (pro "+ přidat" dropdown).
function dracak_relation_options(string $otherTable, string $otherLabel): array
{
    return dracak_db()->query("SELECT id, `$otherLabel` AS label FROM `$otherTable` ORDER BY `$otherLabel`")->fetchAll();
}

function dracak_entity_delete(string $table, int $id): void
{
    $stmt = dracak_db()->prepare("DELETE FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
}

// Poskládá "2k6+2" / "1k6 (lze seslat vícekrát, max 3x denně)" ze sloupců
// zavedených dracak_kostky_pole() — aby se to v seznamu neukazovalo jako
// tři/čtyři rozházená syrová pole, ale jako jeden čitelný zápis.
function dracak_format_kostky(array $row): ?string
{
    if (empty($row['pocet_kostek']) || empty($row['typ_kostky'])) {
        return null;
    }
    $text = $row['pocet_kostek'] . $row['typ_kostky'];
    if (!empty($row['pevny_bonus'])) {
        $bonus = (int)$row['pevny_bonus'];
        $text .= $bonus >= 0 ? "+$bonus" : (string)$bonus;
    }
    if (!empty($row['vicenasobne'])) {
        $text .= ' (lze víckrát' . (!empty($row['max_pouziti']) ? ', max ' . $row['max_pouziti'] : '') . ')';
    }
    return $text;
}

// Vykreslí "živou kartu" ze skutečných DB dat pro pravidla.php — nahrazuje
// zmrzlý statický text u napojeného nadpisu (viz content/edit-links.json)
// aktuálním obsahem DB. Používá stejné třídy jako artefakt pravidel
// (statline/chip/chip-k/chip-v), aby to vizuálně sedělo se zbytkem knihy.
// $config = definice z entities.php pro danou tabulku.
function dracak_render_pravidla_card(string $table, int $id, array $config): ?string
{
    $row = dracak_entity_get($table, $id);
    if ($row === null) return null;
    $fields = dracak_entity_fields($table, $config);
    $fieldsByName = [];
    foreach ($fields as $f) $fieldsByName[$f['name']] = $f;

    $chips = [];
    foreach ($config['summary_fields'] ?? [] as $fieldName => $label) {
        if (empty($row[$fieldName])) continue;
        $value = $row[$fieldName];
        $fd = $fieldsByName[$fieldName] ?? null;
        if ($fd && $fd['type'] === 'select_fk') {
            $opt = dracak_fk_options($fd['ref_table'], $fd['ref_label']);
            $value = array_column($opt, 'label', 'id')[(int)$value] ?? $value;
        }
        $chips[] = '<span class="chip"><span class="chip-k">' . htmlspecialchars($label) . ':</span><span class="chip-v"> ' . htmlspecialchars((string)$value) . '</span></span>';
    }
    $kostky = dracak_kostky_zapis_format($row);
    if ($kostky) {
        $chips[] = '<span class="chip"><span class="chip-k">kostky:</span><span class="chip-v"> ' . htmlspecialchars($kostky) . '</span></span>';
    }

    $html = '';
    if ($chips) {
        $html .= '<p class="statline">' . implode('', $chips) . '</p>';
    }
    $popisField = $fieldsByName['popis'] ?? $fieldsByName['obsah'] ?? $fieldsByName['poznamky'] ?? null;
    if ($popisField && !empty($row[$popisField['name']])) {
        $html .= '<p>' . nl2br(htmlspecialchars((string)$row[$popisField['name']])) . '</p>';
    }
    foreach ($config['relations'] ?? [] as $rel) {
        $items = dracak_relation_current($rel['join_table'], $rel['own_fk'], $rel['other_fk'], $rel['other_table'], $rel['other_label'], $id, $rel['extra_column'] ?? null);
        if (!$items) continue;
        $labels = array_map(function ($it) use ($rel) {
            $label = $it['label'];
            if (!empty($rel['extra_column']) && $it['extra'] !== null && $it['extra'] !== '') {
                $label .= ' (' . $it['extra'] . ')';
            }
            return htmlspecialchars((string)$label);
        }, $items);
        $html .= '<p class="cb cb-note"><em>' . htmlspecialchars($rel['label']) . ':</em> ' . implode(', ', $labels) . '</p>';
    }
    if (!empty($config['vysledky_testu'])) {
        $tiers = dracak_schopnost_vysledky_load($id);
        $rows = [];
        foreach ($tiers as $t) {
            $parts = [];
            if ($t['obecny'] !== '') $parts[] = $t['obecny'];
            if ($t['boj'] !== '') $parts[] = 'V boji: ' . $t['boj'];
            if ($t['mimo_boj'] !== '') $parts[] = 'Mimo boj: ' . $t['mimo_boj'];
            if ($parts) $rows[] = '<tr><td>' . htmlspecialchars($t['nazev']) . '</td><td>' . htmlspecialchars(implode(' ', $parts)) . '</td></tr>';
        }
        if ($rows) {
            $html .= '<div class="table-wrap"><table class="stat-table"><tbody>' . implode('', $rows) . '</tbody></table></div>';
        }
    }
    return $html !== '' ? $html : null;
}
