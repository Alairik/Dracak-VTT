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
if ($isPjOrAdmin) {
    $nestvuryKatalog = dracak_db()->query('SELECT id, nazev FROM nestvury ORDER BY nazev')->fetchAll();
    $efektyKatalog = dracak_db()->query('SELECT id, nazev, typ FROM efekty ORDER BY nazev')->fetchAll();
}

$posledniUdalostId = (int)(dracak_db()->query('SELECT MAX(id) FROM svet_udalosti WHERE svet_id = ' . $svetId)->fetchColumn() ?: 0);

dracak_vtt_page_start($mapa['nazev'], $user);
?>
  <main class="main" style="padding:24px;">
    <p class="crumb"><a href="svet.php?id=<?= $svetId ?>">← <?= htmlspecialchars($svet['nazev']) ?></a></p>
    <h1 class="page-title"><?= htmlspecialchars($mapa['nazev']) ?> <span style="font-size:13px;font-weight:400;color:var(--color-neutral-500);">(<?= $mapa['typ_mapy'] === 'svet' ? 'světová mapa' : 'zóna' ?>)</span></h1>

    <?php if (!$mapa['obrazek_cesta']): ?>
      <div class="empty-state">Tahle mapa ještě nemá nahraný obrázek — přidej ho na stránce světa.</div>
    <?php else: ?>
      <div style="display:flex;gap:16px;margin-top:14px;flex-wrap:wrap;align-items:flex-start;">
        <div id="mapScroll" style="overflow:auto;max-width:100%;border:1px solid var(--color-neutral-300);border-radius:8px;">
          <div id="mapWrap" style="position:relative;display:inline-block;">
            <img id="mapImg" src="mapa_obrazek.php?id=<?= $mapaId ?>" style="display:block;max-width:none;">
            <?php foreach ($tokeny as $t):
                $entKey = $t['typ_entity'] . ':' . $t['entita_id'];
                $efektyText = implode(', ', $aktivniEfektyByEntity[$entKey] ?? []);
            ?>
              <div class="vtt-token" data-id="<?= (int)$t['id'] ?>" data-owned="<?= $t['owned'] ? 1 : 0 ?>"
                   data-typ-entity="<?= htmlspecialchars($t['typ_entity']) ?>" data-entita-id="<?= (int)$t['entita_id'] ?>"
                   title="<?= htmlspecialchars((string)$t['label']) ?>"
                   style="position:absolute;left:<?= (int)$t['x'] ?>px;top:<?= (int)$t['y'] ?>px;width:40px;text-align:center;cursor:<?= $t['owned'] ? 'grab' : 'default' ?>;user-select:none;z-index:<?= (int)$t['z_poradi'] ?>;">
                <div style="width:40px;height:40px;border-radius:50%;background:<?= $t['typ_entity'] === 'postava' ? 'var(--color-accent-600, #7a3b2e)' : '#4a2b2b' ?>;
                            color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.4);">
                  <?= htmlspecialchars(mb_substr((string)$t['label'], 0, 2)) ?>
                </div>
                <div class="vtt-hp" data-token-id="<?= (int)$t['id'] ?>" style="font-size:10px;background:rgba(0,0,0,.6);color:#fff;border-radius:4px;margin-top:2px;padding:1px 3px;">
                  <?= $t['hp'] !== null ? (int)$t['hp'] . '/' . (int)$t['max_hp'] : '—' ?>
                </div>
                <?php if ($efektyText): ?>
                  <div style="font-size:9px;background:rgba(0,0,0,.5);color:#fdd;border-radius:4px;margin-top:1px;padding:1px 3px;"><?= htmlspecialchars($efektyText) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div style="min-width:260px;flex:1;">
          <div class="card elev-sm">
            <h3 class="rel-label">Přidat token</h3>
            <?php if ($volnePostavy): ?>
              <label style="font-size:12px;font-weight:600;">Postava</label>
              <select class="input" id="novaPostavaSelect">
                <option value="">—</option>
                <?php foreach ($volnePostavy as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nazev']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
            <?php if ($isPjOrAdmin && $nestvuryKatalog): ?>
              <label style="font-size:12px;font-weight:600;margin-top:8px;display:block;">Nestvůra (bestiář)</label>
              <select class="input" id="novaNestvuraSelect">
                <option value="">—</option>
                <?php foreach ($nestvuryKatalog as $n): ?>
                  <option value="<?= (int)$n['id'] ?>"><?= htmlspecialchars($n['nazev']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
            <p class="note" style="margin-top:6px;">Vyber v jednom z výběrů a klikni na mapu, kam to položit.</p>
          </div>

          <div class="card elev-sm" style="margin-top:12px;">
            <h3 class="rel-label">Spravovat token</h3>
            <select class="input" id="spravovatSelect">
              <option value="">— vyber token —</option>
              <?php foreach ($tokeny as $t): if (!$t['owned'] && !$isPjOrAdmin) continue; ?>
                <option value="<?= htmlspecialchars($t['typ_entity']) ?>:<?= (int)$t['entita_id'] ?>:<?= (int)$t['id'] ?>">
                  <?= htmlspecialchars((string)$t['label']) ?> (<?= $t['typ_entity'] === 'postava' ? 'postava' : 'nestvůra' ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div style="display:flex;gap:6px;margin-top:8px;align-items:center;">
              <span style="font-size:12px;">Život:</span>
              <button class="btn btn-ghost" type="button" data-delta="-5">−5</button>
              <button class="btn btn-ghost" type="button" data-delta="-1">−1</button>
              <button class="btn btn-ghost" type="button" data-delta="1">+1</button>
              <button class="btn btn-ghost" type="button" data-delta="5">+5</button>
            </div>
            <?php if ($isPjOrAdmin && $efektyKatalog): ?>
              <label style="font-size:12px;font-weight:600;margin-top:10px;display:block;">Aplikovat efekt</label>
              <select class="input" id="efektSelect">
                <?php foreach ($efektyKatalog as $e): ?>
                  <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['nazev']) ?> (<?= htmlspecialchars($e['typ']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <div style="display:flex;gap:6px;margin-top:6px;">
                <input class="input" type="number" id="efektKola" placeholder="kol (prázdné = trvalé)" style="flex:1;">
                <button class="btn btn-secondary" type="button" id="efektBtn">Aplikovat</button>
              </div>
            <?php endif; ?>
            <button class="btn btn-ghost" type="button" id="smazatTokenBtn" style="margin-top:10px;">Smazat token</button>
          </div>

          <?php if ($isPjOrAdmin): ?>
          <div class="card elev-sm" style="margin-top:12px;">
            <button class="btn btn-primary" type="button" id="koloKonecBtn" style="width:100%;">Konec kola (odpočítat trvání efektů)</button>
          </div>
          <?php endif; ?>

          <div class="card elev-sm" style="margin-top:12px;">
            <h3 class="rel-label">Hodit kostkou</h3>
            <div style="display:flex;gap:8px;">
              <input class="input" type="text" id="kostkyNotace" placeholder="2k6+2" style="flex:1;">
              <button class="btn btn-primary" id="hoditBtn" type="button">Hodit</button>
            </div>
            <div id="diceBoxContainer" style="position:fixed;inset:0;pointer-events:none;z-index:9999;"></div>
          </div>

          <div class="card elev-sm" style="margin-top:12px;max-height:320px;overflow:auto;">
            <h3 class="rel-label">Log</h3>
            <div id="udalostiLog" style="font-size:12.5px;display:flex;flex-direction:column-reverse;gap:4px;"></div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>

<script type="module">
const SVET_ID = <?= $svetId ?>;
const MAPA_ID = <?= $mapaId ?>;
let posledniUdalostId = <?= $posledniUdalostId ?>;

const mapWrap = document.getElementById('mapWrap');
const log = document.getElementById('udalostiLog');

function logLine(text) {
  if (!log) return;
  const div = document.createElement('div');
  div.textContent = text;
  log.appendChild(div);
}

function postJson(url, body) {
  return fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)}).then(r => r.json());
}

// --- Drag tokenů ---
let dragEl = null, dragOffsetX = 0, dragOffsetY = 0;
if (mapWrap) {
  mapWrap.addEventListener('mousedown', (e) => {
    const el = e.target.closest('.vtt-token');
    if (!el || el.dataset.owned !== '1') return;
    dragEl = el;
    const rect = el.getBoundingClientRect();
    dragOffsetX = e.clientX - rect.left;
    dragOffsetY = e.clientY - rect.top;
  });
  window.addEventListener('mousemove', (e) => {
    if (!dragEl) return;
    const wrapRect = mapWrap.getBoundingClientRect();
    const x = e.clientX - wrapRect.left - dragOffsetX;
    const y = e.clientY - wrapRect.top - dragOffsetY;
    dragEl.style.left = x + 'px';
    dragEl.style.top = y + 'px';
  });
  window.addEventListener('mouseup', () => {
    if (!dragEl) return;
    const el = dragEl;
    dragEl = null;
    postJson('api/token_presun.php', {token_id: parseInt(el.dataset.id, 10), x: parseInt(el.style.left, 10), y: parseInt(el.style.top, 10)})
      .then(d => { if (d.error) logLine('Chyba přesunu: ' + d.error); });
  });

  mapWrap.addEventListener('click', (e) => {
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

// --- Správa tokenu: život, efekt, smazání ---
const spravovatSelect = document.getElementById('spravovatSelect');
function vybranyToken() {
  if (!spravovatSelect || !spravovatSelect.value) return null;
  const [typEntity, entitaId, tokenId] = spravovatSelect.value.split(':');
  return {typEntity, entitaId: parseInt(entitaId, 10), tokenId: parseInt(tokenId, 10)};
}
document.querySelectorAll('[data-delta]').forEach(btn => {
  btn.addEventListener('click', () => {
    const sel = vybranyToken();
    if (!sel) { logLine('Nejdřív vyber token ve "Spravovat token".'); return; }
    postJson('api/hp_uprava.php', {mapa_id: MAPA_ID, typ_entity: sel.typEntity, entita_id: sel.entitaId, delta: parseInt(btn.dataset.delta, 10)})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        const hpEl = document.querySelector('.vtt-hp[data-token-id="' + sel.tokenId + '"]');
        if (hpEl) hpEl.textContent = d.nove_hp + (d.max_hp !== undefined ? '/' + d.max_hp : '');
        posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
      });
  });
});
const efektBtn = document.getElementById('efektBtn');
if (efektBtn) {
  efektBtn.addEventListener('click', () => {
    const sel = vybranyToken();
    if (!sel) { logLine('Nejdřív vyber token ve "Spravovat token".'); return; }
    const efektId = parseInt(document.getElementById('efektSelect').value, 10);
    const kola = document.getElementById('efektKola').value;
    postJson('api/efekt_pridat.php', {mapa_id: MAPA_ID, typ_entity: sel.typEntity, entita_id: sel.entitaId, efekt_id: efektId, zbyva_kol: kola})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } location.reload(); });
  });
}
const smazatBtn = document.getElementById('smazatTokenBtn');
if (smazatBtn) {
  smazatBtn.addEventListener('click', () => {
    const sel = vybranyToken();
    if (!sel) { logLine('Nejdřív vyber token ve "Spravovat token".'); return; }
    if (!confirm('Opravdu smazat token?')) return;
    postJson('api/token_smazat.php', {token_id: sel.tokenId})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } location.reload(); });
  });
}
const koloKonecBtn = document.getElementById('koloKonecBtn');
if (koloKonecBtn) {
  koloKonecBtn.addEventListener('click', () => {
    postJson('api/kolo_konec.php', {mapa_id: MAPA_ID})
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
      const hpEl = el.querySelector('.vtt-hp');
      if (hpEl) hpEl.textContent = u.payload.nove_hp + (u.payload.max_hp ? '/' + u.payload.max_hp : '');
    }
  } else if (['token_pridan', 'token_smazan', 'efekt_aplikovan', 'efekt_konci'].includes(u.typ)) {
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
<?php dracak_vtt_page_end(); ?>
