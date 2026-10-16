# Tah v souboji — jedno gesto místo hlášení akcí (návrh v2, 2026-10-16)

Nahrazuje lištu akcí z kroku 2a/2b/2c (`docs/navrh-souboje-v1.md`). Pravidla se nemění,
mění se způsob ovládání: hráč na tahu **jen klikne na mapu**, systém z toho pozná akci
(krok, běh, sprint, otočka, útok i jejich spojení), spočítá cenu v akcích a po potvrzení
provede přesun, hod, obranu a výsledek. Podrobný průběh a rozhodnutí uživatele níž.

## Rozhodnuto (uživatel, 2026-10-16)

- **Krok, běh i sprint stojí jednu akci** (veškerý běžný pohyb) a **i s následným útokem je to
  pořád jedna akce** (krok a útok z knihy h1623, běh a útok a sprint a útok jako HB). Oprava
  uživatele 2026-10-16 — dřív jsem u běhu / sprintu a útoku počítal 2 akce, to neplatí.
- **Běh a sprint smí kdykoli**, kromě únavy (unavená postava nesmí sprintovat, vyčerpaná
  ani běhat — tabulka únavy h1634). Úmysl „Pohyb“ ovlivňuje jen iniciativu.
- **Bonus za rozběh (HB, zapsáno do knihy b93089–b93090):** sprintuje-li tvor dvě akce za sebou
  a následně zaútočí (útok smí být spojený s druhým sprintem), má k tomu útoku **+1 ÚČ**.
- **Čtvrtý hex (sprint s dovedností Běh na krátké vzdálenosti):** při pokusu o 4 hexy bez
  platné dovednosti systém nabídne **hod proti pasti** (past na sílu, střední obtížnost, h1698
  a h1686). Úspěch = dovednost platí 3 kola (+1 hex k běhu a sprintu), neúspěch = nejvýš 3 hexy.
- **PJ vidí každý automaticky vyvolaný hod** (ověření dovednosti, překonání pasti…) a smí
  před hodem změnit obtížnost. Obecný mechanismus „výzva k hodu“ pro všechna místa v
  pravidlech, kde se něco hází; kolik jich bude, zatím nevíme.
- **Plán se potvrzuje jedním potvrzením** (plovoucí tlačítko u posledního bodu, dvojklik je
  jen zkratka). Systém vždy ukáže, co vybral („Sprint a útok · 1 akce“), a jde to změnit
  („jen pohyb“, „jen útok“).
- **PJ může vynutit a ovládat vše,** včetně přeskočení na dalšího v pořadí; čekání na
  obránce nemá časovač, PJ rozhodne za hráče tlačítkem.
- **Animace** přesunu a úderu jsou druhý krok, vždy přeskočitelné; skrytá nestvůra (mlha)
  nesmí animací prozradit pohyb.
- **Úmysl před iniciativou zůstává** (pravidlo knihy, ovlivňuje bonusy).

## Co kliknu → co systém pozná

| Klik | Akce | Cena |
|---|---|---|
| hex vedle | Krok | 1 |
| body A, B | Běh | 1 |
| body A, B, C | Sprint | 1 |
| bod D (s platnou dovedností) | Sprint | 1 |
| nepřítel v dosahu | Útok | 1 |
| nepřítel v dosahu po otočení | Otočka a útok | 1 |
| nepřítel 1 hex dál | Krok a útok | 1 |
| nepřítel 2–4 hexy dál | Běh / Sprint a útok | 1 |
| klik do těla tvora / ↶ ↷ | Otočka (čelo) | 1 |
| „Další akce…“ | Čekání, Obrana, Obrana a krok, Jiná činnost, Kouzlení | podle akce |

K nepříteli daleko si systém sám vybere hex, ze kterého dosáhne (nejkratší cesta, pak nejmenší
otáčení); jiný hex u cíle jde vybrat klikem na bod cesty a jeho úpravou. Nedosáhne-li, naplánuje
cestu co nejblíž a řekne proč. Plán nesmí stát víc akcí, než zbývá v tomhle tahu (kvóta půlky
kola) — jinak nabídne aspoň samotný pohyb.

