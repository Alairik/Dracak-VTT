# Panel tokenu → kompaktní deník — návrh (2026-11-09)

**Stav: část A/B/C se už staví.** Rozhodnutí uživatele 2026-11-09, že směr je správný. 2026-11-10 vzniklo
plovoucí okno tvora (`assets/js/tvor_okno.js`) a uživatel k němu dal připomínky na designovém plátně
https://claude.ai/artifact/ShFfKGCh8AUcW3jK3ncVnQ — co z nich plyne, je v `docs/stav-projektu.md`
(sekce „Zapracované připomínky z plátna“). Nejdůležitější obecné pravidlo, které z toho vzešlo:
**vysvětlivka k sekci patří do bubliny za otazníčkem, ne jako text v okně** (je i v `CLAUDE.md`).

## 1. Co je dnes špatně

Panel, který se otevře klikem na token (`#panel-spravovat` v `hra/mapa.php`), míchá tři různé věci dohromady:

| Druh | Co to je | Patří to k |
|---|---|---|
| **Token** | barva, obrázek, otočení, velikost, zabírá hexů, zámek polohy, skrýt před hráči, smazat | značce na mapě |
| **Tvor (herní)** | životy, magenergie, efekty, inventář, dosah pohybu, světlo, infravidění, noční vidění | postavě / nestvůře |
| **Nástroj PJ** | rychlé efekty, aplikovat efekt s trváním, ±životy, ±magy, kořist | právům PJ, ne tokenu |

Druhý řádek je **duplicitní s deníkem**: `hra/denik.php` už má Postup, Obranu, Boj, Pohyb a naložení, **Aktivní efekty**, Inventář,
Kontejnery, Peníze, Kouzla i Zvláštní schopnosti — a `assets/js/denik_dock.js` ho umí ukázat jako plovoucí okno přímo nad mapou.
Vznikl tak „deník lite, který není deník“ (slova uživatele): dvě místa ukazují totéž, každé jinak, a musí se udržovat obě.

Navíc **tokeny a jejich ovládání se 2026-11-09 přesunuly do lišty dole uprostřed** (`assets/js/token_bar.js`), takže část panelu
je dnes na dvou místech i prostorově.

## 2. Návrh: rozdělit podle toho, ČÍ ta věc je

Ne „zrušit panel“, ale rozdělit ho na dvě různé věci, protože token a tvor nejsou totéž (obecný token — sud, překážka, značka —
žádného tvora za sebou nemá).

### A) Vrstva tokenu — malý nástroj PJ, zůstává

Barva, obrázek, otočení, zámek polohy, skrýt před hráči, pořadí vykreslení, smazat (u NPC = vrátit do zásoby).
Jsou to vlastnosti **značky na mapě**, ne postavy, a pravidel se netýkají. Patří k liště správy tokenů dole,
nebo do malé nabídky u tokenu — ne do deníku.

**Velikost** sem patří jen jako **oprava výjimky**: normálně se odvodí z tvora (třída velikosti z bestiáře u nestvůry, rasa u postavy),
jak to už dnes dělá `token_pridat.php` u „zabírá hexů“ (D = 3, E = 5). Řádek „Zabírá hexů“ se navíc nemá ukazovat na čtvercové mapě,
kde hexy nedávají smysl.

### B) Vrstva tvora — kompaktní deník, žádné „lite“

Klik na token otevře **ten pravý deník**, jen v kompaktním režimu:

- **postava** → `hra/denik.php?embed=1` v docku (existuje), sbalený na to podstatné: životy, magenergie, zbraň v ruce, efekty, použitelné věci;
- **nestvůra** → deník nestvůry (existuje, `hra/api/nestvura_denik.php`) doplněný o životy, efekty a výbavu, aby to byl tentýž druh okna;
- **obecný token** (sud, překážka) → žádný deník, jen vrstva A.

Tím zmizí druhá implementace životů, efektů a inventáře.

### C) Vrstva PJ — uvnitř téhož okna, ne vedle něj

PJ potřebuje nad cizím tvorem věci navíc (zadání uživatele 2026-11-09): **zamknout**, **nasadit a sundat efekt i mimo iniciativu**,
rychle ±životy a ±magy, kořist. Návrh: **sekce „PJ“ přímo v kompaktním deníku**, viditelná jen PJ — ne samostatné okno.
Hráč v témže okně vidí svůj deník beze změny.

Důležité: aplikovat efekt musí jít **i když neběží souboj** — dnes to tak funguje a musí to tak zůstat.

