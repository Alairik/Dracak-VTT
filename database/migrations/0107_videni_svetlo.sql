-- Vidění a světlo (docs/videni-a-svetlo-navrh.md, rozhodnutí uživatele 2026-11-02):
--   mapy.osvetleni          — 'den' (výchozí, nic se nemění) nebo 'tma' (hráči vidí jen to, co osvětlí zdroje světla; živé vidění místo trvale odhalené mlhy)
--   tokeny.svetlo_sahy      — dosah světla, které token nese nebo vydává (pochodeň 6, lucerna 8 sáhů…); prázdné = nesvítí
--   tokeny.svetlo_typ       — název zdroje pro rozhraní (pochoden / lucerna / hul / svetlo / vlastni)
--   tokeny.videni_infra_sahy — infravidění tvora v sázích (trpaslík 20, lovec 50); prázdné = nemá
--   tokeny.studeny          — 1 = studenokrevný tvor, infravidění ho nevidí
-- Samé DDL (ALTER ADD) — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin
-- (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md. Bez sloupců zůstává mapa jako dosud (nabídka světla a tmy se nezobrazuje).

ALTER TABLE mapy ADD COLUMN osvetleni VARCHAR(8) NOT NULL DEFAULT 'den' COMMENT 'den / tma — ve tmě hráči vidí jen osvětlené místo';
ALTER TABLE tokeny ADD COLUMN svetlo_sahy DECIMAL(4,1) NULL COMMENT 'dosah světla tokenu v sázích (NULL = nesvítí)';
ALTER TABLE tokeny ADD COLUMN svetlo_typ VARCHAR(16) NULL COMMENT 'pochoden / lucerna / hul / svetlo / vlastni';
ALTER TABLE tokeny ADD COLUMN videni_infra_sahy SMALLINT UNSIGNED NULL COMMENT 'dosah infravidění v sázích (NULL = nemá)';
ALTER TABLE tokeny ADD COLUMN studeny TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = studenokrevný tvor, infravidění ho nevidí';
