// Přechod mezi lokacemi (includes/vtt_prechod.php): pin s cílovou mapou → „Vstoupit s družinou“ přenese postavy družiny na vstupní bod cílové mapy (pin vedoucí zpátky,
// jinak střed), rozmístí je na volné buňky, přepne aktivní mapu světa a hráče za ní přepne (událost aktivni_mapa_zmena s příznakem vstup). Jen PJ; za boje ne.
// Mapa 1 (čtverec 70 px) → mapa 2 (hex 45 px). Po resetu dat: mysql dracak < ../fixture/seed.sql; node pin_vstoupit_api.js
const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const DB = process.env.DB || 'dracak';
const A = 1, B = 2, SVET = 1;
const sql = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -e "${q}"`);
const sqlVal = (q) => require('child_process').execSync(`mysql --default-character-set=utf8mb4 ${DB} -N -e "${q}"`).toString().trim();
let chyb = 0;
const ok = (c, m) => { if (!c) chyb++; console.log((c ? 'OK    ' : 'CHYBA ') + m); };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const prihlas = async (email, heslo) => {
    const p = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    p.chybyJs = []; p.on('pageerror', e => p.chybyJs.push(e.message));
    p.on('dialog', d => d.accept());
    await p.goto(BASE + '/index.php'); await p.fill('#email', email); await p.fill('#password', heslo);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
  };
  const pj = await prihlas('test@local', 'test1234');
  const hrac = await prihlas('hrac@local', 'hrac1234');
  const api = async (p, n, b) => { const r = await p.request.post(BASE + '/hra/api/' + n + '.php', { data: b }); let j = {}; try { j = await r.json(); } catch (e) {} return { s: r.status(), ...j }; };
  const smaz = async (mapa) => { for (const t of (await (await pj.request.get(BASE + '/hra/api/mapa_stav.php?mapa_id=' + mapa)).json()).tokeny) await api(pj, 'token_smazat', { token_id: t.id, mapa_id: mapa }); };
  sql(`delete from kolo_stav; delete from mapa_body; update svet set aktivni_mapa_id=${A} where id=${SVET}`);
  await smaz(A); await smaz(B);
  const roz = sqlVal(`select concat(sirka_px, ' ', vyska_px) from mapy where id=${B}`).split(' ').map(Number);
  const stredB = [roz[0] / 2, roz[1] / 2];
  const poloha = (id) => sqlVal(`select concat(mapa_id, ' ', x, ' ', y) from tokeny where id=${id}`).split(' ').map(Number);

  const h = (await api(pj, 'token_pridat', { mapa_id: A, typ_entity: 'postava', postava_id: 2, x: 335, y: 315 })).token_id;
  const gob = (await api(pj, 'token_pridat', { mapa_id: A, typ_entity: 'nestvura', nestvura_id: 41, x: 405, y: 315 })).token_id;
  const pinAB = (await api(pj, 'mapa_bod_pridat', { mapa_id: A, nazev: 'Jeskyně', typ: 'prechod', x: 500, y: 500, cilova_mapa_id: B, viditelny_hracum: true })).id;
  const pinBez = (await api(pj, 'mapa_bod_pridat', { mapa_id: A, nazev: 'Jen značka', typ: 'jine', x: 600, y: 500, viditelny_hracum: true })).id;
  ok(h && gob && pinAB && pinBez, 'družina, nestvůra a piny jsou připravené');

  // --- oprávnění a chyby ---
  let r = await api(hrac, 'pin_vstoupit', { bod_id: pinAB });
  ok(r.s === 403, 'hráč družinu přenést nesmí (403): ' + r.s);
  r = await api(pj, 'pin_vstoupit', { bod_id: pinBez });
  ok(r.s === 422, 'pin bez cílové mapy se odmítne (422): ' + r.s);
  r = await api(pj, 'pin_vstoupit', { bod_id: 999999 });
  ok(r.s === 404, 'neexistující pin (404): ' + r.s);
  sql(`insert into kolo_stav (mapa_id, cislo_kola) values (${A}, 1)`);
  r = await api(pj, 'pin_vstoupit', { bod_id: pinAB });
  ok(r.s === 409 && poloha(h)[0] === A, 'za boje se družina nepřenáší (409), postava zůstala na mapě 1');
  sql(`delete from kolo_stav`);

  // --- bez pinu zpátky: střed cílové mapy ---
  const posl = parseInt(sqlVal('select max(id) from svet_udalosti'), 10);
  r = await api(pj, 'pin_vstoupit', { bod_id: pinAB });
  ok(r.s === 200 && r.mapa_id === B && r.vstup === 'stred' && r.presunuto.length === 1, 'PJ vstoupí s družinou: ' + JSON.stringify(r).slice(0, 120));
  let p = poloha(h);
  ok(p[0] === B && Math.hypot(p[1] - stredB[0], p[2] - stredB[1]) < 100, 'postava hráče stojí na mapě 2 u středu (' + p.join(', ') + ')');

  // --- cesta zpátky chyběla: pin se založí sám (rozhodnutí uživatele 2026-11-11) ---
  ok(r.zpatecni_pin && r.zpatecni_pin.id, 'odpověď nese nově založený zpáteční pin: ' + JSON.stringify(r.zpatecni_pin));
  const auto = r.zpatecni_pin;
  ok(sqlVal(`select concat(mapa_id, ' ', cilova_mapa_id) from mapa_body where id=${auto.id}`) === B + ' ' + A,
    'pin leží na nové mapě a vede zpátky na tu, odkud družina přišla');
  ok(/^Zpět: /.test(auto.nazev), 'jmenuje se podle mapy, odkud se přišlo: ' + auto.nazev);
  ok(sqlVal(`select viditelny_hracum from mapa_body where id=${auto.id}`) === '1', 'cestu zpátky vidí i hráči');

  // „Posunout jinam“ — pin i družina se přemístí.
  let z = await api(hrac, 'pin_zpatecni', { akce: 'posunout', bod_id: auto.id, x: 200, y: 200 });
  ok(z.s === 403, 'hráč se zpátečním pinem hýbat nesmí (403): ' + z.s);
  z = await api(pj, 'pin_zpatecni', { akce: 'posunout', bod_id: auto.id, x: 200, y: 200 });
  p = poloha(h);
  ok(z.s === 200 && sqlVal(`select concat(x, ' ', y) from mapa_body where id=${auto.id}`) === '200 200', 'pin se posunul tam, kam PJ klepl');
  ok(Math.hypot(p[1] - 200, p[2] - 200) < 120, 'družina se přesunula k němu (' + p.slice(1).join(', ') + ')');

  // „Pin nedělej“ — zmizí, družina zůstane.
  const kdeByla = poloha(h);
  z = await api(pj, 'pin_zpatecni', { akce: 'zrusit', bod_id: auto.id });
  ok(z.s === 200 && sqlVal(`select count(*) from mapa_body where id=${auto.id}`) === '0', 'pin se dá zrušit');
  ok(poloha(h).join(',') === kdeByla.join(','), 'zrušení pinu družinou nehýbe');

  // Podruhé už cesta zpátky existuje — nic dalšího se nezakládá.
  sql(`update tokeny set mapa_id=${A}, x=335, y=315 where id=${h}`);
  const pinZpet = (await api(pj, 'mapa_bod_pridat', { mapa_id: B, nazev: 'Ven', typ: 'prechod', x: 420, y: 420, cilova_mapa_id: A, viditelny_hracum: true })).id;
  r = await api(pj, 'pin_vstoupit', { bod_id: pinAB });
  ok(r.vstup === 'pin' && !r.zpatecni_pin, 'když cesta zpátky je, nový pin nevzniká');
  ok(sqlVal(`select count(*) from mapa_body where mapa_id=${B} and cilova_mapa_id=${A}`) === '1', 'na mapě zůstal jen ten jeden');
  await api(pj, 'mapa_bod_smazat', { id: pinZpet });
  sql(`update tokeny set mapa_id=${A}, x=335, y=315 where id=${h}`);
  ok(poloha(gob)[0] === A, 'nestvůra zůstala na mapě 1');
  ok(sqlVal(`select aktivni_mapa_id from svet where id=${SVET}`) === String(B), 'aktivní mapa světa je teď mapa 2');
  const ud = await (await hrac.request.get(BASE + '/hra/api/udalosti.php?svet_id=1&od=' + posl)).text();
  ok(/aktivni_mapa_zmena/.test(ud) && /"vstup":true/.test(ud), 'hráč dostane událost o přechodu s příznakem vstup');

  // --- pin zpátky = vstupní bod; obsazená buňka se přeskočí ---
  sql(`delete from mapa_body where mapa_id=${B}`);   // ať vstupní bod určuje jen pin založený tady
  const pinBA = (await api(pj, 'mapa_bod_pridat', { mapa_id: B, nazev: 'Východ', typ: 'prechod', x: 300, y: 300, cilova_mapa_id: A, viditelny_hracum: true })).id;
  sql(`update tokeny set mapa_id=${A}, x=335, y=315 where id=${h}`);
  const gob2 = (await api(pj, 'token_pridat', { mapa_id: B, typ_entity: 'nestvura', nestvura_id: 41, x: 300, y: 300 })).token_id;
  const g = poloha(gob2);
  r = await api(pj, 'pin_vstoupit', { bod_id: pinAB });
  p = poloha(h);
  ok(r.vstup === 'pin' && p[0] === B, 'vstupní bod je pin vedoucí zpátky (' + r.vstup + ')');
  const od = Math.hypot(p[1] - g[1], p[2] - g[2]);
  ok(od > 30 && od < 200, 'postava nestojí na buňce s nestvůrou, ale hned vedle (' + od.toFixed(0) + ' px)');

  // --- postava už na cílové mapě stojí: přesune se, nezdvojí ---
  sql(`update tokeny set mapa_id=${A}, x=335, y=315 where id=${h}`);
  const dup = (await api(pj, 'token_pridat', { mapa_id: B, typ_entity: 'postava', postava_id: 2, x: 100, y: 100 }));
  const dupId = dup.token_id;
  r = await api(pj, 'pin_vstoupit', { bod_id: pinAB });
  ok(dupId && sqlVal(`select count(*) from tokeny where typ_entity='postava' and entita_id=2`) === '1', 'postava se nezdvojila (jeden token na celém světě)');

  // --- prohlížeč: hráč na mapě 1, PJ klikne na pin ---
  sql(`update tokeny set mapa_id=${A}, x=335, y=315 where typ_entity='postava' and entita_id=2; update svet set aktivni_mapa_id=${A} where id=${SVET}`);
  await hrac.goto(BASE + '/hra/mapa.php?id=' + A);
  await pj.goto(BASE + '/hra/mapa.php?id=' + A);
  await pj.waitForTimeout(1200); await hrac.waitForTimeout(1200);
  await api(pj, 'aktivni_mapa_nastavit', { mapa_id: B });
  await hrac.waitForTimeout(4500);
  ok(hrac.url().includes('id=' + A), 'obyčejné „Nastavit jako aktivní“ hráče nepřesouvá');
  sql(`update svet set aktivni_mapa_id=${A} where id=${SVET}`);
  await pj.locator('.mapa-bod-marker[data-bod-id="' + pinAB + '"]').click();
  await pj.waitForTimeout(500);
  ok(await pj.locator('#pinInfoVstoupitBtn').isVisible(), 'v informaci o pinu je tlačítko „Vstoupit s družinou“');
  await pj.click('#pinInfoVstoupitBtn');
  await pj.waitForURL('**/mapa.php?id=' + B, { timeout: 8000 }).catch(() => {});
  ok(pj.url().includes('id=' + B), 'PJ se po kliknutí přepne na cílovou mapu');
  await hrac.waitForURL('**/mapa.php?id=' + B, { timeout: 12000 }).catch(() => {});
  ok(hrac.url().includes('id=' + B), 'hráč se přepne za družinou na mapu 2: ' + hrac.url());
  await hrac.waitForTimeout(1200);
  ok(await hrac.locator('.vtt-token').count() >= 1, 'hráč na nové mapě vidí svůj token');
  // --- prohlížeč: hláška o zpátečním pinu, když cesta zpátky chyběla ---
  sql(`delete from mapa_body where mapa_id=${B}`);
  sql(`update tokeny set mapa_id=${A}, x=335, y=315 where typ_entity='postava' and entita_id=2; update svet set aktivni_mapa_id=${A} where id=${SVET}`);
  await pj.goto(BASE + '/hra/mapa.php?id=' + A); await pj.waitForTimeout(1200);
  await pj.locator('.mapa-bod-marker[data-bod-id="' + pinAB + '"]').click(); await pj.waitForTimeout(400);
  await pj.click('#pinInfoVstoupitBtn');
  await pj.waitForURL('**/mapa.php?id=' + B, { timeout: 8000 }).catch(() => {});
  await pj.waitForTimeout(1500);
  ok(await pj.locator('#zpatecniPin').isVisible(), 'PJ dostane hlášku, že se cesta zpátky založila sama');
  ok(/Zpět:/.test(await pj.locator('#zpatecniPinText').textContent()), 'hláška jmenuje pin: ' + (await pj.locator('#zpatecniPinText').textContent()).trim());
  const pinuPred = parseInt(sqlVal(`select count(*) from mapa_body where mapa_id=${B}`), 10);
  await pj.click('#zpatecniPinZrusit'); await pj.waitForTimeout(900);
  ok(parseInt(sqlVal(`select count(*) from mapa_body where mapa_id=${B}`), 10) === pinuPred - 1, '„Pin nedělej“ ho zruší');
  ok(!(await pj.locator('#zpatecniPin').isVisible()), 'hláška po rozhodnutí zmizí');
  ok(await pj.locator('.mapa-bod-marker').count() === 0, 'značka pinu zmizela i z mapy');

  ok(pj.chybyJs.length === 0 && hrac.chybyJs.length === 0, 'žádná chyba JavaScriptu: ' + pj.chybyJs.concat(hrac.chybyJs).join('; '));

  sql(`delete from kolo_stav; delete from mapa_body; update svet set aktivni_mapa_id=${A} where id=${SVET}`);
  await smaz(A); await smaz(B);
  await browser.close();
  console.log(chyb ? '\nSELHALO ' + chyb : '\nVŠE OK');
  process.exit(chyb ? 1 : 0);
})().catch(e => { console.error('PÁD', e); process.exit(2); });
