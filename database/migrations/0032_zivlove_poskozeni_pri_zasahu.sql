-- Shluk "bonusové zranění/léčení při zásahu" vynechaný v 0022 (jiná
-- mechanika aplikace než jednorázové seslání — spouští se při úspěšném
-- zásahu zbraní/útokem, ne při vlastním seslání kouzla). Dva obecné,
-- znovupoužitelné efekty (stejný princip jako Poškození/Léčení):
--
-- "Živlové posílení zranění (při zásahu)" — kouzlo/schopnost dočasně
-- posílí útok (vlastní zbraň, kopí přivolané bytosti, nebo cizí útok
-- skrze runu) o bonusové zranění, které se připočte při úspěšném
-- zásahu. Napojeno: Ledová čepel, Ohnivý břit, Kyselá slova, Zlaté
-- kopí, Obsidiánové kopí, Posvátný plamen, Avatar světla, Avatar
-- temnoty, Hromosvod, Temná čepel (obě úrovně), Runa Oběti I-III.
--
-- "Živlové posílení léčení (při zásahu)" — stejný princip, ale pro
-- Runu Lékárníka I-III, kde se při neúspěšné obraně cíle připočte
-- bonusové LÉČENÍ (ne zranění) stejné povahy jako zdroj.
--
-- U několika kouzel text zmiňuje DALŠÍ podmíněný bonus vůči
-- konkrétnímu typu cíle (např. Avatar světla +5 navíc proti nemrtvým,
-- Zlaté/Obsidiánové kopí bonus proti nemrtvým/andělům) — kostky pole
-- zachycuje jen základní/nejčastější hodnotu, podmíněný bonus navíc
-- je zdokumentován v tooltip_text, ne modelován zvlášť (stejný princip
-- jako "Nebeský oheň" v 0024).
--
-- OPRAVA DŘÍVĚJŠÍHO ZÁVĚRU (0022): "Sněhová koule" NENÍ datová chyba
-- (kostky 1k8 vs. text 1k4+2) — SZ řádek "2/0 +1k8 ledem" je vlastní
-- statistika hozeného projektilu (přesně sedí s kostky polem, stejná
-- kategorie jako Bomba/Ohnivá hlína), věta "zraní za dalších 1k4+2
-- ledem" je patrně redundantní/matoucí formulace ve zdroji popisující
-- tentýž zásah jinými slovy, ne druhá oddělená dávka poškození — proto
-- napojeno na obecné Poškození s kostky beze změny, ne "opraveno".
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php.

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Živlové posílení zranění (při zásahu)', 'buff', 'poškození při úspěšném zásahu', 'dle kostek kouzla/dovednosti (viz pocet_kostek/typ_kostky/pevny_bonus)', 'pri_zasahu', 'dle kouzla/dovednosti', 'Uplynutí trvání, zrušení, nebo odložení/upuštění zbraně.', 0,
'Obecný efekt bonusového zranění připočteného při úspěšném zásahu (posílená zbraň, přivolané kopí, nebo cizí útok skrze runu) — na rozdíl od "Poškození" se neaplikuje při seslání, ale při každém dalším úspěšném zásahu po dobu trvání. Konkrétní kostky bere engine z vlastního záznamu kouzla/dovednosti.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Živlové posílení zranění (při zásahu)');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Živlové posílení léčení (při zásahu)', 'hot', 'léčení při aplikaci (neúspěšná obrana cíle)', 'dle kostek kouzla (viz pocet_kostek/typ_kostky/pevny_bonus)', 'pri_zasahu', 'dle kouzla', 'Jednorázová aplikace při neúspěchu cíle v hodu proti pasti.', 0,
'Obecný efekt bonusového léčení, které cíl obdrží, pokud neuspěje v hodu proti pasti (runa vnucuje léčení stejné povahy jako zdroj). Konkrétní kostky bere engine z vlastního záznamu kouzla.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Živlové posílení léčení (při zásahu)');

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Živlové posílení zranění (při zásahu)')
FROM kouzla k
WHERE k.id IN (333,182,405,760,761,768,788,789,948,641,653,552,553,554);

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Živlové posílení léčení (při zásahu)')
FROM kouzla k
WHERE k.id IN (555,556,557);

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Poškození')
FROM kouzla k
WHERE k.id = 166;
