// Okno „Deník“ na mapě (hra/mapa.php): deník postavy (hra/denik.php?embed=1) v plovoucím okně přes mapu. Výbava, zbraň v ruce,
// životy, kouzla… se upravují přímo při hře, bez proklikávání na jinou stránku. Okno se dá přesunout (za hlavičku), změnit mu
// velikost (za okraje a rohy), sbalit na hlavičku a roztáhnout na celou obrazovku; polohu, velikost a režim si pamatuje prohlížeč.
// Ukazuje postavu podle vybraného tokenu, podle toho, kdo je na tahu, nebo zůstane u jedné postavy.
// Deník v okně a mapa si dávají vědět přes postMessage: změna v deníku hned obnoví mapu (životy na tokenu…),
// změna na mapě (zásah, léčení) hned obnoví deník.

const KLIC = 'dracak_denik_dock';
const SIRKA_MIN = 340;
const VYSKA_MIN = 240;
const TELEFON = 760;         // pod touto šířkou je deník spodní list přes mapu a okno se nepřesouvá
const OKRAJ = 8;             // o kolik px od okraje obrazovky se okno drží při změně velikosti
const HLAVA_VIDITELNA = 200;  // kolik px okna musí zůstat na obrazovce (hlavička s kusem názvu a tlačítky), ať jde okno vždy chytit

function nacti() {
  try { return JSON.parse(localStorage.getItem(KLIC) || '{}') || {}; } catch (e) { return {}; }
}
function uloz(stav) {
  try { localStorage.setItem(KLIC, JSON.stringify(stav)); } catch (e) { /* soukromé okno, zablokované úložiště */ }
}

