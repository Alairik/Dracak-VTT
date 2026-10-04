-- Sdílené poznámky ve světě (viz konverzace) — libovolný člen světa
-- (PJ i hráč) napíše poznámku a sám si vybere, KOMU KONKRÉTNÍMU ji
-- nasdílí (ne "všem hráčům" jako jeden vypínač) — typický usecase je
-- jeden zapisovatel, co se rozhodne podělit o info jen s někým. PJ
-- světa (svet.pj_ucet_id) NENÍ automaticky příjemce žádné poznámky —
-- je to jen další možný příjemce stejně jako kterýkoliv hráč, takže
-- jde udělat i čistě hráčské tajemství, o kterém se PJ nedozví (viz
-- includes/vtt_poznamky.php — jedinou výjimkou s dohledem je skutečný
-- ucty.role='admin', ne PJ světa).
--
-- den_pri_vytvoreni: snapshot svet.aktualni_den_offset v okamžiku
-- vzniku poznámky — "před kolik HERNÍCH dní vznikla" se pak dopočítá
-- jako (svet.aktualni_den_offset − poznamka.den_pri_vytvoreni), NE z
-- reálného data vytvoreno (to je jen audit/pořadí, žádný herní smysl).
-- mapa_id: nepovinná vazba na konkrétní místo (mapu), ke kterému se
-- poznámka váže — viz konverzace "PJ poznámky mohou vést k místu".
CREATE TABLE IF NOT EXISTS svet_poznamky (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  svet_id INT UNSIGNED NOT NULL,
  autor_ucet_id INT UNSIGNED NOT NULL,
  text TEXT NOT NULL,
  den_pri_vytvoreni INT UNSIGNED NOT NULL DEFAULT 0,
  mapa_id INT UNSIGNED DEFAULT NULL,
  vytvoreno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY svet_id (svet_id),
  KEY autor_ucet_id (autor_ucet_id),
  KEY mapa_id (mapa_id),
  CONSTRAINT fk_svet_poznamky_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE,
  CONSTRAINT fk_svet_poznamky_autor FOREIGN KEY (autor_ucet_id) REFERENCES ucty (id),
  CONSTRAINT fk_svet_poznamky_mapa FOREIGN KEY (mapa_id) REFERENCES mapy (id) ON DELETE SET NULL
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

-- Herní kalendář světa — PJ zadá počáteční datum při založení světa,
-- odtud se svět "posouvá" (pohyb po mapě, čas v dungeonu...) jako počet
-- uplynulých HERNÍCH dní. Aktuální herní datum = pocatecni_datum +
-- aktualni_den_offset dní. Posun je zatím výhradně RUČNÍ (PJ ho zadá na
-- nové administraci světa, viz hra/svet_administrace.php) — automatické
-- odvození z ušlé vzdálenosti/akcí by potřebovalo konkrétní Tabulku
-- rychlosti chůze/běhu z h1634, která se ale v přepisu pravidel
-- nedochovala jako čísla (jen slovní rozsahy, viz komentář u
-- content/pravidla-hrac.html), takže by šlo jen o odhad bez opory v
-- textu — raději nechat na PJ, dokud se nenajde lepší zdroj.
ALTER TABLE svet
    ADD COLUMN pocatecni_datum DATE NOT NULL DEFAULT '2026-01-01' AFTER popis,
    ADD COLUMN aktualni_den_offset INT UNSIGNED NOT NULL DEFAULT 0 AFTER pocatecni_datum;
