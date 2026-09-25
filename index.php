<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

dracak_session_start();
if (dracak_current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (dracak_login($email, $password)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Nesprávný e-mail nebo heslo.';
}
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — přihlášení</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="centered">
  <div class="card">
    <h1>Dračák VTT</h1>
    <form method="post">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required autocomplete="username">
      <label for="password">Heslo</label>
      <input id="password" name="password" type="password" required autocomplete="current-password">
      <button class="btn-primary" type="submit">Přihlásit se</button>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    </form>
    <p class="note">Účty zakládá administrátor. Pokud nemáš přístup, ozvi se PJ.</p>
    <p class="note"><a href="pravidla.php">Přečíst si pravidla DrD + domácí pravidla →</a></p>
  </div>
</div>
</body>
</html>
