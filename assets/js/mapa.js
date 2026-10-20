// Mapa (hra/mapa.php): kamera, tokeny, nástroje, synchronizace.
// Konfigurace a počáteční data předává PHP v window.MAPA — samotný skript
// je statický soubor, aby se dal cachovat a upravovat bez PHP.

// Moduly se načítají s číslem verze (čas změny souboru z PHP), aby je prohlížeč
// po nasazení nové verze nebral ze staré mezipaměti.
const VERZE_MODULU = window.MAPA.verzeModulu || '0';
const {hodit3D, nactiKostky, kostky3DPripraveny} = await import('./kostky3d.js?v=' + VERZE_MODULU);
const hexy = await import('./hexy.js?v=' + VERZE_MODULU);
const initSouboj = window.MAPA.funkce.souboj ? (await import('./souboj.js?v=' + VERZE_MODULU)).initSouboj : null;
const initDenikDock = (window.MAPA.denikPostavy || []).length ? (await import('./denik_dock.js?v=' + VERZE_MODULU)).initDenikDock : null;

// --- Kamera: zoom a posun přes CSS transform na #mapWrap ---
// Souřadnice mapy (pixely původního obrázku) se na obrazovku převádějí jen
// tady — zbytek aplikace pracuje výhradně v souřadnicích mapy a pro vstup
// z myši/prstu volá kamera.naMapu(). Grid, zdi, mlha i tokeny se tak počítají
// v jedné soustavě bez ohledu na zoom.

const ZOOM_MIN = 0.05, ZOOM_MAX = 4;
function vytvorKameru(viewport, svet) {
  const MIN = ZOOM_MIN;
  const MAX = ZOOM_MAX;
  const stav = { x: 0, y: 0, z: 1 };
  const posluchaci = [];
  let sirka = 0, vyska = 0; // rozměry mapy v px (naturalWidth/Height)

  function pouzit() {
    svet.style.transform = 'translate(' + stav.x.toFixed(2) + 'px,' + stav.y.toFixed(2) + 'px) scale(' + stav.z.toFixed(5) + ')';
    posluchaci.forEach((fn) => fn(stav));
  }

  function omezit() {
    // Mapa nesmí "odplout" úplně pryč: aspoň čtvrtina viewportu musí zůstat
    // pokryta mapou na každé ose.
    const r = viewport.getBoundingClientRect();
    const w = sirka * stav.z, h = vyska * stav.z;
    const okrajX = Math.min(r.width * 0.25, w * 0.5), okrajY = Math.min(r.height * 0.25, h * 0.5);
    stav.x = Math.min(r.width - okrajX, Math.max(okrajX - w, stav.x));
    stav.y = Math.min(r.height - okrajY, Math.max(okrajY - h, stav.y));
  }

  const kamera = {
    stav,
    nastavRozmery(w, h) { sirka = w; vyska = h; },
    naPosluch(fn) { posluchaci.push(fn); },
    // Klientské souřadnice (clientX/Y) → souřadnice mapy.
    naMapu(clientX, clientY) {
      const r = viewport.getBoundingClientRect();
      return { x: (clientX - r.left - stav.x) / stav.z, y: (clientY - r.top - stav.y) / stav.z };
    },
    naObrazovku(mx, my) {
      const r = viewport.getBoundingClientRect();
      return { x: r.left + stav.x + mx * stav.z, y: r.top + stav.y + my * stav.z };
    },
    // Přiblíží/oddálí tak, aby bod pod kurzorem zůstal na místě.
    zoomKolem(clientX, clientY, faktor) {
      const r = viewport.getBoundingClientRect();
      const novy = Math.min(MAX, Math.max(MIN, stav.z * faktor));
      if (novy === stav.z) return;
      const px = clientX - r.left, py = clientY - r.top;
      const mx = (px - stav.x) / stav.z, my = (py - stav.y) / stav.z;
      stav.z = novy;
      stav.x = px - mx * novy;
      stav.y = py - my * novy;
      omezit();
      pouzit();
    },
    posun(dx, dy) {
      stav.x += dx;
      stav.y += dy;
      omezit();
      pouzit();
    },
    // Přesně nastaví zoom a polohu (pro gesto dvěma prsty).
    nastav(x, y, z) {
      stav.z = Math.min(MAX, Math.max(MIN, z));
      stav.x = x;
      stav.y = y;
      omezit();
      pouzit();
    },
    // Celá mapa se vejde do okna, vystředěná.
    prizpusobit() {
      const r = viewport.getBoundingClientRect();
      if (!sirka || !vyska || !r.width || !r.height) return;
      const z = Math.min(MAX, Math.max(MIN, Math.min(r.width / sirka, r.height / vyska) * 0.98));
      stav.z = z;
      stav.x = (r.width - sirka * z) / 2;
      stav.y = (r.height - vyska * z) / 2;
      pouzit();
    },
    // Výchozí pohled při otevření mapy: na velkém monitoru 1:1, jinak
    // vejít celou mapu; kdyby ale 1:1 nevyplnilo ani půlku okna, přiblížit.
    vychozi() {
      this.prizpusobit();
    },
    // Vystředí bod mapy (např. token na tahu) bez změny zoomu.
    vystredit(mx, my) {
      const r = viewport.getBoundingClientRect();
      stav.x = r.width / 2 - mx * stav.z;
      stav.y = r.height / 2 - my * stav.z;
      omezit();
      pouzit();
    },
    obnovit() { pouzit(); },
    // Pohyb kolem středu okna (tlačítka + / −).
    zoomStred(faktor) {
      const r = viewport.getBoundingClientRect();
      this.zoomKolem(r.left + r.width / 2, r.top + r.height / 2, faktor);
    },
  };
  return kamera;
}

// Gesto dvěma prsty: současný zoom a posun podle středu mezi prsty.
function vytvorGesto(kamera) {
  let zacatek = null;
  return {
    aktivni() { return zacatek !== null; },
    start(a, b) {
      zacatek = {
        vzdalenost: Math.max(1, Math.hypot(b.x - a.x, b.y - a.y)),
        stredX: (a.x + b.x) / 2,
        stredY: (a.y + b.y) / 2,
        kamera: { x: kamera.stav.x, y: kamera.stav.y, z: kamera.stav.z },
      };
    },
    pohyb(a, b, viewport) {
      if (!zacatek) return;
      const r = viewport.getBoundingClientRect();
      const vzd = Math.max(1, Math.hypot(b.x - a.x, b.y - a.y));
      const stredX = (a.x + b.x) / 2, stredY = (a.y + b.y) / 2;
      const z = zacatek.kamera.z * (vzd / zacatek.vzdalenost);
      // Bod mapy, který byl na začátku pod středem prstů, má zůstat pod
      // jejich středem (to dává plynulý zoom + posun dohromady).
      const mx = (zacatek.stredX - r.left - zacatek.kamera.x) / zacatek.kamera.z;
      const my = (zacatek.stredY - r.top - zacatek.kamera.y) / zacatek.kamera.z;
      const zOmezene = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, z));
      kamera.nastav(stredX - r.left - mx * zOmezene, stredY - r.top - my * zOmezene, zOmezene);
    },
    konec() { zacatek = null; },
  };
}

const CFG = window.MAPA;
const SVET_ID = CFG.svetId;
const MAPA_ID = CFG.mapaId;
const POHYB_AUTO = CFG.pohybAuto;
const FUNKCE = CFG.funkce; // které nové funkce tokenů jsou v DB (otočení, velikost…)
// Tvorové na více hexech (HB): tvary a pravidla pohybu posílá server (tabulky
// tvary_tvoru, souboj_pohyb, nebo výchozí hodnoty z PHP), viz includes/vtt_souboj_pravidla.php.
const PRAVIDLA_SOUBOJE = CFG.pravidla || null;
const TVAR_JEDEN = {typ_otaceni: 'smer', utok: 'predni3', bunky: [[0, 0]], predni: [[0, 0]]};
function tvarTokenu(t) {
  if (!FUNKCE.hexy || !PRAVIDLA_SOUBOJE || !(t.hexu > 1)) return TVAR_JEDEN;
  return PRAVIDLA_SOUBOJE.tvary[t.hexu] || TVAR_JEDEN;
}
// Grid konsty jsou "let", ne "const" — grid panel (viz níž) je mění živě
// při náhledu ještě před uložením, a po uložení/po eventu od jiného
// klienta se přepíšou na novou trvalou hodnotu.
let GRID_PX = CFG.grid.px;
let GRID_TYPE = CFG.grid.typ;
let GRID_OFFSET_X = CFG.grid.offX;
let GRID_OFFSET_Y = CFG.grid.offY;
let gridSaved = {px: GRID_PX, typ: GRID_TYPE, offX: GRID_OFFSET_X, offY: GRID_OFFSET_Y};
let zdiList = CFG.zdi.slice();
// Piny — typ určuje ikonu v CSS/JS stejně jako server-side výběr ikony
// pro počáteční vykreslení.
const MAPA_BODY = CFG.piny;
let zedVybranaId = null;
let posledniUdalostId = CFG.stav.posledni_udalost_id;
const SVET_POSTAVY = CFG.svetPostavy;
const IS_PJ_OR_ADMIN = CFG.jePj;
let mlhaAktivni = CFG.mlhaAktivni;
// Koho mlhu PJ zrovna nahlíží/upravuje — null = normální PJ pohled
// (bez náhledu). Jen PJ/admin, hráč tohle vůbec nemá v UI.
let mlhaNahledUcetId = null;
let mlhaKreslimOdhalit = true;

const mapWrap = document.getElementById('mapWrap');
const viewport = document.getElementById('viewport');
const logList = document.getElementById('logList');
const rulerSvg = document.getElementById('rulerSvg');
const mapImgEl = document.getElementById('mapImg');
const mlhaCanvas = document.getElementById('mlhaCanvas');

// --- Kamera (zoom + posun) ---
const kamera = vytvorKameru(viewport, mapWrap);
const gesto = vytvorGesto(kamera);

// --- Mlha války: canvas vrstva nad tokeny (z-index 100). Hráč: vždycky
// svoje vlastní odhalené území (hra/api/mlha_stav.php). PJ/admin: prázdno,
// POKUD zrovna nenahlíží konkrétního hráče přes popover "Mlha" — pak to
// samé, ale přes hra/api/mlha_nahled.php (PJ-only, cizí mlha na vyžádání).
// Server je jediný zdroj pravdy, klient si bitmapu jen vykresluje.
let mlhaPosledniStav = null;
// Buňka mlhy JE buňka herního gridu (čtverec i hex) — stejná geometrie
// jako renderGrid()/gridCellCenter() níž, jen vrácená jako Path2D pro
// vykreslení/optimistické kreslení. Střed (cx,cy) je v souřadnicích mapy.
function mlhaBunkaTvarCesta(cx, cy) {
  const p = new Path2D();
  if (GRID_TYPE === 'hex') {
    for (let i = 0; i < 6; i++) {
      const a = Math.PI / 180 * (60 * i - 30);
      const x = cx + GRID_PX * Math.cos(a), y = cy + GRID_PX * Math.sin(a);
      if (i === 0) p.moveTo(x, y); else p.lineTo(x, y);
    }
    p.closePath();
  } else {
    p.rect(cx - GRID_PX / 2, cy - GRID_PX / 2, GRID_PX, GRID_PX);
  }
  return p;
}
// Střed buňky (row, col) v px — inverzní k gridCellCenter(), jen s
// explicitním indexem místo "nejbližší k bodu" (potřeba pro vykreslení
// celé uložené bitmapy, ne jen jednoho kliknutí). Musí být přesná
// shoda se server-side dracak_vtt_mlha_bunka_stred() (includes/vtt_mlha.php).
function mlhaBunkaStred(row, col) {
  if (GRID_TYPE === 'hex') {
    const colStep = Math.sqrt(3) * GRID_PX, rowStep = 1.5 * GRID_PX;
    const offX = ((GRID_OFFSET_X % colStep) + colStep) % colStep;
    const offY = ((GRID_OFFSET_Y % rowStep) + rowStep) % rowStep;
    const rowShift = (Math.abs(row % 2) === 1) ? colStep / 2 : 0;
    return {x: offX + col * colStep + rowShift, y: offY + row * rowStep};
  }
  const offX = ((GRID_OFFSET_X % GRID_PX) + GRID_PX) % GRID_PX;
  const offY = ((GRID_OFFSET_Y % GRID_PX) + GRID_PX) % GRID_PX;
  return {x: offX + col * GRID_PX + GRID_PX / 2, y: offY + row * GRID_PX + GRID_PX / 2};
}
// Hráč vidí mlhu plně neprůhlednou (nemá pod ní co vidět — to je
// smysl mlhy). PJ/admin ji ale vidí jen v NÁHLEDU cizí mlhy (viz
// osvezitMlhu níž — jinak se PJ mlha vůbec nevykresluje), kde potřebuje
// průsvitno prokouknout mapu pod ní, ať ví, kam přesně maluje.
function mlhaBarvaSkryte() {
  return IS_PJ_OR_ADMIN ? 'rgba(8,8,10,0.65)' : '#08080a';
}
function renderMlha(stav) {
  if (!mlhaCanvas) return;
  mlhaPosledniStav = stav;
  const w = mapImgEl.naturalWidth || mapWrap.clientWidth;
  const h = mapImgEl.naturalHeight || mapWrap.clientHeight;
  if (!w || !h) return;
  mlhaCanvas.width = w;
  mlhaCanvas.height = h;
  const ctx = mlhaCanvas.getContext('2d');
  ctx.clearRect(0, 0, w, h);
  if (!stav || stav.zadna_mlha || !stav.sloupcu || !stav.radku) return;
  const bin = atob(stav.bitmapa_b64);
  ctx.fillStyle = mlhaBarvaSkryte();
  for (let r = 0; r < stav.radku; r++) {
    for (let c = 0; c < stav.sloupcu; c++) {
      if (bin.charCodeAt(r * stav.sloupcu + c) === 1) continue;
      const stred = mlhaBunkaStred(r + stav.min_row, c + stav.min_col);
      ctx.fill(mlhaBunkaTvarCesta(stred.x, stred.y));
    }
  }
}
function osvezitMlhu() {
  if (!mlhaCanvas) return;
  if (IS_PJ_OR_ADMIN) {
    if (!mlhaNahledUcetId) { renderMlha(null); return; }
    fetch('api/mlha_nahled.php?mapa_id=' + MAPA_ID + '&ucet_id=' + mlhaNahledUcetId)
      .then(r => r.json())
      .then(renderMlha)
      .catch(() => {});
    return;
  }
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
  if (!gridSvg) return;
  // Velikost tokenů se odvíjí od gridu — při jeho změně (i živém náhledu
  // v nastavení) se překreslí i ony.
  if (typeof nakreslitTokeny === 'function') nakreslitTokeny();
  if (!GRID_PX || GRID_PX <= 0) { gridSvg.innerHTML = ''; return; }
  const w = mapImgEl.naturalWidth || mapWrap.clientWidth;
  const h = mapImgEl.naturalHeight || mapWrap.clientHeight;
  if (!w || !h) return;

  // Celý grid je jediná cesta (<path>) — tisíce samostatných polygonů
  // dělaly při zoomu zbytečně těžký překreslovací krok.
  let d = '';
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
        for (let i = 0; i < 6; i++) {
          const a = Math.PI / 180 * (60 * i - 30);
          d += (i ? 'L' : 'M') + (cx + size * Math.cos(a)).toFixed(1) + ' ' + (cy + size * Math.sin(a)).toFixed(1);
        }
        d += 'Z';
      }
    }
  } else {
    const offX = ((GRID_OFFSET_X % GRID_PX) + GRID_PX) % GRID_PX;
    const offY = ((GRID_OFFSET_Y % GRID_PX) + GRID_PX) % GRID_PX;
    for (let x = offX; x <= w; x += GRID_PX) d += 'M' + x.toFixed(1) + ' 0V' + h;
    for (let y = offY; y <= h; y += GRID_PX) d += 'M0 ' + y.toFixed(1) + 'H' + w;
  }

  gridSvg.setAttribute('width', w);
  gridSvg.setAttribute('height', h);
  gridSvg.innerHTML = '<path d="' + d + '" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1" vector-effect="non-scaling-stroke"/>';
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
  zrusitMlhaNahled();
  ukoncitEditaciPinu();
  document.querySelectorAll('.popover.open').forEach(p => p.classList.remove('open'));
  document.querySelectorAll('.tbtn.active').forEach(b => b.classList.remove('active'));
}
// Pin v editačním módu (popover-pin-upravit otevřený) jde přetáhnout na
// nové místo — viz mapWrap mousedown/move/up níž. Platí jen pro TENHLE
// konkrétní pin a jen dokud je formulář otevřený, ať to nekoliduje s
// běžným tažením tokenů/kreslením zdi/měřítkem.
let pinEditujiId = null;
function ukoncitEditaciPinu() {
  if (pinEditujiId === null) return;
  const el = document.querySelector('.mapa-bod-marker[data-bod-id="' + pinEditujiId + '"]');
  if (el) el.classList.remove('pin-editing');
  pinEditujiId = null;
}
// Umístí popover tak, aby byl celý vidět ve viewportu — zkusí ho otevřít
// "dolů" od bodu (horní okraj popoveru = horní okraj bodu, jako dřív),
// a pokud by se tam nevešel celý (bod hodně dole na obrazovce), otevře
// ho "nahoru" (dolní okraj popoveru = horní okraj bodu) místo toho. Bez
// týhle logiky popover u pinu blízko dolního okraje mizel mimo obrazovku.
function umistitPopoverKBodu(pop, rect) {
  pop.classList.add('open'); // musí být viditelný, aby šlo změřit výšku
  const vyska = pop.offsetHeight;
  const mezera = 10;
  let top = rect.top;
  if (rect.top + vyska + mezera > window.innerHeight) {
    top = rect.bottom - vyska;
  }
  top = Math.max(mezera, Math.min(top, window.innerHeight - vyska - mezera));
  pop.style.top = top + 'px';
}
function togglePopover(id, btn) {
  const wasOpen = document.getElementById(id).classList.contains('open');
  closeAllPopovers();
  if (!wasOpen) {
    const pop = document.getElementById(id);
    umistitPopoverKBodu(pop, btn.getBoundingClientRect());
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
const tbStrom = document.getElementById('tb-strom');
if (tbStrom) tbStrom.addEventListener('click', () => document.getElementById('panel-strom').classList.toggle('open'));
const stromHledat = document.getElementById('stromHledat');
if (stromHledat) {
  // Ukáže uzel, pokud sedí jeho vlastní název NEBO sedí název
  // libovolného potomka (ať je vidět i cesta k zanořené shodě).
  const filtrovatStrom = (dotaz) => {
    const q = dotaz.trim().toLowerCase();
    const navstiv = (li) => {
      const odkaz = li.querySelector(':scope > a.strom-link');
      let shoda = !q || (odkaz && odkaz.textContent.toLowerCase().includes(q));
      const podUl = li.querySelector(':scope > ul');
      if (podUl) {
        podUl.querySelectorAll(':scope > li').forEach((podLi) => { if (navstiv(podLi)) shoda = true; });
      }
      li.style.display = shoda ? '' : 'none';
      return shoda;
    };
    document.querySelectorAll('#stromRoot > li').forEach(navstiv);
  };
  stromHledat.addEventListener('input', (e) => filtrovatStrom(e.target.value));
}

// --- Nástroje toolbaru: select/move (výchozí), ruler (měření), ping, zeď ---
let currentTool = 'select';
const toolButtons = {
  select: document.getElementById('tb-move'),
  ruka: document.getElementById('tb-ruka'),
  vyber: document.getElementById('tb-vyber'),
  ruler: document.getElementById('tb-ruler'),
  ping: document.getElementById('tb-ping'),
  zed: document.getElementById('tb-zed'),
  pin: document.getElementById('tb-pin'),
};
function setTool(tool) {
  if (currentTool === 'kresleni' && tool !== 'kresleni') {
    const pk = document.getElementById('popover-kresleni');
    if (pk) pk.classList.remove('open');
    if (toolButtons.kresleni) toolButtons.kresleni.classList.remove('active');
  }
  currentTool = tool;
  viewport.dataset.nastroj = tool; // CSS podle toho mění kurzor
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
      umistitPopoverKBodu(popZed, toolButtons.zed.getBoundingClientRect());
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
if (toolButtons.ruka) toolButtons.ruka.addEventListener('click', () => setTool(currentTool === 'ruka' ? 'select' : 'ruka'));
if (toolButtons.vyber) toolButtons.vyber.addEventListener('click', () => setTool(currentTool === 'vyber' ? 'select' : 'vyber'));
if (toolButtons.ruler) toolButtons.ruler.addEventListener('click', () => setTool(currentTool === 'ruler' ? 'select' : 'ruler'));
if (toolButtons.ping) toolButtons.ping.addEventListener('click', () => setTool(currentTool === 'ping' ? 'select' : 'ping'));
if (toolButtons.zed) toolButtons.zed.addEventListener('click', () => setTool(currentTool === 'zed' ? 'select' : 'zed'));
if (toolButtons.pin) toolButtons.pin.addEventListener('click', () => setTool(currentTool === 'pin' ? 'select' : 'pin'));

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

// --- Mlha: popover má 3 nezávislé věci (viz CLAUDE.md diskuze) —
// zapnout/vypnout pro mapu, náhled konkrétního hráče, ruční kreslení
// (odhalit/zatáhnout) do TOHO náhledu. Výběr hráče v selectu rovnou
// vyzbrojí currentTool='mlha' (viz mousedown/move/up výš) — zvlášť
// tlačítko na "začít kreslit" by bylo matoucí navíc, náhled a kreslení
// jsou jedna a ta samá obrazovka.
const tbMlha = document.getElementById('tb-mlha');
if (tbMlha) tbMlha.addEventListener('click', () => togglePopover('popover-mlha', tbMlha));
const mlhaAktivniChk = document.getElementById('mlhaAktivniChk');
if (mlhaAktivniChk) {
  mlhaAktivniChk.addEventListener('change', () => {
    const nova = mlhaAktivniChk.checked;
    postJson('api/mapa_mlha_aktivni.php', {mapa_id: MAPA_ID, aktivni: nova})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); mlhaAktivniChk.checked = !nova; return; }
        mlhaAktivni = d.aktivni;
        logLine(mlhaAktivni ? 'Mlha zapnuta.' : 'Mlha vypnuta — hráči teď vidí celou mapu.');
      });
  });
}
const mlhaNahledSelect = document.getElementById('mlhaNahledSelect');
const mlhaKresliciFields = document.getElementById('mlhaKresliciFields');
function zrusitMlhaNahled() {
  mlhaNahledUcetId = null;
  if (mlhaNahledSelect) mlhaNahledSelect.value = '';
  if (mlhaKresliciFields) mlhaKresliciFields.style.display = 'none';
  if (currentTool === 'mlha') setTool('select');
  osvezitMlhu();
}
const mlhaOdhalitBtn = document.getElementById('mlhaOdhalitBtn');
const mlhaZatahnoutBtn = document.getElementById('mlhaZatahnoutBtn');
const mlhaRezimVolnaRukaBtn = document.getElementById('mlhaRezimVolnaRukaBtn');
const mlhaRezimObdelnikBtn = document.getElementById('mlhaRezimObdelnikBtn');
const mlhaKreslimRezimEl = document.getElementById('mlhaKreslimRezim');
// Dva nezávislé přepínače (štětec odhalit/zatáhnout × tvar volná ruka/obdélník)
// sdílí jeden řádek textu dole v popoveru — každá ze dvou funkcí níž mění
// jen "svoji" část stavu a pak obě společně přeskládají celý text, aby se
// vzájemně nepřepisovaly (dřív nastavMlhaKreslimRezim mazala zmínku o tvaru).
function obnovMlhaRezimText() {
  if (!mlhaKreslimRezimEl) return;
  const tvar = mlhaRezimObdelnik ? 'Obdélník — táhni z rohu do rohu.' : 'Volná ruka — táhni po mapě.';
  const stetec = mlhaKreslimOdhalit ? 'odhalit' : 'zatáhnout';
  mlhaKreslimRezimEl.textContent = tvar + ' Režim: ' + stetec;
}
function nastavMlhaKreslimRezim(odhalit) {
  mlhaKreslimOdhalit = odhalit;
  if (mlhaOdhalitBtn) mlhaOdhalitBtn.classList.toggle('btn-primary', odhalit);
  if (mlhaOdhalitBtn) mlhaOdhalitBtn.classList.toggle('btn-secondary', !odhalit);
  if (mlhaZatahnoutBtn) mlhaZatahnoutBtn.classList.toggle('btn-primary', !odhalit);
  if (mlhaZatahnoutBtn) mlhaZatahnoutBtn.classList.toggle('btn-ghost', odhalit);
  obnovMlhaRezimText();
}
function nastavMlhaRezimObdelnik(obdelnik) {
  mlhaRezimObdelnik = obdelnik;
  mlhaObdelnikStart = null;
  if (rulerSvg) rulerSvg.innerHTML = '';
  if (mlhaRezimVolnaRukaBtn) mlhaRezimVolnaRukaBtn.classList.toggle('btn-primary', !obdelnik);
  if (mlhaRezimVolnaRukaBtn) mlhaRezimVolnaRukaBtn.classList.toggle('btn-secondary', obdelnik);
  if (mlhaRezimObdelnikBtn) mlhaRezimObdelnikBtn.classList.toggle('btn-primary', obdelnik);
  if (mlhaRezimObdelnikBtn) mlhaRezimObdelnikBtn.classList.toggle('btn-ghost', !obdelnik);
  obnovMlhaRezimText();
}
if (mlhaOdhalitBtn) mlhaOdhalitBtn.addEventListener('click', () => nastavMlhaKreslimRezim(true));
if (mlhaZatahnoutBtn) mlhaZatahnoutBtn.addEventListener('click', () => nastavMlhaKreslimRezim(false));
if (mlhaRezimVolnaRukaBtn) mlhaRezimVolnaRukaBtn.addEventListener('click', () => nastavMlhaRezimObdelnik(false));
if (mlhaRezimObdelnikBtn) mlhaRezimObdelnikBtn.addEventListener('click', () => nastavMlhaRezimObdelnik(true));
if (mlhaNahledSelect) {
  mlhaNahledSelect.addEventListener('change', () => {
    mlhaNahledUcetId = mlhaNahledSelect.value ? parseInt(mlhaNahledSelect.value, 10) : null;
    if (mlhaKresliciFields) mlhaKresliciFields.style.display = mlhaNahledUcetId ? '' : 'none';
    if (mlhaNahledUcetId) { nastavMlhaRezimObdelnik(false); nastavMlhaKreslimRezim(true); }
    setTool(mlhaNahledUcetId ? 'mlha' : 'select');
    osvezitMlhu();
  });
}
const mlhaOdhalitVseBtn = document.getElementById('mlhaOdhalitVseBtn');
const mlhaZatahnoutVseBtn = document.getElementById('mlhaZatahnoutVseBtn');
// Hromadná akce pro VŠECHNY hráče světa najednou — bez undo, proto potvrzení.
function mlhaHromadne(odhalit) {
  const otazka = odhalit
    ? 'Odhalit celou mapu úplně všem hráčům? Přepíše se jim tím dosavadní prozkoumané území.'
    : 'Zatáhnout celou mapu zpátky úplně všem hráčům? Smažou se jim tím všechny dosavadní odhalené oblasti.';
  if (!confirm(otazka)) return;
  postJson('api/mlha_hromadne.php', {mapa_id: MAPA_ID, odhalit})
    .then(d => {
      if (d.error) { logLine('Chyba: ' + d.error); return; }
      logLine(odhalit ? 'Mapa odhalena všem hráčům.' : 'Mapa zatažena všem hráčům.');
      osvezitMlhu();
    });
}
if (mlhaOdhalitVseBtn) mlhaOdhalitVseBtn.addEventListener('click', () => mlhaHromadne(true));
if (mlhaZatahnoutVseBtn) mlhaZatahnoutVseBtn.addEventListener('click', () => mlhaHromadne(false));

