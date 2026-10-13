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
      stary.replaceWith(novy);
    } catch (e) {
      /* síť — zkusí se při dalším kole */
    } finally {
      zaneprazdneno = false;
    }
  }

  function hlaska(text) {
    let el = document.getElementById('dn-hlaska');
    if (!el) {
      el = document.createElement('div');
      el.id = 'dn-hlaska';
      el.setAttribute('role', 'alert');
      el.style.cssText = 'position:fixed;left:50%;bottom:20px;transform:translateX(-50%);background:#a3402a;color:#fff;padding:10px 18px;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.25);z-index:9999;font-size:14px;';
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.style.display = 'block';
    clearTimeout(hlaska.t);
    hlaska.t = setTimeout(() => { el.style.display = 'none'; }, 4000);
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
