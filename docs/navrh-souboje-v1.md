# Návrh souboje (rozšířený soubojový systém) — podklad k odsouhlasení

Stav: **návrh odsouhlasený uživatelem; krok 1 stavby je hotový a zapnutý na produkci**
(zahájení souboje, úmysly, iniciativa s hodem tlačítkem + 3D kostkou, tahy ve dvou
půlkách), kroky 2–5 zbývají (blok B plánu „náhrada Owlbear Rodeo“,
viz `docs/stav-projektu.md`). Sepsáno 2026-10-14 z textu
pravidel v `content/pravidla-hrac.html` (h1604–h1629, h1685–h1686, h158–h159)
a `content/pravidla-pj.html` (h2170, h2335–h2347).

Základ je **rozšířený soubojový systém** (hexy, akce, čelo tvora). Základní
systém (jedna kostka útok, jedna obrana, bez mapy) se u stolu dá hrát i bez VTT.

## Zdroje v pravidlech (co z čeho beru)

| Téma | Kde |
|---|---|
| Kolo = ~10 s, překvapení, iniciativa v základním systému | h1606–h1608 |
| Útok: ÚČ = SZ + bonus síly (+1 rodová zbraň), hod 1k6+ (šestka se přehazuje a sčítá), opravy: rozdíl velikostí, bojeschopnost, −1 velké naložení | h1610 |
| Obrana: OČ = KZ + bonus obratnosti, +1 štít, OZ zbraně, −3 bez zbraně, bojeschopnost, −1 naložení | h1611 |
| Zásah, když útok ≥ obrana; zranění = rozdíl + útočnost zbraně, aspoň 1 život | h1612 |
| Nehybný (−5 místo obratnosti, −3, bez štítu/velikosti), vyřazený (× úroveň, max 9), neviditelný (−5 / −4), naplocho (stínové zranění) | h1613 |
| Střelba: ÚČ = SZ + bonus obratnosti, dostřel malý/střední/velký (+1 / 0 / −1), úkryt, bez obrany zbraní; cíl ≥ 2 hexy, volná dráha | h1614–h1617, h1625 |
| Přesila (max 6 útočníků), předstíraný výpad (2k6+), útok zezadu, útěk | h1618, h1624 |
| Pořadí událostí: 1 úmysly → 2 iniciativa → 3 činnost → 4 strana, co ztratila iniciativu → 5 výsledky | h1619, h2170 |
| Hexy: 1 hex = 1 sáh, 1 tvor na hex (kromě A0), tvor je čelem k jedné z 6 stran | h1621 |
| Hod na iniciativu: 1k6 + bonusy/postihy z tabulky; výsledek ≤ 0 → činnost až příští kolo; počet akcí podle výsledku | h1622, h1623 |
| Akce: čekání, útok, obrana, krok, otočka, krok+útok, otočka+útok, obrana+krok, jiná činnost, kouzlení; v tahu aspoň 1 a nejvýš polovina původních akcí; obrana mimo tah stojí akci | h1623 |
| Útok jen na 3 sousední hexy před sebou; boční/zadní útok −4 (−6 když se obránce nebrání); dlouhé zbraně dál | h1623, h1628 |
| Kouzlení: 2× „vyvolání“ akcí po sobě; účinek po předposlední akci; přerušení; útok na kouzlícího = nehybný | h1626 |
| Pohyb: celé kolo krokem, až 2 hexy; útěk: ≥ 5 sáhů od nepřítele nebo 3 kola pohybu | h1625, h1627 |
| Pasti (hod proti pasti 1k10 + vlastnost vs. nebezpečnost) | h1686 |
| PJ: shoda v iniciativě rozhodují obratnost, KZ, zranění, náklad, síla, inteligence; nestvůry stejného druhu jedním hodem; vícenásobné útoky nestvůr +6 iniciativy za každý útok navíc | h2336, h2342, h2343 |
| PJ: hexy nestvůr (A = 1, B = 2, C = 3 …), překvapení, útěk, střelba přes obsazený hex | h2337–h2341 |
| Tabulka bonusů a postihů k iniciativě | v DB (migrace 0045, z fotky); text knihy ji nemá přepsanou |
| Finty šermíře (od 16. úrovně, HB) | h158–h163, 30 fint v DB |