// --- Ruler: čistě klientská pomůcka, nic se neukládá ani nesynchronizuje ---
let rulerStart = null, rulerClearTimer = null;
let zedRozpracovaneBody = [];
let mlhaKreslim = false, mlhaPosledniBunka = null;
let mlhaRezimObdelnik = false, mlhaObdelnikStart = null;
function rulerPoint(e) {
  return kamera.naMapu(e.clientX, e.clientY);
}
// PJ ruční kreslení mlhy, volná ruka (viz popover "Mlha") — na rozdíl
// od dracak_vtt_mlha_odhal_kolem_bodu() žádná LoS kontrola, žádné
// "nikdy znovu nezatáhnout", jen přímo ta jedna buňka GRIDU pod
// kurzorem (gridCellCenter() řeší čtverec i hex stejně jako u tokenů).
// Optimistické kreslení přímo tvaru buňky (okamžitá odezva, žádná
// bit-manipulace báze64 navíc) — server dotaz potvrzuje/ukládá async;
// obdélníkový režim (mlhaRezimObdelnik) po odeslání vždycky přetáhne
// čerstvý stav ze serveru (viz mapWrap mouseup níž), tohle platí jen
// pro kreslení bod-po-bodu.
function mlhaNakresliBod(p) {
  if (!mlhaNahledUcetId || GRID_PX <= 0) return;
  const stred = gridCellCenter(p.x, p.y);
  const key = Math.round(stred.x) + ':' + Math.round(stred.y);
  if (key === mlhaPosledniBunka) return;
  mlhaPosledniBunka = key;
  if (mlhaCanvas) {
    const ctx = mlhaCanvas.getContext('2d');
    const tvar = mlhaBunkaTvarCesta(stred.x, stred.y);
    if (mlhaKreslimOdhalit) {
      ctx.save();
      ctx.globalCompositeOperation = 'destination-out';
      ctx.fill(tvar);
      ctx.restore();
    } else {
      ctx.fillStyle = mlhaBarvaSkryte();
      ctx.fill(tvar);
    }
  }
  postJson('api/mlha_uprava.php', {mapa_id: MAPA_ID, ucet_id: mlhaNahledUcetId, x: stred.x, y: stred.y, odhalit: mlhaKreslimOdhalit})
    .then(d => { if (d.error) logLine('Chyba: ' + d.error); });
}
// --- Hexy: nejbližší buňka k bodu a vzdálenost v hexech ---
// Rozložení "odd-r" (liché řady posunuté doprava o půl buňky), stejné jako
// renderGrid()/gridCellCenter(). Vzdálenost = počet kroků mezi sousedními hexy
// (jeden hex = jeden sáh, h1621).
function hexBunka(px, py) {
  const size = GRID_PX, colStep = Math.sqrt(3) * size, rowStep = 1.5 * size;
  const offX = ((GRID_OFFSET_X % colStep) + colStep) % colStep;
  const offY = ((GRID_OFFSET_Y % rowStep) + rowStep) % rowStep;
  const rowApprox = Math.round((py - offY) / rowStep);
  let best = null, bd = Infinity;
  for (let row = rowApprox - 1; row <= rowApprox + 1; row++) {
    const rowShift = (row & 1) ? colStep / 2 : 0;
    const colApprox = Math.round((px - offX - rowShift) / colStep);
    for (let col = colApprox - 1; col <= colApprox + 1; col++) {
      const cx = offX + col * colStep + rowShift, cy = offY + row * rowStep;
      const d = Math.hypot(cx - px, cy - py);
      if (d < bd) { bd = d; best = {row, col, x: cx, y: cy}; }
    }
  }
  return best;
}
function hexVzdalenost(a, b) {
  const kostka = (c) => { const x = c.col - (c.row - (c.row & 1)) / 2; return {x, z: c.row, y: -x - c.row}; };
  const A = kostka(a), B = kostka(b);
  return Math.max(Math.abs(A.x - B.x), Math.abs(A.y - B.y), Math.abs(A.z - B.z));
}
function hexObrys(cx, cy, size) {
  let pts = '';
  for (let i = 0; i < 6; i++) {
    const ang = Math.PI / 180 * (60 * i - 30);
    pts += (i ? ' ' : '') + (cx + size * Math.cos(ang)).toFixed(1) + ',' + (cy + size * Math.sin(ang)).toFixed(1);
  }
  return pts;
}
function sahyText(n) {
  if (Number.isInteger(n)) return n === 1 ? '1 sáh' : (n >= 2 && n <= 4 ? n + ' sáhy' : n + ' sáhů');
  return n.toFixed(1).replace('.', ',') + ' sáhu';
}
// Vzdálenost dvou bodů mapy ve hře-jednotkách: na hexech počet hexů mezi
// středy buněk, na čtverci/bez gridu přímá vzdálenost.
function popisVzdalenosti(a, b) {
  if (GRID_PX > 0 && GRID_TYPE === 'hex') return sahyText(hexVzdalenost(hexBunka(a.x, a.y), hexBunka(b.x, b.y)));
  const pxSah = pxNaSah();
  const dist = Math.hypot(b.x - a.x, b.y - a.y);
  return pxSah > 0 ? sahyText(Math.round(dist / pxSah * 10) / 10) : Math.round(dist) + ' px';
}
function drawRuler(a, b) {
  if (!rulerSvg) return;
  clearTimeout(rulerClearTimer);
  const hex = GRID_PX > 0 && GRID_TYPE === 'hex';
  // Na hexech se měří od středu do středu buňky (to je pohyb po hexech).
  let ca = a, cb = b, obrysy = '';
  if (hex) {
    const ha = hexBunka(a.x, a.y), hb = hexBunka(b.x, b.y);
    ca = {x: ha.x, y: ha.y};
    cb = {x: hb.x, y: hb.y};
    obrysy = '<polygon points="' + hexObrys(ha.x, ha.y, GRID_PX) + '" fill="rgba(255,179,71,.15)" stroke="#ffb347" stroke-width="1.5" vector-effect="non-scaling-stroke"/>'
      + '<polygon points="' + hexObrys(hb.x, hb.y, GRID_PX) + '" fill="rgba(255,179,71,.25)" stroke="#ffb347" stroke-width="1.5" vector-effect="non-scaling-stroke"/>';
  }
  const label = popisVzdalenosti(ca, cb);
  // Písmo a body mají stejnou velikost na obrazovce bez ohledu na zoom.
  const k = 1 / kamera.stav.z;
  rulerSvg.innerHTML = obrysy +
    '<line x1="' + ca.x + '" y1="' + ca.y + '" x2="' + cb.x + '" y2="' + cb.y + '" stroke="#ffb347" stroke-width="2" stroke-dasharray="6 4" vector-effect="non-scaling-stroke"/>' +
    '<circle cx="' + ca.x + '" cy="' + ca.y + '" r="' + (4 * k) + '" fill="#ffb347"/>' +
    '<circle cx="' + cb.x + '" cy="' + cb.y + '" r="' + (4 * k) + '" fill="#ffb347"/>' +
    '<text x="' + (ca.x + cb.x) / 2 + '" y="' + ((ca.y + cb.y) / 2 - 10 * k) + '" fill="#fff" font-size="' + (14 * k) + '" ' +
    'text-anchor="middle" paint-order="stroke" stroke="#000" stroke-width="' + (3 * k) + '">' + label + '</text>';
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

// --- Tokeny: stav ze serveru a vykreslení ---
// Tokeny se kreslí z dat (ne z HTML od PHP), aby šly překreslit "na místě"
// bez location.reload() — viz obnovitStav() a hra/api/mapa_stav.php.
// Obnova nic neruší: otevřené panely, zoom ani vybraný token zůstávají.
let tokeny = CFG.stav.tokeny.slice();
let koloStav = CFG.stav.kolo;
let iniciativa = CFG.stav.iniciativa;
let ucastnici = CFG.stav.ucastnici || [];
let vyzvy = CFG.stav.vyzvy || [];
let volnePostavy = CFG.stav.volne_postavy;
let kresby = CFG.stav.kresby || [];
const tokenEl = new Map();
const spravovatPanel = document.getElementById('panel-spravovat');
let spravovanyToken = null;
// Tokeny, které tenhle klient právě přesouvá (pozici jim nepřepisuje obnova
// stavu, dokud server nepotvrdí přesun).
const drzeneTokeny = new Set();

function esc(text) {
  return String(text ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
}
function tokenData(id) { return tokeny.find((t) => t.id === id) || null; }
// Token je ve výchozí velikosti o něco menší než buňka gridu (aby se do hexu
// vešel celý); bez gridu pevných 40 px jako dřív.
function zakladniPrumerTokenu() {
  const sah = pxNaSah();
  return sah > 0 ? sah * 0.82 : 40;
}
function prumerTokenu(t) { return zakladniPrumerTokenu() * (t.velikost > 0 ? t.velikost : 1); }
// Dvě písmena jména; u číslovaných kusů ("Goblin 3") první písmeno a číslo ("G3"),
// ať jdou na mapě rozeznat.
function iniciala(label) {
  if (!label) return '?';
  const m = String(label).match(/^(.*\S)\s+(\d{1,2})$/);
  if (m) return Array.from(m[1])[0] + m[2];
  return Array.from(String(label)).slice(0, 2).join('');
}
function barvaTokenu(t) {
  if (t.barva) return t.barva;
  if (t.typ_entity === 'postava') return 'var(--color-accent-600, #7a3b2e)';
  if (t.typ_entity === 'nestvura_instance') return '#4a2b2b';
  return '#555';
}

function vytvorTokenEl(t) {
  const el = document.createElement('div');
  el.className = 'vtt-token';
  el.dataset.id = String(t.id);
  el.innerHTML = '<svg class="telo" width="1" height="1"></svg><div class="face"></div><div class="puck"><span></span></div><div class="hp"></div><div class="fx"></div>';
  mapWrap.appendChild(el);
  return el;
}

function nakreslitToken(t) {
  let el = tokenEl.get(t.id);
  if (!el) { el = vytvorTokenEl(t); tokenEl.set(t.id, el); }
  const d = prumerTokenu(t);
  if (!drzeneTokeny.has(t.id)) {
    el.style.left = t.x + 'px';
    el.style.top = t.y + 'px';
  }
  el.style.zIndex = String(20 + Math.max(-10, Math.min(70, t.z))); // pod mlhou (100), nad kresbami (8) a zdmi (5)
  el.style.setProperty('--d', d.toFixed(1) + 'px');
  const mrtvy = t.hp !== null && t.hp <= 0;
  el.classList.toggle('dead', mrtvy);
  el.classList.toggle('not-turn', !t.na_tahu && t.owned);
  el.classList.toggle('movable', t.owned && t.na_tahu && !t.zamceno);
  el.classList.toggle('zamceno', !!t.zamceno);
  el.classList.toggle('skryty', !t.viditelny);
  el.classList.toggle('aktivni-tah', !!koloStav && koloStav.aktivni_token_id === t.id);
  el.classList.toggle('vybrany', typeof jeVybrany === 'function' && jeVybrany(t.id));
  const puck = el.querySelector('.puck');
  puck.style.backgroundColor = barvaTokenu(t);
  puck.style.fontSize = Math.max(9, Math.round(d * 0.3)) + 'px';
  puck.firstChild.textContent = iniciala(t.label);
  if (FUNKCE.obrazek && t.obrazek_id) {
    puck.style.backgroundImage = "url('obrazek.php?id=" + t.obrazek_id + "')";
    puck.style.backgroundSize = 'cover';
    puck.style.backgroundPosition = 'center';
    puck.firstChild.style.display = 'none';
  } else {
    puck.style.backgroundImage = '';
    puck.firstChild.style.display = '';
  }
  const face = el.querySelector('.face');
  face.style.display = FUNKCE.smer ? '' : 'none';
  face.style.transform = 'rotate(' + (t.smer || 0) + 'deg)';
  nakreslitTelo(t, el);
  const hp = el.querySelector('.hp');
  if (t.hp !== null) {
    hp.style.display = '';
    hp.textContent = t.hp + (t.max_hp !== null ? '/' + t.max_hp : '');
  } else {
    hp.style.display = 'none';
  }
  const fx = el.querySelector('.fx');
  fx.textContent = (t.efekty || []).join(', ');
  fx.style.display = t.efekty && t.efekty.length ? '' : 'none';
  fx.title = (t.efekty_detail || []).map(popisEfektu).join('\n');
  el.title = t.label + (!t.na_tahu && t.owned ? ' (není na tahu)' : '') + (t.zamceno ? ' (zamčený)' : '');
}

// Jeden řádek o efektu pro tooltip: název, zbývající trvání, co boj sám dělá (nebo že to nechá na PJ).
function popisEfektu(e) {
  const kola = e.zbyva_kol === null || e.zbyva_kol === undefined ? 'bez limitu' : e.zbyva_kol + ' ' + (e.zbyva_kol === 1 ? 'kolo' : (e.zbyva_kol < 5 ? 'kola' : 'kol'));
  const co = e.shrnuti && e.shrnuti.length ? e.shrnuti.join(', ') : (e.stav_nazev || '');
  return e.nazev + ' (' + kola + ')' + (co ? ' — ' + co : '') + (e.stav === 'pj' || e.stav === 'past' || e.stav === 'ceka' ? ' [vyhodnotí PJ]' : (e.stav === 'cast' ? ' [zbytek PJ]' : ''));
}

// Tělo tvora na více hexech: průsvitné hexy kolem otočného hexu (tam leží střed
// tokenu) a tlustá žlutá čára na čelní hraně (u tvarů s čelní hranou, D a větší).
// SVG je uvnitř elementu tokenu, takže se s ním hýbe a řadí na mapě.
const SVGNS = 'http://www.w3.org/2000/svg';
function hexBod(cx, cy, size, i) {
  const a = Math.PI / 180 * (60 * i - 30);
  return [cx + size * Math.cos(a), cy + size * Math.sin(a)];
}
function nakreslitTelo(t, el) {
  const svg = el.querySelector('svg.telo');
  if (!svg) return;
  const tvar = tvarTokenu(t);
  const maTelo = FUNKCE.hexy && GRID_PX > 0 && GRID_TYPE === 'hex' && t.hexu > 1;
  const klicTela = maTelo ? [t.hexu, t.smer || 0, GRID_PX, GRID_OFFSET_X, GRID_OFFSET_Y, t.x, t.y, t.barva || '', t.typ_entity].join('|') : '';
  if (svg.dataset.klic === klicTela) return;
  svg.dataset.klic = klicTela;
  svg.innerHTML = '';
  if (!maTelo) return;
  const pivot = hexBunka(t.x, t.y);
  const orient = hexy.orientace(tvar, t.smer || 0);
  // Souřadnice jsou vůči středu tokenu (t.x, t.y); body hexů vůči středu buňky otočného hexu.
  const px = pivot.x - t.x, py = pivot.y - t.y;
  const barva = barvaTokenu(t);
  const g = document.createElementNS(SVGNS, 'g');
  g.setAttribute('class', 'telo-skup');
  const cely = hexy.bunkyTvaru(tvar, pivot, orient);
  const predni = new Set(hexy.bunkyTvaru(tvar, pivot, orient, 'predni').map(hexy.klic));
  cely.forEach((b) => {
    const st = mlhaBunkaStred(b.row, b.col);
    const pts = [0, 1, 2, 3, 4, 5].map((i) => hexBod(st.x - pivot.x + px, st.y - pivot.y + py, GRID_PX * 0.97, i).map((v) => v.toFixed(1)).join(',')).join(' ');
    const poly = document.createElementNS(SVGNS, 'polygon');
    poly.setAttribute('points', pts);
    poly.setAttribute('class', 'telo-hex' + (hexy.klic(b) === hexy.klic(pivot) ? ' otocny' : '') + (tvar.typ_otaceni === 'smer' && predni.has(hexy.klic(b)) ? ' hlava' : ''));
    poly.style.fill = barva;
    g.appendChild(poly);
  });
  if (tvar.typ_otaceni === 'hrana') {
    // Čelo = vnější strany obou horních hexů (sousedé ve směrech 4+orient a 5+orient)
    hexy.bunkyTvaru(tvar, pivot, orient, 'predni').forEach((b) => {
      const st = mlhaBunkaStred(b.row, b.col);
      const cx = st.x - pivot.x + px, cy = st.y - pivot.y + py;
      const body = hexy.celniHranaBody(cx, cy, GRID_PX * 0.97, orient).map((v) => v.map((n) => n.toFixed(1)).join(','));
      const cara = document.createElementNS(SVGNS, 'polyline');
      cara.setAttribute('points', body.join(' '));
      cara.setAttribute('class', 'telo-celo');
      g.appendChild(cara);
    });
  }
  svg.appendChild(g);
}

function nakreslitTokeny() {
  const zive = new Set();
  tokeny.forEach((t) => { zive.add(t.id); nakreslitToken(t); });
  for (const [id, el] of tokenEl) {
    if (!zive.has(id)) { el.remove(); tokenEl.delete(id); }
  }
  aktualizujRotacniUchyt();
}

// --- Výběr tokenů ---
// Vybraný token má obrys a (když ho smíš otáčet) úchyt na otáčení. Výběr je
// množina, aby šel později rozšířit na více tokenů najednou.
const vybrane = new Set();
function jeVybrany(id) { return vybrane.has(id); }
function aktualizujVyber() {
  tokenEl.forEach((el, id) => el.classList.toggle('vybrany', vybrane.has(id)));
  aktualizujRotacniUchyt();
}
function nastavVyber(ids) {
  vybrane.clear();
  ids.forEach((id) => vybrane.add(id));
  aktualizujVyber();
}
function zrusitVyber() {
  if (!vybrane.size) return;
  vybrane.clear();
  aktualizujVyber();
}
// Po obnově stavu zmizelé tokeny z výběru vypadnou.
function ocistitVyber() {
  for (const id of Array.from(vybrane)) { if (!tokenData(id)) vybrane.delete(id); }
}

// --- Otočení ---
// Krok otáčení podle gridu: hex = 6 stran (60°; v rozšířeném soubojovém systému
// musí být každý tvor obrácen čelem k jedné ze šesti stran hexu, h1621),
// čtverec = 8 směrů (45°), bez gridu po 15°.
function krokOtoceni() { return GRID_PX > 0 ? (GRID_TYPE === 'hex' ? 60 : 45) : 15; }
// Tvary s čelní hranou (D a E) hledí na střed hrany: 30° + násobky 60° (nahoru = 270°).
function posunOtoceni(t) { return GRID_PX > 0 && GRID_TYPE === 'hex' ? hexy.zakladSmeru(tvarTokenu(t)) % 60 : 0; }
// V souboji s fázemi na hexové mapě se hráči hýbou a otáčejí akcemi (Krok,
// Otočka — assets/js/souboj.js), tažením tokenu volně smí jen PJ.
function volnyPohyb() {
  return IS_PJ_OR_ADMIN || !(koloStav && koloStav.pohyb_akcemi);
}
function smiMenitSmer(t) {
  return !!t && FUNKCE.smer && (IS_PJ_OR_ADMIN || (volnyPohyb() && t.owned && t.na_tahu && !t.zamceno));
}
function aktualizujRotacniUchyt() {
  const uchyt = document.getElementById('rotUchyt');
  if (!uchyt) return;
  const t = vybrane.size === 1 ? tokenData(Array.from(vybrane)[0]) : null;
  if (!t || !smiMenitSmer(t)) { uchyt.style.display = 'none'; return; }
  const a = (t.smer || 0) * Math.PI / 180;
  const r = prumerTokenu(t) / 2 + 22 / kamera.stav.z;
  uchyt.style.display = '';
  uchyt.style.left = (t.x + Math.cos(a) * r) + 'px';
  uchyt.style.top = (t.y + Math.sin(a) * r) + 'px';
}
kamera.naPosluch(() => aktualizujRotacniUchyt());

// --- Úprava vzhledu tokenu (otočení, velikost, barva, zámek, obrázek…) ---
// Změna se hned projeví lokálně (optimisticky) a odešle na server; při chybě
// se stav načte znovu ze serveru.
function pouzitZmenyTokenu(t, zmeny) {
  if ('hexu' in zmeny && PRAVIDLA_SOUBOJE && !('smer' in zmeny)) {
    // Čelo musí odpovídat novému tvaru (stejně jako na serveru, hra/api/token_uprava.php).
    const stary = tvarTokenu(t);
    const novy = PRAVIDLA_SOUBOJE.tvary[zmeny.hexu] || TVAR_JEDEN;
    t.smer = hexy.smerZOrientace(novy, stary.typ_otaceni === novy.typ_otaceni ? hexy.orientace(stary, t.smer || 0) : 0);
  }
  for (const [klic, hodnota] of Object.entries(zmeny)) {
    if (klic === 'nazev') continue; // jméno závisí na entitě — řeší obnova stavu
    t[klic] = hodnota;
  }
  nakreslitToken(t);
  aktualizujRotacniUchyt();
  if (spravovanyToken && spravovanyToken.tokenId === t.id) obnovVzhledSekci();
}
function upravToken(t, zmeny) {
  pouzitZmenyTokenu(t, zmeny);
  return postJson('api/token_uprava.php', {token_id: t.id, zmeny})
    .then((d) => {
      if (d.error) { logLine('Chyba: ' + d.error); obnovitStav(); return null; }
      if ('nazev' in zmeny || 'viditelny' in zmeny) obnovitStav();
      if ('hexu' in zmeny && typeof d.zmeny?.smer === 'number') { t.smer = d.zmeny.smer; nakreslitToken(t); aktualizujRotacniUchyt(); if (souboj) souboj.prekreslitOverlay(); }
      return d;
    })
    .catch(() => { logLine('Změna se nepodařila odeslat.'); obnovitStav(); return null; });
}
function otocitTokenO(t, stupne) {
  if (!smiMenitSmer(t)) return;
  upravToken(t, {smer: (((t.smer || 0) + stupne) % 360 + 360) % 360});
}

const BARVY_TOKENU = [
  ['#c0392b', 'Červená'], ['#d9822b', 'Oranžová'], ['#d4ac0d', 'Žlutá'], ['#2e8b57', 'Zelená'], ['#17a2b8', 'Tyrkysová'],
  ['#2f6fc9', 'Modrá'], ['#7d4fc4', 'Fialová'], ['#d6509b', 'Růžová'], ['#6b6b6b', 'Šedá'], ['#222222', 'Černá'], ['#e8e8e8', 'Bílá'],
];
const VELIKOSTI_TOKENU = [[0.5, '½'], [1, '1'], [2, '2'], [3, '3'], [4, '4']];

const vzhledEl = {
  sekce: document.getElementById('vzhledSekce'),
  nazevRadek: document.getElementById('vzhledNazevRadek'), nazev: document.getElementById('vzhledNazev'),
  barvaRadek: document.getElementById('vzhledBarvaRadek'), barvy: document.getElementById('vzhledBarvy'),
  velikostRadek: document.getElementById('vzhledVelikostRadek'), velikost: document.getElementById('vzhledVelikost'),
  hexuRadek: document.getElementById('vzhledHexuRadek'), hexu: document.getElementById('vzhledHexu'),
  otoceniRadek: document.getElementById('vzhledOtoceniRadek'),
  obrazekRadek: document.getElementById('vzhledObrazekRadek'), obrazekNahled: document.getElementById('vzhledObrazekNahled'),
  zamcenoRadek: document.getElementById('vzhledZamcenoRadek'), zamceno: document.getElementById('vzhledZamceno'),
  skrytyRadek: document.getElementById('vzhledSkrytyRadek'), skryty: document.getElementById('vzhledSkryty'),
};
function spravovanyTokenData() { return spravovanyToken ? tokenData(spravovanyToken.tokenId) : null; }

function obnovVzhledSekci() {
  const t = spravovanyTokenData();
  if (!t || !vzhledEl.sekce) return;
  const pj = IS_PJ_OR_ADMIN;
  const smiObrazek = FUNKCE.obrazek && (pj || (t.owned && t.na_tahu && !t.zamceno));
  const radky = [
    [vzhledEl.nazevRadek, pj && FUNKCE.nazev && t.typ_entity !== 'postava'],
    [vzhledEl.barvaRadek, pj && FUNKCE.barva],
    [vzhledEl.velikostRadek, pj && FUNKCE.velikost],
    [vzhledEl.hexuRadek, pj && FUNKCE.hexy && GRID_PX > 0 && GRID_TYPE === 'hex' && ['postava', 'nestvura_instance'].includes(t.typ_entity)],
    [vzhledEl.otoceniRadek, smiMenitSmer(t)],
    [vzhledEl.obrazekRadek, smiObrazek],
    [vzhledEl.zamcenoRadek, pj && FUNKCE.zamek],
    [vzhledEl.skrytyRadek, pj && FUNKCE.udalosti],
  ];
  radky.forEach(([el, zobrazit]) => { if (el) el.style.display = zobrazit ? '' : 'none'; });
  vzhledEl.sekce.style.display = radky.some(([, z]) => z) ? '' : 'none';
  if (vzhledEl.nazev && document.activeElement !== vzhledEl.nazev) vzhledEl.nazev.value = t.typ_entity === 'obecny' ? t.label : (t.label || '');
  vzhledEl.barvy.querySelectorAll('.vz-barva').forEach((b) => b.classList.toggle('aktivni', (b.dataset.barva || '') === (t.barva || '')));
  vzhledEl.velikost.querySelectorAll('button').forEach((b) => b.classList.toggle('aktivni', parseFloat(b.dataset.velikost) === (t.velikost || 1)));
  if (vzhledEl.hexu) vzhledEl.hexu.querySelectorAll('button').forEach((b) => b.classList.toggle('aktivni', parseInt(b.dataset.hexu, 10) === (t.hexu || 1)));
  if (vzhledEl.zamceno) vzhledEl.zamceno.checked = !!t.zamceno;
  if (vzhledEl.skryty) vzhledEl.skryty.checked = !t.viditelny;
  if (vzhledEl.obrazekNahled) {
    vzhledEl.obrazekNahled.style.backgroundImage = t.obrazek_id ? "url('obrazek.php?id=" + t.obrazek_id + "')" : '';
  }
}
if (vzhledEl.sekce) {
  // Barvy a velikosti se sestaví jednou.
  vzhledEl.barvy.innerHTML = '<button type="button" class="vz-barva vychozi" data-barva="" title="Výchozí barva"></button>'
    + BARVY_TOKENU.map(([kod, nazev]) => '<button type="button" class="vz-barva" data-barva="' + kod + '" title="' + nazev + '" style="background:' + kod + ';"></button>').join('');
  vzhledEl.barvy.addEventListener('click', (e) => {
    const b = e.target.closest('.vz-barva');
    const t = spravovanyTokenData();
    if (b && t) upravToken(t, {barva: b.dataset.barva || null});
  });
  vzhledEl.velikost.innerHTML = VELIKOSTI_TOKENU.map(([v, text]) => '<button type="button" class="btn btn-ghost" data-velikost="' + v + '" title="' + v + ' sáhu">' + text + '</button>').join('');
  vzhledEl.velikost.addEventListener('click', (e) => {
    const b = e.target.closest('button');
    const t = spravovanyTokenData();
    if (b && t) upravToken(t, {velikost: parseFloat(b.dataset.velikost)});
  });
  if (vzhledEl.hexu && PRAVIDLA_SOUBOJE) {
    // Kolik hexů tvor zabírá (HB, tabulka tvary_tvoru): 1, 2 (zvířecí C), 3 (D), 5 (E)
    vzhledEl.hexu.innerHTML = Object.keys(PRAVIDLA_SOUBOJE.tvary).map((h) => '<button type="button" class="btn btn-ghost" data-hexu="' + h
      + '" title="' + esc(PRAVIDLA_SOUBOJE.tvary[h].popis) + '">' + h + '</button>').join('');
    vzhledEl.hexu.addEventListener('click', (e) => {
      const b = e.target.closest('button');
      const t = spravovanyTokenData();
      if (b && t) upravToken(t, {hexu: parseInt(b.dataset.hexu, 10)});
    });
  }
  document.getElementById('vzhledOtocL').addEventListener('click', () => { const t = spravovanyTokenData(); if (t) otocitTokenO(t, -krokOtoceni()); });
  document.getElementById('vzhledOtocR').addEventListener('click', () => { const t = spravovanyTokenData(); if (t) otocitTokenO(t, krokOtoceni()); });
  vzhledEl.zamceno.addEventListener('change', () => { const t = spravovanyTokenData(); if (t) upravToken(t, {zamceno: vzhledEl.zamceno.checked}); });
  vzhledEl.skryty.addEventListener('change', () => { const t = spravovanyTokenData(); if (t) upravToken(t, {viditelny: !vzhledEl.skryty.checked}); });
  const ulozNazev = () => {
    const t = spravovanyTokenData();
    if (t) upravToken(t, {nazev: vzhledEl.nazev.value.trim()}).then(() => { document.getElementById('spravovatNazev').textContent = vzhledEl.nazev.value.trim() || t.label; });
  };
  document.getElementById('vzhledNazevUlozit').addEventListener('click', ulozNazev);
  vzhledEl.nazev.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); ulozNazev(); } });
  document.getElementById('vzhledObrazekVybrat').addEventListener('click', () => {
    const t = spravovanyTokenData();
    if (t) otevritObrazky((id) => upravToken(t, {obrazek_id: id}));
  });
  document.getElementById('vzhledObrazekOdebrat').addEventListener('click', () => { const t = spravovanyTokenData(); if (t) upravToken(t, {obrazek_id: null}); });
}

