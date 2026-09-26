-- Úklid duplicitních záznamů "Elf" + oprava rasové schopnosti Čich.
--
-- V rasy byly 3 řádky "Elf" (id 1, 4, 34), ale kniha i seed
-- (drd-db-full-v1.sql) definují jen jeden — id 4 (má správný popis,
-- pohyblivost i bonusy k vlastnostem/jazyky odpovídající seedu).
-- Id 1 a 34 jsou duplicity bez popisu — 34 má created_by vyplněné (vzniklo
-- omylem přes editor při testování), 1 je nejspíš starý testovací zbytek.
-- Potvrzeno v konverzaci — smazat oboje, nechat jen id 4.
--
-- Obě duplicity měly navíc mylně napojenou rasovou schopnost "Čich".
-- Podle pravidel (viz content/pravidla-hrac.html, h9/h112) je Čich
-- schopnost Hobita, ne Elfa — a stejně tak to má i seed
-- (`WHERE r.nazev = 'Hobit' AND s.nazev = 'Čich'`). Samostatný řádek
-- "Hobit" už v rasy není (nahrazen Kudůkem — "rasa vzniklá splynutím
-- trpaslíků a hobitů"), takže Čich patří k Kudůkovi (id 3).
--
-- ON DELETE CASCADE na rasa_schopnosti/rasa_bonusy_vlastnosti/rasa_jazyky/
-- rasa_povolani smaže napojená data u id 1 a 34 automaticky.
--
-- Čisté DELETE/INSERT (DML) — mělo by se nasadit samo přes migrate.php.

DELETE FROM rasy WHERE id IN (1, 34) AND nazev = 'Elf';

INSERT IGNORE INTO rasa_schopnosti (rasa_id, schopnost_id)
SELECT 3, id FROM zvlastni_schopnosti WHERE nazev = 'Čich';
