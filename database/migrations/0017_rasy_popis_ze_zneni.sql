-- Přepsat rasy.popis na skutečný text z pravidel (29 z 32)
--
-- Stejná logika jako 0016 (povolani): popis byl strohé mechanické
-- shrnutí, ne text z knihy. Nahrazeno textem z content/pravidla-hrac.html
-- (bez homebrew verzovací poznámky "(Homebrew · zdroj: ...)").
--
-- Vynecháno: Elf (id 1, 4, 34) — v DB jsou 3 duplicitní řádky "Elf" se
-- vzájemně odlišnými napojenými daty (rasa_bonusy_vlastnosti,
-- rasa_schopnosti, rasa_jazyky), zatímco v knize existuje jen jeden
-- záznam Elf — čeká se na rozhodnutí, který řádek je ten pravý / jak
-- sloučit, než se přepíše popis.
--
-- Čisté UPDATE (DML) — mělo by se nasadit samo přes migrate.php.

UPDATE rasy SET popis = 'výška: 110–140 coulů, váha: 1100–2000 mn (55–100 kg), třída velikosti: A

Trpaslíci jsou jednou z nejznámějších člověku podobných ras. Jsou menší, podsadití, zocelení dlouhými věky strávenými v nehostinných pustinách.

Jejich větrem ošlehané tváře snad vždy zdobí hnědé až černé vousy, které však, stejné jako vlasy, poměrně záhy šedivějí.

Sídlí v horách, daleko od civilizace. Díky infravidění vidí i v omezené míře i ve tmě, a proto mohou pod zemí budovat rozsáhlé skalní komplexy šachet, jeskyní a sálů, kde těží drahé kameny, stříbro a především svoje milované zlato.

Trpaslíci většinou postrádají jakýkoliv smysl pro humor, jsou vždy vážní a někdy až příliš sebejistí. Dané slovo dodrží, zejména kyne-li jim z toho nějaká výhoda.

Jejich nejoblíbenější zbraní ve válce je sekera. V boji jsou nesmírně stateční a jen neradi ustupují z prohrané bitvy.

Brání-li trpaslík svoji hroudu zlata, neustoupí ani před rozvzteklaným, vyhladovělým ohnivým drakem.' WHERE id = 2 AND nazev = 'Trpaslík';
UPDATE rasy SET popis = 'výška: 90–130 coulů, váha: 1000–1500 mn (50–75 kg), třída velikosti: A

Kudůkové jsou zvláštní rasou, o které se ví téměř jistě, že vznikla kdysi v dávných dobách splynutím části plemene trpaslíků s hobity. Podobně jako hobiti také kudůkové jsou velmi obratní a poměrně chytří. Po trpaslících zase zdědili jejich nezdolnost a z části také lásku ke zlatu a drahým kamenům. Žijí v horách i v nížinách, ale nemilují podzemí, a proto si staví drobné kamenné domy, většinou daleko od velkých měst a obchodních cest.

Přestože jen málokterý z nich se stane dobrým bojovníkem, dokážou v boji velmi dobře zacházet jak se sekerou, tak i s kuší.

Mnoho a nich také nachází potěšení v různém bylinkářství a alchymii, ale stejně tak dobře se mohou uplatnit třeba jako zloději.' WHERE id = 3 AND nazev = 'Kudůk';
UPDATE rasy SET popis = 'výška: 165–210 coulů, váha: 1300–2300 mn (65–115 kg), třída velikosti: B

Lidé jsou snad nejrozšířenější rasou, žijící jak v oblastech hor, tak ve stepích, lesích a tundrách. Budují rozsáhlá sídla, ať už města či vesnice, která zpravidla leží na velkých obchodních křižovatkách. Lidé jsou velmi houževnatí, a i když nejsou tak silní jako krollové, ani tak inteligentní jako elfové, nacházejí uplatnění v celé škále povolání.

Většina z nich se zabývá především zemědělskou výrobou a řemeslnou výrobou, ale najdete mezi nimi i zdatné lovce a zkušené dobrodruhy všeho druhu.' WHERE id = 5 AND nazev = 'Člověk';
UPDATE rasy SET popis = 'výška: 175–220 coulů, váha: 1500–2800 mn (75–140 kg), třída velikosti: B

