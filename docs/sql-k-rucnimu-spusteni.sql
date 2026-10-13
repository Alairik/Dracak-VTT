-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Lze spustit opakovaně — každá část
-- se provede jen tehdy, když ještě není provedená (jinak vypíše "už bylo provedeno").
-- Na konci je kontrola: má vyjít  rychlost_chuze = 16, rychlost_behu = 16, sloupec_nosnost_tah = 1.

-- 1) 0074: tabulky rychlosti pohybu (Tabulka rychlosti při chůzi / při běhu) + jejich řádky
CREATE TABLE IF NOT EXISTS rychlost_chuze (
    id                  TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bonus_pohyblivosti  TINYINT NOT NULL UNIQUE,
    prochazka           SMALLINT UNSIGNED NOT NULL COMMENT 'bodů chůze za hodinu při procházce',
    chuze               SMALLINT UNSIGNED NOT NULL COMMENT 'bodů chůze za hodinu při chůzi',
    spech               SMALLINT UNSIGNED NOT NULL COMMENT 'bodů chůze za hodinu při spěchu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TABULKA RYCHLOSTI PŘI CHŮZI, PPP h1634 (bonus -5..+5) + Bestiář h2393 (+6..+10).';

INSERT IGNORE INTO rychlost_chuze (bonus_pohyblivosti, prochazka, chuze, spech) VALUES
(-5, 12, 20, 28),
(-4, 15, 25, 35),
(-3, 18, 30, 42),
(-2, 21, 35, 49),
(-1, 24, 40, 56),
(0, 27, 45, 63),
(1, 30, 50, 70),
(2, 33, 55, 77),
(3, 36, 60, 84),
(4, 39, 65, 91),
(5, 42, 70, 98),
(6, 45, 75, 105),
(7, 48, 80, 112),
(8, 51, 85, 119),
(9, 54, 90, 126),
(10, 57, 95, 133);

CREATE TABLE IF NOT EXISTS rychlost_behu (
    id                  TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bonus_pohyblivosti  TINYINT NOT NULL UNIQUE,
    poklus              SMALLINT UNSIGNED NOT NULL COMMENT 'bodů běhu za kolo při poklusu',
    beh                 SMALLINT UNSIGNED NOT NULL COMMENT 'bodů běhu za kolo při běhu',
    sprint              SMALLINT UNSIGNED NOT NULL COMMENT 'bodů běhu za kolo při sprintu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TABULKA RYCHLOSTI PŘI BĚHU, PPP h1634 (bonus -5..+5) + Bestiář h2393 (+6..+10).';

INSERT IGNORE INTO rychlost_behu (bonus_pohyblivosti, poklus, beh, sprint) VALUES
(-5, 16, 24, 48),
(-4, 20, 30, 57),
(-3, 24, 36, 66),
(-2, 28, 42, 75),
(-1, 32, 48, 84),
(0, 36, 54, 93),
(1, 40, 60, 102),
(2, 44, 66, 111),
(3, 48, 72, 120),
(4, 52, 78, 129),
(5, 56, 84, 138),
(6, 60, 90, 147),
(7, 64, 96, 156),
(8, 68, 102, 165),
(9, 72, 108, 174),
(10, 76, 114, 183);

-- 2) 0075: nosnost jízdního zvířete v tahu (hodnotu tažného koně doplní migrace 0076 sama)
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE jizdni_zvirata ADD COLUMN nosnost_tah_mn SMALLINT UNSIGNED NULL COMMENT ''nosnost v tahu/zápřahu v mn; NULL = stejná jako nosnost_mn'' AFTER nosnost_mn',
  'SELECT ''0075 už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jizdni_zvirata' AND COLUMN_NAME = 'nosnost_tah_mn');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kontrola
SELECT
  (SELECT COUNT(*) FROM rychlost_chuze) AS rychlost_chuze,
  (SELECT COUNT(*) FROM rychlost_behu) AS rychlost_behu,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jizdni_zvirata' AND COLUMN_NAME = 'nosnost_tah_mn') AS sloupec_nosnost_tah;
