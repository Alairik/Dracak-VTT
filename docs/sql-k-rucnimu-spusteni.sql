-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Lze spustit opakovaně — každá část
-- se provede jen tehdy, když ještě není provedená (jinak vypíše "už bylo provedeno").
-- Na konci je kontrola: má vyjít  rychlost_chuze = 16, rychlost_behu = 16, sloupec_nosnost_tah = 1, sloupce_pohyb = 4, povozy = 8, povoz_nalozeni = 6.

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

-- 3) 0077: přepínače automatiky pohybu ve světě (zapnuté ve výchozím stavu)
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet ADD COLUMN auto_naklad TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_zranitelnost',
  'SELECT ''0077 auto_naklad už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'auto_naklad');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet ADD COLUMN auto_pohyblivost TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_naklad',
  'SELECT ''0077 auto_pohyblivost už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'auto_pohyblivost');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE svet ADD COLUMN auto_unava TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_pohyblivost',
  'SELECT ''0077 auto_unava už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svet' AND COLUMN_NAME = 'auto_unava');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) 0078: ručně zadaná pohyblivost postavy
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE postavy ADD COLUMN pohyblivost_rucne SMALLINT NULL AFTER doprava_zvire_id',
  'SELECT ''0078 už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'postavy' AND COLUMN_NAME = 'pohyblivost_rucne');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) 0079: povozy (8 modelů) a Tabulka naložení povozu (tabulky + řádky; opakované spuštění nic nezmění)
