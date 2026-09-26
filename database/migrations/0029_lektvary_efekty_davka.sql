-- Re-audit lektvary_efekty — 28 z 73 dosud nenapojených lektvarů,
-- záznam po záznamu podle skutečného popisu (stejný postup jako
-- kouzla/schopnosti). U několika chybějících kostkových polí (útočné/
-- léčivé homebrew lektvary, kde text má jasné "Nk6+M", ale pole
-- pocet_kostek/typ_kostky/pevny_bonus bylo NULL) se pole nejdřív
-- doplní ze stejného důvodu jako migrace 0021 (kouzla.rozsah) — engine
-- čte kostky ze samotného lektvaru, ne z efektu.
--
-- ZÁMĚRNĚ VYNECHÁNO (39 zbylých), s důvodem:
--   - čistě detekční/identifikační/otevírací předměty bez stavového
--     efektu na cíl (Čarovná rtuť, Detekční hůlka, Lakmusový papírek,
--     Píšťalka)
--   - 3 core jedy (Jablečná vůně/Kurare/Melenova pomsta) a 14 homebrew
--     jedů z "Nový ceník lektvarů" (Bolehlav...Pravdomluv) — popis
--     výslovně říká, že přesný mechanický text je jen v nedigitalizované
--     fyzické knize (str. 44-58 PPP/PPE), TODO v datech, ne přehlédnuto
--   - 17 protijedů ke zmíněným jedům (Eraruk...Zlatodým) — nejde
--     definovat "vyléčení" jedu, jehož efekt sám neznáme
--   - 5 Wolfrikových elixírů — TRVALÉ navýšení vlastnosti (jako
--     "Rosomáčí odolnost" u dovedností), ne dočasný efekt s trváním/
--     ukončovací podmínkou, nepatří do stejného modelu jako buff/debuff
--   - Rozpouštěč, Ohnivec — jméno samo nejednoznačné, žádný text efektu
--
-- U 4 lektvarů (Netopýří sluch/Neviditelnost/Soví oči/Zvířecí řeč) je
-- zdrojový text efektu ztracený, ale NÁZEV je jednoznačný běžný trop —
-- efekt vytvořen z názvu, v tooltip_text výslovně označeno jako
-- odvozené z názvu, ne ověřené proti knize.
--
-- Čisté INSERT/UPDATE (DML) — mělo by se nasadit samo přes migrate.php.

-- Doplnění chybějících kostkových polí (jen tam, kde je pole NULL a
-- text má jednoznačnou hodnotu)
UPDATE lektvary SET pevny_bonus = 3
  WHERE id = 31 AND pocet_kostek IS NULL AND pevny_bonus IS NULL;
UPDATE lektvary SET pocet_kostek = 1, typ_kostky = 'k6', pevny_bonus = 0
  WHERE id = 32 AND pocet_kostek IS NULL;
UPDATE lektvary SET pocet_kostek = 2, typ_kostky = 'k6', pevny_bonus = 2
  WHERE id = 33 AND pocet_kostek IS NULL;
UPDATE lektvary SET pocet_kostek = 1, typ_kostky = 'k6', pevny_bonus = 5
  WHERE id = 34 AND pocet_kostek IS NULL;
UPDATE lektvary SET pocet_kostek = 2, typ_kostky = 'k6', pevny_bonus = 4
  WHERE id = 35 AND pocet_kostek IS NULL;
UPDATE lektvary SET pocet_kostek = 1, typ_kostky = 'k6', pevny_bonus = 0
  WHERE id = 47 AND pocet_kostek IS NULL;
UPDATE lektvary SET pocet_kostek = 2, typ_kostky = 'k6', pevny_bonus = 0
  WHERE id = 48 AND pocet_kostek IS NULL;
UPDATE lektvary SET pocet_kostek = 3, typ_kostky = 'k6', pevny_bonus = 0
  WHERE id = 49 AND pocet_kostek IS NULL;

