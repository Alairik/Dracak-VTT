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
            p.aktualni_magenergie AS postava_magenergie, p.max_magenergie AS postava_max_magenergie,
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
    // Magenergie existuje jen na postavy (viz migrace
    // 0050_vtt_postava_magenergie.sql) — nestvura_instance sloupec vůbec
    // nemá, proto tu není druhá větev jako u HP. max_magenergie NULL/0 =
    // povolání magenergii nepoužívá, panel-spravovat řádek pak schová.
    $t['magenergie'] = $t['typ_entity'] === 'postava' ? $t['postava_magenergie'] : null;
    $t['max_magenergie'] = $t['typ_entity'] === 'postava' ? $t['postava_max_magenergie'] : null;
    $t['owned'] = $isPjOrAdmin || ((int)($t['vlastnik_ucet_id'] ?? 0) === (int)$user['id']);
}
unset($t);

// Zdi (LoS/pohyb) — skrytá (viditelna_hracum=0) smí vidět jen PJ/admin,
// stejný filtr jako u tokenů výš. udalosti.php stejnou podmínku vynucuje
// i pro realtime polling, ať skrytá zeď neprosákne hráčům ani tudy.
$stmt = dracak_db()->prepare(
    'SELECT id, x1, y1, x2, y2, sirka_px, blokuje_pohyb, blokuje_vystrel, viditelna_hracum FROM zdi WHERE mapa_id = ?'
);
$stmt->execute([$mapaId]);
$zdi = $stmt->fetchAll();
if (!$isPjOrAdmin) {
    $zdi = array_values(array_filter($zdi, fn($z) => (bool)$z['viditelna_hracum']));
}
foreach ($zdi as &$z) {
    $z['x1'] = (float)$z['x1'];
    $z['y1'] = (float)$z['y1'];
    $z['x2'] = (float)$z['x2'];
    $z['y2'] = (float)$z['y2'];
    $z['sirka_px'] = (float)$z['sirka_px'];
    $z['blokuje_pohyb'] = (bool)$z['blokuje_pohyb'];
    $z['blokuje_vystrel'] = (bool)$z['blokuje_vystrel'];
    $z['viditelna_hracum'] = (bool)$z['viditelna_hracum'];
}
unset($z);

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
$iniciativaBonusy = dracak_db()->query('SELECT id, popis, bonus FROM iniciativa_bonusy ORDER BY id')->fetchAll();

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
  .vtt-token.not-turn .puck { opacity: .6; box-shadow:0 0 0 2px rgba(255,255,255,.25) inset; }
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
      <img id="mapImg" src="mapa_obrazek.php?id=<?= $mapaId ?>" draggable="false" style="display:block;max-width:none;-webkit-user-drag:none;user-select:none;">
      <svg id="gridSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:0;"></svg>
      <svg id="zdiSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:5;"></svg>
      <svg id="rulerSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:140;"></svg>
      <?php if (!$isPjOrAdmin): ?>
      <canvas id="mlhaCanvas" style="position:absolute;top:0;left:0;pointer-events:none;z-index:100;"></canvas>
      <?php endif; ?>
      <?php foreach ($tokeny as $t):
          $entKey = $t['typ_entity'] . ':' . $t['entita_id'];
          $efektyText = implode(', ', $aktivniEfektyByEntity[$entKey] ?? []);
          // Pořadí tahů — čistě klientská indikace (server je autoritativní
          // nezávisle na tomhle, viz hra/api/token_presun.php/pouzij_predmet.php
          // a dracak_vtt_je_na_tahu v includes/vtt.php, stejná logika tady
          // zopakovaná jen pro vykreslení). Bez kolo_stav (boj neprobíhá) je
          // "na tahu" vždycky true — volno jako dnes.
          $naTahu = $isPjOrAdmin || !$koloStav || (int)($koloStav['aktivni_token_id'] ?? 0) === (int)$t['id'];
      ?>
        <div class="vtt-token<?= ($t['hp'] !== null && (int)$t['hp'] <= 0) ? ' dead' : '' ?><?= (!$naTahu && $t['owned']) ? ' not-turn' : '' ?>"
             data-id="<?= (int)$t['id'] ?>" data-owned="<?= $t['owned'] ? 1 : 0 ?>"
             data-na-tahu="<?= $naTahu ? 1 : 0 ?>"
             data-typ-entity="<?= htmlspecialchars($t['typ_entity']) ?>" data-entita-id="<?= (int)$t['entita_id'] ?>"
             data-label="<?= htmlspecialchars((string)$t['label']) ?>"
             data-hp="<?= $t['hp'] !== null ? (int)$t['hp'] : '' ?>"
             data-magenergie="<?= $t['magenergie'] !== null ? (int)$t['magenergie'] : '' ?>"
             data-max-magenergie="<?= ($t['max_magenergie'] !== null && (int)$t['max_magenergie'] > 0) ? (int)$t['max_magenergie'] : '' ?>"
             title="<?= htmlspecialchars((string)$t['label']) . (!$naTahu && $t['owned'] ? ' (není na tahu)' : '') ?>"
             style="left:<?= (int)$t['x'] ?>px;top:<?= (int)$t['y'] ?>px;cursor:<?= ($t['owned'] && $naTahu) ? 'grab' : ($t['owned'] ? 'not-allowed' : 'default') ?>;z-index:<?= (int)$t['z_poradi'] ?>;">
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
    <?php if ($isPjOrAdmin): ?>
      <button class="tbtn" type="button" id="tb-zed" title="Kreslit zeď"><?php dracak_icon('brick-wall'); ?></button>
    <?php endif; ?>
    <button class="tbtn" type="button" id="tb-kostky" title="Hodit kostkou"><?php dracak_icon('dices'); ?></button>
    <button class="tbtn" type="button" id="tb-log" title="Log"><?php dracak_icon('scroll-text'); ?></button>
    <?php if ($isPjOrAdmin): ?>
      <button class="tbtn" type="button" id="tb-grid" title="Nastavení gridu"><?php dracak_icon('grid-2x2'); ?></button>
      <button class="tbtn" type="button" id="tb-kolo" title="Konec kola (odpočítat trvání efektů)"><?php dracak_icon('skip-forward'); ?></button>
      <button class="tbtn" type="button" id="tb-dalsi-tah" title="Další na tahu (iniciativa)"><?php dracak_icon('skip-forward'); ?></button>
      <button class="tbtn" type="button" id="tb-krok-zpet" title="Krok zpět (vrátit poslední akci)"><?php dracak_icon('undo-2'); ?></button>
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

  <?php if ($isPjOrAdmin): ?>
  <div class="popover" id="popover-zed">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Zeď</h3>
    <label for="zedSirka">Šířka (<span id="zedSirkaJednotka">px</span>)</label>
    <input class="input" type="number" id="zedSirka" min="1" max="60" step="1" value="6">
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedBlokujePohyb" checked> Blokuje pohyb
    </label>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedBlokujeVystrel" checked> Blokuje výstřel/pohled
    </label>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedViditelnaHracum" checked> Viditelná hráčům
    </label>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedSnap" checked> Přichytávat ke gridu
    </label>
    <p class="note">Klikáním polož body zdi (start, libovolně moc mezibodů, konec), pak Uložit. Klikni na existující zeď pro výběr a smazání.</p>
    <div style="display:flex;gap:6px;margin-top:8px;">
      <button class="btn btn-primary" type="button" id="zedUlozitBtn" style="flex:1;display:none;">Uložit zeď</button>
      <button class="btn btn-ghost" type="button" id="zedZrusitBtn" style="flex:1;display:none;">Zrušit</button>
    </div>
    <button class="btn btn-ghost" type="button" id="zedSmazatBtn" style="width:100%;margin-top:8px;display:none;">Smazat vybranou zeď</button>
  </div>

  <div class="popover" id="popover-grid">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Nastavení gridu</h3>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="gridZapnuty"> Grid zapnutý
    </label>
    <div id="gridNastaveniFields">
      <label for="gridTypSelect">Typ</label>
      <select class="input" id="gridTypSelect">
        <option value="ctverec">Čtverec</option>
        <option value="hex">Hex</option>
      </select>
      <label for="gridVelikostSlider">Velikost 1 sáhu (<span id="gridVelikostHodnota">70</span> px)</label>
      <input type="range" id="gridVelikostSlider" min="20" max="200" step="1" value="70" style="width:100%;">
      <label for="gridPosunXInput">Posun X</label>
      <input class="input" type="number" id="gridPosunXInput" value="0">
      <label for="gridPosunYInput">Posun Y</label>
      <input class="input" type="number" id="gridPosunYInput" value="0">
    </div>
    <button class="btn btn-primary" type="button" id="gridUlozitBtn" style="width:100%;margin-top:10px;">Uložit</button>
    <p class="note">Náhled na mapě se mění hned, uloží se až tlačítkem.</p>
  </div>
  <?php endif; ?>

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
    <!-- Magenergie (content/pravidla-hrac.html h297 aj., viz migrace
         0050_vtt_postava_magenergie.sql) — jen postavy s nastaveným
         max_magenergie (povolání, co magenergii používá); schováno přes
         otevritSpravovat() JS, ne přes PHP, protože panel je společný
         pro všechny tokeny na mapě, ne per-token. -->
    <div id="panelMagenergieRow" style="display:none;margin-top:10px;">
      <label style="font-size:11px;font-weight:600;">Magenergie: <span id="spravovatMagenergieText">—</span></label>
      <div class="panel-hp-row">
        <button class="btn btn-ghost" type="button" data-mag-delta="-5">−5</button>
        <button class="btn btn-ghost" type="button" data-mag-delta="-1">−1</button>
        <button class="btn btn-ghost" type="button" data-mag-delta="1">+1</button>
        <button class="btn btn-ghost" type="button" data-mag-delta="5">+5</button>
      </div>
    </div>
    <div style="margin-top:10px;">
      <label style="font-size:11px;font-weight:600;">Iniciativa — bonusy/postihy (zaškrtni, co platí)</label>
      <div id="iniciativaBonusyList" style="max-height:120px;overflow-y:auto;font-size:12px;margin-bottom:6px;">
        <?php foreach ($iniciativaBonusy as $b): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-bottom:2px;">
            <input type="checkbox" class="iniciativa-bonus-check" value="<?= (int)$b['id'] ?>">
            <?= htmlspecialchars($b['popis']) ?> (<?= $b['bonus'] >= 0 ? '+' . (int)$b['bonus'] : (int)$b['bonus'] ?>)
          </label>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:6px;align-items:center;">
        <label style="font-size:11px;" for="iniciativaJinyBonus">jiný:</label>
        <input type="number" id="iniciativaJinyBonus" value="0" class="input" style="width:60px;">
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
// Grid konsty jsou "let", ne "const" — grid panel (viz níž) je mění živě
// při náhledu ještě před uložením, a po uložení/po eventu od jiného
// klienta se přepíšou na novou trvalou hodnotu.
let GRID_PX = <?= (int)($mapa['grid_velikost_px'] ?? 0) ?>;
let GRID_TYPE = <?= json_encode($mapa['grid_typ'] ?? 'ctverec') ?>;
let GRID_OFFSET_X = <?= (int)($mapa['grid_posun_x'] ?? 0) ?>;
let GRID_OFFSET_Y = <?= (int)($mapa['grid_posun_y'] ?? 0) ?>;
let gridSaved = {px: GRID_PX, typ: GRID_TYPE, offX: GRID_OFFSET_X, offY: GRID_OFFSET_Y};
const ZDI_INITIAL = <?= json_encode(array_values($zdi), JSON_UNESCAPED_UNICODE) ?>;
let zdiList = ZDI_INITIAL.slice();
let zedVybranaId = null;
let posledniUdalostId = <?= $posledniUdalostId ?>;
const INVENTAR = <?= json_encode($inventarByEntity, JSON_UNESCAPED_UNICODE) ?>;
const SVET_POSTAVY = <?= json_encode($svetPostavyKorist, JSON_UNESCAPED_UNICODE) ?>;
const IS_PJ_OR_ADMIN = <?= $isPjOrAdmin ? 'true' : 'false' ?>;