// --- Knihovna obrázků světa (okno "Obrázek tokenu") ---
const modalObrazky = document.getElementById('modalObrazky');
let obrazekPoVyberu = null;
function zobrazChybuObrazku(text) {
  const el = document.getElementById('obrazekChyba');
  if (!el) return;
  el.textContent = text || '';
  el.style.display = text ? '' : 'none';
}
async function nactiObrazky() {
  const mrizka = document.getElementById('obrazkyMrizka');
  try {
    const r = await fetch('api/obrazky_seznam.php?svet_id=' + SVET_ID, {cache: 'no-store', credentials: 'same-origin'});
    const d = await r.json();
    const seznam = d.obrazky || [];
    mrizka.innerHTML = seznam.length ? '' : '<p class="note" style="grid-column:1/-1;margin:0;">Zatím tu nic není — nahraj první obrázek.</p>';
    seznam.forEach((o) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.title = o.nazev;
      b.style.backgroundImage = "url('obrazek.php?id=" + o.id + "')";
      const popis = document.createElement('span');
      popis.textContent = o.nazev;
      b.appendChild(popis);
      b.addEventListener('click', () => {
        const cb = obrazekPoVyberu;
        zavritObrazky();
        if (cb) cb(o.id);
      });
      mrizka.appendChild(b);
    });
  } catch (err) {
    mrizka.innerHTML = '<p class="note" style="grid-column:1/-1;">Knihovnu se nepodařilo načíst.</p>';
  }
}
function otevritObrazky(poVyberu) {
  if (!modalObrazky) return;
  obrazekPoVyberu = poVyberu;
  zobrazChybuObrazku('');
  modalObrazky.style.display = 'flex';
  nactiObrazky();
}
function zavritObrazky() {
  if (modalObrazky) modalObrazky.style.display = 'none';
  obrazekPoVyberu = null;
}
if (modalObrazky) {
  document.getElementById('modalObrazkyZavrit').addEventListener('click', zavritObrazky);
  modalObrazky.addEventListener('pointerdown', (e) => { if (e.target === modalObrazky) zavritObrazky(); });
  document.getElementById('obrazekNahrat').addEventListener('click', async () => {
    const vstup = document.getElementById('obrazekSoubor');
    if (!vstup.files.length) { zobrazChybuObrazku('Nejdřív vyber soubor.'); return; }
    const fd = new FormData();
    fd.append('svet_id', String(SVET_ID));
    fd.append('soubor', vstup.files[0]);
    zobrazChybuObrazku('');
    try {
      const r = await fetch('api/obrazek_nahrat.php', {method: 'POST', body: fd, credentials: 'same-origin'});
      const d = await r.json();
      if (d.error) { zobrazChybuObrazku(d.error); return; }
      vstup.value = '';
      nactiObrazky();
    } catch (err) {
      zobrazChybuObrazku('Nahrání se nepodařilo.');
    }
  });
}

