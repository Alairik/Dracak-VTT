-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Lze spustit opakovaně — každá část
-- se provede jen tehdy, když ještě není provedená (jinak vypíše "už bylo provedeno").
-- Na konci je kontrola: má vyjít  postavy_sloupcu = 13, predmety_sloupcu = 2, zvirat = 7.

-- 1) 0065: únava postavy
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE postavy ADD COLUMN unava SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_magenergie',
  'SELECT ''0065 už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postavy' AND COLUMN_NAME = 'unava');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) 0067: číselník jízdních zvířat (tabulka + 7 řádků; opakované spuštění nic nezmění)
CREATE TABLE IF NOT EXISTS jizdni_zvirata (
    id                 TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev              VARCHAR(50) NOT NULL UNIQUE,
    pohyblivost        TINYINT UNSIGNED NOT NULL COMMENT 'nenaložené zvíře (h2057: "žádné naložení")',
    nosnost_mn         SMALLINT UNSIGNED NOT NULL,
    vytrvalost         TINYINT UNSIGNED NOT NULL COMMENT 'body únavy do "unavené" (jako vytrvalost postavy)',
    ujede_mil          SMALLINT UNSIGNED NOT NULL,
    cas_na_10_mil_min  SMALLINT UNSIGNED NOT NULL COMMENT 'v pravidlech h:mm, tady v minutách',
    popis              TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TABULKA JEZDECKÝCH ZVÍŘAT, PPP h1635; rozšíření viz homebrew h2057.';
INSERT IGNORE INTO jizdni_zvirata (nazev, pohyblivost, nosnost_mn, vytrvalost, ujede_mil, cas_na_10_mil_min, popis) VALUES
('osel',          8, 400, 10, 28, 120, 'levný, odolný, netrpí závratí, nenese postavy B+, svéhlavý'),
('mezek',        12, 600, 12, 38,  90, 'kříženec oslice a koně, jako osel, nenese C+'),
('mula',         21, 700, 17, 53,  90, 'kříženec kobyly a osla, houževnatá, netrpí závratí, vysoká inteligence, nese i lehčí C'),
('pony',         14, 700, 12, 29, 120, 'malý, vytrvalý, snáší extrémní podmínky, nenese postavy C+'),
('tažný kůň',    18, 800, 15, 42, 105, 'těžká práce, nosnost o čtvrtinu vyšší než klasický, 1000 mn v zápřahu'),
('klasický kůň', 18, 800, 14, 39, 105, 'běžný, krokem, nízká inteligence'),
('jezdecký kůň', 21, 750, 16, 50,  90, 'klus/cval, dobrodruzi');

-- 3) 0068: doprava postavy (až po bodu 2)
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE postavy ADD COLUMN doprava_typ ENUM(''chuze'',''zvire'') NOT NULL DEFAULT ''chuze'', ADD COLUMN doprava_zvire_id TINYINT UNSIGNED NULL, ADD CONSTRAINT fk_postavy_doprava_zvire FOREIGN KEY (doprava_zvire_id) REFERENCES jizdni_zvirata (id)',
  'SELECT ''0068 už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postavy' AND COLUMN_NAME = 'doprava_typ');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) 0070: pole pro Osobní deník (zkušenost, peníze, zbroj, štít, texty)
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE postavy ADD COLUMN zkusenost INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN penize_zl INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN penize_st INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN penize_md INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN velikost VARCHAR(2) NULL, ADD COLUMN zbroj_id INT UNSIGNED NULL, ADD COLUMN stit_id INT UNSIGNED NULL, ADD COLUMN drzena_zbran_id INT UNSIGNED NULL, ADD COLUMN puvod TEXT NULL, ADD COLUMN osobnost TEXT NULL, ADD CONSTRAINT fk_postavy_zbroj FOREIGN KEY (zbroj_id) REFERENCES predmety (id) ON DELETE SET NULL, ADD CONSTRAINT fk_postavy_stit FOREIGN KEY (stit_id) REFERENCES predmety (id) ON DELETE SET NULL, ADD CONSTRAINT fk_postavy_drzena_zbran FOREIGN KEY (drzena_zbran_id) REFERENCES predmety (id) ON DELETE SET NULL',
  'SELECT ''0070 už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postavy' AND COLUMN_NAME = 'zkusenost');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) 0071: síla a délka zbraně
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety ADD COLUMN sila_zbrane TINYINT UNSIGNED NULL, ADD COLUMN delka_zbrane VARCHAR(10) NULL',
  'SELECT ''0071 už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'sila_zbrane');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kontrola
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postavy'
     AND COLUMN_NAME IN ('unava','doprava_typ','doprava_zvire_id','zkusenost','penize_zl','penize_st','penize_md','velikost','zbroj_id','stit_id','drzena_zbran_id','puvod','osobnost')) AS postavy_sloupcu,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety'
     AND COLUMN_NAME IN ('sila_zbrane','delka_zbrane')) AS predmety_sloupcu,
  (SELECT COUNT(*) FROM jizdni_zvirata) AS zvirat;
