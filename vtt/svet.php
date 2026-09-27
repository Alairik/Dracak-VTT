<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

$user = dracak_require_login();
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);
$svetId = (int)($_GET['id'] ?? 0);
$svet = dracak_vtt_require_svet($user, $svetId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $akce = $_POST['akce'] ?? '';

    if ($akce === 'shrnuti') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin může upravit shrnutí.'); }
        $stmt = dracak_db()->prepare(
            'UPDATE svet SET posledni_shrnuti = ?, pripraveno_priste = ?,
             auto_hod_kostkou = ?, auto_aplikace_efektu = ?, auto_vyhodnoceni_pasti = ?, auto_zranitelnost = ?
             WHERE id = ?'
        );
        $stmt->execute([
            trim((string)($_POST['posledni_shrnuti'] ?? '')) ?: null,
            trim((string)($_POST['pripraveno_priste'] ?? '')) ?: null,
            isset($_POST['auto_hod_kostkou']) ? 1 : 0,
            isset($_POST['auto_aplikace_efektu']) ? 1 : 0,
            isset($_POST['auto_vyhodnoceni_pasti']) ? 1 : 0,
            isset($_POST['auto_zranitelnost']) ? 1 : 0,
            $svetId,
        ]);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'pridat_hrace') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin přidává hráče.'); }
        $ucetId = (int)($_POST['ucet_id'] ?? 0);
        if ($ucetId > 0) {
            $stmt = dracak_db()->prepare('INSERT IGNORE INTO svet_hraci (svet_id, ucet_id) VALUES (?, ?)');
            $stmt->execute([$svetId, $ucetId]);
        }
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'nova_mapa') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin přidává mapy.'); }
        $nazev = trim((string)($_POST['nazev'] ?? ''));
        $typMapy = ($_POST['typ_mapy'] ?? 'zona') === 'svet' ? 'svet' : 'zona';
        if ($nazev === '') { http_response_code(422); die('Mapa musí mít název.'); }

        $obrazekCesta = null;
        $sirka = null;
        $vyska = null;
        if (!empty($_FILES['obrazek']['tmp_name']) && is_uploaded_file($_FILES['obrazek']['tmp_name'])) {
            $povoleneTypy = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $info = getimagesize($_FILES['obrazek']['tmp_name']);
            if ($info === false || !isset($povoleneTypy[$info['mime']])) {
                http_response_code(422);
                die('Obrázek mapy musí být PNG, JPG nebo WEBP.');
            }
            $sirka = $info[0];
            $vyska = $info[1];
            $nazevSouboru = bin2hex(random_bytes(16)) . '.' . $povoleneTypy[$info['mime']];
            $cil = __DIR__ . '/../uploads/mapy/' . $nazevSouboru;
            if (!move_uploaded_file($_FILES['obrazek']['tmp_name'], $cil)) {
                dracak_fail_safely('Nepodařilo se uložit nahraný obrázek mapy.');
            }
            $obrazekCesta = $nazevSouboru;
        }

        $stmt = dracak_db()->prepare(
            'INSERT INTO mapy (svet_id, nazev, typ_mapy, obrazek_cesta, sirka_px, vyska_px) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$svetId, $nazev, $typMapy, $obrazekCesta, $sirka, $vyska]);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'nova_postava') {
        $nazev = trim((string)($_POST['nazev'] ?? ''));
        if ($nazev === '') { http_response_code(422); die('Postava musí mít jméno.'); }
        $rasaId = !empty($_POST['rasa_id']) ? (int)$_POST['rasa_id'] : null;
        $povolaniId = !empty($_POST['povolani_id']) ? (int)$_POST['povolani_id'] : null;
        $maxHp = max(0, (int)($_POST['max_hp'] ?? 0));
        $stmt = dracak_db()->prepare(
            'INSERT INTO postavy (svet_id, vlastnik_ucet_id, nazev, rasa_id, povolani_id, uroven, aktualni_hp, max_hp)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
        );
        $stmt->execute([$svetId, $user['id'], $nazev, $rasaId, $povolaniId, $maxHp, $maxHp]);
        header("Location: svet.php?id=$svetId");
        exit;
    }
}

$mapy = dracak_db()->prepare('SELECT * FROM mapy WHERE svet_id = ? ORDER BY typ_mapy DESC, nazev');
$mapy->execute([$svetId]);
$mapy = $mapy->fetchAll();