// --- Pořadí tahů (panel vpravo nahoře) ---
const iniPanel = document.getElementById('panelIniciativa');
const iniList = document.getElementById('iniciativaList');
const iniTitle = document.getElementById('iniciativaTitle');
// Souboj s fázemi (assets/js/souboj.js) — jen když je na serveru zapnutý
// (ruční SQL 0085); jinak zůstává dnešní jednoduchá iniciativa.
const souboj = initSouboj ? initSouboj({
  mapaId: MAPA_ID,
  jePj: IS_PJ_OR_ADMIN,
  postJson,
  logLine,
  getTokeny: () => tokeny,
  getVybrane: () => Array.from(vybrane),
  vystredit: (x, y) => kamera.vystredit(x, y),
  obnovitStav: () => obnovitStav(),
  pohybAuto: POHYB_AUTO,
  hodit3D,
  nactiKostky,
  kostky3DPripraveny,
  hexy,
  jeHexMapa: () => GRID_PX > 0 && GRID_TYPE === 'hex',
  hexBunka: (x, y) => hexBunka(x, y),
  hexStred: (col, row) => mlhaBunkaStred(row, col),
  gridPx: () => GRID_PX,
  pravidla: PRAVIDLA_SOUBOJE,
  tvarTokenu,
  getZdi: () => zdiList,
  soubojSvg: document.getElementById('soubojSvg'),
}) : null;
// Deník postavy v panelu vedle mapy (assets/js/denik_dock.js) — upravuje se přímo při hře.
const denikDock = initDenikDock ? initDenikDock({
  postavy: window.MAPA.denikPostavy,
  spravovaneId: () => (spravovanyToken && spravovanyToken.typEntity === 'postava' ? spravovanyToken.entitaId : null),
  posunMapy: (dx) => kamera.posun(dx, 0),
  poZmene: (postavaId) => {
    obnovitStav();
    if (spravovanyToken && spravovanyToken.typEntity === 'postava' && spravovanyToken.entitaId === postavaId) nactiInventar();
  },
}) : null;
const denikOtevritBtn = document.getElementById('spravovatDenik');
if (denikOtevritBtn && denikDock) {
  denikOtevritBtn.addEventListener('click', () => {
    if (!spravovanyToken || spravovanyToken.typEntity !== 'postava') return;
    denikDock.otevriPro(spravovanyToken.entitaId);
    // Na telefonu je deník spodní list přes půl obrazovky — detail tokenu by pod ním zbytečně překážel.
    if (window.innerWidth <= 760) spravovatPanel.classList.remove('open');
  });
}
function nakreslitIniciativu() {
  if (souboj) { souboj.aplikuj({kolo: koloStav, iniciativa, ucastnici, vyzvy}); return; }
  if (!iniPanel) return;
  if (!koloStav) { iniPanel.style.display = 'none'; return; }
  iniPanel.style.display = '';
  iniTitle.textContent = 'Kolo ' + koloStav.cislo_kola + ' — pořadí tahů';
  iniList.innerHTML = '';
  iniciativa.forEach((r) => {
    const aktivni = r.token_id === koloStav.aktivni_token_id;
    const radek = document.createElement('div');
    radek.className = 'ini-radek' + (aktivni ? ' aktivni' : '');
    radek.dataset.tokenId = String(r.token_id);
    const jmeno = document.createElement('span');
    jmeno.textContent = r.label || '?';
    const cisla = document.createElement('span');
    cisla.textContent = r.hod + (r.modifikator >= 0 ? '+' : '') + r.modifikator + '=' + r.vysledek + ' · ' + r.akce_zbyvajici + '/' + r.akce_celkem + ' akcí';
    radek.append(jmeno, cisla);
    iniList.appendChild(radek);
  });
}
if (iniList && !FUNKCE.souboj) {
  iniList.addEventListener('click', (e) => {
    const radek = e.target.closest('.ini-radek');
    const t = radek ? tokenData(parseInt(radek.dataset.tokenId, 10)) : null;
    if (t) kamera.vystredit(t.x, t.y);
  });
}

// --- Postavy, které ještě nemají na mapě token (výběr v "Přidat token") ---
function naplnitVolnePostavy() {
  const sel = document.getElementById('novaPostavaSelect');
  const wrap = document.getElementById('novaPostavaWrap');
  const prazdno = document.getElementById('novaPostavaPrazdno');
  if (!sel) return;
  const puvodni = sel.value;
  sel.innerHTML = '<option value="">—</option>' + volnePostavy.map((p) => '<option value="' + p.id + '">' + esc(p.nazev) + '</option>').join('');
  if (volnePostavy.some((p) => String(p.id) === puvodni)) sel.value = puvodni;
  if (wrap) wrap.style.display = volnePostavy.length ? '' : 'none';
  if (prazdno) prazdno.style.display = volnePostavy.length ? 'none' : '';
}

// --- Obnova stavu na místě (místo location.reload) ---
let obnovaBezi = false, obnovaZnovu = false, obnovaTimer = null;
function planujObnovuStavu(zpozdeni = 120) {
  clearTimeout(obnovaTimer);
  obnovaTimer = setTimeout(obnovitStav, zpozdeni);
}
async function obnovitStav() {
  if (obnovaBezi) { obnovaZnovu = true; return; }
  obnovaBezi = true;
  try {
    const r = await fetch('api/mapa_stav.php?mapa_id=' + MAPA_ID, {cache: 'no-store', credentials: 'same-origin'});
    if (r.ok) aplikujStav(await r.json());
  } catch (err) { /* výpadek sítě — příští událost to zkusí znovu */ }
  obnovaBezi = false;
  if (obnovaZnovu) { obnovaZnovu = false; obnovitStav(); }
}
function aplikujStav(s) {
  tokeny = s.tokeny;
  koloStav = s.kolo;
  iniciativa = s.iniciativa;
  ucastnici = s.ucastnici || [];
  vyzvy = s.vyzvy || [];
  volnePostavy = s.volne_postavy;
  if (Array.isArray(s.kresby)) { kresby = s.kresby; nakreslitKresby(); }
  if (typeof ocistitVyber === 'function') ocistitVyber();
  nakreslitTokeny();
  nakreslitIniciativu();
  naplnitVolnePostavy();
  obnovPanelSpravovat();
  if (denikDock) {
    const naTahu = koloStav && koloStav.aktivni_token_id ? tokeny.find((x) => x.id === koloStav.aktivni_token_id) : null;
    denikDock.tah(naTahu && naTahu.typ_entity === 'postava' ? naTahu.entita_id : null);
  }
}

// --- Panel Spravovat (klik na vlastní token) ---
const inventarCache = new Map();
function klicEntity(typEntity, entitaId) { return typEntity + ':' + entitaId; }

function otevritSpravovat(t) {
  spravovanyToken = {
    typEntity: t.typ_entity,
    entitaId: t.entita_id,
    tokenId: t.id,
    hp: t.hp,
    magenergie: t.magenergie,
    maxMagenergie: t.max_magenergie,
  };
  document.getElementById('spravovatNazev').textContent = t.label;
  if (denikDock && t.typ_entity === 'postava') denikDock.nasleduj(t.entita_id);
  const denikBtn = document.getElementById('spravovatDenik');
  if (denikBtn) denikBtn.style.display = denikDock && t.typ_entity === 'postava' && denikDock.smi(t.entita_id) ? '' : 'none';
  // Vlastní (obecný) token nemá životy, iniciativu, efekty ani inventář.
  const sekceEntita = document.getElementById('sekceEntita');
  if (sekceEntita) sekceEntita.style.display = t.typ_entity === 'obecny' ? 'none' : '';
  obnovVzhledSekci();
  const magRow = document.getElementById('panelMagenergieRow');
  if (magRow) {
    if (spravovanyToken.maxMagenergie !== null) {
      magRow.style.display = '';
      document.getElementById('spravovatMagenergieText').textContent = (spravovanyToken.magenergie ?? 0) + '/' + spravovanyToken.maxMagenergie;
    } else {
      magRow.style.display = 'none';
    }
  }
  // Iniciativa: u postavy se únava a naložení počítají samy (pokud je PJ
  // nevypnul) — ruční zaškrtnutí těchhle řádků se zamkne.
  let autoPozn = false;
  document.querySelectorAll('.iniciativa-bonus-check[data-auto]').forEach((cb) => {
    const zamknout = spravovanyToken.typEntity === 'postava' && POHYB_AUTO[cb.dataset.auto];
    cb.disabled = zamknout;
    if (zamknout) { cb.checked = false; autoPozn = true; }
    cb.parentElement.style.opacity = zamknout ? '.5' : '';
  });
  const pozn = document.getElementById('iniciativaAutoPozn');
  if (pozn) pozn.style.display = autoPozn ? '' : 'none';
  const pjEl = document.getElementById('spravovatPjStav');
  if (pjEl) { pjEl.style.display = 'none'; pjEl.innerHTML = ''; }
  if (t.typ_entity !== 'obecny') nactiInventar();
  if (denikNestvuryEl) {
    denikNestvuryEl.style.display = 'none';
    denikNestvuryEl.textContent = '';
    if (IS_PJ_OR_ADMIN && t.typ_entity === 'nestvura_instance') nactiDenikNestvury(t.id);
  }
  spravovatPanel.classList.add('open');
}
// Po obnově stavu sedí panel k aktuálním datům (životy, magenergie…);
// zmizel-li token (smazaný jiným klientem), panel se zavře.
function obnovPanelSpravovat() {
  if (!spravovanyToken) return;
  const t = tokenData(spravovanyToken.tokenId);
  if (!t) {
    spravovatPanel.classList.remove('open');
    spravovanyToken = null;
    return;
  }
  const hpZmena = spravovanyToken.hp !== t.hp;
  spravovanyToken.hp = t.hp;
  spravovanyToken.magenergie = t.magenergie;
  spravovanyToken.maxMagenergie = t.max_magenergie;
  document.getElementById('spravovatNazev').textContent = t.label;
  const txt = document.getElementById('spravovatMagenergieText');
  if (txt && t.max_magenergie !== null) txt.textContent = (t.magenergie ?? 0) + '/' + t.max_magenergie;
  obnovVzhledSekci();
  vykresliEfektyPanelu(t);
  if (hpZmena) {
    renderInventar(); renderKorist();
    if (IS_PJ_OR_ADMIN && t.typ_entity === 'nestvura_instance' && denikNestvuryEl && denikNestvuryEl.style.display !== 'none'
      && !denikNestvuryEl.contains(document.activeElement)) nactiDenikNestvury(t.id);
  }
}
// Aktivní efekty vybraného tvora v panelu: co boj sám dělá, kolik kol zbývá, PJ efekt může sundat.
function vykresliEfektyPanelu(t) {
  const cont = document.getElementById('spravovatEfekty');
  if (!cont) return;
  const efekty = t.efekty_detail || [];
  cont.textContent = '';
  if (!efekty.length) { cont.style.display = 'none'; return; }
  cont.style.display = '';
  const h = document.createElement('h4');
  h.className = 'panel-h4';
  h.textContent = 'Aktivní efekty';
  cont.appendChild(h);
  efekty.forEach((e) => {
    const r = document.createElement('div');
    r.className = 'efekt-radek';
    const txt = document.createElement('div');
    txt.className = 'efekt-text';
    txt.textContent = popisEfektu(e);
    if (e.text) txt.title = e.text;
    r.appendChild(txt);
    if (IS_PJ_OR_ADMIN) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-ghost efekt-sundat';
      b.textContent = '✕';
      b.title = 'Sundat efekt';
      b.addEventListener('click', () => {
        postJson('api/efekt_odebrat.php', {mapa_id: MAPA_ID, aktivni_efekt_id: e.id}).then((d) => {
          if (d.error) { logLine('Chyba: ' + d.error); return; }
          obnovitStav();
        });
      });
      r.appendChild(b);
    }
    cont.appendChild(r);
  });
}

// --- Deník nestvůry (jen PJ): přehled z bestiáře + čísla pro boj, uložená pro celý druh ve světě ---
// Bestiář je tajný, proto jen PJ; čísla (útoky s ÚČ a útočností, OČ, OZ, velikost, odolnost) PJ doplní jednou
// a hody je berou samy (hra/api/nestvura_denik.php, includes/vtt_nestvury.php).
const denikNestvuryEl = document.getElementById('denikNestvury');
function dnEl(tag, trida, text) {
  const e = document.createElement(tag);
  if (trida) e.className = trida;
  if (text !== undefined) e.textContent = text;
  return e;
}
async function nactiDenikNestvury(tokenId, telo) {
  if (!denikNestvuryEl) return;
  try {
    const r = telo
      ? await postJson('api/nestvura_denik.php', Object.assign({mapa_id: MAPA_ID, token_id: tokenId}, telo))
      : await (await fetch('api/nestvura_denik.php?mapa_id=' + MAPA_ID + '&token_id=' + tokenId, {cache: 'no-store', credentials: 'same-origin'})).json();
    if (!spravovanyToken || spravovanyToken.tokenId !== tokenId) return;
    if (r.error) { denikNestvuryEl.style.display = 'none'; if (telo) logLine('Chyba: ' + r.error); return; }
    vykresliDenikNestvury(tokenId, r);
    if (telo && r.ulozeno_nyni) logLine('Deník nestvůry „' + r.nazev + '“ ' + (telo.akce === 'uloz' ? 'uložen — platí pro celý druh v tomhle světě.' : 'vrácen podle bestiáře.'));
  } catch (err) {
    denikNestvuryEl.style.display = 'none';
  }
}
function vykresliDenikNestvury(tokenId, d) {
  const box = denikNestvuryEl;
  box.textContent = '';
  box.style.display = '';
  const hlava = dnEl('div', 'dn-hlava');
  hlava.append(dnEl('h4', 'panel-h4', 'Deník nestvůry'), dnEl('span', 'dn-stav' + (d.ulozeno ? ' ulozeno' : ''), d.ulozeno ? 'upraveno PJ' : 'podle bestiáře'));
  box.appendChild(hlava);
  // přehled z bestiáře
  const b = d.bestiar || {};
  const prehled = dnEl('div', 'dn-prehled');
  [['Velikost', b.velikost], ['Odolnost', b.odolnost], ['Životaschopnost', b.zivotaschopnost], ['Pohyblivost', b.pohyblivost],
    ['Vytrvalost', b.vytrvalost], ['Inteligence', b.inteligence], ['Charisma', b.charisma], ['Bojovnost', b.bojovnost],
    ['ÚČ (text)', b.uc], ['OČ (text)', b.oc]].forEach(([k, v]) => {
    if (v === null || v === undefined || v === '') return;
    const r = dnEl('div', 'dn-radek');
    r.append(dnEl('span', 'dn-k', k), dnEl('span', 'dn-v', String(v)));
    prehled.appendChild(r);
  });
  if ((d.schopnosti || []).length) {
    const r = dnEl('div', 'dn-radek');
    r.append(dnEl('span', 'dn-k', 'Schopnosti'), dnEl('span', 'dn-v', d.schopnosti.join(', ')));
    prehled.appendChild(r);
  }
  const hp = dnEl('div', 'dn-radek');
  hp.append(dnEl('span', 'dn-k', 'Životy'), dnEl('span', 'dn-v', d.hp.aktualni + '/' + d.hp.max + (d.mez_vyrazeni !== null ? ' · vyřazen od ' + d.mez_vyrazeni + ' a méně' : '')));
  prehled.appendChild(hp);
  box.appendChild(prehled);

  // čísla pro boj
  box.appendChild(dnEl('div', 'dn-podnadpis', 'Čísla pro boj'));
  const nespocteno = new Set(d.nespocteno || []);
  const utokyBox = dnEl('div', 'dn-utoky');
  const pole = (hodnota, min, max, sirka, placeholder, zvyraznit) => {
    const i = dnEl('input', 'input dn-cislo' + (zvyraznit ? ' chybi' : ''));
    i.type = 'number'; i.min = String(min); i.max = String(max);
    i.value = hodnota === null || hodnota === undefined ? '' : String(hodnota);
    if (sirka) i.style.width = sirka;
    if (placeholder) i.placeholder = placeholder;
    i.addEventListener('keydown', (e) => e.stopPropagation());
    return i;
  };
  function pridejUtok(u, zvyraznit) {
    const r = dnEl('div', 'dn-utok');
    const nazev = dnEl('input', 'input dn-nazev'); nazev.type = 'text'; nazev.maxLength = 60; nazev.placeholder = 'útok'; nazev.value = u ? u.nazev : '';
    nazev.addEventListener('keydown', (e) => e.stopPropagation());
    const uc = pole(u ? u.uc : null, 0, 60, '52px', 'ÚČ', zvyraznit);
    const ut = pole(u ? u.utocnost : 0, -20, 20, '52px', 'útoč.', false);
    ut.title = 'Útočnost zbraně (zranění = rozdíl + útočnost)';
    const x = dnEl('button', 'btn btn-ghost dn-x', '×'); x.type = 'button'; x.title = 'Smazat útok';
    x.addEventListener('click', () => r.remove());
    r.append(nazev, uc, ut, x);
    utokyBox.appendChild(r);
  }
  (d.staty.utoky || []).forEach((u) => pridejUtok(u, false));
  if (!(d.staty.utoky || []).length) pridejUtok(null, true);
  box.appendChild(dnEl('div', 'dn-popisek', 'Útoky (název · ÚČ · útočnost) — nestvůra je střídá v uvedeném pořadí'));
  box.appendChild(utokyBox);
  const pridat = dnEl('button', 'btn btn-ghost dn-pridat', '+ útok'); pridat.type = 'button';
  pridat.addEventListener('click', () => pridejUtok(null, false));
  box.appendChild(pridat);
  const rada = dnEl('div', 'dn-pole-radek');
  const mk = (popisek, el, title) => { const w = dnEl('label', 'dn-pole'); w.title = title || ''; w.append(dnEl('span', '', popisek), el); rada.appendChild(w); return el; };
  const oc = mk('OČ', pole(d.staty.oc, 0, 60, '56px', '', nespocteno.has('oc')), 'Obranné číslo (bez štítu a zbraně se dopočítá z oprav v hodu)');
  const oz = mk('OZ', pole(d.staty.oz, -10, 10, '56px', '', false), 'Obrana zbraně — jen když se nestvůra brání zbraní (prázdné = bez oprav)');
  const od = mk('Odolnost', pole(d.staty.odolnost, 0, 99, '56px', '', nespocteno.has('odolnost')), 'Z odolnosti a životů se počítá mez vyřazení a postih pod 1/3 životů');
  const vel = dnEl('select', 'input dn-select' + (nespocteno.has('velikost') ? ' chybi' : ''));
  [['', '—']].concat(['A0', 'A', 'B', 'C', 'D', 'E'].map((v) => [v, v])).forEach(([v, t]) => { const o = dnEl('option', '', t); o.value = v; if ((d.staty.velikost || '') === v) o.selected = true; vel.appendChild(o); });
  mk('Velikost', vel, 'Pro opravu útoku podle Tabulky rozdílu velikostí' + (b.velikost ? ' (bestiář: ' + b.velikost + ')' : ''));
  box.appendChild(rada);
  if (nespocteno.size) {
    box.appendChild(dnEl('div', 'dn-varovani', 'Žlutá pole se z textu bestiáře nedala spočítat — doplň je jednou a platí pro všechny nestvůry „' + d.nazev + '“ v tomhle světě.'));
  }
  const akce = dnEl('div', 'dn-akce');
  const uloz = dnEl('button', 'btn btn-primary', 'Uložit pro všechny „' + d.nazev + '“'); uloz.type = 'button';
  uloz.disabled = !d.lze_ulozit;
  if (!d.lze_ulozit) uloz.title = 'Chybí ruční SQL (tabulka nestvura_staty_sveta) — zatím jen přehled.';
  uloz.addEventListener('click', () => {
    const utoky = Array.from(utokyBox.querySelectorAll('.dn-utok')).map((r) => ({
      nazev: r.querySelector('.dn-nazev').value,
      uc: r.querySelectorAll('.dn-cislo')[0].value,
      utocnost: r.querySelectorAll('.dn-cislo')[1].value,
    }));
    nactiDenikNestvury(tokenId, {akce: 'uloz', staty: {utoky, oc: oc.value, oz: oz.value, odolnost: od.value, velikost: vel.value}});
  });
  akce.appendChild(uloz);
  if (d.ulozeno) {
    const vrat = dnEl('button', 'btn btn-ghost', 'Vrátit podle bestiáře'); vrat.type = 'button';
    vrat.addEventListener('click', () => nactiDenikNestvury(tokenId, {akce: 'vrat'}));
    akce.appendChild(vrat);
  }
  box.appendChild(akce);
}

// Inventář se načítá až pro token, na který někdo klikl (dřív se posílal
// v HTML pro všechny tokeny najednou).
async function nactiInventar() {
  const st = spravovanyToken;
  if (!st) return;
  const klic = klicEntity(st.typEntity, st.entitaId);
  const cont = document.getElementById('spravovatInventar');
  if (cont && !inventarCache.has(klic)) cont.innerHTML = '<p class="note" style="margin:0;">Načítám inventář…</p>';
  try {
    const r = await fetch('api/entita_inventar.php?typ_entity=' + encodeURIComponent(st.typEntity) + '&entita_id=' + st.entitaId, {cache: 'no-store', credentials: 'same-origin'});
    const d = await r.json();
    if (d.polozky) inventarCache.set(klic, d.polozky); else inventarCache.delete(klic);
    pjStavCache.set(klic, d.pj_stav || null);
  } catch (err) { inventarCache.delete(klic); }
  if (spravovanyToken && klicEntity(spravovanyToken.typEntity, spravovanyToken.entitaId) === klic) {
    renderInventar();
    renderKorist();
    renderPjStav();
  }
}
// PJ vidí u postavy, co únava a naložení právě mění, a u kterých dovedností
// obtížnost určuje on (hra/api/entita_inventar.php → pj_stav).
const pjStavCache = new Map();
function renderPjStav() {
  const el = document.getElementById('spravovatPjStav');
  if (!el || !spravovanyToken) return;
  const st = pjStavCache.get(klicEntity(spravovanyToken.typEntity, spravovanyToken.entitaId));
  if (!IS_PJ_OR_ADMIN || !st || !st.ma_vliv) { el.style.display = 'none'; el.innerHTML = ''; return; }
  el.style.display = '';
  el.innerHTML = '<h4 class="panel-h4">Únava a naložení</h4><ul>' + st.radky.map((r) => '<li>' + esc(r) + '</li>').join('') + '</ul>'
    + (st.pj_rozhoduje && st.pj_rozhoduje.length ? '<p class="note" style="margin:4px 0 0;"><em>Obtížnost určuješ ty:</em> ' + esc(st.pj_rozhoduje.join(' · ')) + '.</p>' : '');
}

