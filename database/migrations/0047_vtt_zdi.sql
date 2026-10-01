-- VTT vrstva — zdi (LoS/pohyb), viz docs/vtt-datovy-model-navrh-v1.md a
-- diskuze v CLAUDE.md o VTT. Jednotný model úsečka+šířka (ne enum
-- hrana/střed) — tenká zeď blokuje jen přechod přes čáru (plot na
-- hraně hexu/čtverce, postava může zůstat na sousední buňce), silná
-- zeď (šířka srovnatelná s velikostí buňky) geometricky pokryje celou
-- buňku, kterou čára prochází středem.
--
-- x1/y1/x2/y2/sirka_px jsou FLOAT (ne INT) — magnetické body na hex
-- gridu (vrchol/střed hrany/střed buňky) vycházejí z sqrt(3)*velikost,
-- nejsou tedy celočíselné; zaokrouhlení na px by snapping dělalo
-- nepřesným. Souřadnice i šířka se ukládají v px (stejně jako
-- tokeny.x/y, mapy.grid_velikost_px) — převod na sáhy je jen
-- prezentační vrstva v mapa.php, stejný princip jako už existující
-- ruler.
--
-- blokuje_pohyb/blokuje_vystrel odděleně (okno blokuje jen výstřel,
-- nízký plot jen pohyb) — skutečné vynucení (blokování tahu/střelby)
-- je samostatná budoucí fáze, tahle migrace zakládá jen datový model
-- pro nástroj na kreslení a vykreslení.
--
-- Samé CREATE TABLE = čistá DDL, web účet (DML-only na Wedosu) ji
-- nikdy neprovede — MUSÍ se spustit ručně přes phpMyAdmin (admin účet)
-- a zapsat do migrace_log, viz CLAUDE.md.
--
-- ALTER na konci jen rozšiřuje ENUM `typ` o 'zed_pridana'/'zed_smazana'/
-- 'mapa_grid_zmena' — plný výčet navazuje na poslední MODIFY v
-- 0041_vtt_inventar_a_loot.sql (17 hodnot), žádná stávající hodnota
-- nemizí.

CREATE TABLE IF NOT EXISTS zdi (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mapa_id INT UNSIGNED NOT NULL,
  x1 FLOAT NOT NULL,
  y1 FLOAT NOT NULL,
  x2 FLOAT NOT NULL,
  y2 FLOAT NOT NULL,
  sirka_px FLOAT NOT NULL DEFAULT 6,
  blokuje_pohyb TINYINT(1) NOT NULL DEFAULT 1,
  blokuje_vystrel TINYINT(1) NOT NULL DEFAULT 1,
  viditelna_hracum TINYINT(1) NOT NULL DEFAULT 1,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY mapa_id (mapa_id),
  CONSTRAINT fk_zdi_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE svet_udalosti MODIFY COLUMN typ ENUM(
  'token_presun','token_pridan','token_smazan',
  'kostka_hod','efekt_aplikovan','efekt_konci',
  'chat','mapa_bod_pridan','mapa_bod_odhalen','aktivni_mapa_zmena',
  'hp_zmena','ping','iniciativa_hozena','kolo_nove','tah_zmena',
  'predmet_loot','predmet_pouzit',
  'zed_pridana','zed_smazana','mapa_grid_zmena'
) NOT NULL;
