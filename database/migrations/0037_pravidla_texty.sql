-- Volný text knihy pravidel, editovatelný přes tužku u KAŽDÉHO nadpisu/
-- odstavce v pravidla.php — ne jen u nadpisů napojených na existující DB
-- entity (kouzla, schopnosti...), ale i u čistě naratívního textu
-- (výklad, úvody kapitol) i u samostatných řádků/odstavců, co dnes žádný
-- edit-links.json/content-links.json vůbec nezná. Řádek vzniká LÍNĚ —
-- až první uložení daného kniha_id, ne dopředu pro celou knihu (řádově
-- tisíce nadpisů/odstavců napříč pravidla-hrac/pj/bestiar.html).
--
-- Napojení je PŘÍMÉ přes kniha_id ('hNNNN' u nadpisu / 'bNNNN' u
-- odstavce/řádku tabulky, id="..." v content/pravidla-*.html) — na
-- rozdíl od edit-links.json/content-links.json (napojení kouzel a
-- schopností na knihu, viz dracak_resolve_content_link) tu NENÍ potřeba
-- name-matching, protože kniha už má vlastní stabilní id nezávislé na
-- auto_increment DB záznamů. Proto UNIQUE přímo na kniha_id.
--
-- Registrováno jako entita 'pravidla_texty' v includes/entities.php
-- (row_owned => true) — zdědí stejnou vlastnickou/oprávnění logiku jako
-- kouzla/schopnosti/lektvary (dracak_can_edit/dracak_can_edit_row v
-- auth.php, granty přes ucet_opravneni v admin.php), žádný speciální
-- mechanismus bokem. Pole 'obsah' má u sebe v entities.php příznak
-- 'sanitize_html' => true — dracak_entity_save() ho proto vždy prožene
-- dracak_sanitize_html() (allow-list, entity_crud.php) bez ohledu na
-- to, jestli přišlo z editor.php nebo z pravidla-text-save.php, protože
-- jde o HTML, co se posílá přímo do prohlížeče DALŠÍCH čtenářů.
--
-- CREATE TABLE = DDL, web DB účet (DML-only na Wedosu) ho nikdy
-- neprovede — MUSÍ se po pushi spustit ručně přes phpMyAdmin (admin
-- účet) a zapsat do migrace_log, stejně jako 0033/0034/0035/0036.
-- Dokud se ručně nespustí, dracak_pravidla_texty_map() v
-- entity_crud.php to tiše přečká (try/catch, prázdná mapa) — stránka
-- pravidel se kvůli chybějící tabulce nerozbije, jen zatím nezobrazí
-- žádné přepsané texty.

CREATE TABLE IF NOT EXISTS pravidla_texty (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_by INT UNSIGNED DEFAULT NULL,
  kniha_id VARCHAR(20) NOT NULL,
  obsah MEDIUMTEXT,
  vytvoreno_v TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  upraveno_v TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY kniha_id (kniha_id),
  KEY created_by (created_by),
  CONSTRAINT pravidla_texty_ibfk_1 FOREIGN KEY (created_by) REFERENCES ucty (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
