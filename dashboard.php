<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = dracak_require_login();
$isAdmin = $user['role'] === 'admin';
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/organic.css">
</head>
<body>
<div class="dash-shell">
  <div class="dash-header">
    <div>
      <div class="dash-brand">🐉 Dračák VTT</div>
      <div class="dash-user">Přihlášen: <strong><?= htmlspecialchars($user['jmeno']) ?></strong><span class="role-badge"><?= htmlspecialchars($user['role']) ?></span></div>
    </div>
    <form method="post" action="logout.php"><button class="btn btn-secondary" type="submit">Odhlásit se</button></form>
  </div>

  <div class="tile-grid">
    <span class="tile tile-soon" aria-disabled="true">
      <div class="tile-icon">🧙</div>
      <div class="tile-title">Postavy</div>
      <div class="tile-desc">Výběr a správa vlastních postav.</div>
      <span class="tile-soon-badge">Připravujeme</span>
    </span>

    <span class="tile tile-soon" aria-disabled="true">
      <div class="tile-icon">🗺️</div>
      <div class="tile-title">Dobrodružství</div>
      <div class="tile-desc">Výběr aktivní kampaně/dobrodružství.</div>
      <span class="tile-soon-badge">Připravujeme</span>
    </span>

    <a class="tile" href="pravidla.php">
      <div class="tile-icon">📖</div>
      <div class="tile-title">Pravidla</div>
      <div class="tile-desc">Hráčská pravidla<?= $user['role'] !== 'hrac' ? ', PJ pravidla a bestiář' : '' ?>.</div>
    </a>

    <a class="tile" href="editor.php">
      <div class="tile-icon">🗂️</div>
      <div class="tile-title">Databáze pravidel</div>
      <div class="tile-desc">Editor kouzel, povolání, vybavení a dalšího obsahu.</div>
    </a>

    <a class="tile" href="mapa.html">
      <div class="tile-icon">🧭</div>
      <div class="tile-title">Mapa světa</div>
      <div class="tile-desc">Mapa přenesená z Torch.</div>
    </a>

    <?php if ($isAdmin): ?>
    <a class="tile" href="admin.php">
      <div class="tile-icon">⚙️</div>
      <div class="tile-title">Správa účtů</div>
      <div class="tile-desc">Zakládání účtů, role, oprávnění.</div>
    </a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