## Průběh tahu (cílový stav)

1. **Plán** (náhled cesty, cena, zbývající akce, čelo na konci). Začíná sám, jakmile je tvor na tahu
   (Esc / „Zrušit“ ho vypne do dalšího tahu, „Plánovat tah“ ho zapne).
2. **Potvrzení.**
3. **Přesun** po vybraných hexech (animace).
4. **Hod** (útok, ověření dovednosti / past): tlačítko „Hodit“ → 3D kostka; hody vidí všichni; PJ
   vidí výzvu a může před hodem upravit obtížnost.
5. **Obrana** napadeného (okno „bránit se / nebránit se“; PJ může rozhodnout za hráče).
6. **Výsledek** (zásah, zranění, životy) + animace úderu.
7. **Efekty** (hoření, kyselina, spánek…) — tikají na začátku tahu postavy, ukazují se na tokenu.
8. **Další postava** (PJ smí přeskočit).

Každý krok je událost na serveru; klienti přehrávají animace z událostí a kdo se připojí
uprostřed, přeskočí rovnou na výsledek. Server je autorita (klient jen navrhuje).

## Etapy

1. **Klikací tah (hotovo 2026-10-16):** plán cesty + útok na nepřítele z jednoho gesta, automatické
   rozpoznání akce (včetně „běh/sprint a útok“, vše za 1 akci), běh a sprint bez vazby na úmysl,
   lišta akcí zmizí (zbude „Další akce…“), plovoucí potvrzení. Útok se zatím jen zapíše (cíl),
   hody přijdou v etapě 2.
2. **Hody a výsledek (hotovo v kódu 2026-10-16, čeká na ruční SQL `souboj_vyzva`):** výzva k hodu
   (PJ vidí a upraví), útok proti obraně, okno Obrana, zranění, bojeschopnost a vyřazení; hod proti
   pasti u 4. hexu; bonus za rozběh (+1 ÚČ). Podrobně „Poznámky k etapě 2“ dole.
3. **Animace a efekty:** přesun po hexech, výpad a číslo zranění, efekty s trváním (hoření,
   kyselina…) na začátku tahu, přeskočit.
4. **Zbraň a dosah:** výběr zbraně / kouzla při útoku, dlouhé zbraně, střelba (≥ 2 hexy, volná dráha).

## Na co nezapomenout

- Vícenásobné útoky (válečník, nestvůry) a zvláštní útoky (zezadu −4/−6, na vyřazeného, neviditelného,
  naplocho, předstíraný výpad).
- Útok přeruší tah (h1623, b10821): fronta událostí a stav „čeká na obranu“.
- Zpět před hodem ano, po hodu ne (zůstává „Krok zpět“ pro PJ).
- Mlha války se odhaluje po cestě; skrytý pohyb nesmí prozradit animace.
- Na dotyku nejde spoléhat na dvojklik — potvrzení plovoucím tlačítkem.
- Čtvercová mapa (bez hexů) zůstává na prostém odečítání akcí.
- Vyřazená postava tah přeskočí.

## Otevřené věci

- **Číslo pro obtížnost „střední“** u dovedností (Běh na krátké vzdálenosti: síla, střední) kniha v
  přepsaném textu nemá (`pasti.obtiznost` je volný text, `zvlastni_schopnosti.mechanika` jen „Obtížnost:
  střední“). Do doplnění z knihy ho před hodem nastavuje PJ a systém si ho pamatuje (příští hod stejné obtížnosti
  se předvyplní). Stejně chybí čtyřstupňová škála výsledku (fatální neúspěch / úspěch / fatální úspěch —
  `vysledky_testu`, h `Fatální neúspěch a Totální úspěch` „zatím nepřečteno“): vyhodnocuje se jen úspěch a neúspěch.
