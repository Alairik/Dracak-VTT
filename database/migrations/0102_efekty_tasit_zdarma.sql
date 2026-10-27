-- Vytasení zbraně bez akce — efekty Erythenova Rychlého tasení a Dikobraza (Zlaté tetování). Samé DML, jde spustit opakovaně
-- (každý příkaz se ptá, jestli už řádek existuje).
--
-- Obecné pravidlo: vytasení nebo výměna zbraně v boji stojí akci (h1606, b10619; migrace 0101 a includes/vtt_zbrane_u_sebe.php).
-- Výjimky jsou efekty se složkou `tasit_zdarma` (parametr = druhy zbraní tvt / vrhaci, hodnota = kolikrát za kolo, prázdná = bez omezení):
--
--  * Erythen — Rychlé tasení (h1094, b7751–b7755): jednou za kolo bez akce vytasí 1–2 zbraně tváří v tvář (dvě zbraně = jedna
--    „kombinace“ katalogu) nebo vytasí a hodí jednu vrhací zbraň; zbraň musí mít volně dostupnou (pochva, holster — ne batoh, ne z rukou).
--    Trvalá schopnost: efekt je připojený ke schopnosti (schopnost_efekty) a má složku `pasivni`, takže ho má každý Erythen od
--    6. úrovně sám a PJ ho nenasazuje. Vržená takto vytasená zbraň má ÚČ +2 a cizí zbraň v dosahu 1 vyžaduje past Obr–7–Obr
--    (b7752, b7755) — to vyhodnotí PJ, proto stav „cast“. Pravopis: kniha píše Erithen, povolání v DB je Erythen (do seznamu
--    „Na kontrolu při proofreadingu“).
--  * Dikobraz — jedna ze zvířecích značek Zlatého tetování Velkého lovce (18. úroveň, h1411, b9098): na 1 kolo dvojnásobné dostřely
--    vrhacích zbraní a tasení vrhací zbraně nestojí akci (bez omezení počtu). Aktivace značky nestojí akci a lovec ji smí použít
--    kdykoliv (b9092), proto ji PJ nasadí jako běžný efekt (rychlá volba). Dostřely ×2 vyhodnotí PJ (stav „cast“). Ostatní značky
--    (Slon, Kobra…) se zatím nepřidávají — čekají na rozhodnutí o jejich mechanice.

INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Rychlé tasení (Erythen)', 'buff', 0, 'akce (vytasení zbraně)', '1× za kolo bez akce', 'jednorazove', 'trvale (schopnost Rychlé tasení)', NULL, 0,
       'Erythen od 6. úrovně (h1094): jednou za kolo bez použití akce vytasí 1–2 zbraně tváří v tvář, nebo vytasí a hodí jednu vrhací zbraň. Zbraň musí mít volně dostupnou (pochva, holster; ne z batohu ani z rukou) — boj to hlídá přes „u sebe“ v deníku. Vržená takto vytasená zbraň má ÚČ o 2 vyšší a cizí zbraň v dosahu 1 vyžaduje past Obr–7–Obr tasení/nic (to vyhodnotí PJ).'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Rychlé tasení (Erythen)');

UPDATE efekty SET mechanika_stav = 'cast', trvani_kol = NULL, kolo_se_nepocita = 0, opakovani = 'obnov'
WHERE nazev = 'Rychlé tasení (Erythen)';

INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'tasit_zdarma', 1, NULL, NULL, NULL, 'tvt,vrhaci', 'h1094 b7751: jednou za kolo bez akce 1–2 zbraně tváří v tvář, nebo vytasit a hodit jednu vrhací'
FROM efekty WHERE nazev = 'Rychlé tasení (Erythen)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'tasit_zdarma');

INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'pasivni', NULL, NULL, NULL, NULL, NULL, 'trvalá schopnost: platí, dokud má postava schopnost Rychlé tasení (schopnost_efekty)'
FROM efekty WHERE nazev = 'Rychlé tasení (Erythen)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'pasivni');

INSERT INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT zs.id, e.id
FROM zvlastni_schopnosti zs
JOIN schopnost_povolani sp ON sp.schopnost_id = zs.id
JOIN povolani p ON p.id = sp.povolani_id AND p.nazev = 'Erythen'
JOIN efekty e ON e.nazev = 'Rychlé tasení (Erythen)'
WHERE zs.nazev = 'Rychlé tasení'
  AND NOT EXISTS (SELECT 1 FROM schopnost_efekty se WHERE se.schopnost_id = zs.id AND se.efekt_id = e.id);

INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, podminka_ukonceni, stackovatelne, tooltip_text)
SELECT 'Dikobraz (Zlaté tetování)', 'buff', 1, 'akce (vytasení zbraně), dostřel vrhacích zbraní', 'tasení vrhací zbraně bez akce, dostřely ×2', 'jednorazove', '1 kolo', 'Uplynutí trvání.', 0,
       'Značka Dikobraz ze Zlatého tetování Velkého lovce (18. úroveň, h1411): na 1 kolo dvojnásobné dostřely vrhacích zbraní a tasení vrhací zbraně nestojí akci. Aktivace značky nestojí akci a lovec ji smí použít kdykoliv. Boj sám hlídá tasení bez akce; dvojnásobné dostřely vyhodnotí PJ.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Dikobraz (Zlaté tetování)');

UPDATE efekty SET mechanika_stav = 'cast', trvani_kol = 1, kolo_se_nepocita = 0, opakovani = 'obnov'
WHERE nazev = 'Dikobraz (Zlaté tetování)';

INSERT INTO efekt_slozky (efekt_id, druh, hodnota, pocet_kostek, typ_kostky, pevny_bonus, parametr, poznamka)
SELECT id, 'tasit_zdarma', NULL, NULL, NULL, NULL, 'vrhaci', 'h1411 b9098: tasení vrhací zbraně nestojí akci (bez omezení počtu)'
FROM efekty WHERE nazev = 'Dikobraz (Zlaté tetování)'
  AND NOT EXISTS (SELECT 1 FROM efekt_slozky s WHERE s.efekt_id = efekty.id AND s.druh = 'tasit_zdarma');
