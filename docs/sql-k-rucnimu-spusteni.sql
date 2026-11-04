-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok:
--  I) Automatické seslání kouzla (migrace 0114): u nasazených efektů přibývá jeden sloupec — cíl, trvání a zda už se účinek vyhodnotil u kouzla, které tvor právě sesílá.
--     S ním hra kouzlo sešle sama ve chvíli, kdy začíná působit (po předposlední akci kouzlení): zaplatí magy, hodí kostky, zraní nebo uzdraví cíl a nasadí efekty.
--     Bez něj se kouzlení počítá a ohlásí jako dosud a účinek se zadává z deníku („Použít“).
-- Nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
--
-- Ověření po spuštění: v administraci Změny databáze → „Co aplikace v databázi vidí“ musí být u aktivni_efekty.kouzleni_data ✓ (číslo z phpMyAdminu není spolehlivé —
-- minule vyšlo 0, i když sloupce v databázi webu byly).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- I1) Údaje o sesílaném kouzlu (JSON: cíl, trvání, zaplacené magy, účinek proběhl).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE aktivni_efekty ADD COLUMN kouzleni_data VARCHAR(255) NULL COMMENT ''sesílané kouzlo: cíl, trvání, účinek proběhl (JSON, štítek Kouzlí)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aktivni_efekty' AND COLUMN_NAME = 'kouzleni_data');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávaná hodnota: 1
SELECT COUNT(*) AS sloupec_kouzleni_data FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aktivni_efekty' AND COLUMN_NAME = 'kouzleni_data';
