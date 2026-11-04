-- Efekt „Kouzlí“ — štítek na tvorovi uprostřed sesílání kouzla v rozšířeném soubojovém systému (samé DML).
--
-- Pravidlo (content/pravidla-hrac.html, kapitola Kouzlení v souboji, b10842–b10847): kouzlo s vyvoláním N kol vyžaduje 2N po sobě
-- jdoucích akcí kouzlení. Kdo kouzlí a zaútočí-li na něj někdo v téhle chvíli, jde o útok na NEHYBNÉHO protivníka (místo bonusu za
-- obratnost −5, −3 za obranu beze zbraně, bez štítu); je-li útok úspěšný a kouzelník zraněn aspoň za 1 život, kouzlo se nezdaří
-- a magenergie je vyplýtvaná (výjimka: mezi předposlední a poslední akcí kouzlení kouzlo uspěje, kouzla s koncentrací se přeruší).
--
-- Rozhodnutí uživatele 2026-11-04: štítek „Kouzlí“ nasadí boj sám po akci kouzlení, jinou akcí tvora zmizí (kouzlení se musí držet po sobě),
-- po poslední akci kouzlení ho sundá PJ (boj nezná počet akcí kouzla), konec souboje ho sundá taky. Zásah kouzlícího boj jen ohlásí,
-- zda se kouzlo zhroutilo, posoudí PJ (předposlední akce).

INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Kouzlí', 'debuff', 0, 'obrana', 'v obraně jako nehybný', 'jednorazove', 'dokud kouzlí', 'Jiná akce než kouzlení, konec souboje, nebo ho sundá PJ po poslední akci kouzlení.', 0,
       'Tvor je uprostřed sesílání kouzla (akce kouzlení musí následovat těsně po sobě). Pro útočníky je nehybný: místo bonusu za obratnost −5, −3 za obranu beze zbraně, bez štítu a bez oprav za velikost. Je-li zraněn aspoň za 1 život před předposlední akcí kouzlení, kouzlo se nezdaří a magenergie propadne. Štítek nasadí boj po akci kouzlení; PJ ho sundá po poslední akci kouzlení.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Kouzlí');

UPDATE efekty SET mechanika_stav = 'auto', trvani_kol = NULL, kolo_se_nepocita = 0, opakovani = 'obnov'
WHERE nazev = 'Kouzlí';

INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'nehybny', NULL, NULL, NULL, NULL, NULL, 'b10846–b10847; rozhodnutí uživatele 2026-11-04'
FROM efekty WHERE nazev = 'Kouzlí'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'nehybny');
