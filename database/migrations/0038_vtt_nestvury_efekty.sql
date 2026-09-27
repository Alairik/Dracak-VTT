-- VTT vrstva, druhá kostra — bojová automatizace (viz
-- docs/vtt-datovy-model-navrh-v1.md): instance nestvůr na mapě a
-- aktivní efekty (buff/debuff/DoT/HoT) navázané na postavu i nestvůru.
--
-- Stejné omezení jako 0037: samé CREATE TABLE, web účet (DML-only) je
-- neprovede, migrate.php na tom krok shodí (očekávané) — MUSÍ se pustit
-- ručně přes phpMyAdmin (admin účet) + zapsat do migrace_log.
--
-- ALTER TABLE svet_udalosti na konci rozšiřuje ENUM `typ` o 'hp_zmena'
-- (dosud pokrýval jen efekt_aplikovan/efekt_konci, ne přímou úpravu
-- života PJ/hráčem). Jde o rozšíření množiny povolených hodnot, ne o
-- destruktivní změnu — žádný existující řádek/hodnota se tím neztrácí.

CREATE TABLE IF NOT EXISTS nestvura_instance (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mapa_id INT UNSIGNED NOT NULL,
  nestvura_id INT UNSIGNED NOT NULL,
  nazev_instance VARCHAR(150) DEFAULT NULL,
  aktualni_hp SMALLINT NOT NULL DEFAULT 0,
  max_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY mapa_id (mapa_id),
  KEY nestvura_id (nestvura_id),
  CONSTRAINT fk_nestvura_instance_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_nestvura_instance_nestvura FOREIGN KEY (nestvura_id) REFERENCES nestvury (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Polymorfní (typ_entity, entita_id) jako u tokeny — váže se na entitu,
-- ne na token, protože efekt musí přežít i zavření/znovuotevření scény.
CREATE TABLE IF NOT EXISTS aktivni_efekty (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  typ_entity ENUM('postava','nestvura_instance') NOT NULL,
  entita_id INT UNSIGNED NOT NULL,
  efekt_id INT UNSIGNED NOT NULL,
  zbyva_kol SMALLINT DEFAULT NULL,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY entita (typ_entity, entita_id),
  KEY efekt_id (efekt_id),
  CONSTRAINT fk_aktivni_efekty_efekt FOREIGN KEY (efekt_id) REFERENCES efekty (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE svet_udalosti MODIFY COLUMN typ ENUM(
  'token_presun','token_pridan','token_smazan',
  'kostka_hod','efekt_aplikovan','efekt_konci',
  'chat','mapa_bod_pridan','mapa_bod_odhalen','aktivni_mapa_zmena',
  'hp_zmena'
) NOT NULL;
