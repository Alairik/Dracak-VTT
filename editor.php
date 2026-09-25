<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/entity_crud.php';

$user = dracak_require_login();
$allEntities = require __DIR__ . '/includes/entities.php';
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);

// Viditelnost: hráč nevidí group=bestiar (bestiář, PJ poznámky) ani ciselniky (systémová data).
$visibleEntities = array_filter($allEntities, function ($cfg) use ($isPjOrAdmin) {
    if ($cfg['group'] === 'bestiar' && !$isPjOrAdmin) return false;
    if ($cfg['group'] === 'ciselniky' && !$isPjOrAdmin) return false;
    return true;
});

function dracak_group_label(string $g): string
{
    return ['obsah' => 'Obsah pravidel', 'bestiar' => 'Bestiář a PJ', 'ciselniky' => 'Číselníky'][$g] ?? $g;
}

// ---------- Nástroj: maticový pohled na velikost_modifikatory ----------
$nastroj = $_GET['nastroj'] ?? null;
if ($nastroj === 'matice') {
    dracak_require_role('admin', 'pj');
    $sizeCodes = ['A0', 'A', 'B', 'C', 'D', 'E'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo = dracak_db();
        $stmt = $pdo->prepare(
            'INSERT INTO velikost_modifikatory (velikost_utocnik, velikost_obrance, modifikator_utoku) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE modifikator_utoku = VALUES(modifikator_utoku)'
        );
        foreach ($sizeCodes as $u) {
            foreach ($sizeCodes as $o) {
                $val = trim((string)($_POST['cell'][$u][$o] ?? ''));
                if ($val === '' || !preg_match('/^-?\d+$/', $val)) continue;
                $stmt->execute([$u, $o, (int)$val]);
            }
        }
        header('Location: editor.php?nastroj=matice');
        exit;
    }
    $sizeLabels = dracak_db()->query('SELECT kod, nazev FROM velikosti')->fetchAll(PDO::FETCH_KEY_PAIR);
    $existing = [];
    foreach (dracak_db()->query('SELECT velikost_utocnik, velikost_obrance, modifikator_utoku FROM velikost_modifikatory')->fetchAll() as $r) {
        $existing[$r['velikost_utocnik']][$r['velikost_obrance']] = $r['modifikator_utoku'];
    }
}

// ---------- Výběr entity + řazení "naposledy otevřené" ----------
$table = $_GET['tabulka'] ?? null;
if ($nastroj === null) {
    if ($table === null || !isset($visibleEntities[$table])) {
        $keys = array_keys($visibleEntities);
        $table = $keys[0] ?? null;
    }
    if ($table === null || !isset($visibleEntities[$table])) {
        http_response_code(500);
        die('Žádné entity nejsou nakonfigurované.');
    }
    if (empty($_SESSION['dracak_recent'])) $_SESSION['dracak_recent'] = [];
    $_SESSION['dracak_recent'] = array_slice(
        array_values(array_unique(array_merge([$table], $_SESSION['dracak_recent']))),
        0, 4
    );
    $config = $visibleEntities[$table];
    $fields = dracak_entity_fields($table, $config);
    $canEdit = dracak_can_edit($user, $table);
    $isCiselnik = $config['group'] === 'ciselniky';
}

$akce = $_GET['akce'] ?? 'seznam';
$flash = null;

// ---------- POST: uložit / smazat ----------
if ($nastroj === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        die('Nemáš právo tenhle typ záznamu editovat.');
    }
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $existingRow = $id !== null ? dracak_entity_get($table, $id) : null;
    if (!dracak_can_edit_row($user, $table, $config, $id !== null ? $existingRow : null)) {
        http_response_code(403);
        die('Tenhle záznam patří jinému uživateli — smíš upravovat jen svoje vlastní záznamy.');
    }
    if (($_POST['akce'] ?? '') === 'smazat') {
        dracak_entity_delete($table, $id);
        header("Location: editor.php?tabulka=$table");
        exit;
    }
    dracak_entity_save($table, $fields, $id, $_POST, $config, (int)$user['id']);
    header("Location: editor.php?tabulka=$table");
    exit;
}

