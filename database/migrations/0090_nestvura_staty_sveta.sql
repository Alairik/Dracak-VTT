-- VTT — malý deník nestvůry: čísla pro boj (útoky s ÚČ a útočností, OČ, OZ, velikost, odolnost), která PJ jednou doplní
-- u nestvůry a platí pro všechny nestvůry stejného druhu v jeho světě (hra/api/nestvura_denik.php, includes/vtt_nestvury.php).
-- Bestiář drží ÚČ a OČ jako volný text a u řady nestvůr jen jako vzorec bez výsledku („+1 + síla zbraně“); co se z textu
-- nespočítá, PJ doplní tady a hody to berou samy. Bez tabulky se čísla berou jen z textu bestiáře a PJ je doplňuje u hodu.
--
-- Samé DDL — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes
-- phpMyAdmin (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md.

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
