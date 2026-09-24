<?php
declare(strict_types=1);
// Jednorázové založení prvního admin účtu. Funguje JEN dokud je tabulka
// ucty prázdná — pak se sám zablokuje. Po použití tenhle soubor smaž z
// hostingu (FTP), ať tam zbytečně nevisí.
require_once __DIR__ . '/includes/db.php';

$pdo = dracak_db();
$count = (int)$pdo->query('SELECT COUNT(*) AS c FROM ucty')->fetch()['c'];

$error = null;
$done = false;

if ($count > 0) {
    $error = 'Účty už existují — tenhle formulář dál nefunguje. Nové účty zakládej v admin.php.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $jmeno = trim($_POST['jmeno'] ?? '');
    $heslo = (string)($_POST['heslo'] ?? '');
    if ($email === '' || $jmeno === '' || strlen($heslo) < 8) {
        $error = 'Vyplň e-mail, jméno a heslo (min. 8 znaků).';
    } else {
        $stmt = $pdo->prepare('INSERT INTO ucty (email, heslo_hash, jmeno, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, password_hash($heslo, PASSWORD_DEFAULT), $jmeno, 'admin']);
        $done = true;
    }
}
?>
<!doctype html>
<html lang="cs">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — první admin</title>
<link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<div class="centered">
  <div class="card">
    <h1>Založit prvního admina</h1>
    <?php if ($done): ?>
      <p class="note">Hotovo. Přihlas se v <a href="index.php">index.php</a> a pak tenhle soubor (setup-admin.php) smaž z hostingu.</p>
    <?php elseif ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <form method="post">
        <label>E-mail</label>
        <input type="email" name="email" required>
        <label>Jméno</label>
        <input type="text" name="jmeno" required>
        <label>Heslo (min. 8 znaků)</label>
        <input type="password" name="heslo" required minlength="8">
        <button class="btn-primary" type="submit">Založit</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