-- Nové efekty

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Éteričnost', 'imunita', 'fyzické/magické/ohnivé zranění', 'nezasažitelná zbraněmi, kouzly, ohněm', 'jednorazove', '1-2 směny', 'Uplynutí trvání.', 0,
'Éterický olej (h275). Potřená postava zprůsvitní, prochází pevnými předměty (1 sáh/směnu), nemůže zraňovat ani manipulovat s předměty, může mluvit a kouzlit.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Éteričnost');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Odolnost vůči ohni (Chladné vody)', 'odolnost', 'ohnivé zranění', 'vysoká teplota poloviční zranění, klasický oheň 0', 'pri_zasahu', '3 směny', 'Uplynutí trvání.', 0,
'Lektvar chladných vod (h275). Oheň o vysoké teplotě (dračí oheň, láva) způsobí poloviční zranění; klasický oheň (olej, pochodeň) nezraní vůbec.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Odolnost vůči ohni (Chladné vody)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Mlhová podoba', 'imunita', 'klasické a magické zbraně; zranitelnost blesk/oheň/výbuch', 'nezranitelná zbraněmi, zranitelná blesky/ohněm/výbuchem', 'jednorazove', '4 směny', 'Uplynutí trvání.', 0,
'Lektvar mlhovina (h275). Promění postavu (a věci) v oblak mlhy — nezranitelná klasickými ani magickými zbraněmi, zranitelná blesky/ohněm/výbuchem; nemůže mluvit, může kouzlit bez řeči/gestikulace.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Mlhová podoba');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Ovládnutí lykantropa', 'debuff', 'vůle/chování (lykantrop)', 'spolupracuje jako s nejlepším přítelem', 'jednorazove', '1-3 směny', 'Uplynutí trvání.', 0,
'Lektvar vlády nad lykantropy (h275). Přiměje lykantropa spolupracovat jako s nejlepším přítelem; sebevražedný nebo přirozenosti odporující příkaz jen ignoruje.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Ovládnutí lykantropa');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Vzkříšení megacloumákem', 'buff', 'stav vyřazení / mdloby / ochromení / křeče', 'ruší mdloby/ochromení/křeče; vzkřísí k pomalé chůzi', 'jednorazove', 'okamžité + doznívání', 'Zhroucení po směně, nebo cíl se sám zastaví.', 0,
'Megacloumák (h275). Odstraňuje mdloby, ochromení, křeče; dokáže i vzkřísit vyřazenou postavu k pomalé chůzi (ta ale ztrácí život za každých 10 kol chůze, max. 6, a po směně se opět zhroutí). Nedoporučuje se použít víc než jednou denně.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Vzkříšení megacloumákem');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Lezení po zdech', 'buff', 'pohyb po svislých plochách', '70 % šance (−1 %/100mn nákladu, −KZ brnění), 1 sáh/kolo', 'jednorazove', '3 směny', 'Uplynutí trvání.', 0,
'Metamorfóza/Pavoučí lektvar (h275). Umožní lezení po zdech. Použije-li ho zloděj, aplikuje se jako jeho zvláštní schopnost s bonusem +30 %.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Lezení po zdech');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Bariéra proti ďáblům', 'imunita', 'ďáblové v okruhu bariéry', 'poloměr 3 sáhy + 0,5 sáhu/úroveň tvůrce', 'jednorazove', '2 směny', 'Uplynutí trvání.', 0,
'Svitek: Ochrana před ďábly (h275). Vytvoří neviditelnou bariéru chránící čtenáře před ďábly.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Bariéra proti ďáblům');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Bariéra proti nemrtvým', 'imunita', 'nemrtví v okruhu bariéry', 'past Roz ~10~ projde/neprojde (past id 27)', 'jednorazove', '4 směny', 'Uplynutí trvání, nebo prolomení pastí.', 0,
'Svitek: Ochrana před nemrtvými (h275). past_id=27 na lektvaru řídí, zda konkrétní nemrtvý bariérou projde.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Bariéra proti nemrtvým');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Bariéra proti neviděným', 'imunita', 'neviděné bytosti v okruhu bariéry', 'past Roz ~8~ projde/neprojde (past id 28)', 'jednorazove', '3 směny', 'Uplynutí trvání, nebo prolomení pastí.', 0,
'Svitek: Ochrana před neviděnými (h275). past_id=28 na lektvaru řídí, zda konkrétní neviděná bytost bariérou projde.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Bariéra proti neviděným');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Bariéra proti démonům', 'imunita', 'volní démoni astrálních sfér', 'chrání před volnými démony astrálních sfér', 'jednorazove', '4 směny', 'Uplynutí trvání.', 0,
'Svitek: Ochrana před démony (h275).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Bariéra proti démonům');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Odolnost proti kouzlům (bariéra)', 'odolnost', 'kouzelnická kouzla procházející bariérou', '% selhání = (magenergie svitku / kouzla) × 100', 'jednorazove', '3 směny', 'Uplynutí trvání.', 0,
'Svitek: Ochrana před kouzly (h275). Funguje jen proti kouzelnickým kouzlům, ne hraničářským.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Odolnost proti kouzlům (bariéra)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Zeslabení zbroje (kyselina)', 'debuff', 'KZ zbroje zasaženého cíle', '-1 KZ', 'pri_zasahu', 'trvalé (do opravy zbroje)', 'Oprava zbroje.', 0,
'Kyselinový lektvar - útočný homebrew (h2056/h2085). Doplňuje samostatné poškození 2k6+2 kyselinou v oblasti 3x3.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Zeslabení zbroje (kyselina)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Regenerace (lektvar)', 'hot', 'životy', 'dle kostek lektvaru za kolo', 'na_zacatku_kola', '5 kol', 'Uplynutí trvání.', 0,
'Regenerační lektvar - homebrew (h2085/h2056). Léčivý plyn v oblasti 5x5 ovlivňovaný větrem, léčí každé kolo po dobu 5 kol (na rozdíl od obecného efektu "Léčení", který je jednorázový).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Regenerace (lektvar)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Zesílený sluch', 'buff', 'sluchové vnímání', 'odvozeno z názvu, přesný text zdroje nedostupný', 'jednorazove', 'neurčeno zdrojem', 'Neurčeno zdrojem.', 0,
'Lektvar netopýřího sluchu - homebrew (h2056, PPE str. 54). Přesný text efektu není v dostupném zdroji, efekt odvozen jen z názvu lektvaru — ověřit proti knize, pokud bude dohledána.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Zesílený sluch');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Neviditelnost (lektvar)', 'imunita', 'zrakové vnímání ostatních', 'odvozeno z názvu, přesný text zdroje nedostupný', 'jednorazove', 'neurčeno zdrojem', 'Neurčeno zdrojem (obvykle útok/akce ruší).', 0,
'Lektvar neviditelnosti - homebrew (h2056, PPE str. 54). Přesný text efektu není v dostupném zdroji, efekt odvozen jen z názvu lektvaru — ověřit proti knize, pokud bude dohledána.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Neviditelnost (lektvar)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Noční vidění', 'buff', 'vidění za tmy', 'odvozeno z názvu, přesný text zdroje nedostupný', 'jednorazove', 'neurčeno zdrojem', 'Neurčeno zdrojem.', 0,
'Lektvar sovích očí - homebrew (h2056, PPE str. 55). Přesný text efektu není v dostupném zdroji, efekt odvozen jen z názvu lektvaru — ověřit proti knize, pokud bude dohledána.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Noční vidění');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Řeč se zvířaty (lektvar)', 'buff', 'komunikace se zvířaty', 'odvozeno z názvu, přesný text zdroje nedostupný', 'jednorazove', 'neurčeno zdrojem', 'Neurčeno zdrojem.', 0,
'Lektvar zvířecí řeči - homebrew (h2056, PPE str. 55). Přesný text efektu není v dostupném zdroji, efekt odvozen jen z názvu lektvaru — ověřit proti knize, pokud bude dohledána.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Řeč se zvířaty (lektvar)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Obnova sil (životy+magy+únava)', 'hot', 'životy, magy, únava', 'dle kostek lektvaru (viz pocet_kostek/typ_kostky/pevny_bonus)', 'jednorazove', 'okamžité', 'Jednorázové použití.', 0,
'Obecný efekt pro elixíry řady Miruvor/Zimuvor/Nebovor (homebrew h2085/h2056) — léčí stejným počtem kostek životy, magy i body únavy najednou. Konkrétní kostky bere engine z vlastního záznamu lektvaru.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Obnova sil (životy+magy+únava)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Odolnost vůči chladu (Pouštní vítr)', 'odolnost', 'chladové zranění', 'extrémní chlad poloviční zranění, přirozený chlad do -40°C 0', 'pri_zasahu', '3 směny', 'Uplynutí trvání.', 0,
'Lektvar pouštního větru - homebrew (h2085/h2056). Extrémně nízké teploty postavu zraňují jen za polovinu (dech ledového draka či magické zranění); přirozené nízké teploty do -40°C postavu nijak nezraňují.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Odolnost vůči chladu (Pouštní vítr)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Klidná mysl', 'buff', 'nálada/emoce', 'bez emocí, jedná pouze logicky', 'jednorazove', '3 směny', 'Uplynutí trvání.', 0,
'Lektvar klidné mysli - homebrew (h2085). Díky tomuto lektvaru není postava schopná výrazné změny nálady — je dokonale bez emocí a veškeré informace zpracovává pouze logika.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Klidná mysl');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Urychlený metabolismus', 'modifikator', 'četnost ostatních lektvarů/jedů', '-1k10 hodin k četnosti', 'jednorazove', 'okamžité', 'Jednorázové použití.', 0,
'Fus''athal - homebrew (h2085, Sikisovi lektvary). Urychlí metabolizmus škodliviny z jiných lektvarů — zkracuje jejich četnost o 1k10 hodin. Vedlejším efektem je pocit úlevy a tlumení negativních emocí.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Urychlený metabolismus');

