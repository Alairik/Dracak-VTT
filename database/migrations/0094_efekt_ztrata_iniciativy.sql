-- Efekt „Ztráta iniciativy (nesplněný slib)“ — samé DML (navazuje na 0091/0092, struktura efektů už je v DB).
--
-- Pravidlo (h1622 b10803): kdo uplatní bonus k iniciativě za svou schopnost nebo zvláštní činnost (vícenásobné útoky,
-- kouzlení), musí jí věnovat první akci kola a nejméně polovinu akcí celého kola. Rozhodnutí uživatele 2026-10-17: polovinu
-- akcí VTT nehlídá zákazem jiných akcí; kdo jí slíbenému úkonu nevěnuje, ztratí v příští iniciativě — dostane −3
-- (řádek „Automatická ztráta iniciativy v základním systému“ Tabulky bonusů a postihů k iniciativě, migrace 0045).
-- Efekt nasazuje na konci kola `dracak_vtt_iniciativa_ztrata_nasad` na jedno kolo; PJ ho smí v panelu efektů sundat.

INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Ztráta iniciativy (nesplněný slib)', 'debuff', 0, 'iniciativa', '−3 iniciativa v tomto kole', 'jednorazove', '1 kolo', 'Uplynutí trvání.', 0,
       'Tvor v minulém kole ohlásil úkon, za který dostal bonus k iniciativě (kouzlení, vícenásobné útoky), ale nevěnoval mu aspoň polovinu akcí kola (h1622). V tomhle kole má proto −3 k iniciativě. Efekt nasadí boj sám na konci kola; PJ ho může sundat.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Ztráta iniciativy (nesplněný slib)');

UPDATE efekty SET mechanika_stav = 'auto', trvani_kol = 1, kolo_se_nepocita = 0, opakovani = 'obnov'
WHERE nazev = 'Ztráta iniciativy (nesplněný slib)';

INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'ini_radek', NULL, NULL, NULL, NULL, 'Automatická ztráta iniciativy v základním systému', 'h1622 b10803; rozhodnutí uživatele 2026-10-17'
FROM efekty WHERE nazev = 'Ztráta iniciativy (nesplněný slib)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'ini_radek');
