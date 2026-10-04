<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_predmety.php';
require_once __DIR__ . '/../includes/vtt_poznamky.php';

$user = dracak_require_login();
$svetId = (int)($_GET['id'] ?? 0);
$svet = dracak_vtt_require_svet($user, $svetId);
$isPjOrAdmin = dracak_vtt_je_pj_sveta($user, $svetId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $akce = $_POST['akce'] ?? '';

    if ($akce === 'shrnuti') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin může upravit shrnutí.'); }
        $stmt = dracak_db()->prepare('UPDATE svet SET posledni_shrnuti = ?, pripraveno_priste = ? WHERE id = ?');
        $stmt->execute([
            trim((string)($_POST['posledni_shrnuti'] ?? '')) ?: null,
            trim((string)($_POST['pripraveno_priste'] ?? '')) ?: null,
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

        // Grid je nepovinný — prázdná velikost = grid_velikost_px zůstává
        // NULL a mapa.php ho nevykresluje (chování shodné se stavem před
        // touhle funkcí). Typ/offset dávají smysl jen spolu s velikostí,
        // ale ukládají se vždy (typ má DB DEFAULT 'ctverec', offsety 0).
        $gridPx = trim((string)($_POST['grid_velikost_px'] ?? ''));
        $gridVelikostPx = $gridPx !== '' ? max(1, (int)$gridPx) : null;
        $gridTyp = ($_POST['grid_typ'] ?? 'ctverec') === 'hex' ? 'hex' : 'ctverec';
        $gridPosunX = (int)($_POST['grid_posun_x'] ?? 0);
        $gridPosunY = (int)($_POST['grid_posun_y'] ?? 0);

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
            'INSERT INTO mapy (svet_id, nazev, typ_mapy, obrazek_cesta, sirka_px, vyska_px, grid_velikost_px, grid_typ, grid_posun_x, grid_posun_y)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$svetId, $nazev, $typMapy, $obrazekCesta, $sirka, $vyska, $gridVelikostPx, $gridTyp, $gridPosunX, $gridPosunY]);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'nova_postava') {
        $nazev = trim((string)($_POST['nazev'] ?? ''));
        if ($nazev === '') { http_response_code(422); die('Postava musí mít jméno.'); }
        $rasaId = !empty($_POST['rasa_id']) ? (int)$_POST['rasa_id'] : null;
        $povolaniId = !empty($_POST['povolani_id']) ? (int)$_POST['povolani_id'] : null;
        $maxHp = max(0, (int)($_POST['max_hp'] ?? 0));
        // Atributy jsou "stupeň" podle pravidel (h104), ne bonus — viz
        // migrace 0040. Nepovinné, starší/rychle založené postavy je
        // mohou mít NULL.
        $atributy = [];
        foreach (['sila', 'obratnost', 'odolnost', 'inteligence', 'charisma'] as $atr) {
            $atributy[$atr] = !empty($_POST[$atr]) ? max(1, (int)$_POST[$atr]) : null;
        }

        // Hráč zakládá vždycky sám sobě — neřeší se, co pošle v POSTu.
        // PJ/admin může založit rovnou pro kohokoliv u stolu (vlastnik_ucet_id
        // v POSTu), jinak taky sám sobě.
        $vlastnikId = $user['id'];
        if ($isPjOrAdmin && !empty($_POST['vlastnik_ucet_id'])) {
            $stmt = dracak_db()->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
            $stmt->execute([$svetId, (int)$_POST['vlastnik_ucet_id']]);
            if ($stmt->fetchColumn()) {
                $vlastnikId = (int)$_POST['vlastnik_ucet_id'];
            }
        }

        $stmt = dracak_db()->prepare(
            'INSERT INTO postavy (svet_id, vlastnik_ucet_id, nazev, rasa_id, povolani_id, uroven, sila, obratnost, odolnost, inteligence, charisma, aktualni_hp, max_hp)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $svetId, $vlastnikId, $nazev, $rasaId, $povolaniId,
            $atributy['sila'], $atributy['obratnost'], $atributy['odolnost'], $atributy['inteligence'], $atributy['charisma'],
            $maxHp, $maxHp,
        ]);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'pripojit_postavu') {
        // Připojení VLASTNÍ už existující, zatím nepřiřazené postavy (viz
        // hra/postavy_moje.php) rovnou z téhle stránky — alternativa k
        // zakládání nové, když si ji hráč připravil dřív bez vazby na
        // svět. Jen vlastník, žádná výjimka pro PJ/admin (ti postavu
        // nevlastní, takže by si tu nic nemohli vybrat).
        $postavaId = (int)($_POST['postava_id'] ?? 0);
        $stmt = dracak_db()->prepare('SELECT 1 FROM postavy WHERE id = ? AND vlastnik_ucet_id = ? AND svet_id IS NULL');
        $stmt->execute([$postavaId, $user['id']]);
        if ($stmt->fetchColumn()) {
            $stmt = dracak_db()->prepare('UPDATE postavy SET svet_id = ? WHERE id = ?');
            $stmt->execute([$svetId, $postavaId]);
        }
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'prevest_postavu') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin přiřazuje postavu jinému hráči.'); }
        $postavaId = (int)($_POST['postava_id'] ?? 0);
        $novyVlastnikId = (int)($_POST['novy_vlastnik_id'] ?? 0);
        $stmt = dracak_db()->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
        $stmt->execute([$svetId, $novyVlastnikId]);
        if ($stmt->fetchColumn()) {
            $stmt = dracak_db()->prepare('UPDATE postavy SET vlastnik_ucet_id = ? WHERE id = ? AND svet_id = ?');
            $stmt->execute([$novyVlastnikId, $postavaId, $svetId]);
        }
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'pridat_polozku') {
        // PJ dá postavě startovní předmět/lektvar/kouzlo — v1 jednoduše
        // podle ID z katalogu (viz editor.php?tabulka=... pro dohledání ID),
        // ne přes plný výběrový katalog, ať je formulář malý (viz konverzace).
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin přidává položky postavě.'); }
        $cilovaPostavaId = (int)($_POST['postava_id'] ?? 0);
        $typPolozky = (string)($_POST['typ_polozky'] ?? '');
        $polozkaId = (int)($_POST['polozka_id'] ?? 0);
        $mnozstvi = max(1, (int)($_POST['mnozstvi'] ?? 1));

        $cfg = dracak_vtt_polozka_config($typPolozky);
        if (!$cfg) { http_response_code(422); die('Neplatný typ položky.'); }

        $stmt = dracak_db()->prepare('SELECT 1 FROM postavy WHERE id = ? AND svet_id = ?');
        $stmt->execute([$cilovaPostavaId, $svetId]);
        if (!$stmt->fetchColumn()) { http_response_code(404); die('Postava nenalezena v tomhle světě.'); }

        if (!dracak_vtt_polozka_katalog($typPolozky, $polozkaId)) {
            http_response_code(404);
            die('Položka s tímhle ID v katalogu neexistuje.');
        }

        if ($typPolozky === 'kouzlo') {
            $ins = dracak_db()->prepare('INSERT IGNORE INTO postava_zna_kouzlo (postava_id, kouzlo_id) VALUES (?, ?)');
            $ins->execute([$cilovaPostavaId, $polozkaId]);
        } else {
            $ins = dracak_db()->prepare(
                "INSERT INTO {$cfg['inventar']} (postava_id, {$cfg['fk']}, mnozstvi) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE mnozstvi = mnozstvi + VALUES(mnozstvi)"
            );
            $ins->execute([$cilovaPostavaId, $polozkaId, $mnozstvi]);
        }
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'pridat_poznamku') {
        // Kdokoli s přístupem do světa (PJ i hráč) — žádná role-based
        // výjimka, viz includes/vtt_poznamky.php.
        $text = trim((string)($_POST['text'] ?? ''));
        if ($text === '') { http_response_code(422); die('Poznámka nemůže být prázdná.'); }
        $sdileno = array_map('intval', (array)($_POST['sdileno'] ?? []));
        // Místo nepovinné, musí ale patřit do TOHOTO světa — jinak by
        // šlo poznámku navázat na mapu z cizího světa.
        $mapaId = !empty($_POST['mapa_id']) ? (int)$_POST['mapa_id'] : null;
        if ($mapaId !== null) {
            $stmt = dracak_db()->prepare('SELECT 1 FROM mapy WHERE id = ? AND svet_id = ?');
            $stmt->execute([$mapaId, $svetId]);
            if (!$stmt->fetchColumn()) { $mapaId = null; }
        }
        dracak_vtt_poznamka_pridat(dracak_db(), $svetId, (int)$user['id'], $text, $sdileno, (int)$svet['aktualni_den_offset'], $mapaId);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'smazat_poznamku') {
        dracak_vtt_poznamka_smazat(dracak_db(), (int)($_POST['poznamka_id'] ?? 0), $user);
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

// Vlastní zatím nepřiřazené postavy (viz hra/postavy_moje.php) — nabídka
// "připojit existující" místo zakládání nové od nuly.
$mojeVolnePostavy = dracak_db()->prepare('SELECT id, nazev FROM postavy WHERE vlastnik_ucet_id = ? AND svet_id IS NULL ORDER BY nazev');
$mojeVolnePostavy->execute([$user['id']]);
$mojeVolnePostavy = $mojeVolnePostavy->fetchAll();

// Inventář za postavu — jen krátký přehled do karty, ne plná správa (ta je
// přes hra/api/pouzij_predmet.php na mapě). Zvlášť dotaz na postavu (max pár
// desítek postav ve světě), ne JOIN přes 3 tabulky najednou.
$inventarePostav = [];
foreach ($postavy as $p) {
    $inventarePostav[(int)$p['id']] = dracak_vtt_inventar('postava', (int)$p['id']);
}

$poznamky = dracak_vtt_poznamky_viditelne(dracak_db(), $user, $svetId);
$moznostiSdileni = dracak_vtt_poznamky_moznosti_sdileni(dracak_db(), $svetId, (int)$user['id']);

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
        'SELECT u.id, u.jmeno FROM svet_hraci sh JOIN ucty u ON u.id = sh.ucet_id WHERE sh.svet_id = ? ORDER BY u.jmeno'
    );
    $stmt->execute([$svetId]);
    $hraciVeSvete = $stmt->fetchAll();
}

