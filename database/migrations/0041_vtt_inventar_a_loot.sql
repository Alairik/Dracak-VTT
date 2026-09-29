-- VTT vrstva — inventář postav a kořist z mrtvých nestvůr (viz CLAUDE.md,
-- odstavec "Proč DB vůbec existuje" a docs/vtt-datovy-model-navrh-v1.md).
--
-- Typované tabulky per entita+katalog (postava_predmety/postava_lektvary/
-- postava_zna_kouzlo) — stejný vzor jako už existující kouzlo_efekty/
-- lektvar_efekty/predmet_efekty, ne jedna polymorfní mega-tabulka.
-- postava_zna_kouzlo nemá mnozstvi — znalost kouzla se nespotřebovává.
--
-- nestvura_instance_vybava je JEDINÁ záměrná výjimka z výše: nejde založit
-- FK na tři různé katalogy (predmety/lektvary/kouzla) najednou, takže drží
-- typ_polozky+polozka_id polymorfně bez DB-level FK na položku — přesně
-- stejný vzor jako aktivni_efekty (migrace 0038_vtt_nestvury_efekty.sql).
-- Je to lehká výbava JEDNÉ bojové instance nestvůry (PJ ji ručně nastaví
-- při pokládání tokenu), ne trvalý inventář celého bestiářového druhu.
--
-- Samé CREATE TABLE = čistá DDL, web účet (DML-only na Wedosu) ji
-- neprovede, migrate.php na tomhle kroku shodí celý deploy (očekávané) —
-- MUSÍ se spustit ručně přes phpMyAdmin (admin účet) a zapsat do
-- migrace_log, viz CLAUDE.md.
--
-- ALTER TABLE svet_udalosti na konci rozšiřuje ENUM `typ` o
-- 'predmet_loot'/'predmet_pouzit' — čistě rozšíření výčtu, žádná
-- existující hodnota nemizí. Tahle migrace vznikla paralelně s
-- 0044_vtt_iniciativa.sql (izolovaný worktree, neviděl tamní 3 nové
-- hodnoty) — při integraci (mimo tenhle soubor, ručně) opraveno na
-- plný výčet PO 0044: 15 hodnot končících 'hp_zmena','ping',
-- 'iniciativa_hozena','kolo_nove','tah_zmena', sem přidány jen
-- 'predmet_loot'/'predmet_pouzit' navrch. Bez týhle opravy by tenhle
-- ALTER tiše smazal tři hodnoty přidané 0044 — MODIFY COLUMN nahrazuje
-- celý výčet, ne jen přidává.

CREATE TABLE IF NOT EXISTS postava_predmety (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  postava_id INT UNSIGNED NOT NULL,
  predmet_id INT UNSIGNED NOT NULL,
  mnozstvi SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY postava_predmet (postava_id, predmet_id),
  KEY predmet_id (predmet_id),
  CONSTRAINT fk_postava_predmety_postava FOREIGN KEY (postava_id) REFERENCES postavy (id) ON DELETE CASCADE,
  CONSTRAINT fk_postava_predmety_predmet FOREIGN KEY (predmet_id) REFERENCES predmety (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS postava_lektvary (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  postava_id INT UNSIGNED NOT NULL,
  lektvar_id INT UNSIGNED NOT NULL,
  mnozstvi SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY postava_lektvar (postava_id, lektvar_id),
  KEY lektvar_id (lektvar_id),
  CONSTRAINT fk_postava_lektvary_postava FOREIGN KEY (postava_id) REFERENCES postavy (id) ON DELETE CASCADE,
  CONSTRAINT fk_postava_lektvary_lektvar FOREIGN KEY (lektvar_id) REFERENCES lektvary (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS postava_zna_kouzlo (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  postava_id INT UNSIGNED NOT NULL,
  kouzlo_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY postava_kouzlo (postava_id, kouzlo_id),
  KEY kouzlo_id (kouzlo_id),
  CONSTRAINT fk_postava_zna_kouzlo_postava FOREIGN KEY (postava_id) REFERENCES postavy (id) ON DELETE CASCADE,
  CONSTRAINT fk_postava_zna_kouzlo_kouzlo FOREIGN KEY (kouzlo_id) REFERENCES kouzla (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nestvura_instance_vybava (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nestvura_instance_id INT UNSIGNED NOT NULL,
  typ_polozky ENUM('predmet','lektvar','kouzlo') NOT NULL,
  polozka_id INT UNSIGNED NOT NULL,
  mnozstvi SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY nestvura_instance_id (nestvura_instance_id),
  KEY polozka (typ_polozky, polozka_id),
  CONSTRAINT fk_nestvura_instance_vybava_ni FOREIGN KEY (nestvura_instance_id) REFERENCES nestvura_instance (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE svet_udalosti MODIFY COLUMN typ ENUM(
  'token_presun','token_pridan','token_smazan',
  'kostka_hod','efekt_aplikovan','efekt_konci',
  'chat','mapa_bod_pridan','mapa_bod_odhalen','aktivni_mapa_zmena',
  'hp_zmena','ping','iniciativa_hozena','kolo_nove','tah_zmena',
  'predmet_loot','predmet_pouzit'
) NOT NULL;
