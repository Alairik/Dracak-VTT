<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_denik.php';
require_once __DIR__ . '/../includes/vtt_predmety.php';

// Osobní deník postavy — hlavní obrazovka hráče (zadání:
// docs/zadani-osobni-denik-design.md, kolonky z content/pravidla-hrac.html
// h1690). Trvale viditelný stavový pás (životy, magenergie, únava,
// naložení, pohyblivost, OČ/ÚČ) + sekce Přehled / Boj / Schopnosti a
// kouzla / Vybavení / Postava. Rychlé zápisy jdou přes
// hra/api/postava_stav.php, stránka se po nich i při pollingu překreslí
// jako fragment (?fragment=1).

$user = dracak_require_login();
$postavaId = (int)($_GET['id'] ?? 0);
$pdo = dracak_db();

$stmt = $pdo->prepare(
    'SELECT p.*, r.nazev AS rasa_nazev, pv.nazev AS povolani_nazev, u.jmeno AS vlastnik_jmeno, s.nazev AS svet_nazev
     FROM postavy p
     LEFT JOIN rasy r ON r.id = p.rasa_id
     LEFT JOIN povolani pv ON pv.id = p.povolani_id
     JOIN ucty u ON u.id = p.vlastnik_ucet_id
     LEFT JOIN svet s ON s.id = p.svet_id
     WHERE p.id = ?'
);
$stmt->execute([$postavaId]);
$postava = $stmt->fetch();
if (!$postava) {
    http_response_code(404);
    die('Postava nenalezena.');
}
$svetId = (int)($postava['svet_id'] ?? 0);
if ($svetId > 0) {
    dracak_vtt_require_svet($user, $svetId);
}
if (!dracak_vtt_can_edit_hp($user, 'postava', $postava)) {
    http_response_code(403);
    die('Tuhle postavu upravovat nesmíš.');
}
$jePj = $user['role'] === 'admin' || ($svetId > 0 && dracak_vtt_je_pj_sveta($user, $svetId));
$smiZkusenost = $jePj || $svetId === 0;

$d = dracak_vtt_denik_data($pdo, $postava);

