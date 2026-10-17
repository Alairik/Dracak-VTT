-- Efekty s opravdovou mechanikou — struktura. Samé přidávání (sloupce, tabulka), nic se nemění ani nemaže.
-- Sloupce efektů: stav mechaniky (auto / cast / pj / past / ceka), výchozí trvání v kolech, zda se kolo nasazení
-- počítá do trvání (kouzla, h1626 b10848), co dělá opakované nasazení (obnov / secti).
-- Tabulka efekt_slozky: co přesně efekt v boji dělá (zdvojí akce, bonus k iniciativě / ÚČ / OČ, nemůže jednat, tik životů…).
-- Nasazený efekt si pamatuje kolo nasazení a kostky převzaté ze zdroje (lektvar Regenerace).
--
-- Samé DDL — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md. Data efektů navazují v 0092.

ALTER TABLE efekty ADD COLUMN mechanika_stav VARCHAR(10) NOT NULL DEFAULT 'pj' COMMENT 'auto / cast / pj / past / ceka';
ALTER TABLE efekty ADD COLUMN trvani_kol SMALLINT UNSIGNED NULL COMMENT 'výchozí trvání v kolech (NULL = bez limitu, zadá PJ)';
ALTER TABLE efekty ADD COLUMN kolo_se_nepocita TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = kolo nasazení se do trvání nepočítá';
ALTER TABLE efekty ADD COLUMN opakovani VARCHAR(10) NOT NULL DEFAULT 'obnov' COMMENT 'obnov / secti';

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

ALTER TABLE aktivni_efekty ADD COLUMN nasazeno_kolo SMALLINT UNSIGNED NULL COMMENT 'číslo kola souboje, ve kterém byl efekt nasazen';
ALTER TABLE aktivni_efekty ADD COLUMN kostky VARCHAR(20) NULL COMMENT 'kostky převzaté ze zdroje, např. 1k6+5';
