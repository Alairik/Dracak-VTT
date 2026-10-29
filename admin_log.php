<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_menu.php';

// Chybový log (jen admin): co spadlo na serveru a co hlásí prohlížeče hráčů. Zápis a formát: includes/log.php.
// Smazání logu je POST s tokenem v relaci a přesměrování (obnovení stránky nesmaže nic znovu).

$user = dracak_require_role('admin');
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

if (empty($_SESSION['log_token'])) {
    $_SESSION['log_token'] = bin2hex(random_bytes(32));
}
$token = (string)$_SESSION['log_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['akce'] ?? '') === 'smazat' && hash_equals($token, dracak_text($_POST['token'] ?? ''))) {
        $_SESSION['log_vysledek'] = dracak_log_smaz() ? 'Log je prázdný.' : 'Log se nepodařilo smazat (nejde psát do složky logs/).';
    } else {
        $_SESSION['log_vysledek'] = 'Požadavek nemá platný token — načti stránku znovu a zkus to ještě jednou.';
    }
    header('Location: admin_log.php');
    exit;
}
$vysledek = $_SESSION['log_vysledek'] ?? null;
unset($_SESSION['log_vysledek']);

$druh = dracak_text($_GET['druh'] ?? '');
$druh = isset(DRACAK_LOG_DRUHY[$druh]) ? $druh : null;
$vse = dracak_log_nacti();
$log = $druh === null ? $vse : dracak_log_nacti($druh);
$pocty = [];
foreach ($vse['zaznamy'] as $z) {
    $pocty[$z['druh']] = ($pocty[$z['druh']] ?? 0) + $z['pocet'];
}
$zaznamy = array_slice($log['zaznamy'], 0, 300);

