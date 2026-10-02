<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_postava.php';

// Wizard pro tvorbu postavy podle pravidel (h104 "Tvorba postavy") —
// rasa -> povolání (jen typ='zakladni', obory/vetev se na 1. úrovni
// nenabízí, odemykají se až na 6. úrovni) -> atributy (nabídnutý poctivý
// hod přes rozsah z h104, vždycky ručně přepsatelný) -> HP (návrh podle
// povolání + bonus za Odolnost, taky přepsatelný) -> jméno + uložení.
//
// Stav mezi kroky se nenosí v session, ale v hidden inputech formuláře
// (žádný JS/AJAX potřeba, celé to jde i přes curl bez prohlížeče) —
// každý další krok posílá dál všechno, co se vyplnilo dřív.
//
// Rychlá/nouzová cesta beze změny zůstává `nova_postava` akce ve
// svet.php (PJ může bleskově založit NPC-ish postavu bez wizardu) —
// tenhle soubor je pro hráče PRIMÁRNÍ cesta, ne náhrada.

$user = dracak_require_login();
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);
// id v URL je nepovinné — bez něj se postava založí zatím bez světa
// (postavy.svet_id NULL, viz migrace 0053) a čeká na pozdější přiřazení
// přes hra/postavy_moje.php. Forms v téhle stránce nemají `action`,
// takže POSTují na stejnou URL a id (pokud bylo) zůstává v query stringu
// po celou dobu wizardu bez nutnosti hidden inputu.
$svetId = (int)($_GET['id'] ?? 0);
$svet = $svetId > 0 ? dracak_vtt_require_svet($user, $svetId) : null;
$pdo = dracak_db();

// Mapování kódu vlastnosti (vlastnosti.kod) na název sloupce v
// `postavy`/jméno POST pole — stejná konvence jako svet.php/postava.php.
const POLE_PODLE_KODU = [
    'Sil' => 'sila',
    'Obr' => 'obratnost',
    'Odl' => 'odolnost',
    'Int' => 'inteligence',
    'Chr' => 'charisma',
];

