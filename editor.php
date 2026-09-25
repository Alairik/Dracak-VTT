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

$table = $_GET['tabulka'] ?? null;
if ($table === null || !isset($visibleEntities[$table])) {
    $keys = array_keys($visibleEntities);
    $table = $keys[0] ?? null;
}
if ($table === null || !isset($visibleEntities[$table])) {
    http_response_code(500);
    die('Žádné entity nejsou nakonfigurované.');
}
$config = $visibleEntities[$table];
$fields = dracak_entity_fields($table, $config);
$canEdit = dracak_can_edit($user, $table);
$akce = $_GET['akce'] ?? 'seznam';
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        die('Nemáš právo tenhle typ záznamu editovat.');
    }
    if (($_POST['akce'] ?? '') === 'smazat') {
        dracak_entity_delete($table, (int)$_POST['id']);
        header("Location: editor.php?tabulka=$table");
        exit;
    }
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    // Hráč smí editovat jen povolené typy a jen svoje vlastní záznamy (mimo admin/pj).
    dracak_entity_save($table, $fields, $id, $_POST);
    header("Location: editor.php?tabulka=$table");
    exit;
}

$editRow = null;
if ($akce === 'novy' && $canEdit) {
    $editRow = array_fill_keys(array_column($fields, 'name'), '');
} elseif ($akce === 'edit' && $canEdit && !empty($_GET['id'])) {
    $editRow = dracak_entity_get($table, (int)$_GET['id']);
}

$rows = $editRow === null ? dracak_entity_list($table, $config) : [];

