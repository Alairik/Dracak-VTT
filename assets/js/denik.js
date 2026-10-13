// Osobní deník (hra/denik.php): rychlé zápisy přes hra/api/postava_stav.php,
// po každé změně i při pollingu se překreslí jen blok #denik (fragment).
// Rozbalené <details> a rozepsané texty se zachovají; polling se přeskočí,
// dokud hráč píše do pole nebo má rozepsaný text.
(function () {
  const wrap = document.querySelector('.dn-wrap');
  if (!wrap) return;
  const ID = wrap.dataset.postavaId;
  const API = 'api/postava_stav.php';
  let zaneprazdneno = false;
  let rozepsano = false;

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
    return data;
  }

  async function obnov() {
    if (zaneprazdneno) return;
    zaneprazdneno = true;
    try {
      const r = await fetch('denik.php?id=' + encodeURIComponent(ID) + '&fragment=1', { credentials: 'same-origin' });
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
    const d = await volej('api/pouzij_predmet.php', telo);
    if (!d) return;
    const radky = [(tl.dataset.pouzij === 'kouzlo' ? 'Seslal(a) jsi ' : 'Použil(a) jsi ') + d.polozka_nazev];
    if (d.kostka) radky.push('[' + d.kostka.hody.join(' + ') + (d.kostka.bonus ? ' ' + znam(d.kostka.bonus) : '') + ']  =  ' + d.kostka.celkem);
    if (d.hp) radky.push((d.hp.delta > 0 ? 'Léčení ' : 'Zranění ') + Math.abs(d.hp.delta) + ' → životy ' + d.hp.nove_hp + '/' + d.hp.max_hp);
    (d.efekty || []).filter(e => e.hp_delta === undefined).forEach(e => radky.push('Efekt: ' + e.nazev));
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
  setInterval(function () {
    if (document.hidden || rozepsano) return;
    const a = document.activeElement;
    if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return;
    obnov();
  }, 5000);
})();
