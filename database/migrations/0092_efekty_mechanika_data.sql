-- Efekty s mechanikou — data (DML). Navazuje na ruční SQL (sloupce efektů, tabulka efekt_slozky, sloupce nasazených efektů),
-- tedy až po 0091. Každá složka cituje místo v knize (h-id / b-id v content/pravidla-hrac.html).
--
-- mechanika_stav (co boj z efektu vyhodnotí sám):
--   auto — všechno, cast — čísla a akce (zbytek PJ), pj — nic (štítek s popisem; výchozí), past — navazuje na pasti a jedy,
--   ceka — v knize chybí údaj (nehádá se).
-- Efekty bez uvedené mechaniky zůstávají „pj“: nejsou zpracované, protože v knize nemají jasný mechanický dopad v boji
-- (aury, drahokamy, vlastnosti, informace…) nebo je ten dopad podmíněný tím, co se nedá z VTT poznat.

-- 1) Nové efekty (Rychlost a Protoplazma jako kouzla, totální úspěch zastrašení).
INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Rychlost (kouzlo)', 'buff', 0, 'akce, iniciativa, rychlost pohybu', '×2 akce; v dalších kolech +6 iniciativa', 'jednorazove', 'podle dodané magenergie (2 magy za kolo)', 'Uplynutí trvání.', 0, 'Kouzlo rychlost (h1626): tvor má dvojnásobek akcí, které právě má; v dalších kolech +6 k iniciativě. Opakované seslání trvání sčítá, účinek ne. Kolo seslání se do trvání nepočítá.' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Rychlost (kouzlo)');
INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protoplazma', 'debuff', 0, 'akce, iniciativa', '½ akcí (dolů); v dalších kolech −3 iniciativa', 'jednorazove', '2–7 kol', 'Uplynutí trvání.', 0, 'Kouzlo protoplazma (h1626): tvor má poloviční počet akcí (dolů); v dalších kolech −3 k iniciativě. Opakované seslání trvání sčítá, účinek ne. Cíl se brání hodem proti pasti (Obr ~ 8 ~ 0). Kolo seslání se do trvání nepočítá.' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protoplazma');
INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Zastrašen (útěk, totální úspěch)', 'debuff', 0, 'pohyb, iniciativa', '+3 Iniciativa (útěk)', 'jednorazove', '4 kola', 'uplynutí trvání nebo ztráta z dohledu', 0, 'Totální úspěch zastrašení (h120 b825): cíl prchá nejvyšší možnou rychlostí co nejpřímější cestou od zastrašovatele po dobu 4 kol a má +3 k iniciativě.' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Zastrašen (útěk, totální úspěch)');

-- 2) Stav mechaniky.
UPDATE efekty SET mechanika_stav = 'auto' WHERE nazev IN ('Krvácení (sečná zbraň trollobijce)', 'Spánek', 'Ochromení', 'Rychlost (lektvar)', 'Ochromení Černou zhoubou', 'Regenerace (lektvar)', 'Běh na krátké vzdálenosti', 'Poškození', 'Léčení', 'Rychlost (kouzlo)', 'Protoplazma');
UPDATE efekty SET mechanika_stav = 'cast' WHERE nazev IN ('Zastrašen (útěk)', 'Zastrašen (útěk, totální úspěch)', 'Berserk (nekontrolovaný vztek)', 'Sražen na zem (bojovník)', 'Ochromení (drtivá zbraň trollobijce)', 'Regenerace Prasiolitu');
UPDATE efekty SET mechanika_stav = 'past' WHERE nazev IN ('Připíchnutí (bodná zbraň trollobijce)', 'Chycen v lovecké pasti', 'Rozdrcen padající zátěží', 'Zasažen šipkovou pastí', 'Zraněn výbuchem pasti (mírně)', 'Zraněn výbuchem pasti (těžce)', 'Klíč exploduje', 'Zámek zajištěn kouzelnou pastí v zámku');
UPDATE efekty SET mechanika_stav = 'ceka' WHERE nazev IN ('Zesílený sluch', 'Neviditelnost (lektvar)', 'Noční vidění', 'Řeč se zvířaty (lektvar)', 'Oslepení (Malé oko)', 'Nutkání mluvit pravdu (Pravdomluv)', 'Strach');
UPDATE efekty SET mechanika_stav = 'past' WHERE nazev LIKE 'Otrava (%' OR nazev LIKE 'Protijed proti jedu %';

