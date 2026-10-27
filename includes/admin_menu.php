<?php
declare(strict_types=1);

// Postranní menu administrace — jedno místo pro všechny stránky admin*.php, ať přidání nové stránky není pět úprav.
// $aktivni = klíč položky, která je právě otevřená.
const DRACAK_ADMIN_POLOZKY = [
    'ucty' => ['admin.php', 'Správa účtů'],
    'migrace' => ['admin_migrace.php', 'Změny databáze'],
    'nasazeni' => ['admin_nasazeni.php', 'Kontrola nasazení'],
    'log' => ['admin_log.php', 'Chybový log'],
    'zaloha' => ['admin_zaloha.php', 'Záloha databáze'],
];

function dracak_admin_menu(string $aktivni, array $user): void
{
    $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
    ?>
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php" style="text-decoration:none;color:inherit;">🏠 Dračák VTT</a>
    <div class="sidebar-user"><?= $e($user['jmeno']) ?><span class="role-badge">admin</span></div>
    <a class="mock-item" href="dashboard.php">🏠 Dashboard</a>
    <?php foreach (DRACAK_ADMIN_POLOZKY as $klic => [$adresa, $nazev]): ?>
    <a class="mock-item<?= $klic === $aktivni ? ' active' : '' ?>" href="<?= $e($adresa) ?>"><?= $e($nazev) ?></a>
    <?php endforeach; ?>
    <a class="mock-item" href="editor.php">← Zpět do editoru</a>
    <div class="sidebar-bottom">
      <form method="post" action="logout.php"><button class="btn btn-ghost" style="width:100%;">Odhlásit se</button></form>
    </div>
  </aside>
    <?php
}
