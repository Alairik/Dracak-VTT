-- Kombinace dvou zbraní se pro bonusy k iniciativě počítají jako jednoruční (samé DML). Rozhodnutí uživatele 2026-10-17.
--
-- Tabulka zbraní řadí kombinace (Dvě dýky, Kord + dýka, Dvě katany …) mezi obouruční, protože se drží v obou rukách. Pro bonusy
-- povolání ale platí „jednoruční zbraně, včetně kombinací“ (Bojovník, h139 b987, b990), kdežto „obouruční zbraně“ jsou jen
-- jednokusé zbraně držené oběma rukama (Cep, Halapartna, Obouruční meč …). Kombinace proto dostávají vlastní hodnotu držení
-- `kombinace` (obě ruce obsazené, jako jednoruční pro bonusy); `obourucni` zůstává jednokusým obouručním zbraním.
UPDATE predmety SET chvat = 'kombinace'
WHERE typ = 'zbran' AND chvat = 'obourucni' AND nazev IN ('Dvě dýky', 'Hákové meče', 'Kord + dýka', 'Krátký meč a dýka', 'Tesák a dýka', 'Tesák + palcát', 'Kord + krátký meč', 'Dva boxery', 'Dvakrát Tanto', 'Široký meč a tesák', 'Šavle + tesák', 'Sekera + tesák', 'Široký meč a dýka', 'Šavle + dýka', 'Dvakrát Kama', 'Tanto + wakizashi', 'Dva palcáty', 'Dvě sekery', 'Kord + Wakizashi', 'Široký meč + sekera', 'Dlouhý meč + palcát', 'Dvě šavle', 'Katana + Wakizashi', 'Katana + Tanto', 'Dva Yatagany', 'Dva Kropáče', 'Dvě válečná kladiva', 'Dvě Schiavony', 'Tesák + dlouhý meč', 'Sekera + kladivo', 'Dvakrát Labrys', 'Dva široké meče', 'Dvě katany', 'Dva dlouhé meče', 'Dvakrát warglaive');
