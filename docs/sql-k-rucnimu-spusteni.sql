-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok:
--  J) NPC je bytost světa, ne mapy (migrace 0115). Po tomhle kroku NPC žije ve světě a mapa je jen místo, kde právě stojí:
--     v liště dole uprostřed si vyrobíš třeba 10 goblinů do zásoby a pak je tažením kladeš na mapu. Zaškrtnutím „každý
--     jedinečný“ dostane každý kus vlastní čísla pro boj (hostinský se jménem), bez zaškrtnutí berou všichni čísla svého
--     druhu jako dosud. NPC se dá navázat na místo, nebo nechat bez místa jako zásobu pro mapy, které ještě nevznikly.
--     Bez tohoto SQL se lišta se zásobou nezobrazí a tokeny se přidávají jako dřív.
-- Nic se nemaže; jde spustit opakovaně a v libovolném stavu databáze.
--
-- 2026-11-09: uživatel blok spustil, ale na stránce Změny databáze → „Co aplikace v databázi vidí“ byly u všech čtyř sloupců ✗.
-- Blok se tedy provedl JINDE, než kam se dívá web (nejspíš jiná databáze vybraná v phpMyAdminu — stalo se už dvakrát předtím).
-- Proto je teď na začátku kontrola J0, která rovnou napíše, v jaké databázi jsi a jestli v ní ta tabulka vůbec je.
--
-- JAK NA TO:
--   1. V administraci otevři Změny databáze a přečti si řádek „Databáze, kterou web používá: …“.
--   2. V phpMyAdminu klikni VLEVO na databázi s přesně tímhle jménem (ne na jinou, ne na žádnou) a teprve pak na záložku SQL.
--   3. Vlož celý blok a spusť. První výsledek (J0) musí ukázat to samé jméno databáze a u tabulky „ANO“.
--      Když ukáže jiné jméno nebo „NE — JSI VE ŠPATNÉ DATABÁZI“, nic dalšího se neprovede a stačí přepnout databázi a spustit znovu.
--   4. Nakonec zkontroluj poslední tři výsledky (4 / YES / 2) a pak na stránce Změny databáze klikni na „Spustit čekající změny“.
--
-- Sem se zapisují jen příkazy, které stránka Změny databáze pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- J0) KONTROLA NA ZAČÁTEK: kde vlastně jsem. Tohle si přečti dřív, než se podíváš na zbytek.
SELECT
  IFNULL(DATABASE(), '(žádná databáze není vybraná!)') AS jsem_v_databazi,
  IF(COUNT(*) > 0, 'ANO — můžeš pokračovat', 'NE — JSI VE ŠPATNÉ DATABÁZI, přepni ji vlevo a spusť blok znovu') AS je_tu_tabulka_nestvura_instance
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance';

-- Když tabulka v téhle databázi není, všechny následující kroky se samy přeskočí (nic nezmění a nic nespadne).
SET @je_tu = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance');

-- J1) Ke kterému světu NPC patří.
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_instance ADD COLUMN svet_id INT UNSIGNED NULL COMMENT ''svět, kterému NPC patří''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'svet_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J2) Mapa smí být prázdná = NPC je v zásobě a nestojí nikde.
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) = 0, 'DO 0',
  'ALTER TABLE nestvura_instance MODIFY COLUMN mapa_id INT UNSIGNED NULL COMMENT ''mapa, na které NPC stojí; prázdné = v zásobě''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'mapa_id' AND IS_NULLABLE = 'NO');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J3) Jedinečné NPC (vlastní čísla) × běžný kus (čísla druhu).
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_instance ADD COLUMN jedinecny TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = jedinečné NPC s vlastními čísly''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'jedinecny');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J4) Vlastní čísla pro boj jedinečného NPC.
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_instance ADD COLUMN data MEDIUMTEXT NULL COMMENT ''vlastní čísla pro boj jedinečného NPC (JSON)''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'data');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J5) Místo, kam NPC patří (hostinský v tomhle městě); prázdné = bez místa, zásoba pro budoucí mapy.
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_instance ADD COLUMN lokace_mapa_id INT UNSIGNED NULL COMMENT ''místo, kam NPC patří; prázdné = bez místa''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'lokace_mapa_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J6) Indexy pro výpis NPC ve světě a v lokaci.
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_instance ADD INDEX idx_ni_svet (svet_id, nestvura_id)')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND INDEX_NAME = 'idx_ni_svet');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_instance ADD INDEX idx_ni_lokace (lokace_mapa_id)')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND INDEX_NAME = 'idx_ni_lokace');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J7) Dosavadní NPC patří do světa své mapy a na to místo, kde stojí (bezpečné opakovaně — doplní jen prázdné).
SET @s = IF(@je_tu = 0, 'DO 0',
  'UPDATE nestvura_instance ni JOIN mapy m ON m.id = ni.mapa_id SET ni.svet_id = m.svet_id WHERE ni.svet_id IS NULL');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF(@je_tu = 0, 'DO 0',
  'UPDATE nestvura_instance SET lokace_mapa_id = mapa_id WHERE lokace_mapa_id IS NULL AND mapa_id IS NOT NULL');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávané hodnoty: 4 (sloupce), YES (mapa smí být prázdná), 2 (indexy).
-- Kdyby tu vyšlo něco jiného, podívej se na výsledek J0 úplně nahoře: skoro jistě jsi v jiné databázi, než kterou používá web.
SELECT COUNT(*) AS sloupce_npc_ocekavano_4 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME IN ('svet_id', 'jedinecny', 'data', 'lokace_mapa_id');
SELECT IS_NULLABLE AS mapa_id_smi_byt_prazdna_ocekavano_YES FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'mapa_id';
SELECT COUNT(DISTINCT INDEX_NAME) AS indexy_npc_ocekavano_2 FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND INDEX_NAME IN ('idx_ni_svet', 'idx_ni_lokace');
