-- Doplnění jedů, protijedů a PPE lektvarů, které migrace 0029
-- označila jako "text efektu není součástí HTML zdroje" -- ukázalo se to jako
-- nesprávné závěr: plný popisný text existuje v content/pravidla-hrac.html
-- (sekce h286 "Jedy a protijedy" + následující PPE rozdil), jen nikdy nebyl
-- vytăžen do sloupce popis. Stejná chyba jako u kouzla.rozsah (0021).
--
-- Mechanika jedů: past se hází proti Odolnosti, obtížnost = "nebezpečnost"
-- jedu (potvrzeno textem: "...jeho nebezpečností a vlastností, na niž se jed
-- vztahuje (není-li řečeno jinak, jedná se o odolnost)"). Formát pole "síla: X/Y"
-- přesně není v dostupném zdroji rozepsán do vzorce (na rozdíl od "trvání",
-- kde je X/Y = působení/prodleva výslovně vysvětleno) -- ponecháno doslova
-- v hodnota_vzorec, ne dopočítáváno/hádáno, tooltip_text nese celý potvrzený
-- mechanický text z knihy.
--
-- Protěji jedy proti nimů nemají vlastní past -- aplikují se přímo a upravují
-- "nebezpečnost"/"síla" původního jedu (viz b3202), typ='modifikator'.
--
-- Nedohledáno ani nyní, ponecháno vynechané: "Dlouhý život" (jed) -- i jeho
-- vlastní protijed "Sladký život" na něj jen odkazuje beze slov ("popsáno u
-- jedu dlouhý život"), tenhle odkazovaný text se nikde nenachází. "Rozpouštěč"
-- má v knize jen odkaz "viz alchimistické novoty" bez vlastního textu.
-- "Malé oko" a "Pravdomluv" mají jen částečně potvrzený efekt (z textu jejich
-- vlastních protijedů), bez přesné síly/nebezpečnosti -- zahrnuto jako
-- neúplné, výslovně tak označeno v tooltip_text.
--
-- Čisté INSERT/UPDATE (DML) -- mělo by se nasadit samo přes migrate.php.

UPDATE lektvary SET popis = 'Bolehlav — magenergie: žádná suroviny: 2 zl základ: bolehlav nalezení: 3 × 50 % trvání: 1 hodina/12 hodin výroba: 1 hodina nebezpečnost: 10 síla: ochrnutí + 1–10/11–20 použití: požití barva/chuť/zápach: 5/80/0 Bolehlav je asi metr vysoká rostlina s dutým stvolem, která se poněkud podobá mrkvi. Roste celkem běžně v mírném podnebí, kde je možné ho se značnou pravděpodobností nalézt. Na výrobu jedné dávky jsou zapotřebí alespoň tři rostliny. Nasbíraná plodina se pak musí nejméně tři měsíce sušit a připravovat. Prášek z bolehlavu se přidává do nápojů nebo jídla, má žlutavou až lehce nahnědlou barvu, výrazně hořkou chuť a je prakticky bez zápachu. Účinkuje zhruba za 12 hodin po požití, ztráta životů se pak projeví během jedné hodiny od prvních příznaků otravy. Ochrnutí pomine po 12 hodinách od prvních příznaků a nelze ho odstranit lektvarem megacloumák.'
  WHERE id = 58 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Bolehlav)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: ochrnutí + 1–10/11–20', 'jednorazove', '1 hodina/12 hodin', 'Uplynutí trvání dle knihy.', 0,
'Bolehlav je asi metr vysoká rostlina s dutým stvolem, která se poněkud podobá mrkvi. Roste celkem běžně v mírném podnebí, kde je možné ho se značnou pravděpodobností nalézt. Na výrobu jedné dávky jsou zapotřebí alespoň tři rostliny. Nasbíraná plodina se pak musí nejméně tři měsíce sušit a připravovat. Prášek z bolehlavu se přidává do nápojů nebo jídla, má žlutavou až lehce nahnědlou barvu, výrazně hořkou chuť a je prakticky bez zápachu. Účinkuje zhruba za 12 hodin po požití, ztráta životů se pak projeví během jedné hodiny od prvních příznaků otravy. Ochrnutí pomine po 12 hodinách od prvních příznaků a nelze ho odstranit lektvarem megacloumák. (nebezpečnost pasti: 10. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Bolehlav)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '10', (SELECT id FROM efekty WHERE nazev = 'Otrava (Bolehlav)'),
'Jed Bolehlav (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Bolehlav %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Bolehlav %')
  WHERE id = 58 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 58, id FROM efekty WHERE nazev = 'Otrava (Bolehlav)';

UPDATE lektvary SET popis = 'Digitalis — magenergie: žádná suroviny: 1 zl základ: náprstník + voda nalezení: 1 × 20 % trvání: 1 směna/3 směny výroba: 6 hodin nebezpečnost: 7 síla: ochrnutí + 13–28/23–38 použití: požití barva/chuť/zápach: 10/5/5 Náprstník je bylina dorůstající výšky až 150 coulů. Má střídavé, podlouhlé listy dlouhé až 30 coulů. Květy jsou zvonkovité, žluté nebo bílé a obrůstají vzpřímený stonek. Roste na mýtinách, na spáleništích a na suchých horských pastvinách. Jed z jeho květů se sušením a přípravou dosahuje nejméně 3 měsíce. Výtažek z náprstníku je slabě nazelenalá nebo nahnědlá čirá kapalina, téměř bez chuti a zápachu (připomíná velmi slabý čaj). Otrava se projeví mělkým dýcháním, zpomalením srdečního tepu a ochrnutím. To má kromě ztráty životů za následek postih na útok a na obranu a při hodech proti pasti na obratnost a inteligenci. Velikost postihu zjistíš, vydělíš-li pěti (zaokrouhluj nahoru) počet životů, o které postižený kvůli jedu přišel. Postihy pominou až za 6 hodin po prvních příznacích a nelze je odstranit lektvarem megacloumák.'
  WHERE id = 59 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Digitalis)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: ochrnutí + 13–28/23–38', 'jednorazove', '1 směna/3 směny', 'Uplynutí trvání dle knihy.', 0,
'Náprstník je bylina dorůstající výšky až 150 coulů. Má střídavé, podlouhlé listy dlouhé až 30 coulů. Květy jsou zvonkovité, žluté nebo bílé a obrůstají vzpřímený stonek. Roste na mýtinách, na spáleništích a na suchých horských pastvinách. Jed z jeho květů se sušením a přípravou dosahuje nejméně 3 měsíce. Výtažek z náprstníku je slabě nazelenalá nebo nahnědlá čirá kapalina, téměř bez chuti a zápachu (připomíná velmi slabý čaj). Otrava se projeví mělkým dýcháním, zpomalením srdečního tepu a ochrnutím. To má kromě ztráty životů za následek postih na útok a na obranu a při hodech proti pasti na obratnost a inteligenci. Velikost postihu zjistíš, vydělíš-li pěti (zaokrouhluj nahoru) počet životů, o které postižený kvůli jedu přišel. Postihy pominou až za 6 hodin po prvních příznacích a nelze je odstranit lektvarem megacloumák. (nebezpečnost pasti: 7. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Digitalis)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '7', (SELECT id FROM efekty WHERE nazev = 'Otrava (Digitalis)'),
'Jed Digitalis (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Digitalis %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Digitalis %')
  WHERE id = 59 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 59, id FROM efekty WHERE nazev = 'Otrava (Digitalis)';

