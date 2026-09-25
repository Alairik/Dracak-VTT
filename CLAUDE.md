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
  starého.** Spouští se RUČNĚ v phpMyAdminu na produkci — WEDOS na tomhle
  tarifu vzdálený přístup k MySQL nenabízí, takže žádná automatizace přes
  GitHub Actions není možná (zkoušelo se, odstraněno, viz historie
  commitů). Jakmile je migrace jednou aplikovaná, její obsah je fixní
  navždy, oprava jde jen dalším novým souborem.
  `database/drd-db-schema-v1.sql`, `drd-db-full-v1.sql` a `0002_ucty_a_role.sql`
  (mimo `migrations/`) jsou jednorázový ruční import odlišný od
  číslovaných migrací.
- **`setup-admin.php` se nikdy nedeployuje** (viz `exclude` v `deploy.yml`)
  — má přístup k DB a zakládá admin účty, nepatří na veřejný web.
