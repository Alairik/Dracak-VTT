-- Doplnění zvlastni_schopnosti.past_id pro 2 schopnosti, které mají v
-- tabulce `pasti` už hotový, ale zatím nikam nenapojený záznam
-- (past_id byl NULL, přestože past s odpovídajícím efektem existuje
-- od dřívějška).
--
-- past_id=3 (poznámka: "Neúspěch v hodu proti pasti spouští proměnu v
-- berserkra... od 10. úrovně lze past posunout o 1, od 20. o 2 stupně")
-- odpovídá slovo od slova schopnosti "Změna v berserkra" (id 17):
-- "...propadnout bojovému šílenství... Od 10. úrovně lze past posunout
-- o 1 stupeň, od 20. úrovně o 2 stupně."
--
-- past_id=21 (poznámka: "Past se hází za cíl lichocení (Tulák); čím
-- nižší inteligence cíle, tím snáze uvěří") odpovídá schopnosti
-- "Lichocení" (id 144): "Tulák lichotí osobě... past Int-{6+1/2 Char
-- tuláka}- polichocen/nic."
--
-- Zbylé nenapojené pasti (7, 8, 11, 12, 13) NEJSOU přehlédnuté — mají
-- reálný protějšek ve schopnosti ("Trollobijecký útok" id 51 svazuje
-- 7+8 dohromady se spoustou dalších mechanik; "Líčení pastí" id 80
-- popisuje 6 typů pastí, z toho 11-13 tři z nich), ale past_id je na
-- zvlastni_schopnosti jen JEDNO FK — jedna schopnost nemůže odkazovat
-- na víc různých pastí najednou. Napojit by šlo jen se schématovou
-- změnou (M:N vazba, nebo rozpad těch schopností na dílčí řádky) — to
-- je CREATE/ALTER a navíc rozhodnutí o datovém modelu, patří do
-- konverzace, ne do týhle DML dávky.
--
-- Čisté UPDATE (DML) — mělo by se nasadit samo přes migrate.php.

UPDATE zvlastni_schopnosti SET past_id = 3 WHERE id = 17 AND past_id IS NULL;
UPDATE zvlastni_schopnosti SET past_id = 21 WHERE id = 144 AND past_id IS NULL;