UPDATE lektvary SET popis = 'Dýmovka — magenergie: 1 mag suroviny: 55 zl základ: pýchavka nalezení: 1 × 5 % trvání: ihned/1 kolo výroba: 10 kol nebezpečnost: 4 síla: 1–6/12 použití: vdechnutí barva/chuť/zápach: 70/–/15 Dýmovka je šedá koule velikosti a tvaru ohnivé hlíny, která po dopadu místo exploze uvolní velké množství šedého dusivého dýmu s lehkým uhelným pachem. Postavy ve vzdálenosti 2 sáhů od místa dopadu si musí hodit proti pasti uvedené v popisu. Kromě toho i při úspěchu mají tyto postihy: postavy vzdálené 1 sáh a méně mají na 1–3 kola postih −3 k hodům na útok a na obranu; postavy vzdálené 1 až 2 sáhy mají na 1–3 kola postih −1 k hodům na útok a na obranu. Dýmovka vydrží 32 až 37 dní, pak zvlhne.'
  WHERE id = 60 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Dýmovka)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: 1–6/12', 'jednorazove', 'ihned/1 kolo', 'Uplynutí trvání dle knihy.', 0,
'Dýmovka je šedá koule velikosti a tvaru ohnivé hlíny, která po dopadu místo exploze uvolní velké množství šedého dusivého dýmu s lehkým uhelným pachem. Postavy ve vzdálenosti 2 sáhů od místa dopadu si musí hodit proti pasti uvedené v popisu. Kromě toho i při úspěchu mají tyto postihy: postavy vzdálené 1 sáh a méně mají na 1–3 kola postih −3 k hodům na útok a na obranu; postavy vzdálené 1 až 2 sáhy mají na 1–3 kola postih −1 k hodům na útok a na obranu. Dýmovka vydrží 32 až 37 dní, pak zvlhne. (nebezpečnost pasti: 4. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Dýmovka)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '4', (SELECT id FROM efekty WHERE nazev = 'Otrava (Dýmovka)'),
'Jed Dýmovka (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Dýmovka %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Dýmovka %')
  WHERE id = 60 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 60, id FROM efekty WHERE nazev = 'Otrava (Dýmovka)';

UPDATE lektvary SET popis = 'Otrušík — magenergie: žádná suroviny: 1 zl základ: kyz arzénu nalezení: 2 × 10 % trvání: 3 směny/6 hodin výroba: 2 dny, doma nebezpečnost: 11 síla: ochrnutí + 11–20/16–25 použití: požití barva/chuť/zápach: 60/0/0 Otrušík sam je červený prášek, bez chuti a zápachu a zhruba po 6 hodinách po požití způsobuje záchvaty kašle a ochrnutí. To se projeví postihem na útok, na obranu a na obratnost. Velikost postihu zjistíš, vydělíš-li pěti (zaokrouhluj nahoru) počet životů, o které postižený kvůli jedu přišel. Ochrnutí pomine po 3 hodinách od prvních příznaků. Lektvar megacloumák jednorázově sníží postihy na ochrnutí na polovinu (zaokrouhluj nahoru), druhé a další použití megacloumáku na účinky této dávky otrušíku nemá vliv.'
  WHERE id = 61 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Otrušík)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: ochrnutí + 11–20/16–25', 'jednorazove', '3 směny/6 hodin', 'Uplynutí trvání dle knihy.', 0,
'Otrušík sam je červený prášek, bez chuti a zápachu a zhruba po 6 hodinách po požití způsobuje záchvaty kašle a ochrnutí. To se projeví postihem na útok, na obranu a na obratnost. Velikost postihu zjistíš, vydělíš-li pěti (zaokrouhluj nahoru) počet životů, o které postižený kvůli jedu přišel. Ochrnutí pomine po 3 hodinách od prvních příznaků. Lektvar megacloumák jednorázově sníží postihy na ochrnutí na polovinu (zaokrouhluj nahoru), druhé a další použití megacloumáku na účinky této dávky otrušíku nemá vliv. (nebezpečnost pasti: 11. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Otrušík)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '11', (SELECT id FROM efekty WHERE nazev = 'Otrava (Otrušík)'),
'Jed Otrušík (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Otrušík %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Otrušík %')
  WHERE id = 61 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 61, id FROM efekty WHERE nazev = 'Otrava (Otrušík)';

UPDATE lektvary SET popis = 'Puchčoud — magenergie: žádná suroviny: 25 zl základ: ledek (5 st) nalezení: 2 × 30 % trvání: ihned/1 kolo výroba: 5 směn doma nebezpečnost: 8 síla: 1–6/3–18 použití: vdechnutí barva/chuť/zápach: 120/–/120 Puchčoud je šedavý prášek, z něhož se při styku se vzduchem začne vyvíjet hustý žlutavý dusivý dým páchnoucí po zkažených vejcích. Zpravidla se během jednoho kola rozšíří do oblaku o poloměru asi 3 sáhy a kromě toho, že způsobuje zranění, ho lze využít i jako kouřovou clonu.'
  WHERE id = 62 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Puchčoud)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: 1–6/3–18', 'jednorazove', 'ihned/1 kolo', 'Uplynutí trvání dle knihy.', 0,
'Puchčoud je šedavý prášek, z něhož se při styku se vzduchem začne vyvíjet hustý žlutavý dusivý dým páchnoucí po zkažených vejcích. Zpravidla se během jednoho kola rozšíří do oblaku o poloměru asi 3 sáhy a kromě toho, že způsobuje zranění, ho lze využít i jako kouřovou clonu. (nebezpečnost pasti: 8. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Puchčoud)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '8', (SELECT id FROM efekty WHERE nazev = 'Otrava (Puchčoud)'),
'Jed Puchčoud (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Puchčoud %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Puchčoud %')
  WHERE id = 62 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 62, id FROM efekty WHERE nazev = 'Otrava (Puchčoud)';