Barbaři jsou v podstatě lidé, ale po staletí trvající odloučení od skutečné lidské civilizace způsobilo, že barbar zpravidla není tak inteligentní jako člověk, i když v boji obvykle není tak divoký a krutý jako kroll. Oproti krollovi má typický barbar tu výhodu (ale nemusí to být pravidlem), že je chytřejší, obratnější a v neposlední řadě také pohlednější.' WHERE id = 6 AND nazev = 'Barbar';
UPDATE rasy SET popis = 'výška: 180–245 coulů, váha: 2000–4000 mn (100–200 kg), třída velikosti: C

Krollové sídlí v horách, tundrách a vůbec v prostředí velmi tvrdém a nehostinném. Dospělý kroll může měřit hodně přes dva metry a zjevem může vzdáleně (velmi vzdáleně) připomínat pračlověka, i když jeho kůže je spíše zrohovatělá než chlupatá. Bezpečně je však poznáte podle jejich nezaměnitelných uší, jimž vděčí za svůj neobvykle jemný sluch.

Krollové jsou silnější než obyčejní lidé, ale také poněkud nerozhodnější a zejména o poznání hloupější. Žijí v nevelkých kmenech, kteří mezi sebou neustále válčí. V těchto bojích se mezi sebou velmi zdokonalili, a proto nyní patří k nejobávanějším bojovníkům a jejich kyjů se vskutku příslovečně strach.

Pokud jde o jejich původ, je možné, že vznikli kdysi dávno spojením skřeta s poloobrem. To by také vysvětlovalo, proč se mezi nimi téměř nikdy nevyskytují jedinci preferující výhradně dobro.' WHERE id = 7 AND nazev = 'Kroll';
UPDATE rasy SET popis = 'semifer humanus

Můj osel byl v posledním tažení. Srdceryvně hýká a vyhazuje kopyty. Já měl štěstí, že jsem nepil ze zdejších vod. Jak jeho agónie zničehonic začala, tak i skončila. Teď jen mělce dýchá a kouká na mě svýma kulatýma očima. Slunce už se kloní k západu a já se nemám k tomu, abych mu ukončil trápení. nemůžu, byl pro mě jako člen rodiny. Sedím vedle svého přítele a poslouchám, jak se mu krátí dech. Už musím být sám napůl šílený žízní, protože proti rudé obloze vidím někoho přicházet. Ano, ano je to mámení smyslů, nikdo přece není tak vysoký. S touto myšlenkou jsem se začal propadat do sladké letargie. Další věc, která mě probrala, byl hrdelní zvuk, který zněl jako krull.

Zmatený jsme otevřel své oči a vidím, jak se nade mnou tyčí velká a mohutná postava připomínající člověka, ale tenhle musí mít přes 3 metry a je takřka nahý. Jeho kůže je osmahlá takřka do černa a jeho tělo je místy pokryté srstí. Má výrazné ušní boltce a tesáky mu trčí z poza pootevřených úst. Na zemi má položený malý seschlý stromek, který mu zřejmě slouží jako zbraň.

Leopold Spadaccini

Má cesta pouští života Strana 219 RV 419

