-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Lze spustit opakovaně — každá část
-- se provede jen tehdy, když ještě není provedená (jinak vypíše "už bylo provedeno").
-- Předchozí bloky (migrace 0065–0081, DROP tří starých tabulek) jsou na produkci provedené.
-- Na konci je kontrola: má vyjít  sloupce_tokenu = 6, typ_obecny = 1, udalosti_varchar = 1, tabulky = 2.
--
-- 0084: mapa — otočení, velikost, barva, zámek, název a obrázek tokenů; vlastní tokeny;
-- knihovna obrázků; kreslení na mapu; události už nejsou pevný výčet (další novinky
-- mapy tak nebudou chtít další ruční SQL).

SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny ADD COLUMN smer SMALLINT NOT NULL DEFAULT 0 COMMENT ''směr čelem ve stupních, 0 = doprava, po směru hodinových ručiček'' AFTER z_poradi',
  'SELECT ''0084 tokeny.smer už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'smer');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny ADD COLUMN velikost_sahu DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT ''průměr tokenu v sázích (1 = jeden hex/čtverec)'' AFTER smer',
  'SELECT ''0084 tokeny.velikost_sahu už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'velikost_sahu');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny ADD COLUMN barva VARCHAR(7) NULL COMMENT ''#rrggbb, NULL = výchozí podle typu'' AFTER velikost_sahu',
  'SELECT ''0084 tokeny.barva už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'barva');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny ADD COLUMN zamceno TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = polohu nejde měnit, dokud PJ neodemkne'' AFTER barva',
  'SELECT ''0084 tokeny.zamceno už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'zamceno');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny ADD COLUMN nazev VARCHAR(100) NULL COMMENT ''vlastní název (obecný token, přejmenovaná nestvůra)'' AFTER zamceno',
  'SELECT ''0084 tokeny.nazev už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'nazev');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny ADD COLUMN obrazek_id INT UNSIGNED NULL COMMENT ''obrázek z knihovny světa (svet_obrazky)'' AFTER nazev',
  'SELECT ''0084 tokeny.obrazek_id už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'obrazek_id');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE tokeny MODIFY COLUMN typ_entity ENUM(''postava'',''nestvura_instance'',''obecny'') NOT NULL',
  'SELECT ''0084 tokeny.typ_entity už má hodnotu obecny'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'typ_entity' AND COLUMN_TYPE LIKE '%obecny%');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet_udalosti MODIFY COLUMN typ VARCHAR(40) NOT NULL',
  'SELECT ''0084 svet_udalosti.typ už je VARCHAR'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_udalosti' AND COLUMN_NAME = 'typ' AND DATA_TYPE = 'varchar');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS svet_obrazky (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  svet_id INT UNSIGNED NOT NULL,
  nazev VARCHAR(100) NOT NULL,
  soubor VARCHAR(64) NOT NULL COMMENT 'náhodný název souboru v uploads/obrazky/',
  sirka SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  vyska SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ucet_id INT UNSIGNED NULL,
  vytvoreno TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY svet_id (svet_id),
  CONSTRAINT fk_svet_obrazky_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mapa_kresby (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mapa_id INT UNSIGNED NOT NULL,
  ucet_id INT UNSIGNED NULL,
  typ VARCHAR(20) NOT NULL COMMENT 'cara, sipka, obdelnik, kruh, kuzel, text',
  data TEXT NOT NULL COMMENT 'JSON: body, text…',
  barva VARCHAR(7) NOT NULL DEFAULT '#ffd166',
  sirka SMALLINT UNSIGNED NOT NULL DEFAULT 4 COMMENT 'tloušťka čáry v px mapy',
  viditelne_hracum TINYINT(1) NOT NULL DEFAULT 1,
  vytvoreno TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY mapa_id (mapa_id),
  CONSTRAINT fk_mapa_kresby_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontrola
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME IN ('smer', 'velikost_sahu', 'barva', 'zamceno', 'nazev', 'obrazek_id')) AS sloupce_tokenu,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tokeny' AND COLUMN_NAME = 'typ_entity' AND COLUMN_TYPE LIKE '%obecny%') AS typ_obecny,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet_udalosti' AND COLUMN_NAME = 'typ' AND DATA_TYPE = 'varchar') AS udalosti_varchar,
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('svet_obrazky', 'mapa_kresby')) AS tabulky;

