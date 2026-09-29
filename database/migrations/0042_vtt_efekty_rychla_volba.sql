-- VTT bojová mapa (hra/mapa.php): "rychlá volba" efektů nad katalogem
-- `efekty` — PJ/admin má v panelu spravovat token dlouhý abecední <select>
-- se VŠEMI řádky `efekty` (desítky, roste); tahle migrace přidává sloupec,
-- kterým lze KTERÝKOLIV řádek katalogu (ne jen těch 5 níže) označit jako
-- "zobrazit i jako klikací rychlý štítek" nad tím selectem — Owlbear-Rodeo
-- styl. Sloupec edituje generický editor pravidel (includes/entities.php,
-- entita 'efekty'), viz poznámka k bodu 1 níže.
--
-- Obsahuje ALTER TABLE (přidání sloupce) → dle CLAUDE.md web DB účet
-- (DML-only) tohle nikdy neprovede, migrate.php na kroku 1 spadne
-- (očekávané, stejně jako u 0037/0038/0040) — MUSÍ se spustit ručně přes
-- phpMyAdmin pod ADMIN účtem, a teprve pak zapsat do `migrace_log`. Kroky
-- 2 a 3 jsou čisté INSERT (DML) — po ručním spuštění 1 proběhnou samy i
-- při opakovaném volání migrate.php (idempotentní přes WHERE NOT EXISTS /
-- INSERT IGNORE, stejný vzor jako migrace 0022).
--
-- ---------------------------------------------------------------------
-- 1. Sloupec pro rychlou volbu
-- ---------------------------------------------------------------------
ALTER TABLE efekty ADD COLUMN rychla_volba TINYINT(1) NOT NULL DEFAULT 0 AFTER typ;

-- ---------------------------------------------------------------------
-- 2. Pět obecných, znovupoužitelných stavových efektů (rychla_volba=1)
-- ---------------------------------------------------------------------
-- Katalog dosud neměl ŽÁDNÝ obecný stavový efekt tohoto typu — jen úzké
-- efekty vázané na jedno konkrétní kouzlo/lektvar (např. "Otrava
-- (Bolehlav)"). Pojmenování/formát (typ='debuff', mechanika_aplikace=
-- 'jednorazove', trvani jako obecný popis — skutečné kolo si drží
-- aktivni_efekty.zbyva_kol per-aplikace) navazuje na vzor migrace 0022
-- ("Poškození"/"Léčení").
--
-- DŮLEŽITÉ ZJIŠTĚNÍ (viz finální report) — database/drd-db-full-v1.sql
-- (řádky ~2827-2829, dosud NEMIGROVANÁ část jednorázového obsahového
-- importu) už dřív navrhoval STEJNÝ koncept obecného "nemůže konat žádné
-- akce" efektu, ale pod názvem "Ochromení", ne "Omráčení". Pokud by tahle
-- konkrétní část full-v1.sql byla už dřív ručně spuštěná na produkci,
-- může tam řádek "Ochromení" už existovat — INSERT níže je proto
-- schválně idempotentní (WHERE NOT EXISTS) a NEPŘEPÍŠE existující řádek.
-- Před širším používáním stojí za to ověřit v produkční DB
-- (`SELECT nazev FROM efekty WHERE nazev IN ('Ochromení','Omráčení')`)
-- a případně sloučit/rozhodnout, který název je kanonický — viz report.
INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, tooltip_text)
SELECT 'Omráčení', 'debuff', 1, 'akceschopnost',
       'cíl nemůže konat žádné akce ani se bránit',
       'jednorazove', 'dle aplikace (kola nastavuje PJ při přidání efektu)',
       'Cíl je omráčen/ochromen — nemůže jednat ani se bránit, dokud efekt trvá.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Omráčení');

INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, tooltip_text)
SELECT 'Strach', 'debuff', 1, 'chování (útěk), ÚČ/OČ',
       'cíl uteče nejpřímější cestou pryč; nemůže-li utéct, postih k ÚČ a bonus k OČ',
       'jednorazove', 'dle aplikace (kola nastavuje PJ při přidání efektu)',
       'Cíl je vyděšený — prchá nejpřímější cestou od zdroje strachu, případně má v boji postih k útoku a bonus k obraně.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Strach');

INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, tooltip_text)
SELECT 'Okouzlení', 'debuff', 1, 'chování cíle',
       'cíl jedná jako spojenec/přítel sesilatele (sebevražedné/nepřirozené příkazy ignoruje)',
       'jednorazove', 'dle aplikace (kola nastavuje PJ při přidání efektu)',
       'Cíl je okouzlený — chová se k sesilateli jako ke spojenci/nejlepšímu příteli, dokud efekt trvá.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Okouzlení');

INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, tooltip_text)
SELECT 'Spánek', 'debuff', 1, 'akceschopnost',
       'cíl upadne do hlubokého spánku a je bezbranný',
       'jednorazove', 'dle aplikace (kola nastavuje PJ při přidání efektu)',
       'Cíl upadl do kouzelného spánku a je bezbranný; probudí ho zranění, zlom kouzlo/rozptyl kouzla nebo uplynutí trvání.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Spánek');

INSERT INTO efekty (nazev, typ, rychla_volba, cil, hodnota_vzorec, mechanika_aplikace, trvani, tooltip_text)
SELECT 'Zmatek', 'debuff', 1, 'chování (náhodný cíl útoku)',
       'cíl si každé kolo hodem určí: útočí na nepřítele / nedělá nic / útočí na vlastní spojence',
       'jednorazove', 'dle aplikace (kola nastavuje PJ při přidání efektu)',
       'Cíl je zmatený — každé kolo se náhodně rozhodne mezi útokem na nepřítele, nečinností nebo útokem na vlastní spojence.'
WHERE NOT EXISTS (SELECT 1 FROM efekty WHERE nazev = 'Zmatek');

-- ---------------------------------------------------------------------
-- 3. První dávka napojení na existující kouzla/lektvary/schopnosti
-- ---------------------------------------------------------------------
-- Prvních pass, NE vyčerpávající (viz report) — každý řádek níže byl
-- ručně ověřen proti skutečnému textu `popis` (ne jen pattern-match na
-- klíčové slovo). Vynechány mj. graduované/vícestupňové efekty
-- (Zastrašení-kouzlo, Hněv lesa — síla efektu se odvíjí od hodu, ne
-- jednoduchý binární stav), efekty s odlišnou mechanikou (Bestie —
-- ztráta iniciativy % šancí + sebezranění; Zenerova karta — hypnotická
-- fixace, ne spánek; Trollobijecký útok — má už vlastní úzký efekt
-- "Ochromení (drtivá zbraň trollobijce)", -2 akce kumulativně, jiná
-- mechanika než plné omráčení) a čistě narativní/PJ-libovůle efekty
-- (Ochrana před vlky, Sláva zesnulých, Uhrančivý pohled). Léčebná/
-- zrušující kouzla (Odstraň ochromení, Megacloumák) záměrně NEJSOU
-- napojena na Omráčení — ruší efekt, nezpůsobují ho.

-- Omráčení <- kouzla
INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Omráčení')
FROM kouzla k
WHERE k.nazev IN (
    'Ledové zrcadlo', 'Svaž postavu', 'Ochromení Ager', 'Ochromení (16. úroveň)',
    'Hlodající vina', 'Slovo moci', 'Útočiště', 'Hromosvod', 'Zadrž osobu'
);

-- Omráčení <- lektvary
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT l.id, (SELECT id FROM efekty WHERE nazev = 'Omráčení')
FROM lektvary l
WHERE l.nazev IN ('Černá zhouba');

-- Strach <- kouzla
INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Strach')
FROM kouzla k
WHERE k.nazev IN ('Pes baskervilský');

-- Strach <- schopnosti/dovednosti
INSERT IGNORE INTO schopnost_efekty (schopnost_id, efekt_id)
SELECT z.id, (SELECT id FROM efekty WHERE nazev = 'Strach')
FROM zvlastni_schopnosti z
WHERE z.nazev IN ('Zastrašování', 'Zastrašení (Válečník)', 'Děsivá přítomnost');

-- Okouzlení <- kouzla
INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Okouzlení')
FROM kouzla k
WHERE k.nazev IN ('Zmam osobu');

-- Okouzlení <- lektvary
INSERT IGNORE INTO lektvar_efekty (lektvar_id, efekt_id)
SELECT l.id, (SELECT id FROM efekty WHERE nazev = 'Okouzlení')
FROM lektvary l
WHERE l.nazev IN ('Lektvar vlády nad lykantropy');

-- Spánek <- kouzla
INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Spánek')
FROM kouzla k
WHERE k.nazev IN ('Usni', 'Kouzlo spánku');

-- Zmatek <- kouzla
INSERT IGNORE INTO kouzlo_efekty (kouzlo_id, efekt_id)
SELECT k.id, (SELECT id FROM efekty WHERE nazev = 'Zmatek')
FROM kouzla k
WHERE k.nazev IN ('Zmatek', 'stepní běžec');
