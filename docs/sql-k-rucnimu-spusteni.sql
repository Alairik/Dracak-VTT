-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok: „Zbraně u sebe“ (Deník v2, krok 2) — k předmětu v inventáři postavy přibude příznak, že ho má při těle
-- (opasek, pochva, závěs, toulec). V boji jde vytasit jen zbraň u sebe; zbraň v batohu ne. Výchozí je „u sebe“ pro
-- všechny dosavadní i nové zbraně (kniha neříká, kolik se jich na opasek vejde), takže se po spuštění nic nepokazí.
-- Samé přidávání sloupce — nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
-- Bez tohoto SQL deník funguje dál, jen nerozlišuje „u sebe“ (vytasení v boji stojí akci i tak).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- 1) Zbraň při těle: 1 = u sebe (v boji jde vytasit), 0 = v batohu.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE postava_predmety
     ADD COLUMN u_sebe TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''zbraň při těle (opasek, pochva, závěs, toulec): 1 = v boji ji jde vytasit, 0 = v batohu''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postava_predmety' AND COLUMN_NAME = 'u_sebe');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola: musí vyjít 1 (sloupec existuje).
SELECT COUNT(*) AS u_sebe_sloupec
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postava_predmety' AND COLUMN_NAME = 'u_sebe';
