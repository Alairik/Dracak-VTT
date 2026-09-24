-- Dracak-VTT: základní schéma pro účty, role a editor pravidel.
-- DRAFT: entity/stats sloupce budou upřesněny po importu drd-db-full-v1.sql
-- (41 povolání, 31 ras, 699 kouzel, 208 příšer + další tabulky).
-- Tahle migrace řeší jen auth/role vrstvu, aby na ní šlo stavět nezávisle
-- na tom, kdy reálná herní data dorazí.

create type public.user_role as enum ('admin', 'pj', 'hrac');

-- Rozšíření auth.users o herní roli a oprávnění k editaci.
create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  display_name text not null,
  role public.user_role not null default 'hrac',
  -- typy entit (entity_types.slug), které smí hráč sám zakládat/editovat.
  -- PJ a admin mají editační práva na vše vždy, tohle pole se pro ně nepoužívá.
  editable_types text[] not null default '{}',
  created_at timestamptz not null default now()
);

alter table public.profiles enable row level security;

create policy "profiles: viditelné pro přihlášené"
  on public.profiles for select
  to authenticated
  using (true);

create policy "profiles: admin spravuje účty"
  on public.profiles for update
  to authenticated
  using (exists (select 1 from public.profiles p where p.id = auth.uid() and p.role = 'admin'));

-- Číselník typů entit (kouzlo, příšera, dovednost, lektvar, povolání, rasa...).
-- hidden_by_default = true => nové záznamy tohoto typu jsou ve výchozím stavu
-- skryté hráčům (bestiář, PJ pravidla), aby PJ nemusel skrývání řešit ručně.
create table public.entity_types (
  slug text primary key,
  name text not null,
  hidden_by_default boolean not null default false,
  sort_order int not null default 0
);

insert into public.entity_types (slug, name, hidden_by_default, sort_order) values
  ('kouzlo', 'Kouzlo', false, 10),
  ('dovednost', 'Dovednost', false, 20),
  ('schopnost', 'Schopnost povolání', false, 30),
  ('lektvar', 'Lektvar / elixír', false, 40),
  ('vybaveni', 'Vybavení', false, 50),
  ('prisera', 'Příšera (bestiář)', true, 60),
  ('povolani', 'Povolání', false, 70),
  ('rasa', 'Rasa', false, 80),
  ('pj_pravidlo', 'PJ pravidlo / poznámka', true, 90);

-- Samotné záznamy. Šablona pro rychlé vyplnění vychází z polí níže:
-- title/level/description = rychlá šablona, stats/combat = čísla a efekt v boji.
-- origin: null/'obecne'/'priser' = nepřiřazeno povolání, jinak slug povolání.
create table public.entities (
  id uuid primary key default gen_random_uuid(),
  type_slug text not null references public.entity_types(slug),
  title text not null,
  level int,
  category text,
  origin text, -- povolání, ke kterému patří; 'obecne' nebo 'priser' když ne
  description text,
  combat_effect text, -- efekt v boji, součást rychlé šablony
  stats jsonb not null default '{}'::jsonb, -- Mana, Dosah, Rozsah, Trvání, ...
  scaling jsonb not null default '[]'::jsonb, -- bonusy podle úrovně
  variants jsonb not null default '[]'::jsonb, -- alternativní verze (L6/L16...)
  hidden_from_players boolean not null default false,
  created_by uuid references public.profiles(id),
  updated_at timestamptz not null default now()
);

alter table public.entities enable row level security;

create policy "entities: hráč nevidí skryté"
  on public.entities for select
  to authenticated
  using (
    hidden_from_players = false
    or exists (
      select 1 from public.profiles p
      where p.id = auth.uid() and p.role in ('admin', 'pj')
    )
  );

create policy "entities: PJ/admin editují vše"
  on public.entities for all
  to authenticated
  using (
    exists (select 1 from public.profiles p where p.id = auth.uid() and p.role in ('admin', 'pj'))
  )
  with check (
    exists (select 1 from public.profiles p where p.id = auth.uid() and p.role in ('admin', 'pj'))
  );

create policy "entities: hráč edituje povolené typy"
  on public.entities for insert
  to authenticated
  with check (
    exists (
      select 1 from public.profiles p
      where p.id = auth.uid() and p.role = 'hrac' and entities.type_slug = any(p.editable_types)
    )
  );

create policy "entities: hráč upravuje vlastní záznamy povolených typů"
  on public.entities for update
  to authenticated
  using (
    created_by = auth.uid()
    and exists (
      select 1 from public.profiles p
      where p.id = auth.uid() and p.role = 'hrac' and entities.type_slug = any(p.editable_types)
    )
  );