## Imaginární souboj o 3 tokenech

Arpag (válečník, hráč 1), Roxan (kouzelník, hráč 2), Bagrot (skřet, PJ) —
postavený na příkladu v knize (h1626) a doplněný o obranu, zranění a výběr akcí.

0. **Před bojem** — PJ rozhodne o překvapení (okno „Zahájit souboj“: ani jedna /
   jedna / obě strany). Překvapená strana v 1. kole nic nedělá; obě = nikdo.
   Tokeny stojí na hexech, každý čelem k jedné ze šesti stran.
1. **Úmysly** — okno pro každého hráče: činnost kola (kouzlení / střelba / útok /
   pohyb / jiná činnost / čekání / útěk, + cíl). PJ zadá úmysly nestvůr tajně.
   Arpag: útok. Roxan: kouzlení Rychlost na Arpaga. Bagrot: pohyb k Roxan.
2. **Iniciativa** — okno s tokeny: 1k6 + automatické bonusy (kouzlení +1, obouruční
   zbraň −2, vícenásobné útoky, Rychlost, únava, naložení…) → výsledek → počet
   akcí (**HB, po třech:** 1–3 = 1, 4–6 = 2, 7–9 = 3, 10–12 = 4 …). Arpag 5, Bagrot 4,
   Roxan 2+1 = 3 → Arpag 2 akce, Bagrot 2 akce, Roxan 1 akce.
3. **Kolo ve dvou polovinách (HB)** — panel pořadí zvýrazní, kdo je na tahu, a ukáže
   jeho akce (puntíky). V **1. polovině** (podle iniciativy od nejvyšší) odehraje každý
   **větší půlku** svých akcí (u lichého počtu zaokrouhleno nahoru: 1 → 1, 2 → 1,
   3 → 2, 4 → 2, 5 → 3), ve **2. polovině** (zase od nejvyšší iniciativy) zbytek.
   - 1. polovina — Arpag (2 akce): **Krok** o hex k Bagrotovi (volný sousední hex,
     otočka až 120°).
   - Bagrot (2 akce): **Pohyb** — 2 hexy k Roxan.
   - Roxan (1 akce): **Kouzlení** (okno kouzla: kouzlo, cíl, cena v magech, průběh
     1/2, tlačítko „přerušit“). Kouzlo působí už po první akci → Arpagovi se
     zdvojnásobí zbývající akce (z 1 na 2). Druhá akce kouzlení je až v příštím kole.
   - 2. polovina — Arpag (2 akce): **Krok a útok** na Bagrota → okno **Útok** (ÚČ
     rozepsané na části, automatické opravy, hod 1k6+) → útok přeruší tah a otevře
     okno **Obrana** Bagrotovi: „bránit se (stojí akci)“ / „nebránit se (−3)“ →
     okno **Výsledek** (zásah ano/ne, zranění = rozdíl + útočnost, životy se odečtou,
     bojeschopnost). Pak druhá akce **Útok** — Bagrot už nemá akci → brání se jen
     brněním (−3).
   - Bagrot (1 akce zbývá, ale jednu spotřeboval na obranu → 0).
   - Roxan: nemá akci. (Kdyby Bagrot zaútočil na Roxan, útočí na nehybného:
     −5 místo obratnosti, −3, bez štítu; je-li zraněna, kouzlo už běží dál.)
   Kolo končí po 2. polovině.
4. **Konec kola** — odpočítají se trvání efektů (kolo seslání se nepočítá), číslo
   kola +1, znovu úmysly a iniciativa (Arpag s Rychlostí má +6 a 4 akce).
