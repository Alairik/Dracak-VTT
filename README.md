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

1. V administraci WEDOSu založ MySQL databázi a uživatele k ní.
2. V phpMyAdminu naimportuj postupně: `database/drd-db-schema-v1.sql`
   (nebo rovnou `drd-db-full-v1.sql`, obsahuje schéma i data), pak
   `database/0002_ucty_a_role.sql`.
3. Zkopíruj `config.example.php` na `config.php` a doplň přihlašovací
   údaje k databázi (`config.php` je v `.gitignore`, nikdy ho necommituj).
4. Nahraj celý projekt přes FTP na hosting.
5. Otevři `setup-admin.php` v prohlížeči a založ první admin účet. Pak
   ten soubor z hostingu smaž (funguje jen jednou, dokud je tabulka
   `ucty` prázdná, ale stejně nemá smysl ho tam nechávat).
6. Přihlas se přes `index.php` → `editor.php`.

## Role

- **admin** — spravuje účty a role (`admin.php`), edituje vše
- **pj** — vidí a edituje vše včetně bestiáře a PJ pravidel
- **hrac** — v navigaci ani přímým URL nevidí bestiář/PJ poznámky ani
  systémové číselníky; smí zakládat/editovat jen typy záznamů, které mu
  admin povolí v `admin.php` (uložené v `ucet_opravneni`)
