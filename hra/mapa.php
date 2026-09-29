<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';

$user = dracak_require_login();
$isPjOrAdmin = in_array($user['role'], ['admin', 'pj'], true);
$mapaId = (int)($_GET['id'] ?? 0);

$stmt = dracak_db()->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa) {
    http_response_code(404);
    die('Mapa nenalezena.');
}
$svet = dracak_vtt_require_svet($user, (int)$mapa['svet_id']);
$svetId = (int)$svet['id'];

$stmt = dracak_db()->prepare(
    'SELECT t.id, t.typ_entity, t.entita_id, t.x, t.y, t.z_poradi, t.viditelny_hracum,
            p.nazev AS postava_nazev, p.vlastnik_ucet_id, p.aktualni_hp AS postava_hp, p.max_hp AS postava_max_hp,
            ni.nazev_instance, ni.aktualni_hp AS nestvura_hp, ni.max_hp AS nestvura_max_hp
     FROM tokeny t
     LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
     LEFT JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = "nestvura_instance"
     WHERE t.mapa_id = ?'
);
$stmt->execute([$mapaId]);
$tokeny = $stmt->fetchAll();
if (!$isPjOrAdmin) {
    $tokeny = array_values(array_filter($tokeny, fn($t) => (bool)$t['viditelny_hracum']));
}
foreach ($tokeny as &$t) {
    $t['label'] = $t['typ_entity'] === 'postava' ? $t['postava_nazev'] : $t['nazev_instance'];
    $t['hp'] = $t['typ_entity'] === 'postava' ? $t['postava_hp'] : $t['nestvura_hp'];
    $t['max_hp'] = $t['typ_entity'] === 'postava' ? $t['postava_max_hp'] : $t['nestvura_max_hp'];
    $t['owned'] = $isPjOrAdmin || ((int)($t['vlastnik_ucet_id'] ?? 0) === (int)$user['id']);
}
unset($t);

// Aktivní efekty pro všechny entity, co mají na téhle mapě token.
$aktivniEfektyByEntity = [];
if ($tokeny) {
    $pary = array_map(fn($t) => $t['typ_entity'] . ':' . $t['entita_id'], $tokeny);
    $pary = array_unique($pary);
    $where = implode(' OR ', array_fill(0, count($pary), '(ae.typ_entity = ? AND ae.entita_id = ?)'));
    $params = [];
    foreach ($pary as $p) { [$te, $ei] = explode(':', $p); $params[] = $te; $params[] = $ei; }
    $stmt = dracak_db()->prepare(
        "SELECT ae.typ_entity, ae.entita_id, ae.zbyva_kol, e.nazev FROM aktivni_efekty ae
         JOIN efekty e ON e.id = ae.efekt_id WHERE $where"
    );
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $ae) {
        $key = $ae['typ_entity'] . ':' . $ae['entita_id'];
        $aktivniEfektyByEntity[$key][] = $ae['nazev'] . ($ae['zbyva_kol'] !== null ? ' (' . $ae['zbyva_kol'] . ' kol)' : '');
    }
}

require_once __DIR__ . '/../includes/vtt_predmety.php';

// Inventář (predmety/lektvary/kouzla) pro každou entitu, co má na téhle mapě
// token — stejný vzor jako $aktivniEfektyByEntity o pár řádků výš. Funguje
// jednotně pro postavu i nestvura_instance (dracak_vtt_inventar() normalizuje
// nestvura_instance_vybava do stejného tvaru).
$inventarByEntity = [];
if ($tokeny) {
    foreach (array_unique(array_map(fn($t) => $t['typ_entity'] . ':' . $t['entita_id'], $tokeny)) as $klic) {
        [$te, $ei] = explode(':', $klic);
        $inventarByEntity[$klic] = dracak_vtt_inventar($te, (int)$ei);
    }
}

// Cíle pro "Předat kořist" — všechny postavy ve světě (ne jen ty s tokenem
// na téhle mapě). Jen PJ/admin je smí použít; loot.php to stejně vynucuje
// server-side, tohle je jen pro UI.
$svetPostavyKorist = [];
if ($isPjOrAdmin) {
    $stmt = dracak_db()->prepare('SELECT id, nazev FROM postavy WHERE svet_id = ? ORDER BY nazev');
    $stmt->execute([$svetId]);
    $svetPostavyKorist = $stmt->fetchAll();
}

$stmt = dracak_db()->prepare(
    'SELECT p.id, p.nazev FROM postavy p
     WHERE p.svet_id = ? AND p.id NOT IN (SELECT entita_id FROM tokeny WHERE mapa_id = ? AND typ_entity = "postava")
     ' . ($isPjOrAdmin ? '' : 'AND p.vlastnik_ucet_id = ?') . '
     ORDER BY p.nazev'
);
$params = $isPjOrAdmin ? [$svetId, $mapaId] : [$svetId, $mapaId, $user['id']];
$stmt->execute($params);
$volnePostavy = $stmt->fetchAll();

$nestvuryKatalog = [];
$efektyKatalog = [];
$rychleEfekty = [];
if ($isPjOrAdmin) {
    $nestvuryKatalog = dracak_db()->query('SELECT id, nazev FROM nestvury ORDER BY nazev')->fetchAll();
    $efektyKatalog = dracak_db()->query('SELECT id, nazev, typ FROM efekty ORDER BY nazev')->fetchAll();
    $rychleEfekty = dracak_db()->query('SELECT id, nazev FROM efekty WHERE rychla_volba = 1 ORDER BY nazev')->fetchAll();
}

require_once __DIR__ . '/../includes/vtt_iniciativa.php';
$stmt = dracak_db()->prepare('SELECT * FROM kolo_stav WHERE mapa_id = ?');
$stmt->execute([$mapaId]);
$koloStav = $stmt->fetch() ?: null;
$iniciativaPoradi = $koloStav ? dracak_vtt_iniciativa_poradi(dracak_db(), $mapaId) : [];