const mapWrap = document.getElementById('mapWrap');
const viewport = document.getElementById('viewport');
const logList = document.getElementById('logList');
const rulerSvg = document.getElementById('rulerSvg');
const mapImgEl = document.getElementById('mapImg');
const mlhaCanvas = document.getElementById('mlhaCanvas');

// --- Mlha války: canvas vrstva nad tokeny (z-index 100), jen pro
// hráče (PJ/admin vidí vždycky celou mapu, mlhaCanvas se pro ně vůbec
// nevykresluje, viz PHP výš) — proto všude dole stačí kontrola
// !mlhaCanvas, žádná zvlášť IS_PJ_OR_ADMIN větev. Server je jediný
// zdroj pravdy (hra/api/mlha_stav.php) — klient si bitmapu jen
// vykresluje, nepočítá si vlastní odhalování.
function renderMlha(stav) {
  if (!mlhaCanvas) return;
  const w = mapImgEl.naturalWidth || mapWrap.clientWidth;
  const h = mapImgEl.naturalHeight || mapWrap.clientHeight;
  if (!w || !h) return;
  mlhaCanvas.width = w;
  mlhaCanvas.height = h;
  const ctx = mlhaCanvas.getContext('2d');
  ctx.clearRect(0, 0, w, h);
  if (!stav || stav.zadna_mlha || !stav.sloupcu || !stav.radku) return;
  const bin = atob(stav.bitmapa_b64);
  const bunka = stav.bunka_px;
  ctx.fillStyle = 'rgba(8,8,10,0.92)';
  for (let row = 0; row < stav.radku; row++) {
    for (let col = 0; col < stav.sloupcu; col++) {
      if (bin.charCodeAt(row * stav.sloupcu + col) === 1) continue;
      ctx.fillRect(col * bunka, row * bunka, bunka, bunka);
    }
  }
}
function osvezitMlhu() {
  if (!mlhaCanvas) return;
  fetch('api/mlha_stav.php?mapa_id=' + MAPA_ID)
    .then(r => r.json())
    .then(renderMlha)
    .catch(() => {});
}

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
// --- Zdi: vykreslení (barva podle blokuje_pohyb/blokuje_vystrel, skrytá
// zeď jen v náhledu PJ jako přerušovaná a poloprůhledná — hráčům se sem
// vůbec nedostane, viz filtr v mapa.php i udalosti.php). ---
function renderZdi() {
  const svg = document.getElementById('zdiSvg');
  if (!svg) return;
  const w = mapImgEl.naturalWidth || mapWrap.clientWidth;
  const h = mapImgEl.naturalHeight || mapWrap.clientHeight;
  if (!w || !h) return;
  svg.setAttribute('width', w);
  svg.setAttribute('height', h);
  let html = '';
  zdiList.forEach(z => {
    let barva = '#a33b2e';
    if (z.blokuje_pohyb && !z.blokuje_vystrel) barva = '#3b7a3b';
    else if (!z.blokuje_pohyb && z.blokuje_vystrel) barva = '#3b5f8f';
    const vybrana = z.id === zedVybranaId;
    const sw = Math.max(2, z.sirka_px);
    html += '<line x1="' + z.x1 + '" y1="' + z.y1 + '" x2="' + z.x2 + '" y2="' + z.y2 + '"'
      + ' stroke="' + (vybrana ? '#ffd166' : barva) + '" stroke-width="' + sw + '" stroke-linecap="round"'
      + ' opacity="' + (z.viditelna_hracum ? 1 : 0.55) + '"'
      + (z.viditelna_hracum ? '' : ' stroke-dasharray="4 3"') + '/>';
  });
  svg.innerHTML = html;
}

