const { chromium } = require(process.env.PLAYWRIGHT || '/opt/node22/lib/node_modules/playwright');
const BASE = (process.env.BASE || 'http://127.0.0.1:8099');
const ok = (c, m) => console.log((c ? 'OK   ' : 'CHYBA') + ' ' + m);
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const pj = await (await browser.newContext()).newPage();
  pj.on('dialog', d => d.accept());
  await pj.goto(BASE + '/index.php');
  await pj.fill('#email', 'test@local'); await pj.fill('#password', 'test1234');
  await Promise.all([pj.waitForNavigation(), pj.click('button[type=submit]')]);
  await pj.goto(BASE + '/hra/mapa.php?id=1'); await pj.waitForTimeout(800);
  const api = (n, b) => pj.evaluate(async ([n, b]) => (await fetch('api/' + n + '.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) })).json(), [n, b]);
  await api('souboj_konec', { mapa_id: 1 });
  const efektId = await pj.evaluate(() => parseInt(document.querySelector('#efektSelect option').value, 10));
  // ruční Konec kola mimo souboj
  let d = await api('efekt_pridat', { mapa_id: 1, typ_entity: 'postava', entita_id: 1, efekt_id: efektId, zbyva_kol: 1 });
  d = await api('kolo_konec', { mapa_id: 1 });
  ok(d.ok && d.expirovalo >= 1, 'ruční Konec kola odpočítá efekt: ' + JSON.stringify(d));
  // konec kola v souboji
  const zaklad = (await pj.evaluate(async () => (await fetch('api/mapa_stav.php?mapa_id=1')).json())).posledni_udalost_id;
  d = await api('efekt_pridat', { mapa_id: 1, typ_entity: 'postava', entita_id: 1, efekt_id: efektId, zbyva_kol: 1 });
  await api('souboj_start', { mapa_id: 1, ucastnici: [{ token_id: 2 }] });
  await api('souboj_faze', { mapa_id: 1, kam: 'iniciativa' });
  await api('souboj_hod', { mapa_id: 1, token_id: 2, hod: 1 });
  await api('souboj_faze', { mapa_id: 1, kam: 'kolo' });
  d = await api('tah_akce', { mapa_id: 1, token_id: 2, druh: 'cekani' });
  ok(d.ok && d.posun.kolo_konec === true, 'Sindor s 1 akcí: po jedné akci konec kola ' + JSON.stringify(d.posun));
  const ev = await pj.evaluate(async (zaklad) => (await (await fetch('api/udalosti.php?svet_id=1&od=' + zaklad)).json()).udalosti.filter(u => u.typ === 'kolo_nove').pop().payload, zaklad);
  ok(ev.efekty_konci >= 1, 'konec kola souboje odpočetl efekty: ' + JSON.stringify(ev));
  await api('souboj_konec', { mapa_id: 1 });
  await browser.close();
})();
