<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_denik.php';
require_once __DIR__ . '/../includes/vtt_cestovani.php';

// Kalkulačka cesty družiny (jen PJ/admin světa): vzdálenost + povrch/terén/
// vlivy + tempo -> hodinová simulace, kolik hodin cesta zabere, kdy se
// družina zpomalí únavou a kdo ji první zastaví. Pravidla a ověření viz
// includes/vtt_cestovani.php. Výpočet běží na serveru (hra/api/cesta_vypocet.php).

$user = dracak_require_login();
$svetId = (int)($_GET['id'] ?? 0);
$svet = dracak_vtt_require_svet($user, $svetId);
if (!dracak_vtt_je_pj_sveta($user, $svetId)) {
    http_response_code(403);
    dracak_vtt_page_start('Cesta', $user, true, ['../assets/css/denik.css', '../assets/css/cesta.css']);
    echo '<div class="dn-wrap"><div class="dn-karta"><p>Kalkulačku cesty smí použít jen PJ světa.</p><a class="btn btn-secondary" href="svet.php?id=' . $svetId . '">← Zpět do světa</a></div></div>';
    dracak_vtt_page_end();
    exit;
}
$pdo = dracak_db();

$stmt = $pdo->prepare(
    'SELECT p.*, r.nazev AS rasa_nazev FROM postavy p LEFT JOIN rasy r ON r.id = p.rasa_id
     WHERE p.svet_id = ? ORDER BY p.nazev'
);
$stmt->execute([$svetId]);
$postavy = $stmt->fetchAll();
$zvirata = [];
try {
    $zvirata = dracak_vtt_jizdni_zvirata($pdo);
} catch (PDOException $e) {
    // tabulka jizdni_zvirata ještě není (migrace 0067) — kalkulačka jede jen pěšky
}
$tabulkyRychlosti = dracak_vtt_rychlost_pohybu($pdo, 0) !== null;

$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$cislo = static function ($n): string {
    return rtrim(rtrim(number_format((float)$n, 1, ',', ' '), '0'), ',');
};
$radky = [];
foreach ($postavy as $p) {
    $d = dracak_vtt_denik_data($pdo, $p, false);
    $mozne = ($p['odolnost'] ?? null) !== null && (int)$p['odolnost'] > 0 && $d['pohyblivost'] !== null;
    $radky[] = ['p' => $p, 'd' => $d, 'mozne' => $mozne];
}