UPDATE lektvary SET popis = 'Slzný plyn — magenergie: 2 magy suroviny: 26 zl základ: šťáva z cibule (1 měďák) trvání: ihned/1 až 3 kola (viz níže) výroba: 4 směny nebezpečnost: 9 síla: slzení po dobu 1 směny/silné slzení 4–6 směn použití: dotyk (pouze při vniknutí do očí) barva/chuť/zápach: 15/–/80 Slzný plyn má poměrně vysokou nebezpečnost, působí však jen tehdy, dostane-li se do očí. Pokud oběť proti jedu uspěje, slzí jen po dobu jedné směny a má postih −10 % na útok i obranu, −1 k hodům proti postřeh a −1 k hodům proti pasti na obratnost. Pokud oběť neuspěje, slzí mnohem intenzivněji a déle.'
  WHERE id = 63 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Slzný plyn)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: slzení po dobu 1 směny/silné slzení 4–6 směn', 'jednorazove', 'ihned/1 až 3 kola', 'Uplynutí trvání dle knihy.', 0,
'Slzný plyn má poměrně vysokou nebezpečnost, působí však jen tehdy, dostane-li se do očí. Pokud oběť proti jedu uspěje, slzí jen po dobu jedné směny a má postih −10 % na útok i obranu, −1 k hodům proti postřeh a −1 k hodům proti pasti na obratnost. Pokud oběť neuspěje, slzí mnohem intenzivněji a déle. (nebezpečnost pasti: 9. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Slzný plyn)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '9', (SELECT id FROM efekty WHERE nazev = 'Otrava (Slzný plyn)'),
'Jed Slzný plyn (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Slzný plyn %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Slzný plyn %')
  WHERE id = 63 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 63, id FROM efekty WHERE nazev = 'Otrava (Slzný plyn)';

UPDATE lektvary SET popis = 'Svrbík — magenergie: 3 magy suroviny: 33 zl základ: mletá křída (3 st) trvání: ihned/3 kola výroba: 3 směny nebezpečnost: 6 síla: svědění zasažených míst po dobu 1 směny/4–6 životů, svědění celého těla po dobu 1–3 směn použití: dotyk barva/chuť/zápach: 40/20/5 Svrbík je velmi jemný bílý až nažloutlý prášek, který s oblíbou využívají různí vtipálkové ke svým žertikům. Zasažené části těla je třeba co nejdříve omýt (stačí vodou), jinak se doba svědění patřičně prodlouží.'
  WHERE id = 64 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Svrbík)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: svědění zasažených míst po dobu 1 směny/4–6 životů, celé tělo 1–3 směny', 'jednorazove', 'ihned/3 kola', 'Uplynutí trvání dle knihy.', 0,
'Svrbík je velmi jemný bílý až nažloutlý prášek, který s oblíbou využívají různí vtipálkové ke svým žertikům. Zasažené části těla je třeba co nejdříve omýt (stačí vodou), jinak se doba svědění patřičně prodlouží. (nebezpečnost pasti: 6. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Svrbík)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '6', (SELECT id FROM efekty WHERE nazev = 'Otrava (Svrbík)'),
'Jed Svrbík (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Svrbík %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Svrbík %')
  WHERE id = 64 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 64, id FROM efekty WHERE nazev = 'Otrava (Svrbík)';

UPDATE lektvary SET popis = 'Amanitin — magenergie: žádná suroviny: žádné základ: muchomůrka zelená nalezení: 1 × 5 % trvání: 12 hodin/12 hodin výroba: 1 směna nebezpečnost: 10 síla: 33–60/53–80 použití: požití barva/chuť/zápach: 15/60/10 Otrava se projeví asi dvě hodiny po požití žaludečními obtížemi, které však po čase ustanou. Skutečně začne působit až o deset hodin později, kdy se otrava projeví dávěním, ochrnutím a smrtí. Proti tomuto jedu neexistuje protijed.'
  WHERE id = 65 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Amanitin)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: 33–60/53–80', 'jednorazove', '12 hodin/12 hodin', 'Uplynutí trvání dle knihy.', 0,
'Otrava se projeví asi dvě hodiny po požití žaludečními obtížemi, které však po čase ustanou. Skutečně začne působit až o deset hodin později, kdy se otrava projeví dávěním, ochrnutím a smrtí. Proti tomuto jedu neexistuje protijed. (nebezpečnost pasti: 10. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Amanitin)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '10', (SELECT id FROM efekty WHERE nazev = 'Otrava (Amanitin)'),
'Jed Amanitin (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Amanitin %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Amanitin %')
  WHERE id = 65 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 65, id FROM efekty WHERE nazev = 'Otrava (Amanitin)';

UPDATE lektvary SET popis = 'Belladona — magenergie: žádná suroviny: 1 zl základ: rulík zlomocný nalezení: 1 × 20 % trvání: 3 směny/1 hodina výroba: 4 hod nebezpečnost: 8 síla: 23–38/33–48 použití: požití barva/chuť/zápach: 20/75/0 Otrava rulíkem se pozná zejména podle rozšířených zornic oběti – takto se projeví už po jedné směně od požití, zatímco skutečné příznaky otravy nastanou až po jedné hodině.'
  WHERE id = 66 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Belladona)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: 23–38/33–48', 'jednorazove', '3 směny/1 hodina', 'Uplynutí trvání dle knihy.', 0,
'Otrava rulíkem se pozná zejména podle rozšířených zornic oběti – takto se projeví už po jedné směně od požití, zatímco skutečné příznaky otravy nastanou až po jedné hodině. (nebezpečnost pasti: 8. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Belladona)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '8', (SELECT id FROM efekty WHERE nazev = 'Otrava (Belladona)'),
'Jed Belladona (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Belladona %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Belladona %')
  WHERE id = 66 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 66, id FROM efekty WHERE nazev = 'Otrava (Belladona)';

UPDATE lektvary SET popis = 'Dopisní jed — magenergie: žádná suroviny: 75 zl základ: med trvání: 6 kol/1 směna výroba: 3 dny, doma nebezpečnost: 5 síla: 1–6/2–12 použití: dotyk barva/chuť/zápach: 35/25/0 Tento jed se používá na otrávení dopisů, knih nebo listin. Aby jed účinkoval, oběť se ho musí dotýkat alespoň jednu směnu.'
  WHERE id = 67 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Dopisní jed)', 'debuff', 'životy/ochromení (dle jedu)', 'síla: 1–6/2–12', 'jednorazove', '6 kol/1 směna', 'Uplynutí trvání dle knihy.', 0,
'Tento jed se používá na otrávení dopisů, knih nebo listin. Aby jed účinkoval, oběť se ho musí dotýkat alespoň jednu směnu. (nebezpečnost pasti: 5. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Dopisní jed)');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '5', (SELECT id FROM efekty WHERE nazev = 'Otrava (Dopisní jed)'),
'Jed Dopisní jed (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Dopisní jed %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Dopisní jed %')
  WHERE id = 67 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 67, id FROM efekty WHERE nazev = 'Otrava (Dopisní jed)';