// --- Vstup: myš, dotyk i pero přes Pointer Events ---
// Jedna cesta pro všechna zařízení. Jeden prst/myš dělá akci podle nástroje
// (tah tokenu, měření, kreslení mlhy…), na prázdném místě posouvá mapu.
// Dva prsty vždy přibližují a posouvají; kolečko myši přibližuje; pravé či
// prostřední tlačítko a mezerník posouvají mapu v jakémkoli nástroji.
const PRAH_MYS = 5, PRAH_DOTYK = 10;
const pointery = new Map();
let akce = null;
let mezernikDrzen = false;
let polozitVic = false;

function bodMapy(e) { return kamera.naMapu(e.clientX, e.clientY); }
function jeVstupniPole(el) {
  return !!el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable);
}

function zacitAkci(e, pinEl) {
  const m = bodMapy(e);
  akce = {
    typ: 'nic', start: {x: e.clientX, y: e.clientY}, pointerId: e.pointerId, posunuto: false,
    prah: e.pointerType === 'touch' ? PRAH_DOTYK : PRAH_MYS, tlacitko: e.button, panRef: null,
  };
  const elPod = e.target.closest('.vtt-token');
  akce.cilToken = elPod ? tokenData(parseInt(elPod.dataset.id, 10)) : null;
  if (pinEl) { akce.typ = 'pin'; akce.pin = pinEl; return; }
  // Plánování akce v souboji (Krok, Otočka, Útok): klik vybírá hex, tah posouvá mapu.
  if (souboj && souboj.planuje() && e.button === 0) { akce.typ = 'souboj'; return; }
  if (e.target.closest('#rotUchyt') && vybrane.size === 1) {
    akce.typ = 'otoceni';
    akce.token = tokenData(Array.from(vybrane)[0]);
    akce.smerPred = akce.token ? akce.token.smer : 0;
    if (akce.token) return;
  }
  if (e.button === 1 || e.button === 2 || mezernikDrzen || currentTool === 'ruka') {
    akce.typ = 'pan';
    akce.kontext = e.button === 2;
    return;
  }
  if (currentTool === 'kresleni') {
    akce.typ = 'kresleni';
    akce.body = [kresbaBodSnap(m, kresbaNastaveni.tvar)];
    akce.prvni = m;
    return;
  }
  if (currentTool === 'ruler') {
    akce.typ = 'ruler';
    rulerStart = m;
    drawRuler(m, m);
    return;
  }
  if (currentTool === 'mlha') {
    akce.typ = 'mlha';
    if (mlhaRezimObdelnik) {
      mlhaObdelnikStart = m;
    } else {
      mlhaKreslim = true;
      mlhaPosledniBunka = null;
      mlhaNakresliBod(m);
    }
    return;
  }
  if (currentTool === 'zed' || currentTool === 'ping' || currentTool === 'pin') {
    akce.typ = 'klik'; // klik = nástroj, tah = posun mapy
    return;
  }
  const el = e.target.closest('.vtt-token');
  const t = el ? tokenData(parseInt(el.dataset.id, 10)) : null;
  akce.cilToken = t;
  if (t) {
    akce.typ = 'token';
    akce.token = t;
    akce.offX = m.x - t.x;
    akce.offY = m.y - t.y;
    // data o tahu/vlastnictví jsou jen pomůcka pro UI — server
    // (hra/api/token_presun.php) je autoritativní nezávisle na tom.
    akce.smiTahnout = t.owned && t.na_tahu && !t.zamceno && volnyPohyb();
    // Shift/Ctrl (nebo nástroj "Výběr rámečkem" na dotyku) přidává/odebírá
    // token z výběru; obyčejný stisk na ještě nevybraný token vybere jen jeho.
    akce.modifikator = e.shiftKey || e.ctrlKey || e.metaKey || currentTool === 'vyber';
    if (!akce.modifikator && !vybrane.has(t.id)) nastavVyber([t.id]);
    naplanujDlouhyStisk(e);
    return;
  }
  // Prázdné místo: s Shiftem (nebo v nástroji "Výběr rámečkem") se táhne
  // výběrový rámeček, jinak se posouvá mapa.
  if (currentTool === 'vyber' || e.shiftKey || e.ctrlKey || e.metaKey) {
    akce.typ = 'ramecek';
    akce.start0 = m;
    akce.pridat = e.shiftKey || e.ctrlKey || e.metaKey;
    return;
  }
  akce.typ = 'prazdno';
  naplanujDlouhyStisk(e);
}

// Dlouhé stisknutí prstem = kontextové menu (jako pravé tlačítko myši).
let dlouhyStiskTimer = null;
function zrusDlouhyStisk() { clearTimeout(dlouhyStiskTimer); dlouhyStiskTimer = null; }
function naplanujDlouhyStisk(e) {
  zrusDlouhyStisk();
  if (e.pointerType === 'mouse') return;
  const id = e.pointerId, cx = e.clientX, cy = e.clientY;
  dlouhyStiskTimer = setTimeout(() => {
    if (!akce || akce.pointerId !== id || akce.posunuto || gesto.aktivni()) return;
    akce.kontextOtevren = true;
    otevritKontextMenu(cx, cy, akce.cilToken || null);
  }, 550);
}

// Posouvá mapu o rozdíl od posledního zpracovaného bodu; začne až po
// překročení prahu (aby klik a třes prstu mapu nehýbaly).
function panAkce(e) {
  if (!akce.posunuto) return;
  const ref = akce.panRef || akce.start;
  kamera.posun(e.clientX - ref.x, e.clientY - ref.y);
  akce.panRef = {x: e.clientX, y: e.clientY};
}

function zrusitAkci() {
  zrusDlouhyStisk();
  if (!akce) return;
  const a = akce;
  akce = null;
  if (a.typ === 'ramecek' && rulerSvg) rulerSvg.innerHTML = '';
  if (a.typ === 'token' && a.posunuto && a.smiTahnout) {
    (a.skupina || [a.token]).forEach((m) => {
      const el = tokenEl.get(m.id);
      if (el) { el.style.left = m.x + 'px'; el.style.top = m.y + 'px'; el.classList.remove('tahnuty'); }
      drzeneTokeny.delete(m.id);
    });
  }
  if (a.typ === 'ruler' || a.typ === 'kresleni') { rulerStart = null; if (rulerSvg) rulerSvg.innerHTML = ''; }
  if (a.typ === 'mlha') { mlhaKreslim = false; mlhaObdelnikStart = null; if (rulerSvg) rulerSvg.innerHTML = ''; }
}

viewport.addEventListener('pointerdown', (e) => {
  // Piny si klik vyřizují samy (tlačítko); výjimka je pin v editaci, který
  // se táhne po mapě.
  const pin = e.target.closest('.mapa-bod-marker');
  const pinTazeny = !!pin && pinEditujiId !== null && parseInt(pin.dataset.bodId, 10) === pinEditujiId;
  if (pin && !pinTazeny) return;
  if (e.pointerType === 'mouse' && e.button !== 0 && e.button !== 1 && e.button !== 2) return;
  pointery.set(e.pointerId, {x: e.clientX, y: e.clientY});
  try { viewport.setPointerCapture(e.pointerId); } catch (err) { /* nevadí */ }
  if (pointery.size === 2) {
    zrusitAkci();
    const [a, b] = Array.from(pointery.values());
    gesto.start(a, b);
    return;
  }
  if (pointery.size > 2) return;
  zacitAkci(e, pinTazeny ? pin : null);
  if (e.button === 1) e.preventDefault(); // prostřední tlačítko jinak spustí automatický posun prohlížeče
});

viewport.addEventListener('pointermove', (e) => {
  const p = pointery.get(e.pointerId);
  if (!p) return;
  p.x = e.clientX;
  p.y = e.clientY;
  if (gesto.aktivni()) {
    const body = Array.from(pointery.values());
    if (body.length >= 2) gesto.pohyb(body[0], body[1], viewport);
    return;
  }
  if (!akce || akce.pointerId !== e.pointerId) return;
  const od = Math.hypot(e.clientX - akce.start.x, e.clientY - akce.start.y);
  if (!akce.posunuto && od > akce.prah) { akce.posunuto = true; zrusDlouhyStisk(); }
  const m = bodMapy(e);
  switch (akce.typ) {
    case 'pan':
    case 'klik':
    case 'prazdno':
    case 'souboj':
      panAkce(e);
      break;
    case 'pin': {
      akce.posunuto = akce.posunuto || od > 2;
      akce.pin.style.left = Math.round(m.x) + 'px';
      akce.pin.style.top = Math.round(m.y) + 'px';
      break;
    }
    case 'ramecek': {
      if (!akce.posunuto || !rulerSvg) break;
      const a0 = akce.start0;
      const x = Math.min(a0.x, m.x), y = Math.min(a0.y, m.y), w = Math.abs(m.x - a0.x), h = Math.abs(m.y - a0.y);
      rulerSvg.innerHTML = '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h
        + '" fill="rgba(120,190,255,0.15)" stroke="#78beff" stroke-width="1.5" stroke-dasharray="6 4" vector-effect="non-scaling-stroke"/>';
      break;
    }
    case 'otoceni': {
      const t = akce.token;
      if (!t) break;
      const uhel = Math.atan2(m.y - t.y, m.x - t.x) * 180 / Math.PI;
      const krok = e.shiftKey ? 1 : krokOtoceni();
      const posun = e.shiftKey ? 0 : posunOtoceni(t);
      t.smer = (((Math.round((uhel - posun) / krok) * krok + posun) % 360) + 360) % 360;
      nakreslitToken(t);
      aktualizujRotacniUchyt();
      break;
    }
    case 'kresleni':
      if (akce.posunuto || kresbaNastaveni.tvar === 'cara') nahledKresby(akce, m);
      break;
    case 'ruler':
      if (rulerStart) drawRuler(rulerStart, m);
      break;
    case 'mlha':
      if (mlhaRezimObdelnik) {
        if (!mlhaObdelnikStart || !rulerSvg) break;
        const x = Math.min(mlhaObdelnikStart.x, m.x), y = Math.min(mlhaObdelnikStart.y, m.y);
        const sw = Math.abs(m.x - mlhaObdelnikStart.x), sh = Math.abs(m.y - mlhaObdelnikStart.y);
        rulerSvg.innerHTML = '<rect x="' + x + '" y="' + y + '" width="' + sw + '" height="' + sh
          + '" fill="rgba(255,209,102,0.15)" stroke="#ffd166" stroke-width="2" stroke-dasharray="6 4" vector-effect="non-scaling-stroke"/>';
      } else if (mlhaKreslim) {
        mlhaNakresliBod(m);
      }
      break;
    case 'token': {
      if (!akce.smiTahnout || akce.modifikator) { panAkce(e); break; }
      if (!akce.posunuto) break;
      const t = akce.token;
      // Táhne se celý výběr (jen tokeny, kterými smíš hýbat); netažený token
      // se táhne sám.
      if (!akce.skupina) {
        const ve = vybrane.has(t.id) ? Array.from(vybrane).map(tokenData).filter((x) => x && x.owned && x.na_tahu && !x.zamceno && volnyPohyb()) : [t];
        akce.skupina = ve.some((x) => x.id === t.id) ? ve : [t];
      }
      let nx, ny;
      if (GRID_PX > 0) {
        // S gridem token vždycky sedí na střed buňky — místo úchopu
        // uvnitř tokenu se tu záměrně ignoruje.
        const c = gridCellCenter(m.x, m.y);
        nx = c.x; ny = c.y;
      } else {
        nx = m.x - akce.offX; ny = m.y - akce.offY;
      }
      const dx = Math.round(nx) - t.x, dy = Math.round(ny) - t.y;
      akce.dx = dx;
      akce.dy = dy;
      akce.skupina.forEach((c) => {
        drzeneTokeny.add(c.id);
        const el = tokenEl.get(c.id);
        if (el) { el.style.left = (c.x + dx) + 'px'; el.style.top = (c.y + dy) + 'px'; el.classList.add('tahnuty'); }
      });
      aktualizujRotacniUchyt();
      break;
    }
    default:
      break;
  }
});

function ulozPresunTokenu(t, x, y) {
  drzeneTokeny.add(t.id);
  postJson('api/token_presun.php', {token_id: t.id, x, y})
    .then((d) => {
      if (d.error) { logLine('Chyba přesunu: ' + d.error); obnovitStav(); return; }
      t.x = x; t.y = y;
    })
    .catch(() => { logLine('Přesun se nepodařilo odeslat.'); obnovitStav(); })
    .finally(() => {
      drzeneTokeny.delete(t.id);
      const el = tokenEl.get(t.id);
      if (el) el.classList.remove('tahnuty');
    });
}

// Společný přesun skupiny: jedna událost, "Krok zpět" vrátí všechny naráz.
// Bez zapnutých nových funkcí (ruční SQL) se jede po jednom.
function ulozPresunSkupiny(skupina, dx, dy) {
  if (skupina.length === 1) { ulozPresunTokenu(skupina[0], skupina[0].x + dx, skupina[0].y + dy); return; }
  const presuny = skupina.map((c) => ({token_id: c.id, x: c.x + dx, y: c.y + dy}));
  const hotovo = () => skupina.forEach((c) => {
    drzeneTokeny.delete(c.id);
    const el = tokenEl.get(c.id);
    if (el) el.classList.remove('tahnuty');
  });
  if (!FUNKCE.udalosti) {
    presuny.forEach((p, i) => ulozPresunTokenu(skupina[i], p.x, p.y));
    return;
  }
  postJson('api/token_presun_davka.php', {tokeny: presuny})
    .then((d) => {
      if (d.error) { logLine('Chyba přesunu skupiny: ' + d.error); obnovitStav(); return; }
      skupina.forEach((c, i) => { c.x = presuny[i].x; c.y = presuny[i].y; });
    })
    .catch(() => { logLine('Přesun skupiny se nepodařilo odeslat.'); obnovitStav(); })
    .finally(hotovo);
}

// Klik (ne tah) na mapu — nástroje ping / zeď / pin a pokládání tokenu.
function klikNaMapu(m) {
  if (currentTool === 'ping') {
    const x = Math.round(m.x), y = Math.round(m.y);
    postJson('api/ping.php', {svet_id: SVET_ID, mapa_id: MAPA_ID, x, y})
      .then((d) => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
        zobrazPing(x, y, d.jmeno || '');
      });
    return;
  }
  if (currentTool === 'zed') {
    // Výběr existující zdi jen když zrovna nerozkreslujeme novou cestu —
    // uprostřed klikání bodů se klik vždycky bere jako další bod, ať
    // omylem nevybereš/nesmažeš jinou zeď, co ti leží v cestě.
    if (zedRozpracovaneBody.length === 0) {
      const blizkaZed = najdiZedBlizkoBodu(m.x, m.y);
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
    const bod = snapPoint(m.x, m.y, zedSnapEnabled());
    zedRozpracovaneBody.push(bod);
    aktualizovatZedUlozitTlacitko();
    drawZedRozpracovanouCestu(bod);
    return;
  }
  if (currentTool === 'pin') {
    // Vždycky znovu otevře s čerstvými souřadnicemi (ne toggle) — dva
    // kliky po sobě by jinak podruhé jen zavřely formulář, ne
    // přepsaly souřadnice na nový bod.
    const xEl = document.getElementById('pinNovyX'), yEl = document.getElementById('pinNovyY');
    if (xEl) xEl.value = Math.round(m.x);
    if (yEl) yEl.value = Math.round(m.y);
    closeAllPopovers();
    const pop = document.getElementById('popover-pin-novy');
    if (pop && toolButtons.pin) {
      umistitPopoverKBodu(pop, toolButtons.pin.getBoundingClientRect());
      toolButtons.pin.classList.add('active');
    }
    return;
  }
  if (currentTool !== 'select') return;
  polozitToken(m);
}

// Vlastní token (NPC bez statistik, překážka, předmět) — pole v popoveru "Přidat token".
const obecnySekceEl = document.getElementById('obecnySekce');
const obecnyNazevEl = document.getElementById('obecnyNazev');
const obecnaBarvaEl = document.getElementById('obecnaBarva');
const obecnaVelikostEl = document.getElementById('obecnaVelikost');
if (obecnySekceEl) {
  obecnySekceEl.style.display = FUNKCE.obecny ? '' : 'none';
  obecnaBarvaEl.innerHTML = '<option value="">výchozí barva</option>' + BARVY_TOKENU.map(([kod, nazev]) => '<option value="' + kod + '">' + nazev + '</option>').join('');
  // Co je vyplněné, to se položí — ostatní výběry se proto vzájemně vyprázdní.
  const vyprazdnit = (...els) => els.forEach((el) => { if (el) { if (el.tagName === 'SELECT') el.selectedIndex = el.size > 1 ? -1 : 0; else el.value = ''; } });
  obecnyNazevEl.addEventListener('input', () => { if (obecnyNazevEl.value.trim()) vyprazdnit(document.getElementById('novaPostavaSelect'), document.getElementById('novaNestvuraSelect')); });
  ['novaPostavaSelect', 'novaNestvuraSelect'].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', () => { if (el.value) vyprazdnit(obecnyNazevEl, id === 'novaPostavaSelect' ? document.getElementById('novaNestvuraSelect') : document.getElementById('novaPostavaSelect')); });
  });
}

// Položí vybranou postavu / nestvůru / vlastní token z popoveru "Přidat token" na mapu.
function polozitToken(m) {
  const postavaSel = document.getElementById('novaPostavaSelect');
  const nestvuraSel = document.getElementById('novaNestvuraSelect');
  const stred = GRID_PX > 0 ? gridCellCenter(m.x, m.y) : m;
  const x = Math.round(stred.x), y = Math.round(stred.y);
  let tel = null;
  if (postavaSel && postavaSel.value) {
    tel = {mapa_id: MAPA_ID, typ_entity: 'postava', postava_id: parseInt(postavaSel.value, 10), x, y};
    if (!polozitVic) postavaSel.value = '';
  } else if (nestvuraSel && nestvuraSel.value) {
    tel = {mapa_id: MAPA_ID, typ_entity: 'nestvura', nestvura_id: parseInt(nestvuraSel.value, 10), x, y};
    if (!polozitVic) nestvuraSel.selectedIndex = -1;
  } else if (obecnyNazevEl && obecnyNazevEl.value.trim() !== '') {
    tel = {
      mapa_id: MAPA_ID, typ_entity: 'obecny', nazev: obecnyNazevEl.value.trim(), x, y,
      barva: obecnaBarvaEl && obecnaBarvaEl.value ? obecnaBarvaEl.value : null,
      velikost: obecnaVelikostEl ? parseFloat(obecnaVelikostEl.value) : 1,
    };
    if (!polozitVic) obecnyNazevEl.value = '';
  }
  if (!tel) { if (typeof zrusitVyber === 'function') zrusitVyber(); return; }
  postJson('api/token_pridat.php', tel).then((d) => {
    if (d.error) { logLine('Chyba přidání: ' + d.error); return; }
    obnovitStav();
  });
}

