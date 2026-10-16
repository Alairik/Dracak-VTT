-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, admin účet, vybraná databáze Dračáku).
--
-- Deník nestvůry (malý): čísla pro boj (útoky s ÚČ a útočností, OČ, OZ, velikost, odolnost), která PJ jednou
-- doplní u nestvůry a platí pro všechny nestvůry stejného druhu v jeho světě. Jedna nová tabulka, nic se nemění
-- ani nemaže; jde spustit opakovaně a v libovolném stavu databáze. Bez ní boj bere čísla jen z textu bestiáře
-- a PJ je doplňuje u každého hodu zvlášť.
--
-- Sem se zapisují jen příkazy, které migrate.php pod DML-only účtem neumí (CREATE, ALTER, DROP…).
-- Každý blok musí jít spustit opakovaně a v libovolném stavu DB (kontrola přes information_schema
-- + PREPARE/EXECUTE, CREATE TABLE IF NOT EXISTS, INSERT IGNORE) a končit kontrolním SELECTem.

CREATE TABLE IF NOT EXISTS nestvura_staty_sveta (
  svet_id INT UNSIGNED NOT NULL,
  nestvura_id INT UNSIGNED NOT NULL,
  data MEDIUMTEXT NOT NULL COMMENT 'JSON: utoky [{nazev, uc, utocnost}], oc, oz, velikost, odolnost',
  upraveno TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (svet_id, nestvura_id),
  KEY nestvura_id (nestvura_id),
  CONSTRAINT fk_nestvura_staty_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE,
  CONSTRAINT fk_nestvura_staty_nestvura FOREIGN KEY (nestvura_id) REFERENCES nestvury (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontrola: musí vyjít 1 (tabulka existuje) a 4 (počet sloupců).
SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_staty_sveta') AS tabulka_existuje,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nestvura_staty_sveta') AS pocet_sloupcu;
