-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Dva bloky v jednom, jde spustit celé najednou:
--  A) Iniciativa v2 — jedno pole „útoků za 2 kola“ místo zaškrtávání řádků tabulky, slib úkonu (kouzlení, vícenásobné útoky)
--     a ztráta iniciativy (−3) v příštím kole, když tvor slíbenému úkonu nevěnuje aspoň polovinu akcí.
--  B) Zbraně a iniciativa — u každé zbraně se uloží jednoruční / obouruční, třída a postih k iniciativě (−2 těžké obouruční
--     a supertěžké zbraně, −3 těžká kuše); postava s takovou zbraní v ruce ho dostane sama.
-- Samé přidávání sloupců — nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze. Bez tohoto SQL
-- zůstává iniciativa jako dosud (zaškrtávací bonusy, nic se nehlídá).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- A1) Účastník souboje: kolik útoků za 2 kola umí (bonus k iniciativě je 3 × (počet − 2), Tabulka vícenásobných útoků).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_ucastnik
     ADD COLUMN utoku_za_2_kola TINYINT UNSIGNED NULL COMMENT ''kolik útoků za 2 kola tvor umí (NULL = 2, bez bonusu)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_ucastnik' AND COLUMN_NAME = 'utoku_za_2_kola');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- A2) Hod na iniciativu: slíbený úkon (kouzlení / útok / střelba), za který tvor dostal bonus.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_iniciativa
     ADD COLUMN zavazek VARCHAR(10) NULL COMMENT ''kouzleni / utok / strelba — úkon, za který tvor dostal bonus k iniciativě''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa' AND COLUMN_NAME = 'zavazek');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- A3) Kolik akcí kola musí slíbenému úkonu věnovat (aspoň polovina).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_iniciativa
     ADD COLUMN zavazek_akci TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''kolik akcí kola musí slíbenému úkonu věnovat (aspoň polovina)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa' AND COLUMN_NAME = 'zavazek_akci');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- A4) Kolik akcí už slíbenému úkonu věnoval.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_iniciativa
     ADD COLUMN zavazek_hotovo TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''kolik akcí už slíbenému úkonu věnoval''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa' AND COLUMN_NAME = 'zavazek_hotovo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- A5) Kolik vlastních akcí v kole už odehrál (první akce musí být slíbený úkon).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_iniciativa
     ADD COLUMN akce_hotovo TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''kolik vlastních akcí v kole už odehrál''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa' AND COLUMN_NAME = 'akce_hotovo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B1) Zbraň pro boj tváří v tvář: jednoruční / obouruční.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety
     ADD COLUMN chvat VARCHAR(12) NULL COMMENT ''jednoruci / obourucni — zbraň pro boj tváří v tvář''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'chvat');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B2) Třída zbraně: lehká / střední / těžká / supertěžká.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety
     ADD COLUMN trida_zbrane VARCHAR(12) NULL COMMENT ''lehka / stredni / tezka / supertezka''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'trida_zbrane');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- B3) Postih k iniciativě při boji touto zbraní (−2, −3; prázdné = žádný).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety
     ADD COLUMN ini_postih TINYINT NULL COMMENT ''postih k iniciativě při boji touto zbraní (NULL = žádný)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'ini_postih');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola: musí vyjít 1, 4 a 3 (sloupec účastníka souboje, sloupce hodu na iniciativu, sloupce zbraní).
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_ucastnik'
     AND COLUMN_NAME = 'utoku_za_2_kola') AS sloupec_ucastnika,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa'
     AND COLUMN_NAME IN ('zavazek', 'zavazek_akci', 'zavazek_hotovo', 'akce_hotovo')) AS sloupce_iniciativy,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety'
     AND COLUMN_NAME IN ('chvat', 'trida_zbrane', 'ini_postih')) AS sloupce_zbrani;
