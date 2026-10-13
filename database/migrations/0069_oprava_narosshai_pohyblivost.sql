-- Doplnění k 0064: rasa Naro'shai zůstala bez pohyblivosti, protože 0064
-- hledala název s typografickým apostrofem (U+00B4, jak je v textu pravidel
-- a v prvním INSERTu seedu), ale seed ho hned v kroku 5b sjednocuje na
-- rovný apostrof Naro'shai (drd-db-full-v1.sql, "UPDATE rasy SET nazev =
-- 'Naro''shai'") — takže UPDATE v 0064 u téhle jediné rasy nezasáhl žádný
-- řádek. Odhaleno až ověřením na skutečné MariaDB s naimportovaným seedem
-- a všemi migracemi (test nad zjednodušenou tabulkou to nechytil).
--
-- Hodnota 12: homebrew blok rasy Naro´shai, h66, b364 "Pohyblivost:
-- Barbar (12)" (content/pravidla-hrac.html), stejně jako v 0064.
--
-- Čisté UPDATE (DML), idempotentní.

UPDATE rasy SET pohyblivost_zaklad = 12 WHERE nazev = 'Naro''shai';
