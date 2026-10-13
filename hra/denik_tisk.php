<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_denik.php';
require_once __DIR__ . '/../includes/vtt_predmety.php';

// Tisková verze Osobního deníku — papírový formulář podle kolonek z
// content/pravidla-hrac.html h1690 ("Doporučuje se psát do něj tužkou a
// předem si ho okopírovat"). S ?id=N předvyplněný postavou, bez id (nebo
// id=0) prázdný formulář. A4 na výšku, dvě strany, černobíle.

$user = dracak_require_login();
$pdo = dracak_db();
$postavaId = (int)($_GET['id'] ?? 0);
$postava = null;
$d = null;
if ($postavaId > 0) {
    $stmt = $pdo->prepare(
        'SELECT p.*, r.nazev AS rasa_nazev, pv.nazev AS povolani_nazev
         FROM postavy p LEFT JOIN rasy r ON r.id = p.rasa_id LEFT JOIN povolani pv ON pv.id = p.povolani_id
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
        die('Tuhle postavu tisknout nesmíš.');
    }
    $d = dracak_vtt_denik_data($pdo, $postava);
}

$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$znam = static fn(?int $n): string => $n === null ? '' : (($n > 0 ? '+' : '') . $n);
$mn = static function ($n): string {
    $n = (float)$n;
    return rtrim(rtrim(number_format($n, 1, ',', ' '), '0'), ',');
};
// Buňka s hodnotou, nebo prázdné políčko k vyplnění tužkou.
$pole = static fn($hodnota): string => ($hodnota === null || $hodnota === '') ? '&nbsp;' : $e($hodnota);

