# Dracak-VTT — pravidla pro práci v repu

- **Nikdy nedávej hesla ani jiné přihlašovací údaje do repa.** `config.php`
  je v `.gitignore` a musí tam zůstat — reálné přístupy k databázi a FTP
  patří jen do GitHub Secrets (`FTP_SERVER`, `FTP_USERNAME`,
  `FTP_PASSWORD`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) a lokálního
  `config.php` na hostingu, nikdy do commitu. (Secret `MIGRATE_TOKEN` už
  se nepoužívá — migrace se pouští z administrace, viz níž; jde smazat.)
- **Nikdy nemaž `.ftp-deploy-sync-state.json`.** Je to stavový soubor
  `SamKirkland/FTP-Deploy-Action`, který si drží FTP server mezi nasazeními
  — bez něj by další deploy nahrál/smazal víc, než je potřeba.
- **Pushuj jen na `main`.** Deploy (`.github/workflows/deploy.yml`) se
  spouští při pushi na `main` a nahrává obsah repa přímo na FTP hosting —
  žádné vedlejší feature branche pro tenhle projekt.
  **Mergni a pushni sám, automaticky, bez ptaní — je to trvalé povolení
  od uživatele.** Pokud session běží na pracovní větvi (např. `ccr-…`
  přidělené prostředím), po dokončení práce ji sám mergni do `main`
  (fast-forward, bez PR) a pushni; na to se neptej, nečekej na
  potvrzení a nenech hotovou práci ležet na vedlejší větvi. Jediné, co
  uživateli po pushi řekni, je co z toho vyžaduje jeho ruku (typicky
  migrace s CREATE/ALTER, viz níž — ty web účet nespustí a stránka
  „Změny databáze“ se na nich zastaví, takže následující migrace čekají,
  dokud je neprovede; a že po nasazení s novou migrací má v administraci
  kliknout na „Spustit čekající změny“).
- **Nová DB migrace = nový soubor v `database/migrations/NNNN_popis.sql`,
  nikdy úprava starého.** Po pushi na `main` se nahraje na server, ale
  **nespustí se sama**: admin (uživatel) klikne v administraci na stránce
  „Změny databáze“ (`admin_migrace.php`, logika `includes/migrace.php`) na
  „Spustit čekající změny“ (rozhodnutí uživatele 2026-10-27, varianta A).
  Důvod: hosting od 22. 10. 2026 odmítá HTTP volání z GitHub Actions
  (404), takže dřívější automatický krok `scripts/migrate.php` přes HTTPS
  s tokenem nešel a byl zrušen; WEDOS navíc nedovoluje připojit se k MySQL
  zvenčí. Migrace běží PHP přímo na serveru, k DB se připojuje lokálně;
  seznam už spuštěných souborů drží tabulka `migrace_log`, takže je
  bezpečné stránku spustit kdykoli. **Po pushi s novou migrací to
  uživateli řekni** (jedna věta: „po nasazení klikni v administraci na
  Změny databáze“) — deploy už to nehlídá.
  Jakmile je migrace jednou aplikovaná, její obsah je fixní navždy,
  oprava jde jen dalším novým souborem.
  `database/drd-db-schema-v1.sql`, `drd-db-full-v1.sql` a `0002_ucty_a_role.sql`
  (mimo `migrations/`) jsou jednorázový ruční import odlišný od
  číslovaných migrací — `admin_migrace.php` se jich netýká.
- **Migrace smí obsahovat jen CREATE, ALTER ADD a INSERT. Nikdy DROP ani
  jinak destruktivní ALTER (DROP COLUMN, DROP DATABASE, TRUNCATE) bez
  výslovného schválení v konverzaci.** `includes/migrace.php` to navíc vynucuje
  automaticky — soubor obsahující zakázaný příkaz odmítne spustit celý,
  i tu neškodnou část před ním.
- **MySQL/MariaDB DDL (CREATE TABLE, ALTER TABLE) není transakční** — dělá
  implicitní commit hned po příkazu. `includes/migrace.php` obaluje soubor
  transakcí kvůli INSERT/UPDATE části, ale u migrace s víc ALTER příkazy,
  kde třetí selže, první dva zůstanou v DB natrvalo i po chybě — to je
  limit enginu, ne chyba skriptu. Drž migrace pokud možno malé a
  soustředěné na jednu věc.
