<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

dracak_session_start();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (dracak_login($email, $password)) {
        header('Location: pravidla.php');
        exit;
    }
    $error = 'Nesprávný e-mail nebo heslo.';
}

$user = dracak_current_user();
$canSeePjBestiar = $user !== null && in_array($user['role'], ['pj', 'admin'], true);

// PJ pravidla a bestiář se pošlou prohlížeči jen přihlášeným pj/admin účtům —
// stejné pravidlo jako v editor.php, vynucené tady na serveru, ne jen v JS.
$headings = json_decode((string)file_get_contents(__DIR__ . '/content/headings.json'), true) ?: [];
if (!$canSeePjBestiar) {
    $headings = array_filter($headings, function ($h) {
        return ($h['g'] ?? '') === 'hrac';
    });
}
$headingsJson = json_encode($headings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<!doctype html><html><head><meta charset=utf8><meta name=viewport content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Pravidla DrD + domácí pravidla</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/pravidla/pravidla.css">
</head><body>

<button class="menu-btn" id="menuBtn" type="button" aria-label="Otevřít navigaci">☰</button>
<div class="backdrop" id="backdrop"></div>

<div class="app variant-hb">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
      <p class="sidebar-title">Pravidla DrD + domácí pravidla</p>
      <p class="sidebar-sub">PPZ + PPP + PPE &middot; verze 1.6 + homebrew</p>

      <div class="auth-box">
        <?php if ($user): ?>
          <div class="auth-status">
            <span class="auth-name">Přihlášen: <strong><?= htmlspecialchars($user['jmeno'] ?: $user['email']) ?></strong> (<?= htmlspecialchars($user['role']) ?>)</span>
          </div>
          <div class="auth-actions">
            <a class="auth-db" href="editor.php">Otevřít databázi →</a>
            <a href="logout.php">Odhlásit se</a>
          </div>
        <?php else: ?>
          <form method="post" class="auth-form">
            <input type="email" name="email" placeholder="E-mail" required autocomplete="username">
            <input type="password" name="password" placeholder="Heslo" required autocomplete="current-password">
            <button type="submit">Přihlásit se</button>
          </form>
          <?php if ($error): ?><p class="auth-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
          <p class="auth-hint">Přihlášení odemkne databázi pravidel a (podle role) i pravidla pro PJ a bestiář.</p>
        <?php endif; ?>
      </div>

      <div class="part-tabs" id="partTabs" role="tablist" aria-label="Část pravidel">
        <button type="button" class="part-tab active" data-part="hrac" role="tab" aria-selected="true">Hráč</button>
        <?php if ($canSeePjBestiar): ?>
        <button type="button" class="part-tab" data-part="pj" role="tab" aria-selected="false">Pán jeskyně</button>
        <button type="button" class="part-tab" data-part="bestiar" role="tab" aria-selected="false">Bestiář</button>
        <?php endif; ?>
      </div>

      <div class="search-field" id="headField">
        <input id="headSearch" type="text" placeholder="Najít nadpis…" autocomplete="off" aria-label="Vyhledat nadpis">
        <span class="field-icon">⌕</span>
        <button class="field-clear" id="headClear" type="button" aria-label="Vymazat">×</button>
        <ul class="head-results" id="headResults"></ul>
      </div>
      <p class="search-hint">Rychlý skok na nadpis kapitoly, schopnosti nebo nestvůry.</p>

      <div class="search-field" id="fullField">
        <input id="fullSearch" type="text" placeholder="Hledat v celém textu… ( / )" autocomplete="off" aria-label="Hledat v textu pravidel">
        <span class="field-icon">⌕</span>
        <button class="field-clear" id="fullClear" type="button" aria-label="Vymazat">×</button>
      </div>
      <div class="scope-row" id="scopeRow" aria-label="Rozsah hledání">
        <span class="scope-label">Hledat v:</span>
        <button type="button" class="scope-chip active" data-scope="all">vše</button>
        <button type="button" class="scope-chip" data-scope="current">jen aktuální části</button>
        <button type="button" class="scope-chip" data-scope="nohb">bez domácích</button>
      </div>
    </div>

    <div class="sidebar-body">
      <div id="tocView">
<?php
readfile(__DIR__ . '/content/toc-hrac.html');
if ($canSeePjBestiar) {
    readfile(__DIR__ . '/content/toc-pj.html');
    readfile(__DIR__ . '/content/toc-bestiar.html');
}
?>
      </div>
      <div class="full-results-wrap" id="fullResultsWrap">
        <div class="full-results-head">
          <span>Výsledky hledání</span>
          <button id="fullClose" type="button">← zpět na obsah</button>
        </div>
        <ul class="full-results" id="fullResults"></ul>
      </div>
    </div>
  </aside>

  <div class="main-col">
    <main class="content" id="content">
<?php
readfile(__DIR__ . '/content/pravidla-hrac.html');
if ($canSeePjBestiar) {
    readfile(__DIR__ . '/content/pravidla-pj.html');
    readfile(__DIR__ . '/content/pravidla-bestiar.html');
}
?>
    </main>
  </div>
</div>

<button class="top-btn" id="topBtn" type="button" aria-label="Nahoru">↑</button>

<script>
window.__HEADINGS__ = <?= $headingsJson ?>;
</script>
<script src="assets/pravidla/pravidla.js"></script>

</body></html>
