# Připravené migrace, které ještě NESMÍ do `database/migrations/`

Soubory tu čekají na potvrzení, že uživatel provedl ruční SQL (`docs/sql-k-rucnimu-spusteni.sql`). `migrate.php` tuhle složku
nečte, takže nic nespustí. Jakmile uživatel napíše, že SQL proběhlo:

1. přesunout (`git mv`) soubory odsud do `database/migrations/`,
2. vyprázdnit `docs/sql-k-rucnimu-spusteni.sql` (jen komentář „nic nečeká“),
3. pushnout na `main` — deploy je spustí přes `migrate.php` (DDL zrcadlo pod DML-only účtem jen „přeskočí“, protože sloupce
   už existují; data se doplní),
4. upravit `docs/stav-projektu.md` (sekce „Čeká na ruční spuštění“) a smazat tuhle složku.

- `0095_zbrane_ini_ddl.sql` — zrcadlo ručního SQL „Zbraně a iniciativa“ (`predmety.chvat`, `trida_zbrane`, `ini_postih`). Samé ALTER.
- `0096_zbrane_ini_data.sql` — chvat / třída / postih k iniciativě pro 80 zbraní podle tabulek zbraní uživatele (jen UPDATE) a oprava
  popisu Warglaive. Musí jít až po 0095.

Ověřeno na kopii produkce: ruční SQL (admin) po částečném provedení i podruhé, potom `migrate.php` pod DML-only účtem (0095 přeskočí,
0096 doplní data) a znovu (nic k dělání).