5. **Konec souboje** — nestvůry mrtvé/vzdaly se/utekly (útěk → pronásledování),
   nebo všechny postavy mrtvé (konec dobrodružství). Pak kořist, zkušenosti,
   léčení a odpočinek.

## Okna a ovládání (návrh)

Panel **Souboj** (kolo, fáze, pořadí, akce jako puntíky; PJ: zahájit / další fáze /
ukončit) · **Úmysly** · **Iniciativa** · **Lišta akcí** u tokenu na tahu · **Útok** ·
**Obrana** (vyskočí obránci, i PJ) · **Kouzlení** · **Střelba** (míření po akcích) ·
**Výsledek** · zvýraznění hexů (kam smí krok, 3 hexy před tokenem, dosah, dostřel).

## Co v systému už je a co chybí

Je: iniciativa se všemi bonusy a automatikou únavy/naložení, počet akcí z tabulky,
pořadí tahů, hody na útok a obranu z deníku (server počítá ÚČ/OČ a opravy),
životy, efekty, otáčení tokenů na 6 stran, hexová vzdálenost, zdi, zámek tahu.

Chybí: úmysly a překvapení, výběr akcí a jejich cena, cíle podle čela, okno útoku
a obrany s přerušením tahu, zranění a bojeschopnost přímo v boji, kouzlení po
akcích, střelba, útěk, finty, vícenásobné útoky nestvůr, ÚČ/OČ nestvůr jako čísla
(v bestiáři je to volný text).

Dnešní „Další na tahu“ předává tah po každé jedné akci; podle domácího pravidla
(dvě poloviny kola) má tvor v jednom tahu větší půlku akcí (1. polovina) nebo zbytek
(2. polovina) — u 2 akcí je to totéž jako dnes, od 3 akcí ne.

## Rozhodnuto (uživatel, 2026-10-14)

- **Kolo se dělí na dvě poloviny (domácí pravidlo, HB, nahrazuje „aspoň jednu a
  nejvýš polovinu akcí v tahu“ z knihy, h1623/b10819):** v 1. polovině každý podle
  iniciativy (od nejvyšší) odehraje **větší půlku** svých akcí, ve 2. polovině (zase
  od nejvyšší) zbytek. Počet akcí v tahu tedy vychází z aktuálního stavu: 1. polovina
  = `zbývá − floor(celkem / 2)`, 2. polovina = vše, co zbývá. **Rychlost** (kniha:
  zdvojí akce, které má tvor „v daném okamžiku k dispozici“, h1626) a **protoplazma**
  (sníží je na polovinu dolů) se tak řeší sama: mění počet zbývajících akcí a
  2. polovina se odehraje celá. Obrana mimo tah (h1623) stojí jednu akci ze zbývajících.
- **Překvapení v rozšířeném systému = −6 k iniciativě** (řádek tabulky), ne
  „nemůže nic dělat“.
- **Hody se nikdy nehází samy.** U každého hodu (1k6+, 1k10…) je tlačítko „Hodit“ →
  ukáže se 3D kostka, a **hodnoty, které kostky ukážou, jsou hod** (klient je pošle,
  server jen hlídá rozsah). Alternativně hráč **zapíše hod ručně** (fyzická
  kostka). Šestka u 1k6+ = další hod. Automatizuje se jen to ostatní: součty,
  opravy, zranění, životy, bojeschopnost (PJ může zranění jedním klikem opravit).
  Ověřeno: vendorovaná kostka (`dice-box` 1.1.4) neumí předepsat výsledek (`2d6@3,4`
  se ignoruje) — proto výsledek bere z kostek, ne ze serveru.
- **Iniciativa nestvůr a postav ovládaných PJ je pro hráče skrytá**; iniciativu
  hráčských postav smějí vidět všichni hráči (rozhodnuto 2026-10-14).
