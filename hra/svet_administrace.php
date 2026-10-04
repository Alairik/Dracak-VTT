<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

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

    if ($akce === 'upravit_datum') {
        $novyDatum = (string)($_POST['pocatecni_datum'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $novyDatum)) {
            $stmt = dracak_db()->prepare('UPDATE svet SET pocatecni_datum = ? WHERE id = ?');
            $stmt->execute([$novyDatum, $svetId]);
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
      <p class="note" style="margin:0 0 10px;">Ruční posun — cesta po mapě ani čas v dungeonu se dnes nepočítají automaticky (chybí podklad v přepisu pravidel pro přesné tabulky rychlosti, viz h1634). Kladné číslo posune čas dopředu, záporné ho vrátí zpět (oprava přehmatu); pod nulu offset neklesne.</p>
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
  </main>
<?php dracak_vtt_page_end(); ?>
