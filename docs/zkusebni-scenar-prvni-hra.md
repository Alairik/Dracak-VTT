# Zkušební scénář „první ostrá hra“

Cíl: projít na **dvou zařízeních** (PJ na počítači, hráč na tabletu nebo telefonu) všechno, co se bude dít v živé hře,
a zapsat, kde to drhne. Trvá zhruba hodinu. Rozhraní je česky; kdo co smí vidět, hlídá server (ověřeno automatickým
testem toku pozvání, viz konec).

## Příprava (jednorázově)

1. Admin (ty) je přihlášený. **Správa účtů → Nový účet**: jeden účet s rolí PJ, jeden s rolí hráč (heslo aspoň 12 znaků).
2. **Změny databáze** (menu administrace): pokud tam něco čeká, klikni na „Spustit čekající změny“. Má tam být „Všechno je aplikované“. Pokud se zastaví na příkazu, který potřebuje ruční SQL, pošli to Claudovi — dostaneš hotový text ke zkopírování do phpMyAdminu.
3. **Kontrola nasazení**: po posledním nasazení má být „Všech N souborů sedí“. Tlačítko „Zkontrolovat“ má ukázat zavřené cesty (✓).
4. **Chybový log** (menu administrace): před hrou ho vymaž tlačítkem „Vymazat log“, ať je po hře vidět jen to, co se stalo při ní.

## Scénář

