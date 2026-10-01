<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

$user = dracak_require_login();
$postavaId = (int)($_GET['id'] ?? 0);

$stmt = dracak_db()->prepare('SELECT * FROM postavy WHERE id = ?');
$stmt->execute([$postavaId]);
$postava = $stmt->fetch();
if (!$postava) {
    http_response_code(404);
    die('Postava nenalezena.');
}
$svet = dracak_vtt_require_svet($user, (int)$postava['svet_id']);
if (!dracak_vtt_can_edit_hp($user, 'postava', $postava)) {
    http_response_code(403);
    die('Tuhle postavu upravovat nesmíš.');
}
$svetId = (int)$svet['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nazev = trim((string)($_POST['nazev'] ?? ''));
    if ($nazev === '') { http_response_code(422); die('Postava musí mít jméno.'); }
    $rasaId = !empty($_POST['rasa_id']) ? (int)$_POST['rasa_id'] : null;
    $povolaniId = !empty($_POST['povolani_id']) ? (int)$_POST['povolani_id'] : null;
    $uroven = max(1, (int)($_POST['uroven'] ?? 1));
    $maxHp = max(0, (int)($_POST['max_hp'] ?? 0));
    // Aktuální život se nesmí přehoupnout přes nové maximum (typicky se
    // upravuje max. život při postupu na vyšší úroveň) — mimo tenhle
    // strop se nekontroluje nic (léčení/zranění v boji řeší mapa.php).
    $aktualniHp = min($maxHp, max(0, (int)($_POST['aktualni_hp'] ?? 0)));
    // Magenergie (viz database/migrations/0050_vtt_postava_magenergie.sql)
    // je na rozdíl od HP nepovinná — prázdné pole = NULL = tohle
    // povolání magenergii nepoužívá (nebo se zatím nevyplnilo), stejná
    // konvence jako nullable atributy níž.
    $maxMagenergie = !empty($_POST['max_magenergie']) ? max(0, (int)$_POST['max_magenergie']) : null;
    $aktualniMagenergie = $maxMagenergie !== null
        ? min($maxMagenergie, max(0, (int)($_POST['aktualni_magenergie'] ?? 0)))
        : null;
    $poznamky = trim((string)($_POST['poznamky'] ?? '')) ?: null;
    $atributy = [];
    foreach (['sila', 'obratnost', 'odolnost', 'inteligence', 'charisma'] as $atr) {
        $atributy[$atr] = !empty($_POST[$atr]) ? max(1, (int)$_POST[$atr]) : null;
    }

    $stmt = dracak_db()->prepare(
        'UPDATE postavy SET nazev = ?, rasa_id = ?, povolani_id = ?, uroven = ?,
         sila = ?, obratnost = ?, odolnost = ?, inteligence = ?, charisma = ?,
         aktualni_hp = ?, max_hp = ?, aktualni_magenergie = ?, max_magenergie = ?, poznamky = ? WHERE id = ?'
    );
    $stmt->execute([
        $nazev, $rasaId, $povolaniId, $uroven,
        $atributy['sila'], $atributy['obratnost'], $atributy['odolnost'], $atributy['inteligence'], $atributy['charisma'],
        $aktualniHp, $maxHp, $aktualniMagenergie, $maxMagenergie, $poznamky, $postavaId,
    ]);
    header("Location: svet.php?id=$svetId");
    exit;
}

$rasyOptions = dracak_db()->query('SELECT id, nazev FROM rasy ORDER BY nazev')->fetchAll();
$povolaniOptions = dracak_db()->query('SELECT id, nazev FROM povolani ORDER BY nazev')->fetchAll();

dracak_vtt_page_start($postava['nazev'], $user);
?>
  <main class="main" style="padding:24px;">
    <p class="crumb"><a href="svet.php?id=<?= $svetId ?>">← <?= htmlspecialchars($svet['nazev']) ?></a></p>
    <h1 class="page-title">Upravit postavu</h1>
    <form method="post" class="card elev-sm" style="max-width:520px;margin-top:14px;">
      <div class="field"><label for="nazev">Jméno postavy *</label>
        <input class="input" type="text" id="nazev" name="nazev" required value="<?= htmlspecialchars($postava['nazev']) ?>">
      </div>
      <div class="field"><label for="rasa_id">Rasa</label>
        <select id="rasa_id" name="rasa_id"><option value="">—</option>
          <?php foreach ($rasyOptions as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === (int)$postava['rasa_id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['nazev']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="povolani_id">Povolání</label>
        <select id="povolani_id" name="povolani_id"><option value="">—</option>
          <?php foreach ($povolaniOptions as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$postava['povolani_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nazev']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="uroven">Úroveň</label>
        <input class="input" type="number" id="uroven" name="uroven" min="1" value="<?= (int)$postava['uroven'] ?>">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div class="field"><label for="aktualni_hp">Aktuální život</label>
          <input class="input" type="number" id="aktualni_hp" name="aktualni_hp" min="0" value="<?= (int)$postava['aktualni_hp'] ?>"></div>
        <div class="field"><label for="max_hp">Max. život</label>
          <input class="input" type="number" id="max_hp" name="max_hp" min="0" value="<?= (int)$postava['max_hp'] ?>"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div class="field"><label for="aktualni_magenergie">Aktuální magenergie</label>
          <input class="input" type="number" id="aktualni_magenergie" name="aktualni_magenergie" min="0" value="<?= htmlspecialchars((string)($postava['aktualni_magenergie'] ?? '')) ?>"></div>
        <div class="field"><label for="max_magenergie">Max. magenergie (prázdné = povolání ji nepoužívá)</label>
          <input class="input" type="number" id="max_magenergie" name="max_magenergie" min="0" value="<?= htmlspecialchars((string)($postava['max_magenergie'] ?? '')) ?>"></div>
      </div>
      <h3 class="rel-label">Atributy (stupeň)</h3>
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;">
        <div class="field"><label for="sila">Síla</label>
          <input class="input" type="number" id="sila" name="sila" min="1" max="30" value="<?= htmlspecialchars((string)($postava['sila'] ?? '')) ?>"></div>
        <div class="field"><label for="obratnost">Obratnost</label>
          <input class="input" type="number" id="obratnost" name="obratnost" min="1" max="30" value="<?= htmlspecialchars((string)($postava['obratnost'] ?? '')) ?>"></div>
        <div class="field"><label for="odolnost">Odolnost</label>
          <input class="input" type="number" id="odolnost" name="odolnost" min="1" max="30" value="<?= htmlspecialchars((string)($postava['odolnost'] ?? '')) ?>"></div>
        <div class="field"><label for="inteligence">Inteligence</label>
          <input class="input" type="number" id="inteligence" name="inteligence" min="1" max="30" value="<?= htmlspecialchars((string)($postava['inteligence'] ?? '')) ?>"></div>
        <div class="field"><label for="charisma">Charisma</label>
          <input class="input" type="number" id="charisma" name="charisma" min="1" max="30" value="<?= htmlspecialchars((string)($postava['charisma'] ?? '')) ?>"></div>
      </div>
      <div class="field"><label for="poznamky">Poznámky</label>
        <textarea id="poznamky" name="poznamky"><?= htmlspecialchars((string)($postava['poznamky'] ?? '')) ?></textarea>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:10px;">Uložit</button>
      <a class="btn btn-ghost" href="svet.php?id=<?= $svetId ?>" style="margin-top:10px;">Zrušit</a>
    </form>
  </main>
<?php dracak_vtt_page_end(); ?>
