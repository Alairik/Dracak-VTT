# Dracak-VTT — pravidla pro práci v repu

- **Nikdy nedávej hesla ani jiné přihlašovací údaje do repa.** `config.php`
  je v `.gitignore` a musí tam zůstat — reálné přístupy k databázi i FTP
  patří jen do GitHub Secrets (`FTP_SERVER`, `FTP_USERNAME`,
  `FTP_PASSWORD`) a lokálního `config.php` na hostingu, nikdy do commitu.
- **Nikdy nemaž `.ftp-deploy-sync-state.json`.** Je to stavový soubor
  `SamKirkland/FTP-Deploy-Action`, který si drží FTP server mezi nasazeními
  — bez něj by další deploy nahrál/smazal víc, než je potřeba.
- **Pushuj jen na `main`.** Deploy (`.github/workflows/deploy.yml`) se
  spouští při pushi na `main` a nahrává obsah repa přímo na FTP hosting —
  žádné vedlejší feature branche pro tenhle projekt.
- **Nová DB migrace = nový soubor v `database/migrations/`, nikdy úprava
  starého.** Push na `main` ho sám spustí na produkci (`scripts/run_migrations.php`,
  sleduje se v `schema_migrations`) — jakmile je jednou aplikovaný, jeho
  obsah je fixní navždy, oprava jde jen dalším novým souborem.
  `database/drd-db-schema-v1.sql`, `drd-db-full-v1.sql` a `0002_ucty_a_role.sql`
  (mimo `migrations/`) jsou jednorázový ruční import, runner se jich
  netýká.
- **`scripts/**` a `setup-admin.php` se nikdy nedeployují** (viz `exclude`
  v `deploy.yml`) — jsou to administrátorské nástroje s přístupem k DB,
  nepatří na veřejný web.