$postavy = dracak_db()->prepare(
    'SELECT p.*, r.nazev AS rasa_nazev, pv.nazev AS povolani_nazev, u.jmeno AS vlastnik_jmeno
     FROM postavy p
     LEFT JOIN rasy r ON r.id = p.rasa_id
     LEFT JOIN povolani pv ON pv.id = p.povolani_id
     JOIN ucty u ON u.id = p.vlastnik_ucet_id
     WHERE p.svet_id = ? ORDER BY p.nazev'
);
$postavy->execute([$svetId]);
$postavy = $postavy->fetchAll();

$rasyOptions = dracak_db()->query('SELECT id, nazev FROM rasy ORDER BY nazev')->fetchAll();
$povolaniOptions = dracak_db()->query('SELECT id, nazev FROM povolani ORDER BY nazev')->fetchAll();

if ($isPjOrAdmin) {
    $stmt = dracak_db()->prepare(
        "SELECT u.id, u.jmeno FROM ucty u
         WHERE u.role = 'hrac' AND u.id NOT IN (SELECT ucet_id FROM svet_hraci WHERE svet_id = ?)
         ORDER BY u.jmeno"
    );
    $stmt->execute([$svetId]);
    $volniHraci = $stmt->fetchAll();

    $stmt = dracak_db()->prepare(
        'SELECT u.jmeno FROM svet_hraci sh JOIN ucty u ON u.id = sh.ucet_id WHERE sh.svet_id = ? ORDER BY u.jmeno'
    );
    $stmt->execute([$svetId]);
    $hraciVeSvete = array_column($stmt->fetchAll(), 'jmeno');
}

