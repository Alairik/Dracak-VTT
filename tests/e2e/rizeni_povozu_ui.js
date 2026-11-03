// Řízení povozu (PJ Tabulka řízení + dovednost Kočí h1727): šance úspěchu kočího při klusu = základ − snížení × (náročnost − 10) + úprava za Kočí,
// počet hodů = započaté směny jízdy, šance na cestu bez nehody = p^směn; v prohlížeči tlačítko hodí k% za každou směnu.
// Po resetu dat: mysql dracak < ../fixture/seed.sql; node rizeni_povozu_ui.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext({ viewport: { width: 1400, height: 1000 } })).newPage();
    p.chybyJs = []; p.on('pageerror', (e) => p.chybyJs.push(e.message));
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const id = (tabulka, nazev) => Number(sqlVal(`select id from ${tabulka} where nazev='${nazev}'`));
  const api = async (p, b) => { const r = await p.request.post(BASE + '/hra/api/cesta_vypocet.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const kociId = id('jizdni_zvirata', 'klasický kůň');
  const kocar = id('povozy', 'malý kočár'), kryty = id('povozy', 'krytý vůz');
  const zaklad = (povozId, naro, koci) => ({
    svet_id: 1, vzdalenost: 5, povrch: naro, teren: 1, vliv: 1, tempo: 'spech', zastav: 3, ucastnici: [{ postava_id: 2, vaha_jezdce: 0, na_povoze: true }],
    povoz: { povoz_id: povozId, vaha_vozu: 4000, nasobek: 2, zvire_id: kociId, pocet: 2, naklad: 0, ...(koci ? { koci } : {}) },
  });

  // --- šance podle Tabulky řízení ---
  let r = await api(pj, zaklad(kryty, 10));
  ok(r.s === 200 && r.povoz && r.povoz.rizeni_procent === 80, 'krytý vůz, klus, náročnost 10: 80 % podle tabulky: ' + JSON.stringify(r.povoz && r.povoz.rizeni_procent) + ' ' + JSON.stringify(r.chyby));
  r = await api(pj, zaklad(kocar, 12));
  ok(r.s === 200 && r.povoz && r.povoz.rizeni_procent === 95, 'malý kočár, klus, náročnost 12: 99 − 2 × 2 = 95 %: ' + (r.povoz && r.povoz.rizeni_procent));
  ok(r.povoz && r.povoz.rizeni_smen >= 1 && r.vysledek && r.povoz.rizeni_smen === Math.ceil(r.vysledek.hodin * 6 - 1e-9), 'počet hodů = započaté směny jízdy (' + (r.povoz && r.povoz.rizeni_smen) + ' směn za ' + (r.vysledek && r.vysledek.hodin) + ' h)');
  const p = r.povoz.rizeni_procent / 100, n = r.povoz.rizeni_smen;
  ok(Math.abs(r.povoz.rizeni_bez_nehody - Math.round(Math.pow(p, n) * 1000) / 10) < 0.11, 'šance na cestu bez nehody = ' + r.povoz.rizeni_procent + ' %^' + n + ' = ' + r.povoz.rizeni_bez_nehody + ' %');

  // --- dovednost Kočí ---
  for (const [k, cekano] of [['totalni', 100], ['uspech', 95], ['neuspech', 85], ['fatalni', 65]]) {
    r = await api(pj, zaklad(kocar, 12, k));
    ok(r.s === 200 && r.povoz.rizeni_procent === cekano, 'Kočí „' + k + '“ upraví 95 % na ' + cekano + ' %: ' + (r.povoz && r.povoz.rizeni_procent));
  }
  r = await api(pj, zaklad(kocar, 12, 'blbost'));
  ok(r.s === 422, 'neznámý výsledek Kočí se odmítne (422)');
  r = await api(pj, { ...zaklad(kocar, 12), tempo: 'chuze' });
  ok(r.s === 200 && r.povoz.rizeni_procent === null && !r.povoz.rizeni_smen, 'krokem (chůze) se řízení nehází');
  r = await api(hrac, zaklad(kocar, 12));
  ok(r.s === 403, 'hráč kalkulačku řízení nepoužije (403)');

  // --- tlačítko hodu v prohlížeči ---
  await pj.goto(BASE + '/hra/cesta.php?id=1&vzdalenost=5');
  await pj.check('#cs-povoz-zapnout');
  await pj.selectOption('#cs-povoz-typ', String(kocar));
  await pj.selectOption('#cs-povoz-koci', 'neuspech');
  await pj.check('input[name=cs-tempo][value=spech]');
  await pj.fill('#cs-povrch', '12');
  await pj.click('#cs-spocitat');
  await pj.waitForSelector('#cs-vysledek:not([hidden]) [data-hod-rizeni]', { timeout: 15000 });
  const text = await pj.textContent('#cs-vysledek');
  ok(/s úpravou za dovednost Kočí/.test(text) && /85 %/.test(text), 'výsledek ukáže šanci 85 % s úpravou za Kočí');
  ok(/šance na cestu bez jediné nehody/.test(text), 'výsledek ukáže šanci na cestu bez nehody');
  await pj.click('[data-hod-rizeni]');
  const vystup = await pj.textContent('#cs-hod-rizeni');
  ok(/NEHODA ve směně \d+ z \d+: padlo \d+ při hranici 85/.test(vystup) || /Cesta bez nehody/.test(vystup), 'hod kočího vypíše výsledek: ' + vystup);
  // 100 % úspěšnost → nikdy nehoda (hranice 100: hod 100 není nad hranicí)
  await pj.selectOption('#cs-povoz-koci', 'totalni');
  await pj.click('#cs-spocitat');
  await pj.waitForSelector('#cs-vysledek [data-hod-rizeni]', { timeout: 15000 });
  await pj.click('[data-hod-rizeni]');
  ok(/Cesta bez nehody/.test(await pj.textContent('#cs-hod-rizeni')), 'při 100 % šanci nehoda nenastane');
  ok(pj.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.join('|'));

  await browser.close();
  console.log(chyb ? 'CHYB: ' + chyb : 'VŠE OK');
  process.exit(chyb ? 1 : 0);
})();
