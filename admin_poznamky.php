<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_menu.php';
require_once __DIR__ . '/includes/vtt_poznamky.php';

// Poznámky všech světů na jednom místě (jen admin) — bezpečnostní dohled, o kterém mluví includes/vtt_poznamky.php: skutečný admin vidí
// všechno bez ohledu na sdílení, PJ světa ne. Jen ke čtení; smazat poznámku jde ze světa (hra/svet.php), stejně jako dřív.
// Filtr: svět, štítek a autor (GET). Texty se ukazují celé, nejnovější nahoře, nejvýš 300 záznamů.

$user = dracak_require_role('admin');
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$pdo = dracak_db();
$roz = dracak_vtt_poznamky_funkce($pdo)['rozsireno'];

$svetFiltr = max(0, (int)($_GET['svet'] ?? 0));
$autorFiltr = max(0, (int)($_GET['autor'] ?? 0));
$stitekFiltr = $roz && isset(DRACAK_VTT_POZNAMKY_STITKY[dracak_text($_GET['stitek'] ?? '')]) ? dracak_text($_GET['stitek'] ?? '') : null;

$svety = $pdo->query('SELECT id, nazev FROM svet ORDER BY nazev')->fetchAll();
$autori = $pdo->query('SELECT DISTINCT u.id, u.jmeno FROM ucty u JOIN svet_poznamky p ON p.autor_ucet_id = u.id ORDER BY u.jmeno')->fetchAll();

$kde = [];
$params = [];
if ($svetFiltr > 0) { $kde[] = 'p.svet_id = ?'; $params[] = $svetFiltr; }
if ($autorFiltr > 0) { $kde[] = 'p.autor_ucet_id = ?'; $params[] = $autorFiltr; }
if ($stitekFiltr !== null) { $kde[] = 'p.stitek = ?'; $params[] = $stitekFiltr; }
$sql = 'SELECT p.*, s.nazev AS svet_nazev, u.jmeno AS autor_jmeno, m.nazev AS mapa_nazev' . ($roz ? ', pv.nazev AS postava_nazev' : '') . '
        FROM svet_poznamky p
        JOIN svet s ON s.id = p.svet_id
        JOIN ucty u ON u.id = p.autor_ucet_id
        LEFT JOIN mapy m ON m.id = p.mapa_id'
    . ($roz ? ' LEFT JOIN postavy pv ON pv.id = p.postava_id' : '')
    . ($kde ? ' WHERE ' . implode(' AND ', $kde) : '')
    . ' ORDER BY p.vytvoreno DESC, p.id DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$poznamky = $stmt->fetchAll();

$sdileno = [];
if ($poznamky) {
    $ids = array_column($poznamky, 'id');
    $stmt = $pdo->prepare(
        'SELECT s.poznamka_id, u.jmeno FROM svet_poznamka_sdileni s JOIN ucty u ON u.id = s.ucet_id
         WHERE s.poznamka_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY u.jmeno'
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $r) {
        $sdileno[(int)$r['poznamka_id']][] = $r['jmeno'];
    }
}
$celkem = (int)$pdo->query('SELECT COUNT(*) FROM svet_poznamky')->fetchColumn();
$cas = static function (string $t): string {
    try {
        return (new DateTimeImmutable($t))->setTimezone(new DateTimeZone('Europe/Prague'))->format('j. n. Y H:i');
    } catch (Throwable $ex) {
        return $t;
    }
};
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — poznámky světů</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/organic.css?v=<?= filemtime(__DIR__ . '/assets/css/organic.css') ?>">
<style>
  .poz-filtr { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin:0 0 14px; }
  .poz-filtr label { display:flex; flex-direction:column; gap:3px; font-size:11.5px; color:var(--color-neutral-600); }
  .poz-text { white-space:pre-wrap; word-break:break-word; max-width:520px; }
</style>
</head>
<body>
<button class="menu-btn" id="menuBtn" type="button" aria-label="Otevřít navigaci">☰</button>
<div class="backdrop" id="backdrop"></div>
<div class="shell">
  <?php dracak_admin_menu('poznamky', $user); ?>

  <main class="main">
    <h1 class="page-title">Poznámky světů</h1>
    <p class="note" style="max-width:860px;">Dohled admina: tady je vidět všechno, co kdo ve světech napsal, i to, co nikomu nenasdílel. PJ světa to nevidí. Celkem <?= $celkem ?> poznámek<?= count($poznamky) < $celkem && !$kde ? ' (zobrazeno nejnovějších ' . count($poznamky) . ')' : '' ?>. Smazat poznámku jde ze stránky světa.</p>

    <form method="get" class="poz-filtr">
      <label>Svět
        <select class="input" name="svet">
          <option value="0">— všechny —</option>
          <?php foreach ($svety as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $svetFiltr === (int)$s['id'] ? 'selected' : '' ?>><?= $e($s['nazev']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Autor
        <select class="input" name="autor">
          <option value="0">— všichni —</option>
          <?php foreach ($autori as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $autorFiltr === (int)$a['id'] ? 'selected' : '' ?>><?= $e($a['jmeno']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <?php if ($roz): ?>
      <label>Štítek
        <select class="input" name="stitek">
          <option value="">— všechny —</option>
          <?php foreach (DRACAK_VTT_POZNAMKY_STITKY as $kl => $nazev): ?><option value="<?= $e($kl) ?>" <?= $stitekFiltr === $kl ? 'selected' : '' ?>><?= $e($nazev) ?></option><?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit">Filtrovat</button>
    </form>

    <div class="card elev-sm" style="margin:0 0 16px;">
      <?php if (!$poznamky): ?>
        <p class="note" style="margin:0;">Žádná poznámka neodpovídá filtru.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Kdy</th><th>Svět</th><th>Autor</th><th>Poznámka</th><th>Štítek</th><th>Místo / postava</th><th>Nasdíleno</th></tr></thead>
            <tbody>
              <?php foreach ($poznamky as $p): ?>
                <tr>
                  <td style="white-space:nowrap;"><?= $e($cas((string)$p['vytvoreno'])) ?><?= !empty($p['upraveno']) ? '<br><span class="note">upraveno ' . $e($cas((string)$p['upraveno'])) . '</span>' : '' ?></td>
                  <td><a href="hra/svet.php?id=<?= (int)$p['svet_id'] ?>#poznamka-<?= (int)$p['id'] ?>"><?= $e($p['svet_nazev']) ?></a></td>
                  <td><?= $e($p['autor_jmeno']) ?></td>
                  <td class="poz-text"><?= $e($p['text']) ?></td>
                  <td><?= $roz && !empty($p['stitek']) && isset(DRACAK_VTT_POZNAMKY_STITKY[$p['stitek']]) ? $e(DRACAK_VTT_POZNAMKY_STITKY[$p['stitek']]) : '—' ?></td>
                  <td><?php
                      $cast = [];
                      if ($p['mapa_nazev']) { $cast[] = 'místo: ' . $e($p['mapa_nazev']); }
                      if ($roz && !empty($p['postava_nazev'])) { $cast[] = 'postava: ' . $e($p['postava_nazev']); }
                      echo $cast ? implode('<br>', $cast) : '—'; ?></td>
                  <td><?= !empty($sdileno[(int)$p['id']]) ? $e(implode(', ', $sdileno[(int)$p['id']])) : '<span class="note">nikomu</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script nonce="<?= dracak_csp_nonce() ?>">
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
