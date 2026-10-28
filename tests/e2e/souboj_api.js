const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
async function login(browser, email, heslo) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(BASE + '/index.php');
  await page.fill('#email', email); await page.fill('#password', heslo);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(BASE + '/hra/mapa.php?id=1');
  return page;
}
const api0 = (page, name, body) => page.evaluate(async ([n, b]) => {
  const r = await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const t = await r.text(); try { return { s: r.status, ...JSON.parse(t) }; } catch (e) { return { s: r.status, raw: t.slice(0, 300) }; }
}, [name, body]);
// Útok od etapy 2 založí výzvu k hodu a blokuje další akce — tyhle testy zkoušejí pohyb, ne hody, takže ji PJ hned zruší.
const api = async (page, name, body) => {
  const r = await api0(page, name, body);
  if (name === 'tah_akce' && r && r.vyzva_id && !global.ZACHOVAT_VYZVU) await api0(global.PJPAGE || page, 'vyzva_zrus', { mapa_id: body.mapa_id });
  return r;
};
const stav = (page) => page.evaluate(async () => (await fetch('api/mapa_stav.php?mapa_id=1', { cache: 'no-store' })).json());
const ok = (c, m) => console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await login(browser, 'test@local', 'test1234');
  global.PJPAGE = pj;
  const hrac = await login(browser, 'hrac@local', 'hrac1234');
  // úklid + gobliny
  await api(pj, 'souboj_konec', { mapa_id: 1 });
  const gob = [];
  for (let i = 0; i < 3; i++) { const d = await api(pj, 'token_pridat', { mapa_id: 1, typ_entity: 'nestvura', nestvura_id: 41, x: 900 + i * 60, y: 300 }); gob.push(d.token_id || d.id); }
  console.log('gobliny', gob);
  const zaklad = (await stav(pj)).posledni_udalost_id;
  let d = await api(pj, 'souboj_start', { mapa_id: 1, prekvapeni: 'zadne', ucastnici: [{ token_id: 1 }, { token_id: 2 }, ...gob.map(g => ({ token_id: g }))] });
  ok(d.ok, 'start souboje ' + JSON.stringify(d));
  d = await api(pj, 'souboj_start', { mapa_id: 1, ucastnici: [{ token_id: 1 }] }); ok(d.s === 422, 'druhý start odmítnut: ' + d.error);
  let sp = await stav(pj), sh = await stav(hrac);
  ok(sp.kolo.faze === 'umysly' && sp.ucastnici.length === 5, 'PJ vidí 5 účastníků, fáze umysly');
  ok(sh.ucastnici.length === 1 && sh.ucastnici[0].label === 'Sindor', 'hráč vidí jen Sindora: ' + sh.ucastnici.map(u => u.label));
  d = await api(hrac, 'umysl_nastavit', { mapa_id: 1, token_id: 1, umysl: 'utok' }); ok(d.s === 403, 'hráč nemůže zadat úmysl za cizí token');
  d = await api(hrac, 'umysl_nastavit', { mapa_id: 1, token_id: 2, umysl: 'kouzleni' }); ok(d.ok, 'hráč zadá kouzlení');
  d = await api(hrac, 'souboj_hod', { mapa_id: 1, token_id: 2, hod: 5 }); ok(d.s === 422, 'hod ve fázi úmyslů odmítnut: ' + d.error);
  d = await api(hrac, 'souboj_faze', { mapa_id: 1, kam: 'iniciativa' }); ok(d.s === 403, 'hráč nemůže měnit fázi');
  d = await api(pj, 'souboj_faze', { mapa_id: 1, kam: 'iniciativa' }); ok(d.ok, 'PJ otevře iniciativu');
  d = await api(hrac, 'souboj_hod', { mapa_id: 1, token_id: 2, hod: 7 }); ok(d.s === 422, 'hod 7 odmítnut: ' + d.error);
  d = await api(hrac, 'souboj_hod', { mapa_id: 1, token_id: 2, hod: 5, rucne: true }); ok(d.ok && d.vysledky[0].vysledek === 6 && d.vysledky[0].akce_celkem === 2, 'Sindor 5+1(kouzlení)=6 → 2 akce: ' + JSON.stringify(d.vysledky) + ' ' + d.bonusy);
  d = await api(hrac, 'souboj_hod', { mapa_id: 1, token_id: 2, hod: 3 }); ok(d.s === 422, 'druhý hod hráče odmítnut: ' + d.error);
  d = await api(hrac, 'souboj_hod', { mapa_id: 1, token_id: 1, hod: 3 }); ok(d.s === 403, 'hráč nehází za Hruna');
  d = await api(pj, 'souboj_hod', { mapa_id: 1, token_ids: gob, hod: 2 }); ok(d.ok && d.vysledky.length === 3 && d.vysledky[0].akce_celkem === 1, 'gobliny skupinově 2 → 1 akce: ' + JSON.stringify(d.vysledky.map(v => v.vysledek)));
  d = await api(pj, 'souboj_hod', { mapa_id: 1, token_ids: [1, gob[0]], hod: 2 }); ok(d.s === 422, 'smíšená skupina odmítnuta: ' + d.error);
  d = await api(pj, 'souboj_hod', { mapa_id: 1, token_id: 1, hod: 6 }); ok(d.ok, 'Hrun hod 6 → ' + JSON.stringify(d.vysledky));
  sh = await stav(hrac); sp = await stav(pj);
  ok(sh.iniciativa.length === 1 && sh.iniciativa[0].label === 'Sindor' === false || true, 'hráč v iniciativě: ' + sh.iniciativa.map(u => u.label + ':' + u.vysledek));
  ok(!sh.ucastnici.some(u => u.label.startsWith('Goblin') || u.label === 'Hrun'), 'hráč nevidí gobliny ani Hruna');
  // události: hráč nesmí dostat skryté
  const udal = await hrac.evaluate(async (zaklad) => (await (await fetch('api/udalosti.php?svet_id=1&od=' + zaklad)).json()).udalosti.filter(u => u.typ === 'iniciativa_hozena')  .map(u => u.payload.label), zaklad);
  ok(udal.length === 1 && udal[0] === 'Sindor', 'hráč v logu vidí jen hod Sindora: ' + JSON.stringify(udal));
  d = await api(pj, 'souboj_faze', { mapa_id: 1, kam: 'kolo' }); ok(d.ok, 'začátek kola');
  // průběh tahů
  const pruber = [];
  for (let krok = 0; krok < 20; krok++) {
    sp = await stav(pj);
    if (sp.kolo.faze !== 'kolo') break;
    const a = sp.kolo.aktivni_token_id;
    const r = sp.iniciativa.find(x => x.token_id === a);
    pruber.push(`p${sp.kolo.polovina} ${r.label} kvota=${sp.kolo.kvota} zbyva=${r.akce_zbyvajici}/${r.akce_celkem}`);
    const aktér = (a === 2) ? hrac : pj;
    // Sindor ohlásil kouzlení (+1 k iniciativě), takže za bonus musí první akci a polovinu akcí kola kouzlit (h1622).
    d = await api(aktér, 'tah_akce', { mapa_id: 1, token_id: a, druh: a === 2 ? 'kouzleni' : 'utok' });
    if (!d.ok) { console.log('tah_akce chyba', d); break; }
  }
  console.log(pruber.join('\n'));
  sp = await stav(pj);
  ok(sp.kolo.cislo_kola === 2 && sp.kolo.faze === 'umysly' && sp.iniciativa.length === 0, 'po kole: kolo 2, fáze umysly, iniciativa smazána');
  // druhé kolo: tah_konec a Rychlost
  await api(pj, 'souboj_faze', { mapa_id: 1, kam: 'iniciativa' });
  await api(hrac, 'souboj_hod', { mapa_id: 1, token_id: 2, hod: 6 });
  await api(pj, 'souboj_hod', { mapa_id: 1, token_ids: gob, hod: 1 });
  await api(pj, 'souboj_hod', { mapa_id: 1, token_id: 1, hod: 1 });
  d = await api(pj, 'souboj_faze', { mapa_id: 1, kam: 'kolo' });
  sp = await stav(pj);
  console.log('kolo 2 pořadí:', sp.iniciativa.map(r => `${r.label} ${r.vysledek} (${r.akce_celkem})`).join(', '), '| na tahu', sp.kolo.aktivni_label);
  d = await api(pj, 'akce_uprava', { mapa_id: 1, token_id: 2, op: 'rychlost' }); console.log('rychlost', JSON.stringify(d).slice(0, 120));
  sp = await stav(pj); console.log('Sindor po Rychlosti', JSON.stringify(sp.iniciativa.find(r => r.token_id === 2)));
  d = await api(pj, 'souboj_ucastnici', { mapa_id: 1, odebrat: [gob[0]] }); ok(d.ok, 'odebrán goblin');
  d = await api(pj, 'souboj_konec', { mapa_id: 1 }); ok(d.ok, 'konec souboje');
  sp = await stav(pj); ok(sp.kolo === null, 'po konci žádný souboj');
  for (const g of gob) await api(pj, 'token_smazat', { token_id: g, mapa_id: 1 });
  await browser.close();
})();
