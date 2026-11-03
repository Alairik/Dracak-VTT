-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok:
--  G) Měřítko světové mapy (migrace 0110): kolik mil je 1000 px mapy. S ním měřidlo na mapě ukáže vzdálenost v mílích a PJ ji jedním klikem
--     pošle do kalkulačky cesty. Přibývá jediný sloupec u mapy.
-- Nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
-- Bez tohoto SQL zůstává všechno jako dosud (pole měřítka se v nastavení gridu nenabízí, měřidlo ukazuje jen sáhy).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- G1) Mapa: měřítko (kolik mil je 1000 px; prázdné = nezadáno).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE mapy
     ADD COLUMN mil_na_1000px DECIMAL(8,2) NULL COMMENT ''měřítko mapy: kolik mil je 1000 px (NULL = nezadáno)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mapy' AND COLUMN_NAME = 'mil_na_1000px');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávaná hodnota: 1
SELECT COUNT(*) AS sloupec_meritka FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mapy' AND COLUMN_NAME = 'mil_na_1000px';
