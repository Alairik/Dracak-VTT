-- Tažný kůň: nosnost v tahu 1000 mn (viz 0075) — o čtvrtinu víc než klasický
-- kůň (800 mn): 800 × 1,25 = 1000. Čistě DML, proběhne automaticky po ručním
-- ALTER z 0075.

UPDATE jizdni_zvirata SET nosnost_tah_mn = 1000 WHERE nazev = 'tažný kůň';
