<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

$user = dracak_require_login();
$isAdmin = $user['role'] === 'admin';

// Založit svět smí KDOKOLI přihlášený, ne jen role='pj' — jeden účet
// může být PJ svého vlastního světa a zároveň jen hráč v cizím (viz
// includes/vtt.php, dracak_vtt_je_pj_sveta). ucty.role dnes slouží jen
// jako admin override a jako výchozí nálepka účtu, nic víc.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nazev = trim((string)($_POST['nazev'] ?? ''));
    $popis = trim((string)($_POST['popis'] ?? ''));
    if ($nazev === '') {
        http_response_code(422);
        die('Svět musí mít název.');
    }
    // Počáteční herní datum — od něj se svět "posouvá" (viz
    // hra/svet_administrace.php); prázdné pole = dnešní reálné datum,
    // ale je to čistě PJova fikce, žádný vztah ke skutečnému kalendáři.
    $pocatecniDatum = trim((string)($_POST['pocatecni_datum'] ?? ''));
    if ($pocatecniDatum === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pocatecniDatum)) {
        $pocatecniDatum = date('Y-m-d');
    }
    $stmt = dracak_db()->prepare('INSERT INTO svet (nazev, popis, pocatecni_datum, pj_ucet_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$nazev, $popis !== '' ? $popis : null, $pocatecniDatum, $user['id']]);
    header('Location: svety.php');
    exit;
}

// Admin má přehled přes všechno (dohled), ostatní vidí sjednocení
// "moje světy jako PJ" (svet.pj_ucet_id) ∪ "světy, kam mě PJ přidal
// jako hráče" (svet_hraci) — totéž ohraničení, jaké teď vynucuje
// dracak_vtt_svet_access().
if ($isAdmin) {
    $svety = dracak_db()->query(
        'SELECT s.*, u.jmeno AS pj_jmeno FROM svet s JOIN ucty u ON u.id = s.pj_ucet_id ORDER BY s.nazev'
    )->fetchAll();
} else {
    $stmt = dracak_db()->prepare(
        'SELECT DISTINCT s.*, u.jmeno AS pj_jmeno FROM svet s
         JOIN ucty u ON u.id = s.pj_ucet_id
         LEFT JOIN svet_hraci sh ON sh.svet_id = s.id
         WHERE s.pj_ucet_id = ? OR sh.ucet_id = ? ORDER BY s.nazev'
    );
    $stmt->execute([$user['id'], $user['id']]);
    $svety = $stmt->fetchAll();
}

dracak_vtt_page_start('Světy', $user);
?>
  <main class="main" style="padding:24px;">
    <h1 class="page-title">Světy</h1>

    <?php if (!$svety): ?>
      <div class="empty-state">Zatím tě nikdo nepřidal do žádného světa — a zatím si žádný ani sám nezaložil. Můžeš založit svůj první níž.</div>
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

    <h2 class="page-title" style="font-size:20px;margin-top:32px;">Založit nový svět</h2>
    <form method="post" class="card elev-sm" style="max-width:520px;">
      <div class="field"><label for="nazev">Název *</label>
        <input class="input" type="text" id="nazev" name="nazev" required>
      </div>
      <div class="field"><label for="popis">Popis</label>
        <textarea id="popis" name="popis"></textarea>
      </div>
      <div class="field"><label for="pocatecni_datum">Počáteční herní datum</label>
        <input class="input" type="date" id="pocatecni_datum" name="pocatecni_datum" value="<?= date('Y-m-d') ?>">
        <p class="note" style="margin:4px 0 0;">Jen tvoje fikce, ne reálný kalendář — odtud se svět posouvá (viz administrace světa).</p>
      </div>
      <button class="btn btn-primary" type="submit">Založit</button>
    </form>
  </main>
<?php dracak_vtt_page_end(); ?>