- **Nestvůry dostanou „malý deník“** (životy, ÚČ/OČ, odolnost, inteligence,
  charisma, pohyblivost, vytrvalost, výbava/kořist, schopnosti, kouzla), na který
  platí stejná pravidla jako na postavy (nosnost, únava, kouzla). Staty se
  v souboji odvodí z čísel v zápisu nestvůry, např. „(+4 + 5) = 9“ = bonus za sílu
  +4 a síla zbraně 5; OČ „(−1 + 7) = 6“ = bonus za obratnost −1 a KZ 7. Rozbor
  bestiáře (208 nestvůr): ÚČ se čistě rozebere u 112, OČ u 133; zbytek má proměnné
  (X), „dle podoby“ nebo „závisí“ → PJ doplní před bojem.
- **Zbytek návrhu (okna, pořadí událostí, zranění automaticky + oprava) schválen.**
- **Zabrané hexy podle velikosti (úprava pro mapu):** A0, A, B = 1 hex; zvířecí C =
  2 hexy vedle sebe; D = 3 hexy u sebe; E = 5 hexů (2+3); F = 7 hexů (2+3+2).
  Humanoidní C = 1 hex, velikost F není (potvrzeno).

## Co se při kontrole ukázalo

- **Tabulka iniciativy a akcí** (h1623): **homebrew, po třech** — 1–3 = 1 akce, 4–6 = 2,
  7–9 = 3, 10–12 = 4, … (rozhodnutí uživatele 2026-10-14). Kniha (PPZ) měla 1–6 = 2,
  7–12 = 4, …; homebrew má přednost, tabulka v `content/pravidla-hrac.html` je přepsaná
  (ids b10810–b10815 + nové b93070, b93071) a kód počítá `ceil(výsledek / 3)`.
  Dva příklady v knize (iniciativa 3 → „všichni mají dvě akce“, h1623 i h1626) a
  „Arpagovi padne 7 (2 akce)“ (b10822) s HB tabulkou nesedí; ponechány podle knihy,
  zapsáno do `kontrolni-seznam-neuplnych-mist.md`.
- **Smrt / vyřazení (rozhodnuto 2026-10-14):** řeší se jen **mez vyřazení** (h1610,
  v kódu `dracak_vtt_bojeschopnost()`; vyřazená = životy ≤ mez). Vyřazená postava
  je **nehratelná** — nemá akce a nehýbe se, dokud jí heal v boji nevrátí životy nad
  mez. Kdo není nad mezí na konci boje, umírá; to se řeší už mimo hru a rozhoduje
  PJ. Žádné počítání kol do smrti, žádný práh smrti.

## Otevřené věci

- Hexy: kotva víc-hexového tvora (který hex je „přední“ pro útok na 3 hexy před
  sebou). Velikost F neexistuje (potvrzeno), největší je E = 5 hexů.
- Co hráč vidí z pořadí (rozhodnuto): iniciativu **hráčských** postav vidí všichni
  hráči (jejich vlastní i ostatních), iniciativu nestvůr a PJ-ovládaných postav ne;
  nestvůra na tahu je jen zvýrazněná na mapě.

## Navržené pořadí stavby

1. ✔ Zahájit souboj (překvapení −6), úmysly, iniciativa s tlačítkem hodu + 3D kostka
   + ruční zápis, skrytá iniciativa pro hráče, kolo ve dvou půlkách. (Hotovo, na produkci.)
2. Lišta akcí a pohyb po hexech (krok, otočka, čelo, 3 hexy před sebou, víc-hexoví tvorové).
3. Útok / obrana / výsledek (hody tlačítkem), zranění automaticky + oprava, bojeschopnost,
   vyřazení.
4. Malý deník nestvůry (staty z čísel v zápisu), kořist.
5. Kouzlení po akcích, střelba, útěk, zvláštní případy, později finty.