Císařská knihovna, odd. 26 - cestopisy' WHERE id = 8 AND nazev = 'Kroll — domácí rozšíření';
UPDATE rasy SET popis = 'Doslova znamená ten který má víru. Klan prvních kněží, geomágů, čarodějů a mystiků' WHERE id = 9 AND nazev = 'Otho';
UPDATE rasy SET popis = 'Pamětníci. Starají se o knihy záští a uchovávají historii lidu, dohližejí na uchovávání tradic.' WHERE id = 10 AND nazev = 'Menwar';
UPDATE rasy SET popis = 'Kováři. Pánové ocele, mistři kladiva. Vždy patřili mezi nejváženější z klanů.' WHERE id = 11 AND nazev = 'Ingeit';
UPDATE rasy SET popis = 'Ničitelé. Váleční lordi. Mistři války a všech druhů boje.' WHERE id = 12 AND nazev = 'Hrestwog';
UPDATE rasy SET popis = 'Tvůrci. Tinkereři. První trpasličí mechanici. Lehce obskurní dokonce i mezi samotnými trpaslíky. Bývají největší zastánci inovací a pokroku v trpasličím společenství.' WHERE id = 13 AND nazev = 'Yrani';
UPDATE rasy SET popis = 'Kameníci, pánové kamenných síní. Trpasličí ekvivalent lidských celebrit.' WHERE id = 14 AND nazev = 'Knurlcan';
UPDATE rasy SET popis = 'Horníci. Hlavní hybná síla trpasličího bohatství.' WHERE id = 15 AND nazev = 'Skillfiz';
UPDATE rasy SET popis = 'Asi nejmladší klany. Zde najdete všechny trpaslíky, kteří mají co dočinění s dlouhodobým pobytem nad povrchem země.' WHERE id = 16 AND nazev = 'Grimstera';
UPDATE rasy SET popis = 'Klan, který vlastně není klanem. Klan trollobíjců. Trpaslíků, kteří žijí jen pro svou slavnou smrt, aby odčinili své hříchy či splatili dluhy.' WHERE id = 17 AND nazev = 'Carach';
UPDATE rasy SET popis = 'Vzdušné síly trpasličích jednotek. Létali na speciálně cvičených gryfech. Jejich Gromrilová zbroj a vrhací kladiva z Vraccasia je navždy zapsali do síní legend.' WHERE id = 18 AND nazev = 'Urur-Vraca';
UPDATE rasy SET popis = 'Lovci draků, kteří je lovili pro jejich bohatství, ale i pro ně samotné.' WHERE id = 19 AND nazev = 'Jurgencar';
UPDATE rasy SET popis = 'Tento rod defakto stále existuje, ale je to spíše označení trpaslíků, kteří byli z nějakého důvodu vyobcováni mimo trpasličí společenství. Doslova to znamená ti, kteří nejsou z kamene.' WHERE id = 20 AND nazev = 'Menkurl';
UPDATE rasy SET popis = 'Malý klan, který řešil rovnováhu mezi klany. Něco jako soudci, kteří se zodpovídají jen králi.

Častokrát měli na starosti krevní msty mezi rody.' WHERE id = 21 AND nazev = 'Edaris';
UPDATE rasy SET popis = 'homo algiditis

Bylo to uprostřed zimy. Za ty nejhustší vánice jakou si dovedete představit a zničehonic někdo zabouchal na hlavní bránu. S chlapama co jsme byly na strážnici jsme si mysleli, že si Vladimír odskočil do lesa pro něco k snědku, ale ten nikdy neklepe, ale přeleze si zeď kde potřebuje. Mysleli jsme, že to třeba vítr zahejbal s vratama, ale pak se ve vychřici začali ozývat trpasličí nadávky. Opatrně jsme odkryly zadělanou střílnu ve strážnici. Máme ji zadeklovanou, aby nám tak tolik netáhlo. A venku nebylo vidět zhola nic. Jen bílá tma a navíc bylo chvíli do západu slunce. A z ničeho nic se dole pod námi se ozval Snorriho hlas: ‘Přísahám u Grugniho kladiva, jestli nás okamžitě nepustíte dovnitř, tak vás ty vrátka rozkopu a vyhodím s holou prdelí na mráz!’.

záznam z deníku

27.Siny. 994, Ymari

Hver Polokolský' WHERE id = 24 AND nazev = 'Barnové';
UPDATE rasy SET popis = 'Cael

Avid humanus

Sami sebe ve svém jazyce označují za nebeské. Obývají vysoká horská sídla a útesy.

Andromeda

Zápisky z Nového světa

Kapitola druhá - Xing' WHERE id = 25 AND nazev = 'Caelové';
UPDATE rasy SET popis = 'Fiora Collosus

Velké a prastaré hvozdy již oplývají vlastní inteligencí a občas se stane, že i tak mocný ekosystém je ohrožen, nebo přímo poražen. Má li tedy prastarý hvozd příležitost, stvoří ze svého srdce bytost, které se říká Furan, mudrci pro ni mají označení Fiora Collosus.

Byť jsou většinou Fůrané velice inteligentní, nedá se mluvit o rase, spíše jako o jedincích, kteří byly vysláni aby přivedli pomoc - druida či kohokoliv, o kom si hvozd myslí, že bude ku pomoci, nebo aby přenesl vzpomínky a moudrost umírajícího hvozdu a přenesl je jinam.

Častokrát slyšíte Fůrana říkat pouze tři slova. Bývá to Já, jsem a jeho jméno. Není to protože by snad postrádal inteligenci, ale jeho jazyk je zcela jiný, než ostatních ras a jeho zdřevnatělé hlasivky neumožňují jiná slova. Měnit může jedině intonaci. Naučit se Fůranštinu je velmi těžké, nikoliv však nemožné.

