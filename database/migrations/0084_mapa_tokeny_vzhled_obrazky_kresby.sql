-- Mapa (blok A plánu "náhrada Owlbear Rodeo", docs/stav-projektu.md):
--  * tokeny: otočení (smer ve stupních, 0 = doprava), velikost v sázích, barva,
--    zámek polohy, vlastní název a obrázek; nový typ tokenu "obecny" (NPC bez
--    statistik, překážka, předmět)
--  * svet_obrazky: knihovna obrázků světa (portréty tokenů)
--  * mapa_kresby: kreslení na mapu (čáry, šipky, tvary, text)
--  * svet_udalosti.typ: z pevného výčtu na VARCHAR, aby další druhy událostí
--    (otočení tokenu, kresba…) už nevyžadovaly ruční DDL. Hodnoty zůstanou.
--
-- Všechno je DDL — web účet ho neprovede, ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql). Jeden příkaz na sloupec: fallback v
-- migrate.php ověřuje jen první ADD COLUMN příkazu.

ALTER TABLE tokeny ADD COLUMN smer SMALLINT NOT NULL DEFAULT 0 COMMENT 'směr čelem ve stupních, 0 = doprava, po směru hodinových ručiček' AFTER z_poradi;
ALTER TABLE tokeny ADD COLUMN velikost_sahu DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT 'průměr tokenu v sázích (1 = jeden hex/čtverec)' AFTER smer;
ALTER TABLE tokeny ADD COLUMN barva VARCHAR(7) NULL COMMENT '#rrggbb, NULL = výchozí podle typu' AFTER velikost_sahu;
ALTER TABLE tokeny ADD COLUMN zamceno TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = polohu nejde měnit, dokud PJ neodemkne' AFTER barva;
ALTER TABLE tokeny ADD COLUMN nazev VARCHAR(100) NULL COMMENT 'vlastní název (obecný token, přejmenovaná nestvůra)' AFTER zamceno;
ALTER TABLE tokeny ADD COLUMN obrazek_id INT UNSIGNED NULL COMMENT 'obrázek z knihovny světa (svet_obrazky)' AFTER nazev;
ALTER TABLE tokeny MODIFY COLUMN typ_entity ENUM('postava','nestvura_instance','obecny') NOT NULL;
ALTER TABLE svet_udalosti MODIFY COLUMN typ VARCHAR(40) NOT NULL;

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