// --- Magnetické body pro kreslení zdi: vrchol/střed hrany/střed buňky,
// čtverec i hex (viz renderGrid() výš pro stejné "odd-r offset" hex rozložení). ---
function squareSnapCandidates(px, py) {
  const g = GRID_PX;
  const offX = ((GRID_OFFSET_X % g) + g) % g;
  const offY = ((GRID_OFFSET_Y % g) + g) % g;
  const ci = Math.round((px - offX) / g);
  const cj = Math.round((py - offY) / g);
  const pts = [];
  for (let di = -1; di <= 1; di++) {
    for (let dj = -1; dj <= 1; dj++) {
      const x0 = offX + (ci + di) * g, y0 = offY + (cj + dj) * g;
      pts.push({x: x0, y: y0});
      pts.push({x: x0 + g / 2, y: y0 + g / 2});
      pts.push({x: x0 + g / 2, y: y0});
      pts.push({x: x0, y: y0 + g / 2});
    }
  }
  return pts;
}
function hexSnapCandidates(px, py) {
  const size = GRID_PX;
  const colStep = Math.sqrt(3) * size;
  const rowStep = 1.5 * size;
  const offX = ((GRID_OFFSET_X % colStep) + colStep) % colStep;
  const offY = ((GRID_OFFSET_Y % rowStep) + rowStep) % rowStep;
  const rowApprox = Math.round((py - offY) / rowStep);
  const pts = [];
  for (let row = rowApprox - 1; row <= rowApprox + 1; row++) {
    const rowShift = (Math.abs(row % 2) === 1) ? colStep / 2 : 0;
    const colApprox = Math.round((px - offX - rowShift) / colStep);
    for (let col = colApprox - 1; col <= colApprox + 1; col++) {
      const cx = offX + col * colStep + rowShift;
      const cy = offY + row * rowStep;
      pts.push({x: cx, y: cy});
      const verts = [];
      for (let i = 0; i < 6; i++) {
        const a = Math.PI / 180 * (60 * i - 30);
        verts.push({x: cx + size * Math.cos(a), y: cy + size * Math.sin(a)});
      }
      verts.forEach(v => pts.push(v));
      for (let i = 0; i < 6; i++) {
        const v1 = verts[i], v2 = verts[(i + 1) % 6];
        pts.push({x: (v1.x + v2.x) / 2, y: (v1.y + v2.y) / 2});
      }
    }
  }
  return pts;
}
function snapPoint(px, py, enabled) {
  if (!enabled || !GRID_PX || GRID_PX <= 0) return {x: px, y: py};
  // Bez poloměru — vždycky nejbližší magnetický bod, žádná "mrtvá zóna",
  // kde by klik propadl bez přichycení (stejný princip jako u tokenů v
  // gridCellCenter(), jen tu kandidáti jsou vrcholy/středy hran/středy
  // buněk místo jen středu buňky, viz squareSnapCandidates/hexSnapCandidates).
  const candidates = GRID_TYPE === 'hex' ? hexSnapCandidates(px, py) : squareSnapCandidates(px, py);
  let best = null, bestDist = Infinity;
  for (const c of candidates) {
    const d = Math.hypot(c.x - px, c.y - py);
    if (d < bestDist) { bestDist = d; best = c; }
  }
  return best || {x: px, y: py};
}