$posledniUdalostId = (int)(dracak_db()->query('SELECT MAX(id) FROM svet_udalosti WHERE svet_id = ' . $svetId)->fetchColumn() ?: 0);
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dračák VTT — <?= htmlspecialchars($mapa['nazev']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/organic.css?v=<?= filemtime(__DIR__ . '/../assets/css/organic.css') ?>">
<style>
  html, body { margin:0; padding:0; height:100%; overflow:hidden; background:#161412; }
  #viewport { position:fixed; inset:0; overflow:auto; }
  #mapWrap { position:relative; display:inline-block; }
  #emptyState { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; color:#ddd; text-align:center; padding:24px; }

  .topbar { position:fixed; top:14px; left:50%; transform:translateX(-50%); z-index:200;
            background:rgba(24,22,20,.88); color:#fff; border-radius:999px; padding:8px 18px;
            display:flex; align-items:center; gap:10px; font-size:14px; backdrop-filter:blur(4px); }
  .topbar a { color:#e8c9a8; text-decoration:none; font-weight:600; }
  .topbar .typ { font-size:11px; opacity:.65; }

  .toolbar { position:fixed; left:16px; top:50%; transform:translateY(-50%); z-index:200;
             display:flex; flex-direction:column; gap:8px; background:rgba(24,22,20,.88);
             padding:8px; border-radius:14px; backdrop-filter:blur(4px); }
  .tbtn { width:46px; height:46px; border-radius:10px; border:none; background:transparent; color:#eee;
          font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center;
          transition:background .12s; }
  .tbtn:hover { background:rgba(255,255,255,.12); }
  .tbtn.active { background:var(--color-accent-600, #7a3b2e); }
  .tbtn.armed { background:#3a6b4a; }
  .tbtn svg, .close-x svg { display:block; }

  .popover { position:fixed; left:74px; z-index:210; background:rgba(24,22,20,.95); color:#eee;
             border-radius:12px; padding:14px; width:280px; box-shadow:0 8px 24px rgba(0,0,0,.5);
             display:none; }
  .popover.open { display:block; }
  .popover h3 { margin:0 0 8px; font-size:13px; color:#e8c9a8; }
  .popover label { font-size:11px; font-weight:600; display:block; margin:8px 0 3px; }
  .popover select, .popover input { width:100%; box-sizing:border-box; }
  .popover .note { font-size:11px; color:#bbb; margin-top:8px; }
  .popover .close-x { position:absolute; top:8px; right:10px; cursor:pointer; color:#999; background:none; border:none; }

  .panel { position:fixed; right:0; top:0; bottom:0; width:300px; z-index:200; background:rgba(20,18,16,.95);
           color:#eee; padding:16px; box-sizing:border-box; transform:translateX(100%); transition:transform .15s;
           overflow-y:auto; }
  .panel.open { transform:translateX(0); }
  .panel h3 { margin:0 0 10px; font-size:14px; color:#e8c9a8; }
  .panel .close-x { position:absolute; top:12px; right:14px; cursor:pointer; color:#999; background:none; border:none; }
  .panel-hp-row { display:flex; gap:6px; margin:8px 0; }
  .panel-hp-row button { flex:1; }
  .panel select, .panel input { width:100%; box-sizing:border-box; margin-bottom:6px; }

  #logList { font-size:12.5px; display:flex; flex-direction:column-reverse; gap:5px; }

  .vtt-token { position:absolute; width:40px; text-align:center; user-select:none; }
  .vtt-token .puck { width:40px; height:40px; border-radius:50%; color:#fff; display:flex; align-items:center;
                      justify-content:center; font-size:11px; font-weight:600; box-shadow:0 2px 6px rgba(0,0,0,.5); }
  .vtt-token.dead .puck { filter: grayscale(1); opacity: .55; }
  .vtt-token .hp { font-size:10px; background:rgba(0,0,0,.65); color:#fff; border-radius:4px; margin-top:2px; padding:1px 3px; }
  .vtt-token .fx { font-size:9px; background:rgba(0,0,0,.55); color:#fdd; border-radius:4px; margin-top:1px; padding:1px 3px; }

  .vtt-ping { position:absolute; transform:translate(-50%,-50%); pointer-events:none; z-index:150;
              display:flex; flex-direction:column; align-items:center; }
  .ping-dot { width:22px; height:22px; border-radius:50%; border:3px solid #ffb347; box-sizing:border-box;
              animation:pingPulse 1s ease-out infinite; }
  .ping-label { margin-top:4px; font-size:11px; background:rgba(0,0,0,.7); color:#fff; padding:1px 6px;
                border-radius:4px; white-space:nowrap; }
  @keyframes pingPulse {
    0% { box-shadow:0 0 0 0 rgba(255,179,71,.6); }
    100% { box-shadow:0 0 0 16px rgba(255,179,71,0); }
  }
</style>
</head>
<body>

<div class="topbar">
  <a href="svet.php?id=<?= $svetId ?>">← <?= htmlspecialchars($svet['nazev']) ?></a>
  <span><?= htmlspecialchars($mapa['nazev']) ?></span>
  <span class="typ">(<?= $mapa['typ_mapy'] === 'svet' ? 'světová mapa' : 'zóna' ?>)</span>
</div>

<?php if (!$mapa['obrazek_cesta']): ?>
  <div id="emptyState">Tahle mapa ještě nemá nahraný obrázek — přidej ho na stránce světa.</div>
<?php else: ?>

  <div id="viewport">
    <div id="mapWrap">
      <img id="mapImg" src="mapa_obrazek.php?id=<?= $mapaId ?>" style="display:block;max-width:none;">
      <svg id="gridSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:0;"></svg>
      <svg id="rulerSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:140;"></svg>
      <?php foreach ($tokeny as $t):
          $entKey = $t['typ_entity'] . ':' . $t['entita_id'];
          $efektyText = implode(', ', $aktivniEfektyByEntity[$entKey] ?? []);
      ?>
        <div class="vtt-token<?= ($t['hp'] !== null && (int)$t['hp'] <= 0) ? ' dead' : '' ?>"
             data-id="<?= (int)$t['id'] ?>" data-owned="<?= $t['owned'] ? 1 : 0 ?>"
             data-typ-entity="<?= htmlspecialchars($t['typ_entity']) ?>" data-entita-id="<?= (int)$t['entita_id'] ?>"
             data-label="<?= htmlspecialchars((string)$t['label']) ?>"
             data-hp="<?= $t['hp'] !== null ? (int)$t['hp'] : '' ?>"
             title="<?= htmlspecialchars((string)$t['label']) ?>"
             style="left:<?= (int)$t['x'] ?>px;top:<?= (int)$t['y'] ?>px;cursor:<?= $t['owned'] ? 'grab' : 'default' ?>;z-index:<?= (int)$t['z_poradi'] ?>;">
          <div class="puck" style="background:<?= $t['typ_entity'] === 'postava' ? 'var(--color-accent-600, #7a3b2e)' : '#4a2b2b' ?>;">
            <?= htmlspecialchars(mb_substr((string)$t['label'], 0, 2)) ?>
          </div>
          <div class="hp" data-token-id="<?= (int)$t['id'] ?>"><?= $t['hp'] !== null ? (int)$t['hp'] . '/' . (int)$t['max_hp'] : '—' ?></div>
          <?php if ($efektyText): ?><div class="fx"><?= htmlspecialchars($efektyText) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="toolbar">
    <?php if ($volnePostavy || ($isPjOrAdmin && $nestvuryKatalog)): ?>
      <button class="tbtn" type="button" id="tb-pridat" title="Přidat token"><?php dracak_icon('user-round-plus'); ?></button>
    <?php endif; ?>
    <button class="tbtn armed" type="button" id="tb-move" title="Vybrat / přesunout"><?php dracak_icon('move'); ?></button>
    <button class="tbtn" type="button" id="tb-ruler" title="Měřit vzdálenost"><?php dracak_icon('ruler'); ?></button>
    <button class="tbtn" type="button" id="tb-ping" title="Ukázat na mapu ostatním"><?php dracak_icon('crosshair'); ?></button>
    <button class="tbtn" type="button" id="tb-kostky" title="Hodit kostkou"><?php dracak_icon('dices'); ?></button>
    <button class="tbtn" type="button" id="tb-log" title="Log"><?php dracak_icon('scroll-text'); ?></button>
    <?php if ($isPjOrAdmin): ?>
      <button class="tbtn" type="button" id="tb-kolo" title="Konec kola (odpočítat trvání efektů)"><?php dracak_icon('skip-forward'); ?></button>
      <button class="tbtn" type="button" id="tb-dalsi-tah" title="Další na tahu (iniciativa)"><?php dracak_icon('skip-forward'); ?></button>
    <?php endif; ?>
  </div>

  <?php if ($koloStav): ?>
  <div id="panelIniciativa" style="position:fixed;top:70px;right:16px;z-index:190;background:rgba(24,22,20,.92);color:#fff;border-radius:12px;padding:10px 14px;min-width:230px;font-size:13px;">
    <div style="font-weight:600;margin-bottom:6px;">Kolo <?= (int)$koloStav['cislo_kola'] ?> — pořadí tahů</div>
    <div id="iniciativaList">
      <?php foreach ($iniciativaPoradi as $r): $jeAktivni = (int)$r['token_id'] === (int)($koloStav['aktivni_token_id'] ?? 0); ?>
        <div data-token-id="<?= (int)$r['token_id'] ?>" style="display:flex;justify-content:space-between;gap:8px;padding:2px 0;<?= $jeAktivni ? 'color:#e8c9a8;font-weight:600;' : '' ?>">
          <span><?= htmlspecialchars((string)($r['label'] ?? '?')) ?></span>
          <span><?= (int)$r['hod'] ?><?= $r['modifikator'] >= 0 ? '+' . (int)$r['modifikator'] : (int)$r['modifikator'] ?>=<?= (int)$r['vysledek'] ?> · <?= (int)$r['akce_zbyvajici'] ?>/<?= (int)$r['akce_celkem'] ?> akcí</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="popover" id="popover-pridat">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Přidat token</h3>
    <?php if ($volnePostavy): ?>
      <label for="novaPostavaSelect">Postava</label>
      <select class="input" id="novaPostavaSelect">
        <option value="">—</option>
        <?php foreach ($volnePostavy as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nazev']) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <?php if ($isPjOrAdmin && $nestvuryKatalog): ?>
      <label for="nestvuraHledat">Nestvůra (bestiář)</label>
      <input class="input" type="text" id="nestvuraHledat" placeholder="hledat…">
      <select class="input" id="novaNestvuraSelect" size="6">
        <?php foreach ($nestvuryKatalog as $n): ?><option value="<?= (int)$n['id'] ?>"><?= htmlspecialchars($n['nazev']) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <p class="note">Vyber a klikni na mapu, kam to položit.</p>
  </div>

  <div class="popover" id="popover-kostky">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Hodit kostkou</h3>
    <input class="input" type="text" id="kostkyNotace" placeholder="2k6+2">
    <button class="btn btn-primary" id="hoditBtn" type="button" style="width:100%;margin-top:8px;">Hodit</button>
  </div>

  <div class="panel" id="panel-log">
    <button class="close-x" data-close-panel><?php dracak_icon('x', 16); ?></button>
    <h3>Log</h3>
    <div id="logList"></div>
  </div>

  <div class="panel" id="panel-spravovat">
    <button class="close-x" data-close-panel><?php dracak_icon('x', 16); ?></button>
    <h3 id="spravovatNazev">—</h3>
    <div class="panel-hp-row">
      <button class="btn btn-ghost" type="button" data-delta="-5">−5</button>
      <button class="btn btn-ghost" type="button" data-delta="-1">−1</button>
      <button class="btn btn-ghost" type="button" data-delta="1">+1</button>
      <button class="btn btn-ghost" type="button" data-delta="5">+5</button>
    </div>
    <div style="margin-top:10px;">
      <label style="font-size:11px;font-weight:600;">Iniciativa — bonus/postih (dočasně ruční, tabulka str. 78 zatím chybí)</label>
      <div style="display:flex;gap:6px;">
        <input type="number" id="iniciativaModifikator" value="0" class="input" style="width:70px;">
        <button class="btn btn-secondary" type="button" id="iniciativaHoditBtn" style="flex:1;">Hoď iniciativu</button>
      </div>
    </div>
    <?php if ($isPjOrAdmin && $rychleEfekty): ?>
      <label style="font-size:11px;font-weight:600;">Rychlé efekty</label>
      <div id="rychleEfektyChips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
        <?php foreach ($rychleEfekty as $e): ?>
          <button type="button" class="btn btn-ghost chip-efekt" data-efekt-id="<?= (int)$e['id'] ?>" style="padding:4px 10px;font-size:12px;"><?= htmlspecialchars($e['nazev']) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($isPjOrAdmin && $efektyKatalog): ?>
      <label style="font-size:11px;font-weight:600;">Aplikovat efekt</label>
      <select id="efektSelect">
        <?php foreach ($efektyKatalog as $e): ?><option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['nazev']) ?> (<?= htmlspecialchars($e['typ']) ?>)</option><?php endforeach; ?>
      </select>
      <input type="number" id="efektKola" placeholder="kol (prázdné = trvalé)">
      <button class="btn btn-secondary" type="button" id="efektBtn" style="width:100%;">Aplikovat efekt</button>
    <?php endif; ?>
    <div id="spravovatInventar" style="margin-top:14px;"></div>
    <div id="spravovatKorist" style="margin-top:14px;"></div>
    <button class="btn btn-ghost" type="button" id="smazatTokenBtn" style="width:100%;margin-top:14px;">Smazat token</button>
  </div>

  <div id="diceBoxContainer" style="position:fixed;inset:0;pointer-events:none;z-index:9999;"></div>

<script type="module">
const SVET_ID = <?= $svetId ?>;
const MAPA_ID = <?= $mapaId ?>;
const GRID_PX = <?= (int)($mapa['grid_velikost_px'] ?? 0) ?>;
const GRID_TYPE = <?= json_encode($mapa['grid_typ'] ?? 'ctverec') ?>;
const GRID_OFFSET_X = <?= (int)($mapa['grid_posun_x'] ?? 0) ?>;
const GRID_OFFSET_Y = <?= (int)($mapa['grid_posun_y'] ?? 0) ?>;
let posledniUdalostId = <?= $posledniUdalostId ?>;
const INVENTAR = <?= json_encode($inventarByEntity, JSON_UNESCAPED_UNICODE) ?>;
const SVET_POSTAVY = <?= json_encode($svetPostavyKorist, JSON_UNESCAPED_UNICODE) ?>;
const IS_PJ_OR_ADMIN = <?= $isPjOrAdmin ? 'true' : 'false' ?>;

const mapWrap = document.getElementById('mapWrap');
const viewport = document.getElementById('viewport');
const logList = document.getElementById('logList');
const rulerSvg = document.getElementById('rulerSvg');
const mapImgEl = document.getElementById('mapImg');

// --- Grid overlay: čistě vizuální, neinteraktivní (pointer-events:none),
// nad obrázkem a pod tokeny/rulerem. Čtverec = rovné čáry po GRID_PX.
// Hex = "pointy-top" šestiúhelníky (vrchol nahoře/dole, řady vodorovně,
// liché řady posunuté o půl šířky doprava — "odd-r offset" layout),
// GRID_PX je hex "size" = poloměr od středu k vrcholu (circumradius).
function renderGrid() {
  const gridSvg = document.getElementById('gridSvg');
  if (!gridSvg || !GRID_PX || GRID_PX <= 0) return;
  const w = mapImgEl.naturalWidth || mapWrap.clientWidth;
  const h = mapImgEl.naturalHeight || mapWrap.clientHeight;
  if (!w || !h) return;

  const stroke = 'rgba(255,255,255,0.35)';
  const sw = 1;
  let svg = '';

  if (GRID_TYPE === 'hex') {
    const size = GRID_PX;
    const colStep = Math.sqrt(3) * size;
    const rowStep = 1.5 * size;
    const offX = ((GRID_OFFSET_X % colStep) + colStep) % colStep;
    const offY = ((GRID_OFFSET_Y % rowStep) + rowStep) % rowStep;
    const firstRow = -1, lastRow = Math.ceil((h - offY) / rowStep) + 1;
    const firstCol = -1, lastCol = Math.ceil((w - offX) / colStep) + 1;
    for (let row = firstRow; row <= lastRow; row++) {
      const cy = offY + row * rowStep;
      const rowShift = (Math.abs(row % 2) === 1) ? colStep / 2 : 0;
      for (let col = firstCol; col <= lastCol; col++) {
        const cx = offX + col * colStep + rowShift;
        if (cx < -colStep || cx > w + colStep || cy < -rowStep * 1.5 || cy > h + rowStep * 1.5) continue;
        let points = '';
        for (let i = 0; i < 6; i++) {
          const a = Math.PI / 180 * (60 * i - 30);
          points += (i ? ' ' : '') + (cx + size * Math.cos(a)).toFixed(1) + ',' + (cy + size * Math.sin(a)).toFixed(1);
        }
        svg += '<polygon points="' + points + '" fill="none" stroke="' + stroke + '" stroke-width="' + sw + '"/>';
      }
    }
  } else {
    const offX = ((GRID_OFFSET_X % GRID_PX) + GRID_PX) % GRID_PX;
    const offY = ((GRID_OFFSET_Y % GRID_PX) + GRID_PX) % GRID_PX;
    for (let x = offX; x <= w; x += GRID_PX) {
      svg += '<line x1="' + x.toFixed(1) + '" y1="0" x2="' + x.toFixed(1) + '" y2="' + h + '" stroke="' + stroke + '" stroke-width="' + sw + '"/>';
    }
    for (let y = offY; y <= h; y += GRID_PX) {
      svg += '<line x1="0" y1="' + y.toFixed(1) + '" x2="' + w + '" y2="' + y.toFixed(1) + '" stroke="' + stroke + '" stroke-width="' + sw + '"/>';
    }
  }

  gridSvg.setAttribute('width', w);
  gridSvg.setAttribute('height', h);
  gridSvg.innerHTML = svg;
}
if (mapImgEl.complete) renderGrid(); else mapImgEl.addEventListener('load', renderGrid);

function logLine(text) {
  if (!logList) return;
  const div = document.createElement('div');
  div.textContent = text;
  logList.appendChild(div);
}

function postJson(url, body) {
  return fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)}).then(r => r.json());
}

// --- Popovery a panely ---
function closeAllPopovers() { document.querySelectorAll('.popover.open').forEach(p => p.classList.remove('open')); document.querySelectorAll('.tbtn.active').forEach(b => b.classList.remove('active')); }
function togglePopover(id, btn) {
  const wasOpen = document.getElementById(id).classList.contains('open');
  closeAllPopovers();
  if (!wasOpen) {
    const pop = document.getElementById(id);
    pop.style.top = btn.getBoundingClientRect().top + 'px';
    pop.classList.add('open');
    btn.classList.add('active');
  }
}
document.querySelectorAll('[data-close-popover]').forEach(b => b.addEventListener('click', closeAllPopovers));
document.querySelectorAll('[data-close-panel]').forEach(b => b.addEventListener('click', (e) => e.target.closest('.panel').classList.remove('open')));

const tbPridat = document.getElementById('tb-pridat');
if (tbPridat) tbPridat.addEventListener('click', () => togglePopover('popover-pridat', tbPridat));
const tbKostky = document.getElementById('tb-kostky');
if (tbKostky) tbKostky.addEventListener('click', () => togglePopover('popover-kostky', tbKostky));
const tbLog = document.getElementById('tb-log');
if (tbLog) tbLog.addEventListener('click', () => document.getElementById('panel-log').classList.toggle('open'));

// --- Nástroje toolbaru: select/move (výchozí), ruler (měření), ping ---
let currentTool = 'select';
const toolButtons = {
  select: document.getElementById('tb-move'),
  ruler: document.getElementById('tb-ruler'),
  ping: document.getElementById('tb-ping'),
};
function setTool(tool) {
  currentTool = tool;
  for (const [t, btn] of Object.entries(toolButtons)) {
    if (btn) btn.classList.toggle('armed', t === tool);
  }
  rulerStart = null;
  if (rulerSvg) rulerSvg.innerHTML = '';
}
if (toolButtons.select) toolButtons.select.addEventListener('click', () => setTool('select'));
if (toolButtons.ruler) toolButtons.ruler.addEventListener('click', () => setTool(currentTool === 'ruler' ? 'select' : 'ruler'));
if (toolButtons.ping) toolButtons.ping.addEventListener('click', () => setTool(currentTool === 'ping' ? 'select' : 'ping'));

// --- Ruler: čistě klientská pomůcka, nic se neukládá ani nesynchronizuje ---
let rulerStart = null, rulerClearTimer = null;
function rulerPoint(e) {
  const r = mapWrap.getBoundingClientRect();
  return {x: e.clientX - r.left, y: e.clientY - r.top};
}
function drawRuler(a, b) {
  if (!rulerSvg) return;
  clearTimeout(rulerClearTimer);
  const dx = b.x - a.x, dy = b.y - a.y;
  const distPx = Math.sqrt(dx * dx + dy * dy);
  const label = GRID_PX > 0 ? (distPx / GRID_PX).toFixed(1) + ' polí' : Math.round(distPx) + ' px';
  rulerSvg.innerHTML =
    '<line x1="' + a.x + '" y1="' + a.y + '" x2="' + b.x + '" y2="' + b.y + '" stroke="#ffb347" stroke-width="2" stroke-dasharray="6 4"/>' +
    '<circle cx="' + a.x + '" cy="' + a.y + '" r="4" fill="#ffb347"/>' +
    '<circle cx="' + b.x + '" cy="' + b.y + '" r="4" fill="#ffb347"/>' +
    '<text x="' + (a.x + b.x) / 2 + '" y="' + ((a.y + b.y) / 2 - 8) + '" fill="#fff" font-size="13" ' +
    'text-anchor="middle" paint-order="stroke" stroke="#000" stroke-width="3">' + label + '</text>';
}
function clearRulerSoon() {
  rulerClearTimer = setTimeout(() => { if (rulerSvg) rulerSvg.innerHTML = ''; }, 2500);
}

// --- Ping: efemérní ukazovátko, viditelné i ostatním přes polling ---
function zobrazPing(x, y, jmeno) {
  const el = document.createElement('div');
  el.className = 'vtt-ping';
  el.style.left = x + 'px';
  el.style.top = y + 'px';
  const dot = document.createElement('div');
  dot.className = 'ping-dot';
  el.appendChild(dot);
  if (jmeno) {
    const label = document.createElement('div');
    label.className = 'ping-label';
    label.textContent = jmeno;
    el.appendChild(label);
  }
  mapWrap.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

const nestvuraHledat = document.getElementById('nestvuraHledat');
if (nestvuraHledat) {
  nestvuraHledat.addEventListener('input', () => {
    const q = nestvuraHledat.value.trim().toLowerCase();
    document.querySelectorAll('#novaNestvuraSelect option').forEach(opt => {
      opt.style.display = (!q || opt.textContent.toLowerCase().includes(q)) ? '' : 'none';
    });
  });
}

// --- Drag i klik na token (klik = otevřít panel Spravovat, drag = přesun) ---
let dragEl = null, dragOffsetX = 0, dragOffsetY = 0, dragMoved = false, dragStartClientX = 0, dragStartClientY = 0;
const spravovatPanel = document.getElementById('panel-spravovat');
let spravovanyToken = null;

function otevritSpravovat(el) {
  spravovanyToken = {
    typEntity: el.dataset.typEntity,
    entitaId: parseInt(el.dataset.entitaId, 10),
    tokenId: parseInt(el.dataset.id, 10),
    hp: el.dataset.hp === '' ? null : parseInt(el.dataset.hp, 10),
  };
  document.getElementById('spravovatNazev').textContent = el.dataset.label;
  renderInventar();
  renderKorist();
  spravovatPanel.classList.add('open');
}

if (mapWrap) {
  mapWrap.addEventListener('mousedown', (e) => {
    if (currentTool === 'ruler') {
      if (e.target.closest('.vtt-token')) return;
      rulerStart = rulerPoint(e);
      drawRuler(rulerStart, rulerStart);
      return;
    }
    if (currentTool !== 'select') return;
    const el = e.target.closest('.vtt-token');
    if (!el || el.dataset.owned !== '1') return;
    dragEl = el;
    dragMoved = false;
    dragStartClientX = e.clientX;
    dragStartClientY = e.clientY;
    const rect = el.getBoundingClientRect();
    dragOffsetX = e.clientX - rect.left;
    dragOffsetY = e.clientY - rect.top;
  });
  window.addEventListener('mousemove', (e) => {
    if (currentTool === 'ruler') {
      if (!rulerStart) return;
      drawRuler(rulerStart, rulerPoint(e));
      return;
    }
    if (!dragEl) return;
    if (!dragMoved && (Math.abs(e.clientX - dragStartClientX) > 5 || Math.abs(e.clientY - dragStartClientY) > 5)) {
      dragMoved = true;
    }
    if (!dragMoved) return;
    const wrapRect = mapWrap.getBoundingClientRect();
    const x = e.clientX - wrapRect.left - dragOffsetX;
    const y = e.clientY - wrapRect.top - dragOffsetY;
    dragEl.style.left = x + 'px';
    dragEl.style.top = y + 'px';
  });
  window.addEventListener('mouseup', () => {
    if (currentTool === 'ruler') {
      if (rulerStart) { rulerStart = null; clearRulerSoon(); }
      return;
    }
    if (!dragEl) return;
    const el = dragEl;
    dragEl = null;
    if (dragMoved) {
      postJson('api/token_presun.php', {token_id: parseInt(el.dataset.id, 10), x: parseInt(el.style.left, 10), y: parseInt(el.style.top, 10)})
        .then(d => { if (d.error) logLine('Chyba přesunu: ' + d.error); });
    } else {
      otevritSpravovat(el);
    }
  });

  mapWrap.addEventListener('click', (e) => {
    if (currentTool === 'ping') {
      if (e.target.closest('.vtt-token')) return;
      const p = rulerPoint(e);
      const x = Math.round(p.x), y = Math.round(p.y);
      postJson('api/ping.php', {svet_id: SVET_ID, mapa_id: MAPA_ID, x, y})
        .then(d => {
          if (d.error) { logLine('Chyba: ' + d.error); return; }
          posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
          zobrazPing(x, y, d.jmeno || '');
        });
      return;
    }
    if (currentTool !== 'select') return;
    if (e.target.closest('.vtt-token')) return;
    const wrapRect = mapWrap.getBoundingClientRect();
    const x = Math.round(e.clientX - wrapRect.left - 20);
    const y = Math.round(e.clientY - wrapRect.top - 20);
    const postavaSel = document.getElementById('novaPostavaSelect');
    const nestvuraSel = document.getElementById('novaNestvuraSelect');
    if (postavaSel && postavaSel.value) {
      postJson('api/token_pridat.php', {mapa_id: MAPA_ID, typ_entity: 'postava', postava_id: parseInt(postavaSel.value, 10), x, y})
        .then(d => { if (d.error) { logLine('Chyba přidání: ' + d.error); return; } location.reload(); });
    } else if (nestvuraSel && nestvuraSel.value) {
      postJson('api/token_pridat.php', {mapa_id: MAPA_ID, typ_entity: 'nestvura', nestvura_id: parseInt(nestvuraSel.value, 10), x, y})
        .then(d => { if (d.error) { logLine('Chyba přidání: ' + d.error); return; } location.reload(); });
    }
  });
}

// --- Panel Spravovat: život, efekt, smazání ---
document.querySelectorAll('#panel-spravovat [data-delta]').forEach(btn => {
  btn.addEventListener('click', () => {
    if (!spravovanyToken) return;
    postJson('api/hp_uprava.php', {mapa_id: MAPA_ID, typ_entity: spravovanyToken.typEntity, entita_id: spravovanyToken.entitaId, delta: parseInt(btn.dataset.delta, 10)})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        const hpEl = document.querySelector('.hp[data-token-id="' + spravovanyToken.tokenId + '"]');
        if (hpEl) hpEl.textContent = d.nove_hp + (d.max_hp !== undefined ? '/' + d.max_hp : '');
        posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
      });
  });
});
const efektBtn = document.getElementById('efektBtn');
if (efektBtn) {
  efektBtn.addEventListener('click', () => {
    if (!spravovanyToken) return;
    const efektId = parseInt(document.getElementById('efektSelect').value, 10);
    const kola = document.getElementById('efektKola').value;
    postJson('api/efekt_pridat.php', {mapa_id: MAPA_ID, typ_entity: spravovanyToken.typEntity, entita_id: spravovanyToken.entitaId, efekt_id: efektId, zbyva_kol: kola})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } location.reload(); });
  });
}
document.querySelectorAll('.chip-efekt').forEach(chip => {
  chip.addEventListener('click', () => {
    if (!spravovanyToken) return;
    document.getElementById('efektSelect').value = chip.dataset.efektId;
    document.getElementById('efektKola').value = '3';
    document.getElementById('efektBtn').click();
  });
});

// --- Inventář spravované entity: "Použít" na každé položce ---
function klicEntity(typEntity, entitaId) { return typEntity + ':' + entitaId; }

function renderInventar() {
  const cont = document.getElementById('spravovatInventar');
  if (!cont || !spravovanyToken) return;
  const jeMrtvaNestvura = spravovanyToken.typEntity === 'nestvura_instance' && spravovanyToken.hp !== null && spravovanyToken.hp <= 0;
  if (jeMrtvaNestvura) { cont.innerHTML = ''; return; }
  const polozky = INVENTAR[klicEntity(spravovanyToken.typEntity, spravovanyToken.entitaId)] || [];
  if (!polozky.length) { cont.innerHTML = ''; return; }
  let html = '<h4 style="font-size:12px;color:#e8c9a8;margin:0 0 6px;">Inventář</h4>';
  polozky.forEach((p, i) => {
    const mnozstviText = p.typ_polozky === 'kouzlo' ? '' : (' ×' + p.mnozstvi);
    html += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:12.5px;">'
      + '<span style="flex:1;">' + p.nazev + mnozstviText + '</span>'
      + '<button class="btn btn-ghost" type="button" data-pouzit-index="' + i + '" style="font-size:11px;padding:3px 8px;">Použít</button>'
      + '</div>';
  });
  cont.innerHTML = html;
  cont.querySelectorAll('[data-pouzit-index]').forEach(btn => {
    btn.addEventListener('click', () => pouzitPolozku(polozky[parseInt(btn.dataset.pouzitIndex, 10)]));
  });
}

// Bez cíle = použito na sobě (typický "vypij lektvar"). Zadáním přesného
// data-label jiného tokenu na mapě jde mířit i jinam — prostý prompt(),
// ne picker, ať to zůstane malé.
function vyberCil(vychoziLabel) {
  const zadani = prompt('Cíl (prázdné = použít na "' + vychoziLabel + '"), napiš přesný název tokenu na mapě:', '');
  if (zadani === null) return undefined;
  if (zadani.trim() === '') return null;
  const cilEl = Array.from(document.querySelectorAll('.vtt-token')).find(t => t.dataset.label === zadani.trim());
  if (!cilEl) { alert('Token "' + zadani + '" na mapě nenašel.'); return undefined; }
  return {typEntity: cilEl.dataset.typEntity, entitaId: parseInt(cilEl.dataset.entitaId, 10)};
}

function pouzitPolozku(p) {
  if (!spravovanyToken) return;
  const cil = vyberCil(document.getElementById('spravovatNazev').textContent);
  if (cil === undefined) return;
  const body = {
    typ_entity: spravovanyToken.typEntity, entita_id: spravovanyToken.entitaId,
    typ_polozky: p.typ_polozky, polozka_id: p.polozka_id, mapa_id: MAPA_ID,
  };
  if (cil) { body.cil_typ_entity = cil.typEntity; body.cil_entita_id = cil.entitaId; }
  postJson('api/pouzij_predmet.php', body).then(d => {
    if (d.error) { logLine('Chyba: ' + d.error); return; }
    let zprava = document.getElementById('spravovatNazev').textContent + ' použil(a) ' + p.nazev;
    if (d.kostka) zprava += ': [' + d.kostka.hody.join(', ') + ']' + (d.kostka.bonus ? (d.kostka.bonus > 0 ? '+' : '') + d.kostka.bonus : '') + ' = ' + d.kostka.celkem;
    if (d.hp) zprava += ' (' + (d.hp.delta > 0 ? '+' : '') + d.hp.delta + ' život)';
    logLine(zprava);
    posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
    location.reload();
  });
}

// --- Kořist z mrtvé nestvůry: "Předat" na každé položce vybavy ---
function renderKorist() {
  const cont = document.getElementById('spravovatKorist');
  if (!cont || !spravovanyToken) return;
  const jeMrtvaNestvura = IS_PJ_OR_ADMIN && spravovanyToken.typEntity === 'nestvura_instance' && spravovanyToken.hp !== null && spravovanyToken.hp <= 0;
  if (!jeMrtvaNestvura) { cont.innerHTML = ''; return; }
  const polozky = INVENTAR[klicEntity('nestvura_instance', spravovanyToken.entitaId)] || [];
  let html = '<h4 style="font-size:12px;color:#e8c9a8;margin:0 0 6px;">Kořist</h4>';
  if (!polozky.length) {
    html += '<p class="note" style="margin:0;">Nemá u sebe nic.</p>';
  } else {
    html += '<select id="koristCilSelect" class="input" style="margin-bottom:6px;">'
      + SVET_POSTAVY.map(p => '<option value="' + p.id + '">' + p.nazev + '</option>').join('')
      + '</select>';
    polozky.forEach((p, i) => {
      const mnozstviText = p.typ_polozky === 'kouzlo' ? '' : (' ×' + p.mnozstvi);
      html += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:12.5px;">'
        + '<span style="flex:1;">' + p.nazev + mnozstviText + '</span>'
        + '<button class="btn btn-ghost" type="button" data-predat-index="' + i + '" style="font-size:11px;padding:3px 8px;">Předat</button>'
        + '</div>';
    });
  }
  cont.innerHTML = html;
  cont.querySelectorAll('[data-predat-index]').forEach(btn => {
    btn.addEventListener('click', () => {
      const p = polozky[parseInt(btn.dataset.predatIndex, 10)];
      const cilId = parseInt(document.getElementById('koristCilSelect').value, 10);
      postJson('api/loot.php', {
        nestvura_instance_id: spravovanyToken.entitaId,
        typ_polozky: p.typ_polozky, polozka_id: p.polozka_id,
        cilova_postava_id: cilId,
      }).then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        logLine('Předáno: ' + d.polozka_nazev + (d.mnozstvi > 1 ? ' ×' + d.mnozstvi : ''));
        posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
        location.reload();
      });
    });
  });
}
const smazatBtn = document.getElementById('smazatTokenBtn');
if (smazatBtn) {
  smazatBtn.addEventListener('click', () => {
    if (!spravovanyToken) return;
    if (!confirm('Opravdu smazat token?')) return;
    postJson('api/token_smazat.php', {token_id: spravovanyToken.tokenId})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } location.reload(); });
  });
}
const koloBtn = document.getElementById('tb-kolo');
if (koloBtn) {
  koloBtn.addEventListener('click', () => {
    postJson('api/kolo_konec.php', {mapa_id: MAPA_ID})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } location.reload(); });
  });
}
const iniciativaHoditBtn = document.getElementById('iniciativaHoditBtn');
if (iniciativaHoditBtn) {
  iniciativaHoditBtn.addEventListener('click', () => {
    if (!spravovanyToken) return;
    const modifikator = parseInt(document.getElementById('iniciativaModifikator').value, 10) || 0;
    postJson('api/iniciativa_hod.php', {mapa_id: MAPA_ID, token_id: spravovanyToken.tokenId, modifikator})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } location.reload(); });
  });
}
const tbDalsiTah = document.getElementById('tb-dalsi-tah');
if (tbDalsiTah) {
  tbDalsiTah.addEventListener('click', () => {
    postJson('api/dalsi_tah.php', {mapa_id: MAPA_ID})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } location.reload(); });
  });
}

