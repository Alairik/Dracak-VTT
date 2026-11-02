-- Noční vidění (docs/videni-a-svetlo-navrh.md, rozhodnutí uživatele 2026-11-02: stav mapy „Noc“, noční vidění lupiče; lupičovo „Zlepšení zraku“ h630 b5663):
--   tokeny.videni_noc_sahy — dosah nočního vidění tvora v sázích (lupič se Zlepšením zraku, kočka, sova…); prázdné = nemá.
--   Stav mapy „noc“ se do mapy.osvetleni ukládá jako další hodnota (sloupec je VARCHAR(8), žádná změna struktury).
-- Samé DDL (ALTER ADD) — web účet (DML-only na Wedosu) ho neprovede, MUSÍ se spustit ručně přes phpMyAdmin (docs/sql-k-rucnimu-spusteni.sql), viz CLAUDE.md.
-- Bez sloupce vidění funguje dál; noční vidění a jeho řádek v panelu tokenu se prostě nezobrazí.

ALTER TABLE tokeny ADD COLUMN videni_noc_sahy SMALLINT UNSIGNED NULL COMMENT 'dosah nočního vidění v sázích (NULL = nemá)';
