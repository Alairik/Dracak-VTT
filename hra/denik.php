<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_denik.php';
require_once __DIR__ . '/../includes/vtt_predmety.php';
require_once __DIR__ . '/../includes/vtt_zbrane_u_sebe.php';

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
$znam = static fn(?int $n): string => $n === null ? '—' : dracak_vtt_znam($n);
$cislo = static fn(int $n): string => $n < 0 ? '−' . abs($n) : (string)$n;
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

// Záhlaví v2 (vlastnosti, OČ, ÚČ aktivní zbraně) a žebříček naložení.
$hl = dracak_vtt_denik_hlavicka($d);
$zebricek = dracak_vtt_denik_zebricek_nalozeni($pdo, $d);
$aktivniZbran = dracak_vtt_denik_aktivni_zbran($d);
// Vytasení zbraně (includes/vtt_zbrane_u_sebe.php): mimo boj zdarma a kteroukoli z inventáře; v boji stojí akci,
// smí jen na vlastním tahu a hráč jen zbraň, kterou má u sebe (PJ jakoukoli).
$souboj = dracak_vtt_zbrane_boj_kontext($pdo, $postava);
$maUSebe = dracak_vtt_zbrane_u_sebe_funkce($pdo);
// Zdroje vytasení bez akce (Erythen — Rychlé tasení, efekt Dikobraz…) a které zbraně jde teď vytasit zdarma.
$zdarmaZdroje = $souboj !== null ? dracak_vtt_zbrane_zdarma_zdroje($pdo, $postava) : [];
$zdarmaEfekt = static function (array $z) use ($pdo, $souboj, $maUSebe, $zdarmaZdroje): ?string {
    if ($souboj === null || !$souboj['na_tahu'] || ($maUSebe && !$z['u_sebe'])) { return null; }
    return dracak_vtt_zbrane_zdarma_efekt($pdo, $zdarmaZdroje, dracak_vtt_zbran_druh($z['kategorie'] ?? null, (string)$z['nazev']), $souboj);
};
$vytasiPodle = static function (array $z) use ($souboj, $maUSebe, $jePj, $zdarmaEfekt): ?string {
    if ($z['drzena']) { return 'drzena'; }
    if ($souboj === null || $jePj) { return null; }   // PJ smí vždy (mimo tah jen zatržením „bez akce“, hlídá server)
    if (!$souboj['na_tahu']) { return 'Zbraň vytasíš na svém tahu — v boji to stojí akci.'; }
    if ($souboj['akci_zbyva'] < 1 && $zdarmaEfekt($z) === null) { return 'V tomhle tahu už nemáš akci.'; }
    if ($maUSebe && !$z['u_sebe']) { return 'Zbraň není u sebe — v boji ji nevytasíš.'; }
    return null;
};
// Další zbraň, kterou jde vytasit: po aktivní v pořadí inventáře; bez aktivní první použitelná.
$dalsiZbranId = null;
$zbranIds = array_column($d['zbrane'], 'id');
$poradi = $aktivniZbran ? array_search($aktivniZbran['id'], $zbranIds, true) : -1;
for ($i = 1; $i <= count($zbranIds); $i++) {
    $z = $d['zbrane'][($poradi + $i) % count($zbranIds)];
    if (!$z['drzena'] && !($aktivniZbran && $z['id'] === $aktivniZbran['id']) && $vytasiPodle($z) === null) { $dalsiZbranId = $z['id']; $dalsiZbranNazev = $z['nazev']; $dalsiZbranZdarma = $zdarmaEfekt($z); break; }
}
$maKouzla = $maMag || (bool)$d['kouzla'];
// Vysvětlení k číslu: při najetí (title) i klepnutí (data-info), ať jde i na dotyku.
$info = static fn(string $t): string => 'title="' . htmlspecialchars($t, ENT_QUOTES) . '" data-info="' . htmlspecialchars($t, ENT_QUOTES) . '"';

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