dracak_vtt_page_start($svet['nazev'], $user);
?>
  <main class="main" style="padding:24px;">
    <p class="crumb"><a href="svety.php">← Světy</a></p>
    <h1 class="page-title">
      <?= htmlspecialchars($svet['nazev']) ?>
      <?php if ($isPjOrAdmin): ?>
        <a class="btn btn-ghost" style="font-size:13px;vertical-align:middle;" href="svet_administrace.php?id=<?= $svetId ?>">⚙ Administrace</a>
      <?php endif; ?>
    </h1>

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
        <button class="btn btn-primary" type="submit">Uložit</button>
      </form>
      <?php else: ?>
        <h3 class="rel-label">Kde jsme skončili</h3>
        <p><?= nl2br(htmlspecialchars((string)($svet['posledni_shrnuti'] ?? '—'))) ?></p>
      <?php endif; ?>
    </div>

    <div class="section-grid" style="margin-top:28px;">
    <div>

    <h2 class="page-title" style="font-size:20px;">Mapy</h2>
    <?php if (!$mapy): ?>
      <div class="empty-state">Zatím žádná mapa.</div>
    <?php else: ?>
      <div class="records-grid">
        <?php foreach ($mapy as $m): ?>
          <div class="card elev-sm rec-card">
            <h3 class="rec-title"><a href="mapa.php?id=<?= (int)$m['id'] ?>" style="text-decoration:none;color:inherit;"><?= htmlspecialchars($m['nazev']) ?></a></h3>
            <div class="rec-grid">
              <div class="k">Typ:</div><div><?= $m['typ_mapy'] === 'svet' ? 'světová' : 'zóna' ?></div>
              <?php if ($m['grid_velikost_px']): ?>
              <div class="k">Grid:</div><div><?= $m['grid_typ'] === 'hex' ? 'hex' : 'čtverec' ?>, <?= (int)$m['grid_velikost_px'] ?> px</div>
              <?php endif; ?>
            </div>
            <div class="rec-actions"><a class="btn btn-secondary" href="mapa.php?id=<?= (int)$m['id'] ?>">Otevřít</a></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($isPjOrAdmin): ?>
      <details style="margin-top:12px;">
        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-accent-700);">+ Přidat mapu</summary>
      <form method="post" enctype="multipart/form-data" class="card elev-sm" style="max-width:520px;margin-top:10px;">
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
        <h3 class="rel-label">Grid (nepovinné)</h3>
        <p class="note" style="margin:-4px 0 10px;">Prázdná velikost = bez gridu, stejné jako dosud.</p>
        <div class="field"><label for="grid_velikost_px">Velikost pole (px)</label>
          <input class="input" type="number" id="grid_velikost_px" name="grid_velikost_px" min="1" step="1" placeholder="např. 70">
        </div>
        <div class="field"><label for="grid_typ">Typ gridu</label>
          <select id="grid_typ" name="grid_typ">
            <option value="ctverec">Čtverec</option>
            <option value="hex">Hex</option>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div class="field"><label for="grid_posun_x">Posun X (px)</label>
            <input class="input" type="number" id="grid_posun_x" name="grid_posun_x" step="1" value="0"></div>
          <div class="field"><label for="grid_posun_y">Posun Y (px)</label>
            <input class="input" type="number" id="grid_posun_y" name="grid_posun_y" step="1" value="0"></div>
        </div>
        <button class="btn btn-primary" type="submit">Přidat mapu</button>
      </form>
      </details>
    <?php endif; ?>

    <h2 class="page-title" style="font-size:20px;margin-top:28px;">Poznámky</h2>
    <p class="note" style="margin-top:-6px;">Kdokoli u stolu si může napsat poznámku a vybrat, komu konkrétnímu ji ukáže — PJ ji vidí jen když mu ji někdo nasdílí, stejně jako kterýkoliv jiný hráč.</p>
    <?php if (!$poznamky): ?>
      <div class="empty-state">Zatím žádná poznámka, kterou bys viděl.</div>
    <?php else: ?>
      <div class="records-grid">
        <?php foreach ($poznamky as $p): $jeAutor = (int)$p['autor_ucet_id'] === (int)$user['id'];
          $dniPred = (int)$svet['aktualni_den_offset'] - (int)$p['den_pri_vytvoreni']; ?>
          <div class="card elev-sm rec-card">
            <div class="rec-grid">
              <div class="k">Autor:</div><div><?= htmlspecialchars($p['autor_jmeno']) ?></div>
              <div class="k">Kdy:</div><div><?= dracak_vtt_pocet_dni_text($dniPred) ?></div>
              <?php if ($p['mapa_nazev']): ?>
              <div class="k">Místo:</div><div><?= htmlspecialchars($p['mapa_nazev']) ?></div>
              <?php endif; ?>
            </div>
            <p style="margin:8px 0;"><?= nl2br(htmlspecialchars($p['text'])) ?></p>
            <?php if ($jeAutor || $user['role'] === 'admin'): ?>
              <p class="note" style="margin:0 0 8px;">Nasdíleno: <?= $p['sdileno_s'] ? htmlspecialchars(implode(', ', $p['sdileno_s'])) : 'nikomu (jen ty)' ?></p>
              <form method="post" onsubmit="return confirm('Smazat tuhle poznámku?');">
                <input type="hidden" name="akce" value="smazat_poznamku">
                <input type="hidden" name="poznamka_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-ghost" type="submit" style="font-size:12px;">Smazat</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" class="card elev-sm" style="margin-top:14px;">
      <input type="hidden" name="akce" value="pridat_poznamku">
      <div class="field"><label for="poznamka_text">Nová poznámka</label>
        <textarea id="poznamka_text" name="text" required></textarea>
      </div>
      <?php if ($mapy): ?>
      <div class="field"><label for="poznamka_mapa_id">Místo (nepovinné)</label>
        <select class="input" id="poznamka_mapa_id" name="mapa_id">
          <option value="">— bez místa —</option>
          <?php foreach ($mapy as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['nazev']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if ($moznostiSdileni): ?>
      <div class="field"><label>Nasdílet komu (nepovinné, lze víc)</label>
        <?php foreach ($moznostiSdileni as $m): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;margin-bottom:4px;">
            <input type="checkbox" name="sdileno[]" value="<?= (int)$m['id'] ?>"> <?= htmlspecialchars($m['jmeno']) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p class="note">Nikdo jiný ve světě zatím není, komu by šlo sdílet.</p>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit">Uložit poznámku</button>
    </form>

    </div>
    <div>

    <?php if ($isPjOrAdmin): ?>
    <h2 class="page-title" style="font-size:20px;">Hráči u stolu</h2>
    <div class="card elev-sm">
      <p><?= $hraciVeSvete ? htmlspecialchars(implode(', ', array_column($hraciVeSvete, 'jmeno'))) : 'Zatím žádný hráč nepřidán.' ?></p>
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
              <?php if ($p['sila'] !== null): ?>
              <div class="k">Atributy:</div><div style="font-size:12.5px;">
                S <?= (int)$p['sila'] ?> · Obr <?= (int)$p['obratnost'] ?> · Odl <?= (int)$p['odolnost'] ?> · Int <?= (int)$p['inteligence'] ?> · Cha <?= (int)$p['charisma'] ?>
              </div>
              <?php endif; ?>
            </div>
            <?php if ($isPjOrAdmin || (int)$p['vlastnik_ucet_id'] === (int)$user['id']): ?>
              <div class="rec-actions"><a class="btn btn-secondary" href="postava.php?id=<?= (int)$p['id'] ?>">Upravit</a></div>
            <?php endif; ?>
            <?php $inv = $inventarePostav[(int)$p['id']] ?? []; if ($inv): ?>
              <h4 class="rel-label" style="margin-top:10px;">Inventář</h4>
              <ul style="margin:0;padding-left:18px;font-size:12.5px;">
                <?php foreach ($inv as $i): ?>
                  <li>
                    <?= htmlspecialchars($i['nazev']) ?>
                    <?php if ($i['typ_polozky'] !== 'kouzlo'): ?>× <?= (int)$i['mnozstvi'] ?><?php endif; ?>
                    <span style="opacity:.6;">(<?= htmlspecialchars($i['typ_polozky']) ?>)</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if ($isPjOrAdmin && count($hraciVeSvete) > 1): ?>
              <form method="post" style="display:flex;gap:6px;margin-top:8px;">
                <input type="hidden" name="akce" value="prevest_postavu">
                <input type="hidden" name="postava_id" value="<?= (int)$p['id'] ?>">
                <select class="input" name="novy_vlastnik_id" style="flex:1;font-size:12px;">
                  <?php foreach ($hraciVeSvete as $h): ?>
                    <option value="<?= (int)$h['id'] ?>" <?= (int)$h['id'] === (int)$p['vlastnik_ucet_id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['jmeno']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-ghost" type="submit" style="font-size:12px;">Přiřadit</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <details style="margin-top:12px;">
      <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-accent-700);">+ Založit / připojit postavu</summary>
    <div class="card elev-sm" style="margin-top:10px;">
      <p style="margin:0 0 10px;">
        <a class="btn btn-primary" href="postava_nova.php?id=<?= $svetId ?>">+ Nová postava (podle pravidel — rasa, povolání, hod na atributy)</a>
      </p>
      <p class="note" style="margin:0;">Krok za krokem nahodí atributy i život podle pravidel (h104), hod jde vždycky ručně přepsat. Formulář níž je rychlá/nouzová cesta beze hodu — hodí se třeba na bleskové založení NPC.</p>
    </div>
    <?php if ($mojeVolnePostavy): ?>
    <form method="post" class="card elev-sm" style="margin-top:10px;display:flex;gap:8px;align-items:flex-end;">
      <input type="hidden" name="akce" value="pripojit_postavu">
      <div class="field" style="flex:1;margin:0;"><label for="pripojit_postava_id">Nebo připoj svoji už založenou postavu</label>
        <select class="input" id="pripojit_postava_id" name="postava_id">
          <?php foreach ($mojeVolnePostavy as $pp): ?>
            <option value="<?= (int)$pp['id'] ?>"><?= htmlspecialchars($pp['nazev']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-secondary" type="submit">Připojit</button>
    </form>
    <?php endif; ?>
    <form method="post" class="card elev-sm" style="margin-top:10px;">
      <input type="hidden" name="akce" value="nova_postava">
      <div class="field"><label for="p_nazev">Jméno postavy *</label>
        <input class="input" type="text" id="p_nazev" name="nazev" required>
      </div>
      <?php if ($isPjOrAdmin && $hraciVeSvete): ?>
      <div class="field"><label for="vlastnik_ucet_id">Založit pro hráče</label>
        <select id="vlastnik_ucet_id" name="vlastnik_ucet_id">
          <option value="">Sebe (<?= htmlspecialchars($user['jmeno']) ?>)</option>
          <?php foreach ($hraciVeSvete as $h): ?>
            <option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['jmeno']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
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
      <h3 class="rel-label">Atributy (stupeň, nepovinné)</h3>
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;">
        <div class="field"><label for="sila">Síla</label>
          <input class="input" type="number" id="sila" name="sila" min="1" max="30"></div>
        <div class="field"><label for="obratnost">Obratnost</label>
          <input class="input" type="number" id="obratnost" name="obratnost" min="1" max="30"></div>
        <div class="field"><label for="odolnost">Odolnost</label>
          <input class="input" type="number" id="odolnost" name="odolnost" min="1" max="30"></div>
        <div class="field"><label for="inteligence">Inteligence</label>
          <input class="input" type="number" id="inteligence" name="inteligence" min="1" max="30"></div>
        <div class="field"><label for="charisma">Charisma</label>
          <input class="input" type="number" id="charisma" name="charisma" min="1" max="30"></div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:10px;">Založit postavu</button>
    </form>
    </details>

    <?php if ($isPjOrAdmin && $postavy): ?>
    <details style="margin-top:12px;">
      <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--color-accent-700);">+ Přidat položku postavě</summary>
    <form method="post" class="card elev-sm" style="margin-top:10px;">
      <input type="hidden" name="akce" value="pridat_polozku">
      <p class="note" style="margin-top:0;">
        ID najdeš v katalogu (<a href="../editor.php?tabulka=predmety" target="_blank">předměty</a>,
        <a href="../editor.php?tabulka=lektvary" target="_blank">lektvary</a>,
        <a href="../editor.php?tabulka=kouzla" target="_blank">kouzla</a>) — u záznamu klikni na "Upravit", ID je v URL.
      </p>
      <div class="field"><label for="polozka_postava_id">Postava *</label>
        <select class="input" id="polozka_postava_id" name="postava_id" required>
          <?php foreach ($postavy as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nazev']) ?> (<?= htmlspecialchars($p['vlastnik_jmeno']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="typ_polozky">Typ *</label>
        <select class="input" id="typ_polozky" name="typ_polozky" required>
          <option value="predmet">Předmět</option>
          <option value="lektvar">Lektvar</option>
          <option value="kouzlo">Kouzlo</option>
        </select>
      </div>
      <div class="field"><label for="polozka_id">ID položky v katalogu *</label>
        <input class="input" type="number" id="polozka_id" name="polozka_id" min="1" required>
      </div>
      <div class="field"><label for="polozka_mnozstvi">Množství</label>
        <input class="input" type="number" id="polozka_mnozstvi" name="mnozstvi" min="1" value="1">
        <p class="note" style="margin:4px 0 0;">U kouzla se ignoruje — znalost kouzla se nepočítá na kusy.</p>
      </div>
      <button class="btn btn-primary" type="submit">Přidat</button>
    </form>
    </details>
    <?php endif; ?>

    </div>
    </div>
  </main>
<?php dracak_vtt_page_end(); ?>

