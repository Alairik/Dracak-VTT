-- První dávka napojení kouzla_efekty — obecné, znovupoužitelné efekty
-- Poškození/Léčení pro kouzla, kde je to jasné a jednoznačné (viz
-- konverzace — efekty musí engine skutečně umět spustit, ne jen
-- popisovat, a musí se procházet záznam po záznamu, ne hromadně
-- podle vzorce).
--
-- Založeno na ručním ověření: u každého z níže napojených kouzel jsem
-- porovnal pole kostky (pocet_kostek/typ_kostky/pevny_bonus) se
-- skutečným textem popisu — číslo sedí, žádná podmíněnost/komplikace
-- (bonusové zranění při zásahu, iluzorní zranění, poškození dělené
-- mezi víc cílů podle vzorce, sebezranění sesilatele apod.).
--
-- VYNECHÁNO ZÁMĚRNĚ (ne přehlédnuto):
--   - kouzla s "bonusovým poškozením při zásahu" (Ledová čepel, Ohnivý
--     břit, Staccato/Legato, celý shluk "ledových zbraní" 163-168) —
--     jiná aplikace (při zásahu, ne při seslání), navíc u "Sněhová
--     koule" (166) jsem našel kostky (1k8) neodpovídající textu
--     (1k4+2) — celý tenhle shluk potřebuje zvlášť opravit kostky,
--     než se bude linkovat na efekt.
--   - kouzla, kde kostky neurčují přímo způsobené zranění (Zmatek,
--     Zabij — určují postiženou životaschopnost, ne zranění; Trojitý
--     sek — bonus k útokům, kostky podezřele nesedí k popisu vůbec;
--     Ledové vězení — délka ochromení, ne zranění; Ledové zrcadlo —
--     sebezranění při zničení posledního zrcadla, vedlejší efekt).
--   - kouzla se scalující/vícesložkovou formulí, kde pole kostky
--     zachycuje jen jednu vrstvu (Sloup ohně — škáluje dle velikosti
--     cíle, pole má jen základní A0 tier).
--
-- Zbytek kouzel s kostkami (cca 180) a všechny dovednosti (342, žádná
-- zatím nemá efekt) čekají na další dávky stejným postupem.
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php.

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, tooltip_text)
SELECT 'Poškození', 'debuff', 'životy', 'dle kostek kouzla/dovednosti (viz pocet_kostek/typ_kostky/pevny_bonus)', 'jednorazove', 'okamžité',
       'Obecný efekt přímého poškození — konkrétní kostky bere engine z vlastního záznamu kouzla/dovednosti/lektvaru, ne odsud.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Poškození');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, tooltip_text)
SELECT 'Léčení', 'hot', 'životy', 'dle kostek kouzla/dovednosti (viz pocet_kostek/typ_kostky/pevny_bonus)', 'jednorazove', 'okamžité',
       'Obecný efekt vyléčení — konkrétní kostky bere engine z vlastního záznamu kouzla/dovednosti/lektvaru, ne odsud.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Léčení');

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Poškození')
FROM kouzla k
WHERE k.nazev IN (
    'Modré blesky','Ohnivý déšť','Zelené blesky','Bílá střela','Bílý blesk','Černý blesk',
    'Ohnivá koule','Rudé blesky','Žluté blesky','Bílý blesk kulový','Bledý blesk',
    'Bledý blesk kulový','Černý blesk kulový','Ledový blesk','Ohnivý bič','Zmrzlý blesk',
    'Ledovy dech','Absolutní nula','Ohnivá střela','Duchové ohně','Katapult',
    'Spalující paprsek','Slzy slunce','Magma','Úder varování','Úder zloby','Úder nenávisti',
    'Smrtící šíp'
);

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Léčení')
FROM kouzla k
WHERE k.nazev IN (
    'Uzdrav lehká zranění','Uzdrav těžká zranění','Ošetři zranění chladem',
    'Ošetři zranění kyselinou','Ošetři zranění ohněm'
);
