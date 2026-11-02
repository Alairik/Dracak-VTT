-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok:
--  F) Hodiny světa (migrace 0109): čas během dne (jedna směna je zhruba 10 minut) a hoření světel. Přibývají dva sloupce:
--     svet.aktualni_smena (kolikátá směna dne, výchozí 48 = 8:00) a tokeny.svetlo_zbyva (kolik směn světlo ještě vydrží, prázdné = bez limitu).
-- Nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
-- Bez tohoto SQL zůstává všechno jako dosud (čas ve dni se nezobrazuje, světla hoří bez limitu).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- F1) Svět: směna dne 0–143 (1 směna = 10 minut, 48 = 8:00).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet
     ADD COLUMN aktualni_smena SMALLINT UNSIGNED NOT NULL DEFAULT 48 COMMENT ''směna dne 0–143 (1 směna = 10 minut, 48 = 8:00)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'aktualni_smena');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- F2) Token: kolik směn světlo (nebo zóna tmy) ještě vydrží (prázdné = bez limitu).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny
     ADD COLUMN svetlo_zbyva SMALLINT UNSIGNED NULL COMMENT ''kolik směn světlo ještě vydrží (NULL = bez limitu)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'svetlo_zbyva');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávané hodnoty: 1, 1
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'aktualni_smena') AS sloupec_smeny_dne,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'svetlo_zbyva') AS sloupec_horeni;
