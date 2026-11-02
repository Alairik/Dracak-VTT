# Vidění a světlo — podklady z knihy a návrh stavby (blok E plánu „náhrada Owlbear“)

Stav: 2026-11-02. **Hotové:** dveře (migrace `0106`), osvětlení mapy, zdroje světla, živé vidění po buňkách, skrývání tokenů mimo dohled a infravidění (migrace `0107`).
Rozhodnutí uživatele: (1) ve tmě vidí hráč jen to, co právě osvětlí družina (doporučená varianta), (2) dveře smí otevírat jen PJ, (3) otázka na noční vidění lupiče zůstává otevřená
(uživatel se ptal, co to je — viz níž). Boj ve tmě je hotový (postih −5/−4/−2, zranění bez postihu, přepínání PJ). **Zóny kouzel Tma a Temnota jsou hotové (2026-11-02, bez DDL: token se `svetlo_typ` `tma` / `temnota` a poloměrem ve `svetlo_sahy`, viz `docs/stav-projektu.md` a `includes/vtt_videni.php`).**
**Zbývá:** hoření světel v čase, noční vidění lupiče, Lovcův Příkrov stínů (h1003 b7357–b7359), zamčené a tajné dveře (na seznamu, zatím nejsou potřeba).

## 1. Co říká kniha (zdroj pravdy: `content/pravidla-*.html`)

| Co | Hodnota | Kde |
|---|---|---|
| Pochodeň | dosvítí **6 sáhů** všemi směry, hoří 3 směny (půl hodiny); nese se v ruce (žádný štít ani obouruční zbraň) | h1685 „Světlo (str. 87)“, b11183–b11185 |
| Lucerna | dosvítí **8 sáhů**; s kvalitní lucernou při poloviční spotřebě oleje **4 sáhy**, při čtvrtinové **2 sáhy**; má okenice (zatemnění, směrování); olej: 1 láhev = 24 směn (4 hodiny) | tamtéž |
| Světlo je vidět zdálky | světlo pochodně/lampy je ve tmě a v otevřeném terénu vidět **až na 1000 sáhů** (nevidící tě spatří dřív, než ty je) | tamtéž |
| Hůl čaroděje | svítí jako pochodeň (1 mag/směnu) nebo jako lucerna (2 magy/směnu) | h470 b4601 |
| Kouzlo **Světlo** (Luxmen) | dokonale osvětlí okolí, **jde s kouzelníkem**; 2 magy za sáh poloměru, koule, trvání 1–5 směn | h316 b3596–b3597 |
| Kouzlo **Tma** (Obscurum) | dokonalá tma, **vyřazuje trpasličí infravidění**; 4 magy za sáh poloměru; za 6 magů za sáh je uvnitř jen šero jako od malé pochodně a infravidění funguje | h318 b3601 |
| Druidova **Temnota** | okruh 300 sáhů; pochodeň není vidět déle než na 30 coulů; **infravidění v ní má jen desetinový dosah** | h193 b1935–b1939 |
| **Infravidění** trpaslíka | dosah **20 sáhů**; neproniká tím, čím neproniká světlo (tedy zdmi); nepoužitelné na slunci, v mlze a pod vodou; vidí **tvory s teplou krví** jako červené skvrny, ne tvar místnosti | h113 b790, h1685 b11186 |
| Lovec (kouzlo) | infravidění jako trpaslík na **50 sáhů** | h993 b7318 |
| **Noční vidění** lupiče („Zlepšení zraku“) | potřebuje jen nepatrně světla, **v absolutní tmě nepomáhá**; v boji ve tmě postih **−2** k útoku i obraně místo −5 a ví, na kterém hexu protivník stojí | h630 b5663 |
| Útok na neviditelného / boj ve tmě | bez zpráv o protivníkovi: ztrácí iniciativu, postih **−5** k útoku i obraně (zásah zraňuje bez postihu); s mlhavou představou, kde stojí: **−4** | h1613 b10738 |
| Tabulka světel (h286) | svíčka +1, 1–2 pochodně +2, lucerna +3, více pochodní +4, úplněk +5, pološero +6, soumrak/úsvit +7, jasné umělé světlo +8, slunce +9, záblesk +10 — **jen intenzita pro hod proti světelné spoušti**, vzdálenosti v ní nejsou | b3166 |

**Nenalezeno (nehádat):** dosah svíčky, dosah noční vidění lupiče při slabém světle, kolik sáhů znamená „šero“/„pološero“ a kolik úplněk.
Kniha je uvádí jen jako názvy. Do VTT proto půjde zadat **vlastní dosah v sázích** a předvolby budou jen ty, které kniha opravdu měří
(pochodeň 6, lucerna 8 / 4 / 2, hůl = pochodeň / lucerna, Světlo = magy ÷ 2).
Drobnost k proofreadingu: paladin „září jako Lucerna (7 sáhů)“ (h1299 b8371), ale lucerna je jinde 8 sáhů.

## 2. Co už v aplikaci je

- **Mlha války** (`includes/vtt_mlha.php`, tabulka `mlha_valky`): per hráč a mapa bitmapa buněk gridu, které hráč **někdy** viděl; odhalují se kolem jeho vlastní postavy
  do 8 sáhů (technický výchozí údaj, ne pravidlo) s kontrolou zdí; **nikdy se nezatahují zpět**. PJ umí odhalit/zatáhnout ručně.