dracak_vtt_page_start('Cesta — ' . $svet['nazev'], $user, true, ['../assets/css/denik.css', '../assets/css/cesta.css']);
?>
<div class="dn-wrap cs-wrap" data-svet-id="<?= $svetId ?>">
<div class="dn" id="cesta">
  <header class="dn-pas dz-pas">
    <div class="dn-hlava">
      <div class="dn-id">
        <div class="dn-nav-horni">
          <a class="svet-pill svet-pill-ghost" href="svet.php?id=<?= $svetId ?>">← <?= $e($svet['nazev']) ?></a>
          <a class="svet-pill svet-pill-ghost" href="druzina.php?id=<?= $svetId ?>">Družina</a>
        </div>
        <h1 class="dn-jmeno">Cesta družiny</h1>
        <div class="dn-pod">Kolik hodin cesta zabere, kdy družinu zpomalí únava a kdo ji zastaví první. Rychlost družiny je rychlost nejpomalejšího.</div>
      </div>
    </div>
  </header>

  <?php if (!$tabulkyRychlosti): ?>
    <div class="dn-karta"><p class="note"><strong>Tabulky rychlosti pohybu na databázi ještě nejsou</strong> (čeká ruční SQL pro migraci 0074, viz docs/sql-k-rucnimu-spusteni.sql) — kalkulačka zatím nic nespočítá.</p></div>
  <?php endif; ?>

  <form id="cs-formular" class="dn-grid" autocomplete="off">
    <div class="dn-karta">
      <h3 class="dn-h3">Trasa</h3>
      <div class="field"><label for="cs-vzdalenost">Vzdálenost (míle)</label>
        <input type="number" id="cs-vzdalenost" min="0.1" max="5000" step="0.1" value="20"></div>

      <div class="field"><label for="cs-povrch-vyber">Povrch (body chůze na míli)</label>
        <select id="cs-povrch-vyber" data-vyber="povrch">
          <?php foreach (DRACAK_VTT_POVRCHY as [$nazev, $od, $do]): $vychozi = (int)round(($od + $do) / 2); ?>
            <option value="<?= $vychozi ?>" data-od="<?= $od ?>" data-do="<?= $do ?>"><?= $e($nazev) ?> (<?= $od === $do ? $od : $od . '–' . $do ?>)</option>
          <?php endforeach; ?>
        </select>
        <input type="number" id="cs-povrch" min="1" max="200" step="any" value="10" aria-label="Náročnost povrchu">
      </div>

      <div class="field"><label for="cs-teren-vyber">Terén (násobek náročnosti)</label>
        <select id="cs-teren-vyber" data-vyber="teren">
          <?php foreach (DRACAK_VTT_TERENY as [$nazev, $od, $do]): $vychozi = $do === null ? $od : ($od + $do) / 2; ?>
            <option value="<?= $vychozi ?>"><?= $e($nazev) ?> (<?= $do === null ? $od . '× a více' : ($od === $do ? $od . '×' : $od . '×–' . $do . '×') ?>)</option>
          <?php endforeach; ?>
        </select>
        <input type="number" id="cs-teren" min="1" max="20" step="any" value="1" aria-label="Násobek terénu">
      </div>

      <div class="field"><label for="cs-vliv-vyber">Ostatní vlivy (počasí, světlo — násobek)</label>
        <select id="cs-vliv-vyber" data-vyber="vliv">
          <?php foreach (DRACAK_VTT_VLIVY as [$nazev, $od, $do]): $vychozi = ($od + $do) / 2; ?>
            <option value="<?= $vychozi ?>"><?= $e($nazev) ?> (<?= $od === $do ? $od . '×' : 'až ' . $do . '×' ?>)</option>
          <?php endforeach; ?>
        </select>
        <input type="number" id="cs-vliv" min="1" max="20" step="any" value="1" aria-label="Násobek vlivů">
      </div>
      <p class="note">Čísla v knize jsou rozsahy a orientační — volí je PJ (PJ pravidla, Náročnost cesty). Výběr jen předvyplní střed rozsahu, číslo můžeš přepsat. Náročnost = povrch × terén × vlivy: <strong id="cs-narocnost">10</strong> bodů na míli.</p>
    </div>

    <div class="dn-karta">
      <h3 class="dn-h3">Tempo a zastavení</h3>
      <div class="field cs-radia">
        <?php foreach (DRACAK_VTT_TEMPA_CHUZE as $kod => $t): ?>
          <label><input type="radio" name="cs-tempo" value="<?= $kod ?>" <?= $kod === 'chuze' ? 'checked' : '' ?>> <?= $e($t['nazev']) ?> <small>(<?= $t['unava_za_hodinu'] == 0.5 ? '1 bod únavy za 2 h' : ($t['unava_za_hodinu'] == 1.0 ? '1 bod za hodinu' : '1 bod za ⅓ h') ?>)</small></label>
        <?php endforeach; ?>
      </div>
      <div class="field"><label for="cs-zastav">Úsek skončí těsně před tím, než někdo bude</label>
        <select id="cs-zastav">
          <option value="1">unavený (nad 1× vytrvalosti)</option>
          <option value="2" selected>vyčerpaný (nad 2× vytrvalosti)</option>
          <option value="3">úplně vyčerpaný (nad 3× vytrvalosti)</option>
        </select>
      </div>
      <p class="note">Poklus, běh a sprint se v knize počítají na kola, ne na hodiny — tady jsou jen tempa chůze. Kniha neříká, kolik hodin denně se pochoduje, proto se simuluje po hodinách až do cíle nebo do zvoleného stupně únavy; co dál (odpočinek, spánek), rozhodni ty.</p>
    </div>

    <div class="dn-karta dn-sirka-cela">
      <h3 class="dn-h3">Kdo cestuje</h3>
      <?php if (!$radky): ?><p class="note">Ve světě zatím nejsou žádné postavy.</p><?php else: ?>
      <div class="dn-tab-wrap"><table class="dn-tab">
        <thead><tr><th></th><th>Postava</th><th class="c">Únava</th><th class="c">Náklad</th><th class="c">Pohyblivost</th><th>Doprava</th><th class="c">Váha jezdce (mn)</th></tr></thead>
        <tbody>
        <?php foreach ($radky as $r): $p = $r['p']; $d = $r['d']; $un = $d['unava']; $poh = $d['pohyblivost']; $zv = $d['doprava'] && $d['doprava']['typ'] === 'zvire' ? $d['doprava']['zvire'] : null; ?>
          <tr data-postava="<?= (int)$p['id'] ?>" class="<?= $r['mozne'] ? '' : 'cs-nelze' ?>">
            <td><input type="checkbox" class="cs-ucastnik" <?= $r['mozne'] ? 'checked' : 'disabled' ?> aria-label="Cestuje <?= $e($p['nazev']) ?>"></td>
            <td><strong><?= $e($p['nazev']) ?></strong><div class="note"><?= $e($p['rasa_nazev'] ?? '') ?></div>
              <?php if (!$r['mozne']): ?><div class="note">chybí odolnost, rasa, síla nebo obratnost — nejde spočítat</div><?php endif; ?></td>
            <td class="c"><?= $un ? (int)$un['body'] . ' / ' . (int)$un['vytrvalost'] : '—' ?><?= $un && $un['stupen'] > 0 ? ' <span class="dn-stitek varovani">' . $e($un['nazev']) . '</span>' : '' ?></td>
            <td class="c"><?= $cislo($d['naklad']['naklad_mn']) ?> mn<?= $d['naklad']['neuplny'] ? ' <span class="dn-stitek neuplne" title="Součet nákladu je neúplný (položky bez váhy)">?</span>' : '' ?></td>
            <td class="c"><?= $poh && $poh['pohyblivost'] !== null ? (int)$poh['pohyblivost'] : '—' ?></td>
            <td><?= $zv ? $e($zv['nazev']) : 'pěšky' ?></td>
            <td class="c"><?php if ($zv): ?><input type="number" class="cs-vaha" min="0" step="10" value="0" aria-label="Váha jezdce <?= $e($p['nazev']) ?>"><?php else: ?>—<?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <p class="note">Doprava se vybírá u postavy. Zvíře pod jezdcem nese jeho váhu + jeho náklad z inventáře; jezdec dostává třetinu bodů únavy zvířete. Váhu postavy pravidla neuvádějí jako jedno číslo (rozsah podle rasy, např. člověk 1300–2300 mn) a deník ji neeviduje — zadej ji ručně, jinak se nepočítá.</p>
      <?php endif; ?>
    </div>

    <div class="dn-karta dn-sirka-cela">
      <h3 class="dn-h3">Doprovodná zvířata s nákladem</h3>
      <?php if (!$zvirata): ?><p class="note">Číselník jízdních zvířat zatím není na databázi.</p><?php else: ?>
      <div id="cs-nosice"></div>
      <button type="button" class="btn btn-secondary" id="cs-pridat-zvire">+ přidat zvíře</button>
      <p class="note">Zvíře bez jezdce, které nese náklad družiny (packhorse). Jde s družinou a jeho rychlost se počítá do nejpomalejšího.</p>
      <template id="cs-zvire-sablona">
        <div class="cs-zvire">
          <select class="cs-zvire-typ"><?php foreach ($zvirata as $z): ?><option value="<?= (int)$z['id'] ?>"><?= $e($z['nazev']) ?> (nosnost <?= (int)$z['nosnost_mn'] ?> mn)</option><?php endforeach; ?></select>
          <label>náklad <input type="number" class="cs-zvire-naklad" min="0" step="10" value="0"> mn</label>
          <button type="button" class="btn btn-ghost cs-zvire-smazat" aria-label="Odebrat zvíře">✕</button>
        </div>
      </template>
      <?php endif; ?>
    </div>
  </form>

  <div class="dn-akce-cesta">
    <button type="button" class="btn btn-primary" id="cs-spocitat" <?= $tabulkyRychlosti ? '' : 'disabled' ?>>Spočítat cestu</button>
    <span class="cs-stav" id="cs-stav" aria-live="polite"></span>
  </div>

  <section class="dn-karta dn-sirka-cela" id="cs-vysledek" hidden></section>
</div>
</div>
<script src="../assets/js/cesta.js?v=<?= filemtime(__DIR__ . '/../assets/js/cesta.js') ?>"></script>
<?php dracak_vtt_page_end(); ?>
