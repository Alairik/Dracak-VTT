# Návrh souboje (rozšířený soubojový systém) — podklad k odsouhlasení

Stav: **návrh k odsouhlasení, nic z toho zatím není postavené** (blok B plánu
„náhrada Owlbear Rodeo“, viz `docs/stav-projektu.md`). Sepsáno 2026-10-14 z textu
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
   akcí (1–6 = 2, 7–12 = 4 …). Arpag 5, Bagrot 4, Roxan 2+1 = 3; všichni 2 akce.
3. **Kolo, tah po tahu** — panel pořadí zvýrazní, kdo je na tahu, a ukáže zbývající
   akce a kolik jich smí v tomto tahu použít (nejvýš polovinu původních).
   - Arpag: **Krok** o hex k Bagrotovi (volný sousední hex, otočka až 120°).
   - Bagrot: **Pohyb** — 2 hexy k Roxan.
   - Roxan: **Kouzlení** (okno kouzla: kouzlo, cíl, cena v magech, průběh 1/2,
     tlačítko „přerušit“). Po první akci už Rychlost působí → Arpagovi se
     zdvojnásobí zbývající akce.
   - Arpag: **Krok a útok** na Bagrota → okno **Útok** (ÚČ rozepsané na části,
     automatické opravy, hod 1k6+) → útok přeruší tah a otevře okno **Obrana**
     Bagrotovi: „bránit se (stojí akci)“ / „nebránit se (−3)“ → okno **Výsledek**
     (zásah ano/ne, zranění = rozdíl + útočnost, životy se odečtou, bojeschopnost).
   - Arpag: druhá akce **Útok** — Bagrot už nemá akci → brání se jen brněním (−3).
   - Bagrot: **Útok** na Roxan, která zrovna kouzlí poslední akci → útok na nehybného
     (−5 místo obratnosti, −3, bez štítu); je-li zraněna, kouzlo už běží dál.
   Kolo končí, až všichni vyčerpají akce.
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

Dnešní „Další na tahu“ předává tah po každé jedné akci; kniha dovoluje víc akcí
v řadě (nejvýš polovinu původních) — u 2 akcí je to totéž, od 4 akcí ne.

## Rozhodnuto (uživatel, 2026-10-14)

- **Tah po více akcích podle knihy**: v jednom tahu aspoň 1 a nejvýš polovina
  akcí (h1623). **Rychlost uprostřed kola** → polovina se počítá z **nového**
  (zdvojeného) počtu.
- **Překvapení v rozšířeném systému = −6 k iniciativě** (řádek tabulky), ne
  „nemůže nic dělat“.
- **Hody se nikdy nehází samy.** U každého hodu (1k6+, 1k10…) je tlačítko „Hodit“ →
  ukáže se 3D kostka, a **hodnoty, které kostky ukážou, jsou hod** (klient je pošle,
  server jen hlídá rozsah). Alternativně hráč **zapíše hod ručně** (fyzická
  kostka). Šestka u 1k6+ = další hod. Automatizuje se jen to ostatní: součty,
  opravy, zranění, životy, bojeschopnost (PJ může zranění jedním klikem opravit).
  Ověřeno: vendorovaná kostka (`dice-box` 1.1.4) neumí předepsat výsledek (`2d6@3,4`
  se ignoruje) — proto výsledek bere z kostek, ne ze serveru.
- **Iniciativa nestvůr a postav ovládaných PJ je pro hráče skrytá**; hráč vidí
  jen postavy, které ovládá.
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
  *K potvrzení:* humanoidní C (obr), a „F“ — v knize velikosti končí na E.

## Co se při kontrole ukázalo

- **Tabulka iniciativy a akcí** (h1623): v textu je **1–6 = 2 akce**, 7–12 = 4, … Dva
  příklady v knize (iniciativa 3 → „všichni mají dvě akce“, h1623 i h1626) to
  potvrzují. Příklad „Arpagovi padne 7 (2 akce)“ (h1623, b10822) tabulce odporuje →
  pravděpodobně překlep, do kontroly. Hodnotu „1–3 = 1 akce“ jsem nikde nenašel;
  pokud ji uživatel pamatuje z knihy, je potřeba foto str. 78.
- **Smrt / vyřazení:** odstavec o prahu smrti postavy jsem v přepisu nenašel.
  Stopy: mez vyřazení a bojeschopnost (h1610); příklad, kde nestvůra s 0 životy je
  „na místě mrtvá“ (h1612); u berserka „0 a méně = padá k zemi, do 3 kol umírá“
  (HB, h134); dovednost První pomoc „zastaví umírání při klesnutí životů pod 0“
  (HB, h1806). Potřeba foto/odkaz na PPZ.

## Otevřené věci

- Hexy: humanoidní C a velikost F (viz výš); kotva víc-hexového tvora (který hex je
  „přední“ pro útok na 3 hexy před sebou).
- Co hráč vidí z pořadí: návrh = jen iniciativu vlastních postav a označení, kdo
  je právě na tahu (bez čísel a pořadí ostatních).
- Práh smrti postavy (viz výš).

## Navržené pořadí stavby

1. Zahájit souboj (překvapení −6), úmysly, iniciativa s tlačítkem hodu + 3D kostka
   + ruční zápis, skrytá iniciativa pro hráče, tah po více akcích.
2. Lišta akcí a pohyb po hexech (krok, otočka, čelo, 3 hexy před sebou, víc-hexoví tvorové).
3. Útok / obrana / výsledek (hody tlačítkem), zranění automaticky + oprava, bojeschopnost,
   vyřazení.
4. Malý deník nestvůry (staty z čísel v zápisu), kořist.
5. Kouzlení po akcích, střelba, útěk, zvláštní případy, později finty.
