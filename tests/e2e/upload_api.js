// Nahrávání obrázků (hra/api/obrazek_nahrat.php): platný obrázek projde a vydá se přes obrazek.php; obří rozměry, text, SVG se skriptem
// a příliš velký soubor dostanou srozumitelnou chybu 422 (ne pád serveru); PHP kód přilepený za PNG se při překódování zahodí.
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const zlib = require('zlib');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const ROOT = process.env.DOCROOT || path.resolve(__dirname, '../..');
const sqlVal = (q) => execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
function crc32(buf) { let c, crc = 0xffffffff; for (let n = 0; n < buf.length; n++) { c = (crc ^ buf[n]) & 0xff; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; crc = (crc >>> 8) ^ c; } return (crc ^ 0xffffffff) >>> 0; }
function blok(typ, data) { const t = Buffer.from(typ); const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const c = Buffer.alloc(4); c.writeUInt32BE(crc32(Buffer.concat([t, data]))); return Buffer.concat([len, t, data, c]); }
function png(w, h) {
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 2;
  const radek = Buffer.concat([Buffer.from([0]), Buffer.alloc(w * 3, 120)]);
  const raw = Buffer.alloc(radek.length * h);
  for (let i = 0; i < h; i++) radek.copy(raw, i * radek.length);
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), blok('IHDR', ihdr), blok('IDAT', zlib.deflateSync(raw, { level: 1 })), blok('IEND', Buffer.alloc(0))]);
}
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const page = await (await browser.newContext()).newPage();
  await page.goto(BASE + '/index.php'); await page.fill('#email', 'test@local'); await page.fill('#password', 'test1234');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  const nahraj = async (nazevSouboru, mime, buffer) => {
    const r = await page.request.post(BASE + '/hra/api/obrazek_nahrat.php', { multipart: { svet_id: '1', nazev: 'test', soubor: { name: nazevSouboru, mimeType: mime, buffer } }, timeout: 120000 });
    let j = null; try { j = await r.json(); } catch (e) { /* ne JSON */ }
    return { s: r.status(), j };
  };
  let r = await nahraj('maly.png', 'image/png', png(64, 64));
  ok(r.s === 200 && r.j && r.j.ok && r.j.id > 0, 'platné PNG 64×64 projde: ' + r.s + ' ' + JSON.stringify(r.j));
  if (r.j && r.j.id) {
    const o = await page.request.get(BASE + '/hra/obrazek.php?id=' + r.j.id);
    ok(o.status() === 200 && /image\/png/.test(o.headers()['content-type'] || ''), 'obrázek se vydá přes obrazek.php jako PNG: ' + o.status());
  }
  r = await nahraj('obri.png', 'image/png', png(4200, 4200));
  ok(r.s === 422 && r.j && /moc bodů/.test(r.j.error || ''), '4200×4200 (17,6 Mbodů) dostane hlášku 422, ne pád: ' + r.s + ' ' + JSON.stringify(r.j));
  r = await nahraj('text.png', 'image/png', Buffer.from('tohle není obrázek'));
  ok(r.s === 422, 'text s příponou .png: 422 (' + r.s + ')');
  r = await nahraj('x.svg', 'image/svg+xml', Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>'));
  ok(r.s === 422, 'SVG se skriptem: 422 (' + r.s + ')');
  const velky = Buffer.concat([png(8, 8), Buffer.alloc(5 * 1024 * 1024, 1)]);
  r = await nahraj('velky.png', 'image/png', velky);
  ok(r.s === 422 && r.j && /4 MB|nahrát/.test(r.j.error || ''), 'soubor nad 4 MB: 422 (' + r.s + ' ' + JSON.stringify(r.j) + ')');
  const polyglot = Buffer.concat([png(8, 8), Buffer.from('<?php system($_GET["c"]); ?>')]);
  r = await nahraj('polyglot.png', 'image/png', polyglot);
  ok(r.s === 200 && r.j && r.j.ok, 'PNG s PHP kódem za koncem projde (překóduje se): ' + r.s);
  if (r.j && r.j.id) {
    const soubor = sqlVal('select soubor from svet_obrazky where id=' + r.j.id);
    const bajty = fs.readFileSync(ROOT + '/uploads/obrazky/' + soubor);
    ok(bajty.slice(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])) && !bajty.includes('<?php') && /^[0-9a-f]{32}\.png$/.test(soubor), 'uložený soubor je čisté PNG pod náhodným názvem, bez PHP kódu: ' + soubor);
  }
  const anonym = await (await browser.newContext()).request.post(BASE + '/hra/api/obrazek_nahrat.php', { multipart: { svet_id: '1', soubor: { name: 'a.png', mimeType: 'image/png', buffer: png(8, 8) } }, maxRedirects: 0 });
  ok(anonym.status() >= 300 && anonym.status() < 500, 'nepřihlášený nic nenahraje (přesměrování na přihlášení / chyba): ' + anonym.status());
  // úklid nahraných souborů
  for (const soubor of sqlVal("select soubor from svet_obrazky where nazev='test'").split('\n').filter(Boolean)) { try { fs.unlinkSync(ROOT + '/uploads/obrazky/' + soubor); } catch (e) {} }
  execSync(`mysql ${DB} -e "delete from svet_obrazky where nazev='test'"`);
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
