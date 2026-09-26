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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/organic.css">
</head>
<body>
<div class="centered">
  <div class="card elev-md">
    <h1>🐉 Dračák VTT</h1>
    <form method="post">
      <div class="field">
        <label for="email">E-mail</label>
        <input class="input" id="email" name="email" type="email" required autocomplete="username">
      </div>
      <div class="field">
        <label for="password">Heslo</label>
        <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-primary" type="submit" style="width:100%;margin-top:6px;">Přihlásit se</button>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    </form>
    <p class="note" style="margin-top:18px;">Účty zakládá administrátor. Pokud nemáš přístup, ozvi se PJ.</p>
    <p class="note"><a href="pravidla.php">Přečíst si pravidla DrD + domácí pravidla →</a></p>
  </div>
</div>
</body>
</html>