// --- Magnet pro tokeny: vždycky střed nejbližší buňky (ne volitelné
// 3 body jako u zdi) — token patří do buňky, ne kamkoliv na ni. Bez
// gridu žádný magnet není (stejný princip jako jinde — bez gridu
// nemáme měřítko ani buňky, na co by se to chytalo). ---
function gridCellCenter(px, py) {
  if (!GRID_PX || GRID_PX <= 0) return {x: px, y: py};
  if (GRID_TYPE === 'hex') {
    const size = GRID_PX;
    const colStep = Math.sqrt(3) * size;
    const rowStep = 1.5 * size;
    const offX = ((GRID_OFFSET_X % colStep) + colStep) % colStep;
    const offY = ((GRID_OFFSET_Y % rowStep) + rowStep) % rowStep;
    const rowApprox = Math.round((py - offY) / rowStep);
    let best = null, bestDist = Infinity;
    for (let row = rowApprox - 1; row <= rowApprox + 1; row++) {
      const rowShift = (Math.abs(row % 2) === 1) ? colStep / 2 : 0;
      const colApprox = Math.round((px - offX - rowShift) / colStep);
      for (let col = colApprox - 1; col <= colApprox + 1; col++) {
        const cx = offX + col * colStep + rowShift;
        const cy = offY + row * rowStep;
        const d = Math.hypot(cx - px, cy - py);
        if (d < bestDist) { bestDist = d; best = {x: cx, y: cy}; }
      }
    }
    return best;
  }
  const offX = ((GRID_OFFSET_X % GRID_PX) + GRID_PX) % GRID_PX;
  const offY = ((GRID_OFFSET_Y % GRID_PX) + GRID_PX) % GRID_PX;
  const i = Math.floor((px - offX) / GRID_PX);
  const j = Math.floor((py - offY) / GRID_PX);
  return {x: offX + i * GRID_PX + GRID_PX / 2, y: offY + j * GRID_PX + GRID_PX / 2};
}

// Px na 1 sáh. Čtverec: GRID_PX je přímo strana buňky = 1 sáh. Hex:
// GRID_PX je "size" (circumradius, střed->vrchol, viz renderGrid()
// výš), ale podle pravidel (content/pravidla-hrac.html h1621/b10798:
// "jeden hex vždy odpovídá jednomu sáhu") je 1 sáh vzdálenost
// STŘED-STŘED sousedních hexů, ne circumradius samotný — to je
// colStep = √3 × size (shodné se vzdáleností všech 6 sousedů v
// "odd-r" rozložení, viz hexSnapCandidates/renderGrid).
function pxNaSah() {
  if (!GRID_PX || GRID_PX <= 0) return 0;
  return GRID_TYPE === 'hex' ? Math.sqrt(3) * GRID_PX : GRID_PX;
}

// --- Vzdálenost bodu od úsečky — pro výběr existující zdi klikem. ---
function vzdalenostKUsecce(px, py, x1, y1, x2, y2) {
  const dx = x2 - x1, dy = y2 - y1;
  const lenSq = dx * dx + dy * dy;
  let t = lenSq > 0 ? ((px - x1) * dx + (py - y1) * dy) / lenSq : 0;
  t = Math.max(0, Math.min(1, t));
  const cx = x1 + t * dx, cy = y1 + t * dy;
  return Math.hypot(px - cx, py - cy);
}
function najdiZedBlizkoBodu(px, py) {
  let nejblizsi = null, nejmensiVzdalenost = Infinity;
  zdiList.forEach(z => {
    const prah = Math.max(6, z.sirka_px / 2 + 4);
    const d = vzdalenostKUsecce(px, py, z.x1, z.y1, z.x2, z.y2);
    if (d <= prah && d < nejmensiVzdalenost) { nejmensiVzdalenost = d; nejblizsi = z; }
  });
  return nejblizsi;
}

