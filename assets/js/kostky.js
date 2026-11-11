// „Hoď si“ a kostka, kterou vidí celý stůl (zadání uživatele 2026-11-11; návrh a jeho připomínky
// na plátně https://claude.ai/artifact/RZz5NCY8rSRKjty6fm7btz).
//
// Dvě věci v jednom modulu, protože spolu souvisí:
//   1) VÝZVA — PJ klikne na token hráče a pošle „hoď si“ (k6 / k10 / k100, nebo „čím chceš“).
//      Hráči vyskočí malé okno. Když PJ kostku určil, je v okně jen tlačítko Hodit a křížek; když ji nechal
//      na hráči, vybere si ji hráč sám. (Rozhodnutí uživatele: buď je to příkaz, nebo volba hráče, nic mezi.)
//   2) KOSTKA PRO VŠECHNY — každý hod ve světě se všem ukáže vpravo dole: kostka se zatočí, dopadne
//      a zůstane na ní číslo. Tím to končí — jestli hod prošel, řeší PJ, aplikace nic nevyhodnocuje.
//      Zvuk se dá vypnout a pamatuje si to prohlížeč.
//
// Hází SERVER (hra/api/kostka_hod.php), ne prohlížeč: jinak by šlo v konzoli přepsat, co padlo, a hod
// „pro všechny“ by nic nedokazoval.

const KLIC_ZVUK = 'dracak_kostky_zvuk';
const DOBA_LETU = 700;        // ms, než kostka dopadne (uživatel: „zkusme 0,7 na celou animaci“)
const DOBA_STANI = 4200;      // ms, jak dlouho pak číslo zůstane

