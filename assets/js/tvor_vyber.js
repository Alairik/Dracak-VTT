// Seznam tvorů s náhledy — výběr nestvůry při pokládání tokenu (zadání uživatele 2026-11-11:
// „chtěl bych, aby měl výběr tokenů a přidání lepší UI… v seznamu, tak jak je kompaktní teď,
// aby tam byl náhledový obrázek potvory a pak její název“; připomínky k návrhu na plátně
// https://claude.ai/artifact/RZz5NCY8rSRKjty6fm7btz, deska „Výběr tokenu s náhledy“).
//
// Co uživatel k návrhu rozhodl:
//   * řádky NIŽŠÍ, náhled přesahuje bublinu řádku,
//   * „třída B“ je ve skutečnosti VELIKOST — stačí písmeno „B“,
//   * „kolik zabírá“ a „jedinečný“ v řádku nemají co dělat,
//   * přibude „žvt X“ (životaschopnost); ÚČ a OČ zůstávají,
//   * útoků může být víc — vypsat všechny, oddělené.
//
// Náhled vypadá jako token na mapě: šestiúhelník na hexové mapě, kolečko na čtvercové,
// v něm iniciála druhu v barvě nestvůry. Obrázek bestiář neeviduje (žádný sloupec ho nemá),
// takže náhled je přesně to, co po položení na mapě opravdu uvidíš.
//
// Seznam jen OBSLUHUJE skrytý <select> — hodnotu zapíše do něj a vyvolá na něm `change`,
// takže všechno ostatní (pokládání, token bar, testy) zůstává beze změny.

const esc = (t) => String(t == null ? '' : t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function iniciala(nazev) {
  return Array.from(String(nazev || '?')).slice(0, 2).join('');
}

// „(+4 + 6/+1) = 10+/1 (tlama) + 2×10/+1; ×2 spáry“ → jednotlivé útoky.
// Dělí se jen středníkem — plus a lomítko jsou součástí zápisu jednoho útoku.
function utoky(text) {
  return String(text || '').split(';').map((s) => s.trim()).filter(Boolean);
}

function zkratit(text, kolik) {
  const t = String(text || '').trim();
  return t.length > kolik ? t.slice(0, kolik - 1) + '…' : t;
}

// Řádek: náhled + jméno + čísla. Popis pod jménem drží jeden řádek, celé znění je v `title`.
function radekHtml(tvor, hex) {
  const cisla = [];
  if (tvor.vel) cisla.push('<span class="tv-chip" title="Velikost podle pravidel">' + esc(zkratit(tvor.vel, 7)) + '</span>');
  if (tvor.zvt) cisla.push('<span class="tv-chip" title="Životaschopnost">žvt ' + esc(zkratit(tvor.zvt, 9)) + '</span>');
  if (tvor.oc) cisla.push('<span class="tv-chip" title="Obranné číslo">OČ ' + esc(zkratit(tvor.oc, 12)) + '</span>');
  // Útoků bývá víc (v knize oddělené středníkem). Vypisují se VŠECHNY v jednom štítku, oddělené „|“,
  // aby řádek zůstal nízký; dlouhý zápis se zkrátí a celé znění je v nájezdu myši.
  const u = utoky(tvor.uc);
  if (u.length) {
    cisla.push('<span class="tv-chip tv-uc" title="' + esc((u.length > 1 ? 'Útočná čísla: ' : 'Útočné číslo: ') + u.join('  |  ')) + '">ÚČ '
      + esc(zkratit(u.join(' | '), 30)) + '</span>');
  }
  const popis = tvor.nazev + (tvor.vel ? ' · velikost ' + tvor.vel : '') + (tvor.zvt ? ' · životaschopnost ' + tvor.zvt : '')
    + (tvor.oc ? ' · OČ ' + tvor.oc : '') + (u.length ? ' · ÚČ ' + u.join(' | ') : '');
  return '<button type="button" class="tv-radek' + (hex ? ' hex' : '') + '" data-tvor="' + tvor.id + '" title="' + esc(popis) + '">'
    + '<span class="tv-nahled" aria-hidden="true">' + esc(iniciala(tvor.nazev)) + '</span>'
    + '<span class="tv-text"><strong>' + esc(tvor.nazev) + '</strong>'
    + (cisla.length ? '<span class="tv-cisla">' + cisla.join('') + '</span>' : '')
    + '</span></button>';
}

// kontejner — kam seznam vykreslit; select — skrytý <select>, který seznam obsluhuje;
// hledat — pole na hledání (nepovinné); hex — true na hexové mapě (tvar náhledu);
// poVybrani — zavolá se po klepnutí na řádek.
export function initTvorVyber({ kontejner, select, hledat, tvorove, hex, poVybrani }) {
  if (!kontejner || !Array.isArray(tvorove)) return null;
  let vidi = tvorove;

  function vykreslit() {
    kontejner.innerHTML = vidi.length
      ? vidi.slice(0, 400).map((t) => radekHtml(t, hex)).join('')
      : '<p class="tv-nic">Nic takového v bestiáři není.</p>';
    oznacit();
  }

  function oznacit() {
    const vybrano = select ? String(select.value || '') : '';
    kontejner.querySelectorAll('.tv-radek').forEach((r) => r.classList.toggle('vybrano', r.dataset.tvor === vybrano));
  }

  function filtrovat(dotaz) {
    const q = String(dotaz || '').trim().toLowerCase();
    vidi = q ? tvorove.filter((t) => t.nazev.toLowerCase().includes(q)) : tvorove;
    vykreslit();
  }

  kontejner.addEventListener('click', (e) => {
    const r = e.target.closest('.tv-radek');
    if (!r || !kontejner.contains(r)) return;
    if (select) {
      select.value = r.dataset.tvor;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    oznacit();
    if (poVybrani) poVybrani(r.dataset.tvor, vidi.find((t) => String(t.id) === r.dataset.tvor) || null);
  });
  if (hledat) hledat.addEventListener('input', () => filtrovat(hledat.value));
  if (select) select.addEventListener('change', oznacit);
  vykreslit();

  return { filtrovat, oznacit, vykreslit, pocet: () => vidi.length };
}
