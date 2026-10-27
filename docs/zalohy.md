# Zálohy a obnova

Co se z projektu obnovit **nedá**, je obsah databáze (postavy, výbava, světy, mapy, tokeny, průběh her, účty) a nahrané obrázky (`uploads/`).
Soubory aplikace jsou v GitHubu — nasazení je vždy nahraje znovu.

## Jak zálohovat

1. **Databáze:** v administraci stránka **Záloha databáze** (`admin_zaloha.php`) → *Stáhnout zálohu (.sql)*. Dělat před každou hrou a po ní, nebo aspoň jednou týdně.
   Soubor je platný, jen když na konci obsahuje řádek `-- Konec zálohy: N tabulek, M řádků.` (bez něj se stahování přerušilo).
   Soubor obsahuje hashe hesel účtů — nesdílet.
2. **Obrázky:** složku `uploads/` stáhnout přes FTP (ručně, jednou za čas; mění se jen při nahrávání map a portrétů).
3. Zálohy ukládat mimo hosting (vlastní disk, cloud) a nechat si aspoň tři poslední.

## Jak obnovit

1. Založit **prázdnou** databázi (v administraci hostingu), nebo stávající vyprázdnit (v phpMyAdminu: *Zaškrtnout vše → Smazat/Drop* — to je jediné místo, kde se maže, a dělá ho člověk vědomě).
2. phpMyAdmin pod **admin účtem** (ne webovým `w…`, ten smí jen číst a měnit řádky): *Import* → soubor se zálohou. Záloha obsahuje `CREATE TABLE IF NOT EXISTS` a `INSERT`, takže web účet by ji neprovedl.
3. V `config.php` na hostingu nastavit údaje obnovené databáze (jméno, uživatel, heslo), pokud se databáze jmenuje jinak.
4. Obrázky nahrát zpět do `uploads/` přes FTP.
5. V administraci zkontrolovat **Změny databáze** (má být „Všechno je aplikované“) a **Kontrola nasazení**.

## Co je ověřené a co ne

- Ověřeno lokálně (2026-10-28): dump celé databáze (88 tabulek, 39 367 řádků, 7 MB, 0,5 s, 6 MB paměti) se vložil do prázdné databáze, počty řádků i `CHECKSUM TABLE`
  sedí u všech tabulek, binární data (bitmapa mlhy) a diakritika přežily; druhý import do neprázdné databáze skončí chybou duplicity a nic nepřepíše;
  dump se dá vyrobit i účtem jen s právy SELECT/INSERT/UPDATE/DELETE (jako web na Wedosu).
- **Neověřeno na ostrém hostingu:** limit velikosti souboru, který phpMyAdmin u Wedosu při importu přijme (kdyby záloha jednou přerostla, dá se rozdělit nebo importovat
  přes příkazovou řádku), a časový limit stahování. Dnešní velikosti (jednotky MB) jsou hluboko pod běžnými limity. Zkušební obnova na Wedosu do pomocné prázdné
  databáze se vyplatí jednou udělat před první ostrou hrou.
- Hosting může mít vlastní zálohy databáze — jak často a na jak dlouho se drží, jsem neověřoval; záloha z administrace je nezávislá pojistka, ne jejich náhrada.