CREATE TABLE IF NOT EXISTS povozy (
    id                       TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev                    VARCHAR(40) NOT NULL UNIQUE,
    kategorie                ENUM('vuz','kocar','dostavnik') NOT NULL,
    vaha_od_mn               SMALLINT UNSIGNED NOT NULL COMMENT 'váha prázdného vozu, PPP',
    vaha_do_mn               SMALLINT UNSIGNED NOT NULL,
    vaha_hb_od_mn            SMALLINT UNSIGNED NULL COMMENT 'váha prázdného vozu, homebrew h2057',
    vaha_hb_do_mn            SMALLINT UNSIGNED NULL,
    nosnost_nasobek_od       DECIMAL(3,1) NOT NULL COMMENT 'konstrukční nosnost = násobek váhy vozu',
    nosnost_nasobek_do       DECIMAL(3,1) NOT NULL,
    unava_za_body_zvirete    TINYINT UNSIGNED NOT NULL COMMENT 'cestující: 1 bod únavy za tolik bodů únavy zvířete',
    klus                     ENUM('ne','jen_dobra_cesta','ano') NOT NULL,
    rizeni_klus              TINYINT UNSIGNED NOT NULL,
    rizeni_pomaly_cval       TINYINT UNSIGNED NOT NULL,
    rizeni_rychly_cval       TINYINT UNSIGNED NOT NULL,
    rizeni_trysk             TINYINT UNSIGNED NOT NULL,
    rizeni_snizeni           TINYINT UNSIGNED NOT NULL COMMENT '% za každý bod náročnosti nad 10',
    kapacita_osob            TINYINT UNSIGNED NULL,
    ridicu                   TINYINT UNSIGNED NULL,
    popis                    TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Povozy: PPP h1639–h1641, PJ Tabulka řízení h2366, homebrew POVOZY h2057.';

INSERT IGNORE INTO povozy (nazev, kategorie, vaha_od_mn, vaha_do_mn, vaha_hb_od_mn, vaha_hb_do_mn, nosnost_nasobek_od, nosnost_nasobek_do, unava_za_body_zvirete, klus, rizeni_klus, rizeni_pomaly_cval, rizeni_rychly_cval, rizeni_trysk, rizeni_snizeni, kapacita_osob, ridicu, popis) VALUES
('žebřiňák',        'vuz',       4000, 6000, 3000,  6000, 1.0, 1.0, 4, 'jen_dobra_cesta', 70, 40, 30, 20, 1, NULL, NULL, 'otevřený vůz pro velkoobjemový náklad na kratší vzdálenosti; zapřahují se obvykle dvě zvířata, u nejmenších i poník, mezek či osel'),
('valník',          'vuz',       4500, 7000, 3500,  7000, 1.5, 1.5, 4, 'jen_dobra_cesta', 80, 50, 30, 30, 1, NULL, NULL, 'plochý otevřený vůz (pytle, sudy, dřevo, písek, kámen); zapřahuje se pár zvířat'),
('krytý vůz',       'vuz',       5000, 8000, 4500,  8000, 2.0, 3.0, 6, 'jen_dobra_cesta', 80, 50, 40, 30, 1, NULL, NULL, 'klasický nákladní vůz s plachtou pro dálkovou dopravu; lehký pár zvířat, těžký vícespřeží; nejbytelnější pojmou trojnásobek váhy'),
('maringotka',      'vuz',       6000, 8000, 6000, 10000, 1.5, 2.0, 8, 'ne',              50, 20, 10,  0, 3, NULL, NULL, 'obyvatelný domek na kolech; jen krokem; výjimečně dvojnásobek váhy'),
('malý kočár',      'kocar',     4000, 4700, 3500,  4500, 1.5, 2.0, 10, 'ano',            99, 80, 70, 40, 2, 2,    1,    'dvě osoby a kočí; krytý, otevřený váží zhruba polovinu; jeden pár jezdeckých koní'),
('velký kočár',     'kocar',     4500, 5600, 5000,  8000, 1.5, 2.0, 10, 'ano',            99, 90, 80, 50, 4, 4,    2,    'čtyři osoby uvnitř a dvě venku (z nich kočí); krytý, otevřený váží zhruba polovinu; dva páry jezdeckých koní'),
('malý dostavník',  'dostavnik', 6000, 7000, 6000,  8000, 2.0, 2.0, 8, 'ano',             99, 80, 60, 30, 3, 5,    1,    'pět osob uvnitř a kočí; zavazadla obecně nejvýš 400 mn na osobu; dva páry koní (v nouzi pár po dobré cestě)'),
('velký dostavník', 'dostavnik', 7000, 8000, 8500, 10000, 2.0, 2.0, 8, 'ano',             99, 80, 70, 40, 5, 9,    2,    'sedm až devět cestujících (dva venku); zavazadla jako u malého; tři páry koní');

CREATE TABLE IF NOT EXISTS povoz_nalozeni (
    id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    narocnost_do SMALLINT UNSIGNED NOT NULL UNIQUE COMMENT 'horní mez náročnosti terénu (bodů pohybu na míli)',
    procento     TINYINT UNSIGNED NOT NULL COMMENT 'kolik % provozního nákladu (posádka + náklad, bez váhy vozu) lze naložit',
    teren        VARCHAR(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'TABULKA NALOŽENÍ POVOZU, PPP h1641.';

INSERT IGNORE INTO povoz_nalozeni (narocnost_do, procento, teren) VALUES
(10,  100, 'rovina, velmi dobrá cesta'),
(12,   90, 'mírné zvlnění, méně kvalitní cesta'),
(15,   80, 'pahorkatina, nekvalitní cesta'),
(20,   65, 'vrchovina, pevný terén mimo cestu'),
(30,   30, 'hory, velmi těžko sjízdný terén'),
(999,   0, 'velehory (více než 30) — nelze projet');

-- Kontrola
SELECT
  (SELECT COUNT(*) FROM rychlost_chuze) AS rychlost_chuze,
  (SELECT COUNT(*) FROM rychlost_behu) AS rychlost_behu,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jizdni_zvirata' AND COLUMN_NAME = 'nosnost_tah_mn') AS sloupec_nosnost_tah,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
     AND ((TABLE_NAME = 'svet' AND COLUMN_NAME IN ('auto_naklad', 'auto_pohyblivost', 'auto_unava'))
       OR (TABLE_NAME = 'postavy' AND COLUMN_NAME = 'pohyblivost_rucne'))) AS sloupce_pohyb,
  (SELECT COUNT(*) FROM povozy) AS povozy,
  (SELECT COUNT(*) FROM povoz_nalozeni) AS povoz_nalozeni;