// --- pomůcky pro šablonu ---
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$znam = static fn(?int $n): string => $n === null ? '—' : (($n > 0 ? '+' : '') . $n);
$mn = static function ($n): string {
    $n = (float)$n;
    return rtrim(rtrim(number_format($n, 1, ',', ' '), '0'), ',');
};
$ma = static fn(string $pole): bool => array_key_exists($pole, $GLOBALS['postava']);
$tlacitka = static function (string $akce, array $kroky, array $extra = []) use ($e): string {
    $out = '';
    foreach ($kroky as $k) {
        $attr = '';
        foreach ($extra as $kl => $hod) {
            $attr .= ' data-' . $e($kl) . '="' . $e($hod) . '"';
        }
        $out .= '<button type="button" class="dn-pm" data-akce="' . $e($akce) . '" data-delta="' . (int)$k . '"' . $attr
            . ' aria-label="' . $e($akce) . ' ' . ($k > 0 ? '+' : '') . (int)$k . '">' . ($k > 0 ? '+' : '−') . abs((int)$k) . '</button>';
    }
    return $out;
};
// Hledání v katalogu pravidel a přidání do inventáře jedním klikem.
$picker = static function (array $typy) use ($e): string {
    $nazvy = ['predmet' => 'předmět', 'lektvar' => 'lektvar', 'kouzlo' => 'kouzlo'];
    $o = '<div class="dn-picker" data-typy="' . $e(implode(',', $typy)) . '">';
    if (count($typy) > 1) {
        $o .= '<select class="dn-picker-typ" aria-label="Druh položky">';
        foreach ($typy as $t) { $o .= '<option value="' . $e($t) . '">' . $e($nazvy[$t]) . '</option>'; }
        $o .= '</select>';
    } else {
        $o .= '<input type="hidden" class="dn-picker-typ" value="' . $e($typy[0]) . '">';
    }
    $o .= '<input type="search" class="dn-picker-q" placeholder="Hledat v katalogu (aspoň 2 znaky)…" autocomplete="off" aria-label="Hledat v katalogu">';
    if ($typy !== ['kouzlo']) {
        $o .= '<input type="number" class="dn-picker-ks" value="1" min="1" max="1000" title="Množství" aria-label="Množství">';
    }
    return $o . '<ul class="dn-picker-vysledky" hidden></ul></div>';
};
$inicialy = (static function (string $jmeno): string {
    $slova = preg_split('/\s+/u', trim($jmeno), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $o = '';
    foreach (array_slice($slova, 0, 2) as $sl) {
        $o .= mb_strtoupper(mb_substr($sl, 0, 1));
    }
    return $o;
})($postava['nazev']);

$maxHp = (int)$postava['max_hp'];
$hp = (int)$postava['aktualni_hp'];
$hpProcent = $maxHp > 0 ? max(0, min(100, (int)round($hp / $maxHp * 100))) : 0;
$boj = $d['bojeschopnost'];
$hpStav = 'ok';
if ($boj && $boj['vyrazena']) {
    $hpStav = 'out';
} elseif ($boj && $boj['pod_tretinou']) {
    $hpStav = 'low';
}
$maMag = dracak_vtt_ma_magenergii($postava);
$unava = $d['unava'];
$poh = $d['pohyblivost'];
$nakl = $d['naklad'];
$nosnost = $poh['nosnost_mn'] ?? null;
$nalozeni = $poh['nalozeni'] ?? null;
$nalozeniNazvy = ['zadne' => 'žádné naložení', 'mirne' => 'mírné naložení', 'stredni' => 'střední naložení', 'velke' => 'velké naložení', 'prilis_velke' => 'nad 4× nosnost'];
$naklPct = $nosnost ? max(0, min(100, $nakl['naklad_mn'] / ($nosnost * 4) * 100)) : 0;
$naklStav = $nalozeni ? ($nalozeni['kod'] === 'zadne' ? 'ok' : ($nalozeni['kod'] === 'mirne' ? 'low' : 'out')) : 'ok';
$velkeNalozeni = $nalozeni && in_array($nalozeni['kod'], ['velke', 'prilis_velke'], true);

// ÚČ pro pás: držená zbraň, jinak jediná zbraň.
$hlavniZbran = null;
foreach ($d['zbrane'] as $z) {
    if ($z['drzena']) { $hlavniZbran = $z; }
}
if ($hlavniZbran === null && count($d['zbrane']) === 1) {
    $hlavniZbran = $d['zbrane'][0];
}

// Výběry zbroje/štítu z inventáře.
$zbrojeInv = [];
$stityInv = [];
foreach ($d['inventar'] as $i) {
    if ($i['typ'] !== 'zbroj') { continue; }
    if (preg_match('/štít|pavéza/ui', $i['nazev'])) {
        $stityInv[] = $i;
    } elseif (!preg_match('/rukavice|přilbice|boty/ui', $i['nazev'])) {
        $zbrojeInv[] = $i;
    }
}

$fragment = isset($_GET['fragment']);
if (!$fragment) {
    dracak_vtt_page_start($postava['nazev'] . ' — deník', $user, true, ['../assets/css/denik.css']);
}
$vlastnostiNazvy = ['sila' => 'Síla', 'obratnost' => 'Obratnost', 'odolnost' => 'Odolnost', 'inteligence' => 'Inteligence', 'charisma' => 'Charisma'];
?>
<?php if (!$fragment): ?>
<div class="dn-wrap" data-postava-id="<?= $postavaId ?>" data-svet-id="<?= $svetId ?>">
<?php endif; ?>
<div class="dn" id="denik">

  <header class="dn-pas">
    <div class="dn-hlava">
      <div class="dn-avatar" aria-hidden="true"><?= $e($inicialy) ?></div>
      <div class="dn-id">
        <div class="dn-nav-horni">
          <a class="svet-pill svet-pill-ghost" href="<?= $svetId > 0 ? 'svet.php?id=' . $svetId : 'postavy_moje.php' ?>">← <?= $svetId > 0 ? $e($postava['svet_nazev']) : 'Moje postavy' ?></a>
          <a class="svet-pill svet-pill-ghost" href="postava.php?id=<?= $postavaId ?>">Upravit základní údaje</a>
          <a class="svet-pill svet-pill-ghost" href="denik_tisk.php?id=<?= $postavaId ?>" target="_blank" rel="noopener">Tisk</a>
        </div>
        <h1 class="dn-jmeno"><?= $e($postava['nazev']) ?></h1>
        <div class="dn-pod">
          <?= $e(implode(' · ', array_filter([
              $postava['rasa_nazev'] ?? null,
              $postava['povolani_nazev'] ?? null,
              (int)$postava['uroven'] . '. úroveň',
              $d['velikost'] ? 'velikost ' . $d['velikost'] : null,
          ]))) ?>
          <span class="dn-pod-sed">· hráč <?= $e($postava['vlastnik_jmeno']) ?></span>
        </div>
      </div>
      <div class="dn-bojcisla">
        <div class="dn-velke" title="Obranné číslo = kvalita zbroje + bonus za obratnost">
          <span class="dn-velke-k">OČ</span>
          <span class="dn-velke-h"><?= $d['obrana'] ? (int)$d['obrana']['oc'] : '—' ?></span>
          <?php if ($d['obrana'] && $d['obrana']['bonus_stit']): ?><span class="dn-velke-p">+1 štít</span><?php endif; ?>
        </div>
        <div class="dn-velke" title="Útočné číslo držené zbraně (síla zbraně + bonus za sílu)">
          <span class="dn-velke-k">ÚČ</span>
          <span class="dn-velke-h"><?= $hlavniZbran && $hlavniZbran['uc_tvar_v_tvar'] !== null ? (int)$hlavniZbran['uc_tvar_v_tvar'] : '—' ?></span>
          <span class="dn-velke-p"><?= $hlavniZbran ? $e($hlavniZbran['nazev']) : 'vyber zbraň' ?></span>
        </div>
      </div>
    </div>

    <div class="dn-metry">
      <!-- Životy -->
      <section class="dn-metr" aria-label="Životy">
        <div class="dn-metr-hlava"><span>Životy</span>
          <?php if ($hpStav === 'out'): ?><span class="dn-stitek nebezpeci">vyřazena z boje</span>
          <?php elseif ($hpStav === 'low'): ?><span class="dn-stitek varovani">postih <?= $znam($boj['postih_pod_tretinou']) ?> k útoku i obraně</span><?php endif; ?>
        </div>
        <div class="dn-metr-cislo"><strong><?= $hp ?></strong><span>/ <?= $maxHp ?></span></div>
        <div class="dn-bar <?= $hpStav ?>" role="meter" aria-valuemin="0" aria-valuemax="<?= $maxHp ?>" aria-valuenow="<?= $hp ?>"><i style="width:<?= $hpProcent ?>%"></i></div>
        <?php if ($boj): ?><div class="dn-metr-pozn">mez vyřazení <?= (int)$boj['mez_vyrazeni'] ?> · postih pod <?= $e(rtrim(rtrim(number_format($maxHp / 3, 1, ',', ''), '0'), ',')) ?> živ.</div><?php endif; ?>
        <div class="dn-pms"><?= $tlacitka('hp', [-5, -1, 1, 5]) ?></div>
      </section>

      <?php if ($maMag): ?>
      <section class="dn-metr" aria-label="Magenergie">
        <div class="dn-metr-hlava"><span>Magenergie</span></div>
        <div class="dn-metr-cislo"><strong><?= (int)$postava['aktualni_magenergie'] ?></strong><span>/ <?= (int)$postava['max_magenergie'] ?></span></div>
        <div class="dn-bar mag"><i style="width:<?= (int)$postava['max_magenergie'] > 0 ? max(0, min(100, round((int)$postava['aktualni_magenergie'] / (int)$postava['max_magenergie'] * 100))) : 0 ?>%"></i></div>
        <div class="dn-metr-pozn">magů</div>
        <div class="dn-pms"><?= $tlacitka('magenergie', [-5, -1, 1, 5]) ?></div>
      </section>
      <?php endif; ?>

      <!-- Únava -->
      <section class="dn-metr" aria-label="Únava">
        <div class="dn-metr-hlava"><span>Únava</span>
          <?php if ($unava && $unava['stupen'] > 0): ?><span class="dn-stitek <?= $unava['stupen'] >= 3 ? 'nebezpeci' : 'varovani' ?>"><?= $e($unava['nazev']) ?></span><?php endif; ?>
        </div>
        <?php if ($unava): ?>
          <div class="dn-metr-cislo"><strong><?= (int)$unava['body'] ?></strong><span>/ <?= (int)$unava['vytrvalost'] ?> vytrvalost</span></div>
          <div class="dn-bar <?= $unava['stupen'] === 0 ? 'ok' : ($unava['stupen'] >= 3 ? 'out' : 'low') ?>"><i style="width:<?= max(0, min(100, round($unava['body'] / (max(1, $unava['vytrvalost']) * 4) * 100))) ?>%"></i><b style="left:25%"></b><b style="left:50%"></b><b style="left:75%"></b></div>
          <div class="dn-metr-pozn"><?= $unava['popis'] ? $e($unava['popis']) : 'bez únavy' ?></div>
        <?php else: ?>
          <div class="dn-metr-pozn">Doplň odolnost (vytrvalost = odolnost).</div>
        <?php endif; ?>
        <?php if ($ma('unava')): ?><div class="dn-pms"><?= $tlacitka('unava', [-5, -1, 1, 5]) ?></div>
        <?php else: ?><div class="dn-metr-pozn">Únava se zatím nedá ukládat (čeká na migraci 0065).</div><?php endif; ?>
      </section>

      <!-- Naložení -->
      <section class="dn-metr dn-metr-siroky" aria-label="Naložení">
        <div class="dn-metr-hlava"><span>Naložení</span>
          <?php if ($nalozeni): ?><span class="dn-stitek <?= $naklStav === 'out' ? 'nebezpeci' : ($naklStav === 'low' ? 'varovani' : '') ?>"><?= $e($nalozeniNazvy[$nalozeni['kod']]) ?></span><?php endif; ?>
          <?php if ($nakl['neuplny']): ?><span class="dn-stitek neuplne" title="Bez váhy: <?= $e(implode(', ', $nakl['bez_vahy'])) ?>">neúplný součet</span><?php endif; ?>
        </div>
        <div class="dn-metr-cislo"><strong><?= $mn($nakl['naklad_mn']) ?></strong><span>mn<?= $nosnost ? ' / nosnost ' . $nosnost : '' ?></span></div>
        <?php if ($nosnost): ?>
        <div class="dn-bar nalozeni <?= $naklStav ?>" aria-label="Náklad v násobcích nosnosti">
          <i style="width:<?= round($naklPct, 1) ?>%"></i>
          <b style="left:25%"><em>×1</em></b><b style="left:50%"><em>×2</em></b><b style="left:75%"><em>×3</em></b>
        </div>
        <div class="dn-metr-pozn"><?= $e(implode(' · ', array_map(fn($h, $k) => $k . ' ' . $h, $d['hranice_nalozeni'], ['žádné do', 'mírné do', 'střední do', 'velké do']))) ?> mn<?= $velkeNalozeni ? ' · −1 k útoku i obraně' : '' ?></div>
        <?php else: ?>
        <div class="dn-metr-pozn">Doplň sílu, aby šla spočítat nosnost.</div>
        <?php endif; ?>
      </section>

      <!-- Pohyblivost -->
      <section class="dn-metr" aria-label="Pohyblivost">
        <div class="dn-metr-hlava"><span>Pohyblivost</span></div>
        <?php if ($poh): ?>
          <div class="dn-metr-cislo"><strong><?= $poh['pohyblivost'] ?? '—' ?></strong><span><?= $poh['bonus_pohyblivosti'] !== null ? 'bonus ' . $znam($poh['bonus_pohyblivosti']) : '' ?></span></div>
          <div class="dn-metr-pozn">základní <?= (int)$poh['zakladni'] ?> = rasa <?= (int)$poh['rasovy_zaklad'] ?>, obr. <?= $znam($poh['bonus_obratnost']) ?>, 2× síla (<?= $znam($poh['bonus_sila']) ?>)<?= $nalozeni && $nalozeni['kod'] !== 'zadne' ? ' · po naložení' : '' ?></div>
          <?php if ($d['doprava'] && $d['doprava']['typ'] === 'zvire'): $z = $d['doprava']['zvire']; ?>
            <div class="dn-metr-pozn">jede na: <strong><?= $e($z['nazev']) ?></strong> (pohyblivost <?= (int)$z['pohyblivost'] ?>)</div>
          <?php endif; ?>
        <?php else: ?>
          <div class="dn-metr-cislo"><strong>—</strong></div>
          <div class="dn-metr-pozn">Doplň rasu, sílu a obratnost.</div>
        <?php endif; ?>
      </section>
    </div>

    <nav class="dn-nav" aria-label="Sekce deníku">
      <a href="#prehled">Přehled</a><a href="#boj">Boj</a><a href="#schopnosti">Schopnosti a kouzla</a><a href="#vybaveni">Vybavení</a><a href="#postava">Postava</a>
    </nav>
  </header>

  <!-- ===================== PŘEHLED ===================== -->
  <section class="dn-sekce" id="prehled">
    <h2 class="dn-h2">Přehled</h2>
    <div class="dn-grid">

      <div class="dn-karta dn-sirka-2">
        <h3 class="dn-h3">Vlastnosti</h3>
        <div class="dn-vlastnosti">
          <?php foreach ($vlastnostiNazvy as $kl => $nazev): ?>
            <div class="dn-vlast">
              <span class="dn-vlast-k"><?= $e($nazev) ?></span>
              <span class="dn-vlast-h"><?= $d['stupne'][$kl] ?? '—' ?></span>
              <span class="dn-vlast-b <?= ($d['bonusy'][$kl] ?? 0) < 0 ? 'minus' : '' ?>"><?= $znam($d['bonusy'][$kl]) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="note">Stupeň vlastnosti (1–21) a z něj bonus/postih podle Tabulky postihů a bonusů.</p>
      </div>

      <div class="dn-karta">
        <h3 class="dn-h3">Postřeh</h3>
        <?php if ($d['postrech']): ?>
          <div class="dn-dvojice"><span>na objekty</span><strong><?= (int)$d['postrech']['objekty'] ?> %</strong></div>
          <div class="dn-dvojice"><span>na mechanismy</span><strong><?= (int)$d['postrech']['mechanismy'] ?> %</strong></div>
          <p class="note">Hledání: hoď k% a odečti postřeh (h1683).</p>
        <?php else: ?><p class="note">Doplň inteligenci.</p><?php endif; ?>
      </div>

      <div class="dn-karta">
        <h3 class="dn-h3">Zkušenost</h3>
        <?php $zk = $d['zkusenost']; ?>
        <?php if ($ma('zkusenost')): ?>
          <div class="dn-metr-cislo"><strong><?= (int)$zk['zkusenost'] ?></strong><span>zt<?= $zk['potrebne'] !== null ? ' / ' . (int)$zk['potrebne'] . ' na ' . (int)$zk['dalsi_uroven'] . '. úroveň' : '' ?></span></div>
          <?php if ($zk['potrebne'] !== null): ?>
            <div class="dn-bar ok"><i style="width:<?= max(0, min(100, round($zk['zkusenost'] / max(1, $zk['potrebne']) * 100))) ?>%"></i></div>
            <div class="dn-metr-pozn"><?= $zk['muze_postoupit'] ? 'Zkušeností je dost — postup je po výcviku (2 měsíce bez dobrodružství, ' . (int)$zk['cena_vycviku_zl'] . ' zl).' : 'Výcvik na další úroveň: ' . (int)$zk['cena_vycviku_zl'] . ' zl.' ?></div>
          <?php else: ?><div class="dn-metr-pozn">Tabulka zkušeností pro tohle povolání nemá další úroveň.</div><?php endif; ?>
          <?php if ($smiZkusenost): ?><div class="dn-pms"><?= $tlacitka('zkusenost', [-50, -10, 10, 50]) ?></div>
          <?php else: ?><div class="dn-metr-pozn">Zkušenost přiděluje PJ.</div><?php endif; ?>
        <?php else: ?>
          <p class="note">Zkušenost se zatím nedá ukládat (čeká na migraci 0070).</p>
        <?php endif; ?>
      </div>

      <div class="dn-karta">
        <h3 class="dn-h3">Rasa a doprava</h3>
        <div class="dn-dvojice"><span>rodová zbraň</span><strong><?= $d['rodova_zbran'] ? $e($d['rodova_zbran']) . ' (+1 ÚČ)' : '—' ?></strong></div>
        <div class="dn-dvojice"><span>velikost</span>
          <?php if ($ma('velikost') && !isset(DRACAK_VTT_VELIKOST_RASY[$d['zakladni_rasa'] ?? ''])): ?>
            <select class="dn-select-mini" data-zmena="velikost" aria-label="Velikost">
              <option value="">—</option>
              <?php foreach (['A0', 'A', 'B', 'C', 'D', 'E'] as $v): ?><option value="<?= $v ?>" <?= ($postava['velikost'] ?? '') === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
            </select>
          <?php else: ?><strong><?= $e($d['velikost'] ?? '—') ?></strong><?php endif; ?>
        </div>
        <div class="dn-dvojice"><span>doprava</span><strong><?= $d['doprava'] && $d['doprava']['typ'] === 'zvire' ? $e($d['doprava']['zvire']['nazev']) : 'pěšky' ?></strong></div>
        <div class="dn-dvojice"><span>jazyky</span><strong><?= $d['jazyky'] ? $e(implode(', ', $d['jazyky'])) : '—' ?></strong></div>
      </div>

      <div class="dn-karta">
        <h3 class="dn-h3">Aktivní efekty</h3>
        <?php if (!$d['efekty']): ?><p class="note">Žádné aktivní efekty.</p>
        <?php else: ?>
          <ul class="dn-efekty">
            <?php foreach ($d['efekty'] as $ef): ?>
              <li class="dn-efekt <?= $e($ef['typ']) ?>" title="<?= $e($ef['tooltip_text']) ?>">
                <strong><?= $e($ef['nazev']) ?></strong> <span class="dn-stitek"><?= $e($ef['typ']) ?></span>
                <span class="dn-efekt-kola"><?= $ef['zbyva_kol'] !== null ? (int)$ef['zbyva_kol'] . ' kol' : 'trvale' ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ===================== BOJ ===================== -->
  <section class="dn-sekce" id="boj">
    <h2 class="dn-h2">Boj</h2>
    <div class="dn-grid">

      <div class="dn-karta">
        <h3 class="dn-h3">Obrana</h3>
        <?php $ob = $d['obrana']; ?>
        <?php if ($ob): ?>
          <div class="dn-vzorec">
            <span class="dn-vz"><small>KZ</small><b><?= (int)$ob['kz'] ?></b></span><span class="dn-op">+</span>
            <span class="dn-vz"><small>obr.</small><b><?= $znam($ob['bonus_obratnost']) ?></b></span><span class="dn-op">=</span>
            <span class="dn-vz dn-vysledek"><small>OČ</small><b><?= (int)$ob['oc'] ?></b></span>
          </div>
          <p class="note">Obranné číslo = kvalita zbroje + bonus za obratnost, nikdy méně než 0. Bez zbroje je KZ 1 (šaty). Štít: +1 k hodu na obranu.</p>
          <div class="dn-hod-akce"><button type="button" class="btn btn-primary" data-hod="obrana">Hod na obranu (1k6+ + OČ)</button></div>
          <?php if ($ma('zbroj_id')): ?>
            <div class="field"><label for="zbroj_id">Zbroj na těle</label>
              <select id="zbroj_id" data-zmena="vyber" data-pole="zbroj_id">
                <option value="">— šaty (KZ 1)</option>
                <?php foreach ($zbrojeInv as $i): ?><option value="<?= (int)$i['id'] ?>" <?= (int)($postava['zbroj_id'] ?? 0) === $i['id'] ? 'selected' : '' ?>><?= $e($i['nazev']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label for="stit_id">Štít</label>
              <select id="stit_id" data-zmena="vyber" data-pole="stit_id">
                <option value="">— bez štítu</option>
                <?php foreach ($stityInv as $i): ?><option value="<?= (int)$i['id'] ?>" <?= (int)($postava['stit_id'] ?? 0) === $i['id'] ? 'selected' : '' ?>><?= $e($i['nazev']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <?php if (!$zbrojeInv && !$stityInv): ?><p class="note">Zbroj ani štít nemáš v inventáři.</p><?php endif; ?>
          <?php else: ?><p class="note">Výběr zbroje a štítu čeká na migraci 0070.</p><?php endif; ?>
          <p class="note">Další části zbroje (rukavice, přilbice, boty) se do OČ zatím nepočítají — pravidla v přepisu neříkají, jak se skládají.</p>
        <?php else: ?><p class="note">Doplň obratnost.</p><?php endif; ?>
      </div>

      <div class="dn-karta">
        <h3 class="dn-h3">Stav v boji</h3>
        <?php if ($boj): ?>
          <div class="dn-dvojice"><span>mez vyřazení</span><strong><?= (int)$boj['mez_vyrazeni'] ?> živ.</strong></div>
          <div class="dn-dvojice"><span>postih pod 1/3 životů</span><strong><?= $znam($boj['postih_pod_tretinou']) ?></strong></div>
          <div class="dn-dvojice"><span>velké naložení</span><strong><?= $velkeNalozeni ? '−1' : '0' ?></strong></div>
          <p class="note">Postih k hodu na útok i obranu: méně než třetina životů (podle odolnosti), velké naložení −1. Při životech na mezi vyřazení a pod ní postava nebojuje (h1610).</p>
        <?php else: ?><p class="note">Doplň odolnost.</p><?php endif; ?>
        <div class="dn-dvojice"><span>únava</span><strong><?= $unava ? $e($unava['nazev']) : '—' ?></strong></div>
        <?php if ($unava && $unava['popis']): ?><p class="note"><?= $e($unava['popis']) ?></p><?php endif; ?>
      </div>

      <div class="dn-karta dn-sirka-cela">
        <h3 class="dn-h3">Zbraně
          <label class="dn-mod" for="dn-mod">ruční oprava k hodu <input type="number" id="dn-mod" value="0" step="1" min="-30" max="30" title="Rozdíl velikostí, dostřel (malý +1, velký −1), úkryt… podle situace"></label>
        </h3>
        <?php if (!$d['zbrane']): ?>
          <p class="note">Žádná zbraň v inventáři.</p>
        <?php else: ?>
          <div class="dn-tab-wrap">
            <table class="dn-tab">
              <thead><tr><th></th><th>Zbraň</th><th class="c">SZ</th><th class="c">Útočnost</th><th class="c">OZ</th><th class="c">Délka</th><th class="c">ÚČ tváří v tvář</th><th class="c">ÚČ střelecký</th><th class="c">Dostřel</th><th class="c">Hod</th></tr></thead>
              <tbody>
              <?php foreach ($d['zbrane'] as $z): ?>
                <tr class="<?= $z['drzena'] ? 'drzena' : '' ?>">
                  <td><?php if ($ma('drzena_zbran_id')): ?><input type="radio" name="drzena" aria-label="Držím <?= $e($z['nazev']) ?>" data-zmena="vyber" data-pole="drzena_zbran_id" value="<?= $z['id'] ?>" <?= $z['drzena'] ? 'checked' : '' ?>><?php endif; ?></td>
                  <td><strong><?= $e($z['nazev']) ?></strong><?= $z['mnozstvi'] > 1 ? ' ×' . $z['mnozstvi'] : '' ?><?= $z['rodova'] ? ' <span class="dn-stitek" title="Rodová zbraň rasy: +1 k ÚČ">rodová</span>' : '' ?></td>
                  <td class="c"><?= $z['sz'] ?? '—' ?></td>
                  <td class="c"><?= $znam($z['utocnost']) ?></td>
                  <td class="c"><?= $znam($z['oz']) ?></td>
                  <td class="c"><?= $e($z['delka'] ?? '—') ?></td>
                  <td class="c uc"><?= $z['uc_tvar_v_tvar'] ?? '—' ?></td>
                  <td class="c uc"><?= $z['uc_strelecky'] ?? '—' ?></td>
                  <td class="c"><?= $z['dostrel'] ? implode(' / ', $z['dostrel']) : '—' ?></td>
                  <td class="c dn-hod-bunka">
                    <?php if ($z['uc_tvar_v_tvar'] !== null): ?><button type="button" class="dn-pm" data-hod="utok" data-zbran="<?= $z['id'] ?>">Útok</button><?php endif; ?>
                    <?php if ($z['uc_strelecky'] !== null): ?><button type="button" class="dn-pm" data-hod="strelba" data-zbran="<?= $z['id'] ?>">Střelba</button><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="note">ÚČ tváří v tvář = síla zbraně + bonus za sílu (+1 rodová zbraň), střelecký = síla zbraně + bonus za obratnost, nikdy méně než 0. Dostřel malý / střední / velký v sázích: ≤ malý +1 k útoku, > střední −1. Kouzelné a artefaktové zbraně bez síly zbraně v katalogu mají ÚČ „—“. Hod = 1k6 s opakováním po šestce + ÚČ, s opravami za málo životů a velké naložení (rozdíl velikostí a dostřel zadej do ruční opravy).</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ===================== SCHOPNOSTI A KOUZLA ===================== -->
  <section class="dn-sekce" id="schopnosti">
    <h2 class="dn-h2">Schopnosti a kouzla
      <?php if ($svetId > 0): ?>
      <label class="dn-mod dn-cil" for="dn-cil">cíl kouzla / lektvaru
        <select id="dn-cil">
          <option value="">na sebe</option>
          <?php foreach ($d['cile'] as $c): ?><option value="<?= $e($c['hodnota']) ?>"><?= $e($c['nazev']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
    </h2>
    <div class="dn-grid">
      <div class="dn-karta dn-sirka-cela">
        <h3 class="dn-h3">Zvláštní schopnosti a dovednosti <span class="dn-pocet"><?= count($d['schopnosti']) ?></span></h3>
        <?php if (!$d['schopnosti']): ?><p class="note">Zatím žádné (povolání a rasa je v katalogu nemají pro tuhle úroveň).</p>
        <?php else: ?>
          <div class="dn-seznam">
            <?php foreach ($d['schopnosti'] as $s): ?>
              <details class="dn-pol" data-id="s<?= (int)$s['id'] ?>-<?= $e($s['zdroj']) ?>">
                <summary>
                  <span class="dn-pol-nazev"><?= $e($s['nazev']) ?></span>
                  <span class="dn-stitek"><?= $e($s['druh']) ?></span>
                  <span class="dn-stitek"><?= $e($s['zdroj']) ?></span>
                  <?php if ($s['uroven_od']): ?><span class="dn-stitek">od <?= (int)$s['uroven_od'] ?>. úrovně</span><?php endif; ?>
                  <?php if ($s['pocet_kostek'] && $s['typ_kostky']): $notaceSch = (int)$s['pocet_kostek'] . $s['typ_kostky'] . ((int)$s['pevny_bonus'] ? ((int)$s['pevny_bonus'] > 0 ? '+' : '') . (int)$s['pevny_bonus'] : ''); ?>
                    <span class="dn-stitek varovani"><?= $e($notaceSch) ?></span>
                    <?php if ($svetId > 0): ?><button type="button" class="dn-pm dn-pm-mini" data-kostka="<?= $e($notaceSch) ?>" data-nazev="<?= $e($s['nazev']) ?>">Hodit</button><?php endif; ?>
                  <?php endif; ?>
                  <?php if ($s['max_pouziti']): ?><span class="dn-stitek"><?= $e($s['max_pouziti']) ?></span><?php endif; ?>
                </summary>
                <div class="dn-pol-telo"><?= nl2br($e($s['popis'] ?? '')) ?><?php if ($s['mechanika']): ?><p class="note">Mechanika: <?= $e($s['mechanika']) ?></p><?php endif; ?></div>
              </details>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="dn-karta dn-sirka-cela">
        <h3 class="dn-h3">Kouzla <span class="dn-pocet"><?= count($d['kouzla']) ?></span></h3>
        <?= $picker(['kouzlo']) ?>
        <?php if (!$d['kouzla']): ?><p class="note">Postava zatím nezná žádné kouzlo.</p>
        <?php else: ?>
          <div class="dn-seznam">
            <?php foreach ($d['kouzla'] as $k): ?>
              <details class="dn-pol" data-id="k<?= (int)$k['id'] ?>">
                <summary>
                  <span class="dn-stitek"><?= (int)$k['uroven_kouzla'] ?>.</span>
                  <span class="dn-pol-nazev"><?= $e($k['nazev']) ?></span>
                  <?php if ($k['cena_magenergie']): ?><span class="dn-stitek varovani"><?= $e($k['cena_magenergie']) ?></span><?php endif; ?>
                  <?php if ($k['dosah']): ?><span class="dn-stitek">dosah <?= $e($k['dosah']) ?></span><?php endif; ?>
                  <?php if ($k['doba_trvani']): ?><span class="dn-stitek">trvání <?= $e($k['doba_trvani']) ?></span><?php endif; ?>
                  <?php if ($k['typ_unavy']): ?><span class="dn-stitek"><?= $e($k['typ_unavy']) ?></span><?php endif; ?>
                </summary>
                <div class="dn-pol-telo">
                  <div class="dn-hod-akce">
                  <?php if ($svetId > 0): ?><button type="button" class="btn btn-primary" data-pouzij="kouzlo" data-polozka="<?= (int)$k['id'] ?>" data-nazev="<?= $e($k['nazev']) ?>"<?= $k['skodi'] ? ' data-skodi="1"' : '' ?>>Seslat<?= $k['cena_magenergie'] ? ' (' . $e($k['cena_magenergie']) . ')' : '' ?></button><?php endif; ?>
                    <button type="button" class="btn btn-ghost" data-inv="odebrat" data-typ="kouzlo" data-id="<?= (int)$k['id'] ?>" data-nazev="<?= $e($k['nazev']) ?>">Zapomenout</button>
                  </div>
                  <p class="note"><?= $e(implode(' · ', array_filter([$k['seznam'], $k['rozsah'] ? 'rozsah ' . $k['rozsah'] : null, $k['doba_seslani'] ? 'sesílání ' . $k['doba_seslani'] : null]))) ?></p>
                  <?= nl2br($e($k['popis'] ?? '')) ?>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ===================== VYBAVENÍ ===================== -->
  <section class="dn-sekce" id="vybaveni">
    <h2 class="dn-h2">Vybavení</h2>
    <div class="dn-grid">
      <div class="dn-karta dn-sirka-2">
        <h3 class="dn-h3">Inventář</h3>
        <?= $picker(['predmet', 'lektvar']) ?>
        <?php if (!$d['inventar']): ?><p class="note">Inventář je prázdný. Najdi položku v katalogu výše a klikni na ni.</p>
        <?php else: ?>
          <div class="dn-tab-wrap">
            <table class="dn-tab">
              <thead><tr><th>Předmět</th><th>Druh</th><th class="c">ks</th><th class="c">váha / ks</th><th class="c">váha celkem</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($d['inventar'] as $i): ?>
                <tr>
                  <td><strong><?= $e($i['nazev']) ?></strong></td>
                  <td><span class="dn-stitek"><?= $e($i['typ']) ?></span></td>
                  <td class="c"><?= (int)$i['mnozstvi'] ?></td>
                  <td class="c"><?= $i['vaha_ks'] !== null ? $mn($i['vaha_ks']) : '<span class="dn-stitek neuplne" title="Váha v katalogu chybí">bez váhy</span>' ?></td>
                  <td class="c"><?= $i['vaha_celkem'] !== null ? $mn($i['vaha_celkem']) : '—' ?></td>
                  <td class="c dn-inv-akce"><?php if ($i['kategorie'] === 'lektvar' && $svetId > 0): ?> <button type="button" class="dn-pm dn-pm-mini" data-pouzij="lektvar" data-polozka="<?= (int)$i['id'] ?>" data-nazev="<?= $e($i['nazev']) ?>"<?= !empty($i['skodi']) ? ' data-skodi="1"' : '' ?>>Vypít</button><?php endif; ?>
                    <button type="button" class="dn-pm dn-pm-mini" data-inv="zmena" data-typ="<?= $e($i['kategorie']) ?>" data-id="<?= (int)$i['id'] ?>" data-delta="-1" aria-label="Ubrat kus">−</button><button type="button" class="dn-pm dn-pm-mini" data-inv="zmena" data-typ="<?= $e($i['kategorie']) ?>" data-id="<?= (int)$i['id'] ?>" data-delta="1" aria-label="Přidat kus">+</button><button type="button" class="dn-pm dn-pm-mini" data-inv="odebrat" data-typ="<?= $e($i['kategorie']) ?>" data-id="<?= (int)$i['id'] ?>" data-nazev="<?= $e($i['nazev']) ?>" aria-label="Odebrat z inventáře">✕</button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr><td colspan="4">Vybavení (základní náklad)</td><td class="c"><strong><?= $mn($nakl['naklad_mn'] - $nakl['penize_mn']) ?> mn</strong></td><td></td></tr>
                <?php if ($nakl['penize_pocitany']): ?><tr><td colspan="4">Peníze (1 mince = 1 mn)</td><td class="c"><?= $mn($nakl['penize_mn']) ?> mn</td><td></td></tr><?php endif; ?>
                <tr class="celkem"><td colspan="4">Celkový náklad</td><td class="c"><strong><?= $mn($nakl['naklad_mn']) ?> mn</strong></td><td></td></tr>
              </tfoot>
            </table>
          </div>
          <?php if ($nakl['neuplny']): ?><p class="note"><span class="dn-stitek neuplne">neúplný součet</span> Bez uvedené váhy (v součtu chybí): <?= $e(implode(', ', $nakl['bez_vahy'])) ?>.</p><?php endif; ?>
          <p class="note">Do nákladu se zatím nepočítá oblečení a jídlo (nejsou v inventáři); vaky, truhly a zvířata jako nosiče nákladu přibudou.</p>
        <?php endif; ?>
      </div>

      <div class="dn-karta">
        <h3 class="dn-h3">Peníze</h3>
        <?php if ($d['penize'] !== null): $pn = $d['penize']; ?>
          <?php foreach (['zl' => 'zlaťáky', 'st' => 'stříbrňáky', 'md' => 'měďáky'] as $m => $popisek): ?>
            <div class="dn-penize">
              <label for="penize_<?= $m ?>"><?= $e($popisek) ?> (<?= $m ?>)</label>
              <input type="number" min="0" id="penize_<?= $m ?>" value="<?= (int)$pn[$m] ?>" data-zmena="penize" data-mena="<?= $m ?>">
            </div>
          <?php endforeach; ?>
          <div class="dn-dvojice"><span>hodnota celkem</span><strong><?= $e(number_format($pn['hodnota_md'] / 100, 2, ',', ' ')) ?> zl</strong></div>
          <p class="note">1 zl = 10 st = 100 md. Každá mince váží 1 mn, ať je z čehokoli.</p>
        <?php else: ?><p class="note">Peníze se zatím nedají ukládat (čeká na migraci 0070).</p><?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ===================== POSTAVA ===================== -->
  <section class="dn-sekce" id="postava">
    <h2 class="dn-h2">Postava</h2>
    <div class="dn-grid">
      <?php foreach ([
          'puvod' => ['Původ', 'Odkud postava pochází, kdo byla její rodina, co ji formovalo.'],
          'osobnost' => ['Povaha, zvyky, sny a cíle', 'Asi čtyři povahové rysy, pravidelné zvyky, sny. Ostatním hráčům hned neříkej — má to vyplynout ze hry.'],
          'poznamky' => ['Poznámky', 'Cokoli, co si chceš pamatovat.'],
      ] as $pole => [$nadpis, $napoveda]): ?>
        <div class="dn-karta <?= $pole === 'poznamky' ? 'dn-sirka-cela' : '' ?>">
          <h3 class="dn-h3"><?= $e($nadpis) ?></h3>
          <?php if ($ma($pole)): ?>
            <textarea class="dn-text" data-text-pole="<?= $pole ?>" rows="6" placeholder="<?= $e($napoveda) ?>"><?= $e($postava[$pole] ?? '') ?></textarea>
            <div class="dn-text-akce"><button type="button" class="btn btn-secondary" data-akce="text" data-pole="<?= $pole ?>">Uložit</button><span class="dn-ulozeno" aria-live="polite"></span></div>
          <?php else: ?><p class="note">Čeká na migraci 0070.</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

</div>
<?php if (!$fragment): ?>
</div>
<script src="../assets/js/denik.js?v=<?= filemtime(__DIR__ . '/../assets/js/denik.js') ?>"></script>
<?php dracak_vtt_page_end(); ?>
<?php endif; ?>
