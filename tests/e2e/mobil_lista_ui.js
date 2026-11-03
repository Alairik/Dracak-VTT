// Telefon (390×844): horní lišta se láme do řádků a všechno v ní je vidět (hodiny s tlačítky posunu času pro PJ, odkaz Pravidla), raid panel je pruh přes šířku
// pod lištou (dřív ho přebila základní pravidla ve stylu a překrýval lištu), stránka nepřetéká vodorovně, pravidla taky. Po resetu dat: mysql dracak < ../fixture/seed.sql; node mobil_lista_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const otevri = async (email, heslo) => {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true });
    const p = await ctx.newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(BASE + '/hra/mapa.php?id=1'); await p.waitForTimeout(1200);
    return p;
  };
  const rect = (p, sel) => p.evaluate((s) => { const e = document.querySelector(s); if (!e) return null; const r = e.getBoundingClientRect(); return { l: r.left, t: r.top, r: r.right, b: r.bottom, w: r.width, h: r.height }; }, sel);

  for (const [kdo, email, heslo] of [['PJ', 'test@local', 'test1234'], ['hráč', 'hrac@local', 'hrac1234']]) {
    const p = await otevri(email, heslo);
    const sirka = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
    ok(sirka.sw <= sirka.cw, kdo + ': stránka nepřetéká vodorovně (' + sirka.sw + ' / ' + sirka.cw + ')');
    const lista = await rect(p, '.topbar');
    ok(lista && lista.l >= 0 && lista.r <= 390.5, kdo + ': horní lišta se vejde do šířky (' + (lista && Math.round(lista.l) + '–' + Math.round(lista.r)) + ')');
    const pravidla = await rect(p, '.topbar-pravidla');
    ok(pravidla && pravidla.r <= 390.5 && pravidla.l >= 0, kdo + ': odkaz Pravidla je celý vidět');
    if (kdo === 'PJ') {
      const tl = await rect(p, '[data-cas-posun="6"]');
      ok(tl && tl.r <= 390.5 && tl.l >= 0, 'PJ: tlačítko „+1 hodina“ je celé vidět (pravý okraj ' + (tl && Math.round(tl.r)) + ')');
    }
    await p.click('#tb-raid'); await p.waitForTimeout(400);
    const panel = await rect(p, '#raidPanel');
    ok(panel && panel.t >= lista.b - 1, kdo + ': raid panel začíná pod horní lištou (' + (panel && Math.round(panel.t)) + ' ≥ ' + Math.round(lista.b) + ')');
    ok(panel && panel.l < 20 && panel.r > 370, kdo + ': raid panel je pruh přes šířku (' + (panel && Math.round(panel.l)) + '–' + (panel && Math.round(panel.r)) + ')');
    // Pravidla na telefonu: dlouhé adresy zdrojů a štítky řádku vlastností se zalamují, stránka se nenatahuje do šířky.
    await p.goto(BASE + '/pravidla.php'); await p.waitForTimeout(600);
    const sirkaPravidla = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
    ok(sirkaPravidla.sw <= sirkaPravidla.cw, kdo + ': pravidla.php nepřetékají vodorovně (' + sirkaPravidla.sw + ' / ' + sirkaPravidla.cw + ')');
    ok(p.chybyJs.length === 0, kdo + ': žádná chyba JavaScriptu: ' + p.chybyJs.join('|'));
    await p.context().close();
  }
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
