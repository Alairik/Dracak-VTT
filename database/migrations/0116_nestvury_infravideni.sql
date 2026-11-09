-- 0116 — Infravidění nestvůr podle bestiáře (a kdo infravidění naopak unikne)
--
-- Proč: infravidění se dosud zadávalo ručně na každém tokenu, přestože bestiář ho u řady nestvůr popisuje
-- i s dosahem. Zdroj pravdy jsou pravidla, ne databáze — chybějící hodnota v DB je chyba dat, ne důvod nechávat
-- ruční pole (rozhodnutí uživatele 2026-11-09). Po téhle migraci token nestvůry infravidění dostane sám.
--
-- Co se přidává:
--  * nestvura_schopnosti.hodnota — číselná hodnota schopnosti u konkrétní nestvůry (dosah infravidění v sázích).
--    Je to obecný sloupec, ne jen pro infravidění: deník nestvůry na něm postaví i další schopnosti a dovednosti.
--  * zvláštní schopnost „Nerozezná ho infravidění“ — pro tvory, které infravidění nezachytí (studená kůže,
--    teplota okolí, splývání). Na tokenu tomu odpovídá příznak „studenokrevný“.
--  * vazby nestvůra ↔ schopnost podle textu bestiáře, s citací bloku u každého řádku.
--
-- POZOR: ALTER pod web účtem (DML-only) neprojde — jde o ruční SQL (blok K v docs/sql-k-rucnimu-spusteni.sql).

ALTER TABLE nestvura_schopnosti ADD COLUMN hodnota VARCHAR(40) NULL COMMENT 'hodnota schopnosti u téhle nestvůry (např. dosah infravidění v sázích)';

-- Schopnost „Nerozezná ho infravidění“ (opak infravidění: tvor se v infravidění neukáže).
INSERT IGNORE INTO zvlastni_schopnosti (nazev, popis)
  SELECT 'Nerozezná ho infravidění', 'Tvor nevydává teplo rozeznatelné infraviděním (studená kůže, teplota okolí, splývání s pozadím). Na tokenu odpovídá příznaku „studenokrevný“.'
  WHERE NOT EXISTS (SELECT 1 FROM zvlastni_schopnosti WHERE nazev = 'Nerezezná ho infravidění' OR nazev = 'Nerozezná ho infravidění');

-- --- Infravidění (dosah podle knihy; prázdný dosah = kniha ho neuvádí, doplní PJ) ---
-- Zmije rohatá: „Má dobře rozvinuté infravidění na deset sáhů“ (b18526)
INSERT IGNORE INTO nestvura_schopnosti (nestvura_id, schopnost_id)
  SELECT n.id, z.id FROM nestvury n, zvlastni_schopnosti z
  WHERE z.nazev = 'Infravidění' AND n.nazev IN ('Zmije rohatá');
UPDATE nestvura_schopnosti ns JOIN nestvury n ON n.id = ns.nestvura_id JOIN zvlastni_schopnosti z ON z.id = ns.schopnost_id
  SET ns.hodnota = '10' WHERE z.nazev = 'Infravidění' AND n.nazev = 'Zmije rohatá' AND ns.hodnota IS NULL;

-- Haterie: „může za zavřenýma očima „vidět“ ve tmě (infravidění na 30 sáhů)“ (b18603)
-- Vážka jeskynní: „Tento tvor má infravidění na 30 sáhů.“ (b18710)
-- Nemrtví (skupina): „Vidí dobře ve tmě, protože mají infravidění na 30 sáhů.“ (b19754)
INSERT IGNORE INTO nestvura_schopnosti (nestvura_id, schopnost_id)
  SELECT n.id, z.id FROM nestvury n, zvlastni_schopnosti z
  WHERE z.nazev = 'Infravidění' AND n.nazev IN ('Haterie', 'Vážka jeskynní',
    'Kostlivec', 'Zombie', 'Ghúl', 'Duch', 'Stín', 'Přízrak*', 'Mumie*', 'Fext*', 'Spektra*', 'Upír*');
UPDATE nestvura_schopnosti ns JOIN nestvury n ON n.id = ns.nestvura_id JOIN zvlastni_schopnosti z ON z.id = ns.schopnost_id
  SET ns.hodnota = '30' WHERE z.nazev = 'Infravidění' AND ns.hodnota IS NULL AND n.nazev IN ('Haterie', 'Vážka jeskynní',
    'Kostlivec', 'Zombie', 'Ghúl', 'Duch', 'Stín', 'Přízrak*', 'Mumie*', 'Fext*', 'Spektra*', 'Upír*');

-- Bez uvedeného dosahu (kniha říká, že tvor infravidění má, ale nečíslem — dosah doplní PJ):
-- Bazilišek „Sám bazilišek sice infravidění má“ (b17822); Krysodlak (zvířecí podoba) „Vzhledem k dobrému infravidění“ (b19305);
-- Skřet „velmi dobře vidí (mají infravidění)“ (b20514)
INSERT IGNORE INTO nestvura_schopnosti (nestvura_id, schopnost_id)
  SELECT n.id, z.id FROM nestvury n, zvlastni_schopnosti z
  WHERE z.nazev = 'Infravidění' AND n.nazev IN ('Bazilišek', 'Krysodlak (zvířecí podoba)', 'Skřet');

-- --- Infravidění je nerozezná ---
-- Haterie: „má studenou kůži, takže ji není možné rozeznat infravidením“ (b18603)
-- Hlen zelený: „trpaslík ho neuvidí, hobit ani infravidění ho nezjistí“ (b18616)
-- Mlok: „teplota se mění podle teploty prostředí, takže nejsou rozpoznatelní infraviděním“ (b19690)
-- Nevidění (skupina): „trpaslíkovo infravidění ani krollův ultraslech neviděné neodhalí“ (b19921)
-- Oživlé sochy (skupina): „trpaslík je zpravidla neuvidí infraviděním“ (b95001; výjimku s lávou viz kontrolní seznam)
INSERT IGNORE INTO nestvura_schopnosti (nestvura_id, schopnost_id)
  SELECT n.id, z.id FROM nestvury n, zvlastni_schopnosti z
  WHERE z.nazev = 'Nerozezná ho infravidění' AND (n.nazev IN ('Haterie', 'Hlen zelený', 'Mlok',
    'Křišťálová socha', 'Železná socha', 'Kamenná socha') OR n.nazev LIKE 'Nevidění —%');