UPDATE lektvary SET popis = 'Moucha (jed) — magenergie: 6 magů suroviny: 45 zl základ: bobulky z jmelí trvání: ihned/6 kol výroba: 6 směn nebezpečnost: 8 síla: částečné ochromení na 1–6 směn / úplné ochromení na 7–12 směn použití: dotyk barva/chuť/zápach: 10/80/50 Jed způsobí pocit slabosti spojený s ochromením. Při částečném ochromení je postava schopna pomalé chůze, ale postih −5 k boji. Při úplném ochromení se zhroutí k zemi a jedná se o vyřazenou postavu. Proti mouze pomáhá například megacloumák, který úplné ochromení zmírní na částečné a částečné odstraní.'
  WHERE id = 68 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Otrava (Moucha (jed))', 'debuff', 'životy/ochromení (dle jedu)', 'síla: částečné ochromení 1–6 směn / úplné ochromení 7–12 směn', 'jednorazove', 'ihned/6 kol', 'Uplynutí trvání dle knihy.', 0,
'Jed způsobí pocit slabosti spojený s ochromením. Při částečném ochromení je postava schopna pomalé chůze, ale postih −5 k boji. Při úplném ochromení se zhroutí k zemi a jedná se o vyřazenou postavu. Proti mouze pomáhá například megacloumák, který úplné ochromení zmírní na částečné a částečné odstraní. (nebezpečnost pasti: 8. Přesný význam rozdělení síly na dvě hodnoty před/za lomenyďarou není v dostupném zdroji vysvětlen -- ponecháno doslova, nedopočítáváno.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Otrava (Moucha (jed))');

INSERT INTO pasti (vlastnosti, obtiznost, efekt_neuspech_id, poznamka)
SELECT 'Odolnost', '8', (SELECT id FROM efekty WHERE nazev = 'Otrava (Moucha (jed))'),
'Jed Moucha (jed) (h286/PPE) -- past proti Odolnosti, obtížnost = nebezpečnost jedu.'
WHERE NOT EXISTS (SELECT 1 FROM pasti WHERE poznamka LIKE 'Jed Moucha (jed) %');

UPDATE lektvary SET past_id = (SELECT id FROM pasti WHERE poznamka LIKE 'Jed Moucha (jed) %')
  WHERE id = 68 AND past_id IS NULL;

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 68, id FROM efekty WHERE nazev = 'Otrava (Moucha (jed))';

-- Protijedy -- typ='modifikator', upravují nebezpečnost/sílu původního jedu

UPDATE lektvary SET popis = 'Eraruk — jed: Kurare magenergie: 3 magy suroviny: 145 zl základ: pryskyřice nalezení: 4 × 30 % trvání: 6 hodin/1 kolo výroba: 1 den, doma nebezpečnost: 0 síla: 0/snížení na jednu desetinu použití: požití barva/chuť/zápach: 85/30/45 Eraruk je tmavohnědá až téměř černá neprůhledná, ale nikoli hustá kapalina lehce nahořklé chuti a s vůní čerstvého jehličí.'
  WHERE id = 72 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Kurare (Eraruk)', 'modifikator', 'nebezpečnost/síla jedu Kurare', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '6 hodin/1 kolo', 'Aplikace protijedu.', 0,
'Eraruk je tmavohnědá až téměř černá neprůhledná, ale nikoli hustá kapalina lehce nahořklé chuti a s vůní čerstvého jehličí. Statistiky protijedu: magenergie: 3 magy suroviny: 145 zl základ: pryskyřice nalezení: 4 × 30 % trvání: 6 hodin/1 kolo výroba: 1 den, doma nebezpečnost: 0 síla: 0/snížení na jednu desetinu použití: požití barva/chuť/zápach: 85/30/45'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Kurare (Eraruk)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 72, id FROM efekty WHERE nazev = 'Protijed proti jedu Kurare (Eraruk)';

UPDATE lektvary SET popis = 'Melanina památka — jed: Melenova pomsta magenergie: 1 mag suroviny: 88 zl základ: rajská šťáva trvání: 2 hodiny/1 směna výroba: 3 hodiny, doma nebezpečnost: −5 síla: neutralizace/snížení na polovinu použití: požití barva/chuť/zápach: 75/65/25 Hustý červený sladkokyselý sirup s nevýraznou vůní po rajčatech.'
  WHERE id = 73 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Melenova pomsta (Melanina památka)', 'modifikator', 'nebezpečnost/síla jedu Melenova pomsta', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '2 hodiny/1 směna', 'Aplikace protijedu.', 0,
'Hustý červený sladkokyselý sirup s nevýraznou vůní po rajčatech. Statistiky protijedu: magenergie: 1 mag suroviny: 88 zl základ: rajská šťáva trvání: 2 hodiny/1 směna výroba: 3 hodiny, doma nebezpečnost: −5 síla: neutralizace/snížení na polovinu použití: požití barva/chuť/zápach: 75/65/25'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Melenova pomsta (Melanina památka)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 73, id FROM efekty WHERE nazev = 'Protijed proti jedu Melenova pomsta (Melanina památka)';

UPDATE lektvary SET popis = 'Šmolková sůl — jed: Jablečná vůně magenergie: 1 mag suroviny: 38 zl základ: sůl trvání: 3 směny/1 kolo výroba: 3 směny nebezpečnost: −5 síla: 0 použití: dotyk (šňupání) barva/chuť/zápach: 95/65/85 Při včasném použití snižuje nebezpečnost jablečné vůně o 5.'
  WHERE id = 74 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Jablečná vůně (Šmolková sůl)', 'modifikator', 'nebezpečnost/síla jedu Jablečná vůně', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '3 směny/1 kolo', 'Aplikace protijedu.', 0,
'Při včasném použití snižuje nebezpečnost jablečné vůně o 5. Statistiky protijedu: magenergie: 1 mag suroviny: 38 zl základ: sůl trvání: 3 směny/1 kolo výroba: 3 směny nebezpečnost: −5 síla: 0 použití: dotyk (šňupání) barva/chuť/zápach: 95/65/85'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Jablečná vůně (Šmolková sůl)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 74, id FROM efekty WHERE nazev = 'Protijed proti jedu Jablečná vůně (Šmolková sůl)';

UPDATE lektvary SET popis = 'Šumivá spása — jed: Černá zhouba magenergie: 2 magy suroviny: 55 zl základ: bílé víno trvání: 6 směn/5 kol výroba: 5 hodin, doma nebezpečnost: neutralizace síla: neutralizace použití: vdechnutí po otravě, vypítí preventivně barva/chuť/zápach: 40/80/95 Je-li protijed upraven pro preventivní použití, je kapalina stabilnější a neodpařuje se tak rychle, takže se dá vypít.'
  WHERE id = 75 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Černá zhouba (Šumivá spása)', 'modifikator', 'nebezpečnost/síla jedu Černá zhouba', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '6 směn/5 kol', 'Aplikace protijedu.', 0,