function dokoncitAkci(a, e) {
  switch (a.typ) {
    case 'souboj':
      if (!a.posunuto && souboj) souboj.klik(bodMapy(e));
      break;
    case 'pin':
      // Jen uloží novou pozici do skrytých polí formuláře — zápis do DB
      // proběhne až kliknutím na "Uložit změny", spolu s ostatními
      // upravovanými poli (stejný vzor jako zbytek téhle edit formy).
      if (a.posunuto) {
        document.getElementById('pinUpravitX').value = parseInt(a.pin.style.left, 10);
        document.getElementById('pinUpravitY').value = parseInt(a.pin.style.top, 10);
      }
      break;
    case 'kresleni':
      if (a.posunuto || kresbaNastaveni.tvar === 'text') dokoncitKresbu(a, bodMapy(e));
      else if (rulerSvg) rulerSvg.innerHTML = '';
      break;
    case 'ruler':
      rulerStart = null;
      clearRulerSoon();
      break;
    case 'mlha':
      if (mlhaRezimObdelnik) {
        if (!mlhaObdelnikStart) break;
        const m = bodMapy(e);
        const start = mlhaObdelnikStart;
        mlhaObdelnikStart = null;
        if (rulerSvg) rulerSvg.innerHTML = '';
        postJson('api/mlha_uprava.php', {
          mapa_id: MAPA_ID, ucet_id: mlhaNahledUcetId,
          x: start.x, y: start.y, x2: m.x, y2: m.y, odhalit: mlhaKreslimOdhalit,
        }).then((d) => { if (d.error) { logLine('Chyba: ' + d.error); return; } osvezitMlhu(); });
      } else {
        mlhaKreslim = false;
      }
      break;
    case 'otoceni':
      if (a.token && a.token.smer !== a.smerPred) {
        upravToken(a.token, {smer: a.token.smer});
      }
      break;
    case 'token': {
      const t = a.token;
      if (a.kontextOtevren) break;
      if (a.smiTahnout && !a.modifikator && a.posunuto) {
        const skupina = a.skupina || [t];
        if (a.dx || a.dy) ulozPresunSkupiny(skupina, a.dx, a.dy);
        else {
          skupina.forEach((c) => {
            drzeneTokeny.delete(c.id);
            const el = tokenEl.get(c.id);
            if (el) { el.style.left = c.x + 'px'; el.style.top = c.y + 'px'; el.classList.remove('tahnuty'); }
          });
        }
      } else if (!a.posunuto) {
        if (a.modifikator) {
          // přidat/odebrat z výběru
          if (vybrane.has(t.id)) vybrane.delete(t.id); else vybrane.add(t.id);
          aktualizujVyber();
        } else {
          nastavVyber([t.id]);
          if (t.owned && t.na_tahu) otevritSpravovat(t);
        }
      }
      break;
    }
    case 'ramecek': {
      const m = bodMapy(e);
      if (rulerSvg) rulerSvg.innerHTML = '';
      if (!a.posunuto) { if (!a.pridat) zrusitVyber(); break; }
      const x1 = Math.min(a.start0.x, m.x), x2 = Math.max(a.start0.x, m.x);
      const y1 = Math.min(a.start0.y, m.y), y2 = Math.max(a.start0.y, m.y);
      const uvnitr = tokeny.filter((t) => t.x >= x1 && t.x <= x2 && t.y >= y1 && t.y <= y2).map((t) => t.id);
      nastavVyber(a.pridat ? Array.from(new Set([...vybrane, ...uvnitr])) : uvnitr);
      break;
    }
    case 'pan':
      if (a.kontext && !a.posunuto) otevritKontextMenu(e.clientX, e.clientY, a.cilToken || null);
      break;
    case 'klik':
    case 'prazdno':
      if (!a.posunuto && !a.kontextOtevren) klikNaMapu(bodMapy(e));
      break;
    default:
      break;
  }
}

function konecPointeru(e, zruseno) {
  zrusDlouhyStisk();
  pointery.delete(e.pointerId);
  try { viewport.releasePointerCapture(e.pointerId); } catch (err) { /* nevadí */ }
  if (gesto.aktivni()) {
    if (pointery.size < 2) gesto.konec();
    akce = null; // zbylý prst po gestu nezačíná novou akci
    return;
  }
  if (!akce || akce.pointerId !== e.pointerId) return;
  const a = akce;
  akce = null;
  if (zruseno) {
    akce = a;
    zrusitAkci();
    return;
  }
  dokoncitAkci(a, e);
}
viewport.addEventListener('pointerup', (e) => konecPointeru(e, false));
viewport.addEventListener('pointercancel', (e) => konecPointeru(e, true));
viewport.addEventListener('contextmenu', (e) => e.preventDefault());

viewport.addEventListener('wheel', (e) => {
  e.preventDefault();
  let dy = e.deltaY;
  if (e.deltaMode === 1) dy *= 16; else if (e.deltaMode === 2) dy *= 400;
  if (e.shiftKey && !e.ctrlKey) {
    kamera.posun(-dy, 0);
    return;
  }
  kamera.zoomKolem(e.clientX, e.clientY, Math.exp(-dy * (e.ctrlKey ? 0.01 : 0.0018)));
}, {passive: false});

window.addEventListener('keydown', (e) => {
  if (jeVstupniPole(e.target)) return;
  if (e.code === 'Space') {
    mezernikDrzen = true;
    viewport.classList.add('pan-ready');
    e.preventDefault();
  } else if (e.key === '+' || e.key === '=') {
    kamera.zoomStred(1.25);
  } else if (e.key === '-') {
    kamera.zoomStred(0.8);
  } else if (e.key === '0') {
    kamera.prizpusobit();
  } else if ((e.key === 'q' || e.key === 'Q' || e.key === 'e' || e.key === 'E') && vybrane.size >= 1 && !e.ctrlKey && !e.metaKey) {
    const smer = (e.key === 'q' || e.key === 'Q') ? -krokOtoceni() : krokOtoceni();
    Array.from(vybrane).map(tokenData).filter(Boolean).forEach((t) => otocitTokenO(t, smer));
  }
});
window.addEventListener('keyup', (e) => {
  if (e.code === 'Space') {
    mezernikDrzen = false;
    viewport.classList.remove('pan-ready');
  }
});
window.addEventListener('blur', () => { mezernikDrzen = false; viewport.classList.remove('pan-ready'); });
window.addEventListener('resize', () => kamera.obnovit());

// --- Piny (na libovolné mapě od fáze 2, viz MAPA_BODY/popover-pin-*) ---
let pinVybranyId = null;
function otevriPinInfo(bod) {
  pinVybranyId = bod.id;
  document.getElementById('pinInfoNazev').textContent = bod.nazev;
  const typLabel = {
    mesto: 'Město', vesnice: 'Vesnice', tajne_misto: 'Tajné místo',
    budova: 'Budova', mistnost: 'Místnost', sklep: 'Sklep / dungeon', prechod: 'Přechod',
    jine: 'Jiné místo',
  }[bod.typ] || bod.typ;
  document.getElementById('pinInfoTyp').textContent = typLabel + (IS_PJ_OR_ADMIN && !bod.viditelny_hracum ? ' — skryté hráčům' : '');
  document.getElementById('pinInfoPopis').textContent = bod.popis || '';
  const npcWrap = document.getElementById('pinInfoNpcWrap');
  const npcList = document.getElementById('pinInfoNpcList');
  if (bod.npc && bod.npc.length) {
    npcWrap.style.display = '';
    npcList.innerHTML = bod.npc.map(n => {
      const rp = [n.rasa_nazev, n.povolani_nazev].filter(Boolean).join(' / ');
      return '<li>' + esc(n.nazev) + (rp ? ' <span style="opacity:.65;">(' + esc(rp) + ', ' + esc(n.uroven) + '. úr.)</span>' : '') + '</li>';
    }).join('');
  } else {
    npcWrap.style.display = 'none';
  }
  const otevritBtn = document.getElementById('pinInfoOtevritBtn');
  const aktivniBtn = document.getElementById('pinInfoAktivniBtn');
  const cestovatBtn = document.getElementById('pinInfoCestovatBtn');
  const upravitBtn = document.getElementById('pinInfoUpravitBtn');
  const smazatBtn = document.getElementById('pinInfoSmazatBtn');
  if (bod.cilova_mapa_id) {
    otevritBtn.style.display = '';
    otevritBtn.href = 'mapa.php?id=' + bod.cilova_mapa_id;
    aktivniBtn.style.display = IS_PJ_OR_ADMIN ? '' : 'none';
  } else {
    otevritBtn.style.display = 'none';
    aktivniBtn.style.display = 'none';
  }
  cestovatBtn.style.display = IS_PJ_OR_ADMIN ? '' : 'none';
  upravitBtn.style.display = IS_PJ_OR_ADMIN ? '' : 'none';
  smazatBtn.style.display = IS_PJ_OR_ADMIN ? '' : 'none';
  const btn = document.querySelector('.mapa-bod-marker[data-bod-id="' + bod.id + '"]');
  const pop = document.getElementById('popover-pin-info');
  if (pop && btn) {
    closeAllPopovers();
    umistitPopoverKBodu(pop, btn.getBoundingClientRect());
  }
}
document.querySelectorAll('.mapa-bod-marker').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    // V editačním módu klik na TENHLE pin už jen táhne (viz mousedown/
    // move/up výš) — nemá znovu přepínat na info popover a tím
    // nechtěně zavírat rozdělaný formulář úprav.
    if (pinEditujiId !== null && parseInt(btn.dataset.bodId, 10) === pinEditujiId) return;
    const bod = MAPA_BODY.find(b => b.id === parseInt(btn.dataset.bodId, 10));
    if (bod) otevriPinInfo(bod);
  });
});
const pinInfoAktivniBtn = document.getElementById('pinInfoAktivniBtn');
if (pinInfoAktivniBtn) {
  pinInfoAktivniBtn.addEventListener('click', () => {
    const bod = MAPA_BODY.find(b => b.id === pinVybranyId);
    if (!bod || !bod.cilova_mapa_id) return;
    postJson('api/aktivni_mapa_nastavit.php', {mapa_id: bod.cilova_mapa_id})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } logLine('Aktivní mapa nastavena na: ' + bod.nazev); });
  });
}
const pinInfoCestovatBtn = document.getElementById('pinInfoCestovatBtn');
if (pinInfoCestovatBtn) {
  pinInfoCestovatBtn.addEventListener('click', () => {
    const bod = MAPA_BODY.find(b => b.id === pinVybranyId);
    if (!bod) return;
    const vstup = prompt('Kolik dní cesta na „' + bod.nazev + '“ zabrala?', '1');
    if (vstup === null) return;
    const dny = parseInt(vstup, 10);
    if (isNaN(dny) || dny < 0) { logLine('Zadej nezáporné celé číslo dní.'); return; }
    postJson('api/pin_cestovat.php', {bod_id: bod.id, dny})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        logLine('Cesta na „' + bod.nazev + '“ — herní čas posunut o ' + dny + ' ' + (dny === 1 ? 'den' : (dny < 5 ? 'dny' : 'dní')) + '.');
      });
  });
}
const pinInfoUpravitBtn = document.getElementById('pinInfoUpravitBtn');
if (pinInfoUpravitBtn) {
  pinInfoUpravitBtn.addEventListener('click', () => {
    const bod = MAPA_BODY.find(b => b.id === pinVybranyId);
    if (!bod) return;
    document.getElementById('pinUpravitId').value = bod.id;
    document.getElementById('pinUpravitX').value = bod.x;
    document.getElementById('pinUpravitY').value = bod.y;
    document.getElementById('pinUpravitNazev').value = bod.nazev;
    document.getElementById('pinUpravitTyp').value = bod.typ;
    document.getElementById('pinUpravitCilovaMapa').value = bod.cilova_mapa_id || '';
    document.getElementById('pinUpravitPopis').value = bod.popis || '';
    document.getElementById('pinUpravitViditelny').checked = !!bod.viditelny_hracum;
    closeAllPopovers();
    const pop = document.getElementById('popover-pin-upravit');
    const zdroj = document.querySelector('.mapa-bod-marker[data-bod-id="' + bod.id + '"]');
    if (pop && zdroj) {
      umistitPopoverKBodu(pop, zdroj.getBoundingClientRect());
      pinEditujiId = bod.id;
      zdroj.classList.add('pin-editing');
    }
  });
}
const pinUpravitUlozitBtn = document.getElementById('pinUpravitUlozitBtn');
if (pinUpravitUlozitBtn) {
  pinUpravitUlozitBtn.addEventListener('click', () => {
    const id = parseInt(document.getElementById('pinUpravitId').value, 10);
    const nazev = document.getElementById('pinUpravitNazev').value.trim();
    if (!nazev) { logLine('Pin musí mít název.'); return; }
    const typ = document.getElementById('pinUpravitTyp').value;
    const cilovaMapaEl = document.getElementById('pinUpravitCilovaMapa');
    const cilovaMapaId = cilovaMapaEl.value ? parseInt(cilovaMapaEl.value, 10) : null;
    const popis = document.getElementById('pinUpravitPopis').value.trim();
    const viditelnyHracum = document.getElementById('pinUpravitViditelny').checked;
    const x = parseInt(document.getElementById('pinUpravitX').value, 10);
    const y = parseInt(document.getElementById('pinUpravitY').value, 10);
    postJson('api/mapa_bod_upravit.php', {id, nazev, typ, cilova_mapa_id: cilovaMapaId, popis, viditelny_hracum: viditelnyHracum, x, y})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        const bod = MAPA_BODY.find(b => b.id === id);
        if (bod) {
          Object.assign(bod, {nazev, typ, cilova_mapa_id: cilovaMapaId, popis, viditelny_hracum: viditelnyHracum, x, y});
        }
        const btn = document.querySelector('.mapa-bod-marker[data-bod-id="' + id + '"]');
        if (btn) {
          btn.style.left = x + 'px';
          btn.style.top = y + 'px';
          btn.title = nazev;
          btn.style.opacity = viditelnyHracum ? '' : '.6';
          const tmpl = document.querySelector('#pinIconTemplates [data-typ="' + typ + '"]');
          btn.innerHTML = tmpl ? tmpl.innerHTML : '';
        }
        closeAllPopovers();
        logLine('Pin „' + nazev + '“ upraven.');
      });
  });
}
const pinInfoSmazatBtn = document.getElementById('pinInfoSmazatBtn');
if (pinInfoSmazatBtn) {
  pinInfoSmazatBtn.addEventListener('click', () => {
    if (!pinVybranyId || !confirm('Smazat tenhle pin?')) return;
    postJson('api/mapa_bod_smazat.php', {id: pinVybranyId})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        const btn = document.querySelector('.mapa-bod-marker[data-bod-id="' + pinVybranyId + '"]');
        if (btn) btn.remove();
        closeAllPopovers();
      });
  });
}
const pinNovyUlozitBtn = document.getElementById('pinNovyUlozitBtn');
if (pinNovyUlozitBtn) {
  pinNovyUlozitBtn.addEventListener('click', () => {
    const nazev = document.getElementById('pinNovyNazev').value.trim();
    if (!nazev) { logLine('Pin musí mít název.'); return; }
    const typ = document.getElementById('pinNovyTyp').value;
    const cilovaMapaEl = document.getElementById('pinNovyCilovaMapa');
    const cilovaMapaId = cilovaMapaEl.value ? parseInt(cilovaMapaEl.value, 10) : null;
    const popis = document.getElementById('pinNovyPopis').value.trim();
    const viditelnyHracum = document.getElementById('pinNovyViditelny').checked;
    const x = parseInt(document.getElementById('pinNovyX').value, 10);
    const y = parseInt(document.getElementById('pinNovyY').value, 10);
    postJson('api/mapa_bod_pridat.php', {
      mapa_id: MAPA_ID, nazev, typ, x, y, cilova_mapa_id: cilovaMapaId, popis, viditelny_hracum: viditelnyHracum,
    }).then(d => {
      if (d.error) { logLine('Chyba: ' + d.error); return; }
      const bod = {id: d.id, nazev, typ, x, y, cilova_mapa_id: cilovaMapaId, popis, viditelny_hracum: viditelnyHracum, npc: []};
      MAPA_BODY.push(bod);
      const layer = document.getElementById('mapaBodyLayer');
      if (layer) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mapa-bod-marker';
        btn.dataset.bodId = String(bod.id);
        btn.title = nazev;
        btn.style.cssText = 'position:absolute;left:' + x + 'px;top:' + y + 'px;pointer-events:auto;background:none;border:none;cursor:pointer;padding:0;' + (viditelnyHracum ? '' : 'opacity:.6;');
        const tmpl = document.querySelector('#pinIconTemplates [data-typ="' + typ + '"]');
        btn.innerHTML = tmpl ? tmpl.innerHTML : '';
        btn.addEventListener('click', (e) => { e.stopPropagation(); otevriPinInfo(bod); });
        layer.appendChild(btn);
      }
      document.getElementById('pinNovyNazev').value = '';
      document.getElementById('pinNovyPopis').value = '';
      closeAllPopovers();
      setTool('select');
    });
  });
}

// --- Panel Spravovat: život, efekt, smazání ---
document.querySelectorAll('#panel-spravovat [data-delta]').forEach(btn => {
  btn.addEventListener('click', () => {
    if (!spravovanyToken) return;
    postJson('api/hp_uprava.php', {mapa_id: MAPA_ID, typ_entity: spravovanyToken.typEntity, entita_id: spravovanyToken.entitaId, delta: parseInt(btn.dataset.delta, 10)})
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        const t = tokenData(spravovanyToken.tokenId);
        if (t) {
          t.hp = d.nove_hp;
          if (d.max_hp !== undefined) t.max_hp = d.max_hp;
          nakreslitToken(t);
        }
        obnovPanelSpravovat();
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
        const tm = tokenData(spravovanyToken.tokenId);
        if (tm) tm.magenergie = d.nova_magenergie;
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
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        // Co boj neumí, to PJovi řekne rovnou (efekt je jen štítek s popisem).
        ((d.efekt && d.efekt.upozorneni) || []).forEach((u) => logLine(d.efekt.nazev + ': ' + u));
        obnovitStav();
      });
  });
}
const efektSelectEl = document.getElementById('efektSelect');
if (efektSelectEl) {
  // Výchozí trvání vybraného efektu se ukáže v poli „kol“, ať PJ ví, co dostane, když ho nechá prázdné.
  const ukazVychoziTrvani = () => {
    const o = efektSelectEl.selectedOptions[0];
    const kola = document.getElementById('efektKola');
    if (!o || !kola) return;
    kola.placeholder = o.dataset.trvani ? 'kol (prázdné = ' + o.dataset.trvani + ')' : 'kol (prázdné = bez limitu)';
  };
  efektSelectEl.addEventListener('change', ukazVychoziTrvani);
  ukazVychoziTrvani();
}
document.querySelectorAll('.chip-efekt').forEach(chip => {
  chip.addEventListener('click', () => {
    if (!spravovanyToken) return;
    const sel = document.getElementById('efektSelect');
    sel.value = chip.dataset.efektId;
    // Má-li efekt výchozí trvání, platí to; jinak rychlá volba dá 3 kola (dřívější chování).
    const o = sel.selectedOptions[0];
    document.getElementById('efektKola').value = o && o.dataset.trvani ? '' : '3';
    document.getElementById('efektBtn').click();
  });
});

