-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
--
-- Iniciativa v2: jedno pole „útoků za 2 kola“ místo zaškrtávání několika řádků Tabulky vícenásobných útoků a hlídání,
-- že kdo dostal bonus k iniciativě za úkon (kouzlení, vícenásobné útoky), věnuje tomu první akci kola a aspoň polovinu
-- akcí celého kola (h1622). Samé přidávání sloupců — nic se nemění ani nemaže; jde spustit opakovaně a v libovolném
-- stavu databáze. Bez tohohle SQL iniciativa funguje jako dosud (zaškrtávací bonusy, bez hlídání).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- 1) Účastník souboje: kolik útoků za 2 kola umí (bonus k iniciativě je 3 × (počet − 2), Tabulka vícenásobných útoků).
--    Zůstává mezi koly, počítá se jen když tvor ohlásí úmysl Útok nebo Střelba.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_ucastnik
     ADD COLUMN utoku_za_2_kola TINYINT UNSIGNED NULL COMMENT ''kolik útoků za 2 kola tvor umí (NULL = 2, bez bonusu)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_ucastnik' AND COLUMN_NAME = 'utoku_za_2_kola');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Hod na iniciativu: slíbený úkon (za který tvor dostal bonus), kolik akcí mu musí věnovat a kolik už věnoval;
--    akce_hotovo = kolik vlastních akcí v kole už odehrál (první akce musí být slíbený úkon).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_iniciativa
     ADD COLUMN zavazek VARCHAR(10) NULL COMMENT ''kouzleni / utok / strelba — úkon, za který tvor dostal bonus k iniciativě'',
     ADD COLUMN zavazek_akci TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''kolik akcí kola musí slíbenému úkonu věnovat (aspoň polovina)'',
     ADD COLUMN zavazek_hotovo TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''kolik akcí už slíbenému úkonu věnoval'',
     ADD COLUMN akce_hotovo TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''kolik vlastních akcí v kole už odehrál''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa' AND COLUMN_NAME = 'zavazek');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola: musí vyjít 1 a 4 (nový sloupec účastníka, nové sloupce hodu na iniciativu).
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_ucastnik'
     AND COLUMN_NAME = 'utoku_za_2_kola') AS sloupec_ucastnika,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_iniciativa'
     AND COLUMN_NAME IN ('zavazek', 'zavazek_akci', 'zavazek_hotovo', 'akce_hotovo')) AS sloupce_iniciativy;