- ÚČ / OČ nestvůr z textu bestiáře: u zápisů s výsledkem („(+1 + 3) = 4“) se čísla vezmou sama, u vzorců
  bez výsledku („+1 + síla zbraně“, „kvalita zbroje + štít“) je doplňuje PJ před každým hodem — vyřeší malý
  deník nestvůry (krok 4).

## Poznámky k etapě 1 (co je hotové a jaká jsou omezení)

- Server: druhy `beh_utok` a `sprint_utok` (stojí 1 akci jako ostatní); bod únavy za sprint se
  přičítá jednou i u „sprint a útok“; `souboj_pohyb.jen_pri_umyslu` je pro běh a sprint
  NULL (migrace 0087), takže je nehlídá úmysl.
- Klient: jediný plán „tah“ (`assets/js/souboj.js`), `druhPlanu()`; klik na nepřítele
  hledá nejméně hexů, pak nejméně otáčení; když nedosáhne, naplánuje cestu co nejblíž a řekne proč.
- Omezení: auto-trasa k nepříteli jde jen nejkratšími cestami (bez objížděk); PJ, který chce tokenem
  volně hýbat, musí plánování vypnout (Esc); útok se zatím jen zapíše.

## Poznámky k etapě 2 (hody a výsledek)

- **Výzva k hodu** (`souboj_vyzva`: mapa, druh `utok` / `obrana` / `past`, stav `ceka` → `hozeno` → `hotovo` /
  `zruseno`, kdo hází, protivník, JSON s čísly) je jediné místo, kde čeká hod; klienti ji dostávají v
  `mapa_stav.php` (`vyzvy`) a o změně se dozvědí událostí (`vyzva_nova`, `vyzva_zmena`, `vyzva_hod`,
  `souboj_vysledek`, `vyzva_zrusena`). Skrytý tvor (token skrytý hráčům) nikdy nevyzradí jméno; událost je
  skrytá jen tehdy, když jsou skrytí oba (útočník i obránce). Staty nestvůr (ÚČ, OČ, opravy, útočnost) hráč
  nevidí — u hodu nestvůry dostane jen hody a celkový součet (bestiář je tajný), do veřejné události výsledku
  nepatří ani útočnost zbraně, ani životy skrytého obránce.
- **Průběh útoku:** `tah_akce.php` odehraje pohyb a založí výzvu „Útok“ (tah se nepřeposouvá) →
  `vyzva_uprava.php` (PJ: oprava, ÚČ/OČ, útočnost; vlastník: zbraň, volba obrany) → `vyzva_hod.php` →
  po hodu útoku vznikne „Obrana“ → po hodu obrany vyhodnocení (zásah, zranění, životy, akce obránce) →
  tah útočníka pokračuje. `vyzva_zrus.php` (jen PJ) zruší vše rozehrané.
- **Pravidla:** zásah a zranění h1612; obrana h1611 a h1623 (bránit se akcí stojí akci, bez zbraně −3, z boku nebo
  zezadu −4 / dohromady −6); opravy útoku h1610 (bojeschopnost, −1 velké naložení, rozdíl velikostí); rozběh = HB.
- **Čtvrtý hex:** `tah_akce` ověří cestu o 4 hexech, jako by dovednost platila, ale neprovede ji — založí
  „past“ s uloženým požadavkem; po úspěchu `dracak_vtt_past_vyhodnot` nasadí efekt a požadavek provede znovu
  (v téže transakci, jako původní zadavatel).
- **Vyřazený tvor** tah přeskočí a hráč za něj nejedná; PJ ano.
- **Nezapomenout dál (etapa 4 a později):** výběr zbraně při útoku, dlouhé zbraně, střelba, „obrana a krok“,
  vícenásobné útoky, zvláštní útoky (neviditelný, nehybný, na vyřazeného), malý deník nestvůry.
