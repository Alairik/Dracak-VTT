-- Efekty pro Paladinových 6 aur (schopnost "Aury", id 224) — pasivní
-- energetické pole s dosahem = bonus za charisma, přepínatelné mezi
-- typy, škálované na 16./26./36. úrovni. Stejný princip jako postoje
-- v 0027 (jednorazove = aktivovaný stav trvající do přepnutí/zrušení),
-- tady navíc s trvalým číselným účinkem na OSTATNÍ tvory v dosahu, ne
-- na samotného paladina (mimo Obětování a Světla — u těch text
-- výslovně říká, že se týkají i paladina samého).
--
-- "Překrývající se stejné aury více paladinů: platí jen silnější" —
-- proto stackovatelne=0 u všech (VTT engine by měl při více zdrojích
-- stejné aury použít jen tu se silnější hodnotou, ne sčítat).
--
-- Krátká pole (hodnota_vzorec max 100 znaků) mají jen základní stupeň
-- a odkaz na škálování; přesné hodnoty všech 4 stupňů (základ/16./26./
-- 36. úroveň) jsou v tooltip_text.
--
-- Aura Obětování je oboustranná (snižuje zranění spojence, ale
-- ekvivalent jako stínové zranění dostává sám paladin) — schéma
-- efekty/*_efekty nemá pole pro "dva různé cíle najednou", proto je
-- to popsáno slovně v cil/tooltip_text, ne rozděleno na dva řádky
-- (šlo by to modelovat i jako dva svázané efekty, ale to by vyžadovalo
-- rozhodnutí o mechanice provázání navíc — zatím jeden řádek).
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php.

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Aura Ochrany', 'odolnost', 'životy (spojenci v dosahu)',
       '−1 život ze všech zdrojů zranění (−2/−3/−5 od 16./26./36.úr)',
       'pri_zasahu', 'dokud je aktivní / nepřepne se',
       'Přepnutí paladina na jinou auru, nebo vlastní zrušení.', 0,
       'Paladinova aura (schopnost "Aury"). Dosah = bonus za charizma paladina. Spojenci v dosahu mají zranění z každého zdroje sníženo o 1 (2/3/5 od 16./26./36. úrovně paladina), odečteno jako poslední krok výpočtu zranění. Netýká se samotného paladina. Překrývá-li se stejná aura víc paladinů, platí jen ta se silnější hodnotou (nesčítá se).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Aura Ochrany');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Aura Bolesti', 'zranitelnost', 'životy (nepřátelé v dosahu)',
       '+1 život zranění navíc (+2/+3/+5 od 16./26./36.úr)',
       'pri_zasahu', 'dokud je aktivní / nepřepne se',
       'Přepnutí paladina na jinou auru, nebo vlastní zrušení.', 0,
       'Paladinova aura (schopnost "Aury"). Dosah = bonus za charizma paladina. Nepřátelé v dosahu dostávají o 1 život zranění navíc z každého zásahu (2/3/5 od 16./26./36. úrovně paladina). Netýká se samotného paladina. Překrývá-li se stejná aura víc paladinů, platí jen ta se silnější hodnotou (nesčítá se).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Aura Bolesti');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Aura Soustředění', 'buff', 'šance na seslání/dovednosti (spojenci v dosahu)',
       '+5 % k sesílání/dovednostem, +1 k hodu (+10/15/25 %, +2/3/5 od 16./26./36.úr)',
       'jednorazove', 'dokud je aktivní / nepřepne se',
       'Přepnutí paladina na jinou auru, nebo vlastní zrušení.', 0,
       'Paladinova aura (schopnost "Aury"). Dosah = bonus za charizma paladina. Spojenci v dosahu mají +5 % šanci na úspěch při sesílání kouzel/zlodějských dovednostech/stopování (max. 99 %) a +1 k hodu na dovednosti; mocnější +10/+15/+25 % a +2/+3/+5 od 16./26./36. úrovně paladina. Netýká se samotného paladina. Překrývá-li se stejná aura víc paladinů, platí jen ta se silnější hodnotou (nesčítá se).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Aura Soustředění');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Aura Rozkolu', 'debuff', 'šance na seslání/dovednosti (nepřátelé v dosahu)',
       '−5 % k sesílání, −1 k hodu (−10/15/25 %, −2/3/5 od 16./26./36.úr)',
       'jednorazove', 'dokud je aktivní / nepřepne se',
       'Přepnutí paladina na jinou auru, nebo vlastní zrušení.', 0,
       'Paladinova aura (schopnost "Aury"). Dosah = bonus za charizma paladina. Nepřátelé v dosahu mají −5 % šanci na úspěch při sesílání kouzel (min. 10 %) a −1 k hodu na dovednosti; mocnější −10/−15/−25 % a −2/−3/−5 od 16./26./36. úrovně paladina. Netýká se samotného paladina. Překrývá-li se stejná aura víc paladinů, platí jen ta se silnější hodnotou (nesčítá se).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Aura Rozkolu');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Aura Obětování', 'modifikator', 'přenos zranění: spojenec → paladin (stínové)',
       'spojenci −(až bonus Chr) zranění, paladin +stejně stínového zranění',
       'pri_zasahu', 'dokud je aktivní / nepřepne se',
       'Přepnutí paladina na jinou auru, nebo vlastní zrušení.', 0,
       'Paladinova aura (schopnost "Aury") — na rozdíl od ostatních aur se TÝKÁ i samotného paladina. Dosah = bonus za charizma paladina. Zranění spojence v dosahu je sníženo až o bonus za charizma paladina, paladin sám dostává stejné množství jako stínové zranění. Mocnější (16./26./36.úr): spojenci sníženo o 2×/3×/5× bonus Chr, paladin dostává jen 1/2, 1/3, 1/5 odpovídajícího stínového zranění. Překrývá-li se stejná aura víc paladinů, platí jen ta se silnější hodnotou (nesčítá se).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Aura Obětování');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Aura Světla', 'buff', 'osvětlení (i paladin sám)',
       'svítí jako pochodeň (mocnější: lucerna/měsíc v úplňku/slunce)',
       'jednorazove', 'dokud je aktivní / nepřepne se',
       'Přepnutí paladina na jinou auru, nebo vlastní zrušení.', 0,
       'Paladinova aura (schopnost "Aury") — TÝKÁ se i samotného paladina, ten svítí jako pochodeň. Mocnější (16./26./36.úr): jako lucerna (7 sáhů) / jako měsíc v úplňku (10 sáhů) / jako slunce (20 sáhů), s postihem −1 ÚČ/OČ pro tvory citlivé na světlo v dosahu. Překrývá-li se stejná aura víc paladinů, platí jen ta se silnější hodnotou (nesčítá se).'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Aura Světla');

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, e.id
FROM zvlastni_schopnosti z
CROSS JOIN efekty e
WHERE z.id = 224
  AND e.nazev IN ('Aura Ochrany','Aura Bolesti','Aura Soustředění','Aura Rozkolu','Aura Obětování','Aura Světla');