// --- Kostky (dice-box vizualizace + autoritativní server výsledek) ---
let diceBox = null;
import('../assets/js/vendor/dice-box/dice-box.es.min.js').then((mod) => {
  diceBox = new mod.default('#diceBoxContainer', {assetPath: '../assets/js/vendor/dice-box/assets/'});
  diceBox.init().catch(() => { diceBox = null; });
}).catch(() => { diceBox = null; });

const hoditBtn = document.getElementById('hoditBtn');
if (hoditBtn) {
  hoditBtn.addEventListener('click', () => {
    const notace = document.getElementById('kostkyNotace').value.trim();
    if (!notace) return;
    postJson('api/kostka_hod.php', {svet_id: SVET_ID, mapa_id: MAPA_ID, notace})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        logLine(d.hodil + ' hodil ' + d.notace + ': [' + d.hody.join(', ') + ']' + (d.bonus ? (d.bonus > 0 ? '+' : '') + d.bonus : '') + ' = ' + d.celkem);
        posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
        if (diceBox) { try { diceBox.roll(notace.replace(/k/gi, 'd')); } catch (e) {} }
      });
  });
}

// --- Polling delt ---
function applyEvent(u) {
  if (u.typ === 'token_presun') {
    const el = mapWrap && mapWrap.querySelector('.vtt-token[data-id="' + u.payload.token_id + '"]');
    if (el) { el.style.left = u.payload.x_po + 'px'; el.style.top = u.payload.y_po + 'px'; }
  } else if (u.typ === 'kostka_hod') {
    logLine((u.payload.hodil || '?') + ' hodil ' + u.payload.notace + ': [' + u.payload.hody.join(', ') + ']'
      + (u.payload.bonus ? (u.payload.bonus > 0 ? '+' : '') + u.payload.bonus : '') + ' = ' + u.payload.celkem);
  } else if (u.typ === 'hp_zmena') {
    const el = mapWrap && mapWrap.querySelector('.vtt-token[data-typ-entity="' + u.payload.typ_entity + '"][data-entita-id="' + u.payload.entita_id + '"]');
    if (el) {
      const hpEl = el.querySelector('.hp');
      if (hpEl) hpEl.textContent = u.payload.nove_hp + (u.payload.max_hp ? '/' + u.payload.max_hp : '');
    }
  } else if (u.typ === 'ping') {
    if (u.mapa_id == MAPA_ID) zobrazPing(u.payload.x, u.payload.y, u.payload.jmeno || '');
  } else if (['token_pridan', 'token_smazan', 'efekt_aplikovan', 'efekt_konci', 'iniciativa_hozena', 'kolo_nove', 'tah_zmena', 'predmet_pouzit', 'predmet_loot'].includes(u.typ)) {
    location.reload();
  }
}

function poll() {
  fetch('api/udalosti.php?svet_id=' + SVET_ID + '&od=' + posledniUdalostId)
    .then(r => r.json())
    .then(d => {
      if (!d.udalosti) return;
      for (const u of d.udalosti) {
        posledniUdalostId = Math.max(posledniUdalostId, u.id);
        applyEvent(u);
      }
    })
    .catch(() => {});
}
setInterval(poll, 1500);
</script>
<?php endif; ?>
</body>
</html>
