<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

$user = dracak_require_login();
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isPjOrAdmin) {
        http_response_code(403);
        die('Jen PJ/admin může založit nový svět.');
    }
    $nazev = trim((string)($_POST['nazev'] ?? ''));
    $popis = trim((string)($_POST['popis'] ?? ''));
    if ($nazev === '') {
        http_response_code(422);
        die('Svět musí mít název.');
    }
    $stmt = dracak_db()->prepare('INSERT INTO svet (nazev, popis, pj_ucet_id) VALUES (?, ?, ?)');
    $stmt->execute([$nazev, $popis !== '' ? $popis : null, $user['id']]);
    header('Location: svety.php');
    exit;
}

if ($isPjOrAdmin) {
    $svety = dracak_db()->query(
        'SELECT s.*, u.jmeno AS pj_jmeno FROM svet s JOIN ucty u ON u.id = s.pj_ucet_id ORDER BY s.nazev'
    )->fetchAll();
} else {
    $stmt = dracak_db()->prepare(
        'SELECT s.*, u.jmeno AS pj_jmeno FROM svet s
         JOIN ucty u ON u.id = s.pj_ucet_id
         JOIN svet_hraci sh ON sh.svet_id = s.id
         WHERE sh.ucet_id = ? ORDER BY s.nazev'
    );
    $stmt->execute([$user['id']]);
    $svety = $stmt->fetchAll();
}

dracak_vtt_page_start('Světy', $user);
?>
  <main class="main" style="padding:24px;">
    <h1 class="page-title">Světy</h1>

    <?php if (!$svety): ?>
      <div class="empty-state"><?= $isPjOrAdmin ? 'Zatím žádný svět. Založ první níž.' : 'Zatím tě PJ nepřidal do žádného světa.' ?></div>
    <?php else: ?>
      <div class="records-grid">
        <?php foreach ($svety as $s): ?>
          <div class="card elev-sm rec-card">
            <h3 class="rec-title"><a href="svet.php?id=<?= (int)$s['id'] ?>" style="text-decoration:none;color:inherit;"><?= htmlspecialchars($s['nazev']) ?></a></h3>
            <div class="rec-grid">
              <div class="k">PJ:</div><div><?= htmlspecialchars($s['pj_jmeno']) ?></div>
            </div>
            <?php if ($s['popis']): ?><div class="rec-body"><p><?= htmlspecialchars($s['popis']) ?></p></div><?php endif; ?>
            <div class="rec-actions"><a class="btn btn-secondary" href="svet.php?id=<?= (int)$s['id'] ?>">Otevřít</a></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($isPjOrAdmin): ?>
      <h2 class="page-title" style="font-size:20px;margin-top:32px;">Založit nový svět</h2>
      <form method="post" class="card elev-sm" style="max-width:520px;">
        <div class="field"><label for="nazev">Název *</label>
          <input class="input" type="text" id="nazev" name="nazev" required>
        </div>
        <div class="field"><label for="popis">Popis</label>
          <textarea id="popis" name="popis"></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Založit</button>
      </form>
    <?php endif; ?>
  </main>
<?php dracak_vtt_page_end(); ?>

