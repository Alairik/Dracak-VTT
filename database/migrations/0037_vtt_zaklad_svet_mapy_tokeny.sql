-- VTT vrstva — základní kostra (viz docs/vtt-datovy-model-navrh-v1.md).
-- Prvních 6 tabulek z návrhu: svet, svet_hraci, mapy, postavy, tokeny,
-- svet_udalosti. Zbytek (zdi, mlha_valky, mapa_body, svet_poznamky,
-- nestvura_instance, aktivni_efekty, postava_predmety) přijde v dalších,
-- menších migracích, až bude tahle kostra ověřená v provozu.
--
-- Samé CREATE TABLE = čistá DDL, web účet (DML-only na Wedosu) ji nikdy
-- neprovede a migrate.php na ní shodí celý deploy krok (očekávané, ne
-- chyba). MUSÍ se spustit ručně přes phpMyAdmin (admin účet) a zapsat do
-- migrace_log — teprve pak `migrate.php` u dalších migrací tuhle
-- přeskočí. Viz CLAUDE.md.
--
-- svet.aktivni_mapa_id -> mapy.id je cyklická vazba (mapy.svet_id ukazuje
-- zpátky na svet), proto se FK constraint na ni přidává až ALTER TABLE
-- na konci souboru, ne přímo v CREATE TABLE svet.

CREATE TABLE IF NOT EXISTS svet (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nazev VARCHAR(150) NOT NULL,
  popis TEXT DEFAULT NULL,
  pj_ucet_id INT UNSIGNED NOT NULL,
  aktivni_mapa_id INT UNSIGNED DEFAULT NULL,
  posledni_shrnuti TEXT DEFAULT NULL,
  pripraveno_priste TEXT DEFAULT NULL,
  auto_hod_kostkou TINYINT(1) NOT NULL DEFAULT 0,
  auto_aplikace_efektu TINYINT(1) NOT NULL DEFAULT 0,
  auto_vyhodnoceni_pasti TINYINT(1) NOT NULL DEFAULT 0,
  auto_zranitelnost TINYINT(1) NOT NULL DEFAULT 0,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY pj_ucet_id (pj_ucet_id),
  CONSTRAINT fk_svet_pj FOREIGN KEY (pj_ucet_id) REFERENCES ucty (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS svet_hraci (
  svet_id INT UNSIGNED NOT NULL,
  ucet_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (svet_id, ucet_id),
  KEY ucet_id (ucet_id),
  CONSTRAINT fk_svet_hraci_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE,
  CONSTRAINT fk_svet_hraci_ucet FOREIGN KEY (ucet_id) REFERENCES ucty (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mapy (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  svet_id INT UNSIGNED NOT NULL,
  nazev VARCHAR(150) NOT NULL,
  typ_mapy ENUM('svet','zona') NOT NULL DEFAULT 'zona',
  obrazek_cesta VARCHAR(255) DEFAULT NULL,
  sirka_px INT UNSIGNED DEFAULT NULL,
  vyska_px INT UNSIGNED DEFAULT NULL,
  grid_velikost_px SMALLINT UNSIGNED DEFAULT NULL,
  grid_posun_x SMALLINT NOT NULL DEFAULT 0,
  grid_posun_y SMALLINT NOT NULL DEFAULT 0,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY svet_id (svet_id),
  CONSTRAINT fk_mapy_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS postavy (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  svet_id INT UNSIGNED NOT NULL,
  vlastnik_ucet_id INT UNSIGNED NOT NULL,
  nazev VARCHAR(150) NOT NULL,
  rasa_id SMALLINT UNSIGNED DEFAULT NULL,
  povolani_id SMALLINT UNSIGNED DEFAULT NULL,
  uroven TINYINT UNSIGNED NOT NULL DEFAULT 1,
  aktualni_hp SMALLINT NOT NULL DEFAULT 0,
  max_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  poznamky TEXT DEFAULT NULL,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY svet_id (svet_id),
  KEY vlastnik_ucet_id (vlastnik_ucet_id),
  KEY rasa_id (rasa_id),
  KEY povolani_id (povolani_id),
  CONSTRAINT fk_postavy_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE,
  CONSTRAINT fk_postavy_vlastnik FOREIGN KEY (vlastnik_ucet_id) REFERENCES ucty (id),
  CONSTRAINT fk_postavy_rasa FOREIGN KEY (rasa_id) REFERENCES rasy (id),
  CONSTRAINT fk_postavy_povolani FOREIGN KEY (povolani_id) REFERENCES povolani (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- entita_id je polymorfní (postava, nebo budoucí nestvura_instance) — bez
-- DB-level FK, řeší aplikační vrstva podle typ_entity.
CREATE TABLE IF NOT EXISTS tokeny (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mapa_id INT UNSIGNED NOT NULL,
  typ_entity ENUM('postava','nestvura_instance') NOT NULL,
  entita_id INT UNSIGNED NOT NULL,
  x INT NOT NULL DEFAULT 0,
  y INT NOT NULL DEFAULT 0,
  z_poradi SMALLINT NOT NULL DEFAULT 0,
  viditelny_hracum TINYINT(1) NOT NULL DEFAULT 1,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY mapa_id (mapa_id),
  KEY entita (typ_entity, entita_id),
  CONSTRAINT fk_tokeny_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only log — polling čte přes (svet_id, id), nikdy se needituje
-- ani nemaže (kromě budoucího runtime úklidu starých řádků, mimo tuhle
-- migraci). payload u token_presun musí nést i hodnotu PŘED změnou kvůli
-- kroku zpět (viz docs/vtt-datovy-model-navrh-v1.md).
CREATE TABLE IF NOT EXISTS svet_udalosti (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  svet_id INT UNSIGNED NOT NULL,
  mapa_id INT UNSIGNED DEFAULT NULL,
  typ ENUM(
    'token_presun','token_pridan','token_smazan',
    'kostka_hod','efekt_aplikovan','efekt_konci',
    'chat','mapa_bod_pridan','mapa_bod_odhalen','aktivni_mapa_zmena'
  ) NOT NULL,
  payload JSON DEFAULT NULL,
  ucet_id INT UNSIGNED DEFAULT NULL,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY svet_id_id (svet_id, id),
  KEY mapa_id (mapa_id),
  CONSTRAINT fk_udalosti_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE,
  CONSTRAINT fk_udalosti_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_udalosti_ucet FOREIGN KEY (ucet_id) REFERENCES ucty (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE svet ADD CONSTRAINT fk_svet_aktivni_mapa
  FOREIGN KEY (aktivni_mapa_id) REFERENCES mapy (id) ON DELETE SET NULL;