// ?embed=1: deník uvnitř mapy (hra/mapa.php, panel „Deník“) — bez odkazů, které by z mapy odvedly pryč.
$embed = isset($_GET['embed']);
$fragment = isset($_GET['fragment']);
if (!$fragment) {
    dracak_vtt_page_start($postava['nazev'] . ' — deník', $user, true, ['../assets/css/denik.css']);
}
$vlastnostiNazvy = ['sila' => 'Síla', 'obratnost' => 'Obratnost', 'odolnost' => 'Odolnost', 'inteligence' => 'Inteligence', 'charisma' => 'Charisma'];
$cilKouzla = $svetId > 0;
?>
<?php if (!$fragment): ?>
<div class="dn-wrap<?= $embed ? ' dn-embed' : '' ?>" data-postava-id="<?= $postavaId ?>" data-svet-id="<?= $svetId ?>">
<?php endif; ?>
<div class="dn" id="denik" data-ma-kouzla="<?= $maKouzla ? '1' : '0' ?>" data-boj="<?= $souboj !== null ? '1' : '0' ?>">

  <!-- ===================== ZÁHLAVÍ (trvale viditelné) ===================== -->
  <header class="dn-pas">
    <div class="dn-hlava">
      <span class="dn-av" aria-hidden="true"><?= $e($inicialy) ?></span>
      <div class="dn-id">
        <h1 class="dn-jmeno"><?= $e($postava['nazev']) ?></h1>
        <p class="dn-pod"><?= $e(implode(' · ', array_filter([
            $postava['rasa_nazev'] ?? null,
            $postava['povolani_nazev'] ?? null,
            (int)$postava['uroven'] . '. úroveň',
        ]))) ?></p>
      </div>
      <div class="dn-attrs" role="group" aria-label="Vlastnosti a jejich bonusy">
        <?php foreach ($hl['atributy'] as $at): ?>
          <span class="dn-attr<?= $at['zmena'] ? ' zm' : '' ?>" <?= $info($at['title']) ?>>
            <span class="dn-attr-k"><?= $e($at['zkratka']) ?></span>
            <span class="dn-attr-h"><?php if ($at['stupen_zaklad'] !== null): ?><s><?= (int)$at['stupen_zaklad'] ?></s> <?php endif; ?><?= $at['stupen'] ?? '—' ?></span>
            <span class="dn-attr-b<?= ($at['bonus'] ?? 0) < 0 ? ' minus' : '' ?>"><?php if ($at['bonus_zaklad'] !== null): ?><s><?= $e($znam($at['bonus_zaklad'])) ?></s> <?php endif; ?><?= $e($znam($at['bonus'])) ?></span>
          </span>
        <?php endforeach; ?>
      </div>
      <?php if ($hl['postrech']): ?>
        <div class="dn-postreh" <?= $info($hl['postrech']['title']) ?>>
          <span class="dn-attr-k">Postřeh</span>
          <span class="dn-ps">objekty <strong><?= $hl['postrech']['objekty'] ?> %</strong></span>
          <span class="dn-ps">mechanismy <strong><?= $hl['postrech']['mechanismy'] ?> %</strong></span>
        </div>
      <?php endif; ?>
      <div class="dn-bojcisla">
        <div class="dn-velke" <?= $info($hl['oc']['title']) ?>>
          <span class="dn-velke-k">OČ</span>
          <span class="dn-velke-h"><?php if ($hl['oc']['zaklad'] !== null): ?><s><?= (int)$hl['oc']['zaklad'] ?></s> <?php endif; ?><?= $hl['oc']['hodnota'] !== null ? $cislo((int)$hl['oc']['hodnota']) : '—' ?></span>
        </div>
        <?php if ($dalsiZbranId !== null && $ma('drzena_zbran_id')): ?>
          <button type="button" class="dn-velke dn-velke-btn" title="<?= $e($hl['uc']['title']) ?>" aria-label="Aktivní zbraň: <?= $e($hl['uc']['popis']) ?>. Klepnutím vytasíš další zbraň<?= $souboj !== null ? ' (stojí akci)' : '' ?>." data-dalsi-zbran="<?= (int)$dalsiZbranId ?>" data-nazev="<?= $e($dalsiZbranNazev ?? '') ?>" data-zdarma="<?= $e($dalsiZbranZdarma ?? '') ?>">
        <?php else: ?>
          <div class="dn-velke dn-velke-jedna" title="<?= $e($hl['uc']['title']) ?>">
        <?php endif; ?>
          <span class="dn-velke-k">ÚČ</span>
          <span class="dn-velke-h"><?php if ($hl['uc']['zaklad'] !== null): ?><s><?= (int)$hl['uc']['zaklad'] ?></s> <?php endif; ?><?= $hl['uc']['hodnota'] !== null ? $cislo((int)$hl['uc']['hodnota']) : '—' ?></span>
          <span class="dn-velke-p"><?= $e($hl['uc']['popis']) ?></span>
        <?= $dalsiZbranId !== null && $ma('drzena_zbran_id') ? '</button>' : '</div>' ?>
      </div>
      <?php if (!$embed): ?>
        <a class="btn btn-secondary dn-zavrit" href="<?= $svetId > 0 ? 'svet.php?id=' . $svetId : 'postavy_moje.php' ?>" data-zavrit>✕ Zavřít deník</a>
      <?php endif; ?>
    </div>

    <div class="dn-stav">
      <!-- Životy -->
      <section class="dn-cell main" aria-label="Životy">
        <div class="dn-cell-id">
          <div class="dn-cell-top"><span class="dn-cell-l">Životy</span>
            <?php if ($hpStav === 'out'): ?><span class="dn-stitek nebezpeci">vyřazena z boje</span>
            <?php elseif ($hpStav === 'low'): ?><span class="dn-stitek varovani">postih <?= $znam($boj['postih_pod_tretinou']) ?> k ÚČ a OČ</span><?php endif; ?>
          </div>
          <span class="dn-cell-v dn-metr-cislo"><strong><?= $hp ?></strong><span>/ <?= $maxHp ?></span></span>
        </div>
        <div class="dn-cell-mid"><div class="dn-bar thin <?= $hpStav ?>" role="meter" aria-label="Životy" aria-valuemin="0" aria-valuemax="<?= $maxHp ?>" aria-valuenow="<?= $hp ?>"><i style="width:<?= $hpProcent ?>%"></i></div></div>
        <div class="dn-pms"><?= $tlacitka('hp', [-5, -1, 1, 5]) ?></div>
        <?php if ($boj): ?>
          <button type="button" class="dn-cell-i" aria-label="Vysvětlení: životy" <?= $info('Mez vyřazení ' . (int)$boj['mez_vyrazeni'] . ' životů: na ní a pod ní postava nebojuje (h1610). Méně než třetina max. životů (' . rtrim(rtrim(number_format($maxHp / 3, 1, ',', ''), '0'), ',') . '): postih ' . $znam($boj['postih_pod_tretinou']) . ' k útoku i obraně' . ($boj['pod_tretinou'] ? ' (platí teď).' : ' (zatím se neuplatňuje).')) ?>>i</button>
        <?php endif; ?>
      </section>

      <?php if ($maMag): ?>
      <!-- Magenergie -->
      <section class="dn-cell main" aria-label="Magenergie">
        <div class="dn-cell-id">
          <div class="dn-cell-top"><span class="dn-cell-l">Magy</span></div>
          <span class="dn-cell-v dn-metr-cislo"><strong><?= (int)$postava['aktualni_magenergie'] ?></strong><span>/ <?= (int)$postava['max_magenergie'] ?></span></span>
        </div>
        <div class="dn-cell-mid"><div class="dn-bar thin mag" role="meter" aria-label="Magenergie" aria-valuemin="0" aria-valuemax="<?= (int)$postava['max_magenergie'] ?>" aria-valuenow="<?= (int)$postava['aktualni_magenergie'] ?>"><i style="width:<?= (int)$postava['max_magenergie'] > 0 ? max(0, min(100, round((int)$postava['aktualni_magenergie'] / (int)$postava['max_magenergie'] * 100))) : 0 ?>%"></i></div></div>
        <div class="dn-pms"><?= $tlacitka('magenergie', [-5, -1, 1, 5]) ?></div>
      </section>
      <?php endif; ?>

      <!-- Únava -->
      <section class="dn-cell aux" aria-label="Únava">
        <div class="dn-cell-id">
          <div class="dn-cell-top"><span class="dn-cell-l">Únava</span>
            <?php if ($unava && $unava['stupen'] > 0): ?><span class="dn-stitek <?= $unava['stupen'] >= 2 ? 'nebezpeci' : 'varovani' ?>"><?= $e($unava['nazev']) ?></span><?php endif; ?>
          </div>
          <?php if ($unava): ?><span class="dn-cell-v dn-metr-cislo"><strong><?= (int)$unava['body'] ?></strong><span>/ <?= (int)$unava['vytrvalost'] ?> vytrvalost</span></span>
          <?php else: ?><span class="dn-cell-v"><span>Doplň odolnost</span></span><?php endif; ?>
        </div>
        <?php if ($unava): ?>
          <div class="dn-cell-mid"><div class="dn-bar thin <?= $unava['stupen'] === 0 ? 'ok' : ($unava['stupen'] >= 2 ? 'out' : 'low') ?>" role="meter" aria-label="Únava" aria-valuemin="0" aria-valuemax="<?= (int)$unava['vytrvalost'] * 4 ?>" aria-valuenow="<?= (int)$unava['body'] ?>"><i style="width:<?= max(0, min(100, round($unava['body'] / (max(1, $unava['vytrvalost']) * 4) * 100))) ?>%"></i><b style="left:25%"></b><b style="left:50%"></b><b style="left:75%"></b></div></div>
        <?php endif; ?>
        <?php if ($ma('unava') && $unava): ?><div class="dn-pms"><?= $tlacitka('unava', [-1, 1]) ?></div><?php endif; ?>
        <?php if ($unava): ?>
          <button type="button" class="dn-cell-i" aria-label="Vysvětlení: únava" <?= $info('Únava ' . (int)$unava['body'] . ', vytrvalost (= Odolnost) ' . (int)$unava['vytrvalost'] . '. ' . ($unava['popis'] ?? 'Bez postihu.') . '. Prahy: unavená nad ' . (int)$unava['vytrvalost'] . ', vyčerpaná nad ' . 2 * (int)$unava['vytrvalost'] . ', úplně vyčerpaná nad ' . 3 * (int)$unava['vytrvalost'] . ', vysílená nad ' . 4 * (int)$unava['vytrvalost'] . '.' . (!$d['nastaveni_pohybu']['auto_unava'] ? ' Postihy se automaticky neuplatňují (PJ je ve světě vypnul), řeší je PJ ručně.' : '')) ?>>i</button>
          <?php if ($d['spanek'] && (int)$unava['body'] > 0 && $ma('unava')): ?>
            <button type="button" class="btn btn-secondary sm dn-spanek" data-akce="spanek" data-hodin="<?= (int)$d['spanek']['hodin'] ?>" data-bodu="<?= (int)$d['spanek']['bodu'] ?>" title="Podle pravidel (h1634): kolik hodin spánku sníží únavu o stupeň a kolik bodů tím zmizí">Prospat <?= (int)$d['spanek']['hodin'] ?> h</button>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <!-- Naložení -->
      <section class="dn-cell aux" aria-label="Naložení">
        <div class="dn-cell-id">
          <div class="dn-cell-top"><span class="dn-cell-l">Naložení</span>
            <?php if ($nalozeni && $nalozeni['kod'] !== 'zadne'): ?><span class="dn-stitek <?= $naklStav === 'out' ? 'nebezpeci' : 'varovani' ?>"><?= $e($nalozeniNazvy[$nalozeni['kod']]) ?></span><?php endif; ?>
            <?php if ($nakl['neuplny']): ?><span class="dn-stitek neuplne" title="Bez váhy: <?= $e(implode(', ', $nakl['bez_vahy'])) ?>">neúplný součet</span><?php endif; ?>
          </div>
          <span class="dn-cell-v dn-metr-cislo"><strong><?= $mn($nakl['naklad_mn']) ?></strong><span>mn<?= $nosnost ? ' / ' . $nosnost : '' ?></span></span>
        </div>
        <?php if ($nosnost): ?>
          <div class="dn-cell-mid"><div class="dn-bar thin nalozeni <?= $naklStav ?>" role="meter" aria-label="Naložení" aria-valuemin="0" aria-valuemax="<?= $nosnost * 4 ?>" aria-valuenow="<?= (int)round($nakl['naklad_mn']) ?>"><i style="width:<?= round($naklPct, 1) ?>%"></i><b style="left:25%"></b><b style="left:50%"></b><b style="left:75%"></b></div></div>
          <button type="button" class="dn-cell-i" aria-label="Vysvětlení: naložení" <?= $info('Nosnost ' . $nosnost . ' mn' . ($poh && $poh['pohyblivost'] !== null ? '. Pohyblivost ' . (int)$poh['zakladni'] . ' → ' . (int)$poh['pohyblivost'] . ' po naložení' : '') . '. Velké naložení (nad 3× nosnost) dává −1 k útoku i obraně' . ($velkeNalozeni ? ' (platí teď)' : ' (zatím se neuplatňuje)') . '.' . (!$d['nastaveni_pohybu']['auto_naklad'] ? ' Naložení se nevyhodnocuje (PJ vypnul automatiku nákladu), váha se jen sčítá.' : '')) ?>>i</button>
        <?php endif; ?>
      </section>
    </div>
  </header>

  <div class="dk-body">
    <div class="dk-tabbar">
      <nav class="dk-tabs" role="tablist" aria-label="Sekce deníku">
        <button type="button" role="tab" class="dk-tab on" data-tab="postava" aria-selected="true">Postava</button>
        <button type="button" role="tab" class="dk-tab" data-tab="inventar" aria-selected="false">Inventář <span class="dn-pocet"><?= count($d['inventar']) ?></span></button>
        <?php if ($maKouzla): ?><button type="button" role="tab" class="dk-tab" data-tab="kouzla" aria-selected="false">Kouzla <span class="dn-pocet"><?= count($d['kouzla']) ?></span></button><?php endif; ?>
        <button type="button" role="tab" class="dk-tab" data-tab="schopnosti" aria-selected="false">Schopnosti <span class="dn-pocet"><?= count($d['schopnosti']) ?></span></button>
      </nav>
      <?php if ($cilKouzla): ?>
      <label class="dn-mod dn-cil" for="dn-cil">cíl kouzla / lektvaru
        <select id="dn-cil">
          <option value="">na sebe</option>
          <?php foreach ($d['cile'] as $c): ?><option value="<?= $e($c['hodnota']) ?>"><?= $e($c['nazev']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
    </div>

    <!-- ===================== POSTAVA ===================== -->
    <div class="dk-panel" role="tabpanel" data-panel="postava" aria-label="Postava">
      <div class="dk-grid2">

        <section class="dn-karta" id="prehled">
          <h2 class="dn-h3">Postup</h2>
          <?php $zk = $d['zkusenost']; ?>
          <?php if ($ma('zkusenost')): ?>
            <div class="dn-dvojice"><span>Zkušenost</span><strong><?= (int)$zk['zkusenost'] ?> zt<?= $zk['potrebne'] !== null ? ' / ' . (int)$zk['potrebne'] . ' na ' . (int)$zk['dalsi_uroven'] . '. úroveň' : '' ?></strong></div>
            <?php if ($zk['potrebne'] !== null): ?>
              <div class="dn-bar thin ok" role="meter" aria-label="Zkušenost do další úrovně" aria-valuemin="0" aria-valuemax="<?= (int)$zk['potrebne'] ?>" aria-valuenow="<?= (int)$zk['zkusenost'] ?>"><i style="width:<?= max(0, min(100, round($zk['zkusenost'] / max(1, $zk['potrebne']) * 100))) ?>%"></i></div>
              <div class="dn-metr-pozn"><?= $zk['muze_postoupit'] ? 'Zkušeností je dost — postup je po výcviku (2 měsíce bez dobrodružství, ' . (int)$zk['cena_vycviku_zl'] . ' zl).' : 'Výcvik na další úroveň: ' . (int)$zk['cena_vycviku_zl'] . ' zl.' ?></div>
            <?php else: ?><div class="dn-metr-pozn">Tabulka zkušeností pro tohle povolání nemá další úroveň.</div><?php endif; ?>
            <?php if ($smiZkusenost): ?><div class="dn-pms dn-pms-mala"><?= $tlacitka('zkusenost', [-50, -10, 10, 50]) ?></div>
            <?php else: ?><div class="dn-metr-pozn">Zkušenost přiděluje PJ. Hráč ji jen čte.</div><?php endif; ?>
          <?php else: ?>
            <p class="note">Zkušenost se zatím nedá ukládat (čeká na migraci 0070).</p>
          <?php endif; ?>
          <div class="dn-ident">
            <div class="dn-dvojice"><span>velikost</span>
              <?php if ($ma('velikost') && !isset(DRACAK_VTT_VELIKOST_RASY[$d['zakladni_rasa'] ?? ''])): ?>
                <select class="dn-select-mini" data-zmena="velikost" aria-label="Velikost">
                  <option value="">—</option>
                  <?php foreach (['A0', 'A', 'B', 'C', 'D', 'E'] as $v): ?><option value="<?= $v ?>" <?= ($postava['velikost'] ?? '') === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                </select>
              <?php else: ?><strong><?= $e($d['velikost'] ?? '—') ?></strong><?php endif; ?>
            </div>
            <div class="dn-dvojice"><span>rodová zbraň</span><strong><?= $d['rodova_zbran'] ? $e($d['rodova_zbran']) . ' (+1 ÚČ)' : '—' ?></strong></div>
            <div class="dn-dvojice"><span>doprava</span><strong><?= $d['doprava'] && $d['doprava']['typ'] === 'zvire' ? $e($d['doprava']['zvire']['nazev']) : 'pěšky' ?></strong></div>
            <div class="dn-dvojice"><span>jazyky</span><strong><?= $d['jazyky'] ? $e(implode(', ', $d['jazyky'])) : '—' ?></strong></div>
          </div>
          <div class="dn-karta-akce">
            <?php if (!$embed): ?><a class="btn btn-secondary sm" href="postava.php?id=<?= $postavaId ?>">Upravit základní údaje</a><?php endif; ?>
            <a class="btn btn-ghost sm" href="denik_tisk.php?id=<?= $postavaId ?>" target="_blank" rel="noopener">Tisk deníku</a>
          </div>
        </section>

        <section class="dn-karta" id="obrana">
          <h2 class="dn-h3">Obrana</h2>
          <?php $ob = $d['obrana']; ?>
          <?php if ($ob): ?>
            <div class="dn-vzorec">
              <span class="dn-vz"><small>KZ</small><b><?= (int)$ob['kz'] ?></b></span><span class="dn-op">+</span>
              <span class="dn-vz"><small>obr.</small><b><?= $znam($ob['bonus_obratnost']) ?></b></span><span class="dn-op">=</span>
              <span class="dn-vz dn-vysledek"><small>OČ</small><b><?= (int)$ob['oc'] ?></b></span>
            </div>
            <p class="note">Obranné číslo = kvalita zbroje + bonus za obratnost, nikdy méně než 0. Bez zbroje je KZ 1 (šaty). Štít: +1 k hodu na obranu; postihy za málo životů a velké naložení jsou v čísle v záhlaví.</p>
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
        </section>

        <section class="dn-karta dk-span2" id="boj">
          <h2 class="dn-h3">Boj
            <label class="dn-mod" for="dn-mod">ruční oprava k hodu <input type="number" id="dn-mod" value="0" step="1" min="-30" max="30" title="Rozdíl velikostí, dostřel (malý +1, velký −1), úkryt… podle situace"></label>
          </h2>
          <?php if (!$d['zbrane']): ?>
            <p class="note">Žádná zbraň v inventáři.</p>
          <?php else: ?>
            <?php if ($souboj !== null): ?>
              <p class="dn-boj-stav" role="status">
                <strong>Jsi v boji.</strong>
                <?php if ($souboj['na_tahu']): ?>Jsi na tahu, zbývá ti <?= (int)$souboj['akci_zbyva'] ?> <?= $souboj['akci_zbyva'] >= 1 && $souboj['akci_zbyva'] <= 4 ? 'akce' : 'akcí' ?> — vytasení nebo výměna zbraně stojí jednu.
                <?php else: ?>Zbraň vytasíš na svém tahu; vytasení nebo výměna stojí jednu akci.<?php endif; ?>
                <?php
                $bezAkce = [];
                foreach ($zdarmaZdroje as $zd) {
                    $zbyva = $zd['limit'] === null ? null : $zd['limit'] - dracak_vtt_zbrane_zdarma_pouzito($pdo, $souboj['mapa_id'], $souboj['token_id'], $souboj['cislo_kola'], $zd['efekt']);
                    if ($zbyva === null || $zbyva > 0) {
                        $druhyText = implode(' / ', array_map(static fn(string $r): string => $r === 'vrhaci' ? 'vrhací' : 'tváří v tvář', $zd['rozsah']));
                        $bezAkce[] = $zd['efekt'] . ' — ' . $druhyText . ($zbyva !== null ? ', zbývá ' . $zbyva . '× v tomhle kole' : '');
                    }
                }
                ?>
                <?php if ($bezAkce): ?><br>Bez akce: <strong><?= $e(implode('; ', $bezAkce)) ?></strong>.<?php endif; ?>
                <?php if ($jePj): ?><label class="dn-bez-akce"><input type="checkbox" id="dn-bez-akce"> bez akce (PJ)</label><?php endif; ?>
              </p>
            <?php endif; ?>
            <div class="dn-tab-wrap">
              <table class="dn-tab">
                <thead><tr><th>V ruce</th><?php if ($maUSebe): ?><th class="c" title="Zbraň má postava při těle (opasek, pochva, závěs, toulec) — v boji ji může vytasit">U sebe</th><?php endif; ?><th>Zbraň</th><th class="c">SZ</th><th class="c">Útočnost</th><th class="c">OZ</th><th class="c">Délka</th><th class="c">ÚČ tváří v tvář</th><th class="c">ÚČ střelecký</th><th class="c">Dostřel</th><th class="c">Hod</th></tr></thead>
                <tbody>
                <?php foreach ($d['zbrane'] as $z): ?>
                  <tr class="<?= $z['drzena'] ? 'drzena' : '' ?>">
                    <td>
                      <?php $duvod = $vytasiPodle($z); $zdarmaZ = $duvod === null ? $zdarmaEfekt($z) : null; ?>
                      <?php if ($z['drzena']): ?><span class="dn-stitek dn-vruce">v ruce</span>
                      <?php elseif ($ma('drzena_zbran_id')): ?>
                        <button type="button" class="dn-pm" data-vytasit="<?= $z['id'] ?>" data-nazev="<?= $e($z['nazev']) ?>" data-zdarma="<?= $e($zdarmaZ ?? '') ?>" <?= $duvod !== null ? 'disabled' : '' ?>
                          title="<?= $e($duvod ?? ($zdarmaZ !== null ? 'Vytasit bez akce — ' . $zdarmaZ : ($souboj !== null ? 'Vytasit — stojí jednu akci' : 'Vzít do ruky (mimo boj zdarma)'))) ?>"><?= $zdarmaZ !== null ? 'Vytasit (bez akce)' : 'Vytasit' ?></button>
                      <?php endif; ?>
                    </td>
                    <?php if ($maUSebe): ?>
                      <td class="c"><input type="checkbox" aria-label="U sebe: <?= $e($z['nazev']) ?>" data-zmena="zbran_u_sebe" data-id="<?= $z['id'] ?>" <?= $z['u_sebe'] || $z['drzena'] ? 'checked' : '' ?> <?= ($souboj !== null && !$jePj) || $z['drzena'] ? 'disabled' : '' ?>></td>
                    <?php endif; ?>
                    <td><button type="button" class="dn-odkaz" data-detail="predmet" data-id="<?= (int)$z['id'] ?>" title="Ukázat detail"><strong><?= $e($z['nazev']) ?></strong></button><?= $z['mnozstvi'] > 1 ? ' ×' . $z['mnozstvi'] : '' ?><?= $z['rodova'] ? ' <span class="dn-stitek" title="Rodová zbraň rasy: +1 k ÚČ">rodová</span>' : '' ?></td>
                    <td class="c"><?= $z['sz'] ?? '—' ?></td>
                    <td class="c"><?= $znam($z['utocnost']) ?></td>
                    <td class="c" title="Obrana zbraně (nepovinné pravidlo, h1611): při obraně zbraní se přičítá k hodu na obranu."><?= $znam($z['oz']) ?></td>
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
            <p class="note">ÚČ tváří v tvář = síla zbraně + bonus za sílu (+1 rodová zbraň), střelecký = síla zbraně + bonus za obratnost, nikdy méně než 0. Dostřel malý / střední / velký v sázích: ≤ malý +1 k útoku, > střední −1. Kouzelné a artefaktové zbraně bez síly zbraně v katalogu mají ÚČ „—“. Hod = 1k6 s opakováním po šestce + ÚČ, s opravami za málo životů a velké naložení (rozdíl velikostí a dostřel zadej do ruční opravy). Aktivní zbraň je ta v záhlaví.</p>
            <p class="note">V ruce je vždy jen jedna zbraň. <strong>Vytasení nebo výměna zbraně v boji stojí akci</strong> — v jednom kole nejde „vytasit meč a použít jej“ (kapitola Čas v souboji). Mimo boj se zbraň mění zdarma.<?php if ($maUSebe): ?> V boji jde vytasit jen zbraň „u sebe“ (opasek, pochva, závěs, toulec — k nošení zbraní je potřeba pochva či závěs); zbraň z ruky se zasune k tělu sama. Zbraně si k tělu dáváš mimo boj.<?php else: ?> Rozlišení „u sebe“ čeká na ruční SQL.<?php endif; ?></p>
          <?php endif; ?>
        </section>

        <section class="dn-karta" id="pohyb">
          <h2 class="dn-h3">Pohyb a naložení</h2>
          <?php if ($poh && $poh['rucne']): ?>
            <div class="dn-dvojice"><span>pohyblivost</span><strong><?= $poh['pohyblivost'] ?? '—' ?><?= $poh['bonus_pohyblivosti'] !== null ? ' (bonus ' . $znam($poh['bonus_pohyblivosti']) . ')' : '' ?></strong></div>
            <p class="note">Pohyblivost se v tomhle světě zadává ručně (PJ vypnul výpočet).</p>
            <?php if ($ma('pohyblivost_rucne')): ?>
              <div class="field"><label for="pohyblivost_rucne">Pohyblivost</label>
                <input type="number" id="pohyblivost_rucne" min="1" max="60" step="1" value="<?= $poh['pohyblivost'] !== null ? (int)$poh['pohyblivost'] : '' ?>" data-zmena="pohyblivost_rucne"></div>
            <?php else: ?><p class="note">Ruční zadání čeká na migraci 0078.</p><?php endif; ?>
            <?php if ($poh['rasovy_zaklad'] !== null && !$poh['omdleva']): ?><p class="note">výpočet by dal <?= (int)$poh['zakladni'] ?> před naložením</p><?php endif; ?>
          <?php elseif ($poh): ?>
            <div class="dn-dvojice"><span>pohyblivost</span><strong><?= $poh['pohyblivost'] ?? '—' ?><?= $poh['bonus_pohyblivosti'] !== null ? ' (bonus ' . $znam($poh['bonus_pohyblivosti']) . ')' : '' ?></strong></div>
            <p class="note">Základní <?= (int)$poh['zakladni'] ?> = rasa <?= (int)$poh['rasovy_zaklad'] ?>, obr. <?= $znam($poh['bonus_obratnost'] - $poh['unava_postih']) ?>, 2× síla (<?= $znam($poh['bonus_sila'] - $poh['unava_postih']) ?>)<?= $nalozeni && $nalozeni['kod'] !== 'zadne' ? ' · po naložení' : '' ?>.<?= $poh['unava_postih'] > 0 ? ' Bez únavy by byla základní pohyblivost ' . (int)$poh['zakladni_bez_unavy'] . '.' : '' ?></p>
            <?php if ($poh['omdleva']): ?><p class="note"><strong>Postava omdlévá — nepohybuje se.</strong></p><?php endif; ?>
          <?php else: ?>
            <p class="note">Doplň rasu, sílu a obratnost.</p>
          <?php endif; ?>
          <?php if ($zebricek): ?>
            <table class="dn-tab dk-ladder">
              <thead><tr><th>Naložení</th><th class="r">Do</th><th class="r">Pohyblivost</th><th class="r">Bonus</th></tr></thead>
              <tbody>
              <?php foreach ($zebricek as $r): ?>
                <tr class="<?= $r['aktualni'] ? 'cur' : '' ?>"><td><?= $e($r['nazev']) ?></td><td class="r"><?= (int)$r['mez'] ?> mn</td><td class="r"><?= (int)$r['pohyblivost'] ?></td><td class="r"><?= $e($znam($r['bonus'])) ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php if (($nalozeni['kod'] ?? null) === 'prilis_velke'): ?><p class="note"><span class="dn-stitek nebezpeci">neunese</span> Náklad je nad čtyřnásobkem nosnosti — tabulka tu pohyblivost nemá.</p><?php endif; ?>
          <?php endif; ?>
          <?php if ($d['rychlost']): $ry = $d['rychlost']; ?>
            <div class="dn-dvojice"><span>chůze za hodinu</span><strong>procházka <?= (int)$ry['chuze']['prochazka'] ?> · chůze <?= (int)$ry['chuze']['chuze'] ?> · spěch <?= (int)$ry['chuze']['spech'] ?></strong></div>
            <div class="dn-dvojice"><span>běh za kolo</span><strong>poklus <?= (int)$ry['beh']['poklus'] ?> · běh <?= (int)$ry['beh']['beh'] ?> · sprint <?= (int)$ry['beh']['sprint'] ?></strong></div>
          <?php elseif ($poh && $poh['bonus_pohyblivosti'] !== null): ?>
            <p class="note">Rychlost pohybu: bonus <?= $znam($poh['bonus_pohyblivosti']) ?> je mimo Tabulky rychlosti (−5 až +10) nebo čeká na migraci 0074.</p>
          <?php endif; ?>
          <?php if ($d['doprava'] && $d['doprava']['typ'] === 'zvire'): $z = $d['doprava']['zvire']; ?>
            <p class="note">Jede na: <strong><?= $e($z['nazev']) ?></strong> (pohyblivost <?= (int)$z['pohyblivost'] ?>).</p>
          <?php endif; ?>
        </section>

        <section class="dn-karta" id="efekty">
          <h2 class="dn-h3">Aktivní efekty <span class="dn-pocet"><?= count($d['efekty']) ?></span></h2>
          <?php if (!$d['efekty']): ?><p class="note">Žádné aktivní efekty.</p>
          <?php else: ?>
            <ul class="dn-efekty">
              <?php foreach ($d['efekty'] as $ef): ?>
                <li class="dn-efekt <?= $e($ef['typ']) ?>" title="<?= $e($ef['tooltip_text']) ?>">
                  <strong><?= $e($ef['nazev']) ?></strong> <span class="dn-stitek"><?= $e($ef['typ']) ?></span>
                  <span class="dn-efekt-kola"><?= $ef['zbyva_kol'] !== null ? (int)$ef['zbyva_kol'] . ' kol' : 'trvale' ?></span>
                  <?php if (!empty($ef['shrnuti']) || !empty($ef['stav_nazev'])): ?>
                    <small class="note"><?= $e($ef['shrnuti'] ? implode(', ', $ef['shrnuti']) : $ef['stav_nazev']) ?><?= $ef['shrnuti'] && in_array($ef['stav'], ['cast'], true) ? ' · zbytek posoudí PJ' : '' ?></small>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <section class="dn-karta dk-span2" id="postava">
          <h2 class="dn-h3"><span aria-hidden="true">🔒</span> Příběh a poznámky <span class="dn-stitek">soukromé</span></h2>
          <div class="dk-texty">
          <?php foreach ([
              'puvod' => ['Původ', 'Odkud postava pochází, kdo byla její rodina, co ji formovalo.'],
              'osobnost' => ['Povaha, zvyky, sny a cíle', 'Asi čtyři povahové rysy, pravidelné zvyky, sny. Ostatním hráčům hned neříkej — má to vyplynout ze hry.'],
              'poznamky' => ['Poznámky', 'Cokoli, co si chceš pamatovat.'],
          ] as $pole => [$nadpis, $napoveda]): ?>
            <div class="dk-text-blok">
              <h3 class="dn-h4"><?= $e($nadpis) ?></h3>
              <?php if ($ma($pole)): ?>
                <textarea class="dn-text" data-text-pole="<?= $pole ?>" rows="5" placeholder="<?= $e($napoveda) ?>"><?= $e($postava[$pole] ?? '') ?></textarea>
                <div class="dn-text-akce"><button type="button" class="btn btn-secondary sm" data-akce="text" data-pole="<?= $pole ?>">Uložit</button><span class="dn-ulozeno" aria-live="polite"></span></div>
              <?php else: ?><p class="note">Čeká na migraci 0070.</p><?php endif; ?>
            </div>
          <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>

    <!-- ===================== INVENTÁŘ ===================== -->
    <div class="dk-panel" role="tabpanel" data-panel="inventar" id="vybaveni" aria-label="Inventář" hidden>
      <div class="dk-inv">
        <section class="dn-karta">
          <h2 class="dn-h3">Inventář <span class="dn-pocet"><?= count($d['inventar']) ?></span></h2>
          <?= $picker(['predmet', 'lektvar']) ?>
          <?php if (!$d['inventar']): ?><p class="note">Inventář je prázdný. Najdi položku v katalogu výše a klikni na ni.</p>
          <?php else: ?>
            <?php
            $druhyInv = ['zbran' => 'Zbraně', 'zbroj' => 'Zbroje', 'vybaveni' => 'Vybavení', 'surovina' => 'Suroviny', 'artefakt' => 'Artefakty', 'lektvar' => 'Lektvary'];
            $pocetDruh = array_count_values(array_column($d['inventar'], 'typ'));
            ?>
            <?php if (count($d['inventar']) >= 6): /* u malého inventáře by hledání a filtry jen překážely */ ?>
            <div class="dn-inv-filtr" role="search">
              <input type="search" id="dn-inv-hledej" class="dn-inv-hledej" placeholder="Hledat v inventáři…" aria-label="Hledat v inventáři" autocomplete="off">
              <div class="dn-inv-chipy" role="group" aria-label="Druh předmětu">
                <button type="button" class="dn-filtr-chip on" data-inv-filtr="">Vše <span class="dn-pocet"><?= count($d['inventar']) ?></span></button>
                <?php foreach ($druhyInv as $kod => $nazevDruhu): if (empty($pocetDruh[$kod])) { continue; } ?>
                  <button type="button" class="dn-filtr-chip" data-inv-filtr="<?= $e($kod) ?>"><?= $e($nazevDruhu) ?> <span class="dn-pocet"><?= (int)$pocetDruh[$kod] ?></span></button>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>
            <div class="dn-tab-wrap">
              <table class="dn-tab" id="dn-inv-tab">
                <thead><tr><th>Předmět</th><th>Druh</th><th class="c">ks</th><th class="c">váha / ks</th><th class="c">váha celkem</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($d['inventar'] as $i): ?>
                  <tr data-inv-typ="<?= $e($i['typ']) ?>" data-inv-nazev="<?= $e($i['nazev']) ?>">
                    <td><button type="button" class="dn-odkaz" data-detail="<?= $i['kategorie'] === 'lektvar' ? 'lektvar' : 'predmet' ?>" data-id="<?= (int)$i['id'] ?>" title="Ukázat detail"><strong><?= $e($i['nazev']) ?></strong></button></td>
                    <td><span class="dn-stitek"><?= $e($i['typ']) ?></span></td>
                    <td class="c"><?= (int)$i['mnozstvi'] ?></td>
                    <td class="c"><?= $i['vaha_ks'] !== null ? $mn($i['vaha_ks']) : '<span class="dn-stitek neuplne" title="Váha v katalogu chybí">bez váhy</span>' ?></td>
                    <td class="c"><?= $i['vaha_celkem'] !== null ? $mn($i['vaha_celkem']) : '—' ?></td>
                    <td class="c dn-inv-akce"><?php if ($i['kategorie'] === 'lektvar' && $svetId > 0): ?> <button type="button" class="dn-pm dn-pm-mini" data-pouzij="lektvar" data-polozka="<?= (int)$i['id'] ?>" data-nazev="<?= $e($i['nazev']) ?>"<?= !empty($i['skodi']) ? ' data-skodi="1"' : '' ?>>Vypít</button><?php endif; ?>
                      <?php if (!empty($i['kapacita_mn']) && $d['kontejnery_dostupne']): ?><button type="button" class="dn-pm dn-pm-mini" data-kont-akce="rozbalit" data-predmet="<?= (int)$i['id'] ?>" title="Změnit na kontejner, do kterého jde něco vložit">Použít jako kontejner</button><?php endif; ?>
                      <button type="button" class="dn-pm dn-pm-mini" data-inv="zmena" data-typ="<?= $e($i['kategorie']) ?>" data-id="<?= (int)$i['id'] ?>" data-delta="-1" aria-label="Ubrat kus">−</button><button type="button" class="dn-pm dn-pm-mini" data-inv="zmena" data-typ="<?= $e($i['kategorie']) ?>" data-id="<?= (int)$i['id'] ?>" data-delta="1" aria-label="Přidat kus">+</button><button type="button" class="dn-pm dn-pm-mini" data-inv="odebrat" data-typ="<?= $e($i['kategorie']) ?>" data-id="<?= (int)$i['id'] ?>" data-nazev="<?= $e($i['nazev']) ?>" aria-label="Odebrat z inventáře">✕</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
                <tbody id="dn-inv-nic" hidden><tr><td colspan="6" class="note">Nic neodpovídá hledání ani filtru.</td></tr></tbody>
                <tfoot>
                  <tr><td colspan="4">Vybavení (základní náklad)</td><td class="c"><strong><?= $mn($nakl['naklad_mn'] - $nakl['penize_mn']) ?> mn</strong></td><td></td></tr>
                  <?php if ($nakl['penize_pocitany']): ?><tr><td colspan="4">Peníze (1 mince = 1 mn)</td><td class="c"><?= $mn($nakl['penize_mn']) ?> mn</td><td></td></tr><?php endif; ?>
                  <tr class="celkem"><td colspan="4">Celkový náklad</td><td class="c"><strong><?= $mn($nakl['naklad_mn']) ?> mn</strong></td><td></td></tr>
                </tfoot>
              </table>
            </div>
            <?php if ($nakl['neuplny']): ?><p class="note"><span class="dn-stitek neuplne">neúplný součet</span> Bez uvedené váhy (v součtu chybí): <?= $e(implode(', ', $nakl['bez_vahy'])) ?>.</p><?php endif; ?>
            <p class="note">Do nákladu se počítá volný inventář, kontejnery (vlastní váha + obsah) a peníze. Oblečení, jídlo a další vybavení z ceníku najdeš v katalogu pod „předmět“.</p>
          <?php endif; ?>
        </section>

        <section class="dn-karta" id="kontejnery">
          <h2 class="dn-h3">Kontejnery <small class="note">vaky, truhly, soudky, trakař…</small></h2>
          <?php if (!$d['kontejnery_dostupne']): ?>
            <p class="note">Kontejnery čekají na úpravu databáze (ruční SQL).</p>
          <?php else: ?>
            <?php
              $jedeNaZvireti = $d['doprava'] && $d['doprava']['typ'] === 'zvire';
              $volnePredmety = array_filter($d['inventar'], fn($i) => $i['kategorie'] === 'predmet' && empty($i['kapacita_mn']));
            ?>
            <?php if (!$d['kontejnery']): ?>
              <p class="note">Zatím žádný kontejner. V katalogu výše vyhledej třeba „vak“, „truhla“ nebo „soudek“ (hledání „předmět“) a přidej ho — pak do něj můžeš věci vkládat.</p>
            <?php endif; ?>
            <div class="dn-kont-seznam">
            <?php foreach ($d['kontejnery'] as $k):
                $pct = $k['kapacita_mn'] ? max(0, min(100, $k['obsah_vaha'] / $k['kapacita_mn'] * 100)) : 0; ?>
              <div class="dn-kont" data-kontejner="<?= (int)$k['id'] ?>">
                <div class="dn-kont-hlava">
                  <strong><?= $e($k['vlastni_nazev'] ?: $k['nazev']) ?></strong>
                  <?php if ($k['vlastni_nazev']): ?><span class="note">(<?= $e($k['nazev']) ?>)</span><?php endif; ?>
                  <span class="dn-stitek">vlastní váha <?= $k['vaha_vlastni'] !== null ? $mn($k['vaha_vlastni']) . ' mn' : '—' ?></span>
                  <?php if ($k['plne']): ?><span class="dn-stitek nebezpeci">přeplněno</span><?php endif; ?>
                </div>
                <?php if ($k['kapacita_mn'] !== null): ?>
                  <div class="dn-bar thin <?= $k['plne'] ? 'out' : ($pct >= 90 ? 'low' : 'ok') ?>" aria-label="Zaplnění kontejneru"><i style="width:<?= round($pct, 1) ?>%"></i></div>
                  <div class="dn-metr-pozn"><?= $mn($k['obsah_vaha']) ?> / <?= $mn($k['kapacita_mn']) ?> mn<?= $k['volne'] !== null && !$k['plne'] ? ' · volno ' . $mn($k['volne']) . ' mn' : '' ?></div>
                <?php endif; ?>
                <?php if ($k['obsah']): ?>
                  <table class="dn-tab dn-kont-obsah"><tbody>
                  <?php foreach ($k['obsah'] as $o): ?>
                    <tr>
                      <td><?= $e($o['nazev']) ?></td>
                      <td class="c"><?= (int)$o['mnozstvi'] ?> ks</td>
                      <td class="c"><?= $o['vaha_celkem'] !== null ? $mn($o['vaha_celkem']) . ' mn' : '<span class="dn-stitek neuplne">bez váhy</span>' ?></td>
                      <td class="c dn-inv-akce">
                        <button type="button" class="dn-pm dn-pm-mini" data-kont-akce="vyndej" data-kontejner="<?= (int)$k['id'] ?>" data-predmet="<?= (int)$o['predmet_id'] ?>" data-mnozstvi="1" aria-label="Vyndat kus">−1</button>
                        <button type="button" class="dn-pm dn-pm-mini" data-kont-akce="vyndej" data-kontejner="<?= (int)$k['id'] ?>" data-predmet="<?= (int)$o['predmet_id'] ?>" data-mnozstvi="" aria-label="Vyndat vše">vyndat vše</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody></table>
                <?php else: ?><p class="note">Prázdný.</p><?php endif; ?>
                <?php if ($volnePredmety): ?>
                  <div class="dn-kont-vloz">
                    <select class="dn-kont-predmet" aria-label="Co vložit">
                      <?php foreach ($volnePredmety as $v): ?><option value="<?= (int)$v['id'] ?>" data-max="<?= (int)$v['mnozstvi'] ?>"><?= $e($v['nazev']) ?> (<?= (int)$v['mnozstvi'] ?> ks)</option><?php endforeach; ?>
                    </select>
                    <input type="number" class="dn-kont-ks" min="1" value="1" aria-label="Kolik kusů">
                    <button type="button" class="btn btn-secondary sm" data-kont-akce="vloz" data-kontejner="<?= (int)$k['id'] ?>">Vložit</button>
                  </div>
                <?php endif; ?>
                <div class="dn-kont-pata">
                  <label>Nese:
                    <select class="dn-kont-nosic" data-kontejner="<?= (int)$k['id'] ?>" <?= $jedeNaZvireti || $k['nosic'] === 'zvire' ? '' : 'disabled title="Postava nejede na zvířeti"' ?>>
                      <option value="postava" <?= $k['nosic'] === 'postava' ? 'selected' : '' ?>>postava</option>
                      <option value="zvire" <?= $k['nosic'] === 'zvire' ? 'selected' : '' ?>>zvíře<?= $jedeNaZvireti ? ' (' . $e($d['doprava']['zvire']['nazev']) . ')' : '' ?></option>
                    </select>
                  </label>
                  <button type="button" class="btn btn-ghost sm" data-kont-akce="prejmenovat" data-kontejner="<?= (int)$k['id'] ?>" data-nazev="<?= $e($k['vlastni_nazev'] ?? '') ?>">Přejmenovat</button>
                  <button type="button" class="btn btn-ghost sm" data-kont-akce="odebrat" data-kontejner="<?= (int)$k['id'] ?>" data-nazev="<?= $e($k['vlastni_nazev'] ?: $k['nazev']) ?>">Odebrat kontejner</button>
                </div>
              </div>
            <?php endforeach; ?>
            </div>
            <?php if ($d['naklad']['naklad_zvire_mn'] > 0): ?><p class="note">Na zvířeti: <strong><?= $mn($d['naklad']['naklad_zvire_mn']) ?> mn</strong> — počítá se jemu (spolu s jezdcem), ne postavě.</p><?php endif; ?>
            <p class="note">Kapacita je jen hlídaná — když ji překročíš, hra upozorní a ty rozhodneš. Věc vložená do kontejneru zmizí z volného inventáře; váha se počítá pořád.</p>
          <?php endif; ?>
        </section>

        <section class="dn-karta" id="penize">
          <h2 class="dn-h3"><span aria-hidden="true">🪙</span> Peníze</h2>
          <?php if ($d['penize'] !== null): $pn = $d['penize']; ?>
            <div class="dk-money">
            <?php foreach (['zl' => 'zlaťáky', 'st' => 'stříbrňáky', 'md' => 'měďáky'] as $m => $popisek): ?>
              <div class="dn-penize">
                <label for="penize_<?= $m ?>"><?= $e($popisek) ?> (<?= $m ?>)</label>
                <input type="number" min="0" id="penize_<?= $m ?>" value="<?= (int)$pn[$m] ?>" data-zmena="penize" data-mena="<?= $m ?>">
              </div>
            <?php endforeach; ?>
            </div>
            <div class="dn-dvojice"><span>hodnota celkem</span><strong><?= $e(number_format($pn['hodnota_md'] / 100, 2, ',', ' ')) ?> zl</strong></div>
            <p class="note">1 zl = 10 st = 100 md. Každá mince váží 1 mn, ať je z čehokoli.</p>
          <?php else: ?><p class="note">Peníze se zatím nedají ukládat (čeká na migraci 0070).</p><?php endif; ?>
        </section>
      </div>
    </div>

    <?php if ($maKouzla): ?>
    <!-- ===================== KOUZLA ===================== -->
    <div class="dk-panel" role="tabpanel" data-panel="kouzla" id="kouzla" aria-label="Kouzla" hidden>
      <section class="dn-karta">
        <h2 class="dn-h3">Kouzla <span class="dn-pocet"><?= count($d['kouzla']) ?></span></h2>
        <?php if ($maMag): ?><p class="note">Magenergie <?= (int)$postava['aktualni_magenergie'] ?> / <?= (int)$postava['max_magenergie'] ?>. Cena u každého kouzla je základ, víc magů odečteš u měřidla v záhlaví.</p><?php endif; ?>
        <?php if ($unava && in_array('kouzla', $unava['zakazy'], true)): ?><p class="note"><span class="dn-stitek nebezpeci"><?= $e($unava['nazev']) ?></span> Vyčerpaná postava nesmí sesílat kouzla.</p><?php endif; ?>
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
                  <?php if ($svetId > 0): ?><button type="button" class="btn btn-primary sm" data-pouzij="kouzlo" data-polozka="<?= (int)$k['id'] ?>" data-nazev="<?= $e($k['nazev']) ?>"<?= $k['skodi'] ? ' data-skodi="1"' : '' ?><?= $unava && in_array('kouzla', $unava['zakazy'], true) ? ' data-unava-zakaz="' . $e($unava['nazev']) . '"' : '' ?>>Seslat<?= $k['cena_magenergie'] ? ' (' . $e($k['cena_magenergie']) . ')' : '' ?></button><?php endif; ?>
                    <button type="button" class="btn btn-ghost sm" data-inv="odebrat" data-typ="kouzlo" data-id="<?= (int)$k['id'] ?>" data-nazev="<?= $e($k['nazev']) ?>">Zapomenout</button>
                  </div>
                  <p class="note"><?= $e(implode(' · ', array_filter([$k['seznam'], $k['rozsah'] ? 'rozsah ' . $k['rozsah'] : null, $k['doba_seslani'] ? 'sesílání ' . $k['doba_seslani'] : null]))) ?></p>
                  <?= nl2br($e($k['popis'] ?? '')) ?>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
    <?php endif; ?>

    <!-- ===================== SCHOPNOSTI ===================== -->
    <div class="dk-panel" role="tabpanel" data-panel="schopnosti" id="schopnosti" aria-label="Schopnosti" hidden>
      <section class="dn-karta">
        <h2 class="dn-h3">Zvláštní schopnosti a dovednosti <span class="dn-pocet"><?= count($d['schopnosti']) ?></span></h2>
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
      </section>
    </div>

  </div>

  <!-- Detail položky z katalogu: plave nad záložkami, objeví se až po kliknutí na název (naplní ho denik.js). -->
  <aside class="dn-detail" id="dn-detail" role="dialog" aria-label="Detail položky" hidden></aside>
</div>
<?php if (!$fragment): ?>
</div>
<script src="../assets/js/denik.js?v=<?= filemtime(__DIR__ . '/../assets/js/denik.js') ?>"></script>
<?php dracak_vtt_page_end(); ?>
<?php endif; ?>
