// Osobní deník (hra/denik.php): rychlé zápisy přes hra/api/postava_stav.php,
// po každé změně i při pollingu se překreslí jen blok #denik (fragment).
// Rozbalené <details>, aktivní záložka a rozepsané texty se zachovají; polling se přeskočí,
// dokud hráč píše do pole nebo má rozepsaný text.
(function () {
  const wrap = document.querySelector('.dn-wrap');
  if (!wrap) return;
  const ID = wrap.dataset.postavaId;
  const API = 'api/postava_stav.php';
  let zaneprazdneno = false;
  let rozepsano = false;

  // ----- záložky (Postava / Inventář / Kouzla / Schopnosti) -----
  const KLIC_TAB = 'dracak_denik_tab_' + ID;
  let aktivniTab = 'postava';
  try { aktivniTab = sessionStorage.getItem(KLIC_TAB) || 'postava'; } catch (e) { /* soukromé okno */ }
  function ukazTab(id) {
    const koren = document.getElementById('denik');
    if (!koren) return;
    if (!koren.querySelector('[data-tab="' + id + '"]')) id = 'postava';
    aktivniTab = id;
    try { sessionStorage.setItem(KLIC_TAB, id); } catch (e) { /* soukromé okno */ }
    koren.querySelectorAll('[data-tab]').forEach(function (t) {
      const on = t.dataset.tab === id;
      t.classList.toggle('on', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    koren.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== id; });
    zavriInfo();
  }
  // Id sekcí, na které se dá odkázat z mapy (okno deníku: skoky Boj / Kouzla / Výbava) — viz docs/stav-projektu.md.
  const SEKCE_TAB = {
    prehled: 'postava', obrana: 'postava', boj: 'postava', pohyb: 'postava', efekty: 'postava', postava: 'postava',
    vybaveni: 'inventar', kontejnery: 'inventar', penize: 'inventar', kouzla: 'kouzla', schopnosti: 'schopnosti',
  };
  const PANELY = ['vybaveni', 'kouzla', 'schopnosti'];   // id celého panelu: srolovat na lištu záložek
  function ukazSekci(id) {
    let tab = SEKCE_TAB[id];
    if (!tab) return false;
    if (!document.querySelector('#denik [data-tab="' + tab + '"]')) tab = tab === 'kouzla' ? 'schopnosti' : 'postava';
    ukazTab(tab);
    const cil = PANELY.indexOf(id) >= 0 ? document.querySelector('#denik .dk-tabbar') : document.getElementById(id);
    if (!cil) return true;
    const pas = document.querySelector('#denik .dn-pas');
    const drziSe = pas && ['sticky', 'fixed'].indexOf(window.getComputedStyle(pas).position) >= 0;
    const odsazeni = (drziSe ? pas.getBoundingClientRect().height : 0) + 8;
    window.scrollTo({ top: window.scrollY + cil.getBoundingClientRect().top - odsazeni, behavior: 'smooth' });
    return true;
  }
  window.dracakDenik = { ukazSekci: ukazSekci, ukazTab: ukazTab };
  document.addEventListener('click', function (ev) {
    const t = ev.target.closest('[data-tab]');
    if (t && wrap.contains(t)) ukazTab(t.dataset.tab);
  });

  // ----- vysvětlení k číslu na klepnutí (title se na dotyku ani z klávesnice neukáže) -----
  let infoEl = null;
  let infoCil = null;
  function zavriInfo() {
    if (infoEl) { infoEl.remove(); infoEl = null; infoCil = null; }
  }
  document.addEventListener('click', function (ev) {
    const t = ev.target.closest('[data-info]');
    const stejny = !!t && t === infoCil;
    zavriInfo();
    if (!t || !wrap.contains(t) || stejny) return;
    infoEl = document.createElement('div');
    infoEl.id = 'dn-info';
    infoEl.setAttribute('role', 'status');
    infoEl.textContent = t.dataset.info;
    document.body.appendChild(infoEl);
    infoCil = t;
    const r = t.getBoundingClientRect();
    const w = infoEl.offsetWidth;
    infoEl.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
    infoEl.style.top = Math.min(r.bottom + 6, window.innerHeight - infoEl.offsetHeight - 8) + 'px';
  });
  window.addEventListener('scroll', zavriInfo, { passive: true });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') zavriInfo(); });

  // „Zavřít deník“ na samostatné stránce vrací tam, odkud se deník otevřel (overlay nad obrazovkou); bez historie na odkaz.
  document.addEventListener('click', function (ev) {
    const a = ev.target.closest('[data-zavrit]');
    if (!a || !wrap.contains(a)) return;
    try {
      if (document.referrer && new URL(document.referrer).origin === window.location.origin && window.history.length > 1) {
        ev.preventDefault();
        window.history.back();
      }
    } catch (e) { /* bez návratu — platí odkaz */ }
  });
  // Vytasení / výměna zbraně: mimo boj zdarma, v souboji stojí akci (h1606) — server ji odečte a posune tah.
  // Klepnutí na ÚČ v záhlaví vytasí další zbraň z těch, které jde vytasit; „Vytasit“ v tabulce Boj tu zbraň, u které je.
  async function vytasit(zbranId, nazev, zdarma) {
    const koren = document.getElementById('denik');
    const vBoji = !!koren && koren.dataset.boj === '1';
    const bezAkce = !!(document.getElementById('dn-bez-akce') || {}).checked;
    if (vBoji && !bezAkce) {
      const text = zdarma
        ? 'Vytasit ' + (nazev || 'zbraň') + ' bez akce (' + zdarma + ')? Využiješ tím možnost, která se obnoví až příští kolo.'
        : 'Vytasit ' + (nazev || 'zbraň') + '? V boji to stojí jednu akci z tvého tahu.';
      if (!window.confirm(text)) return;
    }
    zaneprazdneno = true;
    try {
      const d = await volej('api/zbran_vytasit.php', { postava_id: Number(ID), zbran_id: zbranId, bez_akce: bezAkce });
      if (d && d.efekt) hlaska((nazev || 'Zbraň') + ' vytasena bez akce (' + d.efekt + ').', true);
      else if (d && !d.zdarma) hlaska((nazev || 'Zbraň') + ' vytasena — stálo to akci' + (d.akci_zbyva !== undefined ? ', v této půlce kola ti jich zbývá ' + d.akci_zbyva : '') + '.', true);
    } finally {
      zaneprazdneno = false;
    }
    await obnov();
  }
  document.addEventListener('click', function (ev) {
    const z = ev.target.closest('[data-dalsi-zbran], [data-vytasit]');
    if (!z || !wrap.contains(z) || z.disabled) return;
    vytasit(Number(z.dataset.dalsiZbran || z.dataset.vytasit), z.dataset.nazev || '', z.dataset.zdarma || '');
  });

  // ----- inventář: hledání a filtr podle druhu (jen zobrazení, nic se neukládá do DB) -----
  const KLIC_FILTR = 'dracak_denik_inv_' + ID;
  let filtr = { typ: '', q: '' };
  try { filtr = Object.assign(filtr, JSON.parse(sessionStorage.getItem(KLIC_FILTR) || '{}')); } catch (e) { /* soukromé okno */ }
  const bezDiakritiky = (t) => String(t).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  function ulozFiltr() {
    try { sessionStorage.setItem(KLIC_FILTR, JSON.stringify(filtr)); } catch (e) { /* soukromé okno */ }
  }
  function aplikujFiltr() {
    const tab = document.getElementById('dn-inv-tab');
    if (!tab) return;
    const hledej = document.getElementById('dn-inv-hledej');
    if (hledej && document.activeElement !== hledej) hledej.value = filtr.q;
    document.querySelectorAll('#denik [data-inv-filtr]').forEach(function (c) {
      c.classList.toggle('on', c.dataset.invFiltr === filtr.typ);
    });
    // Druh, který už v inventáři není (poslední kus odebrán), se nesmí držet jako neviditelný filtr.
    if (filtr.typ && !document.querySelector('#denik [data-inv-filtr="' + filtr.typ + '"]')) filtr.typ = '';
    const q = bezDiakritiky(filtr.q.trim());
    let zobrazeno = 0;
    tab.querySelectorAll('tbody tr[data-inv-typ]').forEach(function (tr) {
      const ok = (!filtr.typ || tr.dataset.invTyp === filtr.typ) && (!q || bezDiakritiky(tr.dataset.invNazev).indexOf(q) >= 0);
      tr.hidden = !ok;
      if (ok) zobrazeno++;
    });
    const nic = document.getElementById('dn-inv-nic');
    if (nic) nic.hidden = zobrazeno > 0;
  }
  document.addEventListener('input', function (ev) {
    if (ev.target.id !== 'dn-inv-hledej' || !wrap.contains(ev.target)) return;
    filtr.q = ev.target.value;
    ulozFiltr();
    aplikujFiltr();
  });
  document.addEventListener('click', function (ev) {
    const c = ev.target.closest('[data-inv-filtr]');
    if (!c || !wrap.contains(c)) return;
    filtr.typ = c.dataset.invFiltr;
    ulozFiltr();
    aplikujFiltr();
  });

  // ----- detail položky z katalogu (hra/api/polozka_detail.php): plovoucí karta po kliknutí na název -----
  let detail = null;   // {typ, id, data} — přežije obnovu deníku
  function vykresliDetail() {
    const el = document.getElementById('dn-detail');
    if (!el) return;
    el.textContent = '';
    if (!detail) { el.hidden = true; return; }
    const d = detail.data;
    const hlava = document.createElement('div');
    hlava.className = 'dn-detail-hlava';
    const nadpis = document.createElement('div');
    const h = document.createElement('h3');
    h.className = 'dn-h3';
    h.textContent = d.nazev;
    const pod = document.createElement('div');
    pod.className = 'note';
    pod.textContent = d.podtitul;
    nadpis.append(h, pod);
    const zavrit = document.createElement('button');
    zavrit.type = 'button';
    zavrit.className = 'dn-detail-zavrit';
    zavrit.setAttribute('aria-label', 'Zavřít detail');
    zavrit.dataset.detailZavrit = '1';
    zavrit.textContent = '✕';
    hlava.append(nadpis, zavrit);
    el.append(hlava);
    if (d.radky.length) {
      const dl = document.createElement('dl');
      dl.className = 'dn-detail-radky';
      d.radky.forEach(function (r) {
        const dt = document.createElement('dt');
        dt.textContent = r[0];
        const dd = document.createElement('dd');
        dd.textContent = r[1];
        dl.append(dt, dd);
      });
      el.append(dl);
    }
    if (d.efekty && d.efekty.length) {
      const p = document.createElement('p');
      p.className = 'dn-detail-efekty';
      p.textContent = 'Efekty: ' + d.efekty.join(', ');
      el.append(p);
    }
    if (d.popis) {
      const p = document.createElement('p');
      p.className = 'dn-detail-popis';
      p.textContent = d.popis;
      el.append(p);
    }
    if (!d.radky.length && !d.popis && !(d.efekty && d.efekty.length)) {
      const p = document.createElement('p');
      p.className = 'note';
      p.textContent = 'V katalogu k téhle položce nejsou další údaje.';
      el.append(p);
    }
    el.hidden = false;
  }
  async function ukazDetail(typ, id) {
    if (detail && detail.typ === typ && detail.id === id) { detail = null; vykresliDetail(); return; }   // druhé klepnutí zavře
    try {
      const r = await fetch('api/polozka_detail.php?typ=' + encodeURIComponent(typ) + '&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
      const data = await r.json();
      if (!r.ok || data.error) throw new Error(data.error || 'Detail se nenačetl.');
      detail = { typ: typ, id: id, data: data };
      vykresliDetail();
    } catch (e) {
      hlaska(e.message);
    }
  }
  document.addEventListener('click', function (ev) {
    const o = ev.target.closest('[data-detail]');
    if (o && wrap.contains(o)) { ukazDetail(o.dataset.detail, Number(o.dataset.id)); return; }
    if (ev.target.closest('[data-detail-zavrit]')) { detail = null; vykresliDetail(); }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && detail) { detail = null; vykresliDetail(); }
  });

  // V mapě (iframe) dá deník mapě vědět, že se něco změnilo — mapa si hned stáhne nový stav (životy, inventář…).
  function oznamZmenu() {
    if (window.parent === window) return;
    try { window.parent.postMessage({ dracak: 'denik_zmena', postava_id: Number(ID) }, window.location.origin); } catch (e) { /* jiný původ */ }
  }
  // …a naopak: mapa řekne deníku, ať se obnoví (zásah v souboji, léčení…).
  window.addEventListener('message', function (ev) {
    if (ev.origin !== window.location.origin || ev.source !== window.parent) return;
    if (ev.data && ev.data.dracak === 'denik_obnov') obnovPokudNepise();
  });

  async function posli(telo) {
    const r = await fetch(API, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ postava_id: Number(ID) }, telo)),
    });
    let data = {};
    try { data = await r.json(); } catch (e) { /* prázdná odpověď */ }
    if (!r.ok || data.error) {
      throw new Error(data.error || 'Zápis se nepovedl (' + r.status + ').');
    }
    oznamZmenu();
    return data;
  }

  async function obnov() {
    if (zaneprazdneno) return;
    zaneprazdneno = true;
    try {
      const r = await fetch('denik.php?id=' + encodeURIComponent(ID) + '&fragment=1' + (wrap.classList.contains('dn-embed') ? '&embed=1' : ''), { credentials: 'same-origin' });
      if (!r.ok) return;
      const html = await r.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const novy = doc.getElementById('denik');
      const stary = document.getElementById('denik');
      if (!novy || !stary) return;
      // Zachovat rozbalené položky.
      const otevrene = new Set(Array.from(stary.querySelectorAll('details[open][data-id]')).map(d => d.dataset.id));
      novy.querySelectorAll('details[data-id]').forEach(d => { if (otevrene.has(d.dataset.id)) d.open = true; });
      const cilPred = document.getElementById('dn-cil');
      const cilHodnota = cilPred ? cilPred.value : null;
      const modPred = document.getElementById('dn-mod');
      const modHodnota = modPred ? modPred.value : null;
      stary.replaceWith(novy);
      ukazTab(aktivniTab);
      aplikujFiltr();
      vykresliDetail();
      const modPo = document.getElementById('dn-mod');
      if (modPo && modHodnota !== null) modPo.value = modHodnota;
      if (cilHodnota !== null) {
        const cilPo = document.getElementById('dn-cil');
        if (cilPo) cilPo.value = cilHodnota;
      }
    } catch (e) {
      /* síť — zkusí se při dalším kole */
    } finally {
      zaneprazdneno = false;
    }
  }

  function hlaska(text, ok) {
    let el = document.getElementById('dn-hlaska');
    if (!el) {
      el = document.createElement('div');
      el.id = 'dn-hlaska';
      el.setAttribute('role', 'alert');
      document.body.appendChild(el);
    }
    el.style.cssText = 'position:fixed;left:50%;bottom:20px;transform:translateX(-50%);color:#fff;padding:12px 20px;border-radius:14px;box-shadow:0 4px 14px rgba(0,0,0,.25);z-index:9999;font-size:15px;line-height:1.45;text-align:center;display:block;background:' + (ok ? '#4f5a38' : '#a3402a');
    el.textContent = text;
    clearTimeout(hlaska.t);
    hlaska.t = setTimeout(() => { el.style.display = 'none'; }, ok ? 9000 : 4500);
  }

  const znam = n => (n > 0 ? '+' : n < 0 ? '−' : '+') + Math.abs(n);

  // Obecné volání API s chybou jako hláškou; vrací data, nebo null.
  async function volej(url, telo) {
    try {
      const r = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(telo),
      });
      let data = {};
      try { data = await r.json(); } catch (e) { /* prázdná odpověď */ }
      if (!r.ok || data.error) { hlaska(data.error || 'Nepovedlo se (' + r.status + ').'); return null; }
      oznamZmenu();
      return data;
    } catch (e) {
      hlaska('Spojení se nepovedlo.');
      return null;
    }
  }

  function ukazHod(d) {
    const kostky = d.hody.join(' + ');
    const opravy = (d.opravy || []).map(o => znam(o.hodnota) + ' ' + o.nazev).join(' ');
    const cislo = d.popis.indexOf('Obrana') === 0 ? 'OČ' : (d.popis.indexOf('Hod 1k6+') === 0 ? null : 'ÚČ');
    hlaska(d.popis + '\n[' + kostky + ']' + (cislo ? ' ' + znam(d.zaklad) + ' ' + cislo : '') + (opravy ? ' ' + opravy : '') + '  =  ' + d.celkem, true);
  }

  async function zapis(telo) {
    zaneprazdneno = true;
    try {
      await posli(telo);
    } catch (e) {
      hlaska(e.message);
    } finally {
      zaneprazdneno = false;
    }
    await obnov();
  }

  document.addEventListener('click', function (ev) {
    const tl = ev.target.closest('[data-akce]');
    if (!tl || !wrap.contains(tl)) return;
    const akce = tl.dataset.akce;
    if (akce === 'text') {
      const pole = tl.dataset.pole;
      const ta = document.querySelector('textarea[data-text-pole="' + pole + '"]');
      if (!ta) return;
      zapis({ akce: 'text', pole: pole, hodnota: ta.value }).then(() => {
        rozepsano = false;
        const znacka = document.querySelector('textarea[data-text-pole="' + pole + '"]');
        const ul = znacka && znacka.parentElement.querySelector('.dn-ulozeno');
        if (ul) { ul.textContent = 'Uloženo.'; setTimeout(() => { ul.textContent = ''; }, 2500); }
      });
      return;
    }
    if (akce === 'spanek') {
      if (window.confirm('Prospat ' + tl.dataset.hodin + ' h: únava klesne o ' + tl.dataset.bodu + ' bodů (jeden stupeň). Postavy mohou spát nejvýš 2× denně.')) {
        zapis({ akce: 'spanek' });
      }
      return;
    }
    if (tl.dataset.delta !== undefined) {
      zapis({ akce: akce, delta: Number(tl.dataset.delta) });
    }
  });

  // Správa inventáře: ± kus, odebrat, zapomenout kouzlo.
  document.addEventListener('click', function (ev) {
    const tl = ev.target.closest('[data-inv]');
    if (!tl || !wrap.contains(tl)) return;
    const typ = tl.dataset.typ;
    if (tl.dataset.inv === 'zmena') {
      zapis({ akce: 'inventar_zmena', typ_polozky: typ, polozka_id: Number(tl.dataset.id), delta: Number(tl.dataset.delta) });
    } else if (tl.dataset.inv === 'odebrat') {
      const co = typ === 'kouzlo' ? 'Zapomenout kouzlo ' : 'Odebrat z inventáře: ';
      if (window.confirm(co + (tl.dataset.nazev || '') + '?')) {
        zapis({ akce: 'inventar_odebrat', typ_polozky: typ, polozka_id: Number(tl.dataset.id) });
      }
    }
  });

  // Kontejnery (hra/api/postava_kontejner.php): vložit, vyndat, odebrat, nosič.
  // Při přeplnění server vrátí upozornění; po potvrzení se pošle znovu.
  async function kontejnerAkce(telo) {
    let d = await volej('api/postava_kontejner.php', Object.assign({ postava_id: Number(ID) }, telo));
    if (d && d.vyzaduje_potvrzeni) {
      if (!window.confirm(d.upozorneni)) return;
      d = await volej('api/postava_kontejner.php', Object.assign({ postava_id: Number(ID), potvrdit: true }, telo));
    }
    if (d && d.upozorneni) hlaska(d.upozorneni, true);
    await obnov();
  }
  document.addEventListener('click', function (ev) {
    const tl = ev.target.closest('[data-kont-akce]');
    if (!tl || !wrap.contains(tl)) return;
    const akce = tl.dataset.kontAkce;
    const kontejner = Number(tl.dataset.kontejner || 0);
    if (akce === 'vloz') {
      const box = tl.closest('.dn-kont');
      const sel = box.querySelector('.dn-kont-predmet');
      const ks = Math.max(1, Number(box.querySelector('.dn-kont-ks').value) || 1);
      kontejnerAkce({ akce: 'vloz', kontejner_id: kontejner, predmet_id: Number(sel.value), mnozstvi: ks });
    } else if (akce === 'vyndej') {
      kontejnerAkce({ akce: 'vyndej', kontejner_id: kontejner, predmet_id: Number(tl.dataset.predmet), mnozstvi: tl.dataset.mnozstvi === '' ? null : Number(tl.dataset.mnozstvi) });
    } else if (akce === 'odebrat') {
      if (window.confirm('Odebrat kontejner ' + (tl.dataset.nazev || '') + '? Věci z něj se vrátí do volného inventáře.')) {
        kontejnerAkce({ akce: 'odebrat', kontejner_id: kontejner });
      }
    } else if (akce === 'prejmenovat') {
      const novy = window.prompt('Jak se má kontejner jmenovat? (prázdné = původní název)', tl.dataset.nazev || '');
      if (novy !== null) kontejnerAkce({ akce: 'prejmenovat', kontejner_id: kontejner, nazev: novy });
    } else if (akce === 'rozbalit') {
      kontejnerAkce({ akce: 'rozbalit', predmet_id: Number(tl.dataset.predmet) });
    }
  });
  document.addEventListener('change', function (ev) {
    const el = ev.target;
    if (!el.matches || !el.matches('.dn-kont-nosic') || !wrap.contains(el)) return;
    kontejnerAkce({ akce: 'nosic', kontejner_id: Number(el.dataset.kontejner), nosic: el.value });
  });

  // Hledání v katalogu (hra/api/katalog_hledat.php) a přidání jedním klikem.
  let hledaniCasovac = null;
  document.addEventListener('input', function (ev) {
    const q = ev.target;
    if (!q.matches || !q.matches('.dn-picker-q') || !wrap.contains(q)) return;
    const box = q.closest('.dn-picker');
    const seznam = box.querySelector('.dn-picker-vysledky');
    rozepsano = q.value.trim() !== '' || rozepsano;
    clearTimeout(hledaniCasovac);
    if (q.value.trim().length < 2) { seznam.hidden = true; seznam.innerHTML = ''; return; }
    hledaniCasovac = setTimeout(async function () {
      const typ = box.querySelector('.dn-picker-typ').value;
      try {
        const r = await fetch('api/katalog_hledat.php?typ=' + encodeURIComponent(typ) + '&q=' + encodeURIComponent(q.value.trim()), { credentials: 'same-origin' });
        const data = await r.json();
        seznam.innerHTML = '';
        (data.vysledky || []).forEach(function (v) {
          const li = document.createElement('li');
          li.tabIndex = 0;
          li.dataset.id = v.id; li.dataset.typ = typ;
          const n = document.createElement('span'); n.textContent = v.nazev;
          const i = document.createElement('small'); i.textContent = v.info || '';
          li.append(n, i);
          seznam.appendChild(li);
        });
        if (!(data.vysledky || []).length) {
          const li = document.createElement('li'); li.className = 'dn-picker-prazdne'; li.textContent = 'Nic nenalezeno.'; seznam.appendChild(li);
        }
        seznam.hidden = false;
      } catch (e) { /* hledání se nepovedlo — zkusí se při dalším psaní */ }
    }, 250);
  });
  function vyberZKatalogu(li) {
    if (!li || !li.dataset.id) return;
    const box = li.closest('.dn-picker');
    const ks = box.querySelector('.dn-picker-ks');
    rozepsano = false;
    zapis({ akce: 'inventar_pridat', typ_polozky: li.dataset.typ, polozka_id: Number(li.dataset.id), mnozstvi: ks ? Number(ks.value) || 1 : 1 });
  }
  document.addEventListener('click', function (ev) {
    const li = ev.target.closest('.dn-picker-vysledky li');
    if (li && wrap.contains(li)) { vyberZKatalogu(li); return; }
    // Klik mimo zavře otevřené výsledky.
    document.querySelectorAll('.dn-picker-vysledky').forEach(function (u) { if (!u.parentElement.contains(ev.target)) u.hidden = true; });
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && ev.target.matches && ev.target.matches('.dn-picker-vysledky li')) vyberZKatalogu(ev.target);
  });

  // Hody na útok / střelbu / obranu (hra/api/hod_postavy.php).
  document.addEventListener('click', async function (ev) {
    const tl = ev.target.closest('[data-hod]');
    if (!tl || !wrap.contains(tl)) return;
    const mod = document.getElementById('dn-mod');
    const d = await volej('api/hod_postavy.php', {
      postava_id: Number(ID), druh: tl.dataset.hod,
      zbran_id: tl.dataset.zbran ? Number(tl.dataset.zbran) : 0,
      modifikator: mod ? Number(mod.value) || 0 : 0,
    });
    if (d) ukazHod(d);
  });

  // Seslat kouzlo / vypít lektvar (hra/api/pouzij_predmet.php).
  document.addEventListener('click', async function (ev) {
    const tl = ev.target.closest('[data-pouzij]');
    if (!tl || !wrap.contains(tl)) return;
    ev.preventDefault();
    // Tabulka únavy: vyčerpaná (a horší) postava nemůže sesílat kouzla.
    if (tl.dataset.unavaZakaz &&
        !window.confirm('Postava je ' + tl.dataset.unavaZakaz + ' a podle Tabulky únavy nemůže sesílat kouzla (ani z předmětů). Přesto seslat ' + tl.dataset.nazev + '?')) return;
    const cilEl = document.getElementById('dn-cil');
    const cil = cilEl ? cilEl.value : '';
    // Zraňující kouzlo/lektvar bez zvoleného cíle by zranilo toho, kdo ho použil.
    if (!cil && tl.dataset.skodi === '1' &&
        !window.confirm(tl.dataset.nazev + ' zraňuje. Cíl je „na sebe“ — opravdu to použít na sebe?')) return;
    const telo = {
      typ_entity: 'postava', entita_id: Number(ID),
      typ_polozky: tl.dataset.pouzij, polozka_id: Number(tl.dataset.polozka),
    };
    if (cil) {
      const [cTyp, cId] = cil.split(':');
      telo.cil_typ_entity = cTyp;
      telo.cil_entita_id = Number(cId);
    }
    let d = await volej('api/pouzij_predmet.php', telo);
    if (d && d.potrebuje_trvani) {
      // Trvání efektu kouzla si volí sesilatel (Rychlost podle magenergie…) — zeptáme se a pošleme znovu.
      const kola = window.prompt(d.polozka_nazev + ': na kolik kol má platit efekt „' + d.efekt + '“? (0 = bez limitu)', '');
      if (kola === null || kola.trim() === '' || isNaN(Number(kola)) || Number(kola) < 0) return;
      telo.zbyva_kol = Math.floor(Number(kola));
      d = await volej('api/pouzij_predmet.php', telo);
    }
    if (!d) return;
    const radky = [(tl.dataset.pouzij === 'kouzlo' ? 'Seslal(a) jsi ' : 'Použil(a) jsi ') + d.polozka_nazev];
    if (d.kostka) radky.push('[' + d.kostka.hody.join(' + ') + (d.kostka.bonus ? ' ' + znam(d.kostka.bonus) : '') + ']  =  ' + d.kostka.celkem);
    if (d.hp) radky.push((d.hp.delta > 0 ? 'Léčení ' : 'Zranění ') + Math.abs(d.hp.delta) + ' → životy ' + d.hp.nove_hp + '/' + d.hp.max_hp);
    (d.efekty || []).filter(e => e.hp_delta === undefined).forEach(e => {
      radky.push('Efekt: ' + e.nazev + (e.zbyva_kol ? ' (' + e.zbyva_kol + ' kol)' : '') + (e.obnoveno ? ' – prodlouženo' : '')
        + (e.shrnuti && e.shrnuti.length ? ' — ' + e.shrnuti.join(', ') : ''));
      if (e.akce) radky.push('Akce ' + e.akce.pred[1] + ' → ' + e.akce.po[1]);
    });
    (d.ceka_na_past || []).forEach(e => radky.push('Cíl se brání hodem proti pasti (' + e.past + '). Při neúspěchu mu PJ přidá efekt ' + e.nazev + '.'));
    if (d.magenergie) radky.push('Magenergie −' + d.magenergie.cena + ' → ' + d.magenergie.nova_magenergie + '/' + d.magenergie.max_magenergie);
    hlaska(radky.join('\n'), true);
    await obnov();
  });

  // Hod kostek schopnosti (hra/api/kostka_hod.php, jen ve světě).
  document.addEventListener('click', async function (ev) {
    const tl = ev.target.closest('[data-kostka]');
    if (!tl || !wrap.contains(tl)) return;
    ev.preventDefault();
    const d = await volej('api/kostka_hod.php', { svet_id: Number(wrap.dataset.svetId), notace: tl.dataset.kostka });
    if (d) hlaska(tl.dataset.nazev + ' — ' + d.notace + '\n[' + d.hody.join(' + ') + (d.bonus ? ' ' + znam(d.bonus) : '') + ']  =  ' + d.celkem, true);
  });

  document.addEventListener('change', function (ev) {
    const el = ev.target;
    if (!el.matches || !el.matches('[data-zmena]') || !wrap.contains(el)) return;
    const zmena = el.dataset.zmena;
    if (zmena === 'vyber') {
      zapis({ akce: 'vyber', pole: el.dataset.pole, hodnota: Number(el.value) || 0 });
    } else if (zmena === 'zbran_u_sebe') {
      zapis({ akce: 'zbran_u_sebe', polozka_id: Number(el.dataset.id), hodnota: el.checked ? 1 : 0 });
    } else if (zmena === 'penize') {
      zapis({ akce: 'penize', mena: el.dataset.mena, hodnota: Math.max(0, Number(el.value) || 0) });
    } else if (zmena === 'velikost') {
      zapis({ akce: 'velikost', hodnota: el.value });
    } else if (zmena === 'pohyblivost_rucne') {
      zapis({ akce: 'pohyblivost_rucne', hodnota: el.value === '' ? null : Number(el.value) });
    }
  });

  document.addEventListener('input', function (ev) {
    if (ev.target.matches && ev.target.matches('textarea[data-text-pole]')) rozepsano = true;
  });

  // Polling změn od jiných klientů (PJ strhne životy…): každých 5 s, ne když
  // je karta skrytá, hráč píše, nebo má rozepsaný text.
  function obnovPokudNepise() {
    if (document.hidden || rozepsano) return;
    const a = document.activeElement;
    if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return;
    obnov();
  }
  setInterval(obnovPokudNepise, 5000);
  ukazTab(aktivniTab);
  aplikujFiltr();
})();
