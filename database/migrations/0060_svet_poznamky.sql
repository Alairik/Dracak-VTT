-- Sdílené poznámky ve světě (viz konverzace) — libovolný člen světa
-- (PJ i hráč) napíše poznámku a sám si vybere, KOMU KONKRÉTNÍMU ji
-- nasdílí (ne "všem hráčům" jako jeden vypínač) — typický usecase je
-- jeden zapisovatel, co se rozhodne podělit o info jen s někým. PJ
-- světa (svet.pj_ucet_id) NENÍ automaticky příjemce žádné poznámky —
-- je to jen další možný příjemce stejně jako kterýkoliv hráč, takže
-- jde udělat i čistě hráčské tajemství, o kterém se PJ nedozví (viz
-- includes/vtt_poznamky.php — jedinou výjimkou s dohledem je skutečný
-- ucty.role='admin', ne PJ světa).
CREATE TABLE IF NOT EXISTS svet_poznamky (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  svet_id INT UNSIGNED NOT NULL,
  autor_ucet_id INT UNSIGNED NOT NULL,
  text TEXT NOT NULL,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY svet_id (svet_id),
  KEY autor_ucet_id (autor_ucet_id),
  CONSTRAINT fk_svet_poznamky_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE,
  CONSTRAINT fk_svet_poznamky_autor FOREIGN KEY (autor_ucet_id) REFERENCES ucty (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Komu konkrétnímu je poznámka nasdílená — žádný jiný řádek = vidí ji
-- jen autor (a skutečný admin).
CREATE TABLE IF NOT EXISTS svet_poznamka_sdileni (
  poznamka_id INT UNSIGNED NOT NULL,
  ucet_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (poznamka_id, ucet_id),
  KEY ucet_id (ucet_id),
  CONSTRAINT fk_sdileni_poznamka FOREIGN KEY (poznamka_id) REFERENCES svet_poznamky (id) ON DELETE CASCADE,
  CONSTRAINT fk_sdileni_ucet FOREIGN KEY (ucet_id) REFERENCES ucty (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
