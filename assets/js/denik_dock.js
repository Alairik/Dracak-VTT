// Panel „Deník“ na mapě (hra/mapa.php): deník postavy (hra/denik.php?embed=1) v rámečku vedle mapy. Výbava, zbraň v ruce,
// životy, kouzla… se upravují přímo při hře, bez proklikávání na jinou stránku. Panel ukazuje postavu podle vybraného
// tokenu, podle toho, kdo je na tahu, nebo zůstane u jedné postavy; stav (otevřeno, šířka, režim) si pamatuje prohlížeč.
// Deník v rámečku a mapa si dávají vědět přes postMessage: změna v deníku hned obnoví mapu (životy na tokenu…),
// změna na mapě (zásah, léčení) hned obnoví deník.

const KLIC = 'dracak_denik_dock';
const SIRKA_MIN = 340;

function nacti() {
  try { return JSON.parse(localStorage.getItem(KLIC) || '{}') || {}; } catch (e) { return {}; }
}
function uloz(stav) {
  try { localStorage.setItem(KLIC, JSON.stringify(stav)); } catch (e) { /* soukromé okno, zablokované úložiště */ }
}

const MEZERA = 8;
const TELEFON = 760;         // pod touto šířkou je deník spodní list přes mapu, mapu neposouvá

// ctx: {postavy: [{id, nazev}], spravovaneId: () => id postavy vybraného tokenu | null, poZmene: (postavaId) => void,
//       posunMapy: (dx) => void — mapa se po otevření panelu zúží zleva; obsah se posune tak, aby střed pohledu zůstal uprostřed}
export function initDenikDock(ctx) {
  const dock = document.getElementById('denikDock');
  if (!dock || !ctx.postavy.length) return null;
  const vyber = document.getElementById('denikPostava');
  const sledovat = document.getElementById('denikSledovat');
  const frame = document.getElementById('denikFrame');
  const otevrit = document.getElementById('denikOtevrit');
  const uchyt = document.getElementById('denikUchyt');
  const povoleneId = new Set(ctx.postavy.map((p) => p.id));
  const stav = Object.assign({otevreno: false, sirka: 480, sledovat: 'vyber', postava: null}, nacti());
  let zobrazena = null;
  let posledniTah = null;
  let posledniObnova = 0;
  let posun = 0;   // o kolik px je mapa zúžená zleva panelem

  // Mapa se zúží o panel (CSS proměnná --denik-posun na #viewport), ať panel nezakrývá tokeny. Kamera počítá se skutečným
  // obdélníkem mapy; obsah se posune o půl šířky panelu zpátky, takže to, na co se hrálo (střed pohledu), zůstane uprostřed
  // zúženého okna a nic důležitého nezmizí pod panelem.
  // Lišta s nástroji vlevo má jeden nebo dva sloupce podle výšky okna — panel začíná hned za ní.
  function levyOkraj() {
    const lista = document.querySelector('.toolbar');
    return lista ? Math.round(lista.getBoundingClientRect().right) + 10 : 88;
  }
  function aplikujPosun(bezKompenzace) {
    const levy = levyOkraj();
    dock.style.left = window.innerWidth > TELEFON ? levy + 'px' : '';
    const cil = stav.otevreno && window.innerWidth > TELEFON ? levy + stav.sirka + MEZERA : 0;
    const delta = cil - posun;
    posun = cil;
    document.documentElement.style.setProperty('--denik-posun', cil + 'px');
    if (delta && !bezKompenzace) ctx.posunMapy(-delta / 2);
  }

  ctx.postavy.forEach((p) => vyber.append(new Option(p.nazev, String(p.id))));
  sledovat.value = ['vyber', 'tah', 'nic'].includes(stav.sledovat) ? stav.sledovat : 'vyber';

  function nastavSirku(px, bezKompenzace) {
    const max = Math.max(SIRKA_MIN, Math.min(900, window.innerWidth - levyOkraj() - 240));   // aspoň 240 px mapy zůstane vidět
    stav.sirka = Math.max(SIRKA_MIN, Math.min(max, Math.round(px)));
    dock.style.setProperty('--denik-w', stav.sirka + 'px');
    if (posun || stav.otevreno) aplikujPosun(!!bezKompenzace);
  }
  nastavSirku(stav.sirka, true);

  function zobraz(postavaId) {
    if (!povoleneId.has(postavaId)) return;
    stav.postava = postavaId;
    vyber.value = String(postavaId);
    otevrit.href = 'denik.php?id=' + postavaId;
    uloz(stav);
    if (!stav.otevreno || zobrazena === postavaId) return;
    zobrazena = postavaId;
    frame.src = 'denik.php?id=' + postavaId + '&embed=1';
  }

  function otevri(bezKompenzace, postavaId) {
    stav.otevreno = true;
    dock.style.display = '';
    const vybrana = postavaId || ctx.spravovaneId();
    const cil = vybrana && povoleneId.has(vybrana) ? vybrana : (povoleneId.has(stav.postava) ? stav.postava : ctx.postavy[0].id);
    zobrazena = null;
    zobraz(cil);
    document.body.classList.add('denik-dock-otevreny');
    aplikujPosun(bezKompenzace);
    uloz(stav);
  }
  function zavri() {
    stav.otevreno = false;
    dock.style.display = 'none';
    document.body.classList.remove('denik-dock-otevreny');
    aplikujPosun(false);
    frame.src = 'about:blank';   // zastaví i pravidelné obnovování deníku
    zobrazena = null;
    uloz(stav);
  }

  document.getElementById('denikZavrit').addEventListener('click', zavri);
  vyber.addEventListener('change', () => {
    // Ruční volba postavy = pevně tuhle postavu, ať ji další klik na token nepřepne.
    sledovat.value = 'nic';
    stav.sledovat = 'nic';
    zobraz(parseInt(vyber.value, 10));
  });
  sledovat.addEventListener('change', () => { stav.sledovat = sledovat.value; uloz(stav); });

  // Šířka tažením za okraj (myš, dotyk, pero).
  uchyt.addEventListener('pointerdown', (e) => {
    e.preventDefault();
    uchyt.setPointerCapture(e.pointerId);
    frame.style.pointerEvents = 'none';   // jinak by tažení "zapadlo" do rámečku
    const posun = (ev) => nastavSirku(ev.clientX - dock.getBoundingClientRect().left);
    const konec = () => {
      frame.style.pointerEvents = '';
      uchyt.removeEventListener('pointermove', posun);
      uchyt.removeEventListener('pointerup', konec);
      uchyt.removeEventListener('pointercancel', konec);
      uloz(stav);
    };
    uchyt.addEventListener('pointermove', posun);
    uchyt.addEventListener('pointerup', konec);
    uchyt.addEventListener('pointercancel', konec);
  });
  window.addEventListener('resize', () => { nastavSirku(stav.sirka); aplikujPosun(false); });

  // Zprávy z deníku: změna v deníku → mapa se hned obnoví.
  window.addEventListener('message', (ev) => {
    if (ev.origin !== window.location.origin || ev.source !== frame.contentWindow) return;
    if (ev.data && ev.data.dracak === 'denik_zmena') ctx.poZmene(Number(ev.data.postava_id));
  });

  if (stav.otevreno) {
    stav.otevreno = false;   // otevri() ho zase zapne
    otevri(true);            // při načtení stránky se mapa ještě nevykreslila, nemá co kompenzovat
  }

  return {
    // Smí aktuální uživatel otevřít deník téhle postavy (PJ všechny z světa, hráč své)?
    smi: (postavaId) => povoleneId.has(postavaId),
    // Tlačítko „Otevřít deník“ v detailu tokenu: výslovná volba, přebije i režim „pevně“.
    otevriPro(postavaId) {
      if (!povoleneId.has(postavaId)) return;
      if (stav.otevreno) zobraz(postavaId); else otevri(false, postavaId);
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