### D) Světlo se přesune k předmětu

Dnes PJ nastavuje „Pochodeň (6 sáhů)“ na tokenu ručně, i když postava pochodeň fyzicky má v inventáři. Správně má být v deníku
u předmětu akce **„Zapálit / zhasnout“** a token se rozsvítí sám (doba hoření už existuje, `tokeny.svetlo_zbyva`, 3 směny u pochodně).
Ruční nastavení světla zůstane jen tam, kde deník není: nestvůra a obecný token (ohniště, svítící socha, kouzlo Tma).

### E) Infravidění a noční vidění — NEMAZAT, dotáhnout data

Uživatel navrhl pole zrušit s tím, že „ani potvory to nemají“. **Ověřeno v pravidlech: mají.** `content/pravidla-bestiar.html`
mluví o „infravidění na 30 sáhů“, „infravidění na deset sáhů“ a dalších; v databázi to zmiňuje popis u 8 nestvůr
(Zmije rohatá, Haterie, Hlen zelený, Vážka jeskynní, Krysodlak, Mlok…). Chybí jen **strukturovaně** — tabulka
`nestvura_schopnosti` nemá navázanou ani jednu. U postav je to naopak hotové: Infravidění je schopnost **Trpaslíka, 20 sáhů**.

Správný postup tedy je:
1. u postavy předvyplnit z rasy (jde hned, data jsou);
2. u nestvůr **doplnit data z bestiáře** (migrace podle textu knihy, s citací) a pak taky automaticky;
3. ruční pole ponechat jako opravu pro to, co v datech není — jinak nepůjde postavit tvor, kterého kniha popisuje.

Tohle je přesně případ pravidla z `CLAUDE.md`: *„existující hodnota v DB NENÍ důkaz, že je správně — ověř ji proti textu pravidel“*.
Tady platí i obráceně: **chybějící hodnota v DB není důkaz, že to v pravidlech není.**

## 3. Co z toho plyne pro pořadí prací

Je to přestavba plochy, která se při hře používá nejvíc. Dělat ji na jeden zátah těsně před zkušebním večerem je špatný nápad —
večer by pak zkoušel rozdělanou věc místo hry. Navrhované pořadí:

| # | Krok | Kdy | Velikost |
|---|---|---|---|
| 1 | Velikost se odvodí z tvora; „Zabírá hexů“ jen na hexové mapě; infravidění u postavy z rasy; „Vzhled“ sbalený | **hned** (schváleno 2026-11-09) | malé |
| 2 | Zkušební večer | po kroku 1 | — |
| 3 | Vrstva A: ovládání tokenu do lišty dole, panel se o ně zmenší | po večeru | střední |
| 4 | Vrstva B: klik na token otevře kompaktní deník; panelu zmizí životy, magy, efekty, inventář | po večeru | **velké** |
| 5 | Vrstva C: sekce PJ uvnitř deníku (zámek, efekty mimo iniciativu, ±životy, kořist) | s krokem 4 | střední |
| 6 | Deník nestvůry doplnit o životy, efekty a výbavu, ať je to tentýž druh okna | s krokem 4 | střední |
| 7 | Světlo k předmětu („Zapálit / zhasnout“ v inventáři) | po kroku 4 | střední |
| 8 | Infravidění nestvůr do dat podle bestiáře (migrace) | kdykoli, potřebuje projít knihu | střední |

## 4. Na co si dát pozor

- **Token ≠ tvor.** Obecný token (sud, překážka, značka) nemá deník a nikdy mít nebude — vrstva A musí fungovat samostatně.
- **Hráč vs. PJ v jednom okně.** Kompaktní deník uvidí obě role; co smí PJ navíc, musí hlídat server, ne jen schované tlačítko.
  Pravidla oprávnění jsou v `dracak_vtt_je_pj_sveta()` a v jednotlivých API, nesmí se obejít tím, že se UI jen přeskládá.
- **Mobil.** Deník je na telefonu spodní list přes mapu (`denik_dock.js`, hranice 760 px). Kompaktní deník musí počítat s tím,
  že na telefonu zabere skoro celou obrazovku — a PJ zároveň potřebuje vidět mapu.
- **Testy.** Dnešní panel pokrývá `raid_panel_ui`, `denik_dock`, `inv_ui`, `efekty_ui`, `videni_api`, `zony_tmy_api` a další.
  Při přesunech se nesmí ztratit kontrola toho, co hráč vidět NESMÍ (skryté nestvůry, cizí deník) — to hlídá `druzina_hrac` a `xss_ui`.
