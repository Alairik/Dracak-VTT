// „Opice“: náhodné klikání a psaní v rozhraní (mapa, deník, svět, administrace) jako PJ i hráč. Sbírá chyby JavaScriptu na stránce,
// odpovědi serveru 5xx a záznamy v chybovém logu. Cílem je najít pád, který by hráč zažil jako „nic se nestalo po kliknutí“.
//   SEED=1 KROKU=150 node monkey_ui.js        (před spuštěním reset dat: mysql dracak < ../fixture/seed.sql)
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const ROOT = process.env.DOCROOT || path.resolve(__dirname, '../..');
const LOG = ROOT + '/logs/aplikace.log';
const SEED = parseInt(process.env.SEED || '1', 10);
const KROKU = parseInt(process.env.KROKU || '120', 10);
function rng(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
const R = rng(SEED);
const pick = (a) => a[Math.floor(R() * a.length)];
const texty = ['', '1', '5', '-3', '99999999', 'ěščř', '<b>x</b>', 'x'.repeat(400), '0', '3.5', 'Sindor'];
// Scénáře: [popis, přihlášení, adresa]. Odhlášení a mazání celých světů se přeskakuje, mazání v dialogu se odmítne.
const scenare = [
  ['mapa PJ (hex)', 'test@local', 'test1234', '/hra/mapa.php?id=2'],
  ['mapa hráč (čtverec)', 'hrac@local', 'hrac1234', '/hra/mapa.php?id=1'],
  ['deník hráč', 'hrac@local', 'hrac1234', '/hra/denik.php?id=2'],
  ['svět PJ', 'test@local', 'test1234', '/hra/svet.php?id=1'],
  ['správa světa PJ', 'test@local', 'test1234', '/hra/svet_administrace.php?id=1'],
  ['administrace', 'test@local', 'test1234', '/admin.php'],
];
const NEBEZPECNE = /odhl[aá]sit|logout|smazat sv[eě]t|smazat ú[cč]et|vymazat log|st[aá]hnout z[aá]lohu|spustit [cč]ekaj[ií]c[ií]/i;
(async () => {
  try { fs.unlinkSync(LOG); } catch (e) {}
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const nalezy = [];
  let kliknuti = 0;
  for (const [popis, email, heslo, adresa] of scenare) {
    const ctx = await browser.newContext({ viewport: pick([{ width: 1280, height: 800 }, { width: 820, height: 1180 }, { width: 390, height: 844 }]) });
    const page = await ctx.newPage();
    page.on('dialog', d => (R() < 0.5 ? d.dismiss() : d.accept(pick(texty))).catch(() => {}));
    page.on('pageerror', e => nalezy.push({ popis, druh: 'JS chyba', text: String(e.message).slice(0, 200) }));
    page.on('response', r => { if (r.status() >= 500) nalezy.push({ popis, druh: 'server ' + r.status(), text: r.url().replace(BASE, '').slice(0, 160) }); });
    await page.goto(BASE + '/index.php'); await page.fill('#email', email); await page.fill('#password', heslo);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    await page.goto(BASE + adresa, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    for (let i = 0; i < KROKU; i++) {
      try {
        if (!page.url().startsWith(BASE) || /logout|index\.php/.test(page.url())) { await page.goto(BASE + adresa, { waitUntil: 'domcontentloaded' }); await page.waitForTimeout(300); }
        const r = R();
        if (r < 0.12) { await page.keyboard.press(pick(['Escape', 'Enter', 'Tab', 'q', 'e', 'l', 'h', 'v', 'Delete', 'ArrowLeft', 'ctrl+a', 'ctrl+d', '0', '+', '-', ' '])); }
        else if (r < 0.27) {
          const vp = page.viewportSize();
          await page.mouse.click(Math.floor(R() * vp.width), Math.floor(R() * vp.height), { button: pick(['left', 'left', 'left', 'right']) });
        } else if (r < 0.37) {
          const vp = page.viewportSize();
          await page.mouse.move(R() * vp.width, R() * vp.height); await page.mouse.down(); await page.mouse.move(R() * vp.width, R() * vp.height, { steps: 4 }); await page.mouse.up();
        } else if (r < 0.47) {
          const polia = await page.locator('input:visible:not([type=file]):not([type=hidden]):not([type=password]), textarea:visible, select:visible').all();
          if (polia.length) {
            const p = pick(polia);
            const tag = await p.evaluate(e => e.tagName.toLowerCase());
            if (tag === 'select') { const o = await p.locator('option').all(); if (o.length) await p.selectOption({ index: Math.floor(R() * o.length) }, { timeout: 1500 }); }
            else await p.fill(pick(texty), { timeout: 1500 });
          }
        } else {
          const cile = await page.locator('button:visible, a[href]:visible, [role=button]:visible, summary:visible, label:visible').all();
          if (cile.length) {
            const c = pick(cile);
            const txt = ((await c.innerText({ timeout: 800 }).catch(() => '')) + ' ' + ((await c.getAttribute('href').catch(() => '')) || '') + ' ' + ((await c.getAttribute('title').catch(() => '')) || '') + ' ' + ((await c.getAttribute('aria-label').catch(() => '')) || '')).trim();
            if (NEBEZPECNE.test(txt)) continue;
            const href = await c.getAttribute('href').catch(() => null);
            if (href && /^https?:/.test(href) && !href.startsWith(BASE)) continue;
            await c.click({ timeout: 1500, force: R() < 0.2 });
          }
        }
        kliknuti++;
        await page.waitForTimeout(40);
      } catch (e) { /* prvek zmizel nebo je překrytý — normální při náhodném klikání */ }
    }
    await ctx.close();
  }
  await browser.close();
  console.log(`seed ${SEED}: ${scenare.length} scénářů × ${KROKU} kroků, provedeno ${kliknuti}`);
  const uniq = {};
  for (const n of nalezy) { const k = n.popis + ' | ' + n.druh + ' | ' + n.text; uniq[k] = (uniq[k] || 0) + 1; }
  console.log('nálezy na stránce (JS chyby, 5xx): ' + Object.keys(uniq).length);
  for (const [k, v] of Object.entries(uniq).slice(0, 20)) console.log(`  ${v}× ${k}`);
  const zaznamy = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
  const sl = {};
  for (const z of zaznamy) { const k = z.druh + ' | ' + z.zprava.slice(0, 150) + ' | ' + (z.soubor || z.misto || '') + ':' + (z.radek || ''); sl[k] = (sl[k] || 0) + 1; }
  console.log('záznamy v chybovém logu: ' + zaznamy.length);
  for (const [k, v] of Object.entries(sl).sort((a, b) => b[1] - a[1]).slice(0, 25)) console.log(`  ${v}× ${k}`);
  process.exit(Object.keys(uniq).length || zaznamy.length ? 1 : 0);
})().catch(e => { console.error('PÁD opice', e); process.exit(2); });
