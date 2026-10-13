-- kategorie_zbrane: doplnění 'strelna' / 'vrhaci' u střeleckých a vrhacích
-- zbraní.
--
-- Sloupec je SET('secna','bodna','tupa','drtiva','vrhaci','strelna'), ale u
-- zbraní s dostřelem zůstal NULL. Osobní deník ale potřebuje rozlišit, jestli
-- zbraň jde použít i v boji tváří v tvář: pravidla (h1615) říkají "Vrhací
-- zbraně můžeš používat i v souboji tváří v tvář jako jednoruční zbraně;
-- jejich síla je stejná, ale do útočného čísla se počítá bonus za sílu
-- místo bonusu za obratnost" — střelné (luky, kuše, prak) takto použít
-- nejdou.
--
-- Rozdělení je doslova z homebrew tabulky "Nové střelecké zbraně a
-- střelivo" (h2059, content/pravidla-hrac.html), která má sekce
-- "STŘELECKÉ ZBRANĚ" a "VRHACÍ ZBRANĚ" (lehké / střední / těžké):
--   střelné: krátký luk, dlouhý luk, yumi, válečný luk, lehká kuše, těžká
--            kuše, skrytá kuše, prak, prak na tyči
--   vrhací:  dýka, hvězdice, kámen, flakónek, pochodeň, jehlice, olovo,
--            ocelové koule, kopí, oštěp, harpuna, sekera, vidle, vrhací
--            sekera, labrys
-- Zbraně s dostřelem, které v téhle tabulce nejsou (Arbalest, Fuuma
-- shuriken, super těžké vrhací varianty z h2060), zůstávají NULL — jejich
-- zařazení tu pravidla neříkají. Mění se jen řádky, kde je kategorie NULL.
--
-- Nic v kódu kategorii zatím nečte kromě deníku (ověřeno grepem), dopad
-- jinde nehrozí. Čisté UPDATE (DML) — projde automaticky přes migrate.php.

UPDATE predmety SET kategorie_zbrane = 'strelna'
WHERE typ = 'zbran' AND kategorie_zbrane IS NULL AND nazev IN
    ('Krátký luk', 'Dlouhý luk', 'Yumi', 'Válečný luk', 'Lehká kuše', 'Těžká kuše',
     'Skrytá kuše', 'Prak', 'Prak na tyči');

UPDATE predmety SET kategorie_zbrane = 'vrhaci'
WHERE typ = 'zbran' AND kategorie_zbrane IS NULL AND nazev IN
    ('Dýka (vrhací)', 'Hvězdice', 'Kámen (vrhací)', 'Flakónek (vrhací)', 'Pochodeň (vrhací)',
     'Jehlice (vrhací)', 'Olovo (vrhací)', 'Ocelové koule', 'Kopí (vrhací)', 'Oštěp',
     'Harpuna', 'Sekera (vrhací)', 'Vidle (vrhací)', 'Vrhací sekera', 'Labrys (vrhací)');