// ctx: {postavy: [{id, nazev}], spravovaneId: () => id postavy vybraného tokenu | null, poZmene: (postavaId) => void}
export function initDenikDock(ctx) {
  const dock = document.getElementById('denikDock');
  if (!dock || !ctx.postavy.length) return null;
  const hlava = document.getElementById('denikHlava');
  const titul = document.getElementById('denikTitul');
  const vyber = document.getElementById('denikPostava');
  const sledovat = document.getElementById('denikSledovat');
  const frame = document.getElementById('denikFrame');
  const otevrit = document.getElementById('denikOtevrit');
  const btnCela = document.getElementById('denikCela');
  const btnSbalit = document.getElementById('denikSbalit');
  const povoleneId = new Set(ctx.postavy.map((p) => p.id));
  const stav = Object.assign({otevreno: false, sledovat: 'vyber', postava: null, x: null, y: null, w: 480, h: null, cela: false, sbaleno: false}, nacti());
  let zobrazena = null;
  let posledniTah = null;
  let posledniObnova = 0;

  ctx.postavy.forEach((p) => vyber.append(new Option(p.nazev, String(p.id))));
  sledovat.value = ['vyber', 'tah', 'nic'].includes(stav.sledovat) ? stav.sledovat : 'vyber';

  // ----- poloha a velikost okna -----
  // Lišta s nástroji vlevo má jeden nebo dva sloupce podle výšky okna — okno poprvé vznikne hned za ní.
  function vychoziX() {
    const lista = document.querySelector('.toolbar');
    return lista ? Math.round(lista.getBoundingClientRect().right) + 10 : 88;
  }
  function jeCislo(v) { return typeof v === 'number' && Number.isFinite(v); }
  function ohranic() {
    const sw = window.innerWidth, sh = window.innerHeight;
    stav.w = Math.max(SIRKA_MIN, Math.min(sw - 2 * OKRAJ, Number(stav.w) || 480));
    stav.h = Math.max(VYSKA_MIN, Math.min(sh - 2 * OKRAJ, Number(stav.h) || sh - 24));
    if (!jeCislo(stav.x)) stav.x = vychoziX();
    if (!jeCislo(stav.y)) stav.y = 12;
    stav.x = Math.max(HLAVA_VIDITELNA - stav.w, Math.min(sw - HLAVA_VIDITELNA, stav.x));
    stav.y = Math.max(0, Math.min(sh - stav.h, stav.y));   // svisle celé na obrazovce (vodorovně smí kus přečnívat)
  }
  function aplikuj() {
    const telefon = window.innerWidth <= TELEFON;
    ohranic();
    dock.classList.toggle('cela', !!stav.cela);
    dock.classList.toggle('sbaleno', !!stav.sbaleno && !stav.cela);
    document.body.classList.toggle('denik-dock-cela', !!stav.cela && !!stav.otevreno);
    btnCela.setAttribute('aria-pressed', stav.cela ? 'true' : 'false');
    btnCela.title = stav.cela ? 'Zpět do okna' : 'Na celou obrazovku';
    btnSbalit.setAttribute('aria-pressed', stav.sbaleno ? 'true' : 'false');
    btnSbalit.title = stav.sbaleno ? 'Rozbalit' : 'Sbalit na hlavičku';
    if (telefon) { dock.style.left = dock.style.top = dock.style.width = dock.style.height = ''; return; }
    dock.style.left = stav.x + 'px';
    dock.style.top = stav.y + 'px';
    dock.style.width = stav.w + 'px';
    dock.style.height = stav.h + 'px';
  }

  // Tažení (okno i okraje): po dobu tažení nesmí rámeček chytat myš, jinak by tažení „zapadlo“ do deníku.
  function tahni(cil, e, krok) {
    e.preventDefault();
    cil.setPointerCapture(e.pointerId);
    frame.style.pointerEvents = 'none';
    dock.classList.add('tahne');
    const konec = () => {
      frame.style.pointerEvents = '';
      dock.classList.remove('tahne');
      cil.removeEventListener('pointermove', krok);
      cil.removeEventListener('pointerup', konec);
      cil.removeEventListener('pointercancel', konec);
      uloz(stav);
    };
    cil.addEventListener('pointermove', krok);
    cil.addEventListener('pointerup', konec);
    cil.addEventListener('pointercancel', konec);
  }

  // Přesun za hlavičku (myš, dotyk, pero); ovládací prvky v hlavičce se nechytají.
  hlava.addEventListener('pointerdown', (e) => {
    if (stav.cela || window.innerWidth <= TELEFON || e.button !== 0 || e.target.closest('select, a, button')) return;
    const x0 = e.clientX - stav.x, y0 = e.clientY - stav.y;
    tahni(hlava, e, (ev) => { stav.x = ev.clientX - x0; stav.y = ev.clientY - y0; aplikuj(); });
  });

  // Změna velikosti za okraje a rohy: data-hrana = n, e, s, w, ne, nw, se, sw.
  dock.querySelectorAll('[data-hrana]').forEach((uchyt) => {
    uchyt.addEventListener('pointerdown', (e) => {
      if (stav.cela || stav.sbaleno || window.innerWidth <= TELEFON || e.button !== 0) return;
      e.stopPropagation();
      const hrana = uchyt.dataset.hrana;
      const z = {x: stav.x, y: stav.y, w: stav.w, h: stav.h, px: e.clientX, py: e.clientY};
      const sw = window.innerWidth, sh = window.innerHeight;
      tahni(uchyt, e, (ev) => {
        const dx = ev.clientX - z.px, dy = ev.clientY - z.py;
        if (hrana.includes('e')) stav.w = Math.max(SIRKA_MIN, Math.min(z.w + dx, sw - z.x - OKRAJ));
        if (hrana.includes('s')) stav.h = Math.max(VYSKA_MIN, Math.min(z.h + dy, sh - z.y - OKRAJ));
        if (hrana.includes('w')) { const w = Math.max(SIRKA_MIN, z.w - dx); stav.x = z.x + z.w - w; stav.w = w; }
        if (hrana.includes('n')) {
          const h = Math.max(VYSKA_MIN, Math.min(z.h - dy, z.y + z.h));   // nahoru nejvýš k hornímu okraji obrazovky
          stav.y = z.y + z.h - h;
          stav.h = h;
        }
        aplikuj();
      });
    });
  });
  window.addEventListener('resize', aplikuj);

  // ----- deník v okně -----
  function zobraz(postavaId) {
    if (!povoleneId.has(postavaId)) return;
    stav.postava = postavaId;
    vyber.value = String(postavaId);
    titul.textContent = (ctx.postavy.find((p) => p.id === postavaId) || {}).nazev || '';
    otevrit.href = 'denik.php?id=' + postavaId;
    uloz(stav);
    if (!stav.otevreno || zobrazena === postavaId) return;
    zobrazena = postavaId;
    frame.src = 'denik.php?id=' + postavaId + '&embed=1';
  }

  // Skok na sekci deníku (id sekce: boj, kouzla, schopnosti, vybaveni, prehled, postava). Deník přepne záložku a srovná sekci pod
  // hlavičku; když sekce v deníku není, deník zůstane tam, kde je.
  let cekajici = null;   // {postava, sekce, kdy} — rámeček se ještě načítá, skok proběhne po načtení
  function skocNaSekci(sekce) {
    try {
      // Nový deník ví sám, na kterou záložku sekce patří (window.dracakDenik v assets/js/denik.js).
      const okno = frame.contentWindow;
      if (okno && okno.dracakDenik && typeof okno.dracakDenik.ukazSekci === 'function') return okno.dracakDenik.ukazSekci(sekce);
      const doc = frame.contentDocument;
      const el = doc && doc.getElementById(sekce);
      if (!el) return false;
      const pas = doc.querySelector('.dn-pas');
      const drziSe = pas && ['sticky', 'fixed'].includes(frame.contentWindow.getComputedStyle(pas).position);   // v úzkém okně se hlavička odroluje s obsahem
      const odsazeni = (drziSe ? pas.getBoundingClientRect().height : 0) + 8;
      frame.contentWindow.scrollTo({top: frame.contentWindow.scrollY + el.getBoundingClientRect().top - odsazeni, behavior: 'smooth'});
      return true;
    } catch (e) { return false; }
  }
  frame.addEventListener('load', () => {
    const c = cekajici;
    cekajici = null;
    if (c && c.postava === zobrazena && Date.now() - c.kdy < 8000) skocNaSekci(c.sekce);
  });

  function otevri(postavaId) {
    stav.otevreno = true;
    dock.style.display = '';
    const vybrana = postavaId || ctx.spravovaneId();
    const cil = vybrana && povoleneId.has(vybrana) ? vybrana : (povoleneId.has(stav.postava) ? stav.postava : ctx.postavy[0].id);
    zobrazena = null;
    zobraz(cil);
    aplikuj();
    uloz(stav);
  }
  function zavri() {
    stav.otevreno = false;
    dock.style.display = 'none';
    document.body.classList.remove('denik-dock-cela');
    frame.src = 'about:blank';   // zastaví i pravidelné obnovování deníku
    zobrazena = null;
    uloz(stav);
  }

  document.getElementById('denikZavrit').addEventListener('click', zavri);
  btnCela.addEventListener('click', () => { stav.cela = !stav.cela; aplikuj(); uloz(stav); });
  btnSbalit.addEventListener('click', () => { stav.sbaleno = !stav.sbaleno; stav.cela = false; aplikuj(); uloz(stav); });
  vyber.addEventListener('change', () => {
    // Ruční volba postavy = pevně tuhle postavu, ať ji další klik na token nepřepne.
    sledovat.value = 'nic';
    stav.sledovat = 'nic';
    zobraz(parseInt(vyber.value, 10));
  });
  sledovat.addEventListener('change', () => { stav.sledovat = sledovat.value; uloz(stav); });

  // Zprávy z deníku: změna v deníku → mapa se hned obnoví.
  window.addEventListener('message', (ev) => {
    if (ev.origin !== window.location.origin || ev.source !== frame.contentWindow) return;
    if (ev.data && ev.data.dracak === 'denik_zmena') ctx.poZmene(Number(ev.data.postava_id));
  });

  if (stav.otevreno) {
    stav.otevreno = false;   // otevri() ho zase zapne
    otevri();
  }

  return {
    // Smí aktuální uživatel otevřít deník téhle postavy (PJ všechny ze světa, hráč své)?
    smi: (postavaId) => povoleneId.has(postavaId),
    // Tlačítko „Otevřít deník“ v detailu tokenu: výslovná volba, přebije i režim „pevně“. Volitelně rovnou na sekci
    // deníku (Boj, Schopnosti a kouzla, Vybavení…). Sbalené okno se rozbalí.
    otevriPro(postavaId, sekce) {
      if (!povoleneId.has(postavaId)) return;
      const uzUkazuje = stav.otevreno && zobrazena === postavaId;
      if (sekce) cekajici = {postava: postavaId, sekce, kdy: Date.now()};
      if (stav.sbaleno) { stav.sbaleno = false; aplikuj(); uloz(stav); }
      if (stav.otevreno) zobraz(postavaId); else otevri(postavaId);
      if (sekce && uzUkazuje && skocNaSekci(sekce)) cekajici = null;
    },
    // Klik na token na mapě: ukaž jeho postavu (jen v režimu „podle vybraného tokenu“).
    nasleduj(postavaId) {
      if (stav.sledovat === 'vyber') zobraz(postavaId);
    },
    // Změna tvora na tahu v souboji (režim „podle toho, kdo je na tahu“).
    tah(postavaId) {
      if (postavaId === posledniTah) return;
      posledniTah = postavaId;
      if (stav.sledovat === 'tah' && postavaId) zobraz(postavaId);
    },
    // Na mapě se něco stalo (zásah, léčení, použitá věc) — deník se obnoví, nejvýš jednou za 2 s.
    obnov() {
      if (!stav.otevreno || !frame.contentWindow || Date.now() - posledniObnova < 2000) return;
      posledniObnova = Date.now();
      frame.contentWindow.postMessage({dracak: 'denik_obnov'}, window.location.origin);
    },
  };
}
