<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/entity_crud.php';

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

// Tužka-odkaz z textu pravidel rovnou do editace DB záznamu — jen pro
// nadpisy, co jsme spárovali s konkrétním řádkem (content/edit-links.json,
// vygenerováno jednorázově porovnáním nadpisů s DB), a jen když uživatel
// smí ten konkrétní záznam editovat (stejné pravidlo jako editor.php:
// dracak_can_edit_row — table-level právo + u row_owned tabulek i
// vlastnictví). Nikdy neposílat prohlížeči odkaz na záznam, který
// uživatel nesmí editovat, i kdyby ho jen skryl JS.
$allLinks = json_decode((string)file_get_contents(__DIR__ . '/content/edit-links.json'), true) ?: [];
$allEntities = require __DIR__ . '/includes/entities.php';
$byTable = [];
foreach ($allLinks as $hid => $link) {
    $byTable[$link['table']][$hid] = (int)$link['id'];
}

// Živé karty: nahrazují zmrzlý statický text u napojeného nadpisu aktuálním
// obsahem DB (viz dracak_render_pravidla_card) — pro každého čtenáře, co na
// tu sekci knihy vůbec dosáhne (viditelnost sekcí hlídá už výběr content/*
// souborů výš, ne tohle). Uprav v editoru -> hned se to projeví tady.
$liveCards = [];
foreach ($byTable as $table => $hidToId) {
    $config = $allEntities[$table] ?? null;
    if ($config === null) continue;
    foreach ($hidToId as $hid => $id) {
        $html = dracak_render_pravidla_card($table, $id, $config);
        if ($html !== null) $liveCards[$hid] = $html;
    }
}
$liveCardsJson = json_encode($liveCards, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

// Tužka-odkaz z textu pravidel rovnou do editace DB záznamu — jen když
// uživatel smí ten konkrétní záznam editovat (stejné pravidlo jako
// editor.php: dracak_can_edit_row — table-level právo + u row_owned tabulek
// i vlastnictví). Nikdy neposílat prohlížeči odkaz na záznam, který
// uživatel nesmí editovat, i kdyby ho jen skryl JS.
$editLinks = [];
if ($user !== null) {
    foreach ($byTable as $table => $hidToId) {
        $config = $allEntities[$table] ?? null;
        if ($config === null || !dracak_can_edit($user, $table)) continue;
        $rowOwned = !empty($config['row_owned']) && $user['role'] === 'hrac';
        $ownedIds = [];
        if ($rowOwned) {
            $ids = array_values(array_unique($hidToId));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = dracak_db()->prepare("SELECT id, created_by FROM `$table` WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $r) {
                if ((int)$r['created_by'] === (int)$user['id']) $ownedIds[(int)$r['id']] = true;
            }
        }
        foreach ($hidToId as $hid => $id) {
            if ($rowOwned && !isset($ownedIds[$id])) continue;
            $editLinks[$hid] = ['table' => $table, 'id' => $id];
        }
    }
}
$editLinksJson = json_encode($editLinks, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
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
      <p class="sidebar-title"><a href="<?= $user ? 'dashboard.php' : 'index.php' ?>" style="text-decoration:none;color:inherit;">🏠 Pravidla DrD + domácí pravidla</a></p>
      <p class="sidebar-sub">PPZ + PPP + PPE &middot; verze 1.6 + homebrew</p>

      <div class="auth-box">
        <?php if ($user): ?>
          <div class="auth-status">
            <span class="auth-name">Přihlášen: <strong><?= htmlspecialchars($user['jmeno'] ?: $user['email']) ?></strong> (<?= htmlspecialchars($user['role']) ?>)</span>
          </div>
          <div class="auth-actions">
            <a class="auth-db" href="dashboard.php">Dashboard →</a>
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
window.__EDIT_LINKS__ = <?= $editLinksJson ?>;
window.__LIVE_CARDS__ = <?= $liveCardsJson ?>;
</script>
<script src="assets/pravidla/pravidla.js"></script>
<script>
(function(){
  // Živé karty: nahradí zmrzlý statický text u napojeného nadpisu aktuálním
  // obsahem z DB (viz dracak_render_pravidla_card v entity_crud.php) — než
  // se přidá tužka, ať jde na přehledný nadpis nad aktuálním obsahem, ne
  // nad starým textem, co za chvíli zmizí.
  var cards = window.__LIVE_CARDS__ || {};
  Object.keys(cards).forEach(function(hid){
    var heading = document.getElementById(hid);
    if (!heading) return;
    var toRemove = [];
    document.querySelectorAll('[data-sec="' + hid + '"]').forEach(function(el){
      var wrap = el.closest('.table-wrap');
      var target = wrap || el;
      if (toRemove.indexOf(target) === -1) toRemove.push(target);
    });
    toRemove.forEach(function(el){ el.remove(); });
    heading.insertAdjacentHTML('afterend', cards[hid]);
  });

  var links = window.__EDIT_LINKS__ || {};
  Object.keys(links).forEach(function(hid){
    var el = document.getElementById(hid);
    if (!el) return;
    var link = links[hid];
    var a = document.createElement('a');
    a.className = 'edit-pencil';
    a.href = 'editor.php?tabulka=' + encodeURIComponent(link.table) + '&akce=edit&id=' + link.id;
    a.title = 'Upravit v databázi';
    a.setAttribute('aria-label', 'Upravit v databázi');
    a.textContent = '✎';
    el.appendChild(a);
  });
})();
</script>

</body></html>
