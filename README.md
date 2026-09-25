# Dracak-VTT

Editor pravidel a databáze pro Dračí hlídku (DRH — domácí rozšíření nad
core pravidly DrD), s přihlášením a rolemi (admin / PJ / hráč). Vedle
toho poběží mapa (přenesená z [Torch](https://github.com/Alairik/Torch)).

Stack: čisté **PHP + MySQL/MariaDB**, žádný build krok, žádná externí
služba — nahraje se přes FTP na běžný hosting (WEDOS) a naimportuje přes
phpMyAdmin. (Dřívější verze zkoušela Supabase/Postgres, zahozeno — reálný
hosting je klasický sdílený PHP+MySQL, viz historie commitů.)

## Stav

- ✅ Auth + role (admin/pj/hrac) přes PHP session + `password_hash`
- ✅ Generický editor nad reálným schématem pravidel (`editor.php`) —
  navigace podle typu záznamu, rychlá šablona (klíčová pole nahoře,
  zbytek pod "Zobrazit všechna pole"), skrývání bestiáře a PJ poznámek
  před hráči (na úrovni PHP kódu, ne jen UI — ověřeno testem)
- ✅ Správa účtů (`admin.php`) — zakládání účtů, role, per-typ editační
  práva pro roli hráč
- ✅ Otestováno end-to-end na lokální MariaDB (import schématu + reálných
  dat, login, viditelnost podle role, uložení/smazání záznamu, vynucení
  práv i na úrovni POST requestu, ne jen skrytí tlačítka v UI)
- ⏳ M:N vazby (obory magie u kouzla, efekty u schopnosti, zranitelnosti
  příšer...) se zatím needitují přes UI — jen skalární pole tabulky.
  Do doby, než přibude UI pro multi-select, se řeší přes phpMyAdmin.
- ⏳ Mapa (zatím jen odkaz na Torch, `mapa.html`)
- ⏳ Tabulka `velikosti` (má PK `kod`, ne `id`) není v editoru zatím
  zahrnutá — uprav přes phpMyAdmin, dokud generický editor nepočítá i
  s jiným primárním klíčem než `id`

## Data

`database/drd-db-full-v1.sql` — reálný obsah pravidel: 41 povolání,
31 ras, 680 kouzel, 208 příšer, 342 schopností/dovedností, 219 vybavení,
88 lektvarů a další. `docs/kontrolni-seznam-neuplnych-mist.md` eviduje
283 míst v podkladu, která jsou poškozená/neúplná/nejasná — část bude
časem potřeba doplnit z fotek knihy.

## Spuštění na WEDOSu

1. V administraci WEDOSu založ MySQL databázi a uživatele k ní. (WEDOS
   na tomhle tarifu nenabízí vzdálený přístup k MySQL zvenčí — GitHub
   Actions se k databázi nikdy nedostane, migrace se proto pouští jen
   ručně přes phpMyAdmin, viz níže.)
2. V phpMyAdminu naimportuj postupně: `database/drd-db-schema-v1.sql`
   (nebo rovnou `drd-db-full-v1.sql`, obsahuje schéma i data), pak
   všechny soubory z `database/migrations/` v číselném pořadí.
3. V GitHub repu (Settings → Secrets and variables → Actions) nastav
   `FTP_SERVER`/`FTP_USERNAME`/`FTP_PASSWORD` a `DB_HOST`/`DB_NAME`/
   `DB_USER`/`DB_PASS`. Push na `main` pak sám nahraje soubory na FTP a
   vygeneruje `config.php` na serveru.
4. Otevři `setup-admin.php` (nahraj ho na hosting ručně přes FTP —
   `setup-admin.php` se z bezpečnostních důvodů nedeployuje automaticky)
   a založ první admin účet. Pak ho zase smaž.
5. Přihlas se přes `index.php` → `editor.php`.

## Přidání nové DB migrace

Nový soubor `database/migrations/NNNN_popis.sql` (číslo o 1 vyšší než
poslední) — a pak ho ručně spustit v phpMyAdminu na produkci. Automatické
spouštění migrací se zkoušelo (`scripts/run_migrations.php`), ale WEDOS
na tomhle tarifu vzdálený přístup k MySQL nenabízí vůbec, takže se
GitHub Actions k databázi nikdy nepřipojí — odstraněno, ať deploy
zbytečně nečeká na timeout.

## Role

- **admin** — spravuje účty a role (`admin.php`), edituje vše
- **pj** — vidí a edituje vše včetně bestiáře a PJ pravidel
- **hrac** — v navigaci ani přímým URL nevidí bestiář/PJ poznámky ani
  systémové číselníky; smí zakládat/editovat jen typy záznamů, které mu
  admin povolí v `admin.php` (uložené v `ucet_opravneni`)
