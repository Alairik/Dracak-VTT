-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Lze spustit opakovaně — každá část
-- se provede jen tehdy, když ještě není provedená (jinak vypíše "už bylo provedeno").
-- Předchozí bloky (migrace 0065–0084) jsou na produkci provedené.
-- Na konci je kontrola: má vyjít  sloupce_kola = 3, tabulka_ucastniku = 1.
--
-- 0085: souboj, krok 1 — fáze kola (úmysly → iniciativa → tahy), dvě půlky kola,
-- překvapení a seznam účastníků souboje s jejich úmysly.

SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_stav ADD COLUMN faze VARCHAR(12) NOT NULL DEFAULT ''kolo'' COMMENT ''umysly / iniciativa / kolo'' AFTER aktivni_token_id',
  'SELECT ''0085 kolo_stav.faze už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_stav' AND COLUMN_NAME = 'faze');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_stav ADD COLUMN polovina TINYINT NOT NULL DEFAULT 1 COMMENT ''půlka kola: 1 nebo 2'' AFTER faze',
  'SELECT ''0085 kolo_stav.polovina už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_stav' AND COLUMN_NAME = 'polovina');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE kolo_stav ADD COLUMN prekvapeni VARCHAR(10) NOT NULL DEFAULT ''zadne'' COMMENT ''zadne / druzina / nepratele / obe'' AFTER polovina',
  'SELECT ''0085 kolo_stav.prekvapeni už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_stav' AND COLUMN_NAME = 'prekvapeni');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS kolo_ucastnik (
  mapa_id INT UNSIGNED NOT NULL,
  token_id INT UNSIGNED NOT NULL,
  strana VARCHAR(10) NOT NULL DEFAULT 'druzina' COMMENT 'druzina / nepratele',
  umysl VARCHAR(16) NULL COMMENT 'kouzleni / strelba / utok / pohyb / jina / cekani / utek',
  umysl_cil_token_id INT UNSIGNED NULL,
  PRIMARY KEY (mapa_id, token_id),
  KEY token_id (token_id),
  KEY umysl_cil_token_id (umysl_cil_token_id),
  CONSTRAINT fk_kolo_ucastnik_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_kolo_ucastnik_token FOREIGN KEY (token_id) REFERENCES tokeny (id) ON DELETE CASCADE,
  CONSTRAINT fk_kolo_ucastnik_cil FOREIGN KEY (umysl_cil_token_id) REFERENCES tokeny (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontrola
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_stav' AND COLUMN_NAME IN ('faze', 'polovina', 'prekvapeni')) AS sloupce_kola,
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kolo_ucastnik') AS tabulka_ucastniku;
