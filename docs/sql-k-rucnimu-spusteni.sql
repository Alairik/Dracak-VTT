-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok:
--  H) Postup kouzlení (migrace 0113): u nasazených efektů přibývají tři sloupce — které kouzlo tvor sesílá a kolik akcí kouzlení už proběhlo z kolika.
--     S nimi hra počítá akce kouzla podle jeho vyvolání (2 × kola): sama sundá štítek „Kouzlí“ po poslední akci, ohlásí, kdy kouzlo začíná působit,
--     a zásah před předposlední akcí kouzlo zruší. Bez nich štítek „Kouzlí“ funguje jen ručně (sundá ho PJ) a výběr kouzla se nenabízí.
-- Nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- H1) Které kouzlo tvor sesílá.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE aktivni_efekty ADD COLUMN kouzlo_id INT UNSIGNED NULL COMMENT ''kouzlo, které tvor sesílá (štítek Kouzlí)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aktivni_efekty' AND COLUMN_NAME = 'kouzlo_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- H2) Kolik akcí kouzlení kouzlo vyžaduje (2 × vyvolání v kolech).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE aktivni_efekty ADD COLUMN akci_celkem TINYINT UNSIGNED NULL COMMENT ''kolik akcí kouzlení kouzlo vyžaduje (2 × vyvolání v kolech)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aktivni_efekty' AND COLUMN_NAME = 'akci_celkem');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- H3) Kolik akcí kouzlení už proběhlo.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE aktivni_efekty ADD COLUMN akci_hotovo TINYINT UNSIGNED NULL COMMENT ''kolik akcí kouzlení už proběhlo''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aktivni_efekty' AND COLUMN_NAME = 'akci_hotovo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávaná hodnota: 3
SELECT COUNT(*) AS sloupce_kouzleni FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aktivni_efekty' AND COLUMN_NAME IN ('kouzlo_id', 'akci_celkem', 'akci_hotovo');