- **Zdi** (`zdi`): `blokuje_pohyb`, `blokuje_vystrel` (= pohled), `viditelna_hracum`; kontrola průsečíkem úseček (`dracak_vtt_los_blokovana_zdi`).
- **Dveře** (2026-11-02): zeď s příznakem `dvere` a stavem `otevrene`; otevřené neblokují pohyb, výhled ani střelu; PJ je přepíná klikem.
- Chybí: světlo a tma vůbec, živé vidění (co vidí postava **teď**), infravidění, tokeny mimo dohled se hráči stále ukazují.

## 3. Návrh stavby (technická rozhodnutí jsou moje; rozhodnutí o chování hry viz Otázky)

**Fáze 2 — osvětlení mapy a zdroje světla (data + ovládání).**
- Mapa má nastavení **Osvětlení: Světlo / Tma** (PJ; výchozí Světlo = jako dosud, nic se nemění). Šero neřešíme, kniha pro něj nemá vzdálenosti.
- **Zdroj světla je vlastnost tokenu** (`tokeny.svetlo_sahy`, `tokeny.svetlo_typ`): pochodeň v ruce postavy se hýbe s ní, pochodeň na zdi je obecný token se světlem.
  Předvolby z knihy (pochodeň 6, lucerna 8/4/2, hůl, Světlo) + vlastní dosah. Zapnout/vypnout jedním klikem v panelu tokenu.
- DDL (ruční SQL, jako vždy): `mapy.osvetleni`, `tokeny.svetlo_sahy`, `tokeny.svetlo_typ`.

**Fáze 3 — živé vidění a stíny.**
- Server počítá po buňkách gridu (stejně jako dnešní mlha): buňka je **vidět teď**, když ji vidí paprsek z některého hráčova tokenu přes zdi/zavřené dveře **a** je osvětlená
  (v mapě „Světlo“ vždy; v mapě „Tma“ leží v dosahu zdroje světla, který na ni sám vidí). Jednou viděné buňky zůstávají jako ztlumená paměť (dnešní bitmapa).
- Klient kreslí: nikdy neviděno = černá, paměť = ztlumeně, vidět teď = čistě. Hladký „polygonový“ stín místo kostiček je možné vylepšení až po ověření v praxi.
- Tokeny mimo aktuální dohled se hráči neposílají (server filtruje, ne jen schová) — viz Otázka 1.

**Fáze 4 — infravidění a vidění ve tmě.**
- `tokeny.videni_infra_sahy` (trpaslík 20, lovec 50, jinak žádné; PJ upraví): v mapě „Tma“ uvidí postava s infraviděním **tvory s teplou krví** ve svém dosahu a výhledu
  (ne mapu, ne zdi). Nepůsobí v mapě „Světlo“ (slunce), uvnitř Temnoty jen 1/10 dosahu, uvnitř Tmy vůbec (kromě verze za 6 magů).
- Studenokrevní tvorové (kniha: haterie, mlok…) infraviděním vidět nejsou — příznak na tokenu, který nastaví PJ.
- Noční vidění lupiče: viz Otázka 3.

**Fáze 5 — boj ve tmě.**
- Když útočník (jeho strana) cíl nevidí, útok nabídne postih **−5** (a −4, má-li PJ za to, že tuší, kde stojí); lupič s nočním viděním −2. Využije existující okno „Obrana / útok“
  s opravami; PJ může kdykoli přepsat. Iniciativu neřeší automaticky (kniha říká „ztrácí iniciativu“ — nechává se na PJ, aby to nebylo překvapení).

## 4. Otázky pro uživatele a odpovědi (2026-11-02)

1. **ODPOVĚZENO: ano, podle doporučení.** **Má hráč ve tmě vidět jen to, co zrovna osvětlí jeho družina (a jak daleko vidí)?**
   Příklad: družina jde chodbou s pochodní, vidí 6 sáhů. Goblin stojí 10 sáhů před nimi ve tmě. *Doporučení:* hráči goblina **nevidí**, dokud nevstoupí do světla;
   to, kudy družina prošla, zůstane na mapě ztlumeně jako paměť, ale nestvůry v ní hráči nevidí. Trpaslík s infraviděním ho uvidí do 20 sáhů jako červenou skvrnu.
   Alternativa: dosavadní chování (co družina jednou odhalila, vidí navždy včetně nestvůr) a světlo jen rozhoduje, co se odhalí.
2. **ODPOVĚZENO: jen PJ.** **Kdo smí otevírat a zavírat dveře?** Dnes jen PJ (hráč řekne, PJ klikne). *Doporučení:* začít tak; později můžeme dovolit hráči dveře, u kterých stojí jeho postava.
3. **ČEKÁ (uživatel se zeptal, co noční vidění je: lupičova schopnost „Zlepšení zraku“, h630 b5663 — vidí při nepatrném světle, v úplné tmě ne, a není to infravidění).** **Jak daleko vidí lupič s nočním viděním při slabém světle?** Kniha říká jen „potřebuje nepatrně světla“ (číslo v ní není). *Doporučení:* vidí dvakrát dál než ostatní
   kolem každého zdroje světla, v úplné tmě nic — ale to je moje domněnka, rozhodnutí je tvoje. Podobně **dosah svíčky** kniha neuvádí (lze zadat ručně u každého světla).
