<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_pohyblivost.php';

$user = dracak_require_login();
$postavaId = (int)($_GET['id'] ?? 0);

$stmt = dracak_db()->prepare('SELECT * FROM postavy WHERE id = ?');
$stmt->execute([$postavaId]);
$postava = $stmt->fetch();
if (!$postava) {
    http_response_code(404);
    die('Postava nenalezena.');
}
$svetId = (int)($postava['svet_id'] ?? 0);
if ($svetId > 0) {
    $svet = dracak_vtt_require_svet($user, $svetId);
    if (!dracak_vtt_can_edit_hp($user, 'postava', $postava)) {
        http_response_code(403);
        die('Tuhle postavu upravovat nesmíš.');
    }
} else {
    // Postava zatím nepatří do žádného světa — dracak_vtt_can_edit_hp()
    // by tu pustila libovolného PJ (jeho oprávnění jinak vychází z
    // dracak_vtt_require_svet() výš, kterou tu nemáme co zavolat), proto
    // tady výslovně jen vlastník nebo admin.
    $svet = null;
    if ($user['role'] !== 'admin' && (int)$postava['vlastnik_ucet_id'] !== (int)$user['id']) {
        http_response_code(403);
        die('Tuhle postavu upravovat nesmíš.');
    }
}

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

    // Doprava (migrace 0068) — jen když sloupce na DB už jsou, jinak by
    // celý UPDATE výš (a s ním úprava postavy) neměl jak projít. Zvíře
    // se bere z číselníku, žádné vynucené vlastnictví; neplatné id =
    // chůze. Dvojice typ/zvíře se drží konzistentní tady, ne v DB.
    if (array_key_exists('doprava_typ', $postava)) {
        $zvireId = (int)($_POST['doprava_zvire_id'] ?? 0);
        $stmt = dracak_db()->prepare('SELECT 1 FROM jizdni_zvirata WHERE id = ?');
        $stmt->execute([$zvireId]);
        $platne = $zvireId > 0 && $stmt->fetchColumn() !== false;
        dracak_db()->prepare('UPDATE postavy SET doprava_typ = ?, doprava_zvire_id = ? WHERE id = ?')
            ->execute([$platne ? 'zvire' : 'chuze', $platne ? $zvireId : null, $postavaId]);
    }
    header($svetId > 0 ? "Location: svet.php?id=$svetId" : 'Location: postavy_moje.php');
    exit;
}

$rasyOptions = dracak_db()->query('SELECT id, nazev FROM rasy ORDER BY nazev')->fetchAll();
$povolaniOptions = dracak_db()->query('SELECT id, nazev FROM povolani ORDER BY nazev')->fetchAll();

// Jen ke čtení, podle uložených hodnot (ne podle rozepsaného formuláře).
$naklad = dracak_vtt_postava_naklad_mn(dracak_db(), $postavaId, $postava);
$pohyblivost = dracak_vtt_postava_pohyblivost(dracak_db(), $postava, $naklad['naklad_mn']);
$nalozeniNazvy = ['zadne' => 'žádné', 'mirne' => 'mírné', 'stredni' => 'střední', 'velke' => 'velké', 'prilis_velke' => 'nad 4× nosnost'];
$unava = dracak_vtt_postava_unava_stupen($postava);
$doprava = dracak_vtt_postava_doprava(dracak_db(), $postava);
$jizdniZvirata = $doprava !== null ? dracak_vtt_jizdni_zvirata(dracak_db()) : [];
$znamenko = static fn(int $n): string => ($n > 0 ? '+' : '') . $n;

