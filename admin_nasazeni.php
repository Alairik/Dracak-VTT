<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nasazeni.php';

// Kontrola nasazení (jen admin): sedí soubory na serveru s tím, co deploy nahrával, nic nechybí ani nepřebývá,
// a jsou zavřené cesty, které mají být zavřené. Důvod a princip: includes/nasazeni.php.

$user = dracak_require_role('admin');
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

$kontrola = dracak_nasazeni_kontrola();
$cesty = null;
if (($_GET['cesty'] ?? '') === '1') {
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $cesty = dracak_nasazeni_zavrene_cesty((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''), $https);
}

$problemy = count($kontrola['chybi']) + count($kontrola['jine']) + count($kontrola['navic']) + count($kontrola['uploads_spustitelne']);
$m = $kontrola['manifest'];
$kdy = '';
if ($m && $m['vygenerovano'] !== '') {
    try {
        $kdy = (new DateTimeImmutable($m['vygenerovano']))->setTimezone(new DateTimeZone('Europe/Prague'))->format('j. n. Y H:i');
    } catch (Throwable $ex) {
        $kdy = $m['vygenerovano'];
    }
}
$seznam = static function (array $polozky) use ($e): string {
    $html = '<ul style="margin:6px 0 0 18px;font-size:13px;">';
    foreach (array_slice($polozky, 0, DRACAK_NASAZENI_MAX_VYPIS) as $p) {
        $html .= '<li><code>' . $e($p) . '</code></li>';
    }
    if (count($polozky) > DRACAK_NASAZENI_MAX_VYPIS) {
        $html .= '<li>… a dalších ' . (count($polozky) - DRACAK_NASAZENI_MAX_VYPIS) . '</li>';
    }
    return $html . '</ul>';
};
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — kontrola nasazení</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/organic.css?v=<?= filemtime(__DIR__ . '/assets/css/organic.css') ?>">
</head>
<body>
<button class="menu-btn" id="menuBtn" type="button" aria-label="Otevřít navigaci">☰</button>
<div class="backdrop" id="backdrop"></div>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php" style="text-decoration:none;color:inherit;">🏠 Dračák VTT</a>
    <div class="sidebar-user"><?= $e($user['jmeno']) ?><span class="role-badge">admin</span></div>
    <a class="mock-item" href="dashboard.php">🏠 Dashboard</a>
    <a class="mock-item" href="admin.php">Správa účtů</a>
    <a class="mock-item" href="admin_migrace.php">Změny databáze</a>
    <a class="mock-item active" href="admin_nasazeni.php">Kontrola nasazení</a>
    <a class="mock-item" href="editor.php">← Zpět do editoru</a>
    <div class="sidebar-bottom">
      <form method="post" action="logout.php"><button class="btn btn-ghost" style="width:100%;">Odhlásit se</button></form>
    </div>
  </aside>

  <main class="main">
    <h1 class="page-title">Kontrola nasazení</h1>

    <div class="card elev-sm" style="max-width:760px; margin:16px 0;">
      <h3 style="font-size:16px;margin-bottom:8px;">Soubory na serveru</h3>
      <?php if ($kontrola['chyba'] !== null): ?>
        <p class="note"><?= $e($kontrola['chyba']) ?></p>
      <?php else: ?>
        <p>
          <?php if ($problemy === 0): ?>
            <strong>Všech <?= (int)$kontrola['ok'] ?> souborů sedí</strong> s tím, co deploy nahrával, a nic nepřebývá.
          <?php else: ?>
            <strong>Něco nesedí</strong> (<?= (int)$kontrola['ok'] ?> souborů je v pořádku).
          <?php endif; ?>
        </p>
        <p class="note">Nasazení z <?= $kdy !== '' ? $e($kdy) : 'neznámého času' ?>, verze <code><?= $e(substr($m['commit'], 0, 8)) ?></code>, <?= (int)$m['pocet'] ?> souborů v seznamu.</p>

        <?php if ($kontrola['chybi']): ?>
          <h4 style="margin:12px 0 0;">Chybí (<?= count($kontrola['chybi']) ?>) — FTP je nenahrálo</h4>
          <?= $seznam($kontrola['chybi']) ?>
          <p class="note">Nasazení nahrává jen změněné soubory, takže další deploy chybějící soubor sám nedoplní. Řekni Claudovi (udělá drobnou změnu právě v těch souborech, aby se nahrály znovu), nebo je nahraj ručně přes FTP.</p>
        <?php endif; ?>
        <?php if ($kontrola['jine']): ?>
          <h4 style="margin:12px 0 0;">Jiný obsah než při nasazení (<?= count($kontrola['jine']) ?>)</h4>
          <?= $seznam($kontrola['jine']) ?>
          <p class="note">Nahrálo se jen částečně, nebo soubor někdo změnil přímo na serveru. Jestli změnu neznáš, ber to jako podezřelé.</p>
        <?php endif; ?>
        <?php if ($kontrola['navic']): ?>
          <h4 style="margin:12px 0 0;">Na serveru navíc (<?= count($kontrola['navic']) ?>)</h4>
          <?= $seznam($kontrola['navic']) ?>
          <p class="note">Soubory, které nejsou v repu: zbytek po starém nasazení (smazat), nebo cizí kód. <code>setup-admin.php</code> patří na server jen po dobu zakládání prvního účtu — pak ho smaž.</p>
        <?php endif; ?>
        <?php if ($kontrola['uploads_spustitelne']): ?>
          <h4 style="margin:12px 0 0;color:var(--color-danger,#a3402a);">Spustitelné soubory ve složce nahraných dat (<?= count($kontrola['uploads_spustitelne']) ?>)</h4>
          <?= $seznam($kontrola['uploads_spustitelne']) ?>
          <p class="note"><strong>Smaž je.</strong> Do <code>uploads/</code> patří jen obrázky; PHP nebo jiný skript tam znamená, že se někdo dostal dovnitř.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="card elev-sm" style="max-width:760px; margin:16px 0;">
      <h3 style="font-size:16px;margin-bottom:8px;">Zavřené cesty a hlavičky</h3>
      <?php if ($cesty === null): ?>
        <p class="note">Server si zkusí vyžádat vlastní adresy, které musí být zvenku zavřené (konfigurace, složky s daty), a zkontroluje bezpečnostní hlavičky.</p>
        <a class="btn btn-secondary" href="admin_nasazeni.php?cesty=1">Zkontrolovat</a>
      <?php elseif ($cesty['chyba'] !== null): ?>
        <p class="note"><?= $e($cesty['chyba']) ?></p>
        <p class="note">Zkus to z prohlížeče: otevři <code>/config.php</code>, <code>/includes/auth.php</code> a <code>/deploy-manifest.json</code> — všude musí být „Forbidden“ nebo „Not Found“.</p>
      <?php else: ?>
        <p class="note">Ověřeno z adresy <code><?= $e($cesty['zakladna']) ?></code>.</p>
        <table class="dn-tab" style="width:100%;font-size:13px;">
          <thead><tr><th style="text-align:left;">Adresa</th><th style="text-align:left;">Kód</th><th style="text-align:left;">Výsledek</th></tr></thead>
          <tbody>
            <?php foreach ($cesty['cesty'] as [$cesta, $kod, $ok, $pozn]): ?>
              <tr>
                <td><code>/<?= $e($cesta) ?></code></td>
                <td><?= $kod !== null ? (int)$kod : '—' ?></td>
                <td style="<?= $ok === false ? 'color:var(--color-danger,#a3402a);font-weight:700;' : '' ?>"><?= $ok === true ? '✓ ' : ($ok === false ? '✗ ' : '') ?><?= $e($pozn) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php foreach ($cesty['hlavicky'] as [$nazev, $ok, $pozn]): ?>
              <tr>
                <td>hlavička <code><?= $e($nazev) ?></code></td>
                <td></td>
                <td style="<?= $ok ? '' : 'color:var(--color-danger,#a3402a);font-weight:700;' ?>"><?= $ok ? '✓ ' : '✗ ' ?><?= $e($pozn) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="note" style="margin-top:10px;"><a href="admin_nasazeni.php?cesty=1">Zkontrolovat znovu</a></p>
      <?php endif; ?>
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
