-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Tři bloky v jednom, jde spustit celé najednou:
--  A) Přenos výsledku iniciativy 0 a méně do dalšího kola (migrace 0104): tvor, který skončí na 0 a méně, si v dalším kole znovu
--     hodí, ale místo obvyklých postihů použije výsledek z minulého kola (h1622 b10805).
--  B) Poznámky světa (migrace 0105): štítek (Úkol / NPC / Kořist / Příběh / Místo), vazba na postavu nebo NPC, čas úpravy,
--     a tabulka „přečteno“ pro odznak nepřečtených poznámek.
--  C) Dveře (migrace 0106): zeď, kterou jde otevřít a zavřít (otevřené dveře neblokují pohyb, výhled ani střelu).
-- Samé přidávání sloupců a jedné tabulky — nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
-- Bez tohoto SQL zůstává iniciativa, poznámky i zdi jako dosud (nic se nepřenáší, poznámky jen s textem, místem a sdílením, dveře
-- se v nabídce zdí nezobrazují).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- A1) Účastník souboje: výsledek iniciativy 0 a méně z minulého kola (prázdné = nic se nepřenáší).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_ucastnik
     ADD COLUMN ini_prenos TINYINT NULL COMMENT ''výsledek iniciativy ≤ 0 z minulého kola — v tomhle kole nahrazuje obvyklý modifikátor (h1622 b10805)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_ucastnik' AND COLUMN_NAME = 'ini_prenos');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B1) Poznámka světa: štítek (quest / npc / loot / lore / misto; prázdné = bez štítku).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet_poznamky
     ADD COLUMN stitek VARCHAR(12) NULL COMMENT ''quest / npc / loot / lore / misto — NULL = bez štítku''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamky' AND COLUMN_NAME = 'stitek');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B2) Poznámka světa: vazba na postavu nebo NPC.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet_poznamky
     ADD COLUMN postava_id INT UNSIGNED NULL COMMENT ''vazba na postavu nebo NPC ve světě''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamky' AND COLUMN_NAME = 'postava_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B3) Poznámka světa: kdy ji autor naposledy upravil (prázdné = nikdy).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet_poznamky
     ADD COLUMN upraveno TIMESTAMP NULL DEFAULT NULL COMMENT ''kdy autor poznámku naposledy upravil (NULL = nikdy)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamky' AND COLUMN_NAME = 'upraveno');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B4) Rejstřík na vazbu na postavu (potřebuje ho cizí klíč níž).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet_poznamky ADD KEY postava_id (postava_id)',
  'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamky' AND INDEX_NAME = 'postava_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B5) Smaže-li se postava, vazba poznámky se jen zruší (poznámka zůstane).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet_poznamky
     ADD CONSTRAINT fk_svet_poznamky_postava FOREIGN KEY (postava_id) REFERENCES postavy (id) ON DELETE SET NULL',
  'DO 0')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamky' AND CONSTRAINT_NAME = 'fk_svet_poznamky_postava');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B6) Kdo už kterou poznámku viděl (odznak nepřečtených).
CREATE TABLE IF NOT EXISTS svet_poznamka_precteno (
    poznamka_id INT UNSIGNED NOT NULL,
    ucet_id INT UNSIGNED NOT NULL,
    precteno TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (poznamka_id, ucet_id),
    KEY ucet_id (ucet_id),
    CONSTRAINT fk_precteno_poznamka FOREIGN KEY (poznamka_id) REFERENCES svet_poznamky (id) ON DELETE CASCADE,
    CONSTRAINT fk_precteno_ucet FOREIGN KEY (ucet_id) REFERENCES ucty (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- C1) Zeď: je to dveře? (0 = obyčejná zeď)
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE zdi
     ADD COLUMN dvere TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = dveře (zeď, kterou jde otevřít a zavřít)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zdi' AND COLUMN_NAME = 'dvere');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- C2) Dveře: jsou otevřené? (otevřené neblokují pohyb, výhled ani střelu)
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE zdi
     ADD COLUMN otevrene TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = dveře jsou otevřené (neblokují pohyb, výhled ani střelu)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zdi' AND COLUMN_NAME = 'otevrene');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávané hodnoty: 1, 3, 1, 1, 1, 2
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_ucastnik' AND COLUMN_NAME = 'ini_prenos') AS ini_prenos,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamky' AND COLUMN_NAME IN ('stitek', 'postava_id', 'upraveno')) AS sloupce_poznamek,
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamky' AND INDEX_NAME = 'postava_id') AS rejstrik_postavy,
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamky' AND CONSTRAINT_NAME = 'fk_svet_poznamky_postava') AS cizi_klic,
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_poznamka_precteno') AS tabulka_precteno,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zdi' AND COLUMN_NAME IN ('dvere', 'otevrene')) AS sloupce_dveri;
