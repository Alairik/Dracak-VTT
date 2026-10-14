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

## Otevřené věci k rozhodnutí / ověření

- Rychlost seslaná v půlce kola: „polovina původních akcí“ se počítá z původních 2,
  nebo z nových 4? (Příklad v knize odpovídá nově zdvojenému počtu.)
- Překvapení: v základním systému strana nic nedělá; v tabulce iniciativy je
  „překvapení −6“. Brát obojí?
- Shoda v iniciativě: kniha hráčů říká obratnost a pak PJ; PJ kniha má delší
  pořadí (obratnost, KZ, zranění, náklad, síla, inteligence).
- Hexy nestvůr (h2341): „A = 1, B = 2, C = 3“ hexy — ale člověk (B) stojí na 1 hexu
  (h1621). Text vypadá poškozený → do kontroly při proofreadingu.
- Smrt: v příkladu (h1612) je nestvůra s 0 životy „na místě mrtvá“; samostatný
  odstavec o prahu smrti postavy ještě dohledat.
- ÚČ/OČ nestvůr: volný text („(+4 + 6/+1) = 10+/1 …“) — potřeba čísel pro automatický hod.
