<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_menu.php';
require_once __DIR__ . '/includes/zaloha.php';

// Záloha databáze (jen admin). Soubor SQL se stáhne do počítače; co obsahuje a jak se vrací: includes/zaloha.php, docs/zalohy.md.
// Stažení je POST s tokenem v relaci (GET jen ukáže stránku), takže ho nespustí odkaz z cizího webu ani náhodné načtení.

$user = dracak_require_role('admin');
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

if (empty($_SESSION['zaloha_token'])) {
    $_SESSION['zaloha_token'] = bin2hex(random_bytes(32));
}
$token = (string)$_SESSION['zaloha_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['akce'] ?? '') !== 'stahnout' || !hash_equals($token, dracak_text($_POST['token'] ?? ''))) {
        http_response_code(403);
        die('Požadavek nemá platný token — načti stránku znovu a zkus to ještě jednou.');
    }
    session_write_close();           // dlouhé stahování nesmí držet zámek relace
    @set_time_limit(300);
    ignore_user_abort(false);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="dracak-zaloha-' . date('Y-m-d-Hi') . '.sql"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    try {
        $v = dracak_zaloha_proud(dracak_db(), static function (string $cast): void {
            echo $cast;
            flush();
        });
        error_log('[dracak-vtt] záloha databáze stažena (' . $user['email'] . '): ' . $v['tabulek'] . ' tabulek, ' . $v['radku'] . ' řádků');
    } catch (Throwable $ex) {
        // Hlavičky už odešly; konec souboru bez řádku „Konec zálohy“ prozradí, že je neúplný.
        dracak_log_vyjimka($ex);
        echo "\n-- CHYBA: záloha se nedokončila, soubor je neúplný. Zkus to znovu.\n";
    }
    exit;
}

$tabulek = count(dracak_zaloha_tabulky(dracak_db()));
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — záloha databáze</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/organic.css?v=<?= filemtime(__DIR__ . '/assets/css/organic.css') ?>">
</head>
<body>
<button class="menu-btn" id="menuBtn" type="button" aria-label="Otevřít navigaci">☰</button>
<div class="backdrop" id="backdrop"></div>
<div class="shell">
  <?php dracak_admin_menu('zaloha', $user); ?>

  <main class="main">
    <h1 class="page-title">Záloha databáze</h1>

    <div class="card elev-sm" style="max-width:760px; margin:16px 0;">
      <h3 style="font-size:16px;margin-bottom:8px;">Stáhnout zálohu</h3>
      <p>Uloží do tvého počítače jeden soubor s <strong>celou databází</strong> (<?= $tabulek ?> tabulek): postavy a jejich výbavu, světy, mapy, tokeny, průběh souborů, účty.
        Dělej ji <strong>před každou hrou</strong> a po ní, nebo aspoň jednou týdně — jde o jediná data, která se z projektu obnovit nedají.</p>
      <form method="post">
        <input type="hidden" name="akce" value="stahnout">
        <input type="hidden" name="token" value="<?= $e($token) ?>">
        <button class="btn btn-primary" type="submit">Stáhnout zálohu (.sql)</button>
      </form>
      <p class="note" style="margin-top:12px;">Soubor musí na konci obsahovat řádek <code>-- Konec zálohy: …</code> — bez něj se stahování přerušilo a záloha je neúplná.
        Obsahuje i otisky (hashe) hesel účtů, proto ho nikomu neposílej a neukládej na veřejné místo.</p>
    </div>

    <div class="card elev-sm" style="max-width:760px; margin:16px 0;">
      <h3 style="font-size:16px;margin-bottom:8px;">Co záloha neobsahuje</h3>
      <ul style="margin:0 0 0 18px;">
        <li><strong>Nahrané obrázky</strong> (mapy, portréty tokenů, složka <code>uploads/</code>) — stáhni je jednou za čas přes FTP.</li>
        <li><strong>Soubory aplikace</strong> — ty jsou v GitHubu a nasazení je vždy nahraje znovu.</li>
      </ul>
    </div>

    <div class="card elev-sm" style="max-width:760px; margin:16px 0;">
      <h3 style="font-size:16px;margin-bottom:8px;">Jak se záloha vrací</h3>
      <ol style="margin:0 0 0 18px;">
        <li>V administraci hostingu založ <strong>prázdnou</strong> databázi (nebo stávající vyprázdni).</li>
        <li>Otevři ji v phpMyAdminu pod <strong>admin účtem</strong> (ne webovým) a vyber <em>Import</em> → soubor se zálohou.</li>
        <li>Do <code>config.php</code> na hostingu dej údaje nové databáze, nebo řekni Claudovi, ať s tím pomůže.</li>
      </ol>
      <p class="note">Záloha záměrně nic nemaže: když ji pustíš do databáze, kde už data jsou, skončí chybou a nic nepřepíše. Kdyby to bylo potřeba, projdi postup s Claudem.</p>
    </div>
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
