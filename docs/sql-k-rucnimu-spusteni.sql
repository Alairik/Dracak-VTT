-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok:
--  J) NPC je bytost světa, ne mapy (migrace 0115). Dnes NPC vznikne až tím, že ho položíš na mapu, takže nejde připravit
--     zásobu dopředu ani mít seznam všech NPC ve světě. Po tomhle kroku NPC žije ve světě a mapa je jen místo, kde právě
--     stojí: v liště dole uprostřed si vyrobíš třeba 10 goblinů do zásoby a pak je tažením kladeš na mapu. Zaškrtnutím
--     „každý jedinečný“ dostane každý kus vlastní čísla pro boj (hostinský se jménem), bez zaškrtnutí berou všichni
--     čísla svého druhu jako dosud. NPC se dá navázat na místo (ukáže se u téhle lokace), nebo nechat bez místa
--     jako zásobu pro mapy, které ještě nevznikly. Bez tohoto SQL se lišta se zásobou nezobrazí a tokeny se přidávají jako dřív.
-- Nic se nemaže; jde spustit opakovaně a v libovolném stavu databáze.
--
-- Ověření po spuštění: v administraci Změny databáze → „Co aplikace v databázi vidí“ musí být u nestvura_instance.svet_id,
-- .jedinecny a .data ✓ (číslo z phpMyAdminu není spolehlivé — minule vyšlo 0, i když sloupce v databázi webu byly).
-- Pak na téže stránce klikni na „Spustit čekající změny“ (zapíše 0115 do migrace_log).
--
-- Sem se zapisují jen příkazy, které stránka Změny databáze pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- J1) Ke kterému světu NPC patří.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE nestvura_instance ADD COLUMN svet_id INT UNSIGNED NULL COMMENT ''svět, kterému NPC patří''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'svet_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J2) Mapa smí být prázdná = NPC je v zásobě a nestojí nikde.
SET @s = (SELECT IF(IS_NULLABLE = 'NO',
  'ALTER TABLE nestvura_instance MODIFY COLUMN mapa_id INT UNSIGNED NULL COMMENT ''mapa, na které NPC stojí; prázdné = v zásobě''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'mapa_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J3) Jedinečné NPC (vlastní čísla) × běžný kus (čísla druhu).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE nestvura_instance ADD COLUMN jedinecny TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = jedinečné NPC s vlastními čísly''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'jedinecny');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J4) Vlastní čísla pro boj jedinečného NPC.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE nestvura_instance ADD COLUMN data MEDIUMTEXT NULL COMMENT ''vlastní čísla pro boj jedinečného NPC (JSON)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'data');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J4b) Místo, kam NPC patří (hostinský v tomhle městě); prázdné = bez místa, zásoba pro budoucí mapy.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE nestvura_instance ADD COLUMN lokace_mapa_id INT UNSIGNED NULL COMMENT ''místo, kam NPC patří; prázdné = bez místa''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'lokace_mapa_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE nestvura_instance ADD INDEX idx_ni_lokace (lokace_mapa_id)',
  'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND INDEX_NAME = 'idx_ni_lokace');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J5) Index pro výpis NPC ve světě.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE nestvura_instance ADD INDEX idx_ni_svet (svet_id, nestvura_id)',
  'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND INDEX_NAME = 'idx_ni_svet');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- J6) Dosavadní NPC patří do světa své mapy (bezpečné opakovaně — doplní jen prázdné).
UPDATE nestvura_instance ni JOIN mapy m ON m.id = ni.mapa_id SET ni.svet_id = m.svet_id WHERE ni.svet_id IS NULL;
UPDATE nestvura_instance SET lokace_mapa_id = mapa_id WHERE lokace_mapa_id IS NULL AND mapa_id IS NOT NULL;

-- Kontrola — očekávané hodnoty: 4 (sloupce svet_id, jedinecny, data, lokace_mapa_id), YES (mapa smí být prázdná), 2 (indexy)
SELECT COUNT(*) AS sloupce_npc FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME IN ('svet_id', 'jedinecny', 'data', 'lokace_mapa_id');
SELECT IS_NULLABLE AS mapa_id_smi_byt_prazdna FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND COLUMN_NAME = 'mapa_id';
SELECT COUNT(DISTINCT INDEX_NAME) AS indexy_npc FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_instance' AND INDEX_NAME IN ('idx_ni_svet', 'idx_ni_lokace');