- **DB účet, kterým web a stránka Změny databáze běží (`w...`), má na Wedosu natvrdo jen
  DML (SELECT/INSERT/UPDATE/DELETE) — nikdy CREATE ani ALTER.** To není
  nastavitelné, GRANT selže i pod admin účtem (`a...`), Wedos to
  zákazníkovi nedovolí vůbec. Proto:
  - Tabulka `migrace_log` musí zůstat založená ručně přes phpMyAdmin
    pod **admin** účtem. Pokud by někdy zmizela (nová DB, obnova ze
    zálohy…), stránka Změny databáze ji nenajde a ukáže hotový příkaz k ručnímu
    spuštění — sama si ji založit nemůže.
  - Jakákoli migrace obsahující CREATE/ALTER musí být po pushi **ručně**
    spuštěná přes phpMyAdmin (admin účet) a zapsaná do `migrace_log` —
    stránka Změny databáze pod web účtem takový příkaz nikdy neprovede. Migrace
    obsahující jen INSERT/UPDATE/DELETE proběhnou automaticky v pořádku.
  - Stránka Změny databáze pozná ALTER ADD (sloupec / index / cizí klíč)
    a CREATE TABLE IF NOT EXISTS, které už ručně proběhly, přeskočí je a
    zapíše do `migrace_log` — řeší to jen „už existuje“, ne založení nového.
    Jiné DDL spadne na 1142 a stránka řekne, že potřebuje ruční SQL.
- **Ruční SQL (DDL) vždy pošli jako hotový text ke zkopírování.**
  Uživatel chce jen Ctrl+C / Ctrl+V do phpMyAdminu — nikdy "spusť
  soubor X", "otevři migraci Y" ani "uprav si to". Uživatel to už
  opakoval ve víc konverzacích, takže se na to neptá a nečeká, že
  si to vyžádá znovu. Vždy:
  1. Udržuj **`docs/sql-k-rucnimu-spusteni.sql`** — jeden blok se
     VŠEMI dosud neprovedenými příkazy, co web účet neumí
     (CREATE, ALTER, DROP…), v pořadí, s krátkými komentáři.
  2. V odpovědi uživateli vlož jeho **celý obsah** do code blocku
     (SQL, bez vynechávek a "…"), i když jsi ho posílal už dřív a i
     když se nic nezměnilo — dokud nepotvrdí, že ho provedl.
  3. **Blok musí jít spustit opakovaně a v libovolném stavu DB** —
     phpMyAdmin se na první chybě zastaví a zbytek neprovede, takže
     holé `ALTER TABLE ... ADD COLUMN` spadne na "Duplicate column",
     jakmile je část už hotová (stalo se). Každý ALTER proto obal
     kontrolou přes `information_schema` + `PREPARE`/`EXECUTE` (vzor v
     `docs/sql-k-rucnimu-spusteni.sql`), `CREATE TABLE IF NOT EXISTS`,
     `INSERT IGNORE`; na konec dej kontrolní SELECT s očekávanými čísly.
  4. Než ho pošleš, ověř na lokální MariaDB (postup v
     `docs/stav-projektu.md`), že blok projde na kopii produkčního
     stavu (i po částečném provedení a po druhém spuštění) a že po něm
     stránka Změny databáze (`admin_migrace.php`) pod DML-only účtem doběhne
     bez chyby.
  5. Po potvrzení, že ho provedl, soubor přepiš na aktuální stav
     (prázdný, nebo jen nové příkazy od té doby).
- **Otázky uživateli piš lidsky, česky, bez databázové a programátorské
  mluvy.** Uživatel je account manager, ne vývojář: nerozumí slovům jako
  DROP, index, tabulka, sloupec, migrace, ENUM, "varianta A/B" podle
  technického rozdílu. Technická rozhodnutí (jak něco postavit) dělej
  sám, nejbezpečnější cestou, a řekni jen výsledek. Ptej se jen na věci,
  které jsou opravdu jeho: co má hra dělat z pohledu hráče/PJ, nebo jak
  to je v knize. Každá otázka: jedna věta co se rozhoduje, příklad z hry,
  a tvoje doporučení.
- **Složka `scripts/` v repu není** (starý `scripts/migrate.php` se
  tokenem a voláním z GitHubu zrušil 2026-10-27; deploy ho ze serveru
  smaže sám). Nepřidávej ji zpět s ničím, co jde zavolat přes HTTP bez
  přihlášení — `deploy.yml` ji nevylučuje, nahrálo by se to na veřejný web.
