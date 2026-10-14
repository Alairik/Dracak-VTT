-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
-- Vlož CELÝ obsah najednou do karty SQL a spusť. Lze spustit opakovaně — každá část
-- se provede jen tehdy, když ještě není provedená (jinak vypíše "už bylo provedeno").
-- Předchozí bloky (migrace 0065–0079) jsou na produkci provedené.
-- Na konci je kontrola: má vyjít  zbylo_starych = 0, sloupce_vybaveni = 2, typ_vybaveni = 1, tabulky_kontejneru = 2.

-- 1) Smazání tří starých, nepoužívaných tabulek o únavě z migrace 0008. Neodpovídají
--    pravidlům (jiný model únavy) a nic je nečte. Schváleno uživatelem 2026-10-14.
DROP TABLE IF EXISTS stupne_unavy;
DROP TABLE IF EXISTS narocnost_akci_unava;
DROP TABLE IF EXISTS odstraneni_unavy;

-- 2) 0080: běžné vybavení z ceníku — nový typ předmětu "vybaveni", skupina ceníku a kapacita kontejneru
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety MODIFY COLUMN typ ENUM(''zbran'',''zbroj'',''surovina'',''artefakt'',''vybaveni'') NOT NULL',
  'SELECT ''0080 typ vybaveni už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'typ' AND COLUMN_TYPE LIKE '%vybaveni%');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety ADD COLUMN kategorie_vybaveni VARCHAR(40) NULL COMMENT ''skupina v ceníku (Vaky, Oblečení, Alkohol…), jen typ vybaveni'' AFTER typ',
  'SELECT ''0080 kategorie_vybaveni už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'kategorie_vybaveni');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE predmety ADD COLUMN kapacita_mn DECIMAL(8,2) NULL COMMENT ''kolik mn se do předmětu vejde (vak, truhla, soudek…); NULL = není kontejner'' AFTER vaha',
  'SELECT ''0080 kapacita_mn už bylo provedeno'' AS info')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'kapacita_mn');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) 0081: kontejnery postav (vak/truhla = jeden řádek) a jejich obsah
CREATE TABLE IF NOT EXISTS postava_kontejnery (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    postava_id  INT UNSIGNED NOT NULL,
    predmet_id  INT UNSIGNED NOT NULL,
    nazev       VARCHAR(80) NULL COMMENT 'vlastní popisek, např. "vak s jídlem"',
    nosic       ENUM('postava','zvire') NOT NULL DEFAULT 'postava',
    vytvoreno   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_postava_kontejnery_postava (postava_id),
    KEY idx_postava_kontejnery_predmet (predmet_id),
    CONSTRAINT fk_postava_kontejnery_postava FOREIGN KEY (postava_id) REFERENCES postavy (id) ON DELETE CASCADE,
    CONSTRAINT fk_postava_kontejnery_predmet FOREIGN KEY (predmet_id) REFERENCES predmety (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kontejner_obsah (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontejner_id INT UNSIGNED NOT NULL,
    predmet_id   INT UNSIGNED NOT NULL,
    mnozstvi     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_kontejner_obsah (kontejner_id, predmet_id),
    KEY idx_kontejner_obsah_predmet (predmet_id),
    CONSTRAINT fk_kontejner_obsah_kontejner FOREIGN KEY (kontejner_id) REFERENCES postava_kontejnery (id) ON DELETE CASCADE,
    CONSTRAINT fk_kontejner_obsah_predmet FOREIGN KEY (predmet_id) REFERENCES predmety (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontrola
SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('stupne_unavy', 'narocnost_akci_unava', 'odstraneni_unavy')) AS zbylo_starych,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME IN ('kategorie_vybaveni', 'kapacita_mn')) AS sloupce_vybaveni,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predmety' AND COLUMN_NAME = 'typ' AND COLUMN_TYPE LIKE '%vybaveni%') AS typ_vybaveni,
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('postava_kontejnery', 'kontejner_obsah')) AS tabulky_kontejneru;
