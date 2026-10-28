-- Testovací data pro tests/ (lokální MariaDB; nikdy ne na ostrou databázi!).
-- Stav: svět 1 (PJ = účet 1), hráč = účet 3, postavy Hrun / Sindor / Prázdná / Nulová odolnost, mapa 1 (čtverec) a 2 (hex), jedna zeď,
-- na mapě 1 tokeny 1 (Hrun), 2 (Sindor) a 24 (obecný Sud rumu) — sady na ně odkazují číslem.
-- Ruční ucty se zakládají v tests/build-fixture.sh (hesla se generují, v repu nejsou). Soubor je zároveň „reset“: před vložením
-- smaže všechna dynamická data hry (souboj, efekty, tokeny, události) i testovací data, takže ho lze pouštět před každou sadou.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM aktivni_efekty;
DELETE FROM souboj_vyzva;
DELETE FROM kolo_ucastnik;
DELETE FROM kolo_iniciativa;
DELETE FROM kolo_stav;
DELETE FROM tokeny;
DELETE FROM nestvura_instance;
DELETE FROM nestvura_staty_sveta;
DELETE FROM mapa_kresby;
DELETE FROM mapa_body;
DELETE FROM mlha_valky;
DELETE FROM svet_udalosti;
DELETE FROM svet_obrazky;
DELETE FROM svet_poznamky;
DELETE FROM zdi;
DELETE FROM postava_lektvary;
DELETE FROM postava_zna_kouzlo;
DELETE FROM postava_predmety;
DELETE FROM postava_kontejnery;
DELETE FROM postavy;
DELETE FROM mapy;
DELETE FROM svet_hraci;
DELETE FROM svet;

