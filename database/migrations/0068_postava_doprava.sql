-- Na čem postava zrovna cestuje — Fáze 2 plánu cestování (docs/stav-projektu.md).
--
-- doprava_typ      'chuze' (výchozí) / 'zvire'
-- doprava_zvire_id jízdní zvíře z číselníku jizdni_zvirata (0067), NULL
--                  při chůzi
--
-- Záměrně BEZ vynuceného vlastnictví: postava jede na zvířeti, které si
-- vybere z číselníku, i když je půjčené, ukradené nebo patří družině
-- (rozhodnutí uživatele). Vazba na inventář ("moje zvíře" jako první
-- nabídka) je pozdější rozšíření, ne podmínka. Konzistenci dvojice
-- (zvire <=> doprava_zvire_id vyplněno) drží aplikace (hra/postava.php),
-- ne CHECK — nevím, jakou verzi MariaDB na Wedosu CHECK vynucuje.
--
-- Závisí na 0067 (FK na jizdni_zvirata). ALTER = DDL, web účet ho
-- neprovede, musí se spustit ručně přes phpMyAdmin (admin účet) až PO
-- vytvoření tabulky jizdni_zvirata; migrate.php ho pak při dalším
-- deployi sám zapíše do migrace_log (pozná, že sloupec už existuje).

ALTER TABLE postavy
    ADD COLUMN doprava_typ ENUM('chuze','zvire') NOT NULL DEFAULT 'chuze',
    ADD COLUMN doprava_zvire_id TINYINT UNSIGNED NULL,
    ADD CONSTRAINT fk_postavy_doprava_zvire FOREIGN KEY (doprava_zvire_id) REFERENCES jizdni_zvirata (id);