export function initKostky(ctx) {
  const { postJson, logLine, jsemPj, mujUcetId } = ctx;
  const vrstva = document.getElementById('kostkyVrstva');
  const dialog = document.getElementById('hodVyzvaDialog');
  if (!vrstva) return null;

  let zvuk = true;
  try { zvuk = localStorage.getItem(KLIC_ZVUK) !== '0'; } catch (e) { /* soukromé okno */ }
  let audioCtx = null;
  let vyzvaOtevrena = null;   // {vyzva_id, postava_id, label, kostka}
  const vlastni3d = new Set();  // id událostí, které si tenhle prohlížeč odbyl 3D kostkou

  // --- zvuk: krátké cvaknutí dopadu, vyrobené v prohlížeči (žádný soubor navíc) ---
  function cvaknout() {
    if (!zvuk) return;
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      const t = audioCtx.currentTime;
      const osc = audioCtx.createOscillator();
      const zisk = audioCtx.createGain();
      osc.type = 'triangle';
      osc.frequency.setValueAtTime(220, t);
      osc.frequency.exponentialRampToValueAtTime(90, t + 0.12);
      zisk.gain.setValueAtTime(0.14, t);
      zisk.gain.exponentialRampToValueAtTime(0.001, t + 0.18);
      osc.connect(zisk).connect(audioCtx.destination);
      osc.start(t);
      osc.stop(t + 0.2);
    } catch (e) { /* prohlížeč zvuk nepustí, nevadí */ }
  }

  // --- kostka na obrazovce ---
  // Vpravo dole, asi třetinu od hran (volba uživatele) — drží se nad mapou, ale neleze do lišty.
  function ukazHod(udalost) {
    const box = document.createElement('div');
    box.className = 'kostka-hod';
    const kostka = document.createElement('div');
    kostka.className = 'kostka-telo';
    kostka.textContent = '';
    const cislo = document.createElement('strong');
    cislo.className = 'kostka-cislo';
    cislo.textContent = '?';
    kostka.appendChild(cislo);
    const popis = document.createElement('div');
    popis.className = 'kostka-popis';
    popis.textContent = (udalost.hodil || '?') + ' · ' + (udalost.notace || '');
    box.appendChild(kostka);
    box.appendChild(popis);
    vrstva.appendChild(box);

    requestAnimationFrame(() => box.classList.add('leti'));
    setTimeout(() => {
      box.classList.remove('leti');
      box.classList.add('dopadlo');
      cislo.textContent = String(udalost.celkem);
      cvaknout();
    }, DOBA_LETU);
    setTimeout(() => {
      box.classList.add('mizi');
      setTimeout(() => box.remove(), 400);
    }, DOBA_LETU + DOBA_STANI);
  }

  // --- okno s výzvou u hráče ---
  function zavritVyzvu() {
    vyzvaOtevrena = null;
    if (dialog) dialog.hidden = true;
  }

  function otevritVyzvu(v) {
    if (!dialog) return;
    vyzvaOtevrena = v;
    dialog.hidden = false;
    const nadpis = dialog.querySelector('[data-vyzva-nadpis]');
    const volba = dialog.querySelector('[data-vyzva-volba]');
    const prikaz = dialog.querySelector('[data-vyzva-prikaz]');
    const tlacitko = dialog.querySelector('[data-vyzva-hodit]');
    if (v.kostka) {
      nadpis.textContent = 'Hoď si k' + v.kostka;
      volba.hidden = true;
      prikaz.hidden = false;
      tlacitko.textContent = 'Hodit k' + v.kostka;
      tlacitko.dataset.kostka = String(v.kostka);
    } else {
      nadpis.textContent = 'Hoď si';
      volba.hidden = false;
      prikaz.hidden = true;
      volba.querySelectorAll('[data-vyzva-kostka]').forEach((b) => b.classList.remove('on'));
    }
  }

  async function hodit(typKostky) {
    const v = vyzvaOtevrena;
    zavritVyzvu();
    // Kdo hází, hodí si 3D kostkou (stejně jako z házecího místa); čísla z ní jsou ten hod
    // a posílají se na server. Bez 3D kostek (starý prohlížeč, k100) hodí server.
    let hody = null;
    if (ctx.hodit3D && [4, 6, 8, 10, 12, 20].includes(typKostky)) hody = await ctx.hodit3D(1, typKostky);
    const d = await postJson('api/kostka_hod.php', Object.assign({
      svet_id: ctx.svetId, mapa_id: ctx.mapaId, notace: 'k' + typKostky,
      vyzva_id: v ? v.vyzva_id : null, za_koho: v ? v.label : '',
    }, hody ? { hody } : {}));
    if (d && d.error) { logLine('Chyba: ' + d.error); return; }
    // Vlastní hod si hazeč ukázal 3D kostkou — ať mu ho poll neukáže ještě jako kartu.
    if (hody && d && d.udalost_id) vlastni3d.add(Number(d.udalost_id));
  }

  if (dialog) {
    dialog.querySelectorAll('[data-vyzva-kostka]').forEach((b) => {
      b.addEventListener('click', () => hodit(parseInt(b.dataset.vyzvaKostka, 10)));
    });
    const tl = dialog.querySelector('[data-vyzva-hodit]');
    if (tl) tl.addEventListener('click', () => hodit(parseInt(tl.dataset.kostka, 10)));
    const zav = dialog.querySelector('[data-vyzva-zavrit]');
    if (zav) zav.addEventListener('click', zavritVyzvu);
  }

  const prepinacZvuku = document.getElementById('kostkyZvuk');
  if (prepinacZvuku) {
    prepinacZvuku.checked = zvuk;
    prepinacZvuku.addEventListener('change', () => {
      zvuk = prepinacZvuku.checked;
      try { localStorage.setItem(KLIC_ZVUK, zvuk ? '1' : '0'); } catch (e) { /* nevadí */ }
    });
  }

  return {
    // Výzva došla pollem: ukáže se jen tomu, kdo tu postavu hraje. PJ ji nevidí (poslal ji sám),
    // ledaže hraje postavu i on.
    vyzva(payload) {
      // Okno patří tomu, kdo postavu HRAJE — tedy jejímu vlastníkovi. PJ vidí v deníku všechny postavy
      // světa, takže podle „mám ji v nabídce“ by si výzvu otevřel i sám sobě. Postava bez vlastníka
      // (NPC vedené PJ) je výjimka: tam okno dostane PJ.
      const maVlastnika = payload.vlastnik_ucet_id !== null && payload.vlastnik_ucet_id !== undefined;
      const pro = maVlastnika ? Number(payload.vlastnik_ucet_id) === Number(mujUcetId) : !!jsemPj;
      if (!pro) return;
      otevritVyzvu({
        vyzva_id: payload.vyzva_id || null,
        postava_id: Number(payload.postava_id),
        label: String(payload.label || ''),
        kostka: payload.kostka ? Number(payload.kostka) : null,
      });
    },
    // Hod došel pollem (nebo ho hodil tenhle prohlížeč): ukáže se všem stejně.
    hod(payload) {
      const vlastni = payload.udalost_id && vlastni3d.delete(Number(payload.udalost_id));
      if (!vlastni) ukazHod(payload);
      // Výzva, na kterou se odpovědělo, u hráče zmizí, i kdyby okno nechal otevřené.
      if (vyzvaOtevrena && payload.vyzva_id && Number(payload.vyzva_id) === Number(vyzvaOtevrena.vyzva_id)) zavritVyzvu();
    },
    // PJ: pošli výzvu na token. kostka = 6 | 10 | 100 | null („čím chceš“).
    async vyzvat(tokenId, kostka) {
      if (!jsemPj) return;
      const d = await postJson('api/hod_vyzva.php', { mapa_id: ctx.mapaId, token_id: tokenId, kostka: kostka || null });
      if (d && d.error) { logLine('Chyba: ' + d.error); return; }
      logLine('Výzva k hodu odeslána' + (kostka ? ' (k' + kostka + ')' : '') + '.');
    },
    jeOtevrena: () => !!vyzvaOtevrena,
  };
}
