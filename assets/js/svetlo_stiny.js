// Světlo a stíny na mapě „Noc“ / „Tma“ (docs/videni-a-svetlo-navrh.md, fáze 6): plynulý přechod světla od zdroje ke kraji dosahu a stín za zdmi s hladkou hranou
// místo kostiček mlhy. Čistě vykreslení — co hráč vidí z pohledu pravidel (tokeny, paměť mlhy), počítá dál server po buňkách.
//
// Server (includes/vtt_videni.php, dracak_vtt_videni_geometrie) pošle jen to, co hráč smí vědět: mnohoúhelníky viditelnosti jeho tokenů a těch zdrojů světla,
// které osvětlují něco, co zrovna vidí; zdi ani skryté zdroje klient nedostane. Tady se z nich složí „světelná“ vrstva a vyřízne z mlhy.
//
//   geom = { px_sah, svetla: [{x, y, r, poly: [[x, y], ...], typ}], divaci: [{x, y, noc_r, poly}] }   (všechno v pixelech mapy)
//
// dracakSvetloVyrizni(ctx, w, h, geom): ctx je kontext plátna s mlhou (w × h px mapy), na kterém je už nakreslená tma (paměť ztlumeně, neviděné plně);
// z ní se vyřízne světlo: tam, kde je plné světlo, tma zmizí, kde slábne, zůstává část. Pak se přidá jemný teplý nádech u zdrojů.

const SVETLO_MAX_ROZMER = 2048;      // světelná vrstva se kreslí nejvýš v tomhle rozlišení a pak se roztáhne (přechody jsou hladké, nevadí)
const SVETLO_NADECH = 'rgba(255,170,90,0.22)';

// Průběh jasu od zdroje (0) ke kraji dosahu (1): u zdroje plno, dál plynule slábne, na kraji nic.
const SVETLO_PRUBEH = [[0, 1], [0.2, 0.9], [0.45, 0.6], [0.75, 0.22], [1, 0]];
// Změkčení hran stínu (polostín): zhruba tolik sáhu (hrana přechází plynule místo ostré čáry).
const SVETLO_POLOSTIN_SAHY = 0.1;

function svetloCestaMnohouhelniku(c, poly) {
  c.beginPath();
  for (let i = 0; i < poly.length; i++) {
    if (i === 0) c.moveTo(poly[i][0], poly[i][1]); else c.lineTo(poly[i][0], poly[i][1]);
  }
  c.closePath();
}

function svetloPrechod(c, x, y, r, nasobek) {
  const g = c.createRadialGradient(x, y, 0, x, y, r);
  for (const [pozice, jas] of SVETLO_PRUBEH) g.addColorStop(pozice, 'rgba(255,255,255,' + (jas * nasobek).toFixed(3) + ')');
  return g;
}

function rozmaz(zdroj, w, h, polomer) {
  const c = document.createElement('canvas');
  c.width = w; c.height = h;
  const x = c.getContext('2d');
  if ('filter' in x) {
    x.filter = 'blur(' + polomer.toFixed(1) + 'px)';
    x.drawImage(zdroj, 0, 0);
    x.filter = 'none';
    return c;
  }
  const f = Math.max(2, Math.round(polomer));
  const m = document.createElement('canvas');
  m.width = Math.max(1, Math.round(w / f)); m.height = Math.max(1, Math.round(h / f));
  const mc = m.getContext('2d');
  mc.imageSmoothingQuality = 'high';
  mc.drawImage(zdroj, 0, 0, m.width, m.height);
  x.imageSmoothingQuality = 'high';
  x.drawImage(m, 0, 0, w, h);
  return c;
}

export function dracakSvetloVyrizni(ctx, w, h, geom) {
  if (!geom || !w || !h) return;
  const k = Math.min(1, SVETLO_MAX_ROZMER / Math.max(w, h));
  const lw = Math.max(1, Math.round(w * k)), lh = Math.max(1, Math.round(h * k));
  const vrstva = document.createElement('canvas');
  vrstva.width = lw; vrstva.height = lh;
  const c = vrstva.getContext('2d');
  c.scale(lw / w, lh / h);
  c.globalCompositeOperation = 'lighter';

  // Zdroje světla: přechod ořezaný mnohoúhelníkem, kam zdroj dosvítí (za zdí je stín). Dohořívající světlo (sila < 1) je na pohled menší a slabší,
  // dosah podle pravidel se tím nemění.
  for (const s of geom.svetla || []) {
    const sila = typeof s.sila === 'number' ? Math.max(0.1, Math.min(1, s.sila)) : 1;
    const rKresli = s.r * (0.5 + 0.5 * sila);
    c.save();
    svetloCestaMnohouhelniku(c, s.poly);
    c.clip();
    c.fillStyle = svetloPrechod(c, s.x, s.y, rKresli, 0.35 + 0.65 * sila);
    c.fillRect(s.x - rKresli, s.y - rKresli, rKresli * 2, rKresli * 2);
    c.restore();
  }
  // Divák: pár kroků kolem sebe vidí vždycky (vlastní políčko ani ve tmě nezmizí) a s nočním viděním do jeho dosahu (slabší než světlo, plynule slábne).
  for (const d of geom.divaci || []) {
    c.save();
    svetloCestaMnohouhelniku(c, d.poly);
    c.clip();
    const okoli = Math.max(8, (geom.px_sah || 50) * 0.9);
    c.fillStyle = svetloPrechod(c, d.x, d.y, okoli, 1);
    c.fillRect(d.x - okoli, d.y - okoli, okoli * 2, okoli * 2);
    if (d.noc_r > 0) {
      c.fillStyle = svetloPrechod(c, d.x, d.y, d.noc_r, 0.6);
      c.fillRect(d.x - d.noc_r, d.y - d.noc_r, d.noc_r * 2, d.noc_r * 2);
    }
    c.restore();
  }
  // Vidět je jen to, co leží v zorném poli některého diváka (světlo za jeho zády za zdí nic neosvětlí). Přehled světla pro PJ divaky nemá (bez_divaku).
  if (!geom.bez_divaku) {
    c.globalCompositeOperation = 'destination-in';
    c.fillStyle = '#000';
    c.beginPath();
    for (const d of geom.divaci || []) {
      for (let i = 0; i < d.poly.length; i++) {
        if (i === 0) c.moveTo(d.poly[i][0], d.poly[i][1]); else c.lineTo(d.poly[i][0], d.poly[i][1]);
      }
      c.closePath();
    }
    c.fill('nonzero');
  }

  // Polostín: hrany stínu se změkčí (ctx.filter je v Safari nedostupný — tam se světlo zmenší a zase zvětší, což rozmaže podobně).
  const polomer = Math.max(1, (geom.px_sah || 50) * SVETLO_POLOSTIN_SAHY * (lw / w));
  const jemna = rozmaz(vrstva, lw, lh, polomer);

  // Světlo vyřízne z tmy …
  ctx.save();
  ctx.globalCompositeOperation = 'destination-out';
  ctx.drawImage(jemna, 0, 0, w, h);
  // … a zdroje dostanou jemný teplý nádech.
  const nadech = document.createElement('canvas');
  nadech.width = lw; nadech.height = lh;
  const n = nadech.getContext('2d');
  n.drawImage(jemna, 0, 0);
  n.globalCompositeOperation = 'source-in';
  n.fillStyle = SVETLO_NADECH;
  n.fillRect(0, 0, lw, lh);
  ctx.globalCompositeOperation = 'source-over';
  ctx.drawImage(nadech, 0, 0, w, h);
  ctx.restore();
}

