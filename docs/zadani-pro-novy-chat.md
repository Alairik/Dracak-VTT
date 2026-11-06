# Zadání pro nový chat — Dračák VTT (stav k 2026-11-06)

Tenhle soubor je **předávací dokument**: předchozí chat vyčerpal kontextové okno, takže vše, co potřebuje navazující práce, je tady nebo odsud odkazované.
Čti ho spolu s `CLAUDE.md` (pravidla práce v repu, platí vždy) a `docs/stav-projektu.md` (živý přehled hotového a rozpracovaného).
Nic z toho nepřeskakuj: rozhodnutí uživatele, která tu jsou zapsaná, se **nikdy znovu neotevírají** a neptá se na ně.

## 0) Jak začít (první kroky nového chatu)

1. Přečti `CLAUDE.md`, pak `docs/stav-projektu.md` (celý — je dlouhý, ale je to zdroj pravdy o stavu) a tenhle soubor.
2. Zvedni lokální prostředí (sekce 3) a pusť **celou regresi** `tests/run.sh` (≈ 20 min, běží detašovaně — viz pasti v sekci 3). Poslední plná regrese
   (61 sad = 54 e2e + 7 unit) prošla před poslední dávkou úprav (zrychlení stínů, varování magenergie); po nich prošlo jen 13 souvisejících sad.
   Cíl: mít zelený výchozí stav, než se začne něco nového.
3. Řekni uživateli jednou větou, kde jsi a co navrhuješ jako první věc (sekce 7), a čekej na jeho volbu. Neptej se na věci z tohoto dokumentu.

## 1) Uživatel a jak s ním mluvit

- Uživatel je **account manager** (junior HTML, medior marketing, medior webdesign), **ne vývojář**. Píše česky, odpovídej **česky**.
- **Žádné prázdné průpovídky, neopakuj jeho zadání**, nezačínej omáčkou. Buď věcný a stručný.
- **Buď trochu challenging** — nesouhlasit se vším automaticky; když se jeho názor liší od best practice, řekni mu to (a jak). Poctivě přiznej, co jsi netestoval.
- **Technická rozhodnutí (jak něco postavit) dělej sám**, nejbezpečnější cestou, a řekni jen výsledek. Ptej se **jen na věci, které jsou opravdu jeho**: co má hra dělat
  z pohledu hráče/PJ, nebo jak to je v knize. Každá otázka: jedna věta co se rozhoduje, příklad ze hry, tvoje doporučení. Žádné slovo typu DROP, index, tabulka,
  sloupec, migrace, ENUM, „varianta A/B“ podle technického rozdílu.
- Ruční SQL mu vždy posílej jako **hotový text ke zkopírování** (viz sekce 2), nikdy „otevři soubor X“.
- Když je něco hotové a ověřené, řekni to přímo; když něco jen vypadá hotově, řekni, co chybí (např. „server to umí, ale přes klikací plánovač jsem to neprošel“).

## 2) Pravidla práce v repu (shrnutí `CLAUDE.md` — při sporu platí `CLAUDE.md`)

- **Pushuj jen na `main`, sám, automaticky, bez ptaní** (trvalé povolení uživatele). Žádné feature větve, **žádné pull requesty**. Pokud session běží na přidělené
  pracovní větvi (`ccr-…`), po práci ji mergni do `main` (fast-forward) a pushni. Push na `main` spouští FTP deploy (`.github/workflows/deploy.yml`).
  `docs/**` a `tests/**` se nenasazují (jsou v `exclude`).
- **Nikdy žádná hesla ani přístupy v repu.** `config.php` je v `.gitignore`. Nemaž `.ftp-deploy-sync-state.json`. Složku `scripts/` nezakládej.
- **Nová DB migrace = nový soubor `database/migrations/NNNN_popis.sql`** (poslední je **0114**), starý nikdy neupravuj. Smí obsahovat jen CREATE, ALTER ADD a INSERT —
  **nikdy DROP / TRUNCATE / DROP COLUMN** bez výslovného schválení v konverzaci. Migrace se po pushi **nespustí samy**: uživatel klikne v administraci na
  **Změny databáze → Spustit čekající změny** (`admin_migrace.php`, `includes/migrace.php`). Po pushi s novou migrací mu to řekni jednou větou.
