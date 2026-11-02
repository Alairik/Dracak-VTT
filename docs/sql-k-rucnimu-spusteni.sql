-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Jeden blok:
--  E) Noční vidění (migrace 0108): dosah nočního vidění tvora v sázích (lupič se Zlepšením zraku, h630 b5663). Přibývá jediný sloupec
--     u tokenu; stav mapy „Noc“ žádnou změnu struktury nepotřebuje.
-- Nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
-- Bez tohoto SQL zůstává všechno jako dosud, jen se u tokenu nenabízí řádek „Noční vidění“ (stav mapy „Noc“ funguje, ale bez nočního vidění je jako „Tma“).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- E1) Token: dosah nočního vidění v sázích (prázdné = nemá).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny
     ADD COLUMN videni_noc_sahy SMALLINT UNSIGNED NULL COMMENT ''dosah nočního vidění v sázích (NULL = nemá)''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'videni_noc_sahy');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola — očekávaná hodnota: 1
SELECT COUNT(*) AS sloupec_nocniho_videni FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'videni_noc_sahy';
