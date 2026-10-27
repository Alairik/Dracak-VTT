<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_menu.php';
require_once __DIR__ . '/includes/migrace.php';

// Změny databáze z administrace (jen admin). Nahrazuje scripts/migrate.php, který po nasazení volal GitHub přes HTTP
// s tajným tokenem — hosting volání z GitHubu od 22. 10. 2026 odmítá. Co a proč se tu spouští: includes/migrace.php.
// Spuštění je POST s tokenem v relaci (kromě obrany proti cizímu webu v auth.php) a po něm přesměrování, ať obnovení
// stránky nespustí nic znovu. Z požadavku se do SQL nedostane nic — spouští se jen soubory ze složky database/migrations/.

$user = dracak_require_role('admin');
$pdo = dracak_db();

if (empty($_SESSION['migrace_token'])) {
    $_SESSION['migrace_token'] = bin2hex(random_bytes(32));
}
$token = (string)$_SESSION['migrace_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dodany = (string)($_POST['token'] ?? '');
    if (($_POST['akce'] ?? '') === 'spustit' && hash_equals($token, $dodany)) {
        $v = dracak_migrace_spust($pdo);
        error_log('[dracak-vtt] migrace z administrace (' . $user['email'] . '): spuštěno ' . $v['spusteno']
            . ($v['chyba'] !== null ? ', chyba: ' . $v['chyba'] : ', bez chyby'));
        $_SESSION['migrace_vysledek'] = $v;
    } else {
        $_SESSION['migrace_vysledek'] = ['radky' => [], 'spusteno' => 0, 'chyba' => 'Požadavek nemá platný token — načti stránku znovu a zkus to ještě jednou.'];
    }
    header('Location: admin_migrace.php');
    exit;
}

$vysledek = $_SESSION['migrace_vysledek'] ?? null;
unset($_SESSION['migrace_vysledek']);

$stav = dracak_migrace_stav($pdo);
$cekajici = array_values(array_filter($stav['soubory'], static fn(array $s): bool => !$s['pouzito']));
$hotove = array_values(array_filter($stav['soubory'], static fn(array $s): bool => $s['pouzito']));
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — změny databáze</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/organic.css?v=<?= filemtime(__DIR__ . '/assets/css/organic.css') ?>">
</head>
<body>
<button class="menu-btn" id="menuBtn" type="button" aria-label="Otevřít navigaci">☰</button>
<div class="backdrop" id="backdrop"></div>
<div class="shell">
  <?php dracak_admin_menu('migrace', $user); ?>

  <main class="main">
    <h1 class="page-title">Změny databáze</h1>

    <?php if ($vysledek !== null): ?>
      <div class="card elev-sm" style="max-width:760px; margin:16px 0;" role="status">
        <h3 style="font-size:16px;margin-bottom:8px;">
          <?= $vysledek['chyba'] !== null ? 'Něco se nepovedlo' : ($vysledek['spusteno'] > 0 ? 'Hotovo' : 'Není co spouštět') ?>
        </h3>
        <?php if ($vysledek['radky']): ?><pre style="white-space:pre-wrap;font-size:13px;margin:0 0 8px;"><?= $e(implode("\n", $vysledek['radky'])) ?></pre><?php endif; ?>
        <?php if ($vysledek['chyba'] !== null): ?><p class="error"><?= $e($vysledek['chyba']) ?></p><?php endif; ?>
        <?php if ($vysledek['chyba'] === null && $vysledek['spusteno'] > 0): ?><p class="note">Proběhl<?= $vysledek['spusteno'] === 1 ? 'a' : 'y' ?> <?= (int)$vysledek['spusteno'] ?> <?= $vysledek['spusteno'] === 1 ? 'změna' : ($vysledek['spusteno'] <= 4 ? 'změny' : 'změn') ?>.</p><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!$stav['log_ok']): ?>
      <div class="card elev-sm" style="max-width:760px; margin:16px 0;">
        <h3 style="font-size:16px;margin-bottom:8px;">Chybí evidence proběhlých změn</h3>
        <p class="note">Tabulku <code>migrace_log</code> web účet založit nesmí. Spusť ručně v phpMyAdminu pod admin účtem:</p>
        <pre style="white-space:pre-wrap;font-size:13px;user-select:all;"><?= $e(dracak_migrace_sql_log()) ?></pre>
      </div>
    <?php else: ?>
      <div class="card elev-sm" style="max-width:760px; margin:16px 0;">
        <?php if (!$cekajici): ?>
          <h3 style="font-size:16px;margin-bottom:8px;">Všechno je aplikované</h3>
          <p class="note">Poslední změna: <?= $hotove ? $e(end($hotove)['soubor']) : '—' ?>. Nové změny se tu objeví po dalším nasazení.</p>
        <?php else: ?>
          <h3 style="font-size:16px;margin-bottom:8px;">Čeká <?= count($cekajici) ?> <?= count($cekajici) === 1 ? 'změna' : (count($cekajici) <= 4 ? 'změny' : 'změn') ?></h3>
          <ul style="margin:0 0 12px 18px;">
            <?php foreach ($cekajici as $s): ?>
              <li><code><?= $e($s['soubor']) ?></code> —
                <?php if ($s['struktura']): ?><strong>mění strukturu</strong> (nové tabulky nebo sloupce): spustí se jen tehdy, když ji už provedl ruční SQL, jinak se zastaví a řekne, že ho potřebuje
                <?php else: ?>jen data<?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
          <form method="post" onsubmit="return confirm('Spustit čekající změny databáze (<?= count($cekajici) ?>)? Provedou se popořadě a při první chybě se zastaví.');">
            <input type="hidden" name="akce" value="spustit">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <button class="btn btn-primary" type="submit">Spustit čekající změny</button>
          </form>
        <?php endif; ?>
        <p class="note" style="margin-top:14px;">Tahle stránka pouští změny dat. Nové tabulky a sloupce web účet nesmí vytvořit — ty se dál pouští ručně v phpMyAdminu
          (SQL pošle Claude); stránka je pozná, když už jsou hotové, a zapíše si je. Soubory se zakázaným příkazem (DROP, TRUNCATE) se nespustí vůbec.</p>
      </div>

      <details style="max-width:760px; margin:16px 0;">
        <summary>Už aplikované změny (<?= count($hotove) ?>)</summary>
        <div class="table-wrap"><table class="table" style="margin-top:8px;">
          <thead><tr><th style="text-align:left;">Soubor</th><th style="text-align:left;">Kdy</th></tr></thead>
          <tbody>
            <?php foreach (array_reverse($hotove) as $s): ?>
              <tr><td><code><?= $e($s['soubor']) ?></code></td><td><?= $e($s['kdy']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </details>
    <?php endif; ?>
  </main>
</div>
<script>
(function () {
  var b = document.getElementById('menuBtn'), s = document.getElementById('sidebar'), k = document.getElementById('backdrop');
  if (!b || !s || !k) return;
  function t(o) { s.classList.toggle('open', o); k.classList.toggle('show', o); }
  b.addEventListener('click', function () { t(!s.classList.contains('open')); });
  k.addEventListener('click', function () { t(false); });
})();
</script>
</body>
</html>
