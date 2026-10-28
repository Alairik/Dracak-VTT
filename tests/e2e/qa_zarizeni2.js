// Vizuální kontrola mapy a deníku na telefonu a tabletu (hráč i PJ), screenshoty do scratchpadu.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = (process.env.BASE || 'http://127.0.0.1:8099');
const OUT = (process.env.OUT || '/tmp/dracak-tests') + '/';
require('fs').mkdirSync(OUT, { recursive: true });
const zarizeni = [
  { n: 'telefon', w: 390, h: 844, touch: true },
  { n: 'tablet-na-vysku', w: 820, h: 1180, touch: true },
  { n: 'tablet-na-sirku', w: 1180, h: 820, touch: true },
];
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  for (const z of zarizeni) {
    for (const [role, email, heslo] of [['hrac', 'hrac@local', 'hrac1234'], ['pj', 'test@local', 'test1234']]) {
      const ctx = await browser.newContext({ viewport: { width: z.w, height: z.h }, hasTouch: z.touch, isMobile: z.touch, deviceScaleFactor: 1 });
      const page = await ctx.newPage();
      const chyby = [];
      page.on('pageerror', e => chyby.push(e.message));
      await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
      await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
      await page.goto(BASE + '/hra/mapa.php?id=2'); await page.waitForTimeout(1200);
      await page.screenshot({ path: OUT + `${z.n}_${role}_mapa.png` });
      const pretece = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
      console.log(`${z.n} ${role} mapa: scrollWidth ${pretece.sw} / ${pretece.cw}${pretece.sw > pretece.cw + 1 ? '  ← PŘETÉKÁ' : ''}${chyby.length ? ' JS: ' + chyby.join('; ') : ''}`);
      if (role === 'hrac') {
        await page.goto(BASE + '/hra/denik.php?id=2'); await page.waitForTimeout(800);
        await page.screenshot({ path: OUT + `${z.n}_${role}_denik.png` });
        const p2 = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
        console.log(`${z.n} ${role} deník: scrollWidth ${p2.sw} / ${p2.cw}${p2.sw > p2.cw + 1 ? '  ← PŘETÉKÁ' : ''}`);
      }
      await ctx.close();
    }
  }
  await browser.close();
})();
