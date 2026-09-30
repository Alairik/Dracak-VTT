-- Tabulka bonusů a postihů k iniciativě (str. 78, rozšířený soubojový
-- systém, h1622-h1623) — ve zdroji byla dřív jen nečitelný obrázek (viz
-- docs/kontrolni-seznam-neuplnych-mist.md), teď přepsáno přímo z fotky
-- skutečné stránky, kterou poslal uživatel. Stejný vzor jako
-- opravy_za_atribut/nosnost_podle_sily (migrace 0007) — čistě referenční
-- data, žádná FK na nic konkrétního.
--
-- Bonusy se při hodu na iniciativu vybírají ručně (checkboxy v UI, viz
-- hra/api/iniciativa_hod.php) — VTT dnes netrackuje únavu, naložení ani
-- to, jestli postava zrovna kouzlí/útočí obouručně, takže automatické
-- odvození není možné. Únava/vyčerpání/úplné vyčerpání a mírné/střední/
-- velké naložení jsou odstupňované varianty téhož stavu — PJ/hráč zaškrtne
-- jen tu, co aktuálně platí, ne víc najednou (to UI nevynucuje, spoléhá se
-- na běžnou domluvu u stolu, stejně jako zbytek enginu).
--
-- Samé CREATE TABLE + INSERT — CREATE potřebuje ruční spuštění přes
-- phpMyAdmin (admin účet, DML-only web účet ho neprovede), INSERT by pak
-- proběhl i automaticky, ale je ve stejném souboru kvůli přehlednosti.

CREATE TABLE IF NOT EXISTS iniciativa_bonusy (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  popis VARCHAR(100) NOT NULL,
  bonus SMALLINT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO iniciativa_bonusy (popis, bonus) VALUES
('Překvapení', -6),
('Obouruční zbraň', -2),
('Kouzlení', 1),
('3 útoky za 2 kola', 3),
('2 útoky za 1 kolo', 6),
('Automatická iniciativa v základním systému', 3),
('Automatická ztráta iniciativy v základním systému', -3),
('Kouzlo Rychlost', 6),
('Kouzlo Protoplazma', -3),
('Těžká kuše', -3),
('Únava', -1),
('Vyčerpání', -2),
('Úplné vyčerpání', -3),
('Mírné naložení', -1),
('Střední naložení', -3),
('Velké naložení', -5);