// --- Inventář spravované entity: "Použít" na každé položce ---
function renderInventar() {
  const cont = document.getElementById('spravovatInventar');
  if (!cont || !spravovanyToken) return;
  const jeMrtvaNestvura = spravovanyToken.typEntity === 'nestvura_instance' && spravovanyToken.hp !== null && spravovanyToken.hp <= 0;
  if (jeMrtvaNestvura) { cont.innerHTML = ''; return; }
  const polozky = inventarCache.get(klicEntity(spravovanyToken.typEntity, spravovanyToken.entitaId)) || [];
  if (!polozky.length) { cont.innerHTML = ''; return; }
  let html = '<h4 style="font-size:12px;color:#e8c9a8;margin:0 0 6px;">Inventář</h4>';
  polozky.forEach((p, i) => {
    const mnozstviText = p.typ_polozky === 'kouzlo' ? '' : (' ×' + p.mnozstvi);
    html += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:12.5px;">'
      + '<span style="flex:1;">' + esc(p.nazev) + mnozstviText + '</span>'
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
  const cil = tokeny.find(t => t.label === zadani.trim() && (t.typ_entity === 'postava' || t.typ_entity === 'nestvura_instance'));
  if (!cil) { alert('Token "' + zadani + '" na mapě nenašel.'); return undefined; }
  return {typEntity: cil.typ_entity, entitaId: cil.entita_id};
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
  const odeslat = (b) => postJson('api/pouzij_predmet.php', b).then(d => {
    if (d.potrebuje_trvani) {
      // Trvání efektu kouzla si volí sesilatel (Rychlost podle magenergie…) — zeptáme se a pošleme znovu.
      const kola = prompt(d.polozka_nazev + ': na kolik kol má platit efekt „' + d.efekt + '“? (0 = bez limitu)', '');
      if (kola === null || kola.trim() === '' || isNaN(Number(kola)) || Number(kola) < 0) return;
      return odeslat(Object.assign({}, b, {zbyva_kol: Math.floor(Number(kola))}));
    }
    if (d.error) { logLine('Chyba: ' + d.error); return; }
    let zprava = document.getElementById('spravovatNazev').textContent + ' použil(a) ' + p.nazev;
    if (d.kostka) zprava += ': [' + d.kostka.hody.join(', ') + ']' + (d.kostka.bonus ? (d.kostka.bonus > 0 ? '+' : '') + d.kostka.bonus : '') + ' = ' + d.kostka.celkem;
    if (d.hp) zprava += ' (' + (d.hp.delta > 0 ? '+' : '') + d.hp.delta + ' život)';
    if (d.magenergie) zprava += ' (-' + d.magenergie.cena + ' magenergie, zbývá ' + d.magenergie.nova_magenergie + '/' + d.magenergie.max_magenergie + ')';
    logLine(zprava);
    (d.efekty || []).filter(e => e.upozorneni && e.upozorneni.length).forEach(e => e.upozorneni.forEach(u => logLine(e.nazev + ': ' + u)));
    (d.ceka_na_past || []).forEach(e => logLine('Cíl se brání hodem proti pasti (' + e.past + '). Při neúspěchu přidej efekt ' + e.nazev + ' v panelu cíle.'));
    posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
    obnovitStav();
    nactiInventar();
  });
  odeslat(body);
}

// --- Kořist z mrtvé nestvůry: "Předat" na každé položce vybavy ---
function renderKorist() {
  const cont = document.getElementById('spravovatKorist');
  if (!cont || !spravovanyToken) return;
  const jeMrtvaNestvura = IS_PJ_OR_ADMIN && spravovanyToken.typEntity === 'nestvura_instance' && spravovanyToken.hp !== null && spravovanyToken.hp <= 0;
  if (!jeMrtvaNestvura) { cont.innerHTML = ''; return; }
  const polozky = inventarCache.get(klicEntity('nestvura_instance', spravovanyToken.entitaId)) || [];
  let html = '<h4 style="font-size:12px;color:#e8c9a8;margin:0 0 6px;">Kořist</h4>';
  if (!polozky.length) {
    html += '<p class="note" style="margin:0;">Nemá u sebe nic.</p>';
  } else {
    html += '<select id="koristCilSelect" class="input" style="margin-bottom:6px;">'
      + SVET_POSTAVY.map(p => '<option value="' + p.id + '">' + esc(p.nazev) + '</option>').join('')
      + '</select>';
    polozky.forEach((p, i) => {
      const mnozstviText = p.typ_polozky === 'kouzlo' ? '' : (' ×' + p.mnozstvi);
      html += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:12.5px;">'
        + '<span style="flex:1;">' + esc(p.nazev) + mnozstviText + '</span>'
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
        obnovitStav();
        nactiInventar();
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
      .then(d => {
        if (d.error) { logLine('Chyba: ' + d.error); return; }
        spravovatPanel.classList.remove('open');
        spravovanyToken = null;
        obnovitStav();
      });
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
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } obnovitStav(); });
  });
}
const iniciativaHoditBtn = document.getElementById('iniciativaHoditBtn');
if (iniciativaHoditBtn) {
  iniciativaHoditBtn.addEventListener('click', () => {
    if (!spravovanyToken) return;
    const bonusIds = Array.from(document.querySelectorAll('.iniciativa-bonus-check:checked')).map(c => parseInt(c.value, 10));
    const jinyBonus = parseInt(document.getElementById('iniciativaJinyBonus').value, 10) || 0;
    postJson('api/iniciativa_hod.php', {mapa_id: MAPA_ID, token_id: spravovanyToken.tokenId, bonus_ids: bonusIds, jiny_bonus: jinyBonus})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } obnovitStav(); });
  });
}
const souborjKonecBtn = document.getElementById('souborjKonecBtn');
if (souborjKonecBtn) {
  souborjKonecBtn.addEventListener('click', () => {
    if (!confirm('Ukončit souboj? Pořadí tahů se smaže a hráči se zase mohou volně hýbat.')) return;
    postJson('api/souboj_konec.php', {mapa_id: MAPA_ID})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } obnovitStav(); });
  });
}
const tbDalsiTah = document.getElementById('tb-dalsi-tah');
if (tbDalsiTah) {
  tbDalsiTah.addEventListener('click', () => {
    postJson('api/dalsi_tah.php', {mapa_id: MAPA_ID})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } obnovitStav(); });
  });
}
const tbKrokZpet = document.getElementById('tb-krok-zpet');
if (tbKrokZpet) {
  tbKrokZpet.addEventListener('click', () => {
    postJson('api/krok_zpet.php', {mapa_id: MAPA_ID})
      .then(d => { if (d.error) { logLine('Chyba: ' + d.error); return; } obnovitStav(); });
  });
}

// --- Kostky ---
// 3D kostky (assets/js/kostky3d.js): čísla, která kostky ukážou, jsou hod a
// posílají se na server (ten hlídá jen rozsah). Bez 3D kostek hodí server.
nactiKostky();

const hoditBtn = document.getElementById('hoditBtn');
if (hoditBtn) {
  const DOSTUPNE_3D = [4, 6, 8, 10, 12, 20];
  hoditBtn.addEventListener('click', async () => {
    const notace = document.getElementById('kostkyNotace').value.trim();
    if (!notace) return;
    hoditBtn.disabled = true;
    try {
      let hody = null;
      const m = notace.toLowerCase().match(/^(\d*)k(\d+)([+-]\d+)?$/);
      if (m) {
        const pocet = m[1] === '' ? 1 : parseInt(m[1], 10);
        const typ = parseInt(m[2], 10);
        if (DOSTUPNE_3D.includes(typ) && pocet >= 1 && pocet <= 12) hody = await hodit3D(pocet, typ);
      }
      const d = await postJson('api/kostka_hod.php', Object.assign({svet_id: SVET_ID, mapa_id: MAPA_ID, notace}, hody ? {hody} : {}));
      if (d.error) { logLine('Chyba: ' + d.error); return; }
      logLine(d.hodil + ' hodil ' + d.notace + ': [' + d.hody.join(', ') + ']' + (d.bonus ? (d.bonus > 0 ? '+' : '') + d.bonus : '') + ' = ' + d.celkem);
      posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
    } finally {
      hoditBtn.disabled = false;
    }
  });
}

// --- Polling delt ---
// Události, po kterých se stav mapy znovu načte celý (token přibyl/zmizel,
// efekty, pořadí tahů, použitý předmět…). Načte se "na místě" — nic se
// nepřenačítá a nikomu nezmizí otevřené panely ani zoom.
const UDALOSTI_OBNOVA_STAVU = ['token_pridan', 'token_smazan', 'efekt_aplikovan', 'efekt_konci', 'iniciativa_hozena', 'kolo_nove', 'tah_zmena', 'predmet_pouzit', 'predmet_loot', 'krok_zpet',
  'souboj_start', 'souboj_faze', 'souboj_ucastnici', 'umysl_nastaven', 'akce_provedena', 'tah_konec', 'akce_uprava',
  'vyzva_nova', 'vyzva_zmena', 'vyzva_hod', 'vyzva_zrusena', 'souboj_vysledek'];
let obnovitMlhuPoPollu = false;

function applyEvent(u) {
  const naTeteMape = u.mapa_id === null || u.mapa_id === undefined || u.mapa_id == MAPA_ID;
  if (souboj && naTeteMape) souboj.logUdalost(u);
  if (souboj && (u.typ === 'token_presun' || u.typ === 'tokeny_presun' || u.typ === 'token_zmena')) setTimeout(() => souboj.prekreslitOverlay(), 0);
  if (u.typ === 'token_presun') {
    const t = tokenData(u.payload.token_id);
    if (t) {
      t.x = u.payload.x_po;
      t.y = u.payload.y_po;
      if (!drzeneTokeny.has(t.id)) nakreslitToken(t);
    }
    if (naTeteMape) obnovitMlhuPoPollu = true;
  } else if (u.typ === 'kresba_pridana') {
    if (u.mapa_id == MAPA_ID && !kresby.some((k) => k.id === u.payload.id)) {
      kresby.push({
        id: u.payload.id, typ: u.payload.typ, b: u.payload.b, t: u.payload.t, barva: u.payload.barva, sirka: u.payload.sirka,
        viditelne_hracum: u.payload.viditelne_hracum, moje: false, smi_smazat: IS_PJ_OR_ADMIN,
      });
      nakreslitKresby();
    }
  } else if (u.typ === 'kresba_smazana') {
    if (u.mapa_id == MAPA_ID) {
      const ids = new Set(u.payload.ids || []);
      kresby = kresby.filter((k) => !ids.has(k.id));
      nakreslitKresby();
    }
  } else if (u.typ === 'tokeny_presun') {
    (u.payload.presuny || []).forEach((pr) => {
      const t = tokenData(pr.token_id);
      if (!t) return;
      t.x = pr.x_po;
      t.y = pr.y_po;
      if (!drzeneTokeny.has(t.id)) nakreslitToken(t);
    });
    aktualizujRotacniUchyt();
    if (naTeteMape) obnovitMlhuPoPollu = true;
  } else if (u.typ === 'token_zmena') {
    const t = tokenData(u.payload.token_id);
    const zm = u.payload.zmeny || {};
    if ('nazev' in zm || 'viditelny' in zm || !t) {
      if (naTeteMape) planujObnovuStavu();
    } else {
      pouzitZmenyTokenu(t, zm);
    }
  } else if (u.typ === 'kostka_hod') {
    logLine((u.payload.hodil || '?') + ' hodil ' + u.payload.notace + ': [' + u.payload.hody.join(', ') + ']'
      + (u.payload.bonus ? (u.payload.bonus > 0 ? '+' : '') + u.payload.bonus : '') + ' = ' + u.payload.celkem);
  } else if (u.typ === 'hp_zmena') {
    const t = tokeny.find((x) => x.typ_entity === u.payload.typ_entity && x.entita_id === u.payload.entita_id);
    if (t) {
      t.hp = u.payload.nove_hp;
      if (u.payload.max_hp) t.max_hp = u.payload.max_hp;
      nakreslitToken(t);
      if (spravovanyToken && spravovanyToken.tokenId === t.id) obnovPanelSpravovat();
    }
  } else if (u.typ === 'magenergie_zmena') {
    const t = tokeny.find((x) => x.typ_entity === 'postava' && x.entita_id === u.payload.entita_id);
    if (t) t.magenergie = u.payload.nova_magenergie;
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
  } else if (UDALOSTI_OBNOVA_STAVU.includes(u.typ)) {
    if (naTeteMape) {
      planujObnovuStavu();
      obnovitMlhuPoPollu = true;
    }
  }
}

// Polling se přizpůsobuje: rychle, dokud se něco děje a okno je vidět;
// pomaleji v klidu; skoro vůbec, když je záložka schovaná. Šetří hosting
// (každý hráč jinak dělá dotaz každou vteřinu a půl celé hodiny).
let pollBezi = false;
let posledniAktivita = Date.now();
let pollCislo = 0;
function dalsiPollZa() {
  if (document.hidden) return 10000;
  const klid = Date.now() - posledniAktivita;
  if (klid > 120000) return 4000;
  if (klid > 30000) return 2500;
  return 1500;
}
async function poll() {
  if (pollBezi) return;
  pollBezi = true;
  pollCislo++;
  try {
    const r = await fetch('api/udalosti.php?svet_id=' + SVET_ID + '&od=' + posledniUdalostId, {credentials: 'same-origin', cache: 'no-store'});
    const d = await r.json();
    if (d.udalosti) {
      for (const u of d.udalosti) {
        posledniUdalostId = Math.max(posledniUdalostId, u.id);
        applyEvent(u);
      }
      if (d.udalosti.length) { posledniAktivita = Date.now(); if (denikDock) denikDock.obnov(); }
    }
  } catch (err) { /* výpadek sítě — zkusí se příště */ }
  // Mlha nemá vlastní typ události (PJ ruční kreslení i odhalení hráčem se
  // projeví pravidelným dotazem) — proto se obnovuje po pohybu tokenů a jinak
  // jen každý pátý dotaz.
  if (obnovitMlhuPoPollu || pollCislo % 5 === 0) {
    obnovitMlhuPoPollu = false;
    osvezitMlhu();
  }
  pollBezi = false;
}
function planPollu() {
  setTimeout(async () => { await poll(); planPollu(); }, dalsiPollZa());
}
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) { posledniAktivita = Date.now(); obnovitStav(); poll(); }
});
// Cokoli, co uživatel na mapě udělá, vrací polling na rychlý režim.
['pointerdown', 'keydown'].forEach((n) => window.addEventListener(n, () => { posledniAktivita = Date.now(); }, {passive: true}));
planPollu();


// --- Kontextové menu (pravé tlačítko / dlouhé stisknutí) a hromadné akce ---
const kontextMenu = document.getElementById('kontextMenu');
function zavritKontextMenu() { if (kontextMenu) kontextMenu.style.display = 'none'; }

// Na koho akce platí: je-li token součástí výběru, na celý výběr; jinak jen na něj.
function cileAkce(t) {
  if (t && vybrane.has(t.id) && vybrane.size > 1) return Array.from(vybrane).map(tokenData).filter(Boolean);
  return t ? [t] : Array.from(vybrane).map(tokenData).filter(Boolean);
}
function krokKopie() { return GRID_PX > 0 ? (GRID_TYPE === 'hex' ? 1.5 * GRID_PX : GRID_PX) : 60; }

function upravTokeny(cile, zmenyNaToken) {
  cile.forEach((t) => { const zm = zmenyNaToken(t); if (zm) upravToken(t, zm); });
}
function smazatTokeny(cile) {
  if (!IS_PJ_OR_ADMIN || !cile.length) return;
  const text = cile.length === 1 ? 'Opravdu smazat token „' + cile[0].label + '“?' : 'Opravdu smazat ' + cile.length + ' tokenů?';
  if (!confirm(text)) return;
  Promise.all(cile.map((t) => postJson('api/token_smazat.php', {token_id: t.id}))).then((vysl) => {
    const chyba = vysl.find((d) => d.error);
    if (chyba) logLine('Chyba: ' + chyba.error);
    if (spravovanyToken && cile.some((t) => t.id === spravovanyToken.tokenId)) {
      spravovatPanel.classList.remove('open');
      spravovanyToken = null;
    }
    vybrane.clear();
    obnovitStav();
  });
}
function kopirovatTokeny(cile) {
  const kopirovatelne = cile.filter((t) => t.typ_entity !== 'postava');
  if (!IS_PJ_OR_ADMIN || !kopirovatelne.length) { logLine('Postavu zkopírovat nejde.'); return; }
  const minY = Math.min(...kopirovatelne.map((t) => t.y)), maxY = Math.max(...kopirovatelne.map((t) => t.y));
  const posun = (maxY - minY) + krokKopie();
  Promise.all(kopirovatelne.map((t) => {
    const stred = GRID_PX > 0 ? gridCellCenter(t.x, t.y + posun) : {x: t.x, y: t.y + posun};
    return postJson('api/token_duplikovat.php', {token_id: t.id, x: Math.round(stred.x), y: Math.round(stred.y)});
  })).then((vysl) => {
    const chyba = vysl.find((d) => d.error);
    if (chyba) logLine('Chyba: ' + chyba.error);
    obnovitStav();
  });
}
function vrstvaTokenu(cile, nahoru) {
  const zs = tokeny.map((t) => t.z);
  let z = nahoru ? Math.max(...zs) : Math.min(...zs);
  cile.forEach((t) => { z += nahoru ? 1 : -1; upravToken(t, {z}); });
}

function polozkyKontextu(t, cile) {
  const pj = IS_PJ_OR_ADMIN;
  const n = cile.length;
  const pocet = n > 1 ? ' (' + n + ')' : '';
  const polozky = [];
  if (t) {
    if (n === 1 && t.owned && t.na_tahu) polozky.push(['Otevřít panel', () => otevritSpravovat(t)]);
    if (cile.every(smiMenitSmer)) {
      polozky.push(['Otočit doleva' + pocet + '  (Q)', () => cile.forEach((c) => otocitTokenO(c, -krokOtoceni()))]);
      polozky.push(['Otočit doprava' + pocet + '  (E)', () => cile.forEach((c) => otocitTokenO(c, krokOtoceni()))]);
    }
    if (pj && FUNKCE.zamek) {
      const zamknout = !cile.every((c) => c.zamceno);
      polozky.push([(zamknout ? 'Zamknout polohu' : 'Odemknout polohu') + pocet + '  (L)', () => upravTokeny(cile, () => ({zamceno: zamknout}))]);
    }
    if (pj && FUNKCE.udalosti) {
      const skryt = cile.some((c) => c.viditelny);
      polozky.push([(skryt ? 'Skrýt před hráči' : 'Ukázat hráčům') + pocet + '  (H)', () => upravTokeny(cile, () => ({viditelny: !skryt}))]);
      polozky.push(['Do popředí' + pocet, () => vrstvaTokenu(cile, true)]);
      polozky.push(['Do pozadí' + pocet, () => vrstvaTokenu(cile, false)]);
    }
    if (pj && cile.some((c) => c.typ_entity !== 'postava')) polozky.push(['Kopírovat' + pocet + '  (Ctrl+D)', () => kopirovatTokeny(cile)]);
    if (pj) polozky.push(['Smazat' + pocet + '  (Delete)', () => smazatTokeny(cile), true]);
  } else {
    const kr = FUNKCE.kresby ? kresbaPodBodem(kontextBod.x, kontextBod.y) : null;
    if (kr && kr.smi_smazat) polozky.push(['Smazat kresbu', () => smazatKresby({id: kr.id}), true]);
    polozky.push(['Ping sem', () => { const m = kontextBod; if (m) klikPing(m); }]);
    if (tokeny.length) polozky.push(['Vybrat všechny tokeny  (Ctrl+A)', () => nastavVyber(tokeny.map((x) => x.id))]);
    if (vybrane.size) polozky.push(['Zrušit výběr  (Esc)', () => zrusitVyber()]);
    polozky.push(['Celá mapa  (0)', () => kamera.prizpusobit()]);
  }
  return polozky;
}
let kontextBod = null;
function klikPing(m) {
  const x = Math.round(m.x), y = Math.round(m.y);
  postJson('api/ping.php', {svet_id: SVET_ID, mapa_id: MAPA_ID, x, y}).then((d) => {
    if (d.error) { logLine('Chyba: ' + d.error); return; }
    posledniUdalostId = Math.max(posledniUdalostId, d.udalost_id);
    zobrazPing(x, y, d.jmeno || '');
  });
}
function otevritKontextMenu(clientX, clientY, t) {
  if (!kontextMenu) return;
  kontextBod = kamera.naMapu(clientX, clientY);
  if (t && !vybrane.has(t.id)) nastavVyber([t.id]);
  const cile = t ? cileAkce(t) : [];
  const polozky = polozkyKontextu(t, cile);
  if (!polozky.length) return;
  kontextMenu.innerHTML = '';
  polozky.forEach(([text, fn, nebezpecna]) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = text;
    if (nebezpecna) b.className = 'nebezpecna';
    b.addEventListener('click', () => { zavritKontextMenu(); fn(); });
    kontextMenu.appendChild(b);
  });
  kontextMenu.style.display = 'block';
  const w = kontextMenu.offsetWidth, h = kontextMenu.offsetHeight;
  kontextMenu.style.left = Math.max(8, Math.min(clientX, window.innerWidth - w - 8)) + 'px';
  kontextMenu.style.top = Math.max(8, Math.min(clientY, window.innerHeight - h - 8)) + 'px';
}
window.addEventListener('pointerdown', (e) => {
  if (kontextMenu && kontextMenu.style.display !== 'none' && !kontextMenu.contains(e.target)) zavritKontextMenu();
}, true);
viewport.addEventListener('wheel', zavritKontextMenu, {passive: true});

