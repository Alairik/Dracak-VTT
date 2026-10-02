<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

// Globální roster hráčových vlastních postav, nezávislý na konkrétním
// světě — doplněk k hra/svet.php, kde se postava zakládá/spravuje vždy
// uvnitř jednoho světa. Tady si hráč může připravit postavu dřív, než
// ví, do jakého světa půjde (svet_id NULL, viz migrace 0053), a později
// ji přiřadit do některého světa, kde je zapsaný jako hráč.

$user = dracak_require_login();
$pdo = dracak_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akce'] ?? '') === 'prirad_svet') {
    $postavaId = (int)($_POST['postava_id'] ?? 0);
    $svetId = (int)($_POST['svet_id'] ?? 0);
    // Jen vlastní, dosud nepřiřazená postava a jen svět, kde je uživatel
    // zapsaný v svet_hraci — žádná výjimka pro admin/pj, přiřazuje se
    // vždycky jen vlastní postava, ne cizí.
    $stmt = $pdo->prepare('SELECT 1 FROM postavy WHERE id = ? AND vlastnik_ucet_id = ? AND svet_id IS NULL');
    $stmt->execute([$postavaId, $user['id']]);
    if ($stmt->fetchColumn()) {
        $stmt = $pdo->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
        $stmt->execute([$svetId, $user['id']]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare('UPDATE postavy SET svet_id = ? WHERE id = ?')->execute([$svetId, $postavaId]);
        }
    }
    header('Location: postavy_moje.php');
    exit;
}

$postavy = $pdo->prepare(
    'SELECT p.*, r.nazev AS rasa_nazev, pv.nazev AS povolani_nazev, s.nazev AS svet_nazev
     FROM postavy p
     LEFT JOIN rasy r ON r.id = p.rasa_id
     LEFT JOIN povolani pv ON pv.id = p.povolani_id
     LEFT JOIN svet s ON s.id = p.svet_id
     WHERE p.vlastnik_ucet_id = ? ORDER BY p.nazev'
);
$postavy->execute([$user['id']]);
$postavy = $postavy->fetchAll();

$stmt = $pdo->prepare(
    'SELECT s.id, s.nazev FROM svet s JOIN svet_hraci sh ON sh.svet_id = s.id WHERE sh.ucet_id = ? ORDER BY s.nazev'
);
$stmt->execute([$user['id']]);
$mojeSvety = $stmt->fetchAll();

dracak_vtt_page_start('Moje postavy', $user);
?>
  <main class="main" style="padding:24px;">
    <h1 class="page-title">Moje postavy</h1>
    <p class="note" style="margin-top:-6px;">Postavy, co sis založil/a — nemusí hned patřit do konkrétního světa, přiřadíš je, až budeš chtít.</p>

    <?php if (!$postavy): ?>
      <div class="empty-state">Zatím žádná postava.</div>
    <?php else: ?>
      <div class="records-grid">
        <?php foreach ($postavy as $p): ?>
          <div class="card elev-sm rec-card">
            <h3 class="rec-title"><?= htmlspecialchars($p['nazev']) ?></h3>
            <div class="rec-grid">
              <div class="k">Svět:</div><div><?= $p['svet_nazev'] ? htmlspecialchars($p['svet_nazev']) : '— bez světa' ?></div>
              <div class="k">Rasa/Povolání:</div><div><?= htmlspecialchars(($p['rasa_nazev'] ?? '—') . ' / ' . ($p['povolani_nazev'] ?? '—')) ?></div>
              <div class="k">Úroveň:</div><div><?= (int)$p['uroven'] ?></div>
              <div class="k">Život:</div><div><?= (int)$p['aktualni_hp'] ?> / <?= (int)$p['max_hp'] ?></div>
            </div>
            <div class="rec-actions"><a class="btn btn-secondary" href="postava.php?id=<?= (int)$p['id'] ?>">Upravit</a></div>
            <?php if ($p['svet_id'] === null): ?>
              <?php if ($mojeSvety): ?>
                <form method="post" style="display:flex;gap:6px;margin-top:8px;">
                  <input type="hidden" name="akce" value="prirad_svet">
                  <input type="hidden" name="postava_id" value="<?= (int)$p['id'] ?>">
                  <select class="input" name="svet_id" style="flex:1;font-size:12px;">
                    <?php foreach ($mojeSvety as $s): ?>
                      <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nazev']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-ghost" type="submit" style="font-size:12px;">Přiřadit do světa</button>
                </form>
              <?php else: ?>
                <p class="note" style="margin-top:8px;">Zatím nejsi v žádném světě — PJ tě musí nejdřív přidat ("Hráči u stolu" ve světě).</p>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card elev-sm" style="max-width:480px;margin-top:20px;">
      <p style="margin:0 0 10px;"><a class="btn btn-primary" href="postava_nova.php">+ Nová postava</a></p>
      <p class="note" style="margin:0;">Založíš krok za krokem podle pravidel (rasa, povolání, hod na atributy) — zatím bez světa, přiřadíš ji výš, až budeš vědět kam.</p>
    </div>
  </main>
<?php dracak_vtt_page_end(); ?>