'Je-li protijed upraven pro preventivní použití, je kapalina stabilnější a neodpařuje se tak rychle, takže se dá vypít. Statistiky protijedu: magenergie: 2 magy suroviny: 55 zl základ: bílé víno trvání: 6 směn/5 kol výroba: 5 hodin, doma nebezpečnost: neutralizace síla: neutralizace použití: vdechnutí po otravě, vypítí preventivně barva/chuť/zápach: 40/80/95'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Černá zhouba (Šumivá spása)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 75, id FROM efekty WHERE nazev = 'Protijed proti jedu Černá zhouba (Šumivá spása)';

UPDATE lektvary SET popis = 'Bolehoj — jed: Bolehlav magenergie: 1 mag suroviny: 32 zl základ: levandule (1 st) nalezení: 3 × 35 % trvání: 4 hodiny/kdykoli během účinkování jedu nebezpečnost: −2 síla: neutralizace/−10 použití: vdechnutí barva/chuť/zápach: 30/–/110 Kromě toho, že mírně snižuje nebezpečnost jedu (je-li aplikován před vypuknutím prvních příznaků otravy), snižuje sílu jedu a zejména odstraňuje ochrnutí způsobené bolehlavem.'
  WHERE id = 76 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Bolehlav (Bolehoj)', 'modifikator', 'nebezpečnost/síla jedu Bolehlav', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '4 hodiny/kdykoli během účinkování jedu', 'Aplikace protijedu.', 0,
'Kromě toho, že mírně snižuje nebezpečnost jedu (je-li aplikován před vypuknutím prvních příznaků otravy), snižuje sílu jedu a zejména odstraňuje ochrnutí způsobené bolehlavem. Statistiky protijedu: magenergie: 1 mag suroviny: 32 zl základ: levandule (1 st) nalezení: 3 × 35 % trvání: 4 hodiny/kdykoli během účinkování jedu nebezpečnost: −2 síla: neutralizace/−10 použití: vdechnutí barva/chuť/zápach: 30/–/110'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Bolehlav (Bolehoj)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 76, id FROM efekty WHERE nazev = 'Protijed proti jedu Bolehlav (Bolehoj)';

UPDATE lektvary SET popis = 'Fosfořík — jed: Otrušík magenergie: 3 magy suroviny: 36 zl základ: mletá kostní moučka (3 měďáky) trvání: 2 hodiny/6 hodin a 1 směna výroba: 10 kol nebezpečnost: −5 síla: snížení na polovinu použití: krev barva/chuť/zápach: 20/10/85 Fosfořík vůbec nepůsobí proti ochromujícím účinkům otrušíku, působí pouze na nebezpečnost jedu a ubývání životů. Navíc snižuje srážlivost krve -- po dobu tří dnů od aplikace při zranění spojeném s krvácením postava přijde navíc o ½ života, které zranění způsobilo.'
  WHERE id = 77 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Otrušík (Fosfořík)', 'modifikator', 'nebezpečnost/síla jedu Otrušík', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '2 hodiny/6 hodin a 1 směna', 'Aplikace protijedu.', 0,
'Fosfořík vůbec nepůsobí proti ochromujícím účinkům otrušíku, působí pouze na nebezpečnost jedu a ubývání životů. Navíc snižuje srážlivost krve -- po dobu tří dnů od aplikace při zranění spojeném s krvácením postava přijde navíc o ½ života, které zranění způsobilo. Statistiky protijedu: magenergie: 3 magy suroviny: 36 zl základ: mletá kostní moučka (3 měďáky) trvání: 2 hodiny/6 hodin a 1 směna výroba: 10 kol nebezpečnost: −5 síla: snížení na polovinu použití: krev barva/chuť/zápach: 20/10/85'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Otrušík (Fosfořík)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 77, id FROM efekty WHERE nazev = 'Protijed proti jedu Otrušík (Fosfořík)';

UPDATE lektvary SET popis = 'Kočičí mast — jed: Slzný plyn magenergie: 2 magy suroviny: 58 zl základ: kočičí sádlo trvání: 4 hodiny/kdykoli během účinkování jedu výroba: 4 směny nebezpečnost: −3 síla: neutralizace/viz níže použití: dotyk (mast na oči) barva/chuť/zápach: 60/40/5 Pokud postava u hodu proti pasti uspěla, neutralizuje kočičí mast účinky slzného plynu zcela. Pokud postava neuspěla, sníží násobky postihů uvedené v popisu jedu o 1.'
  WHERE id = 78 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Slzný plyn (Kočičí mast)', 'modifikator', 'nebezpečnost/síla jedu Slzný plyn', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '4 hodiny/kdykoli během účinkování jedu výroba: 4 směny', 'Aplikace protijedu.', 0,
'Pokud postava u hodu proti pasti uspěla, neutralizuje kočičí mast účinky slzného plynu zcela. Pokud postava neuspěla, sníží násobky postihů uvedené v popisu jedu o 1. Statistiky protijedu: magenergie: 2 magy suroviny: 58 zl základ: kočičí sádlo trvání: 4 hodiny/kdykoli během účinkování jedu výroba: 4 směny nebezpečnost: −3 síla: neutralizace/viz níže použití: dotyk (mast na oči) barva/chuť/zápach: 60/40/5'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Slzný plyn (Kočičí mast)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 78, id FROM efekty WHERE nazev = 'Protijed proti jedu Slzný plyn (Kočičí mast)';

UPDATE lektvary SET popis = 'Medvědí lék — jed: Digitalis magenergie: 2 magy suroviny: 24 zl základ: med trvání: 6 hodin/v době působení jedu nebezpečnost: −2 síla: snížení o ⅔ použití: požití (cucání) barva/chuť/zápach: 15/60/20 Po 6 kolech cucání se projeví snížení úbytku životů, po jedné směně účinky digitalisu zcela neutralizuje.'
  WHERE id = 79 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Digitalis (Medvědí lék)', 'modifikator', 'nebezpečnost/síla jedu Digitalis', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '6 hodin/v době působení jedu', 'Aplikace protijedu.', 0,
'Po 6 kolech cucání se projeví snížení úbytku životů, po jedné směně účinky digitalisu zcela neutralizuje. Statistiky protijedu: magenergie: 2 magy suroviny: 24 zl základ: med trvání: 6 hodin/v době působení jedu nebezpečnost: −2 síla: snížení o ⅔ použití: požití (cucání) barva/chuť/zápach: 15/60/20'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Digitalis (Medvědí lék)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 79, id FROM efekty WHERE nazev = 'Protijed proti jedu Digitalis (Medvědí lék)';