- **Deploy (`deploy.yml`) jen nahraje soubory přes FTP.** Kroky, které
  volaly web z GitHubu (migrace, smoke test, kontrola zabezpečení), jsou
  pryč — hosting je z GitHubu odmítá. Místo nich: deploy před nahráním
  vyrobí `deploy-manifest.json` (`.github/scripts/manifest.php`, seznam
  nahrávaných souborů se SHA-256; vzory vyloučení čte z `exclude` v
  `deploy.yml`, takže nové vyloučení stačí přidat tam) a stránka
  „Kontrola nasazení“ (`admin_nasazeni.php`, `includes/nasazeni.php`) na
  serveru porovná manifest s diskem — chybějící / jiné / navíc ležící
  soubory, spustitelné soubory v `uploads/` — a po kliknutí si zkusí
  vyžádat vlastní zavřené cesty a bezpečnostní hlavičky. Po nasazení se
  proto vyplatí zkontrolovat tuhle stránku; CSRF a ostatní se dál kontrolují
  jen ručně. `deploy-manifest.json` je v `.htaccess` zavřený zvenčí.
  FTP-Deploy nahrává jen změněné soubory, takže chybějící soubor další
  deploy sám nedoplní — oprava = drobná změna v tom souboru, nebo ruční FTP.
- **`setup-admin.php` se nikdy nedeployuje** (viz `exclude` v `deploy.yml`)
  — má přístup k DB a zakládá admin účty, nepatří na veřejný web.
- **`content/` drží fragmenty rulebooku pro `pravidla.php`** (`pravidla-hrac.html`,
  `pravidla-pj.html`, `pravidla-bestiar.html`, `toc-*.html`, `headings.json`) —
  má `.htaccess Require all denied` jako `includes/`, protože `pravidla.php`
  je čte interně přes `readfile()`/`file_get_contents()` podle role
  (`pj`/`bestiar` fragmenty jen pro `pj`/`admin`). Nikdy sem nepřidávej nic,
  co by šlo natvrdo poslat prohlížeči bez ohledu na roli.
- **Proč DB vůbec existuje, mimo pravidel:** VTT nad ní poběží a bude z ní
  tahat reálná data přímo do hry — ne jen zobrazovat text k přečtení.
  Z toho plyne, co znamenají strukturovaná pole jako `kostky`
  (pocet_kostek/typ_kostky/pevny_bonus) a vazby na `efekty`
  (`kouzlo_efekty`, `lektvar_efekty`, `schopnost_efekty`): nejsou to
  hezčí popisky, ale mechanický hák, který má engine za hry **skutečně
  spustit** — hodit kostkou, strhnout životy, nasadit debuff s trváním
  — místo aby si PJ musel přečíst `popis` a vyhodnotit to ručně. Platí
  stejně pro kouzla, lektvary i zvláštní schopnosti/dovednosti (ne jen
  kouzla). Efekt/kostky proto patří jen tam, kde má záznam jasný
  mechanický dopad (poškození, léčení, standardní stavový efekt typu
  Spánek/Ochromení, který `efekty` už eviduje jako obecný,
  znovupoužitelný typ) — ne u čistě naratívních/flexibilních věcí bez
  jasné mechaniky (typ "získej dočasně nějakou schopnost zvířete").
  Než se cokoliv z tohohle plošně doplňuje na desítky/stovky záznamů,
  radši se zeptat, protože špatně navržený efekt je hůř opravitelný
  než žádný.
- **Když něco nevíš, koukni do pravidel — repo není zdroj pravdy o hře,
  pravidla jsou.** Kód, DB (seed i hodnoty z migrací), komentáře,
  názvy sloupců a `docs/*.md` jsou jen pokus někoho o přepis pravidel a
  už se několikrát ukázaly špatně (pohyblivost ras v 0007, nosnost v
  0007, `stupne_unavy` v 0008 — všechno bez zdroje). Proto: (1) existující
  hodnota v DB/kódu NENÍ důkaz, že je správně — ověř ji proti textu
  pravidel (`content/pravidla-*.html`, `toc-*.html`), než na ní něco
  postavíš; (2) když si nejsi jistý, nejdřív hledej v pravidlech, ne v
  kódu; (3) když informace v `content/` opravdu není (chybějící tabulka
  v přepisu), řekni to a požádej uživatele o foto/doplnění knihy —
  nedomýšlej a nehádej; (4) co v DB nemá oporu v pravidlech a nic to
  nepoužívá, nemusí tam být.
- **"Doplň to do pravidel" znamená do `/pravidla.php` — a když na těch
  datech VTT staví, i do DB.** Dvě vrstvy, obě:
  1. **Text knihy** = `content/pravidla-hrac.html` / `-pj.html` /
     `-bestiar.html`; to je to, co `pravidla.php` čtenáři posílá (tabulky
     jako `<div class="table-wrap"><table>` podle okolních). **Každý nový
     blok (`p`, `tr`, `li`, `h*` s `data-blk="1"`) musí mít unikátní
     `id="bNNNNN"`** — řady: hrac 93xxx, pj 94xxx, bestiar 95xxx (další
     volné = nejvyšší existující + 1) — bez id nejde editovat tužkou ani
     přepsat přes `pravidla_texty`. Když přepisuješ existující blok, drž
     jeho id.
  2. **DB** = strukturovaná data, ze kterých počítá engine (číselníky,
     tabulky; nová migrace podle pravidel výš, s citací h/b-id v hlavičce).
     `pravidla.php` sám z DB bere jen: živé karty nadpisů napojených
     přes `edit-links.json`/`content-links.json` a přepisy v tabulce
     `pravidla_texty` (ty mají přednost před textem v souboru!).
     Text knihy se tedy NEdoplňuje jen do DB a naopak.