function wizard_nacti_rasu(PDO $pdo, int $rasaId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM rasy WHERE id = ?');
    $stmt->execute([$rasaId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Jen typ='zakladni' — vetev se na 1. úrovni vůbec nenabízí (odemyká se
// až na 6. úrovni, viz povolani.odemyka_se_od_urovne). Tahle validace
// se opakuje v KAŽDÉM kroku, co dostane povolani_id z hidden inputu, ne
// jen při prvním výběru — hidden pole jde přes curl/devtools přepsat na
// cokoliv, nespoléháme na to, že formulář předtím nabízel jen správné
// možnosti.
function wizard_nacti_zakladni_povolani(PDO $pdo, int $povolaniId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM povolani WHERE id = ? AND typ = 'zakladni'");
    $stmt->execute([$povolaniId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function wizard_int_nebo_null($hodnota): ?int
{
    return $hodnota !== null && $hodnota !== '' ? max(1, (int)$hodnota) : null;
}

// --- Render jednotlivých kroků ---------------------------------------

function wizard_krok1(?array $svet, int $svetId, ?int $vybranaRasaId = null): void
{
    global $pdo;
    $rasy = $pdo->query('SELECT id, nazev FROM rasy ORDER BY nazev')->fetchAll();
    ?>
    <form method="post" class="card elev-sm" style="max-width:480px;">
      <input type="hidden" name="krok" value="1">
      <h2 class="rel-label" style="margin-top:0;">Krok 1 / 5 — Rasa</h2>
      <div class="field"><label for="rasa_id">Rasa *</label>
        <select class="input" id="rasa_id" name="rasa_id" required>
          <option value="">— vyber rasu —</option>
          <?php foreach ($rasy as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === $vybranaRasaId ? 'selected' : '' ?>><?= htmlspecialchars($r['nazev']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit" name="akce" value="dalsi">Pokračovat</button>
    </form>
    <?php
}

function wizard_krok2(int $rasaId, array $rasa, ?int $vybranePovolaniId = null): void
{
    global $pdo;
    // Jen základní povolání (core i homebrew mají stejnou váhu, viz
    // CLAUDE.md) — obory/vetev se na 1. úrovni nedávají do nabídky.
    $povolani = $pdo->query("SELECT id, nazev FROM povolani WHERE typ = 'zakladni' ORDER BY nazev")->fetchAll();
    ?>
    <form method="post" class="card elev-sm" style="max-width:480px;">
      <input type="hidden" name="krok" value="2">
      <input type="hidden" name="rasa_id" value="<?= (int)$rasaId ?>">
      <h2 class="rel-label" style="margin-top:0;">Krok 2 / 5 — Povolání</h2>
      <p class="note" style="margin-top:0;">Rasa: <strong><?= htmlspecialchars($rasa['nazev']) ?></strong></p>
      <div class="field"><label for="povolani_id">Povolání *</label>
        <select class="input" id="povolani_id" name="povolani_id" required>
          <option value="">— vyber povolání —</option>
          <?php foreach ($povolani as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $vybranePovolaniId ? 'selected' : '' ?>><?= htmlspecialchars($p['nazev']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="note" style="margin:4px 0 0;">Jen základní povolání — obory (např. Bojovník, Mág, Paladin...) se odemykají až na 6. úrovni.</p>
      </div>
      <button class="btn btn-ghost" type="submit" name="akce" value="zpet">← Zpět</button>
      <button class="btn btn-primary" type="submit" name="akce" value="dalsi">Pokračovat</button>
    </form>
    <?php
}

function wizard_krok3(int $rasaId, int $povolaniId, array $rasa, array $povolani, array $atributy): void
{
    global $pdo;
    $rozsahy = dracak_vtt_navrzene_rozsahy_atributu($pdo, $rasaId, $povolaniId);
    $nazvy = ['Sil' => 'Síla', 'Obr' => 'Obratnost', 'Odl' => 'Odolnost', 'Int' => 'Inteligence', 'Chr' => 'Charisma'];
    ?>
    <form method="post" class="card elev-sm" style="max-width:560px;">
      <input type="hidden" name="krok" value="3">
      <input type="hidden" name="rasa_id" value="<?= (int)$rasaId ?>">
      <input type="hidden" name="povolani_id" value="<?= (int)$povolaniId ?>">
      <h2 class="rel-label" style="margin-top:0;">Krok 3 / 5 — Atributy</h2>
      <p class="note" style="margin-top:0;">
        <strong><?= htmlspecialchars($rasa['nazev']) ?></strong> / <strong><?= htmlspecialchars($povolani['nazev']) ?></strong>
      </p>
      <p class="note">Rozsah stupně vlastnosti podle pravidel (h104) — "základní" vlastnosti povolání jsou opravené rasovou korekcí, zbylé tři jsou přímo z rasové tabulky. Hod je vždycky možné ručně přepsat.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
        <?php foreach (POLE_PODLE_KODU as $kod => $pole): ?>
          <?php $info = $rozsahy[$kod] ?? null; ?>
          <div class="field">
            <label for="<?= $pole ?>">
              <?= htmlspecialchars($nazvy[$kod]) ?>
              <?php if ($info): ?>
                <span class="note" style="display:block;">rozsah <?= (int)$info['dolni'] ?>–<?= (int)$info['horni'] ?> (<?= $info['zdroj'] === 'povolani' ? 'povolání' : 'rasa' ?>)</span>
              <?php endif; ?>
            </label>
            <input class="input" type="number" id="<?= $pole ?>" name="<?= $pole ?>" min="1" max="30"
                   value="<?= htmlspecialchars((string)($atributy[$pole] ?? '')) ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn btn-secondary" type="submit" name="akce" value="hodit">🎲 Hodit (poctivě)</button>
        <button class="btn btn-ghost" type="submit" name="akce" value="zpet">← Zpět</button>
        <button class="btn btn-primary" type="submit" name="akce" value="dalsi">Pokračovat</button>
      </div>
    </form>
    <?php
}

function wizard_krok4(int $rasaId, int $povolaniId, array $rasa, array $povolani, array $atributy, ?int $maxHp): void
{
    global $pdo;
    if ($maxHp === null) {
        $maxHp = dracak_vtt_navrzene_hp($pdo, $povolani['nazev'], $atributy['odolnost'] ?? null);
    }
    ?>
    <form method="post" class="card elev-sm" style="max-width:480px;">
      <input type="hidden" name="krok" value="4">
      <input type="hidden" name="rasa_id" value="<?= (int)$rasaId ?>">
      <input type="hidden" name="povolani_id" value="<?= (int)$povolaniId ?>">
      <?php foreach (POLE_PODLE_KODU as $kod => $pole): ?>
        <input type="hidden" name="<?= $pole ?>" value="<?= htmlspecialchars((string)($atributy[$pole] ?? '')) ?>">
      <?php endforeach; ?>
      <h2 class="rel-label" style="margin-top:0;">Krok 4 / 5 — Život (HP)</h2>
      <p class="note" style="margin-top:0;">
        Návrh podle povolání (h106 TABULKA ŽIVOTŮ) + bonus/postih za Odolnost. Přepiš, pokud je potřeba.
      </p>
      <div class="field"><label for="max_hp">Max. život</label>
        <input class="input" type="number" id="max_hp" name="max_hp" min="1" value="<?= htmlspecialchars((string)($maxHp ?? '')) ?>">
      </div>
      <button class="btn btn-ghost" type="submit" name="akce" value="zpet">← Zpět</button>
      <button class="btn btn-primary" type="submit" name="akce" value="dalsi">Pokračovat</button>
    </form>
    <?php
}

function wizard_krok5(
    int $svetId, int $rasaId, int $povolaniId, array $atributy, int $maxHp,
    bool $isPjOrAdmin, array $hraciVeSvete, string $jmeno = ''
): void {
    ?>
    <form method="post" class="card elev-sm" style="max-width:480px;">
      <input type="hidden" name="krok" value="5">
      <input type="hidden" name="rasa_id" value="<?= (int)$rasaId ?>">
      <input type="hidden" name="povolani_id" value="<?= (int)$povolaniId ?>">
      <?php foreach (POLE_PODLE_KODU as $kod => $pole): ?>
        <input type="hidden" name="<?= $pole ?>" value="<?= htmlspecialchars((string)($atributy[$pole] ?? '')) ?>">
      <?php endforeach; ?>
      <input type="hidden" name="max_hp" value="<?= (int)$maxHp ?>">
      <h2 class="rel-label" style="margin-top:0;">Krok 5 / 5 — Jméno</h2>
      <div class="field"><label for="nazev">Jméno postavy *</label>
        <input class="input" type="text" id="nazev" name="nazev" required value="<?= htmlspecialchars($jmeno) ?>">
      </div>
      <?php if ($isPjOrAdmin && $hraciVeSvete): ?>
      <div class="field"><label for="vlastnik_ucet_id">Založit pro hráče</label>
        <select id="vlastnik_ucet_id" name="vlastnik_ucet_id">
          <option value="">Sebe</option>
          <?php foreach ($hraciVeSvete as $h): ?>
            <option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['jmeno']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <button class="btn btn-ghost" type="submit" name="akce" value="zpet">← Zpět</button>
      <button class="btn btn-primary" type="submit" name="akce" value="ulozit">Založit postavu</button>
    </form>
    <?php
}

// --- Dispatch ---------------------------------------------------------

$krok = (int)($_POST['krok'] ?? 0);
$akce = (string)($_POST['akce'] ?? '');

$atributyZPostu = [];
foreach (POLE_PODLE_KODU as $kod => $pole) {
    $atributyZPostu[$pole] = wizard_int_nebo_null($_POST[$pole] ?? null);
}

dracak_vtt_page_start('Nová postava' . ($svet ? ' — ' . $svet['nazev'] : ''), $user);
?>
<main class="main" style="padding:24px;">
  <?php if ($svet): ?>
  <p class="crumb"><a href="svet.php?id=<?= $svetId ?>">← <?= htmlspecialchars($svet['nazev']) ?></a></p>
  <?php else: ?>
  <p class="crumb"><a href="postavy_moje.php">← Moje postavy</a></p>
  <?php endif; ?>
  <h1 class="page-title">Nová postava</h1>
<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Čerstvý vstup do wizardu (GET) — vždy od kroku 1.
    wizard_krok1($svet, $svetId);
} else {
    $rasaId = (int)($_POST['rasa_id'] ?? 0);
    $povolaniId = (int)($_POST['povolani_id'] ?? 0);

    // `krok` v POSTu = formulář, ze kterého přišel request. `cilovyKrok`
    // = krok, který se má teď vykreslit: "dalsi" o 1 dopředu, "zpet" o 1
    // zpátky (data z předchozích kroků už máme v hidden inputech
    // aktuálního formuláře), "hodit"/"ulozit" zůstávají na místě (reroll
    // na kroku 3 / pokus o uložení na kroku 5).
    if ($akce === 'zpet') {
        $cilovyKrok = max(1, $krok - 1);
    } elseif ($akce === 'dalsi') {
        $cilovyKrok = $krok + 1;
    } else {
        $cilovyKrok = $krok;
    }

    if ($cilovyKrok <= 1) {
        wizard_krok1($svet, $svetId, $rasaId > 0 ? $rasaId : null);
    } elseif ($cilovyKrok === 2) {
        $rasa = $rasaId > 0 ? wizard_nacti_rasu($pdo, $rasaId) : null;
        if (!$rasa) {
            http_response_code(422);
            echo '<div class="card elev-sm"><p>Vyber prosím platnou rasu.</p></div>';
            wizard_krok1($svet, $svetId);
        } else {
            wizard_krok2($rasaId, $rasa, $povolaniId > 0 ? $povolaniId : null);
        }
    } elseif ($cilovyKrok === 3) {
        $rasa = wizard_nacti_rasu($pdo, $rasaId);
        $povolani = $rasa ? wizard_nacti_zakladni_povolani($pdo, $povolaniId) : null;
        if (!$rasa) {
            http_response_code(422);
            echo '<div class="card elev-sm"><p>Vyber prosím platnou rasu.</p></div>';
            wizard_krok1($svet, $svetId);
        } elseif (!$povolani) {
            http_response_code(422);
            echo '<div class="card elev-sm"><p>Vyber prosím platné (základní) povolání.</p></div>';
            wizard_krok2($rasaId, $rasa);
        } else {
            $atributy = $atributyZPostu;
            // Při prvním vstupu do kroku 3 (přišli jsme z kroku 2,
            // "dalsi") i po kliknutí na "Hodit" nabídneme čerstvý
            // poctivý hod — ruční přepsání řeší hráč sám úpravou
            // předvyplněné hodnoty, nikdy se nezakazuje. Při návratu
            // "Zpět" ze 4. kroku se nehází znovu, jen se ukážou dřív
            // zadané/hozené hodnoty z hidden inputů.
            if ($akce === 'hodit' || ($krok === 2 && $akce === 'dalsi')) {
                $hod = dracak_vtt_hod_atributu($pdo, $rasaId, $povolaniId);
                foreach ($hod as $kod => $info) {
                    $atributy[POLE_PODLE_KODU[$kod]] = $info['navrh'];
                }
            }
            wizard_krok3($rasaId, $povolaniId, $rasa, $povolani, $atributy);
        }
    } elseif ($cilovyKrok === 4) {
        $rasa = wizard_nacti_rasu($pdo, $rasaId);
        $povolani = $rasa ? wizard_nacti_zakladni_povolani($pdo, $povolaniId) : null;
        if (!$rasa) {
            http_response_code(422);
            echo '<div class="card elev-sm"><p>Vyber prosím platnou rasu.</p></div>';
            wizard_krok1($svet, $svetId);
        } elseif (!$povolani) {
            http_response_code(422);
            echo '<div class="card elev-sm"><p>Vyber prosím platné (základní) povolání.</p></div>';
            wizard_krok2($rasaId, $rasa);
        } else {
            // Přišli jsme z kroku 3 ("dalsi") -> spočti čerstvý návrh HP
            // z právě zadané Odolnosti. Přišli jsme zpátky z kroku 5
            // ("zpet") -> jen ukaž dřív zadanou hodnotu z hidden inputu.
            $maxHp = ($krok === 3) ? null : (!empty($_POST['max_hp']) ? (int)$_POST['max_hp'] : null);
            wizard_krok4($rasaId, $povolaniId, $rasa, $povolani, $atributyZPostu, $maxHp);
        }
    } elseif ($cilovyKrok >= 5) {
        $rasa = wizard_nacti_rasu($pdo, $rasaId);
        $povolani = wizard_nacti_zakladni_povolani($pdo, $povolaniId);
        $maxHp = max(1, (int)($_POST['max_hp'] ?? 1));
        $hraciVeSvete = [];
        if ($isPjOrAdmin && $svetId > 0) {
            $stmt = $pdo->prepare(
                'SELECT u.id, u.jmeno FROM svet_hraci sh JOIN ucty u ON u.id = sh.ucet_id WHERE sh.svet_id = ? ORDER BY u.jmeno'
            );
            $stmt->execute([$svetId]);
            $hraciVeSvete = $stmt->fetchAll();
        }
        if (!$rasa || !$povolani) {
            http_response_code(422);
            echo '<div class="card elev-sm"><p>Neplatná rasa nebo povolání — zkus to prosím znovu od kroku 1.</p></div>';
            wizard_krok1($svet, $svetId);
        } elseif ($akce === 'ulozit' && $krok === 5) {
            $nazev = trim((string)($_POST['nazev'] ?? ''));
            if ($nazev === '') {
                http_response_code(422);
                echo '<div class="card elev-sm"><p>Postava musí mít jméno.</p></div>';
                wizard_krok5($svetId, $rasaId, $povolaniId, $atributyZPostu, $maxHp, $isPjOrAdmin, $hraciVeSvete, $nazev);
            } else {
                // Stejná vlastnická logika jako akce 'nova_postava' v
                // svet.php: hráč zakládá vždycky sám sobě; PJ/admin smí
                // rovnou pro kohokoliv u stolu (musí být zapsaný v
                // svet_hraci pro tenhle svet).
                $vlastnikId = $user['id'];
                if ($isPjOrAdmin && $svetId > 0 && !empty($_POST['vlastnik_ucet_id'])) {
                    $stmt = $pdo->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
                    $stmt->execute([$svetId, (int)$_POST['vlastnik_ucet_id']]);
                    if ($stmt->fetchColumn()) {
                        $vlastnikId = (int)$_POST['vlastnik_ucet_id'];
                    }
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO postavy (svet_id, vlastnik_ucet_id, nazev, rasa_id, povolani_id, uroven, sila, obratnost, odolnost, inteligence, charisma, aktualni_hp, max_hp)
                     VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $svetId > 0 ? $svetId : null, $vlastnikId, $nazev, $rasaId, $povolaniId,
                    $atributyZPostu['sila'], $atributyZPostu['obratnost'], $atributyZPostu['odolnost'],
                    $atributyZPostu['inteligence'], $atributyZPostu['charisma'],
                    $maxHp, $maxHp,
                ]);
                header($svetId > 0 ? "Location: svet.php?id=$svetId" : 'Location: postavy_moje.php');
                exit;
            }
        } else {
            wizard_krok5($svetId, $rasaId, $povolaniId, $atributyZPostu, $maxHp, $isPjOrAdmin, $hraciVeSvete, (string)($_POST['nazev'] ?? ''));
        }
    }
}
?>
</main>
<?php dracak_vtt_page_end(); ?>
