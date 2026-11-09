// Dotyk (emulace): tažení tokenu prstem, posun prázdného místa jedním prstem a pinch dvěma prsty na telefonu (390×844) a tabletu (820×1180).
// Dotyk se posílá přes Chrome DevTools (Input.dispatchTouchEvent), tedy skutečné dotykové události, ne myš. Nenahradí zkoušku na skutečném zařízení.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node dotyk_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  for (const z of [{ n: 'telefon', w: 390, h: 844 }, { n: 'tablet', w: 820, h: 1180 }]) {
    const ctx = await browser.newContext({ viewport: { width: z.w, height: z.h }, hasTouch: true, isMobile: true });
    const p = await ctx.newPage(); const chybyJs = []; p.on('pageerror', (e) => chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', 'test@local'); await p.fill('#password', 'test1234');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(BASE + '/hra/mapa.php?id=1'); await p.waitForTimeout(1500);
    const cdp = await ctx.newCDPSession(p);
    const dotyk = (typ, body) => cdp.send('Input.dispatchTouchEvent', { type: typ, touchPoints: body.map((b, i) => ({ x: b.x, y: b.y, id: i })) });
    const tah = async (od, do_, kroku = 12) => {
      await dotyk('touchStart', [od]);
      for (let i = 1; i <= kroku; i++) { await dotyk('touchMove', [{ x: od.x + (do_.x - od.x) * i / kroku, y: od.y + (do_.y - od.y) * i / kroku }]); await p.waitForTimeout(16); }
      await dotyk('touchEnd', []); await p.waitForTimeout(500);
    };
    const wrap = () => p.evaluate(() => getComputedStyle(document.getElementById('mapWrap')).transform);
    const stred = (sel) => p.evaluate((s) => { const e = document.querySelector(s); if (!e) return null; const r = e.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; }, sel);

    // 1) Tažení tokenu prstem přesune token na serveru (PJ smí všechno).
    const tok = await stred('.vtt-token[data-id="2"]');
    if (!tok || tok.x < 0 || tok.x > z.w || tok.y < 0 || tok.y > z.h) { ok(false, z.n + ': token Sindor není na obrazovce (' + JSON.stringify(tok) + ')'); }
    else {
      const x0 = require('child_process').execSync('mysql dracak -N -e "select x from tokeny where id=2"').toString().trim();
      await tah(tok, { x: tok.x + 40, y: tok.y + 30 }, 10); await p.waitForTimeout(800);
      const x1 = require('child_process').execSync('mysql dracak -N -e "select x from tokeny where id=2"').toString().trim();
      ok(x0 !== x1, z.n + ': tažení tokenu prstem uložilo novou polohu (x ' + x0 + ' → ' + x1 + ')');
    }
    // 2) Posun prázdného místa jedním prstem změní pohled.
    const pred = await wrap();
    await tah({ x: z.w * 0.5, y: z.h * 0.75 }, { x: z.w * 0.35, y: z.h * 0.65 });
    ok(await wrap() !== pred, z.n + ': tažení jedním prstem po prázdném místě posune mapu');

    // 3) Pinch dvěma prsty změní zvětšení.
    const m1 = await wrap(); const sc1 = parseFloat((m1.match(/matrix\(([^,]+)/) || [])[1]);
    const cx = z.w / 2, cy = z.h * 0.6; let r0 = 40;
    await dotyk('touchStart', [{ x: cx - r0, y: cy }, { x: cx + r0, y: cy }]);
    for (let i = 1; i <= 10; i++) { const r = r0 + i * 12; await dotyk('touchMove', [{ x: cx - r, y: cy }, { x: cx + r, y: cy }]); await p.waitForTimeout(16); }
    await dotyk('touchEnd', []); await p.waitForTimeout(500);
    const m2 = await wrap(); const sc2 = parseFloat((m2.match(/matrix\(([^,]+)/) || [])[1]);
    ok(sc2 > sc1 * 1.3, z.n + ': pinch dvěma prsty přiblíží (' + sc1.toFixed(2) + ' → ' + sc2.toFixed(2) + ')');

    await p.waitForTimeout(300);
    ok(chybyJs.length === 0, z.n + ': žádná chyba JavaScriptu ' + chybyJs.join('|'));
    await ctx.close();
  }
  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
