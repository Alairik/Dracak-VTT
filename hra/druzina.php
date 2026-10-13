<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_denik.php';

// Družina — přehled stavu všech postav světa na jedné obrazovce (PJ u
// stolu: kdo je zraněný, unavený, přetížený, jak rychle se družina
// pohybuje). "Družina se pohybuje rychlostí nejpomalejší postavy"
// (content/pravidla-pj.html, h2355) — proto dole tempo = nejnižší
// pohyblivost po naložení. Viditelné všem členům světa (stejná data už
// ukazují karty postav na nástěnce světa); PJ/admin odkaz do deníku
// kterékoli postavy, hráč jen do své.

$user = dracak_require_login();
$svetId = (int)($_GET['id'] ?? 0);
$svet = dracak_vtt_require_svet($user, $svetId);
$jePj = dracak_vtt_je_pj_sveta($user, $svetId);
$pdo = dracak_db();

$stmt = $pdo->prepare(
    'SELECT p.*, r.nazev AS rasa_nazev, pv.nazev AS povolani_nazev, u.jmeno AS vlastnik_jmeno
     FROM postavy p
     LEFT JOIN rasy r ON r.id = p.rasa_id
     LEFT JOIN povolani pv ON pv.id = p.povolani_id
     JOIN ucty u ON u.id = p.vlastnik_ucet_id
     WHERE p.svet_id = ? ORDER BY p.nazev'
);
$stmt->execute([$svetId]);
$postavy = $stmt->fetchAll();

$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$mn = static function ($n): string {
    return rtrim(rtrim(number_format((float)$n, 1, ',', ' '), '0'), ',');
};
$nalozeniNazvy = ['zadne' => 'žádné', 'mirne' => 'mírné', 'stredni' => 'střední', 'velke' => 'velké', 'prilis_velke' => 'nad 4×'];

$radky = [];
$nejpomalejsi = null;
foreach ($postavy as $p) {
    $d = dracak_vtt_denik_data($pdo, $p, false);
    $maxHp = (int)$p['max_hp'];
    $hp = (int)$p['aktualni_hp'];
    $poh = $d['pohyblivost'];
    $efPoh = $poh['pohyblivost'] ?? null;
    if ($efPoh !== null && ($nejpomalejsi === null || $efPoh < $nejpomalejsi['hodnota'])) {
        $nejpomalejsi = ['hodnota' => $efPoh, 'jmeno' => $p['nazev']];
    }
    $drzena = null;
    foreach ($d['zbrane'] as $z) {
        if ($z['drzena']) { $drzena = $z; }
    }
    $radky[] = [
        'p' => $p, 'd' => $d, 'hp' => $hp, 'maxHp' => $maxHp,
        'hpPct' => $maxHp > 0 ? max(0, min(100, (int)round($hp / $maxHp * 100))) : 0,
        'hpStav' => $d['bojeschopnost'] && $d['bojeschopnost']['vyrazena'] ? 'out' : ($d['bojeschopnost'] && $d['bojeschopnost']['pod_tretinou'] ? 'low' : 'ok'),
        'ucz' => $drzena,
        'smiOtevrit' => $jePj || (int)$p['vlastnik_ucet_id'] === (int)$user['id'],
    ];
}