UPDATE lektvary SET popis = 'Syrník — jed: Dýmovka magenergie: 1 mag suroviny: 22 zl základ: syrovátka trvání: 2 hodiny/1 kolo výroba: 5 kol nebezpečnost: 0 síla: neutralizace/snížení síly na polovinu použití: požití barva/chuť/zápach: 25/85/10 Působí pouze při okamžitém podání bezprostředně po otravě nebo při preventivním použití. Nemá vliv na nebezpečnost dýmovky, jen snižuje sílu jedu.'
  WHERE id = 80 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Dýmovka (Syrník)', 'modifikator', 'nebezpečnost/síla jedu Dýmovka', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '2 hodiny/1 kolo', 'Aplikace protijedu.', 0,
'Působí pouze při okamžitém podání bezprostředně po otravě nebo při preventivním použití. Nemá vliv na nebezpečnost dýmovky, jen snižuje sílu jedu. Statistiky protijedu: magenergie: 1 mag suroviny: 22 zl základ: syrovátka trvání: 2 hodiny/1 kolo výroba: 5 kol nebezpečnost: 0 síla: neutralizace/snížení síly na polovinu použití: požití barva/chuť/zápach: 25/85/10'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Dýmovka (Syrník)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 80, id FROM efekty WHERE nazev = 'Protijed proti jedu Dýmovka (Syrník)';

UPDATE lektvary SET popis = 'Upíří fujtajbl — jed: Puchčoud magenergie: 1 mag suroviny: 48 zl základ: krev a česnek (cca 4 stroužky) trvání: 6 hodin/1 kolo výroba: 4 směny nebezpečnost: −4 síla: neutralizace/−2k6 použití: požití barva/chuť/zápach: 60/95/95 Působí jak na snížení nebezpečnosti puchčoudu, tak na ztrátu životů, ale musí být podán včas -- nejpozději kolo po otravě.'
  WHERE id = 81 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Puchčoud (Upíří fujtajbl)', 'modifikator', 'nebezpečnost/síla jedu Puchčoud', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '6 hodin/1 kolo', 'Aplikace protijedu.', 0,
'Působí jak na snížení nebezpečnosti puchčoudu, tak na ztrátu životů, ale musí být podán včas -- nejpozději kolo po otravě. Statistiky protijedu: magenergie: 1 mag suroviny: 48 zl základ: krev a česnek (cca 4 stroužky) trvání: 6 hodin/1 kolo výroba: 4 směny nebezpečnost: −4 síla: neutralizace/−2k6 použití: požití barva/chuť/zápach: 60/95/95'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Puchčoud (Upíří fujtajbl)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 81, id FROM efekty WHERE nazev = 'Protijed proti jedu Puchčoud (Upíří fujtajbl)';

UPDATE lektvary SET popis = 'Vaječný led — jed: Svrbík magenergie: 1 mag suroviny: 44 zl základ: vaječný bílek trvání: 3 směny/kdykoli během účinkování jedu výroba: 10 kol nebezpečnost: neutralizace síla: neutralizace použití: dotyk barva/chuť/zápach: 0/5/45 Tímto průhledným bezbarvým gelem je třeba potřít postižená místa, která přestanou během 1k6 kol zcela svědět.'
  WHERE id = 82 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Svrbík (Vaječný led)', 'modifikator', 'nebezpečnost/síla jedu Svrbík', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '3 směny/kdykoli během účinkování jedu výroba: 10 kol', 'Aplikace protijedu.', 0,
'Tímto průhledným bezbarvým gelem je třeba potřít postižená místa, která přestanou během 1k6 kol zcela svědět. Statistiky protijedu: magenergie: 1 mag suroviny: 44 zl základ: vaječný bílek trvání: 3 směny/kdykoli během účinkování jedu výroba: 10 kol nebezpečnost: neutralizace síla: neutralizace použití: dotyk barva/chuť/zápach: 0/5/45'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Svrbík (Vaječný led)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 82, id FROM efekty WHERE nazev = 'Protijed proti jedu Svrbík (Vaječný led)';

UPDATE lektvary SET popis = 'Flekatec — jed: Belladona magenergie: 5 magů suroviny: 32 zl základ: škrobová moučka trvání: 3 hodiny/2 směny výroba: 2 hodiny nebezpečnost: −4 (−2) síla: −10(−5)/−15(−10) použití: krev barva/chuť/zápach: 95/50/5 Protijed je třeba podat do dvou hodin po pozření belladony; je-li podán později, ale ještě před vypuknutím prvních příznaků otravy, platí další čísla v závorkách. Jakmile příznaky vypuknou, již zpravidla není pomoci.'
  WHERE id = 83 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Belladona (Flekatec)', 'modifikator', 'nebezpečnost/síla jedu Belladona', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '3 hodiny/2 směny', 'Aplikace protijedu.', 0,
'Protijed je třeba podat do dvou hodin po pozření belladony; je-li podán později, ale ještě před vypuknutím prvních příznaků otravy, platí další čísla v závorkách. Jakmile příznaky vypuknou, již zpravidla není pomoci. Statistiky protijedu: magenergie: 5 magů suroviny: 32 zl základ: škrobová moučka trvání: 3 hodiny/2 směny výroba: 2 hodiny nebezpečnost: −4 (−2) síla: −10(−5)/−15(−10) použití: krev barva/chuť/zápach: 95/50/5'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Belladona (Flekatec)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 83, id FROM efekty WHERE nazev = 'Protijed proti jedu Belladona (Flekatec)';

UPDATE lektvary SET popis = 'Violík — jed: Dopisní jed magenergie: 1 mag suroviny: 15 zl základ: kyselé mléko trvání: 12 hodin/1 směna nebezpečnost: −3 síla: neutralizuje/snížení na polovinu použití: požití barva/chuť/zápach: 70/80/50 K dosažení plného účinku je třeba tento protijed pozřít ještě před vypuknutím prvních příznaků otravy. V případě pozdější aplikace již nesnižuje nebezpečnost jedu, pouze snižuje jeho sílu na polovinu.'
  WHERE id = 84 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Dopisní jed (Violík)', 'modifikator', 'nebezpečnost/síla jedu Dopisní jed', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '12 hodin/1 směna', 'Aplikace protijedu.', 0,
'K dosažení plného účinku je třeba tento protijed pozřít ještě před vypuknutím prvních příznaků otravy. V případě pozdější aplikace již nesnižuje nebezpečnost jedu, pouze snižuje jeho sílu na polovinu. Statistiky protijedu: magenergie: 1 mag suroviny: 15 zl základ: kyselé mléko trvání: 12 hodin/1 směna nebezpečnost: −3 síla: neutralizuje/snížení na polovinu použití: požití barva/chuť/zápach: 70/80/50'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Dopisní jed (Violík)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 84, id FROM efekty WHERE nazev = 'Protijed proti jedu Dopisní jed (Violík)';

