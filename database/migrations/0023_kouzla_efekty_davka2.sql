-- Druhá dávka napojení kouzlo_efekty (Poškození/Léčení) — Bard,
-- Pamětník, Lovec stínů, Warlock kouzla s kostkami. Stejný postup jako
-- 0022: každé kouzlo ručně ověřeno proti popisu, ne plošně.
--
-- AND k.pocet_kostek IS NOT NULL je bezpečnostní pojistka — "Světlonoš"
-- existuje 2x (id 125 bez kostek, id 651 s kostkami 1k4+2 DoT na
-- nemrtvé) a bez týhle podmínky by se jménem chytlo i to první, které
-- žádné poškození vůbec nemá.
--
-- VYNECHÁNO ZÁMĚRNĚ (další vzory stejné třídy jako v 0022):
--   - bonusové zranění/léčení při zásahu, ne při seslání (Kyselá
--     slova, Runa Oběti I-III, Runa Lékárníka I-III, Temná čepel ×2)
--   - štítový/absorpční efekt, ne přímé poškození (Ochrana múz)
--   - kostky neurčují poškození cíle, ale něco jiného — dobu ochromení
--     (Hlodající vina), vzdálenost přesunu (Dlouhý dech), bonus k
--     hodu (Píseň osudu, Rychlý úder, Záštiplná múza)
--   - kostky zachycují jen jednu z víc různých hodnot v textu,
--     neshoduje se jednoznačně s "tou hlavní" (Zvukový výboj)
--   - komplexní přivolaní se zvláštní vlastní mechanikou útoku
--     (Stínový válečník, Stínový střelec)
--   - čistě podmíněné/vedlejší sebezranění (Bestie, Plamen života,
--     Sláva zesnulých — poslední navíc není poškození vůbec, je to
--     buff spojenců)
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php.

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Poškození')
FROM kouzla k
WHERE k.pocet_kostek IS NOT NULL
  AND k.nazev IN (
    'Trapný fórky','Kvintakord','Brutální zesměšnění','Brnkání na nervy 1','Otravná melodie 2',
    'Zdrcující znělka 3','Lamač srdcí 4','Hromový hlas','Stěna zvuku','Hromada','Nespokojené publikum',
    'Deptající pochybnosti','Zloba kamene','Zuřivost kamene','Kamenná bouře','Kamenná zahrada',
    'Verš ohně I','Verš ohně II','Verš ohně III','Dech větru','Lávová dlaň','Lávová pěst','Titánská pěst',
    'Soud předků','Runa Síry III','Runa Zničení I','Runa Zničení II','Runa Zničení III',
    'Runa Slunce I','Runa Slunce II','Runa Slunce III','Světlonoš','Temná ektoplazma','Lože ostnů',
    'Světlonoš (16. úroveň)','Dusivý stín','Zuby noci','Gravitační studna','Drtivá noc','Noční můra',
    'Černá liška','Slovo moci','Tichá brána','Černá hvězda'
);

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Léčení')
FROM kouzla k
WHERE k.pocet_kostek IS NOT NULL
  AND k.nazev IN ('Drill seržant','Živá voda','Zasloužený odpočinek','Urgentní léčba','Temný lék');