| # | Kdo | Co udělat | Co se má stát |
|---|---|---|---|
| 1 | PJ | Přihlásit se, **Světy → Založit** (název, počáteční datum). | Svět se objeví ve výpisu, ty jsi jeho PJ. |
| 2 | PJ | Ve světě **Přidat hráče do světa**, vyber hráčský účet. | Hráč je ve světě. |
| 3 | Hráč | Přihlásit se na druhém zařízení. **Světy** → svět → **Nová postava** (rasa → povolání → atributy → životy → jméno). | Postava vznikne, hráč vidí jen svou. |
| 4 | Hráč | Otevřít **Deník**: v Inventáři přidat z katalogu zbraň, zbroj, lektvar; v Postavě vybrat zbroj a štít. | Čísla v záhlaví (OČ, ÚČ, naložení) se přepočítají. Klepnutím na název se ukáže detail předmětu. |
| 5 | PJ | Ve světě **Nová mapa**: nahrát obrázek, zapnout hexovou mřížku. Otevřít mapu. | Mapa se ukáže, jde zoomovat a posouvat. |
| 6 | PJ | Na mapu přidat token hráčovy postavy a jednu nestvůru (nebo dvě). | Tokeny jsou vidět oběma. Hráč vidí jen to, co mu mlha dovolí. |
| 7 | PJ | **Souboj → Zahájit**: úmysly, iniciativa (hráč si hodí sám, nestvůry hodí PJ), přepnout na tahy. | Pořadí tahů se ukáže všem; skryté hody nestvůr vidí jen PJ. |
| 8 | Hráč | Na tahu: kliknout na hex (krok/běh/sprint), pak útok na nestvůru. | Akce se odečtou, útok vytvoří výzvu k hodu; hod, obrana a zranění proběhnou podle pravidel. |
| 9 | Hráč | V okně deníku na mapě (z detailu tokenu) **Vytasit** jinou zbraň. | V boji stojí akci; zbraň musí být „U sebe“. Mimo boj zdarma. |
| 10 | PJ | Nasadit efekt (např. Spánek, nebo Dikobraz u postavy s vrhacími zbraněmi). | Štítek na tokenu, trvání se odpočítává na konci kola, vytasení vrhací zbraně bez akce platí. |
| 11 | PJ | Strhnout hráči životy z panelu tokenu. | Hráč to hned vidí v deníku (nemusí nic obnovovat). |
| 12 | PJ | **Konec souboje**, pak v deníku postavy: odpočinek/spánek a únava. | Únava klesne podle pravidel, deník to ukáže. |
| 13 | PJ + hráč | V liště mapy otevři **Panel tvorů a souboje** (ikona skupiny lidí). PJ: přetáhni okno jinam, přepni plné/kompaktní řádky, přetáhni box tvora na jiné místo v pořadí. Hráč otevře stejný panel. | PJ vidí nestvůry nahoře, pak družinu s počty životů, štítky a únavou. Hráč vidí jen družinu: avatar, jméno a pruh životů bez čísel. Najetí myší na box zvýrazní token na mapě a naopak. |
| 14 | PJ | V nastavení mapy zvol **Osvětlení mapy → Tma** (nebo Noc). Hráčově postavě dej v panelu tokenu **Vzhled → Světlo → pochodeň**. Nestvůru postav mimo dosah světla. | Hráč bez světla vidí jen sebe; s pochodní osvětlený kruh (6 sáhů). Nestvůra mimo světlo se mu vůbec neposílá. Dveře (zeď s příznakem dveře) PJ otevře klikem — zavřené blokují výhled i pohyb. |
| 15 | PJ | Nahoře v liště klikni **+1 směna** a **+1 hodina**; pochodni nastav v panelu tokenu dobu hoření (předvolba 3 směny). | Čas světa se posune všem, pochodeň po dohoření zhasne a hráč přestane vidět. |
| 16 | PJ | U pinu na mapě s cílovou mapou: **Vstoupit s družinou**. | Družina se přenese na cílovou mapu, aktivní mapa světa se přepne a hráči se přepnou s ní. |
| 17 | PJ | Na světové mapě nastav **Měřítko mapy** (míle na 1000 px), vezmi nástroj **Měřit**, změř trasu; **Shift** naváže další úsek. Klikni na **Spočítat cestu**. | Odkaz ukáže součet mil, kalkulačka cesty ho převezme. Zápis cesty zapíše postavám únavu a (po zaškrtnutí) posune čas světa. |
| 18 | PJ | V kalkulačce cesty zapni **povoz**, tempo spěch (klus), zvol Kočího. Klikni na **Hodit řízení za celou cestu**. | Ukáže šanci řízení, počet hodů a šanci na cestu bez nehody; hod vypíše nehodu (směna, hod, o kolik nad hranicí), nebo že cesta proběhla. |
| 19 | PJ nebo hráč | V panelu tokenu postavy otevři **Dosah pohybu…** a zvol povrch. | Kolik sáhů postava uběhne za kolo (poklus / běh / sprint) a ujde za směnu; tlačítko to ukáže jako kruh na mapě. |

## Po hře

Otevři v administraci **Chybový log**. Co tam je, byla chyba, kterou hráč mohl cítit jako „nic se nestalo po kliknutí“ — u každého záznamu je čas, stránka,
zařízení a jméno hráče. Prázdný log je dobrá zpráva. Záznamy typu „Chyba v prohlížeči“ jsou chyby JavaScriptu u hráčů, ostatní jsou chyby serveru.
Pošli Claudovi pár nejnovějších (rozklikni je a zkopíruj text).

## Co zapisovat

U každého kroku: **prošlo / drhne / nejde** a jedna věta, co přesně (co jsi klikl, co jsi čekal, co se stalo).
Zvlášť sleduj: pomalé překreslování, tlačítka na dotyku, kdy deník nebo mapa zamrzne, a cokoli, co hráč vidí a vidět nemá.

## Co už ověřil automatický test (2026-10-28)

Tok pozvání z bodů 1–3 projel skript: admin založí účty, PJ svět, přidá dva hráče, oba si přes průvodce založí postavu;
hráč 1 **nevidí** deník ani nemůže upravit postavu hráče 2 (403), nemůže jí přes API ubrat životy, nevidí administraci
ani svět, ve kterém není; PJ vidí obě postavy v přehledu družiny.
