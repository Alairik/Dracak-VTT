// Zátěž pollingu: šest současně otevřených map (PJ + 5 hráčů, každý v samostatném přihlášení), PJ po celou dobu přesouvá token a mění životy,
// ostatní jen sledují. Měří dobu odezvy podle adresy (medián, p95, maximum) a počet chyb. Není to kontrola, ale měření – selže jen na 5xx
// nebo nesplněném limitu odezvy (LIMIT_P95_MS, výchozí 1500 ms). Po resetu dat: mysql dracak < ../fixture/seed.sql; node poll_zatez.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const MAPA = 1;
const POCET = +(process.env.POCET || 6);
const OKNO = +(process.env.OKNO || 40) * 1000;
const LIMIT = +(process.env.LIMIT_P95_MS || 1500);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const strany = [];
  const doby = {}; const stavy = {}; const chybyJs = [];
  for (let i = 0; i < POCET; i++) {
    const pj = i === 0;
    const p = await (await browser.newContext({ viewport: { width: 1000, height: 700 } })).newPage();
    p.on('pageerror', (e) => chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', pj ? 'test@local' : 'hrac@local'); await p.fill('#password', pj ? 'test1234' : 'hrac1234');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    p.on('requestfinished', async (r) => {
      const u = new URL(r.url()); if (u.origin !== BASE || !u.pathname.includes('/api/')) return;
      const k = u.pathname.split('/').pop(); const t = r.timing();
      (doby[k] = doby[k] || []).push(t.responseEnd >= 0 ? t.responseEnd : 0);
      const resp = await r.response(); if (resp) stavy[resp.status()] = (stavy[resp.status()] || 0) + 1;
    });
    await p.goto(BASE + '/hra/mapa.php?id=' + MAPA);
    strany.push(p);
  }
  await strany[0].waitForTimeout(3000);
  for (const k of Object.keys(doby)) delete doby[k];
  for (const k of Object.keys(stavy)) delete stavy[k];
  const pjStrana = strany[0];
  const konec = Date.now() + OKNO; let n = 0;
  while (Date.now() < konec) {
    n++;
    await pjStrana.request.post(BASE + '/hra/api/token_presun.php', { data: { token_id: 2, x: 600 + (n % 8) * 10, y: 300 + (n % 5) * 10 } });
    await pjStrana.request.post(BASE + '/hra/api/hp_uprava.php', { data: { mapa_id: MAPA, typ_entity: 'postava', entita_id: 2, delta: n % 2 ? -1 : 1 } });
    await pjStrana.waitForTimeout(1500);
  }
  const q = (a, p) => { const s = [...a].sort((x, y) => x - y); return s[Math.min(s.length - 1, Math.floor(s.length * p))]; };
  let nejhorsi = 0;
  console.log('šest stran, okno ' + OKNO / 1000 + ' s, PJ udělal ' + n + ' přesunů a změn životů');
  for (const [k, a] of Object.entries(doby).sort((x, y) => y[1].length - x[1].length)) {
    const p95 = q(a, 0.95); nejhorsi = Math.max(nejhorsi, p95);
    console.log('   ' + k.padEnd(24) + String(a.length).padStart(5) + '×  medián ' + Math.round(q(a, 0.5)) + ' ms, p95 ' + Math.round(p95) + ' ms, max ' + Math.round(Math.max(...a)) + ' ms');
  }
  console.log('stavy odpovědí: ' + JSON.stringify(stavy));
  const pet = Object.keys(stavy).some((s) => +s >= 500);
  console.log(pet ? 'CHYBA odpověď 5xx' : 'OK    žádná odpověď 5xx');
  console.log(nejhorsi <= LIMIT ? 'OK    nejhorší p95 ' + Math.round(nejhorsi) + ' ms (limit ' + LIMIT + ')' : 'CHYBA nejhorší p95 ' + Math.round(nejhorsi) + ' ms nad limitem ' + LIMIT);
  console.log(chybyJs.length === 0 ? 'OK    žádná chyba JavaScriptu' : 'CHYBA JS: ' + chybyJs.slice(0, 3).join(' | '));
  await browser.close();
  process.exit(pet || nejhorsi > LIMIT || chybyJs.length ? 1 : 0);
})();