$editRow = null;
if ($nastroj === null) {
    if ($akce === 'novy' && $canEdit) {
        $editRow = array_fill_keys(array_column($fields, 'name'), '');
        $editRow['id'] = null;
    } elseif ($akce === 'edit' && $canEdit && !empty($_GET['id'])) {
        $editRow = dracak_entity_get($table, (int)$_GET['id']);
        if ($editRow !== null && !dracak_can_edit_row($user, $table, $config, $editRow)) {
            $editRow = null; // cizí záznam — zobraz aspoň seznam, ne cizí formulář
        }
    }

    // ---------- Filtry a hledání (jen na seznamu, jen server-side SQL) ----------
    $search = trim((string)($_GET['q'] ?? ''));
    $filterValues = [];
    $where = [];
    $params = [];
    if ($editRow === null && !$isCiselnik) {
        if ($search !== '' && in_array('nazev', array_column($fields, 'name'), true)) {
            $where[] = '`nazev` LIKE ?';
            $params[] = '%' . $search . '%';
        }
        foreach ($fields as $f) {
            if (empty($f['filter'])) continue;
            $name = $f['name'];
            if ($f['filter'] === 'range') {
                $min = trim((string)($_GET[$name . '_min'] ?? ''));
                $max = trim((string)($_GET[$name . '_max'] ?? ''));
                $filterValues[$name] = ['min' => $min, 'max' => $max];
                if ($min !== '' && is_numeric($min)) { $where[] = "`$name` >= ?"; $params[] = $min; }
                if ($max !== '' && is_numeric($max)) { $where[] = "`$name` <= ?"; $params[] = $max; }
            } elseif ($f['filter'] === 'select') {
                $val = trim((string)($_GET[$name] ?? ''));
                $filterValues[$name] = $val;
                if ($val !== '') { $where[] = "`$name` = ?"; $params[] = $val; }
            }
        }
        // Kouzla — speciální filtry: mana/dosah (volný text, parsuje se v PHP
        // po SQL dotazu) a Povolání (přes seznam_kouzel_id -> seznamy_kouzel.povolani_id).
        if (in_array('kouzla_mana_dosah_povolani', $config['special_filters'] ?? [], true)) {
            $povolaniFilter = trim((string)($_GET['povolani'] ?? ''));
            if ($povolaniFilter !== '' && ctype_digit($povolaniFilter)) {
                $where[] = 'seznam_kouzel_id IN (SELECT id FROM seznamy_kouzel WHERE povolani_id = ?)';
                $params[] = (int)$povolaniFilter;
            }
        }
        $rows = dracak_entity_list($table, $config, ['where' => $where, 'params' => $params]);
        if (in_array('kouzla_mana_dosah_povolani', $config['special_filters'] ?? [], true)) {
            $manaMin = trim((string)($_GET['cena_magenergie_min'] ?? ''));
            $manaMax = trim((string)($_GET['cena_magenergie_max'] ?? ''));
            $dosahMin = trim((string)($_GET['dosah_min'] ?? ''));
            $dosahMax = trim((string)($_GET['dosah_max'] ?? ''));
            $leadNum = function (?string $s): int {
                if ($s === null) return 0;
                if (preg_match('/(\d+)/', $s, $m)) return (int)$m[1];
                return 0; // "dotek"/"—"/chybí = 0, viz zadání
            };
            $inRange = function (int $val, string $min, string $max): bool {
                if ($min !== '' && is_numeric($min) && $val < (float)$min) return false;
                if ($max !== '' && is_numeric($max) && $val > (float)$max) return false;
                return true;
            };
            if ($manaMin !== '' || $manaMax !== '' || $dosahMin !== '' || $dosahMax !== '') {
                $rows = array_values(array_filter($rows, function ($r) use ($leadNum, $inRange, $manaMin, $manaMax, $dosahMin, $dosahMax) {
                    return $inRange($leadNum($r['cena_magenergie'] ?? null), $manaMin, $manaMax)
                        && $inRange($leadNum($r['dosah'] ?? null), $dosahMin, $dosahMax);
                }));
            }
        }
    } elseif ($editRow === null && $isCiselnik) {
        $rows = dracak_entity_list($table, $config);
    } else {
        $rows = [];
    }

    // Volby pro select-filtry (distinct hodnoty / povolání pro kouzla).
    $filterSelectOptions = [];
    if ($editRow === null && !$isCiselnik) {
        foreach ($fields as $f) {
            if (($f['filter'] ?? null) !== 'select') continue;
            $filterSelectOptions[$f['name']] = $f['options'] ?: dracak_distinct_values($table, $f['name']);
        }
        if (in_array('kouzla_mana_dosah_povolani', $config['special_filters'] ?? [], true)) {
            $filterSelectOptions['__povolani'] = dracak_db()->query(
                'SELECT DISTINCT p.id, p.nazev FROM povolani p JOIN seznamy_kouzel s ON s.povolani_id = p.id ORDER BY p.nazev'
            )->fetchAll();
        }
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — editor pravidel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/organic.css">
</head>
<body>
<button class="menu-btn" id="menuBtn" type="button" aria-label="Otevřít navigaci">☰</button>
<div class="backdrop" id="backdrop"></div>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php" style="text-decoration:none;color:inherit;">🏠 Dračák VTT</a>
    <div class="sidebar-user">
      <?= htmlspecialchars($user['jmeno']) ?><span class="role-badge"><?= htmlspecialchars($user['role']) ?></span>
    </div>

    <div class="field"><input class="input" id="entitySearch" style="padding:6px 10px;font-size:12px" placeholder="Najít entitu…"></div>

    <?php if (!empty($_SESSION['dracak_recent'])): ?>
      <div class="mock-sec">Naposledy otevřené</div>
      <?php foreach ($_SESSION['dracak_recent'] as $key):
          if (!isset($visibleEntities[$key])) continue; ?>
        <a class="mock-item <?= ($nastroj === null && $key === $table) ? 'active' : '' ?>" data-entlabel="<?= htmlspecialchars(mb_strtolower($visibleEntities[$key]['label'])) ?>"
           href="editor.php?tabulka=<?= urlencode($key) ?>"><?= htmlspecialchars($visibleEntities[$key]['label']) ?></a>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php
    $byGroup = [];
    foreach ($visibleEntities as $key => $cfg) { $byGroup[$cfg['group']][$key] = $cfg; }
    foreach (['obsah', 'bestiar', 'ciselniky'] as $group):
        if (empty($byGroup[$group])) continue;
    ?>
      <div class="mock-sec"><?= dracak_group_label($group) ?></div>
      <?php foreach ($byGroup[$group] as $key => $cfg):
          $count = null; ?>
        <a class="mock-item <?= ($nastroj === null && $key === $table) ? 'active' : '' ?>" data-entlabel="<?= htmlspecialchars(mb_strtolower($cfg['label'])) ?>"
           href="editor.php?tabulka=<?= urlencode($key) ?>"><?= htmlspecialchars($cfg['label']) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <?php if ($isPjOrAdmin): ?>
      <div class="mock-sec">Nástroje</div>
      <a class="mock-item <?= $nastroj === 'matice' ? 'active' : '' ?>" href="editor.php?nastroj=matice">Nosnost — matice</a>
    <?php endif; ?>

    <div class="sidebar-bottom">
      <a class="mock-item" href="dashboard.php">🏠 Dashboard</a>
      <a class="mock-item" href="pravidla.php">📖 Pravidla</a>
      <a class="mock-item" href="mapa.html">🗺️ Mapa světa</a>
      <?php if ($user['role'] === 'admin'): ?>
        <a class="mock-item" href="admin.php">⚙️ Správa účtů</a>
      <?php endif; ?>
      <form method="post" action="logout.php"><button class="btn btn-ghost" style="width:100%;margin-top:6px;">Odhlásit se</button></form>
    </div>
  </aside>

  <main class="main">
<?php if ($nastroj === 'matice'): ?>

    <h1 class="page-title">Nosnost podle síly a velikosti — matice</h1>
    <p class="crumb">Maticový pohled místo seznamu řádků — <code>velikost_modifikatory</code> (6×6): bonus/postih k hodu na útok podle velikosti útočníka a obránce.</p>
    <form method="post">
    <div class="table-wrap">
    <table class="table mtx">
      <thead><tr><th></th><?php foreach ($sizeCodes as $c): ?><th><?= htmlspecialchars($c) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach ($sizeCodes as $u): ?>
          <tr>
            <th title="<?= htmlspecialchars($sizeLabels[$u] ?? '') ?>"><?= htmlspecialchars($u) ?></th>
            <?php foreach ($sizeCodes as $o): ?>
              <td><input name="cell[<?= htmlspecialchars($u) ?>][<?= htmlspecialchars($o) ?>]" value="<?= htmlspecialchars((string)($existing[$u][$o] ?? '')) ?>" placeholder="0"></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="note" style="margin-top:10px;">Řádky = velikost útočníka, sloupce = velikost obránce. Prázdné pole se neuloží (bere se jako 0).</p>
    <button class="btn btn-primary" type="submit" style="margin-top:14px;">Uložit matici</button>
    </form>

<?php elseif ($editRow !== null): ?>

    <p class="crumb"><a href="editor.php?tabulka=<?= urlencode($table) ?>">← <?= htmlspecialchars($config['label']) ?></a></p>
    <h1 class="page-title"><?= $akce === 'novy' ? 'Nový záznam' : htmlspecialchars((string)($editRow['nazev'] ?? $config['label'])) ?></h1>
    <form method="post" class="card elev-sm" style="max-width:680px;margin-top:14px;">
      <?php if ($akce === 'edit'): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
      <?php
      $quick = array_values(array_filter($fields, fn($f) => $f['quick'] && empty($f['kostky_core'])));
      $advanced = array_values(array_filter($fields, fn($f) => !$f['quick'] && empty($f['kostky_core'])));
      $hasKostky = (bool)array_filter($fields, fn($f) => !empty($f['kostky_core']));

      function dracak_render_field(array $f, $val): void {
          $val = $val ?? '';
          echo '<div class="field"><label for="f_' . htmlspecialchars($f['name']) . '">' . htmlspecialchars($f['label']) . ($f['required'] ? ' *' : '') . '</label>';
          if ($f['type'] === 'textarea') {
              echo '<textarea id="f_' . htmlspecialchars($f['name']) . '" name="' . htmlspecialchars($f['name']) . '" ' . ($f['required'] ? 'required' : '') . '>' . htmlspecialchars((string)$val) . '</textarea>';
          } elseif ($f['type'] === 'checkbox') {
              echo '<input type="checkbox" id="f_' . htmlspecialchars($f['name']) . '" name="' . htmlspecialchars($f['name']) . '" ' . ($val ? 'checked' : '') . '>';
          } elseif ($f['type'] === 'select' && $f['options']) {
              echo '<select id="f_' . htmlspecialchars($f['name']) . '" name="' . htmlspecialchars($f['name']) . '"><option value="">—</option>';
              foreach ($f['options'] as $opt) {
                  echo '<option value="' . htmlspecialchars($opt) . '" ' . ((string)$val === $opt ? 'selected' : '') . '>' . htmlspecialchars($opt) . '</option>';
              }
              echo '</select>';
          } elseif ($f['type'] === 'select_fk') {
              echo '<select id="f_' . htmlspecialchars($f['name']) . '" name="' . htmlspecialchars($f['name']) . '"><option value="">—</option>';
              foreach (dracak_fk_options($f['ref_table'], $f['ref_label']) as $opt) {
                  echo '<option value="' . (int)$opt['id'] . '" ' . ((string)$val === (string)$opt['id'] ? 'selected' : '') . '>' . htmlspecialchars((string)$opt['label']) . '</option>';
              }
              echo '</select>';
          } else {
              echo '<input type="' . ($f['type'] === 'number' ? 'number' : 'text') . '" id="f_' . htmlspecialchars($f['name']) . '" name="' . htmlspecialchars($f['name']) . '" value="' . htmlspecialchars((string)$val) . '" ' . ($f['required'] ? 'required' : '') . '>';
          }
          echo '</div>';
      }

      $nameField = $quick[0] ?? null;
      $shortQuick = [];
      $textareaQuick = [];
      foreach ($quick as $i => $f) {
          if ($i === 0 && $f['type'] !== 'textarea') continue;
          if ($f['type'] === 'textarea') $textareaQuick[] = $f; else $shortQuick[] = $f;
      }
      if ($nameField) dracak_render_field($nameField, $editRow[$nameField['name']] ?? '');
      if ($shortQuick): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <?php foreach ($shortQuick as $f) dracak_render_field($f, $editRow[$f['name']] ?? ''); ?>
        </div>
      <?php endif;
      if ($hasKostky):
          $kostkyZapis = dracak_kostky_zapis_format($editRow);
      ?>
        <div class="field"><label for="f_kostky_zapis">Kostky (zápis)</label>
          <input type="text" id="f_kostky_zapis" name="kostky_zapis" placeholder="např. 2k6+2" value="<?= htmlspecialchars($kostkyZapis) ?>">
        </div>
      <?php endif;
      foreach ($textareaQuick as $f) dracak_render_field($f, $editRow[$f['name']] ?? '');
      if ($advanced): ?>
        <details style="margin-top:10px;"><summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--color-accent-700);">Zobrazit všechna pole</summary>
          <div style="margin-top:10px;">
            <?php foreach ($advanced as $f) dracak_render_field($f, $editRow[$f['name']] ?? ''); ?>
          </div>
        </details>
      <?php endif; ?>

      <?php foreach ($config['relations'] ?? [] as $rel):
          $join = $rel['join_table'];
          $current = $editRow['id'] !== null
              ? dracak_relation_current($join, $rel['own_fk'], $rel['other_fk'], $rel['other_table'], $rel['other_label'], (int)$editRow['id'], $rel['extra_column'] ?? null)
              : [];
          $currentIds = array_column($current, 'id');
          $options = dracak_relation_options($rel['other_table'], $rel['other_label']);
          $extraType = $rel['extra_type'] ?? null;
      ?>
        <h3 class="rel-label"><?= htmlspecialchars($rel['label']) ?></h3>
        <div class="tagbox" data-rel="<?= htmlspecialchars($join) ?>">
          <?php foreach ($current as $item): ?>
            <span class="pill-x" data-id="<?= (int)$item['id'] ?>" data-label="<?= htmlspecialchars($item['label']) ?>">
              <?= htmlspecialchars($item['label']) ?>
              <?php if ($extraType === 'stepper'): $mod = (int)($item['extra'] ?? 0); ?>
                <span class="chip-stepper">
                  <button type="button" onclick="dracakBump(this,-1)">−</button><b class="chip-val"><?= $mod >= 0 ? '+' . $mod : $mod ?></b><button type="button" onclick="dracakBump(this,1)">+</button>
                </span>
                <input type="hidden" class="chip-extra" name="rel_<?= htmlspecialchars($join) ?>_extra[<?= (int)$item['id'] ?>]" value="<?= $mod ?>">
              <?php elseif ($extraType === 'text'): ?>
                <input type="text" class="chip-extra-input" name="rel_<?= htmlspecialchars($join) ?>_extra[<?= (int)$item['id'] ?>]" value="<?= htmlspecialchars((string)($item['extra'] ?? '')) ?>" placeholder="<?= htmlspecialchars($rel['extra_placeholder'] ?? '') ?>">
              <?php endif; ?>
              <button type="button" class="x-btn" onclick="dracakRemoveTag(this)">×</button>
              <input type="hidden" name="rel_<?= htmlspecialchars($join) ?>[]" value="<?= (int)$item['id'] ?>">
            </span>
          <?php endforeach; ?>
        </div>
        <div class="mn-add">
          <select data-rel="<?= htmlspecialchars($join) ?>" data-extra="<?= htmlspecialchars($extraType ?? '') ?>" data-placeholder="<?= htmlspecialchars($rel['extra_placeholder'] ?? '') ?>">
            <option value="">— vyber a přidej —</option>
            <?php foreach ($options as $opt): if (in_array((int)$opt['id'], $currentIds, true)) continue; ?>
              <option value="<?= (int)$opt['id'] ?>"><?= htmlspecialchars((string)$opt['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-secondary" onclick="dracakAddTag(this)">+ Přidat</button>
        </div>
      <?php endforeach; ?>

      <div style="display:flex;gap:10px;margin-top:20px;">
        <button class="btn btn-primary" type="submit">Uložit</button>
        <a class="btn btn-ghost" href="editor.php?tabulka=<?= urlencode($table) ?>">Zrušit</a>
      </div>
    </form>

<?php elseif ($isCiselnik): ?>

    <h1 class="page-title"><?= htmlspecialchars($config['label']) ?></h1>
    <p class="crumb">Malá referenční tabulka — jen admin/PJ.</p>
    <?php if (!$rows): ?>
      <div class="empty-state">Zatím tu nic není.</div>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr>
          <?php foreach (array_slice($fields, 0, 4) as $f): ?><th><?= htmlspecialchars($f['label']) ?></th><?php endforeach; ?>
          <th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach (array_slice($fields, 0, 4) as $f): ?>
                <td class="<?= $f['type'] === 'textarea' ? 'text-muted' : '' ?>"><?= htmlspecialchars((string)mb_substr((string)($row[$f['name']] ?? ''), 0, 80)) ?></td>
              <?php endforeach; ?>
              <td><a class="btn btn-ghost btn-icon" href="editor.php?tabulka=<?= urlencode($table) ?>&akce=edit&id=<?= (int)$row['id'] ?>" aria-label="Upravit">✎</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>

<?php else: ?>

    <h1 class="page-title"><?= htmlspecialchars($config['label']) ?></h1>
    <div class="toolbar">
      <form method="get" style="flex:1;display:flex;gap:10px;">
        <input type="hidden" name="tabulka" value="<?= htmlspecialchars($table) ?>">
        <input class="input" type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Hledat…" style="flex:1;">
        <?php foreach ($filterValues as $name => $v): if (is_array($v)) continue; if ($v === '') continue; ?>
          <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($v) ?>">
        <?php endforeach; ?>
        <button class="btn btn-secondary" type="submit">Hledat</button>
      </form>
      <?php if ($canEdit): ?>
        <a class="btn btn-primary" href="editor.php?tabulka=<?= urlencode($table) ?>&akce=novy">+ Nový záznam</a>
      <?php endif; ?>
    </div>

    <?php if ($filterValues): ?>
    <form method="get" class="filter-row">
      <input type="hidden" name="tabulka" value="<?= htmlspecialchars($table) ?>">
      <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
      <?php foreach ($fields as $f):
          if (empty($f['filter'])) continue;
          $name = $f['name'];
          if ($f['filter'] === 'range'): ?>
        <div><div class="filter-lbl"><?= htmlspecialchars($f['label']) ?></div>
          <div class="range-pair">
            <input class="range-in" type="number" name="<?= $name ?>_min" placeholder="od" value="<?= htmlspecialchars($filterValues[$name]['min'] ?? '') ?>">
            <span class="range-sep">–</span>
            <input class="range-in" type="number" name="<?= $name ?>_max" placeholder="do" value="<?= htmlspecialchars($filterValues[$name]['max'] ?? '') ?>">
          </div>
        </div>
      <?php elseif ($f['filter'] === 'select'): ?>
        <div><div class="filter-lbl"><?= htmlspecialchars($f['label']) ?></div>
          <select class="input filter-select" name="<?= $name ?>" onchange="this.form.submit()">
            <option value="">vše</option>
            <?php foreach ($filterSelectOptions[$name] ?? [] as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>" <?= ($filterValues[$name] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; endforeach;
      if (in_array('kouzla_mana_dosah_povolani', $config['special_filters'] ?? [], true)): ?>
        <div><div class="filter-lbl">Mana (magů)</div>
          <div class="range-pair">
            <input class="range-in" type="number" name="cena_magenergie_min" placeholder="od" value="<?= htmlspecialchars($_GET['cena_magenergie_min'] ?? '') ?>">
            <span class="range-sep">–</span>
            <input class="range-in" type="number" name="cena_magenergie_max" placeholder="do" value="<?= htmlspecialchars($_GET['cena_magenergie_max'] ?? '') ?>">
          </div>
        </div>
        <div><div class="filter-lbl">Dosah (sáhů)</div>
          <div class="range-pair">
            <input class="range-in" type="number" name="dosah_min" placeholder="od" value="<?= htmlspecialchars($_GET['dosah_min'] ?? '') ?>">
            <span class="range-sep">–</span>
            <input class="range-in" type="number" name="dosah_max" placeholder="do" value="<?= htmlspecialchars($_GET['dosah_max'] ?? '') ?>">
          </div>
        </div>
        <div><div class="filter-lbl">Povolání</div>
          <select class="input filter-select" name="povolani" onchange="this.form.submit()">
            <option value="">vše</option>
            <?php foreach ($filterSelectOptions['__povolani'] ?? [] as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= (string)($_GET['povolani'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nazev']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <button class="btn btn-secondary" type="submit" style="font-size:12px;padding:6px 12px;">Filtrovat</button>
    </form>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="empty-state">Žádné záznamy neodpovídají hledání<?= $canEdit ? '. Buď první.' : '.' ?></div>
    <?php else: ?>
      <div class="records-grid">
        <?php foreach ($rows as $row):
            $titleField = $config['fields'][0]['name'] ?? array_key_first($row);
            $gridFields = [];
            foreach ($config['summary_fields'] ?? [] as $field => $label) {
                if (!empty($row[$field])) $gridFields[] = ['k' => $label, 'v' => $row[$field]];
            }
            $kostky = dracak_kostky_zapis_format($row);
            if ($kostky) $gridFields[] = ['k' => 'Kostky', 'v' => $kostky];
            if ($table === 'kouzla' && !empty($row['seznam_kouzel_id'])) {
                $pName = dracak_db()->prepare(
                    'SELECT p.nazev FROM seznamy_kouzel s JOIN povolani p ON p.id = s.povolani_id WHERE s.id = ?'
                );
                $pName->execute([$row['seznam_kouzel_id']]);
                $pn = $pName->fetchColumn();
                if ($pn) $gridFields[] = ['k' => 'Povolání', 'v' => $pn];
            }
            if ($table === 'rasy') {
                $parentLabel = '(základní rasa)';
                if (!empty($row['rodic_rasa_id'])) {
                    $p = dracak_entity_get('rasy', (int)$row['rodic_rasa_id']);
                    $parentLabel = $p['nazev'] ?? $parentLabel;
                }
                $skills = array_column(dracak_relation_current('rasa_schopnosti', 'rasa_id', 'schopnost_id', 'zvlastni_schopnosti', 'nazev', (int)$row['id']), 'label');
                $gridFields[] = ['k' => 'rodičovská rasa', 'v' => $parentLabel];
                $gridFields[] = ['k' => 'schopnosti', 'v' => $skills ? implode(', ', $skills) : '—'];
            }
        ?>
          <div class="card elev-sm rec-card">
            <div style="display:flex;justify-content:space-between;align-items:baseline;">
              <h3 class="rec-title"><?= htmlspecialchars((string)($row['nazev'] ?? $row[$titleField] ?? $row['id'])) ?></h3>
            </div>
            <?php if ($gridFields): ?>
              <div class="rec-grid">
                <?php foreach ($gridFields as $gf): ?>
                  <div class="k"><?= htmlspecialchars($gf['k']) ?>:</div><div><?= htmlspecialchars((string)$gf['v']) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($row['popis'])): ?>
              <div class="rec-body"><p><?= htmlspecialchars((string)$row['popis']) ?></p></div>
            <?php endif; ?>
            <?php if (!empty($config['row_owned'])): ?>
              <div class="rec-owner"><?= empty($row['created_by']) ? 'Systémový záznam' : 'Vlastní záznam' ?></div>
            <?php endif; ?>
            <?php if (dracak_can_edit_row($user, $table, $config, $row)): ?>
              <div class="rec-actions">
                <a class="btn btn-secondary" href="editor.php?tabulka=<?= urlencode($table) ?>&akce=edit&id=<?= (int)$row['id'] ?>">Upravit</a>
                <form method="post" onsubmit="return confirm('Opravdu smazat?');">
                  <input type="hidden" name="akce" value="smazat">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="btn btn-ghost" type="submit">Smazat</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

<?php endif; ?>
  </main>
</div>
<script>
(function(){
  var menuBtn = document.getElementById('menuBtn'), sidebar = document.getElementById('sidebar'), backdrop = document.getElementById('backdrop');
  function closeNav(){ sidebar.classList.remove('open'); backdrop.classList.remove('show'); }
  if (menuBtn) menuBtn.addEventListener('click', function(){ sidebar.classList.toggle('open'); backdrop.classList.toggle('show'); });
  if (backdrop) backdrop.addEventListener('click', closeNav);

  var es = document.getElementById('entitySearch');
  if (es) es.addEventListener('input', function(){
    var q = es.value.trim().toLowerCase();
    document.querySelectorAll('.mock-item[data-entlabel]').forEach(function(el){
      el.style.display = (!q || el.getAttribute('data-entlabel').indexOf(q) !== -1) ? '' : 'none';
    });
  });
})();

function dracakAddTag(btn){
  var wrap = btn.parentElement, select = wrap.querySelector('select');
  var opt = select.options[select.selectedIndex];
  if (!opt || !opt.value) return;
  var join = select.getAttribute('data-rel'), extraType = select.getAttribute('data-extra'), placeholder = select.getAttribute('data-placeholder') || '';
  var tagbox = document.querySelector('.tagbox[data-rel="' + join + '"]');
  var span = document.createElement('span');
  span.className = 'pill-x'; span.setAttribute('data-id', opt.value); span.setAttribute('data-label', opt.text);
  var html = opt.text;
  if (extraType === 'stepper') {
    html += ' <span class="chip-stepper"><button type="button" onclick="dracakBump(this,-1)">−</button><b class="chip-val">+0</b><button type="button" onclick="dracakBump(this,1)">+</button></span>'
      + '<input type="hidden" class="chip-extra" name="rel_' + join + '_extra[' + opt.value + ']" value="0">';
  } else if (extraType === 'text') {
    html += '<input type="text" class="chip-extra-input" name="rel_' + join + '_extra[' + opt.value + ']" value="" placeholder="' + placeholder + '">';
  }
  html += ' <button type="button" class="x-btn" onclick="dracakRemoveTag(this)">×</button><input type="hidden" name="rel_' + join + '[]" value="' + opt.value + '">';
  span.innerHTML = html;
  tagbox.appendChild(span);
  opt.remove();
  select.value = '';
}
function dracakRemoveTag(btn){
  var pill = btn.closest('.pill-x');
  var wrap = pill.closest('div').parentElement || pill.parentElement;
  var join = pill.closest('.tagbox').getAttribute('data-rel');
  var select = document.querySelector('select[data-rel="' + join + '"]');
  var opt = document.createElement('option');
  opt.value = pill.getAttribute('data-id'); opt.text = pill.getAttribute('data-label');
  select.appendChild(opt);
  pill.remove();
}
function dracakBump(btn, delta){
  var stepper = btn.closest('.chip-stepper'), pill = btn.closest('.pill-x');
  var valEl = stepper.querySelector('.chip-val'), input = pill.querySelector('.chip-extra');
  var v = parseInt(input.value, 10) + delta;
  input.value = v; valEl.textContent = (v >= 0 ? '+' : '') + v;
}
</script>
</body>
</html>
