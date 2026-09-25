<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = dracak_require_role('admin');
$pdo = dracak_db();
$entities = require __DIR__ . '/includes/entities.php';
$editableTables = array_filter($entities, fn($c) => $c['group'] === 'obsah');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $akce = $_POST['akce'] ?? '';

    if ($akce === 'vytvorit') {
        $email = trim($_POST['email'] ?? '');
        $jmeno = trim($_POST['jmeno'] ?? '');
        $heslo = (string)($_POST['heslo'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'pj', 'hrac'], true) ? $_POST['role'] : 'hrac';
        if ($email === '' || $jmeno === '' || strlen($heslo) < 8) {
            $error = 'Vyplň e-mail, jméno a heslo (min. 8 znaků).';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO ucty (email, heslo_hash, jmeno, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$email, password_hash($heslo, PASSWORD_DEFAULT), $jmeno, $role]);
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? 'Tenhle e-mail už účet má.' : 'Chyba: ' . $e->getMessage();
            }
        }
    } elseif ($akce === 'ulozit') {
        $id = (int)$_POST['id'];
        $role = in_array($_POST['role'] ?? '', ['admin', 'pj', 'hrac'], true) ? $_POST['role'] : 'hrac';
        $pdo->prepare('UPDATE ucty SET role = ? WHERE id = ?')->execute([$role, $id]);
        $pdo->prepare('DELETE FROM ucet_opravneni WHERE ucet_id = ?')->execute([$id]);
        foreach ((array)($_POST['tabulky'] ?? []) as $t) {
            if (isset($editableTables[$t])) {
                $pdo->prepare('INSERT INTO ucet_opravneni (ucet_id, tabulka) VALUES (?, ?)')->execute([$id, $t]);
            }
        }
    } elseif ($akce === 'smazat') {
        $id = (int)$_POST['id'];
        if ($id !== (int)$user['id']) {
            $pdo->prepare('DELETE FROM ucty WHERE id = ?')->execute([$id]);
        }
    }
}

$users = $pdo->query('SELECT id, email, jmeno, role FROM ucty ORDER BY jmeno')->fetchAll();
$permsByUser = [];
foreach ($pdo->query('SELECT ucet_id, tabulka FROM ucet_opravneni')->fetchAll() as $r) {
    $permsByUser[$r['ucet_id']][] = $r['tabulka'];
}
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — správa účtů</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="editor-shell">
  <aside class="sidebar">
    <div class="sidebar-header">
      <strong><?= htmlspecialchars($user['jmeno']) ?></strong>
      <div class="role-badge">admin</div>
    </div>
    <a class="nav-item" href="dashboard.php">🏠 Dashboard</a>
    <a class="nav-item" href="editor.php">← Zpět do editoru</a>
    <div class="sidebar-footer">
      <form method="post" action="logout.php"><button class="btn-secondary" style="width:100%;">Odhlásit se</button></form>
    </div>
  </aside>

  <main class="main">
    <div class="main-header"><h2>Správa účtů</h2></div>
    <?php if ($error): ?><div class="error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card" style="max-width:480px; margin-bottom:24px;">
      <h3 style="margin-top:0;">Nový účet</h3>
      <form method="post">
        <input type="hidden" name="akce" value="vytvorit">
        <label>E-mail</label>
        <input type="email" name="email" required>
        <label>Jméno</label>
        <input type="text" name="jmeno" required>
        <label>Heslo (min. 8 znaků)</label>
        <input type="password" name="heslo" required minlength="8">
        <label>Role</label>
        <select name="role">
          <option value="hrac">hráč</option>
          <option value="pj">PJ</option>
          <option value="admin">admin</option>
        </select>
        <button class="btn-primary" type="submit">Založit účet</button>
      </form>
    </div>

    <div class="entity-list">
      <?php foreach ($users as $u): ?>
        <div class="entity-row" style="align-items:flex-start;">
          <form method="post" style="flex:1;">
            <input type="hidden" name="akce" value="ulozit">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <strong><?= htmlspecialchars($u['jmeno']) ?></strong>
            <span class="meta"><?= htmlspecialchars($u['email']) ?></span>
            <div style="margin-top:8px;">
              Role:
              <select name="role" style="width:auto; display:inline-block;">
                <?php foreach (['hrac' => 'hráč', 'pj' => 'PJ', 'admin' => 'admin'] as $val => $label): ?>
                  <option value="<?= $val ?>" <?= $u['role'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="meta" style="margin-top:8px;">
              Smí sám editovat (jen pro roli hráč):
              <div style="margin-top:4px;">
                <?php foreach ($editableTables as $key => $cfg): ?>
                  <label style="display:inline-flex; align-items:center; gap:4px; margin-right:12px; font-size:0.8rem;">
                    <input type="checkbox" style="width:auto;" name="tabulky[]" value="<?= $key ?>"
                      <?= in_array($key, $permsByUser[$u['id']] ?? [], true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($cfg['label']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <button class="btn-secondary" type="submit" style="margin-top:10px;">Uložit</button>
          </form>
          <?php if ((int)$u['id'] !== (int)$user['id']): ?>
            <form method="post" onsubmit="return confirm('Smazat účet <?= htmlspecialchars($u['email']) ?>?');">
              <input type="hidden" name="akce" value="smazat">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn-secondary">Smazat</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>
</body>
</html>
