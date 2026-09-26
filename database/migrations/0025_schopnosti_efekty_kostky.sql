-- První dávka napojení schopnost_efekty (Poškození/Léčení) — zvláštní
-- schopnosti/dovednosti s kostkami (27 z 342). Stejný postup jako
-- 0022-0024 u kouzel: každý záznam ručně ověřen proti popisu.
--
-- Výsledek téhle dávky je hubený (1 z 27) — u schopností/dovedností
-- totiž pole kostky skoro nikdy neznamená "jednorázové poškození/léčení
-- při použití", jak je to u kouzel. VYNECHÁNO ZÁMĚRNĚ, s důvodem:
--
--   - vůbec nejde o efekt, ale o postup do úrovně (Přírůstek životů —
--     Válečník: 1k10 životů za úroveň je hit-die, ne bojový efekt)
--   - komplexní vícesložková schopnost bez jedné jasné hodnoty
--     (Vržení, Trollobijecký útok, Kapacitní drahokamy, Osm bran)
--   - kostky jsou hod na tabulku/kontrolní hod, ne poškození ani léčení
--     (Odvracení zvířat — hod proti tabulce odvracení; Demonikon —
--     hod na náhodnou vlastnost tvora)
--   - schopnost jen modifikuje CIZÍ léčení (lektvaru), sama neléčí
--     (Posílení kouzel — hvozd; Posílený metabolismus; Trollí
--     metabolizmus — všechny navyšují léčivou hodnotu lektvaru o
--     kostky, nejsou zdrojem léčení samy o sobě)
--   - kostky patří do vzorce odporového/soubojového hodu, ne do přímého
--     poškození (Podrobování, Mentální souboj)
--   - kostky určují trvání efektu nebo obnovu jiného zdroje (víry), ne
--     životy (Zocelení skrze přesvědčení — 4k6 = trvání v kolech;
--     Doplnění víry — 1k6 = obnovená víra, ne životy)
--   - kostky zachycují jen jednu vrstvu formule závislé na cíli
--     (Zardoušení pes — 5k6 + životaschopnost psa, proměnná část chybí)
--
-- Celá kategorie druh='dovednost' (10 řádků: Silný hod, Skok o tyči,
-- Jízda na lyžích, Ošetřování zvířat, Rybaření, Střelba do dálky,
-- Udržování ohně, Držení pozice, Plivání ohně, Maskování v přírodě) má
-- navíc společný problém: text je stavěný jako 4stupňová tabulka hodu
-- (Fatální úspěch/Úspěch/Neúspěch/Fatální neúspěch) a pole kostky bylo
-- historicky vytažené z JEDNÉ konkrétní větve téhle tabulky — většinou
-- "Fatální neúspěch" sebezranění (Skok o tyči, Udržování ohně, Plivání
-- ohně, Maskování), občas "Fatální úspěch" bonus (Silný hod, Střelba
-- do dálky, Ošetřování zvířat), u Rybaření dokonce počet ulovených ryb
-- (vůbec ne životy). Napojit tohle na obecný Poškození/Léčení by bylo
-- věcně špatně — vypadalo by to, že použití dovednosti samo o sobě
-- vždy způsobí zranění/léčení, ačkoliv jde jen o vedlejší důsledek
-- jednoho ze čtyř možných výsledků hodu. Navíc "Jízda na lyžích" a
-- "Držení pozice" navíc míchají "stínové životy" (jiný typ zdraví) a/
-- nebo víc různých hodnot v jedné schopnosti. Tahle celá skupina
-- potřebuje vlastní mechaniku (efekt vázaný na konkrétní výsledek hodu
-- dovednosti), ne 'jednorazove' obecný Poškození/Léčení — samostatný
-- budoucí úkol, stejně jako "ledové zbraně" u kouzel.
--
-- ZAHRNUTO: "Jesle" (torna) — pasivní vlastnost předmětu, ne aktivně
-- používaná schopnost, ale má jasný, jednoznačný, deterministický
-- důsledek při spuštění (roztržení vaku), kostky (10k10+5) přesně
-- sedí na jedinou zmíněnou hodnotu v popisu.
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php.

INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Poškození')
FROM zvlastni_schopnosti z
WHERE z.pocet_kostek IS NOT NULL
  AND z.nazev = 'Jesle';
