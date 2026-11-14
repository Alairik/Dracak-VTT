-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Nic se nemaže; blok jde spustit opakovaně a v libovolném stavu databáze.
--
-- BLOK N) Stínové životy (migrace 0121). Rána beze zbraně (pěst, kop) a úder naplocho působí podle pravidel jen
--   „stínové“ zranění: tvora NEZABÍJÍ (fyzické životy nesnižuje) a po skončení souboje se samo zahojí, ale do
--   vyřazení (omráčení) se počítá spolu s obyčejným zraněním (hrac Rvačka h1654 b10988). Aby to hra mohla vést,
--   dostane postava i instance nestvůry jeden nový sloupec `stin_hp` = kolik stínového zranění má tvor právě teď
--   natrpěno (0 = žádné). Výchozí hodnota je 0, takže se po přidání sloupce nic nezmění, jen se od té chvíle
--   stínové zranění vede zvlášť místo aby se odečítalo z běžných životů. Bez tohohle SQL hra funguje dál jako
--   dřív (rána pěstí se odečte jako běžné zranění a jen varuje PJ), stínové životy se prostě ještě nevedou.
--
-- JAK NA TO:
--   1. V administraci otevři Změny databáze a přečti si řádek „Databáze, kterou web používá: …“.
--   2. V phpMyAdminu klikni VLEVO na databázi s přesně tímhle jménem a teprve pak na záložku SQL.
--   3. Vlož celý tenhle soubor a spusť. První výsledek (N0) musí ukázat to samé jméno databáze a „ANO“.
--   4. Poslední výsledek musí být 2 (oba sloupce `stin_hp` existují).
--   5. Nakonec v administraci na stránce Změny databáze klikni na „Spustit čekající změny“ — migrace 0121 se
--      tím zapíše jako hotová (sloupce už existují, takže ji stránka jen přeskočí a odškrtne).

-- N0) KONTROLA NA ZAČÁTEK: kde vlastně jsem.
SELECT
  IFNULL(DATABASE(), '(žádná databáze není vybraná!)') AS jsem_v_databazi,
  IF(COUNT(*) = 2, 'ANO — můžeš pokračovat', 'NE — JSI VE ŠPATNÉ DATABÁZI, přepni ji vlevo a spusť blok znovu') AS jsou_tu_tabulky_zivotu
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('postavy', 'nestvura_instance');

-- N1) Stínové životy u postavy.
SET @je_tu = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postavy');
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE postavy ADD COLUMN stin_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_hp')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postavy' AND COLUMN_NAME = 'stin_hp');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- N2) Stínové životy u instance nestvůry.
SET @je_tu2 = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance');
SET @s = (SELECT IF(@je_tu2 = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_instance ADD COLUMN stin_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_hp')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'stin_hp');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- N3) KONTROLA NA KONEC: musí vyjít 2 (sloupec stin_hp je na postavy i na nestvura_instance).
SELECT COUNT(*) AS stin_hp_sloupcu_ma_byt_2
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('postavy', 'nestvura_instance') AND COLUMN_NAME = 'stin_hp';
