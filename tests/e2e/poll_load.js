// Měření zátěže pollingu: kolik požadavků a dotazů do DB udělá jeden hráč na otevřené mapě za minutu.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const DB = process.env.DB || 'dracak';
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = (process.env.BASE || 'http://127.0.0.1:8099');
const MAPA = 2;
const GL = '/tmp/gl_poll.log';
const sql = (q) => execSync(`mysql -N ${DB} -e "${q}"`).toString().trim();
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 860 } })).newPage();
  await page.goto(BASE + '/index.php'); await page.fill('#email', 'hrac@local'); await page.fill('#password', 'hrac1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  const pozadavky = {};
  page.on('request', r => { const u = new URL(r.url()); if (u.origin === BASE) { const k = u.pathname.replace(/^\/(hra\/)?/, ''); pozadavky[k] = (pozadavky[k] || 0) + 1; } });
  await page.goto(BASE + '/hra/mapa.php?id=' + MAPA);
  await page.waitForTimeout(2500);   // dokončit načtení
  for (const k of Object.keys(pozadavky)) delete pozadavky[k];

  try { fs.unlinkSync(GL); } catch (e) {}
  fs.writeFileSync(GL, ''); fs.chmodSync(GL, 0o666);
  sql(`SET GLOBAL log_output='FILE'; SET GLOBAL general_log_file='${GL}'; SET GLOBAL general_log=1`);
  const okno = +(process.env.OKNO || 28) * 1000;
  await page.waitForTimeout(okno);
  sql('SET GLOBAL general_log=0');
  const log = fs.readFileSync(GL, 'utf8').split('\n');
  const dotazy = log.filter(l => /\s(Query|Execute)\t/.test(l) && !/general_log|information_schema|SELECT 1\b/.test(l));
  const spojeni = log.filter(l => /\sConnect\t/.test(l)).length;
  const min = 60000 / okno;
  console.log('okno ' + okno / 1000 + ' s; násobič na minutu ' + min.toFixed(2));
  const celkemPozadavku = Object.values(pozadavky).reduce((a, b) => a + b, 0);
  console.log('HTTP požadavky na minutu: ' + (celkemPozadavku * min).toFixed(1));
  for (const [k, v] of Object.entries(pozadavky).sort((a, b) => b[1] - a[1])) console.log('   ' + k.padEnd(28) + (v * min).toFixed(1) + '/min');
  console.log('spojení do DB na minutu: ' + (spojeni * min).toFixed(1) + '; dotazů na minutu: ' + (dotazy.length * min).toFixed(1));
  const per = {};
  for (const l of dotazy) { const m = l.split('\t').pop().replace(/\s+/g, ' ').slice(0, 80); per[m] = (per[m] || 0) + 1; }
  console.log('nejčastější dotazy:');
  for (const [q, n] of Object.entries(per).sort((a, b) => b[1] - a[1]).slice(0, 8)) console.log('   ' + String(n).padStart(4) + '  ' + q);
  await browser.close();
})();