UPDATE lektvary SET popis = 'Ježibabí rosol — jed: Moucha magenergie: 7 magů suroviny: 33 zl základ: žabí vajíčka trvání: 3 hodiny/6 kol (do prvních příznaků otravy) výroba: 2 směny nebezpečnost: −4 síla: zkracuje dobu ochromení na polovinu použití: požití barva/chuť/zápach: 60/110/80 Při včasném podání zmírní účinky ochromujícího jedu moucha. Zbytek účinků lze odstranit megacloumákem.'
  WHERE id = 85 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Moucha (Ježibabí rosol)', 'modifikator', 'nebezpečnost/síla jedu Moucha', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '3 hodiny/6 kol (do prvních příznaků otravy) výroba: 2 směny', 'Aplikace protijedu.', 0,
'Při včasném podání zmírní účinky ochromujícího jedu moucha. Zbytek účinků lze odstranit megacloumákem. Statistiky protijedu: magenergie: 7 magů suroviny: 33 zl základ: žabí vajíčka trvání: 3 hodiny/6 kol (do prvních příznaků otravy) výroba: 2 směny nebezpečnost: −4 síla: zkracuje dobu ochromení na polovinu použití: požití barva/chuť/zápach: 60/110/80'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Moucha (Ježibabí rosol)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 85, id FROM efekty WHERE nazev = 'Protijed proti jedu Moucha (Ježibabí rosol)';

UPDATE lektvary SET popis = 'Rozvid — jed: Malé oko magenergie: 2 magy suroviny: 38 zl základ: mletý vápenec trvání: 1 hodina/kdykoli po otravě výroba: 1 směna nebezpečnost: neutralizuje síla: neutralizuje použití: krev barva/chuť/zápach: 40/30/80 Rozvid do tří kol po aplikaci odstraňuje veškeré oslepení způsobené jedem malé oko. Při preventivním podání účinky malého oka zcela eliminuje.'
  WHERE id = 86 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Malé oko (Rozvid)', 'modifikator', 'nebezpečnost/síla jedu Malé oko', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '1 hodina/kdykoli po otravě výroba: 1 směna', 'Aplikace protijedu.', 0,
'Rozvid do tří kol po aplikaci odstraňuje veškeré oslepení způsobené jedem malé oko. Při preventivním podání účinky malého oka zcela eliminuje. Statistiky protijedu: magenergie: 2 magy suroviny: 38 zl základ: mletý vápenec trvání: 1 hodina/kdykoli po otravě výroba: 1 směna nebezpečnost: neutralizuje síla: neutralizuje použití: krev barva/chuť/zápach: 40/30/80'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Malé oko (Rozvid)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 86, id FROM efekty WHERE nazev = 'Protijed proti jedu Malé oko (Rozvid)';

UPDATE lektvary SET popis = 'Zlatodým — jed: Pravdomluv magenergie: 3 magy suroviny: 60 zl základ: tabák (dýmkové koření), eventuálně kadidlo trvání: 3 hodiny/– výroba: 3 dny, doma nebezpečnost: −6 síla: 0 použití: vdechnutí (kouření) barva/chuť/zápach: 50/–/30 Tento protijed funguje pouze preventivně, je tedy nutné aplikovat ho ještě před požitím pravdomluvu.'
  WHERE id = 88 AND popis LIKE '%TODO%';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Protijed proti jedu Pravdomluv (Zlatodým)', 'modifikator', 'nebezpečnost/síla jedu Pravdomluv', 'nebezpečnost/síla dle statistik protijedu (viz tooltip)', 'jednorazove', '3 hodiny/–', 'Aplikace protijedu.', 0,
'Tento protijed funguje pouze preventivně, je tedy nutné aplikovat ho ještě před požitím pravdomluvu. Statistiky protijedu: magenergie: 3 magy suroviny: 60 zl základ: tabák (dýmkové koření), eventuálně kadidlo trvání: 3 hodiny/– výroba: 3 dny, doma nebezpečnost: −6 síla: 0 použití: vdechnutí (kouření) barva/chuť/zápach: 50/–/30'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Protijed proti jedu Pravdomluv (Zlatodým)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 88, id FROM efekty WHERE nazev = 'Protijed proti jedu Pravdomluv (Zlatodým)';

-- PPE lektvary (36-40) -- migrace 0029 u těchto 4 (36-39) vytvořila
-- efekty jen "odvozené z názvu" s výslovnou poznámkou, že zdrojový text není
-- k dispozici. Ten text se teď našel (content/pravidla-hrac.html, b3232+) --
-- původní 4 efekty se přepíšou na přesné znění místo smazání/du plicit,
-- aby zůstalo id stálé (už jsou naláliné přes lektvar_efekty). "Ohnivec" (40)
-- byl dřív vynechán jako "název nejednoznačný" -- plný text také existuje.

UPDATE lektvary SET popis = 'Netopýří sluch — magenergie: 3 magy suroviny: 1 zl základ: krollí ucho a netopýří krev trvání: 5 směn výroba: 6 směn použití: požití barva/chuť/zápach: 60/40/30 Postava získává všechny výhody a nevýhody krollova ultrasluchu (zvláštní schopnost "Ultrasluch", id 2 v tabulce zvlastni_schopnosti — dosah 50 sáhů, určí vzdálenost a přibližný tvar/velikost, nefunguje v hlučném prostředí hlasitějším než lidský hovor, funguje v mlze/pod vodou, ne skrz vodní hladinu).'
  WHERE id = 36;

UPDATE efekty SET cil = 'prostorové vnímání sluchem (jako Ultrasluch)', tooltip_text = 'Postava získává všechny výhody a nevýhody krollova ultrasluchu (zvláštní schopnost "Ultrasluch", id 2 v tabulce zvlastni_schopnosti — dosah 50 sáhů, určí vzdálenost a přibližný tvar/velikost, nefunguje v hlučném prostředí hlasitějším než lidský hovor, funguje v mlze/pod vodou, ne skrz vodní hladinu).'
  WHERE nazev = 'Zesílený sluch';

UPDATE lektvary SET popis = 'Neviditelnost — magenergie: 10 magů suroviny: 5 zl základ: ocet trvání: viz níže výroba: 5 směn použití: požití barva/chuť/zápach: 40/80/50 Postava, která ho pozře, se stává se vším, co má na sobě, neviditelnou — absolutně nezjistitelnou žádným vizuálním prostředkem (zrakem, infravidění atd.), i když ji lze detekovat krollovým ultrasluchem, hobitím čichem, podle zvuků nebo pohybu předmětů. Postava zůstane neviditelná, dokud nezačne útočit, nepoužije psychickou zvláštní schopnost (např. telepatii) nebo nepromluví. Být cílem cizí akce (útoku, kouzla) neviditelnost neruší.'
  WHERE id = 37;

