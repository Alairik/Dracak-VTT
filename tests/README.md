# Testy

Automatické kontroly, které se pouštějí **jen lokálně** (cloud session nebo vývojový počítač) proti lokální MariaDB. Na hosting
se nenasazují (`tests/**` je v `exclude` v `.github/workflows/deploy.yml`) a nesmí se spouštět proti ostré databázi:
`tests/fixture/seed.sql` před vložením testovacích dat **maže** postavy, mapy, tokeny i průběh her.

## Předpoklady

- MariaDB / MySQL s uživatelem `root` bez hesla přes socket (`mysql` v příkazové řádce), PHP 8.x, Node a Playwright s Chromiem
  (výchozí cesty `/opt/node22/lib/node_modules/playwright` a `/opt/pw-browsers/chromium-1194/chrome-linux/chrome`; jiné přes `PLAYWRIGHT`, `CHROMIUM`).
- Postup instalace MariaDB v cloud session: `docs/stav-projektu.md`, „Lokální ověření na skutečné DB“.

## Postavení testovací databáze

```bash
tests/build-fixture.sh --config        # databáze „dracak“: pravidla + migrace + testovací účty + testovací data; založí config.php, pokud chybí
tests/build-fixture.sh dracak --force  # postavit znovu (smaže existující databázi stejného jména!)
```

Testovací účty: `test@local` / `test1234` (admin, PJ světa 1) a `hrac@local` / `hrac1234` (hráč). Hesla jsou jen lokální a hashe se generují při stavbě,
v repu žádné nejsou. Testovací data: svět 1, postavy Hrun / Sindor (ID 2, hráč) / Prázdná / Nulová odolnost, mapa 1 (čtverec) a mapa 2 (hex), jedna zeď.
Podklady map (`uploads/mapy/testmapa_*.png`) vyrobí `tests/fixture/make-images.py`; jsou v `.gitignore`.

## Spuštění

```bash
tests/run.sh                  # všechno (unit i e2e), před každou sadou se testovací data vrátí do výchozího stavu
tests/run.sh souboj_api       # jedna sada (jméno z tests/e2e bez .js)
tests/run.sh unit/log_unit    # jedna unit sada
```

`run.sh` podle potřeby spustí `php -S 127.0.0.1:8099 -t .` (jiný port přes `BASE`, jiný kořen webu přes `DOCROOT`) a vypíše `ok` / `SELHALO` u každé sady.
Sada selhala, když skončí nenulovým kódem nebo vypíše řádek začínající `CHYBA`, `SELHALO`, `PÁD`, `FAIL` či `CHYB: n`.
Proměnné: `DB` (výchozí `dracak`), `BASE`, `OUT` (screenshoty, výchozí `/tmp/dracak-tests`).

## Co je kde

