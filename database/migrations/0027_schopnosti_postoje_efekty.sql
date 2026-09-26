-- Efekty pro 3 aktivované obranné postoje/pozice — jediné jasně
-- odlišitelné "trvalé stavové" efekty nalezené při průchodu 315
-- schopností/dovedností BEZ kostek (zbytek jsou buď numerická
-- postupová matematika k úrovni bez spouštěče/trvání — Přesnost, Šerm,
-- Cvik apod. — nebo dovednostní kontroly PJ se 4stupňovou tabulkou
-- výsledků — Kovářství, Plavání, Zastrašování apod. — ani jedno není
-- "efekt", který má engine za hry spustit).
--
-- Všechny tři mají stejný tvar jako existující "Berserk (nekontrolovaný
-- vztek)" (id 4, buff/jednorazove) — aktivovaný přepínací stav trvající
-- do splnění jasné ukončovací podmínky, ne kolo od kola:
--   - Postoj šedivého jeřába (id 202): +1 KZ TvT / +2 KZ vrhané-střelené,
--     zranitelnost zezadu/z boku, končí rozběhnutím/zrušením/sražením.
--   - Postoj černého jeřába (id 212): pokročilá verze, +2/+4 KZ,
--     stejné ukončení.
--   - Vějířová obrana (id 218, Mystik): +1 OČ (roste na 12./22. úrovni)
--     proti útokům z výseče před sesilatelem, končí opuštěním hexu.
--
-- hodnota_vzorec/trvani jsou varchar(100) — stručný souhrn, přesné
-- znění (postihy, škálování dle úrovně) je v tooltip_text (TEXT).
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php.

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Postoj šedivého jeřába', 'buff', 'obrana',
       '+1 KZ TvT / +2 KZ vrhané-střelené; zranitelnost zezadu/z boku',
       'jednorazove', 'dokud stojí na nohou / nezruší se',
       'Rozběhnutí, vlastní zrušení, nebo sražení na zem.', 0,
       'Defenzivní bojový postoj Erythena (Novic), 1 víra, 1 kolo vyvolání, 1 únava/3 kola, rozsah 3 hexy před sesilatelem. +1 KZ proti útokům zbraní tváří v tvář, +2 KZ proti vrhaným/střeleným zbraním (stejný postih k vlastnímu útoku ze stoje z postoje). Zranitelnost: −7 OČ při útoku zezadu, −5 OČ z boku. Vyžaduje prázdné ruce a pohyb nejvýše chůzí; lze bez omezení používat disciplíny/kouzlit s prázdnýma rukama.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Postoj šedivého jeřába');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Postoj černého jeřába', 'buff', 'obrana',
       '+2 KZ TvT / +4 KZ vrhané-střelené; zranitelnost zezadu/z boku',
       'jednorazove', 'dokud stojí na nohou / nezruší se',
       'Rozběhnutí, vlastní zrušení, nebo sražení na zem.', 0,
       'Pokročilá verze Postoje šedivého jeřába (Erythen/Novic), 1 víra, 1 kolo vyvolání, 1 únava/4 kola, rozsah 3 hexy před sesilatelem. +2 KZ proti útokům zbraní tváří v tvář, +4 KZ proti vrhaným/střeleným zbraním (stejný postih útočí-li sám z postoje). Zranitelnost: −6 OČ při útoku zezadu, −4 OČ z boku. Lze bez omezení používat disciplíny/kouzlit s prázdnýma rukama.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Postoj černého jeřába');

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Vějířová obrana', 'buff', 'obrana',
       '+1 OČ (+2 od 12.úr, +3 od 22.úr) proti útokům z výseče',
       'jednorazove', 'dokud nevyklidí hex / nezruší se',
       'Opuštění hexu, nebo vlastní zrušení (nestojí akci).', 0,
       'Aktivovaná obranná pozice Mystika (2 akce, vyžaduje zbraň dosahu 2). Soustředí obranu na výseč 3 hexů před sebou až do dohledu; útoky odtud se brání za cenu 1 akce (dvě obrany = 1 akce) s bonusem k OČ, proti střelám navíc ekvivalent malého krytu +1 OČ. Lze srazit běžné projektily (ne nad střední vrhací zbraně/arbalest/balistu). Útočí-li mystik sám z pozice, má −1 ÚČ (na vyšších úrovních roste i tento postih spolu s bonusem). Otáčet se lze, opustit hex ne.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Vějířová obrana');

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Postoj šedivého jeřába')
FROM zvlastni_schopnosti z WHERE z.id = 202;

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Postoj černého jeřába')
FROM zvlastni_schopnosti z WHERE z.id = 212;

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Vějířová obrana')
FROM zvlastni_schopnosti z WHERE z.id = 218;