INSERT INTO `svet` (`id`, `nazev`, `popis`, `pocatecni_datum`, `aktualni_den_offset`, `pj_ucet_id`, `aktivni_mapa_id`, `posledni_shrnuti`, `pripraveno_priste`, `auto_hod_kostkou`, `auto_aplikace_efektu`, `auto_vyhodnoceni_pasti`, `auto_zranitelnost`, `auto_naklad`, `auto_pohyblivost`, `auto_unava`, `vytvoreno`) VALUES (1,'Testovací svět',NULL,'2026-01-01',0,1,NULL,NULL,NULL,0,0,0,0,1,1,1,'2026-10-13 19:58:18');
INSERT INTO `svet_hraci` (`svet_id`, `ucet_id`) VALUES (1,1);
INSERT INTO `svet_hraci` (`svet_id`, `ucet_id`) VALUES (1,3);
INSERT INTO `postavy` (`id`, `svet_id`, `vlastnik_ucet_id`, `nazev`, `rasa_id`, `povolani_id`, `uroven`, `sila`, `obratnost`, `odolnost`, `inteligence`, `charisma`, `aktualni_hp`, `max_hp`, `aktualni_magenergie`, `max_magenergie`, `unava`, `poznamky`, `vytvoreno`, `doprava_typ`, `doprava_zvire_id`, `pohyblivost_rucne`, `zkusenost`, `penize_zl`, `penize_st`, `penize_md`, `velikost`, `zbroj_id`, `stit_id`, `drzena_zbran_id`, `puvod`, `osobnost`) VALUES (1,1,1,'Hrun',6,2,1,19,9,15,8,9,5,5,NULL,NULL,21,NULL,'2026-10-13 19:58:18','zvire',7,NULL,50,12,0,0,NULL,130,NULL,NULL,NULL,'Zbrklý, hrdý, miluje pivo.');
INSERT INTO `postavy` (`id`, `svet_id`, `vlastnik_ucet_id`, `nazev`, `rasa_id`, `povolani_id`, `uroven`, `sila`, `obratnost`, `odolnost`, `inteligence`, `charisma`, `aktualni_hp`, `max_hp`, `aktualni_magenergie`, `max_magenergie`, `unava`, `poznamky`, `vytvoreno`, `doprava_typ`, `doprava_zvire_id`, `pohyblivost_rucne`, `zkusenost`, `penize_zl`, `penize_st`, `penize_md`, `velikost`, `zbroj_id`, `stit_id`, `drzena_zbran_id`, `puvod`, `osobnost`) VALUES (2,1,3,'Sindor',4,1,1,8,12,8,18,17,5,5,3,NULL,0,NULL,'2026-10-13 20:07:50','chuze',NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `postavy` (`id`, `svet_id`, `vlastnik_ucet_id`, `nazev`, `rasa_id`, `povolani_id`, `uroven`, `sila`, `obratnost`, `odolnost`, `inteligence`, `charisma`, `aktualni_hp`, `max_hp`, `aktualni_magenergie`, `max_magenergie`, `unava`, `poznamky`, `vytvoreno`, `doprava_typ`, `doprava_zvire_id`, `pohyblivost_rucne`, `zkusenost`, `penize_zl`, `penize_st`, `penize_md`, `velikost`, `zbroj_id`, `stit_id`, `drzena_zbran_id`, `puvod`, `osobnost`) VALUES (3,1,1,'Prázdná',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,0,0,NULL,NULL,0,NULL,'2026-10-13 20:24:59','chuze',NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `postavy` (`id`, `svet_id`, `vlastnik_ucet_id`, `nazev`, `rasa_id`, `povolani_id`, `uroven`, `sila`, `obratnost`, `odolnost`, `inteligence`, `charisma`, `aktualni_hp`, `max_hp`, `aktualni_magenergie`, `max_magenergie`, `unava`, `poznamky`, `vytvoreno`, `doprava_typ`, `doprava_zvire_id`, `pohyblivost_rucne`, `zkusenost`, `penize_zl`, `penize_st`, `penize_md`, `velikost`, `zbroj_id`, `stit_id`, `drzena_zbran_id`, `puvod`, `osobnost`) VALUES (4,1,1,'Nulová odolnost',1,NULL,1,10,10,0,10,10,0,0,NULL,NULL,0,NULL,'2026-10-13 20:24:59','chuze',NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `postava_predmety` (`id`, `postava_id`, `predmet_id`, `mnozstvi`, `u_sebe`) VALUES (1,1,114,1,1);
INSERT INTO `postava_predmety` (`id`, `postava_id`, `predmet_id`, `mnozstvi`, `u_sebe`) VALUES (2,1,115,1,1);
INSERT INTO `postava_predmety` (`id`, `postava_id`, `predmet_id`, `mnozstvi`, `u_sebe`) VALUES (3,1,130,1,1);
INSERT INTO `postava_predmety` (`id`, `postava_id`, `predmet_id`, `mnozstvi`, `u_sebe`) VALUES (4,1,2,2,1);
INSERT INTO `postava_predmety` (`id`, `postava_id`, `predmet_id`, `mnozstvi`, `u_sebe`) VALUES (5,2,2,1,1);
INSERT INTO `postava_predmety` (`id`, `postava_id`, `predmet_id`, `mnozstvi`, `u_sebe`) VALUES (66,2,39,1,1);
INSERT INTO `postava_zna_kouzlo` (`id`, `postava_id`, `kouzlo_id`) VALUES (1,2,10);
INSERT INTO `postava_zna_kouzlo` (`id`, `postava_id`, `kouzlo_id`) VALUES (4,2,17);
INSERT INTO `postava_zna_kouzlo` (`id`, `postava_id`, `kouzlo_id`) VALUES (5,2,18);
INSERT INTO `postava_zna_kouzlo` (`id`, `postava_id`, `kouzlo_id`) VALUES (2,2,35);
INSERT INTO `postava_lektvary` (`id`, `postava_id`, `lektvar_id`, `mnozstvi`) VALUES (1,1,1,1);
INSERT INTO `postava_lektvary` (`id`, `postava_id`, `lektvar_id`, `mnozstvi`) VALUES (2,1,7,2);
INSERT INTO `mapy` (`id`, `svet_id`, `nazev`, `typ_mapy`, `obrazek_cesta`, `sirka_px`, `vyska_px`, `grid_velikost_px`, `grid_typ`, `mlha_aktivni`, `grid_posun_x`, `grid_posun_y`, `vytvoreno`) VALUES (1,1,'Testovací mapa','svet','testmapa_ctverec.png',2400,1800,70,'ctverec',1,0,0,'2026-10-14 05:48:54');
INSERT INTO `mapy` (`id`, `svet_id`, `nazev`, `typ_mapy`, `obrazek_cesta`, `sirka_px`, `vyska_px`, `grid_velikost_px`, `grid_typ`, `mlha_aktivni`, `grid_posun_x`, `grid_posun_y`, `vytvoreno`) VALUES (2,1,'Hex bojiště','zona','testmapa_hex.png',3200,2200,45,'hex',1,0,0,'2026-10-14 06:40:12');
INSERT INTO `zdi` (`id`, `mapa_id`, `x1`, `y1`, `x2`, `y2`, `sirka_px`, `blokuje_pohyb`, `blokuje_vystrel`, `viditelna_hracum`, `vytvoreno`) VALUES (6,1,560,280,595,385,14,1,1,1,'2026-10-14 07:07:25');
INSERT INTO `tokeny` (`id`, `mapa_id`, `typ_entity`, `entita_id`, `x`, `y`, `z_poradi`, `smer`, `velikost_sahu`, `hexu`, `barva`, `zamceno`, `nazev`, `obrazek_id`, `viditelny_hracum`) VALUES (1,1,'postava',1,245,175,0,0,1.00,1,NULL,0,NULL,NULL,1);
INSERT INTO `tokeny` (`id`, `mapa_id`, `typ_entity`, `entita_id`, `x`, `y`, `z_poradi`, `smer`, `velikost_sahu`, `hexu`, `barva`, `zamceno`, `nazev`, `obrazek_id`, `viditelny_hracum`) VALUES (2,1,'postava',2,665,315,0,45,1.00,1,NULL,0,NULL,NULL,1);
INSERT INTO `tokeny` (`id`, `mapa_id`, `typ_entity`, `entita_id`, `x`, `y`, `z_poradi`, `smer`, `velikost_sahu`, `hexu`, `barva`, `zamceno`, `nazev`, `obrazek_id`, `viditelny_hracum`) VALUES (24,1,'obecny',0,805,455,0,0,2.00,1,'#d9822b',1,'Sud rumu',NULL,1);

SET FOREIGN_KEY_CHECKS = 1;
