-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Nic se nemaže; blok jde spustit opakovaně a v libovolném stavu databáze.
--
-- TŘI BLOKY V JEDNOM VLOŽENÍ:
--
--  K) Infravidění nestvůr z bestiáře (migrace 0116 a 0117). Dosud se infravidění zadávalo ručně na každém
--     tokenu, přestože bestiář ho u řady nestvůr popisuje i s dosahem (zmije rohatá 10 sáhů, vážka jeskynní
--     a nemrtví 30 sáhů…). Po tomhle kroku token nestvůry infravidění dostane sám a stejně tak příznak
--     „infravidění ho nerozezná“ (hlen zelený, mlok, nevidění, oživlé sochy). Přidává se jeden sloupec,
--     do kterého se u nestvůry ukládá hodnota schopnosti — deník nestvůry na něm postaví i další schopnosti
--     a dovednosti. Stejný sloupec dostane i vazba rasa ↔ schopnost, odkud si infravidění (trpaslík 20 sáhů)
--     vezme token postavy. Bez tohohle SQL se nic nezkazí, jen se infravidění dál zadává ručně jako dřív.
--
--  L) Skupina útoku u zbraně (bestiář b17729–b17745). Boj od 2026-11-10 počítá se zranitelností nestvůry,
--     ale každý fyzický útok musí brát jako „B obyčejná zbraň“ — kouzelnou (C) ani stříbrnou (D) zbraň
--     katalog předmětů nijak neoznačuje. Tenhle sloupec to umožní: u konkrétní zbraně se v editoru vybere
--     skupina a boj pak sám pozná, že nestvůra zranitelná jen kouzelnými zbraněmi obyčejný meč neucítí.
--     Prázdná hodnota = obyčejná zbraň, takže se po přidání sloupce nic nezmění, dokud PJ něco nenastaví.
--
--  M) Přepínač animací souboje (migrace 0120). Plynulý pohyb tokenů, máchnutí zbraní, letící střely a kouzla
--     a hlášení „kdo je na tahu“ mají jít vypnout v administraci světa. Výchozí hodnota je „zapnuté“, takže
--     se po přidání sloupce nic nezmění; dokud sloupec není, animace běží a přepínač je neaktivní.
--
-- JAK NA TO:
--   1. V administraci otevři Změny databáze a přečti si řádek „Databáze, kterou web používá: …“.
--   2. V phpMyAdminu klikni VLEVO na databázi s přesně tímhle jménem a teprve pak na záložku SQL.
--   3. Vlož celý tenhle soubor a spusť. První výsledek (K0) musí ukázat to samé jméno databáze a „ANO“.
--   4. Poslední výsledek musí být 4. Pak se v administraci na stránce Změny databáze podívej do tabulky
--      „Co aplikace v databázi vidí“ — u všech čtyř řádků musí být ✓.
--   5. Nakonec tamtéž klikni na „Spustit čekající změny“ — teprve ta doplní, které nestvůry infravidění
--      mají (to už jsou běžné INSERTy, ty web účet umí sám).

-- K0) KONTROLA NA ZAČÁTEK: kde vlastně jsem.
SELECT
  IFNULL(DATABASE(), '(žádná databáze není vybraná!)') AS jsem_v_databazi,
  IF(COUNT(*) = 2, 'ANO — můžeš pokračovat', 'NE — JSI VE ŠPATNÉ DATABÁZI, přepni ji vlevo a spusť blok znovu') AS jsou_tu_tabulky_schopnosti
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('nestvura_schopnosti', 'rasa_schopnosti');

SET @je_tu = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_schopnosti');

-- K1) Hodnota schopnosti u konkrétní nestvůry (dosah infravidění v sázích; prázdné = kniha dosah neuvádí).
SET @s = (SELECT IF(@je_tu = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE nestvura_schopnosti ADD COLUMN hodnota VARCHAR(40) NULL COMMENT ''hodnota schopnosti u téhle nestvůry (např. dosah infravidění v sázích)''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_schopnosti' AND COLUMN_NAME = 'hodnota');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- K2) Totéž u ras (migrace 0117): dosah infravidění trpaslíka (20 sáhů, hrac b790).
SET @je_tu2 = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rasa_schopnosti');
SET @s = (SELECT IF(@je_tu2 = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE rasa_schopnosti ADD COLUMN hodnota VARCHAR(40) NULL COMMENT ''hodnota schopnosti u téhle rasy (např. dosah infravidění v sázích)''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rasa_schopnosti' AND COLUMN_NAME = 'hodnota');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- L1) Skupina útoku u zbraně: prázdné = obyčejná zbraň (B), jinak písmeno z bestiáře (C kouzelná, D stříbrná…).
SET @je_tu3 = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety');
SET @s = (SELECT IF(@je_tu3 = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE predmety ADD COLUMN zranitelnost_skupina CHAR(1) NULL COMMENT ''skupina útoku podle bestiáře (b17729-b17745); prázdné = B obyčejná zbraň''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'zranitelnost_skupina');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- M1) Přepínač animací souboje u světa: 1 = zapnuté (výchozí), 0 = vypnuté.
SET @je_tu4 = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet');
SET @s = (SELECT IF(@je_tu4 = 0 OR COUNT(*) > 0, 'DO 0',
  'ALTER TABLE svet ADD COLUMN animace TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''animace souboje na mapě (pohyb, útok, kouzlo); 0 = vypnuté''')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'animace');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávaná hodnota: 4 (sloupec „hodnota“ u nestvur i u ras, skupina útoku u předmětů, animace u světa)
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'hodnota'
      AND TABLE_NAME IN ('nestvura_schopnosti', 'rasa_schopnosti'))
  + (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'zranitelnost_skupina')
  + (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'animace')
  AS sloupce_ocekavano_4;
