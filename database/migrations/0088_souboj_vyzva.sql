-- VTT — souboj, tah v2, etapa 2: výzvy k hodu (útok, obrana, hod proti pasti).
--
-- Tabulka souboj_vyzva drží čekající hod, který vidí všichni u stolu: druh (utok / obrana / past), stav
-- (ceka / hozeno / hotovo / zruseno), kdo hází, protivník a JSON s čísly (základ, opravy, hody, výsledek).
-- Hody se nehází samy — hráč (nebo PJ za něj) klikne na „Hodit“ nebo zapíše vlastní hod
-- (docs/navrh-tahu-v2.md, includes/vtt_souboj_utok.php).
--
-- Samé DDL — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes
-- phpMyAdmin (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md. Bez tabulky se útok jen zapíše do logu.

CREATE TABLE IF NOT EXISTS souboj_vyzva (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mapa_id INT UNSIGNED NOT NULL,
  druh VARCHAR(10) NOT NULL COMMENT 'utok / obrana / past',
  stav VARCHAR(10) NOT NULL DEFAULT 'ceka' COMMENT 'ceka (čeká na hod) / hozeno / hotovo / zruseno',
  token_id INT UNSIGNED NOT NULL COMMENT 'kdo hází',
  cil_token_id INT UNSIGNED NULL COMMENT 'protivník (u útoku obránce, u obrany útočník)',
  rodic_id INT UNSIGNED NULL COMMENT 'obrana patří k útoku',
  data MEDIUMTEXT NOT NULL COMMENT 'JSON: složky čísla, hody, výsledek',
  vytvoreno TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY mapa_stav (mapa_id, stav),
  KEY token_id (token_id),
  KEY cil_token_id (cil_token_id),
  KEY rodic_id (rodic_id),
  CONSTRAINT fk_souboj_vyzva_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE CASCADE,
  CONSTRAINT fk_souboj_vyzva_token FOREIGN KEY (token_id) REFERENCES tokeny (id) ON DELETE CASCADE,
  CONSTRAINT fk_souboj_vyzva_cil FOREIGN KEY (cil_token_id) REFERENCES tokeny (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
