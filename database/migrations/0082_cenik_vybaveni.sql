-- Nový ceník (content/pravidla-hrac.html h2055, Homebrew · zdroj: Ceníky · upraveno
-- 8. 9. 2025) — všech 217 položek jako předměty typu "vybaveni" (Fáze 1b plánu,
-- docs/stav-projektu.md). Rozhodnutí uživatele 2026-10-14: do hry celý ceník.
--
-- Zásady převodu (ověřeno proti textu pravidel):
--   cena   v zlaťácích: 1 zl = 10 st = 100 md (pravidla-hrac.html, "Peníze");
--          u rozsahů ("30 md - 4 st") je uložena dolní hodnota, původní text je
--          v popisu; u položek účtovaných za jednotku (metr, list, den, velikost)
--          je to cena za tu jednotku
--   váha   v mn (mince), jak ji ceník uvádí; u jednotek jako cena
--   oblečení má v ceníku tři hodnoty za velikost A/B/C — jsou z toho tři položky
--   kapacita_mn jen u věcí, do kterých se vkládá náklad v mn: vaky, torna, soudky,
--          sud, truhly, trakař (u ostatních je objem/kusy/osoby jen v popisu)
--   Služby (jídlo a nocleh v hostinci, koupel) váží 0 mn
--   Opakované spuštění nic nezduplikuje (NOT EXISTS podle názvu).
--
-- Zdrojové zvláštnosti, které jsem NEOPRAVOVAL: Soudek 10 l váží 200 mn (20 l: 100 mn),
-- Nákladní vak střední stojí méně než malý, "lst" u zimních bot je zřejmě "st".
--
-- Čisté INSERTy; proběhnou automaticky, jakmile je v DB provedené 0080.

INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Torna kožená', 'vybaveni', 'Vaky a toulce', 30, 800, 0.6, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Torna kožená' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Velký vak', 'vybaveni', 'Vaky a toulce', 15, 360, 0.25, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Velký vak' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Malý vak', 'vybaveni', 'Vaky a toulce', 9, 140, 0.5, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Malý vak' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Sedlový vak malý', 'vybaveni', 'Vaky a toulce', 12, 250, 0.2, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sedlový vak malý' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Sedlový vak střední', 'vybaveni', 'Vaky a toulce', 24, 500, 0.65, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sedlový vak střední' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Sedlový vak velký', 'vybaveni', 'Vaky a toulce', 36, 750, 0.8, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sedlový vak velký' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Nákladní vak malý', 'vybaveni', 'Vaky a toulce', 45, 1000, 1, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Nákladní vak malý' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Nákladní vak střední', 'vybaveni', 'Vaky a toulce', 60, 1500, 0.75, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Nákladní vak střední' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Nákladní vak velký', 'vybaveni', 'Vaky a toulce', 80, 2000, 2, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Nákladní vak velký' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Toulec na šípy/šipky', 'vybaveni', 'Vaky a toulce', 7, NULL, 0.15, 'v ceníku: 20ks' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Toulec na šípy/šipky' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Toulec na šípy/šipky rozšířený', 'vybaveni', 'Vaky a toulce', 20, NULL, 0.6, 'v ceníku: 4x10ks' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Toulec na šípy/šipky rozšířený' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jednoruční zbraně', 'vybaveni', 'Pochvy', 10, NULL, 0.1, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jednoruční zbraně' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Obouruční zbraně', 'vybaveni', 'Pochvy', 15, NULL, 0.15, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Obouruční zbraně' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Tyčové zbraně', 'vybaveni', 'Pochvy', 10, NULL, 0.8, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Tyčové zbraně' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Super těžké zbraně', 'vybaveni', 'Pochvy', 20, NULL, 0.2, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Super těžké zbraně' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Holster na vrhací zbraň', 'vybaveni', 'Pochvy', 10, NULL, 0.15, 'v ceníku: každých 5 ks' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Holster na vrhací zbraň' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Měch na vodu 1l', 'vybaveni', 'Nádoby', 5, NULL, 0.15, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Měch na vodu 1l' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Flakónek', 'vybaveni', 'Nádoby', 3, NULL, 0.5, 'v ceníku: 0,1l' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Flakónek' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Láhev 1l', 'vybaveni', 'Nádoby', 10, NULL, 0.2, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Láhev 1l' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Soudek 5l', 'vybaveni', 'Nádoby', 25, 100, 0.36, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Soudek 5l' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Soudek 10l', 'vybaveni', 'Nádoby', 200, 200, 0.75, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Soudek 10l' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Soudek 20l', 'vybaveni', 'Nádoby', 100, 400, 1.6, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Soudek 20l' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Soudek 50l', 'vybaveni', 'Nádoby', 255, 1000, 4, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Soudek 50l' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Sud 225l', 'vybaveni', 'Nádoby', 1100, 4500, 1.05, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sud 225l' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo na 1 den v hostinci — Pajzl', 'vybaveni', 'Služby', 0, NULL, 0.3, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo na 1 den v hostinci — Pajzl' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo na 1 den v hostinci — Průměř', 'vybaveni', 'Služby', 0, NULL, 0.6, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo na 1 den v hostinci — Průměř' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo na 1 den v hostinci — Luxus', 'vybaveni', 'Služby', 0, NULL, 0.12, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo na 1 den v hostinci — Luxus' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo na 1 den cestovní — Nouzové', 'vybaveni', 'Služby', 0, NULL, 0.3, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo na 1 den cestovní — Nouzové' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo na 1 den cestovní — Normální', 'vybaveni', 'Služby', 0, NULL, 0.7, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo na 1 den cestovní — Normální' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo na 1 den cestovní — Luxusní', 'vybaveni', 'Služby', 0, NULL, 0.14, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo na 1 den cestovní — Luxusní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Nocleh v hostinci — Pajzl', 'vybaveni', 'Služby', 0, NULL, 0.1, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Nocleh v hostinci — Pajzl' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Nocleh v hostinci — Normální', 'vybaveni', 'Služby', 0, NULL, 0.5, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Nocleh v hostinci — Normální' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Nocleh v hostinci — Luxusní', 'vybaveni', 'Služby', 0, NULL, 0.12, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Nocleh v hostinci — Luxusní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Nocleh v hostinci — Koupel', 'vybaveni', 'Služby', 0, NULL, 0.02, 'cena v ceníku: 2 - 20 md' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Nocleh v hostinci — Koupel' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Pochodeň', 'vybaveni', 'Svícení', 10, NULL, 0.07, 'v ceníku: 2 směny' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Pochodeň' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Lucerna obyčejná', 'vybaveni', 'Svícení', 26, NULL, 0.5, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Lucerna obyčejná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Lucerna s okenicemi', 'vybaveni', 'Svícení', 37, NULL, 0.15, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Lucerna s okenicemi' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Olej na svícení', 'vybaveni', 'Svícení', 10, NULL, 0.35, 'v ceníku: 6 směn' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Olej na svícení' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Křesadlo', 'vybaveni', 'Svícení', 2, NULL, 0.1, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Křesadlo' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Sedlo mizerné', 'vybaveni', 'Sedla', 180, NULL, 1.5, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sedlo mizerné' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Sedlo normální', 'vybaveni', 'Sedla', 240, NULL, 3, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sedlo normální' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Sedlo Luxusní', 'vybaveni', 'Sedla', 260, NULL, 5.5, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sedlo Luxusní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Sedlo válečné', 'vybaveni', 'Sedla', 300, NULL, 4.5, 'poznámka: Bonus +1 k udržení se v sedle' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Sedlo válečné' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní mizerné (velikost A)', 'vybaveni', 'Oblečení', 25, NULL, 0.15, 'v ceníku: A,B,C; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní mizerné (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní mizerné (velikost B)', 'vybaveni', 'Oblečení', 30, NULL, 0.2, 'v ceníku: A,B,C; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní mizerné (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní mizerné (velikost C)', 'vybaveni', 'Oblečení', 35, NULL, 0.25, 'v ceníku: A,B,C; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní mizerné (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní obyčejné (velikost A)', 'vybaveni', 'Oblečení', 35, NULL, 0.8, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní obyčejné (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní obyčejné (velikost B)', 'vybaveni', 'Oblečení', 40, NULL, 1, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní obyčejné (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní obyčejné (velikost C)', 'vybaveni', 'Oblečení', 45, NULL, 1.2, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní obyčejné (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní luxusní (velikost A)', 'vybaveni', 'Oblečení', 60, NULL, 8, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní luxusní (velikost B)', 'vybaveni', 'Oblečení', 70, NULL, 9, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty letní luxusní (velikost C)', 'vybaveni', 'Oblečení', 80, NULL, 10, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty letní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní mizerné (velikost A)', 'vybaveni', 'Oblečení', 40, NULL, 0.25, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní mizerné (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní mizerné (velikost B)', 'vybaveni', 'Oblečení', 50, NULL, 0.3, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní mizerné (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní mizerné (velikost C)', 'vybaveni', 'Oblečení', 60, NULL, 0.35, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní mizerné (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní normální (velikost A)', 'vybaveni', 'Oblečení', 80, NULL, 1.8, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní normální (velikost B)', 'vybaveni', 'Oblečení', 90, NULL, 2, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní normální (velikost C)', 'vybaveni', 'Oblečení', 100, NULL, 2.2, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní luxusní (velikost A)', 'vybaveni', 'Oblečení', 110, NULL, 13.5, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní luxusní (velikost B)', 'vybaveni', 'Oblečení', 120, NULL, 15, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Šaty zimní luxusní (velikost C)', 'vybaveni', 'Oblečení', 130, NULL, 16.5, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Šaty zimní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní mizerný (velikost A)', 'vybaveni', 'Oblečení', 25, NULL, 0.1, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní mizerný (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní mizerný (velikost B)', 'vybaveni', 'Oblečení', 30, NULL, 0.15, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní mizerný (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní mizerný (velikost C)', 'vybaveni', 'Oblečení', 35, NULL, 0.2, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní mizerný (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní normální (velikost A)', 'vybaveni', 'Oblečení', 50, NULL, 0.25, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní normální (velikost B)', 'vybaveni', 'Oblečení', 60, NULL, 0.3, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní normální (velikost C)', 'vybaveni', 'Oblečení', 70, NULL, 0.35, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní luxusní (velikost A)', 'vybaveni', 'Oblečení', 90, NULL, 1.8, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní luxusní (velikost B)', 'vybaveni', 'Oblečení', 100, NULL, 2, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť letní luxusní (velikost C)', 'vybaveni', 'Oblečení', 110, NULL, 2.2, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť letní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní mizerný (velikost A)', 'vybaveni', 'Oblečení', 25, NULL, 0.15, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní mizerný (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní mizerný (velikost B)', 'vybaveni', 'Oblečení', 30, NULL, 0.2, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní mizerný (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní mizerný (velikost C)', 'vybaveni', 'Oblečení', 35, NULL, 0.3, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní mizerný (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní normální (velikost A)', 'vybaveni', 'Oblečení', 55, NULL, 0.5, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní normální (velikost B)', 'vybaveni', 'Oblečení', 60, NULL, 0.6, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní normální (velikost C)', 'vybaveni', 'Oblečení', 65, NULL, 0.7, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní luxusní (velikost A)', 'vybaveni', 'Oblečení', 100, NULL, 4, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní luxusní (velikost B)', 'vybaveni', 'Oblečení', 110, NULL, 4.5, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Plášť zimní luxusní (velikost C)', 'vybaveni', 'Oblečení', 120, NULL, 5, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Plášť zimní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní mizerné (velikost A)', 'vybaveni', 'Oblečení', 25, NULL, 0.15, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní mizerné (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní mizerné (velikost B)', 'vybaveni', 'Oblečení', 30, NULL, 0.2, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní mizerné (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní mizerné (velikost C)', 'vybaveni', 'Oblečení', 35, NULL, 0.25, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní mizerné (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní normální (velikost A)', 'vybaveni', 'Oblečení', 35, NULL, 0.5, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní normální (velikost B)', 'vybaveni', 'Oblečení', 40, NULL, 0.6, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní normální (velikost C)', 'vybaveni', 'Oblečení', 45, NULL, 0.7, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní luxusní (velikost A)', 'vybaveni', 'Oblečení', 40, NULL, 1.3, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní luxusní (velikost B)', 'vybaveni', 'Oblečení', 45, NULL, 1.5, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty letní luxusní (velikost C)', 'vybaveni', 'Oblečení', 50, NULL, 1.7, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty letní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní mizerné (velikost A)', 'vybaveni', 'Oblečení', 25, NULL, 0.2, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní mizerné (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní mizerné (velikost B)', 'vybaveni', 'Oblečení', 30, NULL, 0.3, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní mizerné (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní mizerné (velikost C)', 'vybaveni', 'Oblečení', 35, NULL, 0.4, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní mizerné (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní normální (velikost A)', 'vybaveni', 'Oblečení', 45, NULL, 0.8, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní normální (velikost B)', 'vybaveni', 'Oblečení', 50, NULL, 0.9, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní normální (velikost C)', 'vybaveni', 'Oblečení', 55, NULL, 1, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní luxusní (velikost A)', 'vybaveni', 'Oblečení', 50, NULL, 2.3, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní luxusní (velikost B)', 'vybaveni', 'Oblečení', 60, NULL, 2.5, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Boty zimní luxusní (velikost C)', 'vybaveni', 'Oblečení', 70, NULL, 2.7, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Boty zimní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní mizerný (velikost A)', 'vybaveni', 'Oblečení', 8, NULL, 0.25, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní mizerný (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní mizerný (velikost B)', 'vybaveni', 'Oblečení', 9, NULL, 0.3, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní mizerný (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní mizerný (velikost C)', 'vybaveni', 'Oblečení', 10, NULL, 0.35, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní mizerný (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní normální (velikost A)', 'vybaveni', 'Oblečení', 10, NULL, 0.6, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní normální (velikost B)', 'vybaveni', 'Oblečení', 12, NULL, 0.7, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní normální (velikost C)', 'vybaveni', 'Oblečení', 14, NULL, 0.8, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní luxusní (velikost A)', 'vybaveni', 'Oblečení', 13, NULL, 0.5, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní luxusní (velikost B)', 'vybaveni', 'Oblečení', 15, NULL, 0.6, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Klobouk letní luxusní (velikost C)', 'vybaveni', 'Oblečení', 17, NULL, 0.7, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Klobouk letní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní mizerná (velikost A)', 'vybaveni', 'Oblečení', 10, NULL, 0.5, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní mizerná (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní mizerná (velikost B)', 'vybaveni', 'Oblečení', 12, NULL, 0.6, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní mizerná (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní mizerná (velikost C)', 'vybaveni', 'Oblečení', 14, NULL, 0.7, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní mizerná (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní normální (velikost A)', 'vybaveni', 'Oblečení', 14, NULL, 0.3, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní normální (velikost B)', 'vybaveni', 'Oblečení', 16, NULL, 0.4, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní normální (velikost C)', 'vybaveni', 'Oblečení', 18, NULL, 0.5, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní luxusní (velikost A)', 'vybaveni', 'Oblečení', 20, NULL, 1.3, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní luxusní (velikost B)', 'vybaveni', 'Oblečení', 22, NULL, 1.4, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Čepice zimní luxusní (velikost C)', 'vybaveni', 'Oblečení', 24, NULL, 1.5, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Čepice zimní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní mizerné (velikost A)', 'vybaveni', 'Oblečení', 3, NULL, 0.2, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní mizerné (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní mizerné (velikost B)', 'vybaveni', 'Oblečení', 4, NULL, 0.3, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní mizerné (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní mizerné (velikost C)', 'vybaveni', 'Oblečení', 5, NULL, 0.4, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní mizerné (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní normální (velikost A)', 'vybaveni', 'Oblečení', 4, NULL, 0.4, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní normální (velikost B)', 'vybaveni', 'Oblečení', 5, NULL, 0.5, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní normální (velikost C)', 'vybaveni', 'Oblečení', 6, NULL, 0.6, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní luxusní (velikost A)', 'vybaveni', 'Oblečení', 7, NULL, 0.7, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní luxusní (velikost B)', 'vybaveni', 'Oblečení', 8, NULL, 0.8, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice letní luxusní (velikost C)', 'vybaveni', 'Oblečení', 9, NULL, 0.9, 'ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice letní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní mizerné (velikost A)', 'vybaveni', 'Oblečení', 5, NULL, 0.5, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní mizerné (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní mizerné (velikost B)', 'vybaveni', 'Oblečení', 6, NULL, 0.6, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní mizerné (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní mizerné (velikost C)', 'vybaveni', 'Oblečení', 7, NULL, 0.7, 'v ceníku: -5; v ceníku: -5; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní mizerné (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní normální (velikost A)', 'vybaveni', 'Oblečení', 8, NULL, 0.15, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní normální (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní normální (velikost B)', 'vybaveni', 'Oblečení', 9, NULL, 0.2, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní normální (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní normální (velikost C)', 'vybaveni', 'Oblečení', 10, NULL, 0.25, 'v ceníku: -15; v ceníku: -15; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní normální (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní luxusní (velikost A)', 'vybaveni', 'Oblečení', 11, NULL, 1, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní luxusní (velikost A)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní luxusní (velikost B)', 'vybaveni', 'Oblečení', 12, NULL, 1.1, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní luxusní (velikost B)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rukavice zimní luxusní (velikost C)', 'vybaveni', 'Oblečení', 13, NULL, 1.2, 'v ceníku: -25; v ceníku: -25; ceník: oblečení, pokrývka hlavy, boty, plášť (minimum)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rukavice zimní luxusní (velikost C)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní stan letní mizerný', 'vybaveni', 'Stany', 70, NULL, 1, 'v ceníku: 1 osoba; poznámka: x počet osob' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní stan letní mizerný' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní stan letní standartní', 'vybaveni', 'Stany', 100, NULL, 2.5, 'v ceníku: 1 osoba' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní stan letní standartní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní stan letní luxusní', 'vybaveni', 'Stany', 180, NULL, 8, 'v ceníku: 1 osoba' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní stan letní luxusní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní stan zimní mizerný', 'vybaveni', 'Stany', 90, NULL, 1.5, 'v ceníku: 1 osoba; poznámka: -5' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní stan zimní mizerný' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní stan zimní standartní', 'vybaveni', 'Stany', 135, NULL, 3.5, 'v ceníku: 1 osoba; poznámka: -15' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní stan zimní standartní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní stan zimní luxusní', 'vybaveni', 'Stany', 250, NULL, 2, 'v ceníku: 1 osoba; poznámka: -25' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní stan zimní luxusní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Maskovací síť na osobu', 'vybaveni', 'Maskovací sítě', 10, NULL, 0.55, 'cena v ceníku: 5,50 st za velikost; váha v ceníku: 10 mn za velikost' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Maskovací síť na osobu' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Maskovací síť na člověka', 'vybaveni', 'Maskovací sítě', 20, NULL, 0.65, 'cena v ceníku: 6,50 st za osobu; váha v ceníku: 20mn za osobu' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Maskovací síť na člověka' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Truhla malá dřevěná', 'vybaveni', 'Truhlice', 60, 200, 0.5, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Truhla malá dřevěná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Truhla střední dřevěná', 'vybaveni', 'Truhlice', 90, 500, 0.8, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Truhla střední dřevěná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Truhla velká dřevěná', 'vybaveni', 'Truhlice', 200, 1500, 1.9, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Truhla velká dřevěná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Truhla malá železná', 'vybaveni', 'Truhlice', 150, 200, 1, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Truhla malá železná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Truhla střední železná', 'vybaveni', 'Truhlice', 225, 500, 1.6, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Truhla střední železná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Truhla velká železná', 'vybaveni', 'Truhlice', 500, 1500, 3.8, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Truhla velká železná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Deka mizerná', 'vybaveni', 'Deky', 20, NULL, 0.2, 'v ceníku: -5; ceník: nebo plášť + deka' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Deka mizerná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Deka normální', 'vybaveni', 'Deky', 35, NULL, 0.8, 'v ceníku: -15; ceník: nebo plášť + deka' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Deka normální' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Deka luxusní', 'vybaveni', 'Deky', 50, NULL, 1.6, 'v ceníku: -25; ceník: nebo plášť + deka' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Deka luxusní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz obyčejný (2000 mn)', 'vybaveni', 'Provazy', 5, NULL, 0.9, 'nosnost lana 2000 mn; cena v ceníku: 90 md 1 metr; váha v ceníku: 5 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz obyčejný (2000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz obyčejný (4000 mn)', 'vybaveni', 'Provazy', 10, NULL, 2, 'nosnost lana 4000 mn; cena v ceníku: 20 st 1 metr; váha v ceníku: 10 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz obyčejný (4000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz obyčejný (6000 mn)', 'vybaveni', 'Provazy', 16, NULL, 4, 'nosnost lana 6000 mn; cena v ceníku: 40 st 1 metr; váha v ceníku: 16 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz obyčejný (6000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz runový (12 000 mn)', 'vybaveni', 'Provazy', 8, NULL, 8, 'nosnost lana 12 000 mn; cena v ceníku: 80 st 1 metr; váha v ceníku: 8 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz runový (12 000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz runový (16 000 mn)', 'vybaveni', 'Provazy', 11, NULL, 1.2, 'nosnost lana 16 000 mn; cena v ceníku: 1,20 zl 1 metr; váha v ceníku: 11 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz runový (16 000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz runový (22 000 mn)', 'vybaveni', 'Provazy', 13, NULL, 1.65, 'nosnost lana 22 000 mn; cena v ceníku: 1,65 zl 1 metr; váha v ceníku: 13 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz runový (22 000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz runový (28 000 mn)', 'vybaveni', 'Provazy', 17, NULL, 2.1, 'nosnost lana 28 000 mn; cena v ceníku: 2,10 zl 1 metr; váha v ceníku: 17 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz runový (28 000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz runový (35 000 mn)', 'vybaveni', 'Provazy', 20, NULL, 2.45, 'nosnost lana 35 000 mn; cena v ceníku: 2,45 zl 1 metr; váha v ceníku: 20 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz runový (35 000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Provaz runový (45 000 mn)', 'vybaveni', 'Provazy', 25, NULL, 3.36, 'nosnost lana 45 000 mn; cena v ceníku: 3,36 zl 1 metr; váha v ceníku: 25 mn 1 metr' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Provaz runový (45 000 mn)' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Pergamen', 'vybaveni', 'Psací potřeby', 0.1, NULL, 0.4, 'v ceníku: trvanlivý; cena v ceníku: 4 st 1 list' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Pergamen' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Papír', 'vybaveni', 'Psací potřeby', 0.1, NULL, 0.08, 'cena v ceníku: 8 md 1 list' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Papír' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Uhel', 'vybaveni', 'Psací potřeby', 0.1, NULL, 0.01, 'cena v ceníku: 1 md na 10 listů' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Uhel' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Inkoust', 'vybaveni', 'Psací potřeby', 0.1, NULL, 0.02, 'v ceníku: trvanlivý; cena v ceníku: 2 md na 10 listů' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Inkoust' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Brk', 'vybaveni', 'Psací potřeby', 0.3, NULL, 0.01, 'cena v ceníku: 1 md na 20 listů' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Brk' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Kladivo', 'vybaveni', 'Stavební nářadí', 40, NULL, 0.4, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Kladivo' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Hřebíky', 'vybaveni', 'Stavební nářadí', 5, NULL, 0.1, 'v ceníku: 30 ks' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Hřebíky' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Pila', 'vybaveni', 'Stavební nářadí', 20, NULL, 0.7, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Pila' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Trakař', 'vybaveni', 'Stavební nářadí', 750, 2000, 1.6, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Trakař' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Krumpáč', 'vybaveni', 'Stavební nářadí', 100, NULL, 0.2, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Krumpáč' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Lopata', 'vybaveni', 'Stavební nářadí', 60, NULL, 0.1, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Lopata' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Páčidlo', 'vybaveni', 'Stavební nářadí', 40, NULL, 0.8, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Páčidlo' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Lopátka polní', 'vybaveni', 'Stavební nářadí', 40, NULL, 0.6, NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Lopátka polní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Kotlík', 'vybaveni', 'Jídelní náčiní', 50, NULL, 0.15, 'v ceníku: 3 osoby' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Kotlík' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídelní sada mizerná', 'vybaveni', 'Jídelní náčiní', 20, NULL, 0.7, 'v ceníku: 1 osoba; poznámka: talíř, příbor, pohár' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídelní sada mizerná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídelní sada normální', 'vybaveni', 'Jídelní náčiní', 25, NULL, 0.2, 'v ceníku: 1 osoba; poznámka: talíř, příbor, pohár' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídelní sada normální' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídelní sada luxusní', 'vybaveni', 'Jídelní náčiní', 30, NULL, 1.1, 'v ceníku: 1 osoba; poznámka: talíř, příbor, pohár' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídelní sada luxusní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Koření', 'vybaveni', 'Jídelní náčiní', 0.2, NULL, 0.1, 'v ceníku: 1 dávka' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Koření' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Ledová truhla', 'vybaveni', 'Nástroje pro řemesla', 1000, NULL, 1.7, 'poznámka: 200 mn; ceník: parametry lze pro zjednodušení lze použít pro všechny ostatní povolání; ceník: konkrétní ceny v Přesných řemeslech. Cestovní verze +100% ceny -50% hmotnosti' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Ledová truhla' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Led do truhly', 'vybaveni', 'Nástroje pro řemesla', 200, NULL, 0.3, 'cena v ceníku: 30 md - 4 st; ceník: parametry lze pro zjednodušení lze použít pro všechny ostatní povolání; ceník: konkrétní ceny v Přesných řemeslech. Cestovní verze +100% ceny -50% hmotnosti' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Led do truhly' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní kovárna mizerná', 'vybaveni', 'Nástroje pro řemesla', 400, NULL, 7, 'v ceníku: 20% delší doba; ceník: parametry lze pro zjednodušení lze použít pro všechny ostatní povolání; ceník: konkrétní ceny v Přesných řemeslech. Cestovní verze +100% ceny -50% hmotnosti' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní kovárna mizerná' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní kovárna normální', 'vybaveni', 'Nástroje pro řemesla', 550, NULL, 1.7, 'ceník: parametry lze pro zjednodušení lze použít pro všechny ostatní povolání; ceník: konkrétní ceny v Přesných řemeslech. Cestovní verze +100% ceny -50% hmotnosti' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní kovárna normální' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cestovní kovárna skvělá', 'vybaveni', 'Nástroje pro řemesla', 800, NULL, 4.4, 'v ceníku: 10% kratší doba; poznámka: -1 k pasti; ceník: parametry lze pro zjednodušení lze použít pro všechny ostatní povolání; ceník: konkrétní ceny v Přesných řemeslech. Cestovní verze +100% ceny -50% hmotnosti' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cestovní kovárna skvělá' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Lékárnické vybavení', 'vybaveni', 'Nástroje pro řemesla', 60, NULL, 6, 'ceník: parametry lze pro zjednodušení lze použít pro všechny ostatní povolání; ceník: konkrétní ceny v Přesných řemeslech. Cestovní verze +100% ceny -50% hmotnosti' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Lékárnické vybavení' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Lékárnické vybavení cestovní', 'vybaveni', 'Nástroje pro řemesla', 30, NULL, 1.2, 'ceník: parametry lze pro zjednodušení lze použít pro všechny ostatní povolání; ceník: konkrétní ceny v Přesných řemeslech. Cestovní verze +100% ceny -50% hmotnosti' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Lékárnické vybavení cestovní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Rýžové víno', 'vybaveni', 'Alkohol', 20, NULL, 0.2, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Rýžové víno' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Mizerné pivo', 'vybaveni', 'Alkohol', 20, NULL, 0.02, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Mizerné pivo' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Pivo', 'vybaveni', 'Alkohol', 20, NULL, 0.05, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Pivo' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Burčák', 'vybaveni', 'Alkohol', 20, NULL, 0.7, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Burčák' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Víno', 'vybaveni', 'Alkohol', 20, NULL, 0.16, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Víno' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Cider', 'vybaveni', 'Alkohol', 20, NULL, 0.3, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Cider' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Medovina', 'vybaveni', 'Alkohol', 20, NULL, 0.6, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Medovina' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Kančí krev', 'vybaveni', 'Alkohol', 20, NULL, 0.2, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Kančí krev' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Zkvašené mléko', 'vybaveni', 'Alkohol', 20, NULL, 0.01, 'skupina: Kvasné alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Zkvašené mléko' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Saké', 'vybaveni', 'Alkohol', 20, NULL, 0.12, 'skupina: Destilované alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Saké' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Žihadlo', 'vybaveni', 'Alkohol', 20, NULL, 0.12, 'skupina: Destilované alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Žihadlo' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Slivovice', 'vybaveni', 'Alkohol', 20, NULL, 0.4, 'skupina: Destilované alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Slivovice' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Vodka', 'vybaveni', 'Alkohol', 20, NULL, 0.2, 'skupina: Destilované alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Vodka' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Whisky', 'vybaveni', 'Alkohol', 20, NULL, 0.4, 'skupina: Destilované alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Whisky' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Tatranský čaj', 'vybaveni', 'Alkohol', 20, NULL, 0.5, 'skupina: Destilované alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Tatranský čaj' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Absinth', 'vybaveni', 'Alkohol', 20, NULL, 0.6, 'skupina: Destilované alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Absinth' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Tequila', 'vybaveni', 'Alkohol', 20, NULL, 0.7, 'skupina: Destilované alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Tequila' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Pinta', 'vybaveni', 'Alkohol', 20, NULL, 0.25, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Pinta' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Mahakamský ležák', 'vybaveni', 'Alkohol', 20, NULL, 1.02, 'skupina: Legendární alkoholy; přesná cena v ceníku: 10,24 st' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Mahakamský ležák' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Toussaintské víno', 'vybaveni', 'Alkohol', 20, NULL, 5.08, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Toussaintské víno' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Moron', 'vybaveni', 'Alkohol', 20, NULL, 3.8, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Moron' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Bílá medovina', 'vybaveni', 'Alkohol', 20, NULL, 7.6, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Bílá medovina' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Krew', 'vybaveni', 'Alkohol', 20, NULL, 6.4, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Krew' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Hákarl auga', 'vybaveni', 'Alkohol', 20, NULL, 3.2, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Hákarl auga' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Hadí ušknutí', 'vybaveni', 'Alkohol', 20, NULL, 3.2, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Hadí ušknutí' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Kislevská vodka', 'vybaveni', 'Alkohol', 20, NULL, 3.2, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Kislevská vodka' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Burbon', 'vybaveni', 'Alkohol', 20, NULL, 6.4, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Burbon' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Saddandinovo žihadlo', 'vybaveni', 'Alkohol', 20, NULL, 7.8, 'skupina: Legendární alkoholy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Saddandinovo žihadlo' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo v hostinci', 'vybaveni', 'Potraviny', 0, NULL, 0.4, 'cena 40 md až X zl za den (podle kvality)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo v hostinci' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo cestovní', 'vybaveni', 'Potraviny', 20, NULL, 0.7, 'cena i váha jsou za den; náklady 45 md, cena 70 md až X zl za den (podle kvality)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo cestovní' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo cestovní koncentrované', 'vybaveni', 'Potraviny', 10, NULL, 0.15, 'cena i váha jsou za den' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo cestovní koncentrované' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo cestovní okouzlené', 'vybaveni', 'Potraviny', 20, NULL, 0.5, 'cena i váha jsou za den; v ceníku: 5 let trvanlivost' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo cestovní okouzlené' AND typ = 'vybaveni');
INSERT INTO predmety (nazev, typ, kategorie_vybaveni, vaha, kapacita_mn, cena, popis)
SELECT 'Jídlo cestovní koncentrované +', 'vybaveni', 'Potraviny', 10, NULL, 1, 'cena i váha jsou za den; v ceníku: -50% váhy' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM predmety WHERE nazev = 'Jídlo cestovní koncentrované +' AND typ = 'vybaveni');