Fůran je na pomezí mezi klasickou živou bytostí a rostlinou. Ke svému životu potřebuje půdu a vodu. má dobrou náladu při slunečných dech a naopak skleslou, když je zataženo. Koloběh přírody se přímo projevuje na jeho zevnějšku.

Jeho vzrůst je značně variabilní. Začíná jako malý semenáček (30-40cm) a dorůstá velikosti i přes 3 metry.' WHERE id = 26 AND nazev = 'Furan';
UPDATE rasy SET popis = 'Gnollové

Canis humanus

Velmi přitpůsobivá rasa obývající všechny myslitelé oblasti a podnebí.

Andromeda

Zápisky z Nového světa

Kapitola devátá - Step a pampa' WHERE id = 27 AND nazev = 'Gnollové';
UPDATE rasy SET popis = 'Kobold

Lacertus humanus

Jakmile uslyšíte v temnotě šustění jejich šupin, utíkejte.

Andromeda

Zápisky z Nového světa, druhý díl

Kapitola první - Hory západu' WHERE id = 28 AND nazev = 'Kobold';
UPDATE rasy SET popis = 'lagos humanus

Tento drobounký národ jen snad zázrakem přežil na území nyní známém jako Brottheim. Píši zázrakem, protože je zde tolik faktorů, které mohly způsobit jejich vymření, že je skoro až neuvěřitelné, že na ostrovech kde žili se nevyskytoval větší predátor nežli vlk.

Jejich adaptace na naší kulturu proběhla až nezvykle lehce. Všechny nejasnosti se vyřešili za pomocí diplomacie a nebylo jediného magiliwa, který by jakkoliv agresivně vystupoval proti našim lidem.

Doporučuji další zkoumání tohoto druhu, neboť jeho potenciál je nesmírný. Mají zvláštní smysl pro pěstování všeho co roste a z mě zatím neznámého důvodu jejich dobytek umírá sešlostí věkem. Dalším doporučením by pak byla případná karanténní opatření. Jsou posedlí sexem a jsou schopni se velice rychle množit, dostanou li k tomu prostor a mají li zdroje.

Jejich mysl je bystrá a velice rychle chápou nové věci, jsou neustále v pohybu, pokud zrovna nespí. Mé povinnosti mi zatím neumožnili dostatečně pečlivé zkoumání tohoto druhu, ale jsem přesvědčený, že o nich neslyšíme naposledy.

Arachnofóbix

Deník 990-99x, Strana 86

Soukromá knihovna Bílé Věže' WHERE id = 29 AND nazev = 'Magiliw';
UPDATE rasy SET popis = 'felix sapiens

Mno vážně strážníku. Tam nahoře! Na zvonici! Vypadal jako kočka, ale byl velikej a celej chlupatej. Mno slyšel jsem vrzat zavěšení zvonu a asi tam dávil nějakou nebohou ženštinu. Jak že to vím? Mno slyšel jsem ji vzlykat. A proč mám na vás dejchat?

Záznam městské hlídky Grestu

Výpověď zvoníka Lamberta

Archív hlídky, odd. c16/989/7' WHERE id = 30 AND nazev = 'Naro\'shai';
UPDATE rasy SET popis = 'Skaven

Rattus humanus

Dlouhé agilní tělo pokryté lesklou srstí, snáší se na svém vlastním lysém ocasu od stropu a prodává mi pivo.

Andromeda

Zápisky z Nového světa, druhý díl

Kapitola třetí - Velká hanza' WHERE id = 31 AND nazev = 'Skaven';
UPDATE rasy SET popis = 'bucerus sapiens

Bylo to jako dunění. Pak se začali třást i věci v poličkách. Já, mno nikdy jsem nemyslel, že se něco tak velkýho může přihnat tak rychle. Než jsem zjistil co se děje, branka už visela na jednom pantu a poslední věc co si pamatuju, že mě praštil strom. Byl to listnáč. Dub.

Bolivar Schnekt - jediný přeživší

Výňatek ze spisu Valitera de Prono

Knihovna Grest, odd. 47

- se zvláštním přístupem' WHERE id = 32 AND nazev = 'Tauren';
UPDATE rasy SET popis = 'homo infernalis

Nejdříve vás odpuzují, pak vás zaujmou, poté vás přesvědčí a nakonec vás zotročí. Přesto máte pocit že to jsou fajn lidi.

Gunther Klanovid - starosta Mokrosvist

Zápis z jednání městské rady, strana 2

rz. 991, zápis: Elen Klanovidová' WHERE id = 33 AND nazev = 'Tiefling';
