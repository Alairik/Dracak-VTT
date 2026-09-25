-- Strukturované efekty dovedností podle 4 stupňů výsledku testu (past),
-- viz vysledky_testu (fatální neúspěch/neúspěch/úspěch/fatální úspěch).
-- kontext rozlišuje, že efekt může být jiný v boji a mimo boj (např.
-- "Běh na krátké vzdálenosti") — 'obecny' když se nerozlišuje.
CREATE TABLE schopnost_vysledky (
    schopnost_id       INT UNSIGNED NOT NULL,
    vysledek_testu_id  TINYINT UNSIGNED NOT NULL,
    kontext            ENUM('obecny', 'boj', 'mimo_boj') NOT NULL DEFAULT 'obecny',
    efekt_text         TEXT NOT NULL,
    PRIMARY KEY (schopnost_id, vysledek_testu_id, kontext),
    FOREIGN KEY (schopnost_id) REFERENCES zvlastni_schopnosti(id) ON DELETE CASCADE,
    FOREIGN KEY (vysledek_testu_id) REFERENCES vysledky_testu(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