- **Rozpor mezi zdroji: přednost má homebrew, překlepy se nechají.**
  Když se základní pravidla (PPZ/PPP/PPE) a homebrew (`data-hb`, "Homebrew ·
  zdroj: …") liší, platí **homebrew** — je upravený vůči lepší realitě
  (rozhodnutí uživatele 2026-10-14). Obě hodnoty ulož, kde to jde (např.
  `povozy.vaha_*` a `vaha_hb_*`), a výchozí je homebrew. **Zjevné překlepy
  ve zdroji neopravuj sám** — nech podle knihy (při rozporu podle homebrew) a
  zapiš je do `docs/kontrolni-seznam-neuplnych-mist.md`, sekce "Na kontrolu při
  proofreadingu"; na konci se udělá společná korektura a opraví se najednou.
- **Nejistota v herní mechanice (výpočty, vzorce, co přesně dělá
  iniciativa/finta/atribut) se neřeší odhadem.** Skutečný text pravidel
  je v `content/pravidla-hrac.html`, `pravidla-pj.html`,
  `pravidla-bestiar.html` — tam je zdroj pravdy, ne intuice podle jiných
  systémů (D&D apod.). Než se cokoliv počítá nebo staví, nejdřív si
  najít odpovídající pasáž v těchhle souborech. Nejde o jednorázovou
  volbu "odhad, nebo přesně" — odhad není rovnocenná možnost k nabídnutí,
  je to jediná cesta, co nikdy nejde vzít.
  - **"Nenašel jsem to" / "to tu není" se netvrdí po jednom hledání nebo
    jedné naražené větě.** Narazit v textu na "viz kapitola X — mimo
    rozsah tohoto dokumentu" znamená, že mimo rozsah je TA KONKRÉTNÍ
    PASÁŽ/ÚRYVEK, ne nutně že kapitola X v souboru vůbec chybí — ověř to
    přes skutečný obsah (`content/toc-hrac.html`, `toc-pj.html`,
    `toc-bestiar.html` mají kompletní seznam nadpisů/sekcí) dřív, než se
    řekne "tahle mechanika/kapitola tu není". Platí to i pro propojené
    kapitoly — když se zdá, že jedna tabulka chybí, zkontroluj, jestli
    na ni sousední/navazující sekce (jiné zvíře, jiná aktivita, jiné
    povolání) neodkazuje křížově stejnými jednotkami ("v bodech pohybu,
    viz výše" apod.) — může to být přesně ta chybějící hodnota, jen pod
    jinou nadpisem.
- **`docs/stav-projektu.md` je živý přehled "co je hotové, co se řeší,
  jaký je další krok" — udržuj ho aktuální, vždy, ne až někdy později.**
  Při každé netriviální práci (nová funkce, oprava dat, architektonické
  rozhodnutí, zjištění z pravidel, co mění plán) zapiš update do tohohle
  souboru ve STEJNÉM kroku/commitu, ne jako samostatný "uklízecí" úkol
  nazítří — jinak zastará přesně jako `docs/vtt-datovy-model-navrh-v1.md`
  a `docs/kontrolni-seznam-neuplnych-mist.md`, které už drift měly a
  musely se dohánět zpětně. Soubor existuje proto, aby se v projektu
  dokázal rychle zorientovat i model/session bez historie konverzace —
  jestli po tvé práci neodpovídá realitě, úkol není hotový.
- **"Homebrew" v komentářích `drd-db-full-v1.sql` (Stopař stínů, Panoš,
  Střelec, Divoch, Šaman a další rozšířená povolání/rasy) neznamená
  "nestandardní doplněk, který stojí bokem."** Je to jen odkaz na to, že
  daný obsah je z „Pravidel pro pokročilé" místo základní příručky —
  pořád má konkrétní citaci (h935, h1076…) a je to plnohodnotná součást
  pravidel. Nikdy to neber jako důvod tohle povolání/rasu/mechaniku
  vynechat, odložit na později nebo řešit jinak než core obsah — jediný
  rozdíl je, kde v `content/pravidla-*.html` to hledat.
