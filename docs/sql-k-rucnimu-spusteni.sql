-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Nic se nemaže; blok jde spustit opakovaně a v libovolném stavu databáze.
--
-- (Blok N — stínové životy, migrace 0121 — uživatel nasadil 2026-11-17.)
--
-- BLOK O) Kop (migrace 0122). Kop z Tabulky rvačky (hrac h1654 b10987) je volitelný útok nohou: vyšší útočné číslo
--   než pěst (síla nohy A 3, B 5, C 7 + bonus za sílu) a útočnost +3, ale jde použít jen JEDNOU ZA KOLO a v nejbližší
--   další akci (útok nebo obrana) má útočník postih −4 (bojovník, šermíř, lupič a chodec jen −2). Aby to hra mohla
--   hlídat, dostane řádek účastníka souboje (kolo_iniciativa) dva nové sloupce: kop_kolo (ve kterém kole tvor
--   naposledy kopl) a kop_postih (kolik srazit z nejbližšího útoku/obrany). Oba jsou výchozí 0, takže se po přidání
--   nic nezmění. Bez tohohle SQL se kop prostě nenabízí a souboj jede jako dřív. Kop je útok beze zbraně, takže
--   působí jen stínové zranění — proto má smysl až po bloku N (stínové životy).
--
-- JAK NA TO:
--   1. V administraci otevři Změny databáze a přečti si řádek „Databáze, kterou web používá: …“.
--   2. V phpMyAdminu klikni VLEVO na databázi s přesně tímhle jménem a teprve pak na záložku SQL.
--   3. Vlož celý tenhle soubor a spusť. První výsledek (O0) musí ukázat to samé jméno databáze a „ANO“.
--   4. Poslední výsledek musí být 2 (oba sloupce kopu existují).
--   5. Nakonec v administraci na stránce Změny databáze klikni na „Spustit čekající změny“ — migrace 0122 se
--      tím zapíše jako hotová (sloupce už existují, takže ji stránka jen přeskočí a odškrtne).

-- O0) KONTROLA NA ZAČÁTEK: kde vlastně jsem.
SELECT
  IFNULL(DATABASE(), '(žádná databáze není vybraná!)') AS jsem_v_databazi,
  IF(COUNT(*) = 1, 'ANO — můžeš pokračovat', 'NE — JSI VE ŠPATNÉ DATABÁZI, přepni ji vlevo a spusť blok znovu') AS je_tu_tabulka_souboje
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa';

SET @je_tu = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa');

-- O1) Ve kterém kole tvor naposledy kopl (hlídá „jen jednou za kolo“).
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE kolo_iniciativa ADD COLUMN kop_kolo SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER akce_hotovo')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa' AND COLUMN_NAME = 'kop_kolo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- O2) Čekající postih za minulý kop (srazí se z nejbližšího útoku/obrany).
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE kolo_iniciativa ADD COLUMN kop_postih TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER kop_kolo')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa' AND COLUMN_NAME = 'kop_postih');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- O3) KONTROLA NA KONEC: musí vyjít 2 (oba sloupce kopu na kolo_iniciativa).
SELECT COUNT(*) AS kop_sloupcu_ma_byt_2
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa' AND COLUMN_NAME IN ('kop_kolo', 'kop_postih');