- **Web účet k DB je jen DML (SELECT/INSERT/UPDATE/DELETE), CREATE/ALTER neumí** (WEDOS to nedovolí). Proto každý DDL jde jako **ruční SQL do phpMyAdminu (admin účet)**:
  1. udržuj `docs/sql-k-rucnimu-spusteni.sql` (jediný soubor se všemi dosud neprovedenými DDL příkazy; **teď je prázdný** — všechno provedeno);
  2. v odpovědi vlož jeho **celý obsah** do code blocku, bez vynechávek, dokud uživatel nepotvrdí, že ho provedl;
  3. blok musí jít spustit **opakovaně a v libovolném stavu DB** (kontrola přes `information_schema` + `PREPARE/EXECUTE`, `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`)
     a končit kontrolním SELECTem s očekávanými čísly (vzor: historie souboru v gitu, např. commit `a371728`);
  4. než ho pošleš, ověř na lokální MariaDB (i po částečném provedení a po druhém spuštění) a že stránka Změny databáze pod DML-only účtem doběhne;
  5. po potvrzení uživatele soubor vyprázdni (vzor hlavičky je v současném souboru).
  **Číslu z phpMyAdminu nevěř** — dvakrát vyšlo 0, přestože sloupce v databázi webu byly (phpMyAdmin se díval do jiné databáze). Ověřuje se na stránce
  **Změny databáze → „Co aplikace v databázi vidí“** (název databáze, kterou web používá, a ✓ / ✗ u sloupců).
- **Zdroj pravdy o hře jsou pravidla** (`content/pravidla-hrac.html`, `-pj.html`, `-bestiar.html`, `toc-*.html`), **ne repo ani DB** (už se několikrát ukázalo špatně).
  Nejistotu v mechanice **neřeš odhadem** — najdi pasáž. Když v `content/` informace opravdu není, řekni to a požádej uživatele o foto z knihy. Při rozporu
  základní pravidla × homebrew platí **homebrew**; zjevné překlepy ve zdroji neopravuj sám, zapiš je do `docs/kontrolni-seznam-neuplnych-mist.md`.
  „Homebrew“ v komentářích DB neznamená „vedlejší doplněk“, ale obsah z Pravidel pro pokročilé — plnohodnotná pravidla.
- **„Doplň to do pravidel“** = do `/pravidla.php` (`content/…`, každý nový blok má unikátní `id="bNNNNN"`; řady hrac 93xxx, pj 94xxx, bestiar 95xxx), a pokud na datech VTT
  staví, i do DB (migrace). Strukturovaná mechanická data (kostky, efekty) se plošně nedoplňují bez předchozí domluvy.
- **`docs/stav-projektu.md` aktualizuj ve stejném commitu** jako netriviální práci (jinak zastará). Opakující se údaje drž tam, ne v odpovědích.
- **Testy:** před pushem změny v souboji, iniciativě, efektech, zbraních nebo deníku pusť příslušné sady; u změn základu (`includes/db.php`, `vtt.php`, auth) všechny.
  Testy **nikdy proti ostré databázi** (`tests/fixture/seed.sql` maže data her).
- **CSP:** nový inline `<script>` má `nonce="<?= dracak_csp_nonce() ?>"`, žádný inline `onclick`/`onsubmit`/`onchange` (použij `data-potvrdit`, `data-akce`…). Text z DB do `innerHTML` jen přes `esc()`.
- **Chybový log** (`logs/aplikace.log`, `admin_log.php`) je provozní data, ne obsah repa; nezapisuj do něj hesla ani tokeny.
- Každý commit končí řádky `Co-Authored-By: …` a `Claude-Session: …` podle systémového zadání session.

## 3) Lokální prostředí a pasti (cloud session) — **přečti, ušetří to hodiny**