// 1 sáh = 1 buňka gridu (stejné jako ruler) — šířka zdi se zadává v
// sáhách, když je grid aktivní, jinak jako syrové px (bez gridu nemáme
// měřítko, viz drawRuler()).
function zedSirkaPx() {
  const input = document.getElementById('zedSirka');
  if (!input) return 6;
  const val = parseFloat(input.value) || 0;
  const pxSah = pxNaSah();
  return pxSah > 0 ? Math.max(1, val * pxSah) : Math.max(1, val);
}
function zedSnapEnabled() {
  const chk = document.getElementById('zedSnap');
  return !!(chk && chk.checked);
}
function aktualizovatZedJednotky() {
  const input = document.getElementById('zedSirka');
  const label = document.getElementById('zedSirkaJednotka');
  if (!input) return;
  if (GRID_PX > 0) {
    input.min = '0.1'; input.max = '2'; input.step = '0.1'; input.value = '0.2';
    if (label) label.textContent = 'sáhy';
  } else {
    input.min = '1'; input.max = '60'; input.step = '1'; input.value = '6';
    if (label) label.textContent = 'px';
  }
}
// Rozpracovaná zeď = klikem posbírané body (start, libovolně mezibodů,
// konec) — NEukládá se po jednom, čeká na výslovné "Uložit" (viz
// zedUlozitBtn). Vykresluje se do rulerSvg (sdílená ephemerní vrstva,
// stejně jako ruler/drag-preview — zed a ruler nástroj nejdou použít
// zároveň, takže není kolize).
function drawZedRozpracovanouCestu(aktualniBod) {
  if (!rulerSvg) return;
  const sw = Math.max(2, zedSirkaPx());
  let html = '';
  for (let i = 0; i < zedRozpracovaneBody.length - 1; i++) {
    const a = zedRozpracovaneBody[i], b = zedRozpracovaneBody[i + 1];
    html += '<line x1="' + a.x + '" y1="' + a.y + '" x2="' + b.x + '" y2="' + b.y + '"'
      + ' stroke="#ffd166" stroke-width="' + sw + '" stroke-linecap="round" opacity="0.8"/>';
  }
  const posledni = zedRozpracovaneBody[zedRozpracovaneBody.length - 1];
  if (posledni && aktualniBod) {
    html += '<line x1="' + posledni.x + '" y1="' + posledni.y + '" x2="' + aktualniBod.x + '" y2="' + aktualniBod.y + '"'
      + ' stroke="#ffd166" stroke-width="' + sw + '" stroke-linecap="round" stroke-dasharray="6 4" opacity="0.5"/>';
  }
  zedRozpracovaneBody.forEach(b => { html += '<circle cx="' + b.x + '" cy="' + b.y + '" r="4" fill="#ffd166"/>'; });
  rulerSvg.innerHTML = html;
}
function aktualizovatZedUlozitTlacitko() {
  const btnUlozit = document.getElementById('zedUlozitBtn');
  const btnZrusit = document.getElementById('zedZrusitBtn');
  const zobrazit = zedRozpracovaneBody.length >= 2;
  if (btnUlozit) btnUlozit.style.display = zobrazit ? '' : 'none';
  if (btnZrusit) btnZrusit.style.display = zedRozpracovaneBody.length > 0 ? '' : 'none';
}
function zrusitZedRozpracovanouCestu() {
  zedRozpracovaneBody = [];
  aktualizovatZedUlozitTlacitko();
  if (rulerSvg) rulerSvg.innerHTML = '';
}

if (mapImgEl.complete) { renderGrid(); renderZdi(); osvezitMlhu(); } else mapImgEl.addEventListener('load', () => { renderGrid(); renderZdi(); osvezitMlhu(); });

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
function closeAllPopovers() {
  zrusitZivyNahledGridu();
  document.querySelectorAll('.popover.open').forEach(p => p.classList.remove('open'));
  document.querySelectorAll('.tbtn.active').forEach(b => b.classList.remove('active'));
}
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

// --- Nástroje toolbaru: select/move (výchozí), ruler (měření), ping, zeď ---
let currentTool = 'select';
const toolButtons = {
  select: document.getElementById('tb-move'),
  ruler: document.getElementById('tb-ruler'),
  ping: document.getElementById('tb-ping'),
  zed: document.getElementById('tb-zed'),
};
function setTool(tool) {
  currentTool = tool;
  for (const [t, btn] of Object.entries(toolButtons)) {
    if (btn) btn.classList.toggle('armed', t === tool);
  }
  rulerStart = null;
  if (rulerSvg) rulerSvg.innerHTML = '';
  zedRozpracovaneBody = [];
  aktualizovatZedUlozitTlacitko();
  const popZed = document.getElementById('popover-zed');
  if (popZed) {
    if (tool === 'zed') {
      popZed.style.top = toolButtons.zed.getBoundingClientRect().top + 'px';
      popZed.classList.add('open');
      toolButtons.zed.classList.add('active');
      aktualizovatZedJednotky();
    } else {
      popZed.classList.remove('open');
      toolButtons.zed.classList.remove('active');
      zedVybranaId = null;
      const btnSmazat = document.getElementById('zedSmazatBtn');
      if (btnSmazat) btnSmazat.style.display = 'none';
      renderZdi();
    }
  }
}
if (toolButtons.select) toolButtons.select.addEventListener('click', () => setTool('select'));
if (toolButtons.ruler) toolButtons.ruler.addEventListener('click', () => setTool(currentTool === 'ruler' ? 'select' : 'ruler'));
if (toolButtons.ping) toolButtons.ping.addEventListener('click', () => setTool(currentTool === 'ping' ? 'select' : 'ping'));
if (toolButtons.zed) toolButtons.zed.addEventListener('click', () => setTool(currentTool === 'zed' ? 'select' : 'zed'));

