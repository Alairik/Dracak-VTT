-- Efekt „Ztráta iniciativy (nesplněný slib)“ — kolo nasazení se do trvání nepočítá (jako u kouzel, h1626 b10848). Samé DML.
--
-- Rozhodnutí uživatele 2026-10-17: slib úkonu se nehlídá zákazem, ale trestem −3 k iniciativě v příštím kole — za nesplněnou
-- polovinu akcí kola (zjistí se na konci kola) i za první akci kola věnovanou něčemu jinému (zjistí se hned). Efekt se tedy
-- nasazuje už v kole, kdy k porušení došlo, a má držet celé příští kolo: kolo nasazení se proto nepočítá do jeho jednoho kola.
UPDATE efekty SET kolo_se_nepocita = 1 WHERE nazev = 'Ztráta iniciativy (nesplněný slib)';
UPDATE efekty SET tooltip_text = 'Tvor v minulém kole ohlásil úkon, za který dostal bonus k iniciativě (kouzlení, vícenásobné útoky), ale nevěnoval mu první akci kola, nebo aspoň polovinu akcí kola (h1622). V tomhle kole má proto −3 k iniciativě. Efekt nasadí boj sám; PJ ho může sundat.'
WHERE nazev = 'Ztráta iniciativy (nesplněný slib)';