**Zvednutí prostředí** (po každém restartu kontejneru je databáze i PHP server pryč — a restartuje se opakovaně i uprostřed práce):
```bash
(setsid nohup mysqld_safe --user=mysql >/tmp/claude-0/mysqld.log 2>&1 < /dev/null &)
for i in $(seq 1 40); do mysqladmin ping >/dev/null 2>&1 && break; sleep 1; done
mkdir -p /tmp/dracak-tests
(PHP_CLI_SERVER_WORKERS=4 setsid nohup php -S 127.0.0.1:8099 -t . > /tmp/dracak-tests/php-server.log 2>&1 < /dev/null &)
```
Když databáze neexistuje: `tests/build-fixture.sh --config` (viz `tests/README.md`). Testovací účty: `test@local` / `test1234` (admin, PJ světa 1), `hrac@local` / `hrac1234` (hráč, vlastní postavu Sindor, ID 2).

**Pasti, na které jsme narazili:**
- **`tests/run.sh … | tail` může viset donekonečna** (po restartu kontejneru PHP server drží rouru). Pouštěj detašovaně a čti log:
  `(setsid nohup tests/run.sh sada1 sada2 > /tmp/claude-0/log.txt 2>&1 < /dev/null &)` a čekej na `VŠE PROŠLO` / `SELHALO` (`until grep -qE "…" log; do sleep 5; done`).
  Pomalá volání mimo `run_in_background` skončí na limitu 500 s.
- **Když zadrhne vše** („mysqladmin ping“ nejde): zabij běžící `run.sh`/`node`, znovu zvedni MariaDB a PHP server (výše).
- **Neměň zdrojáky ani `git stash`, zatímco běží testy** — PHP server čte soubory za běhu, test pak selže z jiného důvodu, než si myslíš.
- Sady před spuštěním **resetují data** (`tests/fixture/seed.sql`); testovací Sindor má v seedu `max_magenergie = NULL` — testy kouzel si magy nastavují samy (`update postavy set max_magenergie=30 …`).
- V shell stringech (`mysql -e "…"`) **nepoužívej dvojité uvozovky uvnitř SQL** — rozbijí příkaz. Pro JSON v payloadu události použij `json_extract(payload, '$.klic') = true`.
- Některé e2e testy přepisují **sledované screenshoty** `tests/e2e/*.png` → před commitem `git checkout tests/e2e/*.png`.
- Test s regresní kontrolou: **ověř, že nový test bez opravy padá** (`git stash push soubor`, pustit, `git stash pop`) — jinak nic nehlídá. (Dělej to jen když neběží jiné testy.)
- Python skripty na úpravu souborů s českým textem: čti/piš s `encoding='utf-8'` a používej `assert s.count(old)==1`. **Nikdy nezkracuj soubor přes `cat >` bez heredoc** ani neopravuj přes slepý skript — jednou se tím soubor useknul (obnoveno z gitu).
- Funkce v `includes/` mají v češtině pojmenované proměnné; **kolize názvů funkcí** hlídej `grep -rn "function nazev" includes` (stalo se u `dracak_vtt_kouzla_postavy`).
- `window.MAPA.verzeModulu` (v `hra/mapa.php`) je **největší čas změny souborů JS modulů** — jinak prohlížeč drží starou kopii. Když přidáš do `mapa.js` nový modul, **přidej ho do `max(filemtime…)`**
  (u `svetlo_stiny.js` to chybělo a bylo to chyba).

## 4) Projekt v kostce

- **Dračák VTT**: virtuální stůl pro Dračí doupě (Dračí hlídka / DrD) — PHP bez frameworku (`declare(strict_types=1)`, PDO), MariaDB, JS bez buildu (ES moduly), česká UI.
  Hosting WEDOS (FTP deploy z GitHub Actions při pushi na `main`), repo `alairik/dracak-vtt`.
- Mapa `hra/mapa.php` + `assets/js/mapa.js` (kamera, tokeny, kresby, zdi, mlha, světlo), souboj `assets/js/souboj.js` + `includes/vtt_souboj*.php`,
  efekty `includes/vtt_efekty.php`, deník postavy `hra/denik.php` + `includes/vtt_denik.php`, cestování `includes/vtt_cestovani.php`, pravidla `pravidla.php` + `content/`.
- DB je strukturovaná proto, aby ji engine **skutečně spouštěl** (kostky, efekty, trvání), ne jen zobrazoval text — viz `CLAUDE.md`.
- Funkce za „feature flagy“ (`dracak_vtt_tokeny_funkce()` v `includes/vtt_mapa_stav.php`, kontrola sloupců přes `information_schema`): kód se **degraduje** a funguje i před ručním SQL.