| Složka | Co |
|---|---|
| `tests/unit/` | `log_unit.php` (chybový log), `migrace_unit.php` (rozdělení SQL a zakázané příkazy; část proti DB zatím jen ručně), `zaloha_test.php` (záloha → obnova do prázdné DB, kontrolní součty), `nasazeni_unit.php` (kontrola zavřených cest: aplikace v podsložce domény, doména vydávající cizí obsah) |
| `tests/e2e/` | Playwright a API sady: souboj a iniciativa (`souboj_api`, `ini_v2_api`, `vyzva_api`, `hex_*`), efekty (`efekty_*`), zbraně a vytasení (`zbrane_*`, `tasit_zdarma_api`), deník (`denik_v2`, `denik_dock`, `inv_ui`), tok pozvání a oprávnění (`e2e_hra`, `druzina_*`), chybový log a záloha (`log_ui`, `zaloha_ui`), review regrese (`review2_api`) |
| `tests/e2e/fuzz_api.js` | Náhodné špatné vstupy do všech endpointů `hra/api/*.php` (názvy parametrů se vytáhnou z kódu; jiný typ, záporné, obří, null, pole, dlouhý text) jako PJ, hráč i nepřihlášený; server nesmí spadnout (5xx) ani zapsat chybu do logu. Není v `run.sh`: `cd tests/e2e && SEED=3 KOLIK=15 node fuzz_api.js`, před spuštěním reset dat (`mysql dracak < ../fixture/seed.sql`). Nalezla pády na obřích souřadnicích a velikosti mřížky, nekonečnou smyčku v mlze a dlouhý název pinu |
| `tests/e2e/fuzz_stranky.js`, `fuzz_formulare.js` | Totéž pro stránky: náhodné parametry v adrese (GET) a náhodné hodnoty ve formulářích (POST; názvy polí z kódu stránky). `fuzz_formulare.js` si před během uloží tabulku `ucty` (admin.php umí účty měnit) a po běhu ji vrátí. Nalezly pád úpravy postavy s hodnotou mimo rozsah a nového světa s neplatným datem nebo dlouhým názvem |
| `tests/e2e/monkey_ui.js` | „Opice“: náhodné klikání, psaní a tažení v rozhraní (mapa, deník, svět, administrace) jako PJ i hráč na různých velikostech okna; sbírá chyby JavaScriptu na stránce, odpovědi 5xx a záznamy v chybovém logu. `cd tests/e2e && SEED=1 KROKU=100 node monkey_ui.js` po resetu dat. První běh (465 kliknutí) bez nálezu |
| `tests/e2e/upload_api.js` | Nahrávání obrázků: platné PNG projde a vydá se, obří rozměry / text s příponou .png / SVG se skriptem / soubor nad 4 MB dostanou 422, PHP kód přilepený za PNG se překódováním zahodí, nepřihlášený nic nenahraje. Nalezla, že obrázek s desítkami Mbodů by na hostingu s 128 MB paměti spadl na vyčerpané paměti |
| `tests/e2e/xss_ui.js` | Uložený XSS: útočný kód se uloží do všech uživatelských textů (název světa, jména postav a účtů, poznámky, shrnutí, popisky tokenů a pinů, text kresby) a otevřou se všechny stránky, které je vypisují; nic se nesmí spustit. 2026-10-31: bez nálezu (12 stránek) |
| `tests/e2e/editor_ui.js` | Editor číselníků po přechodu z inline atributů na `data-*` a delegovanou obsluhu (CSP): kroky +/− u hodnoty štítku, přidání a odebrání štítku (i právě přidaného), odeslání filtru po změně, potvrzení před smazáním (Zrušit nic nesmaže), žádná chyba JavaScriptu |
| `tests/e2e/csp_api.js` | Content-Security-Policy: hlavička s nonce na 18 stránkách, nonce sedí ke všem inline skriptům a je pokaždé jiný, žádný inline `on*` atribut; `csp-report.php` bere jen POST, ignoruje nesmysly, ořezává obří hlášení, nezapisuje tajné hodnoty z adresy a drží strop 60 hlášení za minutu |
| `tests/e2e/soubehy_api.js` | Souběžné požadavky z osmi samostatných přihlášení (jedno přihlášení se v PHP řadí za sebou, souběh je vidět až při víc relacích): 60 současných zásahů −1 života musí dát přesně −60 (dřív se zásahy ztrácely: 951 místo 940), totéž magenergie, únava, zkušenost, počet kusů v inventáři; poslední kus lektvaru jde použít jen jednou; v souboji projde jen tolik akcí, kolik jich tvor má, a současné „Další tah“ nespadne; chybový log zůstane prázdný |
| `tests/e2e/ini_prenos_api.js` | Přenos výsledku iniciativy ≤ 0 do dalšího kola (h1622 b10805): kdo skončí na 0 a méně, hází v dalším kole znovu, ale místo obvyklého modifikátoru přičte výsledek z minulého kola (−3 + hod 5 = 2); ruční úprava PJ se přičte dál; kdo skončí na 1 a víc, nic nepřenáší; důvod je v události hodu |
| `tests/e2e/poznamky_ui.js` | Poznámky světa: auto-návrh štítku (klíčové slovo, vazba na postavu, na místo), zvolený štítek má přednost, neplatné štítky / postavy / příjemci se zahodí, odznak nepřečtených (seznam světů i svět), úprava jen autorem (cizí = 403) a její vliv na „nové“, filtr podle štítku, escapování textu, dohled admina, odebrání hráče ruší sdílení (jeho postava a poznámky zůstanou) |
| `tests/e2e/dvere_api.js` | Dveře (zeď s příznakem `dvere`): zavřené blokují pohyb hráče i výhled, otevřené nic; přepíná je jen PJ (hráč 403); obyčejnou zeď nejde přepnout; změna skrytých dveří neprosákne hráčům přes polling; v prohlížeči PJ otevře dveře klikem |
| `tests/e2e/videni_api.js` | Vidění a světlo na mapě „Tma“: bez světla hráč vidí jen sebe, pochodeň osvětlí kruh 6 sáhů, nestvůra mimo dosah / za zavřenými dveřmi / za zdí se hráči vůbec nepošle (seznam tokenů ani události), infravidění ukáže teplokrevné tvory v dosahu (ne studenokrevné, ne za zdí, ne dál), co viděl, zůstane v paměti, hráč nesmí měnit světlo ani osvětlení (403), PJ vidí všechno a kruh dosahu, v panelu tokenu nastaví lucernu, na mapě „Světlo“ se nic nemění |
| `tests/e2e/boj_ve_tme_api.js` | Boj ve tmě: na mapě „Tma“ má hráč, který cíl nevidí, postih −5 k útoku (se světlem žádný, na mapě „Světlo“ žádný); PJ přepne −4 / −2 / žádný, hráč ne (403), neplatná volba 422; zasáhne-li útočník s postihem, zranění se počítá bez něj; nestvůra nedostane postih automaticky |
| `tests/e2e/zony_tmy_api.js` | Zóny kouzel Tma a Temnota (bez DDL: token se `svetlo_typ` `tma` / `temnota`): hráč zónu nastavit nesmí (403), poloměr nad 300 sáhů a neznámý druh 422; zdroj světla ani buňky uvnitř Tmy nesvítí a infravidění v ní nefunguje (pozorovatel i cíl), v Temnotě světlo dosáhne jen 0,3 sáhu a infravidění má 1/10 dosahu, zóna sama nesvítí a hráč ji nevidí; PJ ji vidí jako fialový kruh a nastaví v panelu tokenu |
| `tests/e2e/noc_videni_api.js` | Stav mapy „Noc“ a noční vidění (migrace 0108): stav osvětlení mění jen PJ (hráč 403, neznámý stav 422), v noci bez světla a bez nočního vidění hráč vidí jen svou buňku, tvor s nočním viděním vidí kruh do svého dosahu (ne dál, ne za zavřenými dveřmi ani zdí, ne v zóně Tma / Temnota), sám svítící tvor je vidět i dál, v úplné „Tmě“ noční vidění nepomáhá, PJ nastaví stav a dosah v rozhraní; `boj_ve_tme_api.js` navíc kontroluje postih −2 lupiče v noci |
| `tests/e2e/hodiny_sveta_api.js` | Hodiny světa a hoření světel (migrace 0109): čas ve dni po směnách (posun jen PJ, hráč 403, meze 422, přelití přes půlnoc do dalšího dne, přesné nastavení), pochodeň 3 směny / lucerna 24 směn / Temnota 3 směny po vypršení zhasnou (hráč pak nevidí, události `token_zmena` a `cas_zmena` dorazí hráči), světlo bez limitu nehoří, hráč dobu hoření nastavit nesmí; v prohlížeči čas v liště, tlačítka jen PJ, předvolby v panelu tokenu nastaví dobu, administrace nastaví čas ve dni |
| `tests/e2e/pin_vstoupit_api.js` | Přechod mezi lokacemi: „Vstoupit s družinou“ u pinu — hráč 403, pin bez cílové mapy 422, neexistující 404, za boje 409; družina se přenese na střed cílové mapy, nebo na pin vedoucí zpátky, na volnou buňku vedle obsazené, bez zdvojení postavy; nestvůra zůstane, aktivní mapa světa se přepne, hráč dostane událost; v prohlížeči tlačítko v informaci o pinu přepne PJ i hráče, obyčejné „Nastavit jako aktivní“ hráče nepřesouvá |
| `tests/e2e/raid_panel_ui.js` | Raid panel (jen PJ): hráč ho nemá ani PJ doplňky tokenu; PJ ho otevře z lišty, nestvůry a NPC nahoře, pak družina s počty, podtitul a životy; myš nad boxem zvýrazní token a naopak; klik vybere token bez posunu kamery; tažení boxů mění pořadí a „↺ pořadí“ ho vrátí (přežije obnovení); kompaktní řádky, přesun okna za hlavičku a sbalení se pamatují; vyřazený tvor je šedý a na konci skupiny; v souboji jsou boxy podle iniciativy, bez nadpisů skupin, tvor na tahu zlatý |
| `tests/e2e/fuzz_souboj.js` | Náhodná procházka soubojem (PJ i hráč posílají přes API náhodné i nesmyslné akce); hledá chyby serveru (5xx) a záznamy v chybovém logu. Není v `run.sh` (náhodná, pomalá): `cd tests/e2e && SEED=3 KROKU=200 node fuzz_souboj.js`, vždy po resetu dat (`mysql dracak < ../fixture/seed.sql`). Nalezla pád Dalšího tahu u tvora s 0 akcemi |
| `tests/e2e/qa_zarizeni2.js`, `poll_load.js` | Měření, ne kontrola: screenshoty na telefonu a tabletu; požadavky a dotazy do DB na hráče za minutu (zapíná general log MariaDB). Spouštějí se ručně: `cd tests/e2e && node qa_zarizeni2.js` |
| `tests/fixture/` | `seed.sql` (reset a testovací data), `make-images.py` (podklady map) |

## Co testy nepokrývají

- Ostrý hosting (limity WEDOSu, `.htaccess`, FTP). Zavřené cesty a soubory na serveru ověřuje stránka **Kontrola nasazení** v administraci.
- Stránka **Změny databáze** pod účtem jen s právy DML a migrace s `ALTER` (ověřovalo se ručně na kopii databáze s účtem bez `ALTER`).
- Dotyková gesta na skutečném zařízení.

## Když přidáváš test

Nová sada patří do `tests/e2e/` (název `téma_api.js` / `téma_ui.js`), bere adresu z `process.env.BASE`, databázi z `process.env.DB`, nikdy nespoléhá na stav,
který jiná sada nechala (`run.sh` ho vždy resetuje), a po sobě uklízí tokeny a souboj. Přidej ji do `E2E_VYCHOZI` v `tests/run.sh`.