function dracak_group_label(string $g): string
{
    return ['obsah' => 'Obsah pravidel', 'bestiar' => 'Bestiář a PJ', 'ciselniky' => 'Číselníky'][$g] ?? $g;
}
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — editor pravidel</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="editor-shell">
  <aside class="sidebar">
    <div class="sidebar-header">
      <strong><?= htmlspecialchars($user['jmeno']) ?></strong>
      <div class="role-badge"><?= htmlspecialchars($user['role']) ?></div>
    </div>
    <?php
    $byGroup = [];
    foreach ($visibleEntities as $key => $cfg) {
        $byGroup[$cfg['group']][$key] = $cfg;
    }
    foreach (['obsah', 'bestiar', 'ciselniky'] as $group):
        if (empty($byGroup[$group])) continue;
    ?>
      <div style="padding:10px 18px 4px; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-dim);">
        <?= dracak_group_label($group) ?>
      </div>
      <?php foreach ($byGroup[$group] as $key => $cfg): ?>
        <a class="nav-item <?= $key === $table ? 'active' : '' ?>" href="editor.php?tabulka=<?= urlencode($key) ?>">
          <span><?= htmlspecialchars($cfg['label']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="sidebar-footer">
      <a class="nav-item" href="dashboard.php">🏠 Dashboard</a>
      <a class="nav-item" href="pravidla.php">📖 Pravidla</a>
      <a class="nav-item" href="mapa.html">🗺️ Mapa světa</a>
      <?php if ($user['role'] === 'admin'): ?>
        <a class="nav-item" href="admin.php">⚙️ Správa účtů</a>
      <?php endif; ?>
      <form method="post" action="logout.php"><button class="btn-secondary" style="width:100%; margin-top:10px;">Odhlásit se</button></form>
    </div>
  </aside>

  <main class="main">
    <?php if ($editRow !== null): ?>
      <div class="main-header">
        <h2><?= $akce === 'novy' ? 'Nový záznam' : 'Upravit záznam' ?> — <?= htmlspecialchars($config['label']) ?></h2>
        <a class="btn-secondary" style="text-decoration:none;" href="editor.php?tabulka=<?= urlencode($table) ?>">← Zpět na seznam</a>
      </div>
      <form method="post" class="card" style="max-width:640px;">
        <?php if ($akce === 'edit'): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
        <?php
        $quick = array_filter($fields, fn($f) => $f['quick']);
        $advanced = array_filter($fields, fn($f) => !$f['quick']);
        foreach ([$quick, $advanced] as $i => $group):
            if (!$group) continue;
            if ($i === 1): ?>
              <details style="margin-top:16px;"><summary style="cursor:pointer; color:var(--text-dim);">Zobrazit všechna pole</summary>
            <?php endif;
            foreach ($group as $f):
                $val = $editRow[$f['name']] ?? '';
        ?>
          <label for="f_<?= $f['name'] ?>"><?= htmlspecialchars($f['label']) ?><?= $f['required'] ? ' *' : '' ?></label>
          <?php if ($f['type'] === 'textarea'): ?>
            <textarea id="f_<?= $f['name'] ?>" name="<?= $f['name'] ?>" <?= $f['required'] ? 'required' : '' ?>><?= htmlspecialchars((string)$val) ?></textarea>
          <?php elseif ($f['type'] === 'checkbox'): ?>
            <input type="checkbox" id="f_<?= $f['name'] ?>" name="<?= $f['name'] ?>" style="width:auto;" <?= $val ? 'checked' : '' ?>>
          <?php elseif ($f['type'] === 'select' && $f['options']): ?>
            <select id="f_<?= $f['name'] ?>" name="<?= $f['name'] ?>">
              <option value="">—</option>
              <?php foreach ($f['options'] as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>" <?= (string)$val === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($f['type'] === 'select_fk'): ?>
            <select id="f_<?= $f['name'] ?>" name="<?= $f['name'] ?>">
              <option value="">—</option>
              <?php foreach (dracak_fk_options($f['ref_table'], $f['ref_label']) as $opt): ?>
                <option value="<?= (int)$opt['id'] ?>" <?= (string)$val === (string)$opt['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="<?= $f['type'] === 'number' ? 'number' : 'text' ?>" id="f_<?= $f['name'] ?>" name="<?= $f['name'] ?>"
                   value="<?= htmlspecialchars((string)$val) ?>" <?= $f['required'] ? 'required' : '' ?>>
          <?php endif; ?>
        <?php endforeach;
        endforeach;
        if ($advanced) echo '</details>';
        ?>
        <div style="display:flex; gap:10px; margin-top:20px;">
          <button class="btn-primary" type="submit" style="margin-top:0;">Uložit</button>
          <a class="btn-secondary" style="text-decoration:none; padding:10px 16px; display:inline-block;" href="editor.php?tabulka=<?= urlencode($table) ?>">Zrušit</a>
        </div>
      </form>

    <?php else: ?>
      <div class="main-header">
        <h2><?= htmlspecialchars($config['label']) ?></h2>
        <?php if ($canEdit): ?>
          <a class="btn-primary" style="margin-top:0; width:auto; text-decoration:none; display:inline-block;"
             href="editor.php?tabulka=<?= urlencode($table) ?>&akce=novy">+ Nový záznam</a>
        <?php endif; ?>
      </div>
      <?php if (!$rows): ?>
        <div class="empty-state">Zatím tu nic není<?= $canEdit ? '. Buď první.' : '.' ?></div>
      <?php else: ?>
        <div class="entity-list">
          <?php foreach ($rows as $row):
              $titleField = $config['fields'][0]['name'] ?? array_key_first($row);
              $badges = [];
              foreach ($config['summary_fields'] ?? [] as $field => $badgeLabel) {
                  if (!empty($row[$field])) {
                      $badges[] = $badgeLabel . ': ' . $row[$field];
                  }
              }
              $kostky = dracak_format_kostky($row);
              if ($kostky) {
                  $badges[] = 'Kostky: ' . $kostky;
              }
          ?>
            <div class="entity-row">
              <div>
                <strong><?= htmlspecialchars((string)($row['nazev'] ?? $row[$titleField] ?? $row['id'])) ?></strong>
                <?php if ($badges): ?>
                  <div class="stat-badges">
                    <?php foreach ($badges as $b): ?><span class="stat-badge"><?= htmlspecialchars($b) ?></span><?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($row['popis'])): ?>
                  <div class="meta"><?= htmlspecialchars(mb_substr((string)$row['popis'], 0, 160)) ?></div>
                <?php endif; ?>
              </div>
              <?php if ($canEdit): ?>
                <div style="display:flex; gap:8px;">
                  <a class="btn-secondary" style="text-decoration:none; padding:6px 12px;"
                     href="editor.php?tabulka=<?= urlencode($table) ?>&akce=edit&id=<?= (int)$row['id'] ?>">Upravit</a>
                  <form method="post" onsubmit="return confirm('Opravdu smazat?');">
                    <input type="hidden" name="akce" value="smazat">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="btn-secondary" style="padding:6px 12px;">Smazat</button>
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
</body>
</html>