## 5) Co se udělalo v poslední session (2026-11-04 až 06) — vše nasazeno, regrese zelená

Commity jsou na `main` (poslední `5ee00ff`). Stručně, s klíčovými soubory:

**A) Kouzlení v souboji** (`includes/vtt_kouzleni.php`, klient `assets/js/souboj.js`, `hra/api/kouzla_tvora.php`, `hra/api/souboj_konec.php`; migrace 0112–0114)
- Štítek-efekt **„Kouzlí“** (složka `nehybny`): kdo kouzlí, je pro útočníky nehybný (v **hodu na obranu** místo bonusu za obratnost −5; obratnost jako vlastnost se nemění).
- **Hra počítá akce kouzlení z vyvolání kouzla** (`dracak_vtt_kouzlo_akce`): N kol = 2N akcí, „1 akce“ = 1, **„ihned“ = 0**, směny a vzorce „za každých…“ se nepočítají
  (u nich a u nestvůr zadá PJ počet ručně). Kouzlo začíná působit **po předposlední akci**, poslední je odpočinek; zásah před předposlední akcí kouzlo zruší (magy propadnou).
- **Kouzlo se sesílá samo** (rozhodnutí uživatele: „proč by se to mělo dělat ručně“): akce kouzlení nese kouzlo, **cíl**, **trvání v kolech** a případně **magy**; magy se strhnou **při zahájení**
  (propadnou při přerušení), účinek (kostky, zranění/léčení, efekty) provede stejný kód jako „Použít“ v deníku (`dracak_vtt_polozka_pouzij_ucinek` v `includes/vtt_predmety.php`).
  Kouzla s hodem proti pasti zůstávají „čeká na past“ (rozhoduje PJ).
- **Kouzla „ihned“ (0 akcí) se vážou k jiné akci** (rozhodnutí uživatele: „vyhodnocovat právě tehdy; teoreticky víc ihned kouzel najednou k dané akci“): `tah_akce` přijme pole `ihned`
  (nejvýš 5; pohyb, útok, čekání… kromě kouzlení), `vyzva_uprava` přijme `ihned` u **obrany** (sešle se před hodem, přepočte opravy obrany). Server nejdřív ověří **celý seznam**
  (kouzla postavy, cíl, dosah, trvání, součet magů) a teprve pak zapíše. Klient: menu „Kouzla ihned…“ (fronta; třída `ihned-menu`, **ne** `dalsi-akce` — testy hlídají jediné „Další akce…“),
  blok „Seslat teď“ v kartě obrany, fronta se připojí i k **potvrzení naplánovaného tahu na mapě** (`sIhned` / `vyprazdniIhned` v `souboj.js`; dřív se tam tiše zahazovala).
- **Cena kouzla se nedá snížit zadáním** (`magy:0`): `dracak_vtt_kouzli_cena` bere `max(zadané, katalogová)`.
- **Postava bez nastavené magenergie kouzlí zdarma** (stará logika) — nově to **log hlásí** (`bez_magenergie` v události `kouzleni_stav`, ⚠ řádek v logu).
- Testy: `tests/e2e/kouzleni_api.js`, `kouzleni_ui.js`, `kouzleni_plan_ui.js`, `tests/unit/kouzleni_unit.php`.

**B) Světlo a stíny** (`includes/vtt_videni.php`, `includes/vtt_polygon.php`, `assets/js/svetlo_stiny.js`, `mapa.js`, endpointy `mlha_stav.php`, `mlha_nahled.php`, `svetlo_pj.php`; návrh `docs/videni-a-svetlo-navrh.md`, fáze 6)
- Zdi vrhají stín s polostínem, světlo slábne plynule, dohořívající světlo slábne (jen vzhled). Server posílá klientovi **jen mnohoúhelníky viditelnosti** (zdi samotné ne).
- Podmínky, aby se stíny kreslily: osvětlení mapy **Řídká tma / Tma / Hustá tma / Naprostá tma** (ne Světlo a Pološero), **mlha aktivní**, nastavený grid, token **svítí** (světlo nastavuje PJ v panelu tokenu),
  zdi z editoru zdí (bez zdí je světlo jen kruh). **PJ stíny nevidí, dokud nezaškrtne „Ukázat světlo a stíny“ v okně Mlha** (hráč je vidí vždy). Pod přepínačem je **stavový řádek**, který říká,
  co chybí (`dracak_vtt_videni_diagnoza`) — když uživatel řekne „nevidím stíny“, nejdřív zkontroluj tohle (poprvé to byl jen špatně nastavený stav mapy).
