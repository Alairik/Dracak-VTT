-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok:
--  K) Infravidění nestvůr z bestiáře (migrace 0116). Dosud se infravidění zadávalo ručně na každém tokenu, přestože
--     bestiář ho u řady nestvůr popisuje i s dosahem (zmije rohatá 10 sáhů, vážka jeskynní a nemrtví 30 sáhů…).
--     Po tomhle kroku token nestvůry infravidění dostane sám a stejně tak příznak „infravidění ho nerozezná“
--     (hlen zelený, mlok, nevidění, oživlé sochy). Přidává se jeden sloupec, do kterého se u nestvůry ukládá
--     hodnota schopnosti — deník nestvůry na něm postaví i další schopnosti a dovednosti.
--     Bez tohoto SQL se nic nezkazí, jen se infravidění dál zadává ručně jako dřív.
-- Nic se nemaže; jde spustit opakovaně a v libovolném stavu databáze.
--
-- JAK NA TO:
--   1. V administraci otevři Změny databáze a přečti si řádek „Databáze, kterou web používá: …“.
--   2. V phpMyAdminu klikni VLEVO na databázi s přesně tímhle jménem a teprve pak na záložku SQL.
--   3. Vlož celý blok a spusť. První výsledek (K0) musí ukázat to samé jméno databáze a u tabulky „ANO“.
--   4. Zkontroluj poslední výsledek (1) a pak na stránce Změny databáze klikni na „Spustit čekající změny“
--      — teprve ta do databáze doplní, které nestvůry infravidění mají (to už jsou běžné INSERTy, ty web účet umí).

-- K0) KONTROLA NA ZAČÁTEK: kde vlastně jsem.
SELECT
  IFNULL(DATABASE(), '(žádná databáze není vybraná!)') AS jsem_v_databazi,
  IF(COUNT(*) > 0, 'ANO — můžeš pokračovat', 'NE — JSI VE ŠPATNÉ DATABÁZI, přepni ji vlevo a spusť blok znovu') AS je_tu_tabulka_nestvura_schopnosti
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_schopnosti';

SET @je_tu = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_schopnosti');

-- K1) Hodnota schopnosti u konkrétní nestvůry (dosah infravidění v sázích; prázdné = kniha dosah neuvádí).
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_schopnosti ADD COLUMN hodnota VARCHAR(40) NULL COMMENT ''hodnota schopnosti u téhle nestvůry (např. dosah infravidění v sázích)''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_schopnosti' AND COLUMN_NAME = 'hodnota');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávaná hodnota: 1
SELECT COUNT(*) AS sloupec_hodnota_ocekavano_1 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_schopnosti' AND COLUMN_NAME = 'hodnota';