-- 3) Výchozí trvání v kolech (jen tam, kde kniha říká počet kol), kolo seslání se nepočítá (kouzla, h1626 b10848; stejně i lektvar
--    Rychlosti — rozhodnutí uživatele 2026-10-17: kouzlo a lektvar Rychlosti se sčítají jako jeden efekt), sčítání trvání.
UPDATE efekty SET trvani_kol = 2 WHERE nazev = 'Zastrašen (útěk)';
UPDATE efekty SET trvani_kol = 4 WHERE nazev = 'Zastrašen (útěk, totální úspěch)';
UPDATE efekty SET trvani_kol = 1 WHERE nazev = 'Sražen na zem (bojovník)';
UPDATE efekty SET trvani_kol = 1 WHERE nazev = 'Ochromení (drtivá zbraň trollobijce)';
UPDATE efekty SET trvani_kol = 5 WHERE nazev = 'Rychlost (lektvar)';
UPDATE efekty SET trvani_kol = 5 WHERE nazev = 'Regenerace (lektvar)';
UPDATE efekty SET trvani_kol = 3 WHERE nazev = 'Běh na krátké vzdálenosti';
UPDATE efekty SET kolo_se_nepocita = 1 WHERE nazev IN ('Spánek', 'Ochromení', 'Rychlost (kouzlo)', 'Protoplazma', 'Rychlost (lektvar)');
UPDATE efekty SET opakovani = 'secti' WHERE nazev IN ('Rychlost (lektvar)', 'Rychlost (kouzlo)', 'Protoplazma');
-- Sražení a ochromení trollobijce, krvácení se sčítají (každé zásahy zvlášť) — už mají stackovatelne = 1 (Ochromení trollobijce, Krvácení).

-- 4) Složky mechaniky.
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'ini_bonus', 2, NULL, NULL, NULL, NULL, 'h120 b826: úspěch zastrašení, 2 kola, +2 k iniciativě' FROM efekty WHERE nazev = 'Zastrašen (útěk)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'ini_bonus');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'ini_bonus', 3, NULL, NULL, NULL, NULL, 'h120 b825: totální úspěch, 4 kola, +3 k iniciativě' FROM efekty WHERE nazev = 'Zastrašen (útěk, totální úspěch)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'ini_bonus');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'ini_bonus', 6, NULL, NULL, NULL, NULL, 'h134 b945: během berserkeru +6 k iniciativě (dvojnásobný bonus za sílu posoudí PJ)' FROM efekty WHERE nazev = 'Berserk (nekontrolovaný vztek)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'ini_bonus');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'akce_zmena', -2, NULL, NULL, NULL, NULL, 'h144 b1025: vstávání stojí 2 akce' FROM efekty WHERE nazev = 'Sražen na zem (bojovník)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'akce_zmena');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'nemuze_branit', NULL, NULL, NULL, NULL, NULL, 'h144 b1025: během vstávání se nemůže bránit štítem ani zbraní' FROM efekty WHERE nazev = 'Sražen na zem (bojovník)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'nemuze_branit');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'akce_zmena', -2, NULL, NULL, NULL, NULL, 'h173 b1542: ihned ztratí 2 akce, efekt se sčítá' FROM efekty WHERE nazev = 'Ochromení (drtivá zbraň trollobijce)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'akce_zmena');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'tik_zivoty', -1, 1, 'k4', 0, NULL, 'h173 b1545–b1546: krvácení za 1k4, sčítá se, neustane do ošetření' FROM efekty WHERE nazev = 'Krvácení (sečná zbraň trollobijce)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'tik_zivoty');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'nemuze_jednat', NULL, NULL, NULL, NULL, NULL, 'Spánek: tvor nejedná' FROM efekty WHERE nazev = 'Spánek'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'nemuze_jednat');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'nehybny', NULL, NULL, NULL, NULL, NULL, 'h1613 b10736: sám spící se brání jako nehybný' FROM efekty WHERE nazev = 'Spánek'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'nehybny');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'zraneni_x_uroven', NULL, NULL, NULL, NULL, NULL, 'h1613 b10736–b10737: spící jako vyřazený, zranění se násobí úrovní obránce (postava nejvýš 9, nestvůra životaschopností); rozhodnutí uživatele 2026-10-17' FROM efekty WHERE nazev = 'Spánek'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'zraneni_x_uroven');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'nemuze_jednat', NULL, NULL, NULL, NULL, NULL, 'tooltip efektu: nemůže konat žádné akce ani se bránit' FROM efekty WHERE nazev = 'Ochromení'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'nemuze_jednat');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'nehybny', NULL, NULL, NULL, NULL, NULL, 'h1613 b10733: nehybný protivník' FROM efekty WHERE nazev = 'Ochromení'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'nehybny');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'nemuze_jednat', NULL, NULL, NULL, NULL, NULL, 'ochromení' FROM efekty WHERE nazev = 'Ochromení Černou zhoubou'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'nemuze_jednat');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'nehybny', NULL, NULL, NULL, NULL, NULL, 'h1613 b10733: nehybný protivník' FROM efekty WHERE nazev = 'Ochromení Černou zhoubou'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'nehybny');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'tik_zivoty', 1, 1, 'k4', 0, NULL, 'regeneruje 1k4 životů za kolo' FROM efekty WHERE nazev = 'Regenerace Prasiolitu'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'tik_zivoty');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'tik_zivoty', 1, NULL, NULL, NULL, NULL, 'kostky za kolo se berou z lektvaru (aktivni_efekty.kostky)' FROM efekty WHERE nazev = 'Regenerace (lektvar)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'tik_zivoty');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'akce_zdvojit', NULL, NULL, NULL, NULL, NULL, 'h1626 b10849: zdvojnásobí akce, které tvor právě má' FROM efekty WHERE nazev = 'Rychlost (lektvar)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'akce_zdvojit');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'ini_radek', NULL, NULL, NULL, NULL, 'Kouzlo Rychlost', 'h1626 b10849: v dalších kolech +6 k iniciativě' FROM efekty WHERE nazev = 'Rychlost (lektvar)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'ini_radek');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'akce_zdvojit', NULL, NULL, NULL, NULL, NULL, 'h1626 b10849' FROM efekty WHERE nazev = 'Rychlost (kouzlo)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'akce_zdvojit');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'ini_radek', NULL, NULL, NULL, NULL, 'Kouzlo Rychlost', 'h1626 b10849' FROM efekty WHERE nazev = 'Rychlost (kouzlo)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'ini_radek');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'akce_pulit', NULL, NULL, NULL, NULL, NULL, 'h1626 b10849: akce na polovinu, dolů' FROM efekty WHERE nazev = 'Protoplazma'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'akce_pulit');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'ini_radek', NULL, NULL, NULL, NULL, 'Kouzlo Protoplazma', 'h1626 b10849: v dalších kolech −3 k iniciativě' FROM efekty WHERE nazev = 'Protoplazma'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'ini_radek');
INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'beh_hexu', 1, NULL, NULL, NULL, NULL, 'h1698: +1 hex při běhu a sprintu v boji' FROM efekty WHERE nazev = 'Běh na krátké vzdálenosti'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'beh_hexu');

