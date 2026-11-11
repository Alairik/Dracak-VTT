<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_pohyblivost.php';
require_once __DIR__ . '/../includes/vtt_cas.php';

// Administrace světa — PJ-only stránka, kam patří věci, co nejsou
// denní provoz (hráči/mapy/postavy na hra/svet.php), ale nastavení
// světa samotného: automatizace pravidel (dřív přímo na svet.php) a
// herní kalendář (posun času — viz migrace 0060_svet_poznamky.sql,
// komentář u svet.pocatecni_datum/aktualni_den_offset).

$user = dracak_require_login();
$svetId = (int)($_GET['id'] ?? 0);
$svet = dracak_vtt_require_svet($user, $svetId);
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    die('Administrace světa je jen pro PJ/admin.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $akce = $_POST['akce'] ?? '';

    if ($akce === 'automatizace') {
        $stmt = dracak_db()->prepare(
            'UPDATE svet SET auto_hod_kostkou = ?, auto_aplikace_efektu = ?, auto_vyhodnoceni_pasti = ?, auto_zranitelnost = ?
             WHERE id = ?'
        );
        $stmt->execute([
            isset($_POST['auto_hod_kostkou']) ? 1 : 0,
            isset($_POST['auto_aplikace_efektu']) ? 1 : 0,
            isset($_POST['auto_vyhodnoceni_pasti']) ? 1 : 0,
            isset($_POST['auto_zranitelnost']) ? 1 : 0,
            $svetId,
        ]);
        header("Location: svet_administrace.php?id=$svetId");
        exit;
    }

    if ($akce === 'animace') {
        // Sloupec svet.animace přidává migrace 0120 (ruční SQL). Bez něj se nic neukládá a animace běží.
        $maSloupec = (int)dracak_db()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'animace'"
        )->fetchColumn() > 0;
        if ($maSloupec) {
            dracak_db()->prepare('UPDATE svet SET animace = ? WHERE id = ?')
                ->execute([isset($_POST['animace']) ? 1 : 0, $svetId]);
        }
        header("Location: svet_administrace.php?id=$svetId");
        exit;
    }

    if ($akce === 'pohyb') {
        // Fáze 5 (migrace 0077): bez sloupců se nic neukládá.
        $mamSloupce = (int)dracak_db()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'auto_unava'"
        )->fetchColumn() > 0;
        if ($mamSloupce) {
            dracak_db()->prepare('UPDATE svet SET auto_naklad = ?, auto_pohyblivost = ?, auto_unava = ? WHERE id = ?')->execute([
                isset($_POST['auto_naklad']) ? 1 : 0,
                isset($_POST['auto_pohyblivost']) ? 1 : 0,
                isset($_POST['auto_unava']) ? 1 : 0,
                $svetId,
            ]);
        }
        header("Location: svet_administrace.php?id=$svetId");
        exit;
    }

    if ($akce === 'upravit_datum') {
        $novyDatum = dracak_text($_POST['pocatecni_datum'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $novyDatum)) {
            $stmt = dracak_db()->prepare('UPDATE svet SET pocatecni_datum = ? WHERE id = ?');
            $stmt->execute([$novyDatum, $svetId]);
        }
        header("Location: svet_administrace.php?id=$svetId");
        exit;
    }

    if ($akce === 'nastavit_cas_dne') {
        // Čas ve dni přesně (hh:mm po deseti minutách = po směnách); nic nehoří, jde o opravu času (includes/vtt_cas.php).
        if (preg_match('/^(\d{1,2}):(\d{2})$/', (string)($_POST['cas_dne'] ?? ''), $m) && (int)$m[1] < 24 && (int)$m[2] < 60) {
            dracak_vtt_cas_nastav(dracak_db(), $svetId, intdiv((int)$m[1] * 60 + (int)$m[2], 10), (int)$user['id']);
        }
        header("Location: svet_administrace.php?id=$svetId");
        exit;
    }

    if ($akce === 'posunout_cas') {
        // Celé číslo, i záporné (oprava přehmatu) — viz komentář u
        // aktualni_den_offset v migraci, posun je výhradně PJova ruční
        // volba, žádný automatický dopočet z pohybu po mapě.
        $delta = (int)($_POST['delta_dni'] ?? 0);
        if ($delta !== 0) {
            $stmt = dracak_db()->prepare(
                'UPDATE svet SET aktualni_den_offset = GREATEST(0, CAST(aktualni_den_offset AS SIGNED) + ?) WHERE id = ?'
            );
            $stmt->execute([$delta, $svetId]);
        }
        header("Location: svet_administrace.php?id=$svetId");
        exit;
    }
}

$aktualniDatum = (new DateTimeImmutable($svet['pocatecni_datum']))->modify('+' . (int)$svet['aktualni_den_offset'] . ' days');

