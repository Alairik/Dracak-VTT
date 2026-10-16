-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
--
-- Souboj, tah v2 — etapa 2: výzvy k hodu (útok, obrana, hod proti pasti). Jedna nová tabulka,
-- nic se nemění ani nemaže; jde spustit opakovaně a v libovolném stavu databáze.
-- Bez ní souboj funguje jako dosud (útok se jen zapíše do logu, bez hodů).
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

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

-- Kontrola: musí vyjít 1 (tabulka existuje) a 9 (počet sloupců).
SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'souboj_vyzva') AS tabulka_existuje,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'souboj_vyzva') AS pocet_sloupcu;
