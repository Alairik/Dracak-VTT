-- VTT — souboj, krok 1 (docs/navrh-souboje-v1.md): fáze kola, půlky kola, překvapení a
-- účastníci souboje s úmysly.
--
-- kolo_stav.faze      'umysly' (hráči hlásí, co budou dělat) -> 'iniciativa' (hody) -> 'kolo'
--                     (tahy). Řádky vzniklé starým způsobem (první hod iniciativy bez
--                     zahájení souboje) zůstanou ve fázi 'kolo'.
-- kolo_stav.polovina  Kolo se dělí na dvě půlky (domácí pravidlo, content/pravidla-hrac.html
--                     b10819): v 1. půlce každý podle iniciativy odehraje větší půlku akcí,
--                     ve 2. zbytek.
-- kolo_stav.prekvapeni  'zadne' / 'druzina' / 'nepratele' / 'obe' — kdo je v 1. kole překvapen
--                     (−6 k iniciativě, tabulka iniciativa_bonusy).
-- kolo_ucastnik       Kdo se souboje účastní: na které straně stojí (překvapení se počítá po
--                     stranách) a co v tomto kole zamýšlí (pro bonusy k iniciativě).
--
-- Samé DDL — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes
-- phpMyAdmin (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md.

ALTER TABLE kolo_stav ADD COLUMN faze VARCHAR(12) NOT NULL DEFAULT 'kolo' COMMENT 'umysly / iniciativa / kolo' AFTER aktivni_token_id;
ALTER TABLE kolo_stav ADD COLUMN polovina TINYINT NOT NULL DEFAULT 1 COMMENT 'půlka kola: 1 nebo 2' AFTER faze;
ALTER TABLE kolo_stav ADD COLUMN prekvapeni VARCHAR(10) NOT NULL DEFAULT 'zadne' COMMENT 'zadne / druzina / nepratele / obe' AFTER polovina;

CREATE TABLE IF NOT EXISTS kolo_ucastnik (
  mapa_id INT UNSIGNED NOT NULL,
  token_id INT UNSIGNED NOT NULL,
  strana VARCHAR(10) NOT NULL DEFAULT 'druzina' COMMENT 'druzina / nepratele',
  umysl VARCHAR(16) NULL COMMENT 'kouzleni / strelba / utok / pohyb / jina / cekani / utek',
  umysl_cil_token_id INT UNSIGNED NULL,
  PRIMARY KEY (mapa_id, token_id),
  KEY token_id (token_id),
  KEY umysl_cil_token_id (umysl_cil_token_id),
  CONSTRAINT fk_kolo_ucastnik_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_kolo_ucastnik_token FOREIGN KEY (token_id) REFERENCES tokeny (id) ON DELETE CASCADE,
  CONSTRAINT fk_kolo_ucastnik_cil FOREIGN KEY (umysl_cil_token_id) REFERENCES tokeny (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