dracak_vtt_page_start($postava['nazev'], $user);
?>
  <main class="main" style="padding:24px;">
    <?php if ($svet): ?>
    <p class="crumb"><a href="svet.php?id=<?= $svetId ?>">← <?= htmlspecialchars($svet['nazev']) ?></a></p>
    <?php else: ?>
    <p class="crumb"><a href="postavy_moje.php">← Moje postavy</a></p>
    <p class="note">Zatím bez světa.</p>
    <?php endif; ?>
    <h1 class="page-title">Upravit postavu</h1>
    <p class="note" style="margin:0 0 8px;"><a href="denik.php?id=<?= $postavaId ?>">→ Osobní deník (přehled postavy)</a></p>
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
      <h3 class="rel-label">Pohyb a únava</h3>
      <p class="note" style="margin:0 0 4px;">
        <?php if ($pohyblivost): ?>
          <strong>Základní pohyblivost (bez naložení): <?= $pohyblivost['zakladni'] ?></strong>
          — rasa <?= $pohyblivost['rasovy_zaklad'] ?>,
          obratnost <?= $znamenko($pohyblivost['bonus_obratnost']) ?>,
          2× síla (<?= $znamenko($pohyblivost['bonus_sila']) ?>)<br>
          <?php if ($pohyblivost['nosnost_mn'] !== null && $pohyblivost['nalozeni']): ?>
            Náklad z inventáře: <?= round($naklad['naklad_mn'], 1) ?> mn z nosnosti <?= $pohyblivost['nosnost_mn'] ?> mn
            — naložení <?= $nalozeniNazvy[$pohyblivost['nalozeni']['kod']] ?>.
            <strong>Pohyblivost <?= $pohyblivost['pohyblivost'] ?? '—' ?></strong><?= $pohyblivost['bonus_pohyblivosti'] !== null ? ' (bonus ' . $znamenko($pohyblivost['bonus_pohyblivosti']) . ')' : '' ?>.
            <?php if ($naklad['neuplny']): ?>
              <em>Součet je neúplný — bez váhy: <?= htmlspecialchars(implode(', ', $naklad['bez_vahy'])) ?>.</em>
            <?php endif; ?>
            Nepočítá se oblečení a jídlo<?= $naklad['penize_pocitany'] ? '' : ' ani peníze' ?> (nejsou v inventáři).
          <?php endif; ?>
        <?php else: ?>
          Pohyblivost: doplň rasu, sílu a obratnost (a rasa musí mít pohyblivost v číselníku).
        <?php endif; ?>
      </p>
      <p class="note" style="margin:0 0 4px;">
        <?php if ($unava): ?>
          <strong>Únava: <?= $unava['body'] ?> / vytrvalost <?= $unava['vytrvalost'] ?></strong>
          — <?= htmlspecialchars($unava['nazev']) ?><?= $unava['popis'] ? ' (' . htmlspecialchars($unava['popis']) . ')' : '' ?>
        <?php else: ?>
          Únava: doplň odolnost (vytrvalost = odolnost).
        <?php endif; ?>
      </p>
      <?php if ($doprava !== null): ?>
      <div class="field"><label for="doprava_zvire_id">Doprava</label>
        <select id="doprava_zvire_id" name="doprava_zvire_id">
          <option value="">Pěšky</option>
          <?php foreach ($jizdniZvirata as $z): ?>
            <option value="<?= (int)$z['id'] ?>" <?= $doprava['zvire'] && (int)$doprava['zvire']['id'] === (int)$z['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($z['nazev']) ?> (pohyblivost <?= (int)$z['pohyblivost'] ?>, nosnost <?= (int)$z['nosnost_mn'] ?> mn, vytrvalost <?= (int)$z['vytrvalost'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <span class="note">Zvíře se vybírá z číselníku, nemusí patřit postavě (půjčené, ukradené…).</span>
      </div>
      <?php endif; ?>
      <div class="field"><label for="poznamky">Poznámky</label>
        <textarea id="poznamky" name="poznamky"><?= htmlspecialchars((string)($postava['poznamky'] ?? '')) ?></textarea>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:10px;">Uložit</button>
      <a class="btn btn-ghost" href="<?= $svet ? "svet.php?id=$svetId" : 'postavy_moje.php' ?>" style="margin-top:10px;">Zrušit</a>
    </form>
  </main>
<?php dracak_vtt_page_end(); ?>
