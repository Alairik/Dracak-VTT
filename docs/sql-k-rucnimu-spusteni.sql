-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
--
-- Zbraně a iniciativa: u každé zbraně se uloží, jestli je jednoruční / obouruční, jak je těžká a jaký má postih k iniciativě
-- (−2 těžké obouruční a supertěžké zbraně, −3 těžká kuše). Postava s takovou zbraní v ruce pak postih dostane sama, bez
-- zaškrtávání. Samé přidávání sloupců — nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
-- Bez tohohle SQL se „Obouruční zbraň“ a „Těžká kuše“ u iniciativy dál zaškrtávají ručně.
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- 1) Zbraň pro boj tváří v tvář: jednoruční / obouruční.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety
     ADD COLUMN chvat VARCHAR(12) NULL COMMENT ''jednoruci / obourucni — zbraň pro boj tváří v tvář''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'chvat');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Třída zbraně: lehká / střední / těžká / supertěžká.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety
     ADD COLUMN trida_zbrane VARCHAR(12) NULL COMMENT ''lehka / stredni / tezka / supertezka''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'trida_zbrane');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 3) Postih k iniciativě při boji touto zbraní (−2, −3; prázdné = žádný).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety
     ADD COLUMN ini_postih TINYINT NULL COMMENT ''postih k iniciativě při boji touto zbraní (NULL = žádný)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'ini_postih');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola: musí vyjít 3 (tři nové sloupce u zbraní).
SELECT COUNT(*) AS nove_sloupce_zbrani
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME IN ('chvat', 'trida_zbrane', 'ini_postih');