// --- Grid panel: živý náhled před uložením (viz zrusitZivyNahledGridu
// volané z closeAllPopovers — zavření bez uložení náhled vrátí zpět). ---
const gridZapnutyChk = document.getElementById('gridZapnuty');
const gridTypSelect = document.getElementById('gridTypSelect');
const gridVelikostSlider = document.getElementById('gridVelikostSlider');
const gridVelikostHodnota = document.getElementById('gridVelikostHodnota');
const gridPosunXInput = document.getElementById('gridPosunXInput');
const gridPosunYInput = document.getElementById('gridPosunYInput');
const gridNastaveniFields = document.getElementById('gridNastaveniFields');

function zrusitZivyNahledGridu() {
  if (!gridZapnutyChk) return;
  GRID_PX = gridSaved.px; GRID_TYPE = gridSaved.typ; GRID_OFFSET_X = gridSaved.offX; GRID_OFFSET_Y = gridSaved.offY;
  renderGrid();
}
// Slider nastavuje GRID_PX (u hexu circumradius, viz pxNaSah() výš),
// ne přímo "1 sáh v px" — u hexu proto popisek u slideru dopočítá
// skutečnou velikost sáhu (√3×), ať PJ neměří grid podle čísla, co ve
// skutečnosti znamená něco jiného.
function aktualizovatGridVelikostHodnotu() {
  const raw = parseInt(gridVelikostSlider.value, 10) || 0;
  const sah = gridTypSelect.value === 'hex' ? Math.round(raw * Math.sqrt(3)) : raw;
  gridVelikostHodnota.textContent = sah;
}
function nacistGridFormular() {
  if (!gridZapnutyChk) return;
  gridZapnutyChk.checked = gridSaved.px > 0;
  gridTypSelect.value = gridSaved.typ;
  gridVelikostSlider.value = gridSaved.px > 0 ? gridSaved.px : 70;
  aktualizovatGridVelikostHodnotu();
  gridPosunXInput.value = gridSaved.offX;
  gridPosunYInput.value = gridSaved.offY;
  gridNastaveniFields.style.display = gridZapnutyChk.checked ? '' : 'none';
}
function zivyNahledGridu() {
  GRID_PX = gridZapnutyChk.checked ? parseInt(gridVelikostSlider.value, 10) : 0;
  GRID_TYPE = gridTypSelect.value;
  GRID_OFFSET_X = parseInt(gridPosunXInput.value, 10) || 0;
  GRID_OFFSET_Y = parseInt(gridPosunYInput.value, 10) || 0;
  renderGrid();
}
if (gridZapnutyChk) {
  gridZapnutyChk.addEventListener('change', () => { gridNastaveniFields.style.display = gridZapnutyChk.checked ? '' : 'none'; zivyNahledGridu(); });
  gridTypSelect.addEventListener('change', () => { aktualizovatGridVelikostHodnotu(); zivyNahledGridu(); });
  gridVelikostSlider.addEventListener('input', () => { aktualizovatGridVelikostHodnotu(); zivyNahledGridu(); });
  gridPosunXInput.addEventListener('input', zivyNahledGridu);
  gridPosunYInput.addEventListener('input', zivyNahledGridu);
}
const tbGrid = document.getElementById('tb-grid');
if (tbGrid) tbGrid.addEventListener('click', () => { nacistGridFormular(); togglePopover('popover-grid', tbGrid); });
const gridUlozitBtn = document.getElementById('gridUlozitBtn');
if (gridUlozitBtn) {
  gridUlozitBtn.addEventListener('click', () => {
    const novy = {
      px: gridZapnutyChk.checked ? parseInt(gridVelikostSlider.value, 10) : 0,
      typ: gridTypSelect.value,
      offX: parseInt(gridPosunXInput.value, 10) || 0,
      offY: parseInt(gridPosunYInput.value, 10) || 0,
    };
    postJson('api/mapa_grid_uprava.php', {mapa_id: MAPA_ID, grid_velikost_px: novy.px, grid_typ: novy.typ, grid_posun_x: novy.offX, grid_posun_y: novy.offY})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        gridSaved = novy;
        GRID_PX = novy.px; GRID_TYPE = novy.typ; GRID_OFFSET_X = novy.offX; GRID_OFFSET_Y = novy.offY;
        aktualizovatZedJednotky();
        closeAllPopovers();
        logLine('Nastavení gridu uloženo.');
      });
  });
}

// --- Ruler: čistě klientská pomůcka, nic se neukládá ani nesynchronizuje ---
let rulerStart = null, rulerClearTimer = null;
let zedRozpracovaneBody = [];
function rulerPoint(e) {
  const r = mapWrap.getBoundingClientRect();
  return {x: e.clientX - r.left, y: e.clientY - r.top};
}
function drawRuler(a, b) {
  if (!rulerSvg) return;
  clearTimeout(rulerClearTimer);
  const dx = b.x - a.x, dy = b.y - a.y;
  const distPx = Math.sqrt(dx * dx + dy * dy);
  // 1 sáh = pxNaSah() (čtverec: 1 buňka; hex: střed-střed sousedních
  // hexů, viz h1621 "jeden hex vždy odpovídá jednomu sáhu") — bez gridu
  // nemáme měřítko, takže zůstává nepřevedené px jako jediná poctivá možnost.
  const pxSah = pxNaSah();
  const label = pxSah > 0 ? (distPx / pxSah).toFixed(1) + ' sáhů' : Math.round(distPx) + ' px';
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
    magenergie: el.dataset.magenergie === '' ? null : parseInt(el.dataset.magenergie, 10),
    maxMagenergie: el.dataset.maxMagenergie === '' ? null : parseInt(el.dataset.maxMagenergie, 10),
  };
  document.getElementById('spravovatNazev').textContent = el.dataset.label;
  const magRow = document.getElementById('panelMagenergieRow');
  if (magRow) {
    if (spravovanyToken.maxMagenergie !== null) {
      magRow.style.display = '';
      document.getElementById('spravovatMagenergieText').textContent = (spravovanyToken.magenergie ?? 0) + '/' + spravovanyToken.maxMagenergie;
    } else {
      magRow.style.display = 'none';
    }
  }
  renderInventar();
  renderKorist();
  spravovatPanel.classList.add('open');
}

