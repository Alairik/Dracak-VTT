# Dracak-VTT — pravidla pro práci v repu

- **Nikdy nedávej hesla ani jiné přihlašovací údaje do repa.** `config.php`
  je v `.gitignore` a musí tam zůstat — reálné přístupy k databázi, FTP i
  `migrate_token` patří jen do GitHub Secrets (`FTP_SERVER`, `FTP_USERNAME`,
  `FTP_PASSWORD`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`,
  `MIGRATE_TOKEN`) a lokálního `config.php` na hostingu, nikdy do commitu.
- **Nikdy nemaž `.ftp-deploy-sync-state.json`.** Je to stavový soubor
  `SamKirkland/FTP-Deploy-Action`, který si drží FTP server mezi nasazeními
  — bez něj by další deploy nahrál/smazal víc, než je potřeba.
- **Pushuj jen na `main`.** Deploy (`.github/workflows/deploy.yml`) se
  spouští při pushi na `main` a nahrává obsah repa přímo na FTP hosting —
  žádné vedlejší feature branche pro tenhle projekt.
- **Nová DB migrace = nový soubor v `database/migrations/NNNN_popis.sql`,
  nikdy úprava starého.** Po pushi na `main` se automaticky spustí sama —
  poslední krok `deploy.yml` po úspěšném FTP Deploy zavolá
  `scripts/migrate.php` na produkci přes HTTPS (WEDOS nedovoluje GitHub
  Actions připojit se k MySQL přímo zvenčí, takže migrace běží PHP
  skriptem přímo na serveru, který se k DB připojuje lokálně —
  `migrate.php` si drží seznam už spuštěných souborů v tabulce
  `migrace_log`, takže je bezpečné volat ho po každém deployi).
  Jakmile je migrace jednou aplikovaná, její obsah je fixní navždy,
  oprava jde jen dalším novým souborem.
  `database/drd-db-schema-v1.sql`, `drd-db-full-v1.sql` a `0002_ucty_a_role.sql`
  (mimo `migrations/`) jsou jednorázový ruční import odlišný od
  číslovaných migrací — `migrate.php` se jich netýká.
- **Migrace smí obsahovat jen CREATE, ALTER ADD a INSERT. Nikdy DROP ani
  jinak destruktivní ALTER (DROP COLUMN, DROP DATABASE, TRUNCATE) bez
  výslovného schválení v konverzaci.** `migrate.php` to navíc vynucuje
  automaticky — soubor obsahující zakázaný příkaz odmítne spustit celý,
  i tu neškodnou část před ním.
- **MySQL/MariaDB DDL (CREATE TABLE, ALTER TABLE) není transakční** — dělá
  implicitní commit hned po příkazu. `migrate.php` obaluje soubor
  transakcí kvůli INSERT/UPDATE části, ale u migrace s víc ALTER příkazy,
  kde třetí selže, první dva zůstanou v DB natrvalo i po chybě — to je
  limit enginu, ne chyba skriptu. Drž migrace pokud možno malé a
  soustředěné na jednu věc.
- **`scripts/` smí na serveru obsahovat JEN `migrate.php`.** Nic jiného
  se tam nepřidává, aniž by se zároveň upravil `exclude` v `deploy.yml`
  — momentálně se `scripts/` nijak nevylučuje (spoléhá se na to, že tam
  nic jiného není), takže by se cokoliv dalšího nahrálo na veřejný web.
- **`setup-admin.php` se nikdy nedeployuje** (viz `exclude` v `deploy.yml`)
  — má přístup k DB a zakládá admin účty, nepatří na veřejný web.
- **`content/` drží fragmenty rulebooku pro `pravidla.php`** (`pravidla-hrac.html`,
  `pravidla-pj.html`, `pravidla-bestiar.html`, `toc-*.html`, `headings.json`) —
  má `.htaccess Require all denied` jako `includes/`, protože `pravidla.php`
  je čte interně přes `readfile()`/`file_get_contents()` podle role
  (`pj`/`bestiar` fragmenty jen pro `pj`/`admin`). Nikdy sem nepřidávej nic,
  co by šlo natvrdo poslat prohlížeči bez ohledu na roli.
