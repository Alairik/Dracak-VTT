-- Nová M:N tabulka kouzlo_suroviny — kouzla dosud neměla ŽÁDNOU
-- strukturní vazbu na fyzické komponenty/ingredience (na rozdíl od
-- lektvarů, kde lektvar_suroviny už existovala, jen neúplně
-- vyplněná). Stejný vzor jako lektvar_suroviny (kouzlo_id, predmet_id,
-- mnozstvi).
--
-- Průzkum: z 69 kouzel zmiňujících "Komponent" jde skoro vždy o
-- Bardovu/Pamětníkovu abstraktní H/N/T (hlas/nástroj/tanec) volbu
-- výkonu, ne o spotřebovávanou fyzickou surovinu — tam kouzlo_suroviny
-- nepatří. Skutečné fyzické "Pomůcky:" s konkrétní věcí má jen
-- Chodcova kapitola "kouzla pocestných" (11 kouzel, id 322-332) —
-- řešeno v navazující migraci 0035.
--
-- CREATE TABLE = DDL, web účet (DML-only) ho neprovede — MUSÍ se
-- spustit ručně přes phpMyAdmin (admin účet) a zapsat do migrace_log,
-- stejně jako 0033 (schopnost_pasti). Dokud se obě ručně nespustí,
-- migrate.php bude na nich při každém deployi zůstávat stát.

CREATE TABLE IF NOT EXISTS kouzlo_suroviny (
  kouzlo_id INT UNSIGNED NOT NULL,
  predmet_id INT UNSIGNED NOT NULL,
  mnozstvi VARCHAR(30) DEFAULT NULL,
  PRIMARY KEY (kouzlo_id, predmet_id),
  KEY predmet_id (predmet_id),
  CONSTRAINT kouzlo_suroviny_ibfk_1 FOREIGN KEY (kouzlo_id) REFERENCES kouzla (id) ON DELETE CASCADE,
  CONSTRAINT kouzlo_suroviny_ibfk_2 FOREIGN KEY (predmet_id) REFERENCES predmety (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
