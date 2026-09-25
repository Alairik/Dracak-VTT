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
    <div class="sidebar-user"><?= htmlspecialchars($user['jmeno']) ?><span class="role-badge">admin</span></div>
    <a class="mock-item" href="dashboard.php">🏠 Dashboard</a>
    <a class="mock-item active" href="admin.php">Správa účtů</a>
    <a class="mock-item" href="editor.php">← Zpět do editoru</a>
    <div class="sidebar-bottom">
      <form method="post" action="logout.php"><button class="btn btn-ghost" style="width:100%;">Odhlásit se</button></form>
    </div>
  </aside>

  <main class="main">
    <h1 class="page-title">Správa účtů</h1>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="card elev-sm" style="max-width:480px; margin:16px 0 24px;">
      <h3 style="font-size:16px;margin-bottom:12px;">Nový účet</h3>
      <form method="post">
        <input type="hidden" name="akce" value="vytvorit">
        <div class="field"><label>E-mail</label><input type="email" name="email" required></div>
        <div class="field"><label>Jméno</label><input type="text" name="jmeno" required></div>
        <div class="field"><label>Heslo (min. 8 znaků)</label><input type="password" name="heslo" required minlength="8"></div>
        <div class="field"><label>Role</label>
          <select name="role">
            <option value="hrac">hráč</option>
            <option value="pj">PJ</option>
            <option value="admin">admin</option>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">Založit účet</button>
      </form>
    </div>

    <div class="records-grid" style="grid-template-columns:1fr;">
      <?php foreach ($users as $u): ?>
        <div class="card elev-sm" style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;">
          <form method="post" style="flex:1;">
            <input type="hidden" name="akce" value="ulozit">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <div class="rec-title" style="font-size:16px;"><?= htmlspecialchars($u['jmeno']) ?></div>
            <div class="note"><?= htmlspecialchars($u['email']) ?></div>
            <div class="field" style="margin-top:10px;max-width:200px;">
              <label>Role</label>
              <select name="role">
                <?php foreach (['hrac' => 'hráč', 'pj' => 'PJ', 'admin' => 'admin'] as $val => $label): ?>
                  <option value="<?= $val ?>" <?= $u['role'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="note" style="margin-top:10px;">Smí sám editovat (jen pro roli hráč):</div>
            <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
              <?php foreach ($editableTables as $key => $cfg): ?>
                <label class="tag" style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                  <input type="checkbox" style="width:auto;" name="tabulky[]" value="<?= $key ?>"
                    <?= in_array($key, $permsByUser[$u['id']] ?? [], true) ? 'checked' : '' ?>>
                  <?= htmlspecialchars($cfg['label']) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <button class="btn btn-secondary" type="submit" style="margin-top:12px;font-size:12px;padding:6px 12px;">Uložit</button>
          </form>
          <?php if ((int)$u['id'] !== (int)$user['id']): ?>
            <form method="post" onsubmit="return confirm('Smazat účet <?= htmlspecialchars($u['email']) ?>?');">
              <input type="hidden" name="akce" value="smazat">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn btn-ghost" type="submit">Smazat</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>
<script>
(function(){
  var menuBtn = document.getElementById('menuBtn'), sidebar = document.getElementById('sidebar'), backdrop = document.getElementById('backdrop');
  function closeNav(){ sidebar.classList.remove('open'); backdrop.classList.remove('show'); }
  if (menuBtn) menuBtn.addEventListener('click', function(){ sidebar.classList.toggle('open'); backdrop.classList.toggle('show'); });
  if (backdrop) backdrop.addEventListener('click', closeNav);
})();
</script>
</body>
</html>