- **Kruh dosahu světla / zóny tmy se při tažení tokenu hýbe s ním v reálném čase** (PJ; `posunSvetloTokenu`, `poPresunuTokenu` v `mapa.js`); po položení se **hned** znovu stáhne přehled světla a mlhy.
  Samotné stíny se během tažení nepřepočítávají (rozhodnutí uživatele: stačí po položení).
- **Výkon ve městě** (uživatel: „stovky zdí tam teoreticky budou, každý dům má zvenku minimálně 4“): `dracak_vtt_videni_polygon` testuje paprsek jen proti zdem v jeho úhlovém rozsahu
  (binární hledání, sdílené rohy jednou). Město s 480 zdmi: divák 268 → 13 ms, celý požadavek `mlha_stav.php` ≈ 14 ms. Výsledek shodný s původním postupem (unit test na náhodných scénách).
  **Nezměněný a neměřený na plné družině** zůstává výpočet vidění po buňkách (`dracak_vtt_videni_vypocet`) — při pomalosti začít tam.
- Testy: `svetlo_stiny_ui.js`, `svetlo_tazeni_ui.js`, `tests/unit/videni_polygon_unit.php`, `videni_api`, `noc_videni_api`, `zony_tmy_api`, `boj_ve_tme_api`.

**C) Revize kódu** (`/code-review`, kouzlení + světlo) — opraveno: zadání ceny, varování magenergie, výkon polygonu. **Neopraveno** (vědomě, nízká priorita):
- kouzlo „ihned“ připojené ke sprintu se čtvrtým hexem (hod proti pasti) se sešle až po hodu a hráč to neví (hláška chybí; při přehrání akce se vše ověřuje znovu);
- `dracak_vtt_kouzli_ihned_proved` tiše přeskočí kouzlo, když cíl zmizel mezi ověřením a provedením (mělo by hlásit);
- `ihned_over` a `ihned_proved` opakují stejné dotazy do DB uvnitř transakce.

**D) Ostatní:** stránka Změny databáze má „Co aplikace v databázi vidí“; ruční SQL bloky A–I (migrace 0104–0114) **všechny provedeny**, `docs/sql-k-rucnimu-spusteni.sql` je prázdný;
chybový log, záloha, kontrola nasazení, fuzzery, raid panel, hodiny světa, pochod po mapě světa, jezdecká zvířata, zóny tmy, noční vidění — hotovo (detaily v `docs/stav-projektu.md`).

## 6) Co čeká na rozhodnutí uživatele (neřešit samo; 2026-11-04 řekl „zbylé body jen zatím zapiš“)

1. **Zlaté tetování** — ostatní značky mimo Dikobraze (doporučení: Gepard = dvojnásobná rychlost pohybu a rozběhu; Kobra = 2 okamžité akce, smí přerušit cizí kolo).
   **Pegas/Gryf** — označení „na zemi“ vypadá na špatném řádku v tištěné knize (ověřit; viz kontrolní seznam).
2. **Druh zbraně u 13 položek** (`docs/kontrolni-seznam-neuplnych-mist.md`: Fuuma shuriken, Atlatl, Řetězový meč, Khakkhara, Hůl „Minami no mori“, Rachejtle…).
3. **Deník v2** — které kroky (figurína a sloty, Dovednosti, Společníci, připnutá kouzla) a s jakými pravidly (`docs/prototypy/denik-v2-predani.md`).
4. **Subdoména `vtt` u WEDOSu** na složku `vtt` (hezká adresa bez `/vtt/`).
5. **Boj ve tmě — automatická ztráta iniciativy** (h1613 b10738; dnes jen postihy −5/−4/−2; kniha mluví o dvojici útočník–protivník, engine pořadí po dvojicích nezná).
6. **Lovcův Příkrov stínů** (h1003 b7357–b7359) — zóna, kde světla zhasnou a Lovec vidí „jako by tam tma nebyla“ (kniha nerozlišuje infravidění ostatních ani spolubojovníky).
7. **Deník nestvůry před bojem** (zadání uživatele 2026-10-20; **design navrhne uživatel** — čeká na něj).
8. **Zkušební hra** podle `docs/zkusebni-scenar-prvni-hra.md` (kroky 13–19 pokrývají nové věci) a měření na skutečném telefonu/tabletu, ověření obnovy zálohy (`docs/zalohy.md`) — jen uživatel.
   Můj názor: nové funkce se nakupily rychleji, než se ve skutečné hře zkoušely — zkušební večer má přednost před dalšími vrstvami.
