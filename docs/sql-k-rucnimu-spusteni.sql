-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
--
-- Efekty s opravdovou mechanikou: u každého efektu se uloží, CO přesně dělá (čísla, akce, kostky),
-- jak dlouho trvá a jestli ho boj vyhodnotí sám, nebo ho musí posoudit PJ. Dosud byly efekty jen text
-- (název, popis, „+2 Iniciativa“ jako věta) a boj je ignoroval. Samé přidávání — žádný sloupec ani
-- řádek se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
-- Bez tohohle SQL boj dál funguje jako dosud (efekty jsou jen štítky).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

-- 1) Efekt: jak moc ho boj vyhodnotí (mechanika_stav), výchozí trvání v kolech, zda se kolo nasazení
--    počítá do trvání (kouzla: ne, h1626) a co dělá opakované nasazení (obnov = delší trvání vyhrává,
--    secti = trvání se sčítá, účinek ne — Rychlost, Protoplazma).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE efekty
     ADD COLUMN mechanika_stav VARCHAR(10) NOT NULL DEFAULT ''pj'' COMMENT ''auto / cast / pj / past / ceka'',
     ADD COLUMN trvani_kol SMALLINT UNSIGNED NULL COMMENT ''výchozí trvání v kolech (NULL = bez limitu, zadá PJ)'',
     ADD COLUMN kolo_se_nepocita TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = kolo nasazení se do trvání nepočítá'',
     ADD COLUMN opakovani VARCHAR(10) NOT NULL DEFAULT ''obnov'' COMMENT ''obnov / secti''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'efekty' AND COLUMN_NAME = 'mechanika_stav');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Složky mechaniky efektu: jeden řádek = jedna věc, kterou boj udělá (druh + číslo / kostky).
CREATE TABLE IF NOT EXISTS efekt_slozky (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  efekt_id INT UNSIGNED NOT NULL,
  druh VARCHAR(30) NOT NULL COMMENT 'akce_zdvojit / akce_pulit / akce_zmena / ini_radek / ini_bonus / uc_bonus / oc_bonus / beh_hexu / nemuze_jednat / nemuze_branit / nehybny / tik_zivoty',
  hodnota SMALLINT NULL COMMENT 'číslo složky (bonus, počet akcí, počet hexů; u tik_zivoty −1 = ubírá, 1 = léčí)',
  pocet_kostek TINYINT UNSIGNED NULL,
  typ_kostky ENUM('k3','k4','k6','k8','k10','k12','k20','k100') NULL,
  pevny_bonus SMALLINT NULL,
  parametr VARCHAR(60) NULL COMMENT 'např. název řádku Tabulky bonusů a postihů k iniciativě',
  poznamka VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY efekt_id (efekt_id),
  CONSTRAINT fk_efekt_slozky_efekt FOREIGN KEY (efekt_id) REFERENCES efekty (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Nasazený efekt: v jakém kole souboje byl nasazen (kolo nasazení se u kouzel nepočítá do trvání)
--    a kostky převzaté z lektvaru / kouzla (Regenerace „dle kostek lektvaru za kolo“).
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE aktivni_efekty
     ADD COLUMN nasazeno_kolo SMALLINT UNSIGNED NULL COMMENT ''číslo kola souboje, ve kterém byl efekt nasazen'',
     ADD COLUMN kostky VARCHAR(20) NULL COMMENT ''kostky převzaté ze zdroje, např. 1k6+5''',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aktivni_efekty' AND COLUMN_NAME = 'nasazeno_kolo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kontrola: musí vyjít 4, 1, 8 a 2 (nové sloupce efektů, tabulka složek, její sloupce, nové sloupce nasazených efektů).
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'efekty'
     AND COLUMN_NAME IN ('mechanika_stav', 'trvani_kol', 'kolo_se_nepocita', 'opakovani')) AS sloupce_efektu,
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'efekt_slozky') AS tabulka_slozek,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'efekt_slozky') AS sloupce_slozek,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aktivni_efekty'
     AND COLUMN_NAME IN ('nasazeno_kolo', 'kostky')) AS sloupce_nasazenych;