// Jména účtů k číslům v záznamech (jeden dotaz).
$jmena = [];
$ids = array_values(array_unique(array_filter(array_map(static fn(array $z): int => (int)($z['uzivatel'] ?? 0), $zaznamy))));
if ($ids) {
    $q = dracak_db()->prepare('SELECT id, jmeno FROM ucty WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $q->execute($ids);
    foreach ($q->fetchAll() as $r) {
        $jmena[(int)$r['id']] = (string)$r['jmeno'];
    }
}
$cas = static function (string $iso): string {
    try {
        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('Europe/Prague'))->format('j. n. H:i:s');
    } catch (Throwable $ex) {
        return $iso;
    }
};
$velikost = $log['velikost'] >= 1048576 ? number_format($log['velikost'] / 1048576, 1, ',', '') . ' MB' : number_format($log['velikost'] / 1024, 0, ',', '') . ' kB';
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — chybový log</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/organic.css?v=<?= filemtime(__DIR__ . '/assets/css/organic.css') ?>">
<style>
  .log-filtr { display:flex; flex-wrap:wrap; gap:6px; margin:0 0 12px; }
  .log-filtr a { text-decoration:none; }
  .log-filtr .tag { padding:4px 11px; font-size:12px; }
  .log-filtr a[aria-current="true"] .tag { background:var(--color-accent-100); color:var(--color-accent-800); font-weight:700; }
  .log-zprava { word-break:break-word; }
  .log-zprava details summary { cursor:pointer; }
  .log-zprava pre { white-space:pre-wrap; word-break:break-word; font-size:12px; margin:6px 0 0; }
  .log-druh-fatalni, .log-druh-vyjimka, .log-druh-db { background:#f6d9d3; color:#7a2a1a; }
  .log-druh-prohlizec { background:var(--color-accent-2-100); color:var(--color-accent-2-800); }
</style>
</head>
<body>
<button class="menu-btn" id="menuBtn" type="button" aria-label="Otevřít navigaci">☰</button>
<div class="backdrop" id="backdrop"></div>
<div class="shell">
  <?php dracak_admin_menu('log', $user); ?>

  <main class="main">
    <h1 class="page-title">Chybový log</h1>

    <?php if ($vysledek !== null): ?>
      <div class="card elev-sm" style="max-width:960px; margin:16px 0;" role="status"><?= $e($vysledek) ?></div>
    <?php endif; ?>

    <div class="card elev-sm" style="max-width:960px; margin:16px 0;">
      <?php if (!$vse['existuje'] || !$vse['zaznamy']): ?>
        <h3 style="font-size:16px;margin-bottom:8px;">Žádné chyby</h3>
        <p class="note">Server ani prohlížeče zatím nic nenahlásily. Když něco spadne, objeví se to tady — i s tím, na jaké stránce a u koho.</p>
      <?php else: ?>
        <div class="log-filtr" role="navigation" aria-label="Druh chyby">
          <a href="admin_log.php" <?= $druh === null ? 'aria-current="true"' : '' ?>><span class="tag">Vše (<?= array_sum($pocty) ?>)</span></a>
          <?php foreach (DRACAK_LOG_DRUHY as $kod => $nazev): if (empty($pocty[$kod])) { continue; } ?>
            <a href="admin_log.php?druh=<?= $e($kod) ?>" <?= $druh === $kod ? 'aria-current="true"' : '' ?>><span class="tag"><?= $e($nazev) ?> (<?= (int)$pocty[$kod] ?>)</span></a>
          <?php endforeach; ?>
        </div>
        <p class="note" style="margin:0 0 10px;">Nejnovější nahoře; stejná chyba na stejném místě je sloučená („×N“). Log zabírá <?= $e($velikost) ?><?= $log['velikost'] >= DRACAK_LOG_MAX_BAJTU ? ' (starší záznamy se časem mažou)' : '' ?>.</p>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Kdy</th><th>Druh</th><th>Co se stalo</th><th>Kde</th><th>Kdo</th></tr></thead>
            <tbody>
              <?php foreach ($zaznamy as $z):
                  $misto = isset($z['soubor']) ? $z['soubor'] . (isset($z['radek']) ? ':' . $z['radek'] : '') : ($z['misto'] ?? '');
                  $detail = [];
                  if (!empty($z['adresa'])) { $detail[] = 'Požadavek: ' . $z['adresa']; }
                  if (!empty($z['stranka'])) { $detail[] = 'Stránka: ' . $z['stranka']; }
                  if (!empty($z['ua'])) { $detail[] = 'Prohlížeč: ' . $z['ua']; }
                  foreach ($z['stopa'] ?? [] as $r) { $detail[] = '  ' . $r; }
                  if ($z['pocet'] > 1) { array_unshift($detail, 'Poprvé v přečtené části: ' . $cas($z['prvni'])); }
              ?>
                <tr>
                  <td style="white-space:nowrap;"><?= $e($cas($z['t'])) ?><?= $z['pocet'] > 1 ? '<br><span class="note">×' . (int)$z['pocet'] . '</span>' : '' ?></td>
                  <td><span class="tag log-druh-<?= $e($z['druh']) ?>"><?= $e(DRACAK_LOG_DRUHY[$z['druh']] ?? $z['druh']) ?></span></td>
                  <td class="log-zprava">
                    <?php if ($detail): ?>
                      <details><summary><?= $e($z['zprava']) ?></summary><pre><?= $e(implode("\n", $detail)) ?></pre></details>
                    <?php else: ?><?= $e($z['zprava']) ?><?php endif; ?>
                  </td>
                  <td><code><?= $e($misto) ?></code></td>
                  <td><?= isset($z['uzivatel']) ? $e($jmena[(int)$z['uzivatel']] ?? ('účet ' . (int)$z['uzivatel'])) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($log['zaznamy']) > count($zaznamy)): ?>
          <p class="note">Zobrazeno nejnovějších <?= count($zaznamy) ?> z <?= count($log['zaznamy']) ?>.</p>
        <?php endif; ?>
        <form method="post" style="margin-top:14px;" onsubmit="return confirm('Smazat celý chybový log?');">
          <input type="hidden" name="akce" value="smazat">
          <input type="hidden" name="token" value="<?= $e($token) ?>">
          <button class="btn btn-secondary" type="submit">Vymazat log</button>
        </form>
      <?php endif; ?>
    </div>
    <p class="note" style="max-width:960px;">Zapisují se neošetřené chyby na serveru, chyby databáze, varování a fatální chyby PHP a chyby JavaScriptu v prohlížeči přihlášených hráčů.
      Očekávané hlášky pro hráče („Na tahu je jiný tvor“ apod.) sem nepatří. Hesla ani tokeny v adresách se nezapisují.</p>
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
