# Dracak-VTT

Editor pravidel a databáze pro Dračí hlídku (DRH), s přihlášením a rolemi
(admin / PJ / hráč). Vedle toho poběží mapa (přenesená z [Torch](https://github.com/Alairik/Torch)).

## Stav

- ✅ Auth + role (admin/pj/hrac), skrývání záznamů (bestiář, PJ pravidla) přes RLS
- ✅ Základní editor: navigace podle typů záznamů, rychlá šablona pro nový záznam
- ⏳ Reálné schéma entit (kouzla, příšery, povolání, rasy…) — čeká na import
  `drd-db-full-v1.sql` (13 tabulek, 1703 řádků: 41 povolání, 31 ras, 699 kouzel,
  208 příšer). Zatím je `entities` obecná tabulka se `stats`/`scaling`/`variants`
  jako JSONB, viz `supabase/migrations/0001_init_auth_roles.sql`.
- ⏳ Mapa (zatím jen odkaz na Torch, `mapa.html`)
- ⏳ Zakládání nových účtů (vyžaduje Supabase service-role klíč / Edge Function,
  zatím se dělá přes Supabase dashboard → Authentication → Invite user)

## Spuštění

Žádný build krok — čistý statický web (HTML/CSS/JS), nahraje se na hosting
tak jak je.

1. Založ projekt na [supabase.com](https://supabase.com) (zdarma).
2. V SQL editoru spusť `supabase/migrations/0001_init_auth_roles.sql`.
3. Do `assets/js/config.js` doplň `SUPABASE_URL` a `SUPABASE_ANON_KEY`
   (Project Settings → API — jde o veřejný anon klíč, ochranu dat řeší RLS
   v migraci, ne tajnost klíče).
4. Pozvi první účet přes Supabase dashboard a v tabulce `profiles` mu ručně
   nastav `role = 'admin'`, než bude hotová admin obrazovka pro zakládání účtů.

## Role

- **admin** — spravuje účty a role (`admin.html`), edituje vše
- **pj** — vidí a edituje vše včetně bestiáře a PJ pravidel
- **hrac** — nevidí záznamy označené jako skryté (bestiář, PJ pravidla ve
  výchozím stavu); smí zakládat/editovat jen typy záznamů, které mu admin/PJ
  povolí (`profiles.editable_types`)
