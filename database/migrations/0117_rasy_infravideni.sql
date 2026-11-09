-- 0117 — Infravidění ras podle pravidel (dosah na vazbě rasa ↔ schopnost)
--
-- Proč: token postavy si infravidění dosud nebral odnikud a PJ ho zadával ručně, přestože pravidla ho u trpaslíka
-- uvádějí i s dosahem. Stejný princip jako u nestvůr (0116): zdroj pravdy jsou pravidla, chybějící údaj v DB je chyba dat.
--
-- Citace: „Trpaslík má infravidění s dosahem 20 sáhů; neproniká materiálem, kterým neproniká obyčejné světlo.“
--         (content/pravidla-hrac.html, blok b790)
-- Prohledány všechny rasy z tabulky rasy proti textu pravidel — infravidění má z hratelných ras **jen trpaslík**;
-- u ostatních (kroll ultrasluch, hobit čich) jde o jiné smysly, které s tmou a teplem nepracují stejně.
-- Pozn.: kouzlo Lovce dává „infravidění jako trpaslík na vzdálenost 50 sáhů“ (b7318) — to je dočasný účinek kouzla,
-- ne vlastní dosah rasy, proto se sem nepíše.
--
-- POZOR: ALTER pod web účtem (DML-only) neprojde — jde o ruční SQL (blok K v docs/sql-k-rucnimu-spusteni.sql).

ALTER TABLE rasa_schopnosti ADD COLUMN hodnota VARCHAR(40) NULL COMMENT 'hodnota schopnosti u téhle rasy (např. dosah infravidění v sázích)';

UPDATE rasa_schopnosti rs
  JOIN rasy r ON r.id = rs.rasa_id
  JOIN zvlastni_schopnosti z ON z.id = rs.schopnost_id
  SET rs.hodnota = '20'
  WHERE z.nazev = 'Infravidění' AND r.nazev = 'Trpaslík' AND rs.hodnota IS NULL;
