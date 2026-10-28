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
| `tests/unit/` | `log_unit.php` (chybový log), `migrace_unit.php` (rozdělení SQL a zakázané příkazy; část proti DB zatím jen ručně), `zaloha_test.php` (záloha → obnova do prázdné DB, kontrolní součty) |
| `tests/e2e/` | Playwright a API sady: souboj a iniciativa (`souboj_api`, `ini_v2_api`, `vyzva_api`, `hex_*`), efekty (`efekty_*`), zbraně a vytasení (`zbrane_*`, `tasit_zdarma_api`), deník (`denik_v2`, `denik_dock`, `inv_ui`), tok pozvání a oprávnění (`e2e_hra`, `druzina_*`), chybový log a záloha (`log_ui`, `zaloha_ui`), review regrese (`review2_api`) |
| `tests/e2e/qa_zarizeni2.js`, `poll_load.js` | Měření, ne kontrola: screenshoty na telefonu a tabletu; požadavky a dotazy do DB na hráče za minutu (zapíná general log MariaDB). Spouštějí se ručně: `cd tests/e2e && node qa_zarizeni2.js` |
| `tests/fixture/` | `seed.sql` (reset a testovací data), `make-images.py` (podklady map) |

## Co testy nepokrývají

- Ostrý hosting (limity WEDOSu, `.htaccess`, FTP). Zavřené cesty a soubory na serveru ověřuje stránka **Kontrola nasazení** v administraci.
- Stránka **Změny databáze** pod účtem jen s právy DML a migrace s `ALTER` (ověřovalo se ručně na kopii databáze s účtem bez `ALTER`).
- Dotyková gesta na skutečném zařízení.

## Když přidáváš test

Nová sada patří do `tests/e2e/` (název `téma_api.js` / `téma_ui.js`), bere adresu z `process.env.BASE`, databázi z `process.env.DB`, nikdy nespoléhá na stav,
který jiná sada nechala (`run.sh` ho vždy resetuje), a po sobě uklízí tokeny a souboj. Přidej ji do `E2E_VYCHOZI` v `tests/run.sh`.