-- Napojení lektvar_efekty

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 3, id FROM efekty WHERE nazev = 'Éteričnost';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 4, id FROM efekty WHERE nazev = 'Odolnost vůči ohni (Chladné vody)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 5, id FROM efekty WHERE nazev = 'Mlhová podoba';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 8, id FROM efekty WHERE nazev = 'Ovládnutí lykantropa';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 10, id FROM efekty WHERE nazev = 'Vzkříšení megacloumákem';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 11, id FROM efekty WHERE nazev = 'Lezení po zdech';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 13, id FROM efekty WHERE nazev = 'Bariéra proti ďáblům';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 14, id FROM efekty WHERE nazev = 'Bariéra proti nemrtvým';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 15, id FROM efekty WHERE nazev = 'Bariéra proti neviděným';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 16, id FROM efekty WHERE nazev = 'Bariéra proti démonům';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 17, id FROM efekty WHERE nazev = 'Odolnost proti kouzlům (bariéra)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 25, id FROM efekty WHERE nazev = 'Poškození';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 26, id FROM efekty WHERE nazev = 'Poškození';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 31, id FROM efekty WHERE nazev = 'Léčení';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 32, id FROM efekty WHERE nazev = 'Poškození';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 33, id FROM efekty WHERE nazev = 'Poškození';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 33, id FROM efekty WHERE nazev = 'Zeslabení zbroje (kyselina)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 34, id FROM efekty WHERE nazev = 'Regenerace (lektvar)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 35, id FROM efekty WHERE nazev = 'Poškození';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 36, id FROM efekty WHERE nazev = 'Zesílený sluch';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 37, id FROM efekty WHERE nazev = 'Neviditelnost (lektvar)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 38, id FROM efekty WHERE nazev = 'Noční vidění';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 39, id FROM efekty WHERE nazev = 'Řeč se zvířaty (lektvar)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 47, id FROM efekty WHERE nazev = 'Obnova sil (životy+magy+únava)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 48, id FROM efekty WHERE nazev = 'Obnova sil (životy+magy+únava)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 49, id FROM efekty WHERE nazev = 'Obnova sil (životy+magy+únava)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 55, id FROM efekty WHERE nazev = 'Odolnost vůči chladu (Pouštní vítr)';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 56, id FROM efekty WHERE nazev = 'Klidná mysl';
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id) SELECT 57, id FROM efekty WHERE nazev = 'Urychlený metabolismus';