dracak_vtt_page_start('Administrace — ' . $svet['nazev'], $user);
?>
  <main class="main" style="padding:24px;">
    <p class="crumb"><a href="svet.php?id=<?= $svetId ?>">← <?= htmlspecialchars($svet['nazev']) ?></a></p>
    <h1 class="page-title">Administrace světa</h1>

    <h2 class="page-title" style="font-size:20px;margin-top:20px;">Herní kalendář</h2>
    <div class="card elev-sm" style="max-width:520px;">
      <p style="margin:0 0 10px;">Aktuální herní datum: <strong><?= $aktualniDatum->format('d.m.Y') ?></strong>
        <?php if ((int)$svet['aktualni_den_offset'] > 0): ?>
          <span class="note">(+<?= (int)$svet['aktualni_den_offset'] ?> dní od založení)</span>
        <?php endif; ?>
      </p>
      <form method="post" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:14px;">
        <input type="hidden" name="akce" value="posunout_cas">
        <div class="field" style="flex:1;margin:0;"><label for="delta_dni">Posunout čas o (dní)</label>
          <input class="input" type="number" id="delta_dni" name="delta_dni" step="1" value="1">
        </div>
        <button class="btn btn-primary" type="submit">Posunout</button>
      </form>
      <p class="note" style="margin:0 0 10px;">Ruční posun — čas světa se po cestě sám neposouvá (svět eviduje jen celé dny); délku cesty spočítá kalkulačka cesty, tady posuneš čas podle ní. Kladné číslo posune čas dopředu, záporné ho vrátí zpět (oprava přehmatu); pod nulu offset neklesne.</p>
      <?php $casDne = dracak_vtt_cas_stav(dracak_db(), $svetId); ?>
      <?php if ($casDne !== null): ?>
      <form method="post" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:14px;">
        <input type="hidden" name="akce" value="nastavit_cas_dne">
        <div class="field" style="flex:1;margin:0;"><label for="cas_dne">Čas ve dni (po deseti minutách = jedna směna)</label>
          <input class="input" type="time" id="cas_dne" name="cas_dne" step="600" value="<?= htmlspecialchars(str_pad($casDne['text'], 5, '0', STR_PAD_LEFT)) ?>">
        </div>
        <button class="btn btn-secondary" type="submit">Nastavit</button>
      </form>
      <p class="note" style="margin:0 0 10px;">Hodinu posouváš tlačítky v liště mapy (+1 směna = 10 minut, +1 hodina); posun odečte hoření světel. Tady čas jen opravíš, nic se neodečte.</p>
      <?php endif; ?>
      <form method="post" style="display:flex;gap:8px;align-items:flex-end;">
        <input type="hidden" name="akce" value="upravit_datum">
        <div class="field" style="flex:1;margin:0;"><label for="pocatecni_datum">Počáteční datum</label>
          <input class="input" type="date" id="pocatecni_datum" name="pocatecni_datum" value="<?= htmlspecialchars($svet['pocatecni_datum']) ?>">
        </div>
        <button class="btn btn-secondary" type="submit">Uložit</button>
      </form>
    </div>

    <h2 class="page-title" style="font-size:20px;margin-top:28px;">Automatizace pravidel</h2>
    <p class="note" style="margin-top:-6px;">Co engine spočítá/aplikuje sám, vs. co zůstává na ručním hodu a PJ rozhodnutí.</p>
    <form method="post" class="card elev-sm" style="max-width:520px;">
      <input type="hidden" name="akce" value="automatizace">
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
    <p class="note" style="max-width:520px;">Automatizace zatím jen ukládá nastavení — samotné napojení na kouzla/schopnosti/pasti přijde v další vrstvě.</p>

    <h2 class="page-title" style="font-size:20px;margin-top:28px;">Animace na mapě</h2>
    <p class="note" style="margin-top:-6px;">Plynulý pohyb tokenů, máchnutí zbraní, letící střely a kouzla, hlášení „kdo je na tahu“. Vypnutím zůstane všechno, jen se to děje skokem.</p>
    <?php $maAnimaci = array_key_exists('animace', $svet); ?>
    <form method="post" class="card elev-sm" style="max-width:520px;">
      <input type="hidden" name="akce" value="animace">
      <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;margin-bottom:10px;">
        <input type="checkbox" name="animace" <?= !$maAnimaci || $svet['animace'] ? 'checked' : '' ?> <?= $maAnimaci ? '' : 'disabled' ?>> Animace souboje zapnuté
      </label>
      <button class="btn btn-primary" type="submit" <?= $maAnimaci ? '' : 'disabled' ?>>Uložit</button>
      <?php if (!$maAnimaci): ?>
        <p class="note" style="margin-bottom:0;">Zapne se po jednorázovém kroku správce (ruční SQL). Do té doby animace běží.</p>
      <?php endif; ?>
    </form>

    <h2 class="page-title" style="font-size:20px;margin-top:28px;">Pohyb, naložení a únava</h2>
    <p class="note" style="margin-top:-6px;">Tohle engine počítá sám (deník postavy, družina, <a href="cesta.php?id=<?= $svetId ?>">kalkulačka cesty</a>). Vypni, co chceš u stolu řešit ručně.</p>
    <?php
      $pohybNast = dracak_vtt_svet_cestovani_nastaveni(dracak_db(), $svetId);
      $pohybSloupce = array_key_exists('auto_unava', $svet);
    ?>
    <?php if (!$pohybSloupce): ?>
      <p class="note" style="max-width:520px;">Přepínače čekají na ruční SQL pro migraci 0077 (docs/sql-k-rucnimu-spusteni.sql).</p>
    <?php else: ?>
    <form method="post" class="card elev-sm" style="max-width:520px;">
      <input type="hidden" name="akce" value="pohyb">
      <label style="display:flex;align-items:flex-start;gap:6px;font-weight:400;font-size:13px;margin-bottom:8px;">
        <input type="checkbox" name="auto_naklad" <?= $pohybNast['auto_naklad'] ? 'checked' : '' ?>>
        <span>Náklad z inventáře (a peníze) se počítá do naložení<br><small class="note">Vypnuto: naložení se nevyhodnocuje, žádný postih.</small></span>
      </label>
      <label style="display:flex;align-items:flex-start;gap:6px;font-weight:400;font-size:13px;margin-bottom:8px;">
        <input type="checkbox" name="auto_pohyblivost" <?= $pohybNast['auto_pohyblivost'] ? 'checked' : '' ?>>
        <span>Pohyblivost se počítá z rasy, obratnosti a síly<br><small class="note">Vypnuto: pohyblivost zadáš ručně u každé postavy v deníku (zjednodušení).</small></span>
      </label>
      <label style="display:flex;align-items:flex-start;gap:6px;font-weight:400;font-size:13px;margin-bottom:14px;">
        <input type="checkbox" name="auto_unava" <?= $pohybNast['auto_unava'] ? 'checked' : '' ?>>
        <span>Únava ubírá bonusy a stupně (Tabulka únavy)<br><small class="note">Vypnuto: únava se jen eviduje, postihy aplikuješ ručně.</small></span>
      </label>
      <button class="btn btn-primary" type="submit">Uložit</button>
    </form>
    <?php endif; ?>
    <!-- Oblasti: kus světa (mapa a všechno pod ní) se dá vyvézt do souboru a přidat do jiného světa.
         Vyváží se z mapy — v informaci o pinu je „Vyvézt oblast…“. Sem patří jen přidání cizí oblasti. -->
    <h2 class="page-title" style="font-size:20px;margin-top:28px;">Oblasti</h2>
    <p class="note" style="max-width:520px;margin-top:0;">Vyvézt kus světa můžeš i na mapě — v informaci o pinu je
      „Vyvézt oblast…“ a vezme mapu pod pinem a všechno pod ní. Tady se vyváží celý svět najednou.</p>
    <a class="btn btn-secondary" href="api/oblast_export.php?cely_svet=1&amp;svet_id=<?= (int)$svetId ?>"
       style="margin-bottom:18px;display:inline-block;">Vyvézt celý svět…</a>

    <h3 class="page-title" style="font-size:17px;margin-top:10px;">Přidat oblast ze souboru</h3>
    <p class="note" style="max-width:520px;margin-top:0;">Přidá do tohohle světa kus jiného světa — mapu a všechno pod ní
      (mapy, na které z ní vedou piny), i s nestvůrami, zdmi a podklady. Postavy a hráči se nepřenášejí.
      Nejdřív se ukáže, co balíček obsahuje, a teprve pak se přidá.</p>
    <div class="card elev-sm" style="max-width:520px;">
      <input class="input" type="file" id="oblastSoubor" accept=".zip,application/zip" style="width:100%;">
      <div style="display:flex;gap:6px;margin-top:10px;">
        <button class="btn btn-secondary" type="button" id="oblastKontrolaBtn" style="flex:1;">Co je v souboru?</button>
        <button class="btn btn-primary" type="button" id="oblastPridatBtn" style="flex:1;" disabled>Přidat do světa</button>
      </div>
      <div id="oblastVysledek" class="note" style="margin-top:10px;"></div>
    </div>
  </main>
<script nonce="<?= dracak_csp_nonce() ?>" type="module" src="../assets/js/oblast_import.js?v=<?= filemtime(__DIR__ . '/../assets/js/oblast_import.js') ?>"></script>
<?php dracak_vtt_page_end(); ?>
