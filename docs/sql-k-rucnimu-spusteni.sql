-- Dračák VTT — SQL k ručnímu spuštění (phpMyAdmin, vybraná databáze Dračáku — TA SAMÁ, kterou používá web).
--
-- Blok „Vytasení bez akce“: efekty Erythenova Rychlého tasení a Dikobraza (Zlaté tetování Velkého lovce). Samé vkládání
-- řádků (žádný CREATE ani ALTER), jde spustit opakovaně a v libovolném stavu databáze — každý příkaz se nejdřív ptá,
-- jestli řádek už existuje. Je tu proto, že automatické migrace z GitHubu teď nejedou (hosting blokuje volání z GitHubu,
-- viz docs/stav-projektu.md); stejné příkazy jsou v database/migrations/0102_efekty_tasit_zdarma.sql, takže až migrace
-- zase poběží, nic se nezdvojí.
-- Bez tohoto SQL platí obecné pravidlo: vytasení zbraně v boji stojí akci (u všech).
--
-- Dřívější blok „Zbraně u sebe“ (sloupec postava_predmety.u_sebe) je provedený (kontrolní SELECT vyšel 1) a je v migraci 0101.

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

-- Kontrola: musí vyjít 2, 3, 1 (dva efekty, tři složky, jedna vazba schopnosti Rychlé tasení na efekt).
SELECT
  (SELECT COUNT(*) FROM efekty WHERE nazev IN ('Rychlé tasení (Erythen)', 'Dikobraz (Zlaté tetování)')) AS efekty_2,
  (SELECT COUNT(*) FROM efekt_slozky s JOIN efekty e ON e.id = s.efekt_id
     WHERE e.nazev IN ('Rychlé tasení (Erythen)', 'Dikobraz (Zlaté tetování)')) AS slozky_3,
  (SELECT COUNT(*) FROM schopnost_efekty se JOIN efekty e ON e.id = se.efekt_id
     WHERE e.nazev = 'Rychlé tasení (Erythen)') AS vazba_1;