// Klávesové zkratky pro výběr (Q/E otáčení je výš).
window.addEventListener('keydown', (e) => {
  if (jeVstupniPole(e.target)) return;
  const ctrl = e.ctrlKey || e.metaKey;
  const cile = Array.from(vybrane).map(tokenData).filter(Boolean);
  if (e.key === 'Escape') {
    zavritKontextMenu();
    zrusitVyber();
    if (currentTool !== 'select') setTool('select');
  } else if (ctrl && (e.key === 'a' || e.key === 'A')) {
    e.preventDefault();
    nastavVyber(tokeny.map((t) => t.id));
  } else if (ctrl && (e.key === 'd' || e.key === 'D')) {
    e.preventDefault();
    if (cile.length) kopirovatTokeny(cile);
  } else if ((e.key === 'Delete' || e.key === 'Backspace') && cile.length) {
    e.preventDefault();
    smazatTokeny(cile);
  } else if ((e.key === 'l' || e.key === 'L') && cile.length && IS_PJ_OR_ADMIN && FUNKCE.zamek) {
    const zamknout = !cile.every((c) => c.zamceno);
    upravTokeny(cile, () => ({zamceno: zamknout}));
  } else if ((e.key === 'h' || e.key === 'H') && cile.length && IS_PJ_OR_ADMIN && FUNKCE.udalosti) {
    const skryt = cile.some((c) => c.viditelny);
    upravTokeny(cile, () => ({viditelny: !skryt}));
  } else if ((e.key === 'v' || e.key === 'V') && !ctrl) {
    setTool('select');
  }
});

// --- Kreslení na mapu (čára, šipka, obdélník, kruh, kužel, text) ---
// Kresby se ukládají (tabulka mapa_kresby) a ostatním se objeví přes polling.
// Vykreslují se do #kresbySvg pod tokeny; rozpracovaná kresba v #rulerSvg.
const kresbySvg = document.getElementById('kresbySvg');
const TVARY_KRESBY = [['cara', 'Čára'], ['sipka', 'Šipka'], ['obdelnik', 'Obdélník'], ['kruh', 'Kruh'], ['kuzel', 'Kužel'], ['text', 'Text']];
const SIRKY_KRESBY = [[3, 'Tenká'], [6, 'Střední'], [12, 'Tlustá']];
const BARVY_KRESBY = ['#ffd166', '#ef476f', '#06d6a0', '#118ab2', '#ffffff', '#111111', '#b5179e', '#ff9f1c'];
const kresbaNastaveni = {tvar: 'cara', barva: '#ffd166', sirka: 6};

function escAttr(t) { return esc(t); }
// SVG jedné kresby; $nahled = rozpracovaná (průhlednější, s popisem rozměru).
function svgKresby(k, nahled) {
  const b = k.b, w = k.sirka, c = k.barva;
  const skryta = !k.viditelne_hracum;
  const atr = 'stroke="' + c + '" stroke-width="' + w + '" stroke-linecap="round" stroke-linejoin="round" fill="none"'
    + (skryta || nahled ? ' opacity="0.7"' : '') + (skryta ? ' stroke-dasharray="' + (w * 2) + ' ' + (w * 1.5) + '"' : '');
  const vypln = 'fill="' + c + '" fill-opacity="0.14"';
  const bod = (p) => p[0] + ',' + p[1];
  switch (k.typ) {
    case 'cara':
      return '<polyline points="' + b.map(bod).join(' ') + '" ' + atr + '/>';
    case 'sipka': {
      if (b.length < 2) return '';
      const a = Math.atan2(b[1][1] - b[0][1], b[1][0] - b[0][0]);
      const d = Math.max(14, w * 4);
      const p1 = [b[1][0] - d * Math.cos(a - 0.45), b[1][1] - d * Math.sin(a - 0.45)];
      const p2 = [b[1][0] - d * Math.cos(a + 0.45), b[1][1] - d * Math.sin(a + 0.45)];
      return '<line x1="' + b[0][0] + '" y1="' + b[0][1] + '" x2="' + b[1][0] + '" y2="' + b[1][1] + '" ' + atr + '/>'
        + '<polyline points="' + bod(p1) + ' ' + bod(b[1]) + ' ' + bod(p2) + '" ' + atr + '/>';
    }
    case 'obdelnik': {
      if (b.length < 2) return '';
      const x = Math.min(b[0][0], b[1][0]), y = Math.min(b[0][1], b[1][1]);
      return '<rect x="' + x + '" y="' + y + '" width="' + Math.abs(b[1][0] - b[0][0]) + '" height="' + Math.abs(b[1][1] - b[0][1]) + '" ' + atr.replace('fill="none"', vypln) + '/>';
    }
    case 'kruh': {
      if (b.length < 2) return '';
      const r = Math.hypot(b[1][0] - b[0][0], b[1][1] - b[0][1]);
      return '<circle cx="' + b[0][0] + '" cy="' + b[0][1] + '" r="' + r + '" ' + atr.replace('fill="none"', 'fill="' + c + '" fill-opacity="0.18"') + '/>';
    }
    case 'kuzel': {
      if (b.length < 2) return '';
      const L = Math.hypot(b[1][0] - b[0][0], b[1][1] - b[0][1]);
      const a = Math.atan2(b[1][1] - b[0][1], b[1][0] - b[0][0]);
      const pol = Math.PI / 6; // kužel o rozevření 60°
      const e1 = [b[0][0] + L * Math.cos(a - pol), b[0][1] + L * Math.sin(a - pol)];
      const e2 = [b[0][0] + L * Math.cos(a + pol), b[0][1] + L * Math.sin(a + pol)];
      return '<path d="M' + bod(b[0]) + ' L' + bod(e1) + ' A' + L + ' ' + L + ' 0 0 1 ' + bod(e2) + ' Z" ' + atr.replace('fill="none"', 'fill="' + c + '" fill-opacity="0.2"') + '/>';
    }
    case 'text': {
      const velikost = 12 + w * 3;
      return '<text x="' + b[0][0] + '" y="' + b[0][1] + '" font-size="' + velikost + '" font-weight="600" fill="' + c + '" stroke="#000" stroke-width="' + (velikost / 9)
        + '" paint-order="stroke" stroke-linejoin="round"' + (skryta ? ' opacity="0.7"' : '') + '>' + esc(k.t || '') + '</text>';
    }
    default:
      return '';
  }
}
function nakreslitKresby() {
  if (!kresbySvg) return;
  const w = mapImgEl.naturalWidth || mapWrap.clientWidth, h = mapImgEl.naturalHeight || mapWrap.clientHeight;
  if (w) { kresbySvg.setAttribute('width', w); kresbySvg.setAttribute('height', h); }
  kresbySvg.innerHTML = kresby.map((k) => '<g data-id="' + k.id + '">' + svgKresby(k) + '</g>').join('');
}

// Vzdálenost bodu od kresby (pro výběr pravým tlačítkem).
function kresbaPodBodem(x, y) {
  const tol = 8 / kamera.stav.z;
  for (let i = kresby.length - 1; i >= 0; i--) {
    const k = kresby[i], b = k.b;
    const prah = Math.max(k.sirka / 2 + 2, tol);
    let zasah = false;
    if (k.typ === 'cara') {
      for (let j = 0; j < b.length - 1 && !zasah; j++) zasah = vzdalenostKUsecce(x, y, b[j][0], b[j][1], b[j + 1][0], b[j + 1][1]) <= prah;
    } else if (k.typ === 'sipka') {
      zasah = vzdalenostKUsecce(x, y, b[0][0], b[0][1], b[1][0], b[1][1]) <= Math.max(prah, 10 / kamera.stav.z);
    } else if (k.typ === 'obdelnik') {
      zasah = x >= Math.min(b[0][0], b[1][0]) - tol && x <= Math.max(b[0][0], b[1][0]) + tol && y >= Math.min(b[0][1], b[1][1]) - tol && y <= Math.max(b[0][1], b[1][1]) + tol;
    } else if (k.typ === 'kruh') {
      zasah = Math.hypot(x - b[0][0], y - b[0][1]) <= Math.hypot(b[1][0] - b[0][0], b[1][1] - b[0][1]) + tol;
    } else if (k.typ === 'kuzel') {
      const L = Math.hypot(b[1][0] - b[0][0], b[1][1] - b[0][1]);
      const vzd = Math.hypot(x - b[0][0], y - b[0][1]);
      let rozdil = Math.abs(Math.atan2(y - b[0][1], x - b[0][0]) - Math.atan2(b[1][1] - b[0][1], b[1][0] - b[0][0]));
      if (rozdil > Math.PI) rozdil = 2 * Math.PI - rozdil;
      zasah = vzd <= L + tol && (rozdil <= Math.PI / 6 || vzd < tol);
    } else if (k.typ === 'text') {
      const velikost = 12 + k.sirka * 3;
      const delka = (k.t || '').length * velikost * 0.6;
      zasah = x >= b[0][0] - tol && x <= b[0][0] + delka + tol && y >= b[0][1] - velikost - tol && y <= b[0][1] + tol;
    }
    if (zasah) return k;
  }
  return null;
}

// Zjednodušení čáry (Douglas–Peucker), ať se neposílají stovky bodů.
function zjednoduseniCary(body, eps) {
  if (body.length < 3) return body;
  const rec = (a, z) => {
    let dmax = 0, idx = -1;
    for (let i = a + 1; i < z; i++) {
      const d = vzdalenostKUsecce(body[i][0], body[i][1], body[a][0], body[a][1], body[z][0], body[z][1]);
      if (d > dmax) { dmax = d; idx = i; }
    }
    if (dmax > eps && idx > 0) return rec(a, idx).slice(0, -1).concat(rec(idx, z));
    return [body[a], body[z]];
  };
  return rec(0, body.length - 1);
}

function kresbaBodSnap(m, tvar) {
  // Střed kruhu a hrot kužele se na gridu chytají na střed buňky.
  if (GRID_PX > 0 && (tvar === 'kruh' || tvar === 'kuzel')) {
    const c = GRID_TYPE === 'hex' ? hexBunka(m.x, m.y) : gridCellCenter(m.x, m.y);
    return [c.x, c.y];
  }
  return [m.x, m.y];
}
function nahledKresby(akceK, m) {
  const tvar = kresbaNastaveni.tvar;
  const z = kamera.stav.z;
  if (tvar === 'cara') {
    const b = akceK.body, p = b[b.length - 1];
    if (Math.hypot(m.x - p[0], m.y - p[1]) > 2 / z) b.push([m.x, m.y]);
  } else if (tvar !== 'text') {
    akceK.body = [akceK.body[0], [m.x, m.y]];
  }
  const k = {typ: tvar, b: akceK.body, barva: kresbaNastaveni.barva, sirka: kresbaNastaveni.sirka, viditelne_hracum: true};
  let popis = '';
  if ((tvar === 'kruh' || tvar === 'kuzel' || tvar === 'sipka') && akceK.body.length > 1) {
    const a = {x: akceK.body[0][0], y: akceK.body[0][1]}, c = {x: akceK.body[1][0], y: akceK.body[1][1]};
    const kk = 1 / z;
    popis = '<text x="' + c.x + '" y="' + (c.y - 12 * kk) + '" fill="#fff" font-size="' + (14 * kk) + '" text-anchor="middle" paint-order="stroke" stroke="#000" stroke-width="' + (3 * kk) + '">'
      + popisVzdalenosti(a, c) + '</text>';
  }
  if (rulerSvg) rulerSvg.innerHTML = svgKresby(k, true) + popis;
}

function odeslatKresbu(k) {
  return postJson('api/kresba_pridat.php', {mapa_id: MAPA_ID, typ: k.typ, b: k.b, t: k.t, barva: k.barva, sirka: k.sirka, viditelne_hracum: k.viditelne_hracum})
    .then((d) => {
      if (d.error) { logLine('Chyba: ' + d.error); return; }
      if (!kresby.some((x) => x.id === d.kresba.id)) kresby.push(d.kresba);
      nakreslitKresby();
    })
    .catch(() => logLine('Kresbu se nepodařilo odeslat.'));
}
function dokoncitKresbu(akceK, m) {
  if (rulerSvg) rulerSvg.innerHTML = '';
  const tvar = kresbaNastaveni.tvar;
  const skryta = IS_PJ_OR_ADMIN && document.getElementById('kresbaSkryta') && document.getElementById('kresbaSkryta').checked;
  const k = {typ: tvar, b: akceK.body, barva: kresbaNastaveni.barva, sirka: kresbaNastaveni.sirka, viditelne_hracum: !skryta};
  if (tvar === 'text') {
    const t = prompt('Text na mapě:', '');
    if (!t || !t.trim()) return;
    k.t = t.trim().slice(0, 200);
    k.b = [[Math.round(m.x), Math.round(m.y)]];
  } else if (tvar === 'cara') {
    if (akceK.body.length < 2) return;
    k.b = zjednoduseniCary(akceK.body, 1.5 / kamera.stav.z).slice(0, 400);
  } else {
    if (akceK.body.length < 2 || Math.hypot(akceK.body[1][0] - akceK.body[0][0], akceK.body[1][1] - akceK.body[0][1]) < 4 / kamera.stav.z) return;
  }
  k.b = k.b.map((p) => [Math.round(p[0] * 10) / 10, Math.round(p[1] * 10) / 10]);
  odeslatKresbu(k);
}

function smazatKresby(zadani) {
  return postJson('api/kresba_smazat.php', zadani).then((d) => {
    if (d.error) { logLine('Chyba: ' + d.error); return; }
    const ids = new Set(d.ids);
    kresby = kresby.filter((k) => !ids.has(k.id));
    nakreslitKresby();
  });
}

// Panel nastavení kreslení
const kresleniTlacitko = document.getElementById('tb-kresleni');
if (kresleniTlacitko && FUNKCE.kresby) {
  kresleniTlacitko.style.display = '';
  toolButtons.kresleni = kresleniTlacitko;
  const popKresleni = document.getElementById('popover-kresleni');
  const tvaryEl = document.getElementById('kresbaTvary'), barvyEl = document.getElementById('kresbaBarvy'), sirkyEl = document.getElementById('kresbaSirky');
  tvaryEl.innerHTML = TVARY_KRESBY.map(([k, t]) => '<button type="button" class="btn btn-ghost" data-tvar="' + k + '">' + t + '</button>').join('');
  barvyEl.innerHTML = BARVY_KRESBY.map((c) => '<button type="button" class="vz-barva" data-barva="' + c + '" style="background:' + c + ';"></button>').join('');
  sirkyEl.innerHTML = SIRKY_KRESBY.map(([w, t]) => '<button type="button" class="btn btn-ghost" data-sirka="' + w + '">' + t + '</button>').join('');
  const obnovNastaveni = () => {
    tvaryEl.querySelectorAll('button').forEach((b) => b.classList.toggle('aktivni', b.dataset.tvar === kresbaNastaveni.tvar));
    barvyEl.querySelectorAll('button').forEach((b) => b.classList.toggle('aktivni', b.dataset.barva === kresbaNastaveni.barva));
    sirkyEl.querySelectorAll('button').forEach((b) => b.classList.toggle('aktivni', parseInt(b.dataset.sirka, 10) === kresbaNastaveni.sirka));
  };
  tvaryEl.addEventListener('click', (e) => { const b = e.target.closest('button'); if (b) { kresbaNastaveni.tvar = b.dataset.tvar; obnovNastaveni(); } });
  barvyEl.addEventListener('click', (e) => { const b = e.target.closest('button'); if (b) { kresbaNastaveni.barva = b.dataset.barva; obnovNastaveni(); } });
  sirkyEl.addEventListener('click', (e) => { const b = e.target.closest('button'); if (b) { kresbaNastaveni.sirka = parseInt(b.dataset.sirka, 10); obnovNastaveni(); } });
  obnovNastaveni();
  kresleniTlacitko.addEventListener('click', () => {
    if (currentTool === 'kresleni') { setTool('select'); popKresleni.classList.remove('open'); kresleniTlacitko.classList.remove('active'); return; }
    closeAllPopovers();
    setTool('kresleni');
    umistitPopoverKBodu(popKresleni, kresleniTlacitko.getBoundingClientRect());
    kresleniTlacitko.classList.add('active');
  });
  document.getElementById('kresbaSmazatMoje').addEventListener('click', () => {
    if (confirm('Smazat všechny tvoje kresby na téhle mapě?')) smazatKresby({mapa_id: MAPA_ID, rezim: 'moje'});
  });
  const smazVse = document.getElementById('kresbaSmazatVse');
  if (smazVse) smazVse.addEventListener('click', () => {
    if (confirm('Smazat VŠECHNY kresby na téhle mapě (i cizí)?')) smazatKresby({mapa_id: MAPA_ID, rezim: 'vsechny'});
  });
}

// --- Zoom: tlačítka, údaj, uložení pohledu ---
const zoomInfo = document.getElementById('zoomInfo');
let ulozPohledTimer = null;
kamera.naPosluch((st) => {
  mapWrap.style.setProperty('--z', String(st.z));
  if (zoomInfo) zoomInfo.textContent = Math.round(st.z * 100) + ' %';
  clearTimeout(ulozPohledTimer);
  ulozPohledTimer = setTimeout(() => {
    try { sessionStorage.setItem('mapa_pohled_' + MAPA_ID, JSON.stringify({x: st.x, y: st.y, z: st.z})); } catch (err) { /* nevadí */ }
  }, 400);
});
const zoomPlus = document.getElementById('zoom-plus');
const zoomMinus = document.getElementById('zoom-minus');
const zoomFit = document.getElementById('zoom-fit');
if (zoomPlus) zoomPlus.addEventListener('click', () => kamera.zoomStred(1.25));
if (zoomMinus) zoomMinus.addEventListener('click', () => kamera.zoomStred(0.8));
if (zoomFit) zoomFit.addEventListener('click', () => kamera.prizpusobit());
const polozitVicChk = document.getElementById('polozitVic');
if (polozitVicChk) polozitVicChk.addEventListener('change', () => { polozitVic = polozitVicChk.checked; });

// Výchozí pohled: je-li na mapě družina, zaměří se na ni (a na čitelnou
// velikost buňky); jinak se vejde celá mapa. Po obnovení stránky se vrací
// poslední pohled z téhle záložky.
function vychoziPohled() {
  let ulozeny = null;
  try { ulozeny = JSON.parse(sessionStorage.getItem('mapa_pohled_' + MAPA_ID) || 'null'); } catch (err) { ulozeny = null; }
  if (ulozeny && isFinite(ulozeny.x) && isFinite(ulozeny.y) && isFinite(ulozeny.z)) {
    kamera.nastav(ulozeny.x, ulozeny.y, ulozeny.z);
    return;
  }
  const viditelne = tokeny.filter((t) => t.viditelny || IS_PJ_OR_ADMIN);
  if (!viditelne.length) { kamera.prizpusobit(); return; }
  // Oblast okna, kterou nezakrývá lišta nástrojů (vlevo na počítači, dole
  // na telefonu) ani horní lišta.
  const r = viewport.getBoundingClientRect();
  const tb = document.querySelector('.toolbar');
  const tr = tb ? tb.getBoundingClientRect() : null;
  const dole = tr && tr.width > tr.height ? r.height - tr.top + 12 : 0;
  const vlevo = tr && tr.width <= tr.height ? tr.right + 12 : 0;
  const nahore = 64;
  const volnaSirka = r.width - vlevo, volnaVyska = r.height - nahore - dole;
  const minX = Math.min(...viditelne.map((t) => t.x)), maxX = Math.max(...viditelne.map((t) => t.x));
  const minY = Math.min(...viditelne.map((t) => t.y)), maxY = Math.max(...viditelne.map((t) => t.y));
  const sah = pxNaSah() || 70;
  const okraj = sah * 3;
  const zVejde = Math.min(volnaSirka / (maxX - minX + 2 * okraj), volnaVyska / (maxY - minY + 2 * okraj));
  const zCitelne = Math.min(1.25, Math.max(0.3, 64 / sah));
  const z = Math.min(zVejde, zCitelne);
  let px = vlevo + volnaSirka / 2 - ((minX + maxX) / 2) * z;
  let py = nahore + volnaVyska / 2 - ((minY + maxY) / 2) * z;
  // Mapa vyplní okno, pokud se to dá (žádný prázdný pruh kolem okraje).
  const mw = mapImgEl.naturalWidth * z, mh = mapImgEl.naturalHeight * z;
  px = mw > r.width ? Math.min(vlevo, Math.max(r.width - mw, px)) : (r.width - mw) / 2;
  py = mh > r.height ? Math.min(nahore, Math.max(r.height - mh, py)) : (r.height - mh) / 2;
  kamera.nastav(px, py, z);
}

function inicializovatMapu() {
  const w = mapImgEl.naturalWidth, h = mapImgEl.naturalHeight;
  mapWrap.style.width = w + 'px';
  mapWrap.style.height = h + 'px';
  kamera.nastavRozmery(w, h);
  renderGrid();
  renderZdi();
  osvezitMlhu();
  nakreslitTokeny();
  nakreslitKresby();
  nakreslitIniciativu();
  naplnitVolnePostavy();
  vychoziPohled();
}
if (mapImgEl.complete && mapImgEl.naturalWidth) inicializovatMapu(); else mapImgEl.addEventListener('load', inicializovatMapu);