$fragment = isset($_GET['fragment']);
if (!$fragment) {
    dracak_vtt_page_start('Družina — ' . $svet['nazev'], $user, true, ['../assets/css/denik.css']);
}
?>
<?php if (!$fragment): ?><div class="dn-wrap dz-wrap" data-svet-id="<?= $svetId ?>"><?php endif; ?>
<div class="dn" id="druzina">
  <header class="dn-pas dz-pas">
    <div class="dn-hlava">
      <div class="dn-id">
        <div class="dn-nav-horni">
          <a class="svet-pill svet-pill-ghost" href="svet.php?id=<?= $svetId ?>">← <?= $e($svet['nazev']) ?></a>
          <?php if ($jePj): ?><a class="svet-pill svet-pill-ghost" href="cesta.php?id=<?= $svetId ?>">Cesta</a><?php endif; ?>
        </div>
        <h1 class="dn-jmeno">Družina</h1>
        <div class="dn-pod">Stav všech postav světa na jednom místě.</div>
      </div>
      <div class="dn-bojcisla">
        <div class="dn-velke" title="Družina se pohybuje rychlostí nejpomalejší postavy">
          <span class="dn-velke-k">TEMPO</span>
          <span class="dn-velke-h"><?= $nejpomalejsi ? (int)$nejpomalejsi['hodnota'] : '—' ?></span>
          <span class="dn-velke-p"><?= $nejpomalejsi ? $e($nejpomalejsi['jmeno']) . ' (nejpomalejší)' : 'doplň atributy' ?></span>
        </div>
      </div>
    </div>
  </header>

  <?php if (!$radky): ?>
    <div class="dn-karta"><p class="note">Ve světě zatím nejsou žádné postavy.</p></div>
  <?php else: ?>
  <div class="dn-karta dn-sirka-cela">
    <div class="dn-tab-wrap">
      <table class="dn-tab dz-tab">
        <thead><tr>
          <th>Postava</th><th>Životy</th><th class="c">Magy</th><th>Únava</th><th>Naložení</th>
          <th class="c">Pohyblivost</th><th class="c">OČ</th><th class="c">ÚČ</th><th>Doprava</th><th>Efekty</th>
        </tr></thead>
        <tbody>
        <?php foreach ($radky as $r): $p = $r['p']; $d = $r['d']; $un = $d['unava']; $poh = $d['pohyblivost']; $nal = $poh['nalozeni'] ?? null; ?>
          <tr>
            <td>
              <?php if ($r['smiOtevrit']): ?><a href="denik.php?id=<?= (int)$p['id'] ?>"><strong><?= $e($p['nazev']) ?></strong></a><?php else: ?><strong><?= $e($p['nazev']) ?></strong><?php endif; ?>
              <div class="note"><?= $e(implode(' · ', array_filter([$p['rasa_nazev'], $p['povolani_nazev'], (int)$p['uroven'] . '. úr.', $p['vlastnik_jmeno']]))) ?></div>
            </td>
            <td style="min-width:150px">
              <strong><?= $r['hp'] ?></strong> / <?= $r['maxHp'] ?>
              <?php if ($r['hpStav'] === 'out'): ?><span class="dn-stitek nebezpeci">vyřazena</span><?php elseif ($r['hpStav'] === 'low'): ?><span class="dn-stitek varovani">pod 1/3</span><?php endif; ?>
              <div class="dn-bar <?= $r['hpStav'] ?>"><i style="width:<?= $r['hpPct'] ?>%"></i></div>
            </td>
            <td class="c"><?= dracak_vtt_ma_magenergii($p) ? (int)$p['aktualni_magenergie'] . ' / ' . (int)$p['max_magenergie'] : '—' ?></td>
            <td><?php if ($un): ?><?= (int)$un['body'] ?> / <?= (int)$un['vytrvalost'] ?> <?= $un['stupen'] > 0 ? '<span class="dn-stitek ' . ($un['stupen'] >= 3 ? 'nebezpeci' : 'varovani') . '">' . $e($un['nazev']) . '</span>' : '' ?><?php else: ?>—<?php endif; ?></td>
            <td><?php if ($nal): ?><?= $mn($d['naklad']['naklad_mn']) ?> mn <span class="dn-stitek <?= in_array($nal['kod'], ['velke', 'prilis_velke'], true) ? 'nebezpeci' : ($nal['kod'] === 'zadne' ? '' : 'varovani') ?>"><?= $e($nalozeniNazvy[$nal['kod']]) ?></span><?= $d['naklad']['neuplny'] ? ' <span class="dn-stitek neuplne" title="Součet nákladu je neúplný">?</span>' : '' ?><?php else: ?>—<?php endif; ?></td>
            <td class="c"><strong><?= $poh && $poh['pohyblivost'] !== null ? (int)$poh['pohyblivost'] : '—' ?></strong></td>
            <td class="c uc"><?= $d['obrana'] ? (int)$d['obrana']['oc'] : '—' ?></td>
            <td class="c uc" title="<?= $r['ucz'] ? $e($r['ucz']['nazev']) : 'nevybraná zbraň' ?>"><?= $r['ucz'] && $r['ucz']['uc_tvar_v_tvar'] !== null ? (int)$r['ucz']['uc_tvar_v_tvar'] : '—' ?></td>
            <td><?= $d['doprava'] && $d['doprava']['typ'] === 'zvire' ? $e($d['doprava']['zvire']['nazev']) : 'pěšky' ?></td>
            <td>
              <?php
                $stmtE = $pdo->prepare("SELECT e.nazev FROM aktivni_efekty ae JOIN efekty e ON e.id = ae.efekt_id WHERE ae.typ_entity = 'postava' AND ae.entita_id = ?");
                $stmtE->execute([(int)$p['id']]);
                $ef = $stmtE->fetchAll(PDO::FETCH_COLUMN);
              ?>
              <?= $ef ? $e(implode(', ', $ef)) : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="note">ÚČ = držená zbraň (vybírá se v deníku). Pohyblivost je po naložení. Tempo družiny je nejnižší pohyblivost — družina se pohybuje rychlostí nejpomalejší postavy.</p>
  </div>
  <?php endif; ?>
</div>
<?php if (!$fragment): ?>
</div>
<script>
// Překreslení každých 5 s (životy, únava… se mění za hry).
(function () {
  const ID = document.querySelector('.dz-wrap').dataset.svetId;
  setInterval(async function () {
    if (document.hidden) return;
    try {
      const r = await fetch('druzina.php?id=' + encodeURIComponent(ID) + '&fragment=1', { credentials: 'same-origin' });
      if (!r.ok) return;
      const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
      const novy = doc.getElementById('druzina');
      const stary = document.getElementById('druzina');
      if (novy && stary) stary.replaceWith(novy);
    } catch (e) { /* zkusí se příště */ }
  }, 5000);
})();
</script>
<?php dracak_vtt_page_end(); ?>
<?php endif; ?>
