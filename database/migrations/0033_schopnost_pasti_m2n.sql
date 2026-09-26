-- Řeší poslední otevřený bod: "Trollobijecký útok" (schopnost id 51)
-- svazuje dohromady mechaniky Skoku (past id 7) a Zbraňového
-- specialisty (past id 8); "Líčení pastí" (schopnost id 80) popisuje
-- 6 typů pastí, z toho 3 (Drtivá/Šipková/Výbušná, past id 11/12/13)
-- mají vlastní past. zvlastni_schopnosti.past_id je ale jen JEDNO FK
-- — jedna schopnost nemůže odkazovat na víc pastí najednou.
--
-- Řešení: nová M:N vazební tabulka schopnost_pasti vedle stávajícího
-- past_id sloupce (ten zůstává beze změny, nic ho nenahrazuje —
-- rozšíření, ne redesign). Existující 1:1 vazby (18 schopností s
-- past_id) se navíc zrcadlí i sem, aby šlo číst "všechny pasti dané
-- schopnosti" jedním dotazem bez ohledu na to, kolik jich má.
--
-- CREATE TABLE = DDL, web účet (DML-only na Wedosu) ho nikdy neprovede
-- a migrate.php by na něm shodil celou dávku i pro migrace za ním —
-- MUSÍ se spustit ručně přes phpMyAdmin (admin účet) PŘED nasazením
-- téhle větve, a zapsat do migrace_log. Viz CLAUDE.md.

CREATE TABLE IF NOT EXISTS schopnost_pasti (
  schopnost_id INT UNSIGNED NOT NULL,
  past_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (schopnost_id, past_id),
  KEY past_id (past_id),
  CONSTRAINT schopnost_pasti_ibfk_1 FOREIGN KEY (schopnost_id) REFERENCES zvlastni_schopnosti (id) ON DELETE CASCADE,
  CONSTRAINT schopnost_pasti_ibfk_2 FOREIGN KEY (past_id) REFERENCES pasti (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zrcadlení existujících 1:1 vazeb (past_id sloupec zůstává, toto je jen doplněk)
INSERT IGNORE INTO schopnost_pasti (schopnost_id, past_id)
SELECT id, past_id FROM zvlastni_schopnosti WHERE past_id IS NOT NULL;

-- Nové M:N vazby, které do jednoho past_id sloupce nešly
INSERT IGNORE INTO schopnost_pasti (schopnost_id, past_id) VALUES (51, 7), (51, 8);
INSERT IGNORE INTO schopnost_pasti (schopnost_id, past_id) VALUES (80, 11), (80, 12), (80, 13);