dracak_vtt_page_start($svet['nazev'], $user);
?>
  <main class="main" style="padding:24px;">
    <p class="crumb"><a href="svety.php">← Světy</a></p>
    <h1 class="page-title"><?= htmlspecialchars($svet['nazev']) ?></h1>

    <div class="card elev-sm" style="margin-top:14px;">
      <?php if ($isPjOrAdmin): ?>
      <form method="post">
        <input type="hidden" name="akce" value="shrnuti">
        <div class="field"><label for="posledni_shrnuti">Kde jsme skončili</label>
          <textarea id="posledni_shrnuti" name="posledni_shrnuti"><?= htmlspecialchars((string)($svet['posledni_shrnuti'] ?? '')) ?></textarea>
        </div>
        <div class="field"><label for="pripraveno_priste">Co bude příště (jen PJ/admin vidí)</label>
          <textarea id="pripraveno_priste" name="pripraveno_priste"><?= htmlspecialchars((string)($svet['pripraveno_priste'] ?? '')) ?></textarea>
        </div>
        <h3 class="rel-label">Automatizace pravidel</h3>
        <p class="note" style="margin:-4px 0 10px;">Co engine spočítá/aplikuje sám, vs. co zůstává na ručním hodu a PJ rozhodnutí.</p>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;margin-bottom:6px;">
          <input type="checkbox" name="auto_hod_kostkou" <?= $svet['auto_hod_kostkou'] ? 'checked' : '' ?>> Automatický hod kostkou
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;margin-bottom:6px;">
          <input type="checkbox" name="auto_aplikace_efektu" <?= $svet['auto_aplikace_efektu'] ? 'checked' : '' ?>> Automatická aplikace efektu
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;margin-bottom:6px;">
          <input type="checkbox" name="auto_vyhodnoceni_pasti" <?= $svet['auto_vyhodnoceni_pasti'] ? 'checked' : '' ?>> Automatické vyhodnocení pasti
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;margin-bottom:14px;">
          <input type="checkbox" name="auto_zranitelnost" <?= $svet['auto_zranitelnost'] ? 'checked' : '' ?>> Automatický modifikátor zranitelnosti
        </label>
        <button class="btn btn-primary" type="submit">Uložit</button>
      </form>
      <p class="note" style="margin-top:10px;">Automatizace zatím jen ukládá nastavení — samotné napojení na kouzla/schopnosti/pasti přijde v další vrstvě.</p>
      <?php else: ?>
        <h3 class="rel-label">Kde jsme skončili</h3>
        <p><?= nl2br(htmlspecialchars((string)($svet['posledni_shrnuti'] ?? '—'))) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($isPjOrAdmin): ?>
    <h2 class="page-title" style="font-size:20px;margin-top:28px;">Hráči u stolu</h2>
    <div class="card elev-sm">
      <p><?= $hraciVeSvete ? htmlspecialchars(implode(', ', $hraciVeSvete)) : 'Zatím žádný hráč nepřidán.' ?></p>
      <?php if ($volniHraci): ?>
      <form method="post" style="display:flex;gap:10px;margin-top:10px;">
        <input type="hidden" name="akce" value="pridat_hrace">
        <select class="input" name="ucet_id">
          <?php foreach ($volniHraci as $h): ?>
            <option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['jmeno']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary" type="submit">+ Přidat</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <h2 class="page-title" style="font-size:20px;margin-top:28px;">Mapy</h2>
    <?php if (!$mapy): ?>
      <div class="empty-state">Zatím žádná mapa.</div>
    <?php else: ?>
      <div class="records-grid">
        <?php foreach ($mapy as $m): ?>
          <div class="card elev-sm rec-card">
            <h3 class="rec-title"><a href="mapa.php?id=<?= (int)$m['id'] ?>" style="text-decoration:none;color:inherit;"><?= htmlspecialchars($m['nazev']) ?></a></h3>
            <div class="rec-grid"><div class="k">Typ:</div><div><?= $m['typ_mapy'] === 'svet' ? 'světová' : 'zóna' ?></div></div>
            <div class="rec-actions"><a class="btn btn-secondary" href="mapa.php?id=<?= (int)$m['id'] ?>">Otevřít</a></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($isPjOrAdmin): ?>
      <form method="post" enctype="multipart/form-data" class="card elev-sm" style="max-width:520px;margin-top:14px;">
        <input type="hidden" name="akce" value="nova_mapa">
        <div class="field"><label for="mapa_nazev">Název *</label>
          <input class="input" type="text" id="mapa_nazev" name="nazev" required>
        </div>
        <div class="field"><label for="typ_mapy">Typ</label>
          <select id="typ_mapy" name="typ_mapy">
            <option value="zona">Zóna (bitevní mapa)</option>
            <option value="svet">Světová mapa</option>
          </select>
        </div>
        <div class="field"><label for="obrazek">Obrázek mapy (PNG/JPG/WEBP)</label>
          <input type="file" id="obrazek" name="obrazek" accept="image/png,image/jpeg,image/webp">
        </div>
        <button class="btn btn-primary" type="submit">Přidat mapu</button>
      </form>
    <?php endif; ?>

    <h2 class="page-title" style="font-size:20px;margin-top:28px;">Postavy</h2>
    <?php if (!$postavy): ?>
      <div class="empty-state">Zatím žádná postava.</div>
    <?php else: ?>
      <div class="records-grid">
        <?php foreach ($postavy as $p): ?>
          <div class="card elev-sm rec-card">
            <h3 class="rec-title"><?= htmlspecialchars($p['nazev']) ?></h3>
            <div class="rec-grid">
              <div class="k">Hráč:</div><div><?= htmlspecialchars($p['vlastnik_jmeno']) ?></div>
              <div class="k">Rasa/Povolání:</div><div><?= htmlspecialchars(($p['rasa_nazev'] ?? '—') . ' / ' . ($p['povolani_nazev'] ?? '—')) ?></div>
              <div class="k">Život:</div><div><?= (int)$p['aktualni_hp'] ?> / <?= (int)$p['max_hp'] ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" class="card elev-sm" style="max-width:520px;margin-top:14px;">
      <input type="hidden" name="akce" value="nova_postava">
      <div class="field"><label for="p_nazev">Jméno postavy *</label>
        <input class="input" type="text" id="p_nazev" name="nazev" required>
      </div>
      <div class="field"><label for="rasa_id">Rasa</label>
        <select id="rasa_id" name="rasa_id"><option value="">—</option>
          <?php foreach ($rasyOptions as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nazev']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="povolani_id">Povolání</label>
        <select id="povolani_id" name="povolani_id"><option value="">—</option>
          <?php foreach ($povolaniOptions as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nazev']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="max_hp">Max. život</label>
        <input class="input" type="number" id="max_hp" name="max_hp" min="0" value="10">
      </div>
      <button class="btn btn-primary" type="submit">Založit postavu</button>
    </form>
  </main>
<?php dracak_vtt_page_end(); ?>