-- 5) Kouzla Rychlost a Protoplazma nasadí svůj efekt (Protoplazma má hod proti pasti — efekt se po sesláním nenasadí sám,
--    kouzlo ho nabídne PJ po hodu cíle).
INSERT INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, e.id FROM kouzla k JOIN efekty e ON e.nazev = 'Rychlost (kouzlo)' WHERE k.nazev = 'Rychlost' AND k.doba_trvani = 'podle dodané magenergie'
  AND NOT EXISTS (SELECT 1 FROM kouzlo_efekty x WHERE x.kouzlo_id = k.id AND x.efekt_id = e.id);
INSERT INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, e.id FROM kouzla k JOIN efekty e ON e.nazev = 'Protoplazma' WHERE k.nazev = 'Protoplazma'
  AND NOT EXISTS (SELECT 1 FROM kouzlo_efekty x WHERE x.kouzlo_id = k.id AND x.efekt_id = e.id);

-- 6) Chybné vazby kouzlo → Ochromení (jen spojovací řádky, kouzla zůstávají): kouzlo Ochromení nemá nasadit na cíl.
--    Ledové zrcadlo odráží blesky, Hlodající vina nepoškozuje pohyb, Slovo moci zraňuje a oslepuje, Útočiště (mystik, kněz)
--    chrání sesilatele a ochromuje až útočníky, Hromosvod omráčí jen s 5% šancí, Žár omráčí (omráčení není ochromení).
DELETE ke FROM kouzlo_efekty ke JOIN efekty e ON e.id = ke.efekt_id AND e.nazev = 'Ochromení'
JOIN kouzla k ON k.id = ke.kouzlo_id
WHERE k.nazev IN ('Ledové zrcadlo', 'Hlodající vina', 'Slovo moci', 'Útočiště', 'Hromosvod', 'žár');