// Dráha 1–50 (životy / magy): aktuální hodnota zatmavená, nad maximem šrafováno.
$draha = static function (string $nazev, ?int $aktualni, ?int $max): string {
    $html = '<div class="draha"><div class="draha-n">' . $nazev . '</div>';
    foreach ([[1, 25], [26, 50]] as [$od, $do]) {
        $html .= '<div class="draha-r">';
        for ($i = $od; $i <= $do; $i++) {
            $cls = '';
            if ($aktualni !== null && $i === $aktualni) { $cls = ' akt'; }
            elseif ($max !== null && $i > $max) { $cls = ' nad'; }
            $html .= '<span class="' . trim($cls) . '">' . $i . '</span>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
};

$vl = ['sila' => 'Síla', 'obratnost' => 'Obratnost', 'odolnost' => 'Odolnost', 'inteligence' => 'Inteligence', 'charisma' => 'Charisma'];
$poh = $d['pohyblivost'] ?? null;
$zakl = $poh['zakladni'] ?? null;
$ob = $d['obrana'] ?? null;
$nos = $poh['nosnost_mn'] ?? null;
$zbrane = $d['zbrane'] ?? [];
$inv = array_values(array_filter($d['inventar'] ?? [], fn($i) => $i['kategorie'] === 'predmet' || $i['kategorie'] === 'lektvar'));
$nakl = $d['naklad'] ?? null;
$maMag = $postava && dracak_vtt_ma_magenergii($postava);
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Osobní deník<?= $postava ? ' — ' . $e($postava['nazev']) : ' (prázdný)' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
<style>
  @page { size: A4 portrait; margin: 9mm; }
  * { box-sizing: border-box; }
  html { background: #e8e2d4; }
  body { margin: 0; font-family: 'Playfair Display', Georgia, serif; font-size: 10.5px; color: #111; font-variant-numeric: lining-nums tabular-nums; }
  .strana { width: 192mm; min-height: 279mm; margin: 8mm auto; padding: 0; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.2); padding: 6mm; page-break-after: always; }
  .strana:last-child { page-break-after: auto; }
  h1 { font-size: 20px; margin: 0 0 2mm; letter-spacing: .04em; }
  h2 { font-size: 11px; margin: 0 0 1mm; text-transform: uppercase; letter-spacing: .08em; border-bottom: 1.2px solid #111; padding-bottom: .5mm; }
  .radek { display: flex; gap: 3mm; margin-bottom: 3mm; }
  .box { border: 1.2px solid #111; border-radius: 1.5mm; padding: 1.2mm 2mm 1mm; min-height: 9mm; flex: 1; }
  .box small { display: block; font-size: 7.5px; text-transform: uppercase; letter-spacing: .07em; color: #444; }
  .box b { font-size: 13px; }
  .box.velky b { font-size: 18px; }
  .sloupce { display: grid; grid-template-columns: 62mm 1fr; gap: 4mm; margin-bottom: 3mm; }
  table { width: 100%; border-collapse: collapse; }
  th { font-size: 7.5px; text-transform: uppercase; letter-spacing: .06em; text-align: left; border-bottom: 1.2px solid #111; padding: .6mm 1mm; font-weight: 600; }
  td { border-bottom: .6px solid #888; padding: .8mm 1mm; height: 6.4mm; font-size: 10.5px; }
  td.c, th.c { text-align: center; }
  .vl td:first-child { font-weight: 700; }
  .vl td { height: 7.6mm; font-size: 12px; }
  .draha { margin-bottom: 2.5mm; }
  .draha-n { font-size: 8px; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; margin-bottom: .6mm; }
  .draha-r { display: grid; grid-template-columns: repeat(25, 1fr); }
  .draha-r span { border: .6px solid #666; margin: 0 -.3px -.3px 0; text-align: center; font-size: 7.5px; padding: .6mm 0; }
  .draha-r span.akt { background: #111; color: #fff; font-weight: 700; }
  .draha-r span.nad { background: repeating-linear-gradient(45deg, #ddd, #ddd 1px, #fff 1px, #fff 3px); color: #888; }
  .mini { display: flex; gap: 2mm; margin-top: 1.5mm; }
  .mini .box { min-height: 8mm; }
  .radky td { height: 6.6mm; }
  .pod { font-size: 8px; color: #555; margin-top: .8mm; }
  .dve { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
  .tl { position: fixed; top: 6px; right: 10px; background: #fff; border: 1px solid #999; border-radius: 8px; padding: 6px 12px; font-size: 13px; cursor: pointer; z-index: 5; }
  @media print {
    html { background: #fff; }
    .strana { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; }
    .tl { display: none; }
  }
</style>
</head>
<body>
<button class="tl" type="button" onclick="window.print()">Vytisknout</button>

<!-- ===================== STRANA 1 ===================== -->
<section class="strana">
  <h1>Osobní deník</h1>
  <div class="radek">
    <div class="box" style="flex:2.2"><small>Jméno</small><b><?= $pole($postava['nazev'] ?? null) ?></b></div>
    <div class="box" style="flex:2.2"><small>Rasa a povolání</small><b><?= $pole($postava ? trim(($postava['rasa_nazev'] ?? '') . ' ' . ($postava['povolani_nazev'] ?? '')) : null) ?></b></div>
    <div class="box" style="flex:.7"><small>Úroveň</small><b><?= $pole($postava['uroven'] ?? null) ?></b></div>
    <div class="box" style="flex:1"><small>Zkušenost (zt)</small><b><?= $pole($postava['zkusenost'] ?? null) ?></b></div>
    <div class="box" style="flex:.9"><small>Erb (volitelné)</small><b>&nbsp;</b></div>
  </div>

  <div class="sloupce">
    <div>
      <h2>Vlastnosti</h2>
      <table class="vl">
        <thead><tr><th></th><th class="c">stupeň</th><th class="c">bonus</th></tr></thead>
        <tbody>
        <?php foreach ($vl as $kl => $nazev): ?>
          <tr><td><?= $e($nazev) ?></td><td class="c"><?= $pole($d['stupne'][$kl] ?? null) ?></td><td class="c"><?= $pole($d ? $znam($d['bonusy'][$kl]) : null) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="mini">
        <div class="box"><small>Velikost</small><b><?= $pole($d['velikost'] ?? null) ?></b></div>
        <div class="box"><small>Max. životů</small><b><?= $pole($postava ? $postava['max_hp'] : null) ?></b></div>
        <div class="box"><small>Max. magů</small><b><?= $pole($postava && $maMag ? $postava['max_magenergie'] : null) ?></b></div>
      </div>
      <div class="mini">
        <div class="box"><small>Postřeh — objekty</small><b><?= $pole($d && $d['postrech'] ? $d['postrech']['objekty'] . ' %' : null) ?></b></div>
        <div class="box"><small>Postřeh — mechanismy</small><b><?= $pole($d && $d['postrech'] ? $d['postrech']['mechanismy'] . ' %' : null) ?></b></div>
      </div>
    </div>
    <div>
      <?= $draha('Životy', $postava ? (int)$postava['aktualni_hp'] : null, $postava ? (int)$postava['max_hp'] : null) ?>
      <?= $draha('Magy', $postava && $maMag ? (int)$postava['aktualni_magenergie'] : null, $postava && $maMag ? (int)$postava['max_magenergie'] : null) ?>
      <h2>Pohyblivost a naložení</h2>
      <div class="mini">
        <div class="box"><small>Pohyblivost (základní)</small><b><?= $pole($zakl) ?></b></div>
        <div class="box"><small>bonus</small><b><?= $pole($poh ? $znam($poh['bonus_pohyblivosti'] ?? null) : null) ?></b></div>
        <div class="box"><small>mírné ¾</small><b><?= $pole($zakl !== null ? dracak_vtt_pohyblivost_po_nalozeni($zakl, 3) : null) ?></b></div>
        <div class="box"><small>střední ½</small><b><?= $pole($zakl !== null ? dracak_vtt_pohyblivost_po_nalozeni($zakl, 2) : null) ?></b></div>
        <div class="box"><small>velké ¼</small><b><?= $pole($zakl !== null ? dracak_vtt_pohyblivost_po_nalozeni($zakl, 1) : null) ?></b></div>
      </div>
      <div class="mini">
        <div class="box"><small>Nosnost — žádné do (mn)</small><b><?= $pole($nos) ?></b></div>
        <div class="box"><small>mírné do</small><b><?= $pole($nos ? $nos * 2 : null) ?></b></div>
        <div class="box"><small>střední do</small><b><?= $pole($nos ? $nos * 3 : null) ?></b></div>
        <div class="box"><small>velké do</small><b><?= $pole($nos ? $nos * 4 : null) ?></b></div>
      </div>
      <div class="mini">
        <div class="box"><small>Únava (body / vytrvalost)</small><b><?= $d && $d['unava'] ? (int)$d['unava']['body'] . ' / ' . (int)$d['unava']['vytrvalost'] : '&nbsp;' ?></b></div>
        <div class="box"><small>Doprava</small><b><?= $d && $d['doprava'] && $d['doprava']['typ'] === 'zvire' ? $e($d['doprava']['zvire']['nazev']) : ($d ? 'pěšky' : '&nbsp;') ?></b></div>
      </div>
    </div>
  </div>

  <h2>Boj tváří v tvář</h2>
  <table class="radky">
    <thead><tr><th>Zbraň</th><th class="c">ÚČ</th><th class="c">útočnost</th><th class="c">OZ</th><th>kde je uložena</th></tr></thead>
    <tbody>
    <?php for ($i = 0; $i < 5; $i++): $z = $zbrane[$i] ?? null; ?>
      <tr><td><?= $pole($z ? $z['nazev'] . ($z['rodova'] ? ' (rodová)' : '') : null) ?></td><td class="c"><?= $pole($z['uc_tvar_v_tvar'] ?? null) ?></td><td class="c"><?= $pole($z ? $znam($z['utocnost']) : null) ?></td><td class="c"><?= $pole($z ? $znam($z['oz']) : null) ?></td><td>&nbsp;</td></tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <div class="pod">ÚČ = síla zbraně + bonus za sílu (+1 rodová zbraň), nikdy méně než 0.</div>

  <h2 style="margin-top:3mm">Střelecký boj</h2>
  <table class="radky">
    <thead><tr><th>Zbraň</th><th class="c">ÚČ</th><th class="c">útočnost</th><th class="c">dostřel malý / střední / velký</th></tr></thead>
    <tbody>
    <?php
      $strelne = array_values(array_filter($zbrane, fn($z) => $z['uc_strelecky'] !== null));
      for ($i = 0; $i < 3; $i++): $z = $strelne[$i] ?? null; ?>
      <tr><td><?= $pole($z['nazev'] ?? null) ?></td><td class="c"><?= $pole($z['uc_strelecky'] ?? null) ?></td><td class="c"><?= $pole($z ? $znam($z['utocnost']) : null) ?></td><td class="c"><?= $pole($z && $z['dostrel'] ? implode(' / ', $z['dostrel']) : null) ?></td></tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <div class="pod">ÚČ střelecký = síla zbraně + bonus za obratnost. Bez OZ; útok tváří v tvář na střelce má postih −2 k obraně.</div>

  <div class="mini" style="margin-top:3mm">
    <div class="box"><small>Kvalita zbroje (KZ)</small><b><?= $pole($ob['kz'] ?? null) ?></b></div>
    <div class="box"><small>Bonus za obratnost</small><b><?= $pole($ob ? $znam($ob['bonus_obratnost']) : null) ?></b></div>
    <div class="box velky"><small>Obranné číslo (OČ)</small><b><?= $pole($ob['oc'] ?? null) ?></b></div>
    <div class="box"><small>Štít (+1 k obraně)</small><b><?= $pole($ob['stit'] ?? null) ?></b></div>
  </div>

  <div class="dve" style="margin-top:3mm">
    <div>
      <h2>Zvláštní schopnosti</h2>
      <table class="radky"><tbody>
      <?php $sch = array_values(array_map(fn($s) => $s['nazev'], $d['schopnosti'] ?? [])); for ($i = 0; $i < 7; $i++): ?>
        <tr><td><?= $pole($sch[$i] ?? null) ?></td></tr>
      <?php endfor; ?>
      </tbody></table>
    </div>
    <div>
      <h2>Kouzla</h2>
      <table class="radky"><tbody>
      <?php $kz = array_values(array_map(fn($k) => $k['nazev'] . ($k['cena_magenergie'] ? ' (' . $k['cena_magenergie'] . ')' : ''), $d['kouzla'] ?? [])); for ($i = 0; $i < 7; $i++): ?>
        <tr><td><?= $pole($kz[$i] ?? null) ?></td></tr>
      <?php endfor; ?>
      </tbody></table>
    </div>
  </div>
</section>

<!-- ===================== STRANA 2 ===================== -->
<section class="strana">
  <h1>Osobní deník — vybavení <?= $postava ? '— ' . $e($postava['nazev']) : '' ?></h1>

  <h2>Vybavení</h2>
  <table class="radky">
    <thead><tr><th>Předmět</th><th class="c">ks</th><th>umístění (kde je uložen)</th><th class="c">váha (mn)</th></tr></thead>
    <tbody>
    <?php for ($i = 0; $i < 22; $i++): $r = $inv[$i] ?? null; ?>
      <tr><td><?= $pole($r['nazev'] ?? null) ?></td><td class="c"><?= $pole($r['mnozstvi'] ?? null) ?></td><td>&nbsp;</td><td class="c"><?= $r && $r['vaha_celkem'] !== null ? $mn($r['vaha_celkem']) : '&nbsp;' ?></td></tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <div class="pod">Vybavení z katalogu bez uvedené váhy se do součtu nepočítá — dopiš ji ručně.</div>

  <div class="dve" style="margin-top:4mm">
    <div>
      <h2>Poklad</h2>
      <table class="radky">
        <thead><tr><th>Co</th><th class="c">cena</th><th>umístění</th><th class="c">váha</th></tr></thead>
        <tbody><?php for ($i = 0; $i < 6; $i++): ?><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr><?php endfor; ?></tbody>
      </table>
    </div>
    <div>
      <h2>Peníze (každá mince váží 1 mn)</h2>
      <div class="mini">
        <div class="box"><small>zlaťáky (zl)</small><b><?= $pole($d['penize']['zl'] ?? null) ?></b></div>
        <div class="box"><small>stříbrňáky (st)</small><b><?= $pole($d['penize']['st'] ?? null) ?></b></div>
        <div class="box"><small>měďáky (md)</small><b><?= $pole($d['penize']['md'] ?? null) ?></b></div>
      </div>
      <div class="pod">1 zl = 10 st = 100 md</div>
      <h2 style="margin-top:4mm">Náklad</h2>
      <div class="mini">
        <div class="box"><small>Celkem (poklad)</small><b>&nbsp;</b></div>
        <div class="box"><small>Základní náklad (vybavení)</small><b><?= $nakl ? $mn($nakl['naklad_mn'] - $nakl['penize_mn']) : '&nbsp;' ?></b></div>
      </div>
      <div class="mini">
        <div class="box velky"><small>Celkový náklad (mn)</small><b><?= $nakl ? $mn($nakl['naklad_mn']) : '&nbsp;' ?></b></div>
        <div class="box"><small>Naložení</small><b>&nbsp;</b></div>
      </div>
    </div>
  </div>

  <h2 style="margin-top:4mm">Poznámky</h2>
  <table class="radky"><tbody><?php for ($i = 0; $i < 9; $i++): ?><tr><td>&nbsp;</td></tr><?php endfor; ?></tbody></table>
</section>
</body>
</html>
