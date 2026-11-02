-- Dveře (plán „Vidění, světlo a stíny“, docs/stav-projektu.md, blok E č. 25): zeď, kterou jde otevřít a zavřít.
--   dvere     — 1 = tahle zeď jsou dveře (kreslí se jinak a PJ je přepíná)
--   otevrene  — 1 = dveře jsou otevřené: neblokují pohyb, výhled ani střelu (zavřené blokují podle příznaků zdi jako dřív)
-- Samé DDL (ALTER ADD) — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md. Bez sloupců se dveře v nabídce nezobrazují a zdi fungují jako dosud.

ALTER TABLE zdi ADD COLUMN dvere TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = dveře (zeď, kterou jde otevřít a zavřít)';
ALTER TABLE zdi ADD COLUMN otevrene TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = dveře jsou otevřené (neblokují pohyb, výhled ani střelu)';