9. Nastavení **maximální magenergie u postav** v ostré hře (jinak kouzlí zdarma; hra nově hlásí).

## 7) Kandidáti na další práci bez rozhodnutí uživatele (vždy s testy)

- Zamčené a tajné dveře (uživatel je bude potřebovat později) a **otevírání dveří hráčem** (zatím jen PJ).
- Knihovna map (duplikace, pořadí scén) — blok C (16).
- Ikony efektů a únavy přímo na tokenu (pruh životů na tokenu uživatel zatím **nechce**, chtěl raid panel — ten je hotový).
- Doladit neopravené nálezy revize (sekce 5C) a znovu `/code-review` na dalších oblastech (omezení přihlášení, chybový log, záloha, kontrola nasazení, fuzzery).
- Měření výpočtu vidění po buňkách na plné družině a mapě se stovkami zdí; případně cache polygonů.
- Fuzzing dalších míst (souběh `efekt_pridat`, mlha, `rozbalit` kontejner); přepnutí CSP z hlášení na vynucování (čeká na pár dní bez hlášení v logu).
- Zbytek v `docs/stav-projektu.md`, sekce „Další kroky bez rozhodnutí uživatele“ a „Čeká na rozhodnutí uživatele“.

## 8) Stručná mapa kódu pro oblasti, na kterých se naposled pracovalo

| Oblast | Soubory |
|---|---|
| Kouzlení (akce, štítek, automatické seslání, „ihned“) | `includes/vtt_kouzleni.php`, `includes/vtt_souboj_akce.php` (zápis akce + hook „ihned“), `hra/api/vyzva_uprava.php` (ihned u obrany), `includes/vtt_souboj_utok.php` (zásah kouzlícího, `dracak_vtt_obrana_obnov_efekty`), `includes/vtt_predmety.php` (`dracak_vtt_polozka_pouzij_ucinek`) |
| Klient souboje | `assets/js/souboj.js` (`kouzleniBlok`, `ihnedBlok`, `sIhned`, `volat`, `odeslatPlan`, log `kouzleni_stav`), `assets/css/mapa.css` (`.kouzleni-*`, `.ihned-*`) |
| Vidění / světlo / stíny | `includes/vtt_videni.php` (výpočet po buňkách, geometrie, diagnóza), `includes/vtt_polygon.php`, `assets/js/svetlo_stiny.js`, `assets/js/mapa.js` (`renderMlha`, `renderMlhaSvetlo`, `osvezitSvetloPj`, `renderSvetla`, `posunSvetloTokenu`) |
| Feature flagy | `includes/vtt_mapa_stav.php` (`dracak_vtt_tokeny_funkce`), `dracak_vtt_videni_funkce` ve `vtt_videni.php` |
| Migrace z administrace | `admin_migrace.php`, `includes/migrace.php`, `database/migrations/` |
| Testy | `tests/run.sh` (seznamy `E2E_VYCHOZI`, `UNIT_VYCHOZI`), `tests/README.md`, `tests/fixture/seed.sql` |

## 9) Návrh prvního promptu pro nový chat (vlož uživatel, nebo si ho nový chat vyžádá)

> Pokračujeme v projektu Dračák VTT (repo `alairik/dracak-vtt`). Přečti `CLAUDE.md`, `docs/stav-projektu.md` a `docs/zadani-pro-novy-chat.md`, zvedni lokální prostředí a pusť celou regresi.
> Pak mi jednou větou řekni, kde jsme, a navrhni první krok. Pravidla z `CLAUDE.md` platí (commit a push přímo na `main`, ruční SQL jako hotový text, otázky lidsky česky, žádné odhady mechaniky).
