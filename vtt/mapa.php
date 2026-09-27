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
            p.nazev AS postava_nazev, p.vlastnik_ucet_id
     FROM tokeny t
     LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
     WHERE t.mapa_id = ?'
);
$stmt->execute([$mapaId]);
$tokeny = $stmt->fetchAll();
if (!$isPjOrAdmin) {
    $tokeny = array_values(array_filter($tokeny, fn($t) => (bool)$t['viditelny_hracum']));
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
                $owned = $isPjOrAdmin || ((int)($t['vlastnik_ucet_id'] ?? 0) === (int)$user['id']);
                $label = $t['postava_nazev'] ?? ('#' . $t['entita_id']);
            ?>
              <div class="vtt-token" data-id="<?= (int)$t['id'] ?>" data-owned="<?= $owned ? 1 : 0 ?>"
                   title="<?= htmlspecialchars($label) ?>"
                   style="position:absolute;left:<?= (int)$t['x'] ?>px;top:<?= (int)$t['y'] ?>px;width:40px;height:40px;border-radius:50%;
                          background:var(--color-accent-600, #7a3b2e);color:#fff;display:flex;align-items:center;justify-content:center;
                          font-size:11px;font-weight:600;cursor:<?= $owned ? 'grab' : 'default' ?>;user-select:none;box-shadow:0 2px 6px rgba(0,0,0,.4);z-index:<?= (int)$t['z_poradi'] ?>;">
                <?= htmlspecialchars(mb_substr($label, 0, 2)) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div style="min-width:260px;flex:1;">
          <?php if ($volnePostavy): ?>
          <div class="card elev-sm">
            <h3 class="rel-label">Přidat token</h3>
            <select class="input" id="novaPostavaSelect">
              <?php foreach ($volnePostavy as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nazev']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="note" style="margin-top:6px;">Vyber postavu a klikni na mapu, kam ji položit.</p>
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
    el.style.cursor = 'grabbing';
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
    el.style.cursor = 'grab';
    fetch('api/token_presun.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({token_id: parseInt(el.dataset.id, 10), x: parseInt(el.style.left, 10), y: parseInt(el.style.top, 10)}),
    }).then(r => r.json()).then(d => { if (d.error) logLine('Chyba přesunu: ' + d.error); });
  });

  // --- Přidání tokenu kliknutím na mapu (mimo existující token) ---
  mapWrap.addEventListener('click', (e) => {
    if (e.target.closest('.vtt-token')) return;
    const select = document.getElementById('novaPostavaSelect');
    if (!select || !select.value) return;
    const wrapRect = mapWrap.getBoundingClientRect();
    const x = Math.round(e.clientX - wrapRect.left - 20);
    const y = Math.round(e.clientY - wrapRect.top - 20);
    fetch('api/token_pridat.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({mapa_id: MAPA_ID, postava_id: parseInt(select.value, 10), x, y}),
    }).then(r => r.json()).then(d => {
      if (d.error) { logLine('Chyba přidání: ' + d.error); return; }
      location.reload();
    });
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
    fetch('api/kostka_hod.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({svet_id: SVET_ID, mapa_id: MAPA_ID, notace}),
    }).then(r => r.json()).then(d => {
      if (d.error) { logLine('Chyba: ' + d.error); return; }
      logLine(d.hodil + ' hodil ' + d.notace + ': [' + d.hody.join(', ') + ']' + (d.bonus ? (d.bonus > 0 ? '+' : '') + d.bonus : '') + ' = ' + d.celkem);
      posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
      // 3D animace je čistě orientační vizualizace (vlastní fyzikální
      // náhoda knihovny) — autoritativní výsledek je ten v logu výše,
      // dice-box neumí vzít hotové číslo a jen ho "zahrát".
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
  } else if (u.typ === 'token_pridan' || u.typ === 'token_smazan') {
    // Nový/smazaný token vyžaduje přepočet volných postav v dropdownu — jednodušší je znovunačíst.
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