if (mapWrap) {
  mapWrap.addEventListener('mousedown', (e) => {
    if (currentTool === 'ruler') {
      // Měřítko musí jít začít i kliknutím přímo na token (časté měření
      // "dosáhne token A na token B") — ruler nijak nekoliduje s
      // táhnutím tokenu, to běží jen v currentTool==='select' větvi níž.
      rulerStart = rulerPoint(e);
      drawRuler(rulerStart, rulerStart);
      return;
    }
    if (currentTool === 'zed') return; // zed nekreslí tažením, viz mapWrap 'click' níž
    if (currentTool !== 'select') return;
    const el = e.target.closest('.vtt-token');
    // data-na-tahu je jen klientská pomůcka (viz PHP výš, $naTahu) —
    // server (hra/api/token_presun.php) je autoritativní nezávisle na
    // týhle kontrole, tahle jen vizuálně zamezí drag, ať to hráč nezkouší
    // nadarmo a nedostane zbytečnou chybu z backendu.
    if (!el || el.dataset.owned !== '1' || el.dataset.naTahu === '0') return;
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
    if (currentTool === 'zed') {
      if (zedRozpracovaneBody.length === 0) return;
      const p = rulerPoint(e);
      drawZedRozpracovanouCestu(snapPoint(p.x, p.y, zedSnapEnabled()));
      return;
    }
    if (!dragEl) return;
    if (!dragMoved && (Math.abs(e.clientX - dragStartClientX) > 5 || Math.abs(e.clientY - dragStartClientY) > 5)) {
      dragMoved = true;
    }
    if (!dragMoved) return;
    const wrapRect = mapWrap.getBoundingClientRect();
    if (GRID_PX > 0) {
      // S gridem token vždycky sedí na střed buňky — grab point uvnitř
      // pucku (dragOffsetX/Y) se tu záměrně ignoruje, token je diskrétní
      // obyvatel buňky, ne volně tažený bod.
      const c = gridCellCenter(e.clientX - wrapRect.left, e.clientY - wrapRect.top);
      dragEl.style.left = Math.round(c.x - 20) + 'px';
      dragEl.style.top = Math.round(c.y - 20) + 'px';
    } else {
      const x = e.clientX - wrapRect.left - dragOffsetX;
      const y = e.clientY - wrapRect.top - dragOffsetY;
      dragEl.style.left = x + 'px';
      dragEl.style.top = y + 'px';
    }
  });
  window.addEventListener('mouseup', (e) => {
    if (currentTool === 'ruler') {
      if (rulerStart) { rulerStart = null; clearRulerSoon(); }
      return;
    }
    if (currentTool === 'zed') return; // zed se zapisuje klikem (viz mapWrap 'click') a ukládá tlačítkem, ne mouseup
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
    if (currentTool === 'zed') {
      if (e.target.closest('.vtt-token')) return;
      const p = rulerPoint(e);
      // Výběr existující zdi jen když zrovna nerozkreslujeme novou cestu —
      // uprostřed klikání bodů se klik vždycky bere jako další bod, ať
      // omylem nevybereš/nesmažeš jinou zeď, co ti leží v cestě.
      if (zedRozpracovaneBody.length === 0) {
        const blizkaZed = najdiZedBlizkoBodu(p.x, p.y);
        const btnSmazat = document.getElementById('zedSmazatBtn');
        if (blizkaZed) {
          zedVybranaId = blizkaZed.id;
          if (btnSmazat) btnSmazat.style.display = 'block';
          renderZdi();
          return;
        }
        zedVybranaId = null;
        if (btnSmazat) btnSmazat.style.display = 'none';
        renderZdi();
      }
      const bod = snapPoint(p.x, p.y, zedSnapEnabled());
      zedRozpracovaneBody.push(bod);
      aktualizovatZedUlozitTlacitko();
      drawZedRozpracovanouCestu(bod);
      return;
    }
    if (currentTool !== 'select') return;
    if (e.target.closest('.vtt-token')) return;
    const wrapRect = mapWrap.getBoundingClientRect();
    const dropPoint = gridCellCenter(e.clientX - wrapRect.left, e.clientY - wrapRect.top);
    const x = Math.round(dropPoint.x - 20);
    const y = Math.round(dropPoint.y - 20);
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
document.querySelectorAll('#panel-spravovat [data-mag-delta]').forEach(btn => {
  btn.addEventListener('click', () => {
    if (!spravovanyToken || spravovanyToken.maxMagenergie === null) return;
    postJson('api/magenergie_uprava.php', {mapa_id: MAPA_ID, entita_id: spravovanyToken.entitaId, delta: parseInt(btn.dataset.magDelta, 10)})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        spravovanyToken.magenergie = d.nova_magenergie;
        const txt = document.getElementById('spravovatMagenergieText');
        if (txt) txt.textContent = d.nova_magenergie + '/' + d.max_magenergie;
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
    if (d.magenergie) zprava += ' (-' + d.magenergie.cena + ' magenergie, zbývá ' + d.magenergie.nova_magenergie + '/' + d.magenergie.max_magenergie + ')';
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
const zedSmazatBtn = document.getElementById('zedSmazatBtn');
if (zedSmazatBtn) {
  zedSmazatBtn.addEventListener('click', () => {
    if (zedVybranaId === null) return;
    postJson('api/zed_smazat.php', {id: zedVybranaId}).then(d => {
      if (d.error) { logLine('Chyba: ' + d.error); return; }
      zdiList = zdiList.filter(z => z.id !== zedVybranaId);
      zedVybranaId = null;
      zedSmazatBtn.style.display = 'none';
      renderZdi();
    });
  });
}
const zedZrusitBtn = document.getElementById('zedZrusitBtn');
if (zedZrusitBtn) zedZrusitBtn.addEventListener('click', zrusitZedRozpracovanouCestu);
const zedUlozitBtn = document.getElementById('zedUlozitBtn');
if (zedUlozitBtn) {
  zedUlozitBtn.addEventListener('click', () => {
    if (zedRozpracovaneBody.length < 2) return;
    const sirkaPx = zedSirkaPx();
    const blokujePohyb = document.getElementById('zedBlokujePohyb').checked;
    const blokujeVystrel = document.getElementById('zedBlokujeVystrel').checked;
    const viditelnaHracum = document.getElementById('zedViditelnaHracum').checked;
    const useky = [];
    for (let i = 0; i < zedRozpracovaneBody.length - 1; i++) {
      useky.push([zedRozpracovaneBody[i], zedRozpracovaneBody[i + 1]]);
    }
    // Ukládá se úsek po úseku sekvenčně (ne najednou) — ať chyba uprostřed
    // lomené zdi nezanechá nekonzistentní stav a zdiList/render odpovídá
    // přesně tomu, co se fakt uložilo na server.
    function ulozUsek(i) {
      if (i >= useky.length) {
        zedRozpracovaneBody = [];
        aktualizovatZedUlozitTlacitko();
        if (rulerSvg) rulerSvg.innerHTML = '';
        return;
      }
      const [a, b] = useky[i];
      postJson('api/zed_pridat.php', {
        mapa_id: MAPA_ID, x1: a.x, y1: a.y, x2: b.x, y2: b.y,
        sirka_px: sirkaPx, blokuje_pohyb: blokujePohyb, blokuje_vystrel: blokujeVystrel, viditelna_hracum: viditelnaHracum,
      }).then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        zdiList.push({
          id: d.id, x1: a.x, y1: a.y, x2: b.x, y2: b.y, sirka_px: sirkaPx,
          blokuje_pohyb: blokujePohyb, blokuje_vystrel: blokujeVystrel, viditelna_hracum: viditelnaHracum,
        });
        posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
        renderZdi();
        ulozUsek(i + 1);
      });
    }
    ulozUsek(0);
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
    const bonusIds = Array.from(document.querySelectorAll('.iniciativa-bonus-check:checked')).map(c => parseInt(c.value, 10));
    const jinyBonus = parseInt(document.getElementById('iniciativaJinyBonus').value, 10) || 0;
    postJson('api/iniciativa_hod.php', {mapa_id: MAPA_ID, token_id: spravovanyToken.tokenId, bonus_ids: bonusIds, jiny_bonus: jinyBonus})
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
const tbKrokZpet = document.getElementById('tb-krok-zpet');
if (tbKrokZpet) {
  tbKrokZpet.addEventListener('click', () => {
    postJson('api/krok_zpet.php', {mapa_id: MAPA_ID})
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
    osvezitMlhu();
  } else if (u.typ === 'kostka_hod') {
    logLine((u.payload.hodil || '?') + ' hodil ' + u.payload.notace + ': [' + u.payload.hody.join(', ') + ']'
      + (u.payload.bonus ? (u.payload.bonus > 0 ? '+' : '') + u.payload.bonus : '') + ' = ' + u.payload.celkem);
  } else if (u.typ === 'hp_zmena') {
    const el = mapWrap && mapWrap.querySelector('.vtt-token[data-typ-entity="' + u.payload.typ_entity + '"][data-entita-id="' + u.payload.entita_id + '"]');
    if (el) {
      const hpEl = el.querySelector('.hp');
      if (hpEl) hpEl.textContent = u.payload.nove_hp + (u.payload.max_hp ? '/' + u.payload.max_hp : '');
    }
  } else if (u.typ === 'magenergie_zmena') {
    // Magenergie nemá na tokenu vlastní badge (jen v panel-spravovat),
    // proto se tu aktualizuje jen otevřený panel, ne hledání elementu na mapě.
    if (spravovanyToken && spravovanyToken.typEntity === 'postava' && spravovanyToken.entitaId === u.payload.entita_id) {
      spravovanyToken.magenergie = u.payload.nova_magenergie;
      const txt = document.getElementById('spravovatMagenergieText');
      if (txt) txt.textContent = u.payload.nova_magenergie + '/' + u.payload.max_magenergie;
    }
  } else if (u.typ === 'ping') {
    if (u.mapa_id == MAPA_ID) zobrazPing(u.payload.x, u.payload.y, u.payload.jmeno || '');
  } else if (u.typ === 'zed_pridana') {
    if (u.mapa_id == MAPA_ID && !zdiList.some(z => z.id === u.payload.id)) {
      zdiList.push(u.payload);
      renderZdi();
    }
  } else if (u.typ === 'zed_smazana') {
    if (u.mapa_id == MAPA_ID) {
      zdiList = zdiList.filter(z => z.id !== u.payload.id);
      if (zedVybranaId === u.payload.id) zedVybranaId = null;
      renderZdi();
    }
  } else if (u.typ === 'mapa_grid_zmena') {
    if (u.mapa_id == MAPA_ID) {
      gridSaved = {px: u.payload.grid_velikost_px || 0, typ: u.payload.grid_typ, offX: u.payload.grid_posun_x, offY: u.payload.grid_posun_y};
      GRID_PX = gridSaved.px; GRID_TYPE = gridSaved.typ; GRID_OFFSET_X = gridSaved.offX; GRID_OFFSET_Y = gridSaved.offY;
      renderGrid();
      aktualizovatZedJednotky();
    }
  } else if (['token_pridan', 'token_smazan', 'efekt_aplikovan', 'efekt_konci', 'iniciativa_hozena', 'kolo_nove', 'tah_zmena', 'predmet_pouzit', 'predmet_loot', 'krok_zpet'].includes(u.typ)) {
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