UPDATE efekty SET cil = 'vizuální detekce', tooltip_text = 'Postava, která ho pozře, se stává se vším, co má na sobě, neviditelnou — absolutně nezjistitelnou žádným vizuálním prostředkem (zrakem, infravidění atd.), i když ji lze detekovat krollovým ultrasluchem, hobitím čichem, podle zvuků nebo pohybu předmětů. Postava zůstane neviditelná, dokud nezačne útočit, nepoužije psychickou zvláštní schopnost (např. telepatii) nebo nepromluví. Být cílem cizí akce (útoku, kouzla) neviditelnost neruší.'
  WHERE nazev = 'Neviditelnost (lektvar)';

UPDATE lektvary SET popis = 'Soví oči — magenergie: 10 magů suroviny: 4 zl základ: oko sovy a jelení lůj trvání: 6 směn výroba: 6 směn použití: dotyk – vetřít do očí barva/chuť/zápach: 60/40/30 Ve skutečnosti jde o mast, kterou je třeba vetřít do očí a poté je na 6 hodin zavřít (během této doby mohou jemně pálit); jakmile pálení ustane, jsou oči přizpůsobené nočnímu vidění.'
  WHERE id = 38;

UPDATE efekty SET cil = 'vidění za tmy', tooltip_text = 'Ve skutečnosti jde o mast, kterou je třeba vetřít do očí a poté je na 6 hodin zavřít (během této doby mohou jemně pálit); jakmile pálení ustane, jsou oči přizpůsobené nočnímu vidění.'
  WHERE nazev = 'Noční vidění';

UPDATE lektvary SET popis = 'Zvířecí řeč — magenergie: 5 magů suroviny: 1 zl základ: zvířecí krev trvání: 1 směna výroba: 6 směn, doma použití: dotyk – klokání barva/chuť/zápach: 25/80/30 Uživatel může 1 směnu volně mluvit se zvířetem stejného druhu, jako bylo to, z jehož krve je lektvar vyroben (jen inteligence 1, ne 0). Hodnota informací plně závisí na znalostech a zkušenostech daného zvířete.'
  WHERE id = 39;

UPDATE efekty SET cil = 'komunikace se zvířaty', tooltip_text = 'Uživatel může 1 směnu volně mluvit se zvířetem stejného druhu, jako bylo to, z jehož krve je lektvar vyroben (jen inteligence 1, ne 0). Hodnota informací plně závisí na znalostech a zkušenostech daného zvířete.'
  WHERE nazev = 'Řeč se zvířaty (lektvar)';

UPDATE lektvary SET popis = 'Ohnivec — magenergie: 35 magů suroviny: 75 zl základ: dřevěný popel trvání: 3 směny Postava se po použití lektvaru změní v nehmotnou bytost tvořenou plameny (stejně šaty/předměty/tvorové velikosti A0 u sebe). Může se volně pohybovat a protahovat štěrbinami (jako lektvar mlhovina), ale nemůže levitovat. Zranitelná je pouze magickými zbraněmi, chladem a mrazem za dvojnásobek, výbuchem za polovinu obvyklého zranění, a je ovlivnitelná mentálním útokem. Tvorové s inteligencí a I prchají, 2-5 se stahují z blízkosti, 6-11 se vyhýbají fyzickému kontaktu, 12-15 se snaží vyjednávat. Postava plápolá, ale nezapaluje předměty, kterých se dotýká, popálí však dotykem za 1-3 životy. (Pozn.: zápis kódů zranitelnosti u tohoto lektvaru je v originále patrně poškozený/zkomolený (opakující se "oheň"/"mráz" u více kódů) — přepsáno doslovně, bez tiché opravy.)'
  WHERE id = 40;

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Ohnivá podoba (Ohnivec)', 'imunita', 'fyzická/ohnivá zranitelnost, ovlivnitelnost strachem', 'zranitelnost jen magií/chladem 2x/výbuchem 1/2, dotyk 1-3 živ.', 'jednorazove', '3 směny', 'Uplynutí trvání.', 0,
'Postava se po použití lektvaru změní v nehmotnou bytost tvořenou plameny (stejně šaty/předměty/tvorové velikosti A0 u sebe). Může se volně pohybovat a protahovat štěrbinami (jako lektvar mlhovina), ale nemůže levitovat. Zranitelná je pouze magickými zbraněmi, chladem a mrazem za dvojnásobek, výbuchem za polovinu obvyklého zranění, a je ovlivnitelná mentálním útokem. Tvorové s inteligencí a I prchají, 2-5 se stahují z blízkosti, 6-11 se vyhýbají fyzickému kontaktu, 12-15 se snaží vyjednávat. Postava plápolá, ale nezapaluje předměty, kterých se dotýká, popálí však dotykem za 1-3 životy. (Pozn.: zápis kódů zranitelnosti u tohoto lektvaru je v originále patrně poškozený/zkomolený (opakující se "oheň"/"mráz" u více kódů) — přepsáno doslovně, bez tiché opravy.)'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Ohnivá podoba (Ohnivec)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 40, id FROM efekty WHERE nazev = 'Ohnivá podoba (Ohnivec)';

-- Částečně potvrzené (jen z textu jejich vlastního protijedu, ne z-- vlastního popisu jedu, který zdroj neobsahuje) -- výslovně označeno.

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Oslepení (Malé oko)', 'debuff', 'zrak', 'oslepení (přesná síla/nebezpečnost jedu nedohledána)', 'jednorazove', 'nedohledáno', 'Uplynutí trvání, nebo protijed Rozvid.', 0,
'Jed "malé oko" nemá v dostupném zdroji vlastní popisný odstavec (jen záznam v ceníku "viz PPE str. 57"). Efekt (oslepení) je odvozen jen z textu protijedu Rozvid: "do tří kol po aplikaci odstraňuje veškeré oslepení způsobené jedem malé oko". Síla, nebezpečnost a trvání jedu samotného nejsou známy.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Oslepení (Malé oko)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 70, id FROM efekty WHERE nazev = 'Oslepení (Malé oko)';

INSERT INTO efekty (nazev, typ, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Nutkání mluvit pravdu (Pravdomluv)', 'debuff', 'vůle/upřímnost', 'nucen mluvit pravdu (přesná síla/nebezpečnost nedohledána)', 'jednorazove', 'nedohledáno', 'Uplynutí trvání.', 0,
'Jed "pravdomluv" nemá v dostupném zdroji vlastní popisný odstavec (jen záznam v ceníku "viz PPE str. 57"). Efekt je odvozen z názvu a z textu protijedu Zlatodým, který "funguje pouze preventivně, je nutné aplikovat ho ještě před požitím pravdomluvu" -- potvrzuje, že jde o donucovací/pravdomluvný efekt, ne přesnou mechaniku.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Nutkání mluvit pravdu (Pravdomluv)');

INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT 71, id FROM efekty WHERE nazev = 'Nutkání mluvit pravdu (Pravdomluv)';
