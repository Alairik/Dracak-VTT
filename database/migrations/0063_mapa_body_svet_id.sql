-- Piny dosud měly svět jen nepřímo (mapa_body.mapa_id → mapy.svet_id),
-- stejně jako třeba tokeny — na rozdíl od svet_poznamky, co svet_id nese
-- přímo. Poznámka z konverzace: piny mají mít napojení na svět taky
-- přímo — zjednoduší to kontrolu oprávnění při editaci pinu (bez JOINu
-- přes mapy) a sjednotí to vzor s poznámkami.
--
-- Bezpečné doplnit zpětně beze ztráty/nejasnosti dat: mapa_id je na
-- mapa_body odjakživa NOT NULL a pin se dodnes nedal přesunout na jinou
-- mapu (jen cilova_mapa_id, to je něco jiného — kam pin VEDE, ne kde
-- sám je), takže svet_id = mapy.svet_id podle mapa_id je jednoznačné
-- a nemůže se do budoucna rozjet, dokud takovou funkci (přesun pinu na
-- mapu jiného světa) někdo záměrně nepřidá.
ALTER TABLE mapa_body ADD COLUMN svet_id INT UNSIGNED NULL AFTER id;

UPDATE mapa_body b JOIN mapy m ON m.id = b.mapa_id SET b.svet_id = m.svet_id;

ALTER TABLE mapa_body
    MODIFY COLUMN svet_id INT UNSIGNED NOT NULL,
    ADD KEY svet_id (svet_id),
    ADD CONSTRAINT fk_mapa_body_svet FOREIGN KEY (svet_id) REFERENCES svet (id) ON DELETE CASCADE;
