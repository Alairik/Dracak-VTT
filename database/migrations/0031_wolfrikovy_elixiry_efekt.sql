-- Wolfrikovy elixíry 1.-5. stupně — dřív vynechány jako "trvalá změna
-- vlastnosti, nepatří do modelu efektů s trváním". Přehodnoceno: efekty
-- schéma nevyžaduje, aby trvani bylo konečné — "trvalé" je platná
-- hodnota stejně jako "3 směny", jen se nikdy sama neukončí časem.
--
-- Jeden obecný, znovupoužitelný efekt pro všech 5 stupňů (jako
-- Poškození/Léčení u kouzel) — konkrétní počet stupňů bere engine z
-- lektvary.pevny_bonus (kostky pole zde nedávají smysl, jde o flat
-- hodnotu 1-5, ne o hod kostkou — stejná konvence jako u "Léčivý
-- obvaz", 0029).
--
-- Čisté INSERT/UPDATE (DML) — mělo by se nasadit samo přes migrate.php.

UPDATE lektvary SET pevny_bonus = 1 WHERE id = 50 AND pocet_kostek IS NULL AND pevny_bonus IS NULL;
UPDATE lektvary SET pevny_bonus = 2 WHERE id = 51 AND pocet_kostek IS NULL AND pevny_bonus IS NULL;
UPDATE lektvary SET pevny_bonus = 3 WHERE id = 52 AND pocet_kostek IS NULL AND pevny_bonus IS NULL;
UPDATE lektvary SET pevny_bonus = 4 WHERE id = 53 AND pocet_kostek IS NULL AND pevny_bonus IS NULL;
UPDATE lektvary SET pevny_bonus = 5 WHERE id = 54 AND pocet_kostek IS NULL AND pevny_bonus IS NULL;

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Trvalé posílení vlastnosti (elixír)', 'buff', 'vlastnost dle volby pijáka', 'dle pevny_bonus lektvaru (1-5 stupňů), max. 21+5', 'jednorazove', 'trvalé', 'Nelze svévolně zrušit; požití dalšího elixíru do 5 let od předchozího obrátí účinek (postih místo bonusu).', 0,
'Wolfrikův elixír (homebrew, h2085). Trvale zvýší zvolenou fyzickou/psychickou vlastnost (Sil/Obr/Odol/Int/Chr...) o počet stupňů daný konkrétním elixírem. Nelze navýšit nad 21+5. Konkrétní počet stupňů bere engine z pevny_bonus záznamu lektvaru, ne z tohoto efektu (obecný, znovupoužitelný pro všech 5 stupňů elixíru).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Trvalé posílení vlastnosti (elixír)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT l.id, (SELECT id FROM efekty WHERE nazev = 'Trvalé posílení vlastnosti (elixír)')
FROM lektvary l WHERE l.id IN (50,51,52,53,54);
