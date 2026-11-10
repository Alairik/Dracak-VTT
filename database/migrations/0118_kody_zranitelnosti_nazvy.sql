-- 0118 — Chybějící názvy kódů zranitelnosti (I–N = skupiny kouzel)
--
-- Proč: šest kódů zranitelnosti mělo v databázi prázdný název a poznámku „TBD — pravděpodobně skupina kouzel,
-- ověřit proti bestiáři“. V bestiáři legenda je, jen ji nikdo nepřepsal do dat — a bez názvu se u nestvůry
-- (celkem 701 vazeb) zranitelnost ukázat nedá.
--
-- Citace (content/pravidla-bestiar.html, kapitola „Zranitelnost“ v popisu parametrů nestvůry):
--   „I. skupina kouzel (hraničářská) – úder varování, úder zloby … odstraň únavu“
--   „II. skupina kouzel (útočná) – modré, zelené, žluté, rudé … sršatec, temný úder“
--   „K. III. skupina kouzel (ohnivá a mrazivá) – nebezpečné ovoce, ohnivý déšť … ohnivá koule“
--   „L. IV. skupina kouzel (fyzická) – améba, břichomluvectví … pohlcení září“
--   „M. V. skupina kouzel (psychická) – čtení myšlenek, davová hypnóza … zmam osobu“
--   „N. VI. skupina kouzel (hraničářská) – bílá střela“
-- Pozn.: kniha označuje jako „hraničářskou“ skupinu I i VI — ponecháno podle zdroje, zapsáno do
-- docs/kontrolni-seznam-neuplnych-mist.md k ověření při korektuře (CLAUDE.md: překlepy ve zdroji neopravovat sám).
-- Písmeno J má v přepisu useknutou předponu („I. I. skupina … II. skupina“), pořadí písmen ale vychází jednoznačně.
--
-- Vazba na tabulku skupiny_kouzel se ZÁMĚRNĚ nedoplňuje: ta má čtyři řádky (fyzická, ochranná, psychická,
-- útočná), což neodpovídá šesti číslovaným skupinám z bestiáře. Spojovat je by byl odhad.
--
-- Jen UPDATE — web účet to zvládne, ruční SQL není potřeba.

UPDATE kody_zranitelnosti SET nazev = 'I. skupina kouzel (hraničářská)',
  popis = 'Úder varování, úder zloby, úder nenávisti, uzdravení, odstranění ochromění či únavy, neutralizace jedu.'
  WHERE kod = 'I' AND (nazev IS NULL OR nazev = '');
UPDATE kody_zranitelnosti SET nazev = 'II. skupina kouzel (útočná)',
  popis = 'Blesky všech barev, kulové a průrazné blesky, sršatec, temný úder.'
  WHERE kod = 'J' AND (nazev IS NULL OR nazev = '');
UPDATE kody_zranitelnosti SET nazev = 'III. skupina kouzel (ohnivá a mrazivá)',
  popis = 'Ohnivý déšť, chlad hvězd, ledový blesk, lomený plamen, ohnivý bič, pekelný oheň, ohnivá koule.'
  WHERE kod = 'K' AND (nazev IS NULL OR nazev = '');
UPDATE kody_zranitelnosti SET nazev = 'IV. skupina kouzel (fyzická)',
  popis = 'Améba, dlouhá ruka, hyperprostor, metamorfóza, mrak smrti, neviditelnost, pohlcení září.'
  WHERE kod = 'L' AND (nazev IS NULL OR nazev = '');
UPDATE kody_zranitelnosti SET nazev = 'V. skupina kouzel (psychická)',
  popis = 'Čtení myšlenek, hypnóza, kouzlo spánku, sugesce, zmatek, zmam osobu a další psychická kouzla.'
  WHERE kod = 'M' AND (nazev IS NULL OR nazev = '');
UPDATE kody_zranitelnosti SET nazev = 'VI. skupina kouzel (hraničářská)',
  popis = 'Bílá střela.'
  WHERE kod = 'N' AND (nazev IS NULL OR nazev = '');
