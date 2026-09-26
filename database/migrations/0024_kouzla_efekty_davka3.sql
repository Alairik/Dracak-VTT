-- Třetí a poslední dávka napojení kouzlo_efekty (Poškození/Léčení) —
-- zbytek kouzel s kostkami z worklistu (217 řádků), stejný postup jako
-- 0022/0023: každé kouzlo ručně ověřeno proti popisu, ne plošně.

-- "Pekelný oheň" a "Prokletá půda" existují 2x se stejným jménem (liší se
-- jen velikostí prvního písmene u "Prokletá/prokletá půda" — MariaDB
-- collation na sloupci nazev je case-insensitive, takže IN (...) by
-- chytlo oba řádky). Proto se id 786 řeší zvlášť přes WHERE k.id = 786,
-- ne přes jméno.

-- VYNECHÁNO ZÁMĚRNĚ (další vzory stejné třídy jako v 0022/0023):
--   - kostky zachycují jen jednu z víc srovnatelně důležitých, ale
--     vzájemně odlišných hodnot v textu (stejný vzor jako "Zvukový
--     výboj" v 0023): "Láva" (kostky=10k10 sedí na "spadne do lávy",
--     ale text má i samostatných 1k6 za "stojí vedle"), "Ohnivý ďábel"
--     (kostky=1k6 sedí na dotyk sousedního pole, ale text má i
--     samostatných 5k10 za "projde skrz")
--   - vícecílová řetězová formule s klesající hodnotou po průchodech,
--     kostky zachycují jen první článek řetězu (Řetězový blesk — stejná
--     třída jako "Sloup ohně" v 0022)
--   - periodický plošný efekt "každé kolo dokud je cíl v oblasti" —
--     neodpovídá mechanika_aplikace='jednorazove' obecných efektů
--     Poškození/Léčení, potřebuje vlastní DoT/HoT mechaniku
--     (na_zacatku_kola s trváním), ne jednorázové spuštění: "Posvěcená
--     půda", "Prokletá půda" (id 779), "Požár", "Pekelný oheň" (id 107,
--     "zraňuje v každém kole")
--
-- ZAHRNUTO i přes vedlejší podmíněný bonus (kostky = garantovaný
-- základ, bonus navíc pro podtyp cíle nebo šance na extra efekt
-- nemění základní hodnotu): "Nebeský oheň", "Pekelný oheň" (id 786,
-- symetrický ekvivalent Nebeského ohně), "Zuby noci 16. úroveň" (text
-- základ explicitně značí slovem "celkem"), "Ohnivý dotek" (extra
-- zapálení je jen 20% šance, základní zranění je jisté při splnění
-- podmínky doteku).
--
-- Čisté INSERT (DML) — mělo by se nasadit samo přes migrate.php.

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Poškození')
FROM kouzla k
WHERE k.pocet_kostek IS NOT NULL
  AND k.nazev IN (
    'Bleskový přesun','Lože ostnů 16. úroveň','Nebeský oheň','Ohnivý dotek',
    'Sluneční paprsek','Vlna smrti','Zlotos','Zuby noci 16. úroveň'
);

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Poškození')
FROM kouzla k
WHERE k.id = 786;

INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Léčení')
FROM kouzla k
WHERE k.pocet_kostek IS NOT NULL
  AND k.nazev IN (
    'Darování života','Gejzír','Měsíční paprsek','Ošetři kritická zranění',
    'Potrava pro tělo i duši','Vlna života','Vyleč nepatrná zranění'
);
