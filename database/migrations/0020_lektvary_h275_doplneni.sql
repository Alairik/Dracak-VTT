-- Doplnění chybějících položek u core lektvarů z h275 (Alchymistovy
-- zvláštní schopnosti — Lučba), první krok systematického průchodu
-- lektvarů podle textu pravidel (stejný přístup, co u kouzel v 0012).
--
-- 1) Kostky — u tří lektvarů je v knize výslovný rozsah zranění/léčení,
--    který jde bezpečně převést na kostky (u Bomby je "3k6+3" přímo v
--    textu, u zbylých dvou diff dělitelný 5 → k6, dle stejného
--    algoritmu jako 0012):
--      - Lektvar rudého kříže (7): "Vyléčí 3–8 životů" -> 1k6+2
--      - Bomba (25): "6–21 (3k6+3) životů"            -> 3k6+3
--      - Ohnivá hlína (26): "zraní za 6–11 životů"     -> 1k6+5
--
-- 2) Past — dva svitky mají v textu výslovnou pevnou past ("Past Roz
--    ~10~ projde/neprojde", "Past Roz ~8~ projde/neprojde"), na kterou
--    ale v `pasti` nic neodpovídalo (tabulka má jen pasti u konkrétních
--    schopností/kouzel). Založeny 2 nové řádky a napojeny přes past_id.
--    efekt_uspech_id/efekt_neuspech_id necháno NULL — "projde/neprojde"
--    tu znamená jen prolomení bariéry svitku, ne herní efekt z `efekty`
--    (ten popisuje sám svitek, viz jeho popis).
--
-- Čisté UPDATE/INSERT (DML) — mělo by se nasadit samo přes migrate.php.

UPDATE lektvary SET pocet_kostek = 1, typ_kostky = 'k6', pevny_bonus = 2
WHERE id = 7 AND nazev = 'Lektvar rudého kříže';

UPDATE lektvary SET pocet_kostek = 3, typ_kostky = 'k6', pevny_bonus = 3
WHERE id = 25 AND nazev = 'Bomba';

UPDATE lektvary SET pocet_kostek = 1, typ_kostky = 'k6', pevny_bonus = 5
WHERE id = 26 AND nazev = 'Ohnivá hlína';

INSERT INTO pasti (vlastnosti, obtiznost, poznamka) VALUES
('Roz', '10', 'Past pro svitek "Ochrana před nemrtvými" (h275) — neúspěch tvora znamená prolomení bariéry svitku.'),
('Roz', '8', 'Past pro svitek "Ochrana před neviděnými" (h275) — neúspěch tvora znamená prolomení bariéry svitku.');

UPDATE lektvary l
JOIN pasti p ON p.poznamka LIKE '%Ochrana před nemrtvými%'
SET l.past_id = p.id
WHERE l.id = 14 AND l.nazev = 'Svitek: Ochrana před nemrtvými';

UPDATE lektvary l
JOIN pasti p ON p.poznamka LIKE '%Ochrana před neviděnými%'
SET l.past_id = p.id
WHERE l.id = 15 AND l.nazev = 'Svitek: Ochrana před neviděnými';
