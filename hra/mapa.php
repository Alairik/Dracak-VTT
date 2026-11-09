<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_mapa_body.php';

$user = dracak_require_login();
$mapaId = (int)($_GET['id'] ?? 0);

$stmt = dracak_db()->prepare('SELECT * FROM mapy WHERE id = ?');
$stmt->execute([$mapaId]);
$mapa = $stmt->fetch();
if (!$mapa) {
    http_response_code(404);
    die('Mapa nenalezena.');
}
$svet = dracak_vtt_require_svet($user, (int)$mapa['svet_id']);
$svetId = (int)$svet['id'];
$isPjOrAdmin = dracak_vtt_je_pj_sveta($user, $svetId);

require_once __DIR__ . '/../includes/vtt_mapa_stav.php';
require_once __DIR__ . '/../includes/vtt_cas.php';
require_once __DIR__ . '/../includes/vtt_videni.php';
// Tokeny, efekty, pořadí tahů a postavy k položení — jediný zdroj pravdy
// sdílený s hra/api/mapa_stav.php (obnova "na místě" bez přenačtení stránky).
$stav = dracak_vtt_mapa_stav(dracak_db(), $user, $mapa, $isPjOrAdmin);

// Zdi (LoS/pohyb) — skrytá (viditelna_hracum=0) smí vidět jen PJ/admin,
// stejný filtr jako u tokenů výš. udalosti.php stejnou podmínku vynucuje
// i pro realtime polling, ať skrytá zeď neprosákne hráčům ani tudy.
$zdiDvere = dracak_vtt_zdi_ma_dvere(dracak_db());
$stmt = dracak_db()->prepare(
    'SELECT id, x1, y1, x2, y2, sirka_px, blokuje_pohyb, blokuje_vystrel, viditelna_hracum' . ($zdiDvere ? ', dvere, otevrene' : '') . ' FROM zdi WHERE mapa_id = ?'
);
$stmt->execute([$mapaId]);
$zdi = $stmt->fetchAll();
if (!$isPjOrAdmin) {
    $zdi = array_values(array_filter($zdi, fn($z) => (bool)$z['viditelna_hracum']));
}
foreach ($zdi as &$z) {
    $z['x1'] = (float)$z['x1'];
    $z['y1'] = (float)$z['y1'];
    $z['x2'] = (float)$z['x2'];
    $z['y2'] = (float)$z['y2'];
    $z['sirka_px'] = (float)$z['sirka_px'];
    $z['blokuje_pohyb'] = (bool)$z['blokuje_pohyb'];
    $z['blokuje_vystrel'] = (bool)$z['blokuje_vystrel'];
    $z['viditelna_hracum'] = (bool)$z['viditelna_hracum'];
    $z['dvere'] = $zdiDvere && !empty($z['dvere']);
    $z['otevrene'] = $zdiDvere && !empty($z['otevrene']);
}
unset($z);

// Piny — dřív jen na světové mapě, teď na libovolné (viz migrace
// 0062_piny_hierarchie_a_typy.sql): zóna může mít piny na budovy,
// budova na místnosti atd., libovolně hluboko. Pin na mapě samotné
// (ne jen na světové) umožňuje tenhle vnořený rozpad.
$mapaBody = dracak_vtt_mapa_body_pro_mapu(dracak_db(), $mapaId, $isPjOrAdmin);
foreach ($mapaBody as &$b) {
    $b['npc'] = $b['cilova_mapa_id'] ? dracak_vtt_mapa_bod_npc(dracak_db(), (int)$b['cilova_mapa_id']) : [];
}
unset($b);
// Nabídka map pro "cílová mapa" při zakládání pinu — jen zóny (ne
// světová mapa samotná, ta je vždycky kořen) ze STEJNÉHO světa a
// nikdy mapa, na které pin zrovna zakládáme (přímý self-loop by
// breadcrumbu/stromu v dalších fázích rozbil procházení).
$zonoveMapyVeSvete = $isPjOrAdmin ? dracak_db()->prepare(
    "SELECT id, nazev FROM mapy WHERE svet_id = ? AND typ_mapy = 'zona' AND id != ? ORDER BY nazev"
) : null;
if ($zonoveMapyVeSvete) {
    $zonoveMapyVeSvete->execute([$svetId, $mapaId]);
    $zonoveMapyVeSvete = $zonoveMapyVeSvete->fetchAll();
} else {
    $zonoveMapyVeSvete = [];
}

// Breadcrumb (odkud se sem přišlo) a celý strom míst ve světě pro
// postranní panel — viz includes/vtt_mapa_body.php.
$mapaCesta = dracak_vtt_mapa_cesta(dracak_db(), $mapaId);
$mapaStrom = dracak_vtt_mapa_strom(dracak_db(), $svetId);

// Cíle pro "Předat kořist" — všechny postavy ve světě (ne jen ty s tokenem
// na téhle mapě). Jen PJ/admin je smí použít; loot.php to stejně vynucuje
// server-side, tohle je jen pro UI.
$svetPostavyKorist = [];
if ($isPjOrAdmin) {
    $stmt = dracak_db()->prepare('SELECT id, nazev FROM postavy WHERE svet_id = ? ORDER BY nazev');
    $stmt->execute([$svetId]);
    $svetPostavyKorist = $stmt->fetchAll();
}

// Deník postavy v panelu na mapě: PJ/admin otevře deník kterékoli postavy světa, hráč jen svých (hra/denik.php to hlídá znovu).
$denikPostavy = [];
if ($svetId > 0) {
    $stmt = dracak_db()->prepare('SELECT id, nazev FROM postavy WHERE svet_id = ?' . ($isPjOrAdmin ? '' : ' AND vlastnik_ucet_id = ?') . ' ORDER BY nazev');
    $stmt->execute($isPjOrAdmin ? [$svetId] : [$svetId, (int)$user['id']]);
    $denikPostavy = $stmt->fetchAll();
}

$nestvuryKatalog = [];
$efektyKatalog = [];
$rychleEfekty = [];
$hraciVeSvete = [];
if ($isPjOrAdmin) {
    $nestvuryKatalog = dracak_db()->query('SELECT id, nazev FROM nestvury ORDER BY nazev')->fetchAll();
    // Poškození a Léčení nejsou trvalé efekty (jednorázová změna životů přes kouzlo / lektvar) — v nabídce nejsou.
    $efektyMech = dracak_vtt_tokeny_funkce()['efekty_mech'];
    $efektyKatalog = array_values(array_filter(
        dracak_db()->query('SELECT id, nazev, typ, cil' . ($efektyMech ? ', mechanika_stav, trvani_kol' : '') . ' FROM efekty ORDER BY nazev')->fetchAll(),
        fn($e) => dracak_vtt_hp_znamenko($e) === null
    ));
    $rychleEfekty = dracak_db()->query('SELECT id, nazev FROM efekty WHERE rychla_volba = 1 ORDER BY nazev')->fetchAll();
    // Pro popover "Mlha" — výběr, čí mlhu PJ nahlíží/upravuje (viz
    // hra/api/mlha_nahled.php, hra/api/mlha_uprava.php).
    $stmt = dracak_db()->prepare('SELECT u.id, u.jmeno FROM svet_hraci sh JOIN ucty u ON u.id = sh.ucet_id WHERE sh.svet_id = ? ORDER BY u.jmeno');
    $stmt->execute([$svetId]);
    $hraciVeSvete = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/vtt_iniciativa.php';
require_once __DIR__ . '/../includes/vtt_pohyblivost.php';
require_once __DIR__ . '/../includes/vtt_souboj_pravidla.php';
$pohybNastaveni = dracak_vtt_svet_cestovani_nastaveni(dracak_db(), $svetId);
$iniciativaBonusy = dracak_db()->query('SELECT id, popis, bonus FROM iniciativa_bonusy ORDER BY id')->fetchAll();
// Souboj s fázemi (ruční SQL 0085): hody iniciativy jdou z panelu souboje, ne
// z panelu tokenu. Překvapení a kouzlení se přičtou samy (podle zahájení
// souboje a úmyslu), řádky pro základní systém tu nemají smysl.
$souboj = dracak_vtt_tokeny_funkce()['souboj'];
$iniV2 = dracak_vtt_tokeny_funkce()['iniciativa_v2'];
$zbraneIni = dracak_vtt_tokeny_funkce()['zbrane_ini'];
$hodBonusy = array_values(array_filter($iniciativaBonusy, fn($b) => !in_array($b['popis'], [
    'Překvapení', 'Kouzlení', 'Automatická iniciativa v základním systému', 'Automatická ztráta iniciativy v základním systému',
], true) && !($iniV2 && in_array($b['popis'], DRACAK_VTT_INI_UTOKY, true))));   // v2: vícenásobné útoky jen polem „útoků za 2 kola“

?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title>Dračák VTT — <?= htmlspecialchars($mapa['nazev']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/organic.css?v=<?= filemtime(__DIR__ . '/../assets/css/organic.css') ?>">
<link rel="stylesheet" href="../assets/css/mapa.css?v=<?= filemtime(__DIR__ . '/../assets/css/mapa.css') ?>">
<script src="../assets/js/chyby.js?v=<?= filemtime(__DIR__ . '/../assets/js/chyby.js') ?>"></script>
</head>
<body>

<div class="topbar">
  <a href="svet.php?id=<?= $svetId ?>">← <?= htmlspecialchars($svet['nazev']) ?></a>
  <div class="breadcrumb">
    <?php foreach ($mapaCesta as $i => $krok): ?>
      <?php if ($i > 0): ?><span class="sep">›</span><?php endif; ?>
      <?php if ((int)$krok['id'] === $mapaId): ?>
        <span><?= htmlspecialchars($krok['nazev']) ?></span>
      <?php else: ?>
        <a href="mapa.php?id=<?= (int)$krok['id'] ?>"><?= htmlspecialchars($krok['nazev']) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <span class="typ">(<?= $mapa['typ_mapy'] === 'svet' ? 'světová mapa' : 'zóna' ?>)</span>
  <?php $casSveta = dracak_vtt_cas_stav(dracak_db(), $svetId); ?>
  <?php if ($casSveta !== null): ?>
  <span class="svet-cas" id="svetCas" title="Čas ve světě — 1 směna je zhruba 10 minut. Posouvá ho PJ."><span id="svetCasText"><?= htmlspecialchars($casSveta['datum'] . ' · ' . $casSveta['text']) ?></span>
    <?php if ($isPjOrAdmin): ?>
    <button type="button" class="svet-cas-btn" data-cas-posun="1" title="Posunout čas o 1 směnu (10 minut)">+1 směna</button>
    <button type="button" class="svet-cas-btn" data-cas-posun="6" title="Posunout čas o hodinu (6 směn)">+1 hodina</button>
    <?php endif; ?>
  </span>
  <?php endif; ?>
  <a class="topbar-pravidla" href="../pravidla.php" target="_blank" rel="noopener" title="Pravidla — otevře se v nové záložce, mapa zůstane, jak je">Pravidla ↗</a>
</div>

<?php if (!$mapa['obrazek_cesta']): ?>
  <div id="emptyState">Tahle mapa ještě nemá nahraný obrázek — přidej ho na stránce světa.</div>
<?php else: ?>

  <div id="viewport">
    <div id="mapWrap">
      <img id="mapImg" src="mapa_obrazek.php?id=<?= $mapaId ?>" draggable="false" style="display:block;max-width:none;-webkit-user-drag:none;user-select:none;">
      <svg id="gridSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:0;"></svg>
      <svg id="svetlaSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:4;"></svg>
      <svg id="zdiSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:5;"></svg>
      <svg id="kresbySvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:8;"></svg>
      <svg id="soubojSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:95;"></svg>
      <svg id="rulerSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;overflow:visible;z-index:140;"></svg>
      <canvas id="mlhaCanvas" style="position:absolute;top:0;left:0;pointer-events:none;z-index:100;"></canvas>
      <div id="pinIconTemplates" style="display:none;">
        <span data-typ="mesto"><?php dracak_icon('castle', 28); ?></span>
        <span data-typ="vesnice"><?php dracak_icon('home', 28); ?></span>
        <span data-typ="tajne_misto"><?php dracak_icon('eye-off', 28); ?></span>
        <span data-typ="budova"><?php dracak_icon('building-2', 28); ?></span>
        <span data-typ="mistnost"><?php dracak_icon('door-open', 28); ?></span>
        <span data-typ="sklep"><?php dracak_icon('warehouse', 28); ?></span>
        <span data-typ="prechod"><?php dracak_icon('signpost', 28); ?></span>
        <span data-typ="jine"><?php dracak_icon('map-pin', 28); ?></span>
      </div>
      <div id="rotUchyt" title="Táhni a otoč token" style="display:none;"></div>
      <div id="mapaBodyLayer" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:50;">
        <?php foreach ($mapaBody as $b): ?>
          <button type="button" class="mapa-bod-marker" data-bod-id="<?= (int)$b['id'] ?>"
            style="position:absolute;left:<?= (int)$b['x'] ?>px;top:<?= (int)$b['y'] ?>px;pointer-events:auto;background:none;border:none;cursor:pointer;padding:0;<?= $b['viditelny_hracum'] ? '' : 'opacity:.6;' ?>"
            title="<?= htmlspecialchars($b['nazev']) ?>">
            <?php dracak_icon(dracak_vtt_mapa_bod_ikona($b['typ']), 28); ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="toolbar">
    <button class="tbtn" type="button" id="tb-pridat" title="Přidat token"><?php dracak_icon('user-round-plus'); ?></button>
    <button class="tbtn armed" type="button" id="tb-move" title="Vybrat / přesunout (V)"><?php dracak_icon('move'); ?></button>
    <button class="tbtn" type="button" id="tb-vyber" title="Výběr rámečkem — táhni přes tokeny, klepnutím přidáš další (jde i Shiftem)"><?php dracak_icon('mouse-pointer-2'); ?></button>
    <button class="tbtn" type="button" id="tb-ruka" title="Posouvat mapu (ruka) — jde i mezerníkem, pravým tlačítkem nebo dvěma prsty"><?php dracak_icon('hand'); ?></button>
    <button class="tbtn" type="button" id="tb-ruler" title="Měřit vzdálenost (na mapě světa s měřítkem navazuje Shift další úsek trasy)"><?php dracak_icon('ruler'); ?></button>
    <button class="tbtn" type="button" id="tb-kresleni" title="Kreslit na mapu (čára, šipka, obrazec, text)" style="display:none;"><?php dracak_icon('pen-line'); ?></button>
    <button class="tbtn" type="button" id="tb-ping" title="Ukázat na mapu ostatním"><?php dracak_icon('crosshair'); ?></button>
    <?php if ($mapaStrom): ?>
      <button class="tbtn" type="button" id="tb-strom" title="Mapa míst"><?php dracak_icon('list-tree'); ?></button>
    <?php endif; ?>
    <?php if ($isPjOrAdmin): ?>
      <button class="tbtn" type="button" id="tb-zed" title="Kreslit zeď"><?php dracak_icon('brick-wall'); ?></button>
    <?php endif; ?>
    <?php if ($isPjOrAdmin): ?>
      <button class="tbtn" type="button" id="tb-pin" title="Přidat pin (místo)"><?php dracak_icon('map-pin'); ?></button>
    <?php endif; ?>
    <?php if ($isPjOrAdmin && dracak_vtt_tokeny_funkce()['npc_zasoba']): ?>
      <button class="tbtn" type="button" id="tb-tokeny" title="Správa tokenů — výroba NPC do zásoby a pokládání tažením (lišta dole uprostřed)"><?php dracak_icon('layers'); ?></button>
    <?php endif; ?>
    <button class="tbtn" type="button" id="tb-raid" title="<?= $isPjOrAdmin ? 'Panel tvorů a souboje — nestvůry, družina, pořadí tahů (plovoucí okno)' : 'Družina a pořadí tahů (plovoucí okno)' ?>"><?php dracak_icon('users'); ?></button>
    <button class="tbtn" type="button" id="tb-kostky" title="Hodit kostkou"><?php dracak_icon('dices'); ?></button>
    <button class="tbtn" type="button" id="tb-log" title="Log"><?php dracak_icon('scroll-text'); ?></button>
    <?php if ($isPjOrAdmin): ?>
      <button class="tbtn" type="button" id="tb-grid" title="Nastavení gridu"><?php dracak_icon('grid-2x2'); ?></button>
      <button class="tbtn" type="button" id="tb-mlha" title="Mlha války"><?php dracak_icon('cloud-fog'); ?></button>
      <?php if ($souboj): ?>
        <button class="tbtn" type="button" id="tb-souboj" title="Zahájit souboj"><?php dracak_icon('swords'); ?></button>
      <?php endif; ?>
      <button class="tbtn" type="button" id="tb-kolo" title="Konec kola (odpočítat trvání efektů)"><?php dracak_icon('rotate-cw'); ?></button>
      <?php if (!$souboj): ?>
        <button class="tbtn" type="button" id="tb-dalsi-tah" title="Další na tahu (iniciativa)"><?php dracak_icon('skip-forward'); ?></button>
      <?php endif; ?>
      <button class="tbtn" type="button" id="tb-krok-zpet" title="Krok zpět (vrátit poslední akci)"><?php dracak_icon('undo-2'); ?></button>
    <?php endif; ?>
  </div>

  <?php if ($isPjOrAdmin): ?>
  <a id="meridloCesta" class="meridlo-cesta" href="#" style="display:none;" title="Otevře kalkulačku cesty s touto vzdáleností"></a>
  <?php endif; ?>

  <?php if ($isPjOrAdmin && dracak_vtt_tokeny_funkce()['npc_zasoba']): ?>
  <!-- Správa tokenů (jen PJ; assets/js/token_bar.js): lišta dole uprostřed — výroba NPC do zásoby, zásoba
       nepoložených, NPC téhle lokace a naposledy použité druhy. Pokládá se tažením na mapu (prst i myš). -->
  <div id="tokenBar" class="token-bar" style="display:none;" role="region" aria-label="Správa tokenů">
    <div class="tb-zalozky">
      <button class="tb-zal aktivni" type="button" data-tb-zal="novy">Nový token</button>
      <button class="tb-zal" type="button" data-tb-zal="zasoba">Nevyužité <span class="tb-pocet" id="tbPocetZasoba">0</span></button>
      <button class="tb-zal" type="button" data-tb-zal="lokace">Tato lokace <span class="tb-pocet" id="tbPocetLokace">0</span></button>
      <button class="tb-zal" type="button" data-tb-zal="naposledy">Naposledy</button>
      <button class="tb-zavrit" type="button" id="tbZavrit" title="Zavřít lištu"><?php dracak_icon('x', 14); ?></button>
    </div>
    <div class="tb-telo">
      <div class="tb-panel" data-tb-panel="novy">
        <div class="tb-radek">
          <input class="input tb-hledat" type="text" id="tbHledat" placeholder="hledat druh…" autocomplete="off">
          <select class="input tb-druh" id="tbDruh" size="1"></select>
          <label class="tb-pole" title="Kolik kusů vyrobit do zásoby">počet
            <input class="input tb-pocet-input" type="number" id="tbPocet" min="1" max="50" value="1">
          </label>
          <label class="tb-pole" title="Kam NPC patří — ve správě se ukáže u tohohle místa">místo
            <select class="input" id="tbLokace">
              <option value="tady">tady (<?= htmlspecialchars(mb_strimwidth((string)$mapa['nazev'], 0, 18, '…')) ?>)</option>
              <option value="">bez místa (do zásoby)</option>
            </select>
          </label>
          <label class="tb-chk" title="Každý kus dostane vlastní čísla pro boj (hostinský se jménem). Bez zaškrtnutí berou všichni čísla svého druhu.">
            <input type="checkbox" id="tbJedinecny"> každý jedinečný
          </label>
          <button class="btn btn-primary tb-vyrobit" type="button" id="tbVyrobit">Vyrobit do zásoby</button>
        </div>
        <p class="tb-note" id="tbNovyNote">Vyrob si zásobu dopředu (třeba 10× Goblin) a pak je jen přetáhni z „Nevyužité“ na mapu.</p>
      </div>
      <div class="tb-panel" data-tb-panel="zasoba" hidden>
        <div class="tb-radek tb-filtr">
          <span class="tb-filtr-popis">Ukázat:</span>
          <button class="tb-f aktivni" type="button" data-tb-filtr="vse">vše</button>
          <button class="tb-f" type="button" data-tb-filtr="tady">z téhle lokace</button>
          <button class="tb-f" type="button" data-tb-filtr="bez">bez místa</button>
        </div>
        <div class="tb-chipy" id="tbZasoba"></div>
      </div>
      <div class="tb-panel" data-tb-panel="lokace" hidden>
        <div class="tb-chipy" id="tbLokaceSeznam"></div>
      </div>
      <div class="tb-panel" data-tb-panel="naposledy" hidden>
        <div class="tb-chipy" id="tbNaposledy"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div id="zoomCtl">
    <button class="tbtn" type="button" id="zoom-minus" title="Oddálit (−)"><?php dracak_icon('zoom-out', 18); ?></button>
    <span id="zoomInfo">100 %</span>
    <button class="tbtn" type="button" id="zoom-plus" title="Přiblížit (+)"><?php dracak_icon('zoom-in', 18); ?></button>
    <button class="tbtn" type="button" id="zoom-fit" title="Celá mapa (0)"><?php dracak_icon('maximize', 18); ?></button>
  </div>


  <div class="popover" id="popover-pridat">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Přidat token</h3>
    <div id="novaPostavaWrap">
      <label for="novaPostavaSelect">Postava</label>
      <select class="input" id="novaPostavaSelect">
        <option value="">—</option>
      </select>
    </div>
    <p class="note" id="novaPostavaPrazdno" style="display:none;">Všechny postavy už na mapě jsou.</p>
    <?php if ($isPjOrAdmin && $nestvuryKatalog): ?>
      <label for="nestvuraHledat">Nestvůra (bestiář)</label>
      <input class="input" type="text" id="nestvuraHledat" placeholder="hledat…">
      <select class="input" id="novaNestvuraSelect" size="6">
        <?php foreach ($nestvuryKatalog as $n): ?><option value="<?= (int)$n['id'] ?>"><?= htmlspecialchars($n['nazev']) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <?php if ($isPjOrAdmin): ?>
      <div id="obecnySekce" style="display:none;">
        <label for="obecnyNazev">Vlastní token (NPC bez statistik, překážka, předmět)</label>
        <input class="input" type="text" id="obecnyNazev" maxlength="100" placeholder="Název — např. Sud, Kapitán stráže">
        <div class="vz-radek" style="margin-top:6px;">
          <select class="input" id="obecnaBarva" style="flex:1;"></select>
          <select class="input" id="obecnaVelikost" style="flex:1;">
            <option value="0.5">½ sáhu</option>
            <option value="1" selected>1 sáh</option>
            <option value="2">2 sáhy</option>
            <option value="3">3 sáhy</option>
            <option value="4">4 sáhy</option>
          </select>
        </div>
      </div>
    <?php endif; ?>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="polozitVic"> Pokládat víc kusů za sebou
    </label>
    <p class="note">Vyber a klepni na mapu, kam to položit.</p>
  </div>

  <div class="popover" id="popover-kresleni">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Kreslení</h3>
    <label>Tvar</label>
    <div class="vz-seg" id="kresbaTvary"></div>
    <label>Barva</label>
    <div class="vz-barvy" id="kresbaBarvy"></div>
    <label>Tloušťka</label>
    <div class="vz-seg" id="kresbaSirky"></div>
    <?php if ($isPjOrAdmin): ?>
      <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
        <input type="checkbox" id="kresbaSkryta"> Jen pro PJ (hráči ji neuvidí)
      </label>
    <?php endif; ?>
    <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;">
      <button class="btn btn-ghost" type="button" id="kresbaSmazatMoje" style="flex:1;font-size:12px;">Smazat moje kresby</button>
      <?php if ($isPjOrAdmin): ?><button class="btn btn-ghost" type="button" id="kresbaSmazatVse" style="flex:1;font-size:12px;">Smazat všechny</button><?php endif; ?>
    </div>
    <p class="note">Táhni po mapě. Kruh a kužel ukážou dosah v sázích (na hexech v hexech). Jednotlivou kresbu smažeš pravým tlačítkem (na dotyku dlouhým stisknutím).</p>
  </div>

  <div class="popover" id="popover-kostky">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Hodit kostkou</h3>
    <input class="input" type="text" id="kostkyNotace" placeholder="2k6+2">
    <button class="btn btn-primary" id="hoditBtn" type="button" style="width:100%;margin-top:8px;">Hodit</button>
  </div>

  <?php if ($isPjOrAdmin): ?>
  <div class="popover" id="popover-zed">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Zeď</h3>
    <label for="zedSirka">Šířka (<span id="zedSirkaJednotka">px</span>)</label>
    <input class="input" type="number" id="zedSirka" min="1" max="60" step="1" value="6">
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedBlokujePohyb" checked> Blokuje pohyb
    </label>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedBlokujeVystrel" checked> Blokuje výstřel/pohled
    </label>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedViditelnaHracum" checked> Viditelná hráčům
    </label>
    <?php if ($zdiDvere): ?>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedDvere"> Dveře (jdou otevřít a zavřít)
    </label>
    <?php endif; ?>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="zedSnap" checked> Přichytávat ke gridu
    </label>
    <p class="note">Klikáním polož body zdi (start, libovolně moc mezibodů, konec), pak Uložit. Klikni na existující zeď pro výběr a smazání.</p>
    <div style="display:flex;gap:6px;margin-top:8px;">
      <button class="btn btn-primary" type="button" id="zedUlozitBtn" style="flex:1;display:none;">Uložit zeď</button>
      <button class="btn btn-ghost" type="button" id="zedZrusitBtn" style="flex:1;display:none;">Zrušit</button>
    </div>
    <button class="btn btn-ghost" type="button" id="zedSmazatBtn" style="width:100%;margin-top:8px;display:none;">Smazat vybranou zeď</button>
  </div>

  <div class="popover" id="popover-pin-novy">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Nový pin</h3>
    <input type="hidden" id="pinNovyX">
    <input type="hidden" id="pinNovyY">
    <label for="pinNovyNazev">Název *</label>
    <input class="input" type="text" id="pinNovyNazev" required>
    <label for="pinNovyTyp">Typ</label>
    <select class="input" id="pinNovyTyp">
      <option value="mesto">Město</option>
      <option value="vesnice">Vesnice</option>
      <option value="tajne_misto">Tajné místo</option>
      <option value="budova">Budova</option>
      <option value="mistnost">Místnost</option>
      <option value="sklep">Sklep / dungeon</option>
      <option value="prechod">Přechod</option>
      <option value="jine">Jiné</option>
    </select>
    <label for="pinNovyCilovaMapa">Cílová mapa (nepovinné)</label>
    <select class="input" id="pinNovyCilovaMapa">
      <option value="">— bez cílové mapy —</option>
      <?php foreach ($zonoveMapyVeSvete as $zm): ?>
        <option value="<?= (int)$zm['id'] ?>"><?= htmlspecialchars($zm['nazev']) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="pinNovyPopis">Popis</label>
    <textarea id="pinNovyPopis"></textarea>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="pinNovyViditelny" checked> Viditelný hráčům
    </label>
    <button class="btn btn-primary" type="button" id="pinNovyUlozitBtn" style="width:100%;margin-top:10px;">Založit pin</button>
  </div>

  <div class="popover" id="popover-pin-upravit">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Upravit pin</h3>
    <input type="hidden" id="pinUpravitId">
    <input type="hidden" id="pinUpravitX">
    <input type="hidden" id="pinUpravitY">
    <p class="note" style="margin-top:0;">Pin na mapě teď jde přetáhnout na nové místo — zůstává tažitelný, dokud je tohle okno otevřené.</p>
    <label for="pinUpravitNazev">Název *</label>
    <input class="input" type="text" id="pinUpravitNazev" required>
    <label for="pinUpravitTyp">Typ</label>
    <select class="input" id="pinUpravitTyp">
      <option value="mesto">Město</option>
      <option value="vesnice">Vesnice</option>
      <option value="tajne_misto">Tajné místo</option>
      <option value="budova">Budova</option>
      <option value="mistnost">Místnost</option>
      <option value="sklep">Sklep / dungeon</option>
      <option value="prechod">Přechod</option>
      <option value="jine">Jiné</option>
    </select>
    <label for="pinUpravitCilovaMapa">Cílová mapa (nepovinné)</label>
    <select class="input" id="pinUpravitCilovaMapa">
      <option value="">— bez cílové mapy —</option>
      <?php foreach ($zonoveMapyVeSvete as $zm): ?>
        <option value="<?= (int)$zm['id'] ?>"><?= htmlspecialchars($zm['nazev']) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="pinUpravitPopis">Popis</label>
    <textarea id="pinUpravitPopis"></textarea>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="pinUpravitViditelny"> Viditelný hráčům
    </label>
    <button class="btn btn-primary" type="button" id="pinUpravitUlozitBtn" style="width:100%;margin-top:10px;">Uložit změny</button>
  </div>

  <div class="popover" id="popover-pin-info">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3 id="pinInfoNazev">Pin</h3>
    <p class="note" id="pinInfoTyp"></p>
    <p id="pinInfoPopis"></p>
    <div id="pinInfoNpcWrap" style="display:none;">
      <h4 class="rel-label">NPC na místě</h4>
      <ul id="pinInfoNpcList" style="margin:0 0 10px;padding-left:18px;font-size:12.5px;"></ul>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <a class="btn btn-secondary" id="pinInfoOtevritBtn" href="#" style="flex:1;display:none;">Otevřít mapu</a>
      <button class="btn btn-secondary" type="button" id="pinInfoAktivniBtn" style="flex:1;display:none;">Nastavit jako aktivní</button>
    </div>
    <button class="btn btn-primary" type="button" id="pinInfoVstoupitBtn" style="width:100%;margin-top:8px;display:none;" title="Přenese postavy družiny na vstupní bod cílové mapy, přepne aktivní mapu a hráče za ní přepne taky">Vstoupit s družinou</button>
    <button class="btn btn-secondary" type="button" id="pinInfoCestovatBtn" style="width:100%;margin-top:8px;display:none;">Cestovat sem</button>
    <button class="btn btn-secondary" type="button" id="pinInfoUpravitBtn" style="width:100%;margin-top:8px;display:none;">Upravit pin</button>
    <button class="btn btn-ghost" type="button" id="pinInfoSmazatBtn" style="width:100%;margin-top:8px;display:none;">Smazat pin</button>
  </div>

  <div class="popover" id="popover-grid">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Nastavení gridu</h3>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="gridZapnuty"> Grid zapnutý
    </label>
    <div id="gridNastaveniFields">
      <label for="gridTypSelect">Typ</label>
      <select class="input" id="gridTypSelect">
        <option value="ctverec">Čtverec</option>
        <option value="hex">Hex</option>
      </select>
      <label for="gridVelikostSlider">Velikost 1 sáhu (<span id="gridVelikostHodnota">70</span> px)</label>
      <input type="range" id="gridVelikostSlider" min="20" max="200" step="1" value="70" style="width:100%;">
      <label for="gridPosunXInput">Posun X</label>
      <input class="input" type="number" id="gridPosunXInput" value="0">
      <label for="gridPosunYInput">Posun Y</label>
      <input class="input" type="number" id="gridPosunYInput" value="0">
    </div>
    <button class="btn btn-primary" type="button" id="gridUlozitBtn" style="width:100%;margin-top:10px;">Uložit</button>
    <p class="note">Náhled na mapě se mění hned, uloží se až tlačítkem.</p>
    <?php if (dracak_vtt_tokeny_funkce()['meritko']): ?>
    <div style="border-top:1px solid rgba(255,255,255,.15);margin-top:10px;padding-top:8px;">
      <label for="meritkoMil">Měřítko mapy — kolik mil je 1000 px (světová mapa; prázdné = bez měřítka)</label>
      <input class="input" type="number" id="meritkoMil" min="0.01" max="100000" step="0.1" placeholder="např. 25" value="<?= isset($mapa['mil_na_1000px']) && $mapa['mil_na_1000px'] !== null ? htmlspecialchars((string)(float)$mapa['mil_na_1000px']) : '' ?>">
      <button class="btn btn-secondary" type="button" id="meritkoUlozitBtn" style="width:100%;margin-top:6px;">Uložit měřítko</button>
      <p class="note">S měřítkem ukáže měřidlo vzdálenost i v mílích a jedním klikem ji pošleš do kalkulačky cesty.</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="popover" id="popover-mlha">
    <button class="close-x" data-close-popover><?php dracak_icon('x', 14); ?></button>
    <h3>Mlha války</h3>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;">
      <input type="checkbox" id="mlhaAktivniChk" <?= $mapa['mlha_aktivni'] ? 'checked' : '' ?>> Mlha aktivní na týhle mapě
    </label>
    <?php if (dracak_vtt_tokeny_funkce()['videni']): ?>
    <label for="mapaOsvetleni" style="margin-top:10px;">Osvětlení mapy</label>
    <select class="input" id="mapaOsvetleni">
      <?php foreach (DRACAK_VTT_OSVETLENI as $klic => [$nazevOsv]): ?>
      <option value="<?= $klic ?>" <?= ($mapa['osvetleni'] ?? 'den') === $klic ? 'selected' : '' ?>><?= htmlspecialchars($klic === 'den' ? 'Světlo (mlha jako dosud)' : $nazevOsv) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-top:8px;" title="Zatemní tvoji mapu a ukáže světlo všech zdrojů se stínem za zdmi — vidíš jen ty, hráči nic nepoznají. Funguje na mapě Noc a Tma.">
      <input type="checkbox" id="svetloPjChk"> Ukázat světlo a stíny (vidíš jen ty)
    </label>
    <p class="note" id="svetloPjStav" hidden style="margin-top:4px;"></p>
    <p class="note" style="margin-top:4px;">Stupnice tmy podle knihy (Lektvar sovích očí): řídká tma (úplněk), tma (hvězdy), hustá tma, naprostá tma; pološero je jako den. Ve tmě hráč vidí jen to, co osvítí pochodeň, lucerna nebo jiný zdroj světla (nastavíš u tokenu). Tvorové s nočním viděním (např. lupič se Zlepšením zraku) vidí navíc do nastaveného dosahu — v naprosté tmě a v kouzelné tmě ne. Kudy prošel, zůstane ztlumeně; nestvůry mimo dohled nevidí. Funguje při zapnuté mlze a nastaveném gridu.</p>
    <?php endif; ?>
    <p class="note" style="margin-top:4px;">Vypnuté = hráči vidí celou mapu bez odhalování, stejně jako ty. Mlha vyžaduje nastavený grid (respektuje jeho buňky/hexy) — bez gridu nejde zapnout.</p>
    <div style="display:flex;gap:6px;margin-top:10px;">
      <button class="btn btn-secondary" type="button" id="mlhaOdhalitVseBtn" style="flex:1;font-size:12px;">Odhalit mapu všem</button>
      <button class="btn btn-ghost" type="button" id="mlhaZatahnoutVseBtn" style="flex:1;font-size:12px;">Zahalit mapu všem</button>
    </div>
    <?php if ($hraciVeSvete): ?>
    <label for="mlhaNahledSelect" style="margin-top:14px;">Náhled a ruční úprava mlhy hráče</label>
    <select class="input" id="mlhaNahledSelect">
      <option value="">— normální pohled (bez náhledu) —</option>
      <?php foreach ($hraciVeSvete as $h): ?>
        <option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['jmeno']) ?></option>
      <?php endforeach; ?>
    </select>
    <div id="mlhaKresliciFields" style="display:none;margin-top:8px;">
      <p class="note" style="margin:0 0 6px;">Vidíš, co má tenhle hráč odhalené (tmavé = skryté).</p>
      <div style="display:flex;gap:6px;">
        <button class="btn btn-secondary" type="button" id="mlhaRezimVolnaRukaBtn" style="flex:1;font-size:12px;">Volná ruka</button>
        <button class="btn btn-ghost" type="button" id="mlhaRezimObdelnikBtn" style="flex:1;font-size:12px;">Obdélník</button>
      </div>
      <div style="display:flex;gap:6px;margin-top:6px;">
        <button class="btn btn-secondary" type="button" id="mlhaOdhalitBtn" style="flex:1;">Odhalit</button>
        <button class="btn btn-ghost" type="button" id="mlhaZatahnoutBtn" style="flex:1;">Zatáhnout</button>
      </div>
      <p class="note" style="margin-top:6px;" id="mlhaKreslimRezim">Volná ruka — táhni po mapě. Režim: odhalit</p>
    </div>
    <?php else: ?>
    <p class="note">Ve světě zatím není žádný hráč pro náhled.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($mapaStrom):
    // Rekurzivní vykreslení stromu (svět → piny → zóny → jejich piny...).
    // Čistě zobrazovací — dřina je hotová v dracak_vtt_mapa_strom().
    $renderStromUzel = function (array $uzel) use (&$renderStromUzel, $mapaId) {
        $aktivni = (int)$uzel['id'] === $mapaId;
        echo '<li><a href="mapa.php?id=' . (int)$uzel['id'] . '" class="strom-link' . ($aktivni ? ' aktivni' : '') . '">';
        if (!empty($uzel['pin_typ'])) {
            dracak_icon(dracak_vtt_mapa_bod_ikona($uzel['pin_typ']), 14);
        }
        echo '<span>' . htmlspecialchars($uzel['nazev']) . '</span></a>';
        if ($uzel['deti']) {
            echo '<ul>';
            foreach ($uzel['deti'] as $dite) {
                $renderStromUzel($dite);
            }
            echo '</ul>';
        }
        echo '</li>';
    };
  ?>
  <div class="panel" id="panel-strom">
    <button class="close-x" data-close-panel><?php dracak_icon('x', 16); ?></button>
    <h3>Mapa míst</h3>
    <input class="input" type="text" id="stromHledat" placeholder="Hledat místo…" style="margin-bottom:10px;">
    <ul class="strom" id="stromRoot">
      <?php foreach ($mapaStrom as $koren) {
          $renderStromUzel($koren);
      } ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="panel" id="panel-log">
    <button class="close-x" data-close-panel><?php dracak_icon('x', 16); ?></button>
    <h3>Log</h3>
    <div id="logList"></div>
  </div>

  <div class="panel" id="panel-spravovat">
    <button class="close-x" data-close-panel><?php dracak_icon('x', 16); ?></button>
    <h3 id="spravovatNazev">—</h3>
    <?php if ($denikPostavy): ?>
    <!-- Deník postavy: otevře panel vedle mapy (assets/js/denik_dock.js) s deníkem právě tohoto tokenu -->
    <div id="spravovatDenikBlok" class="denik-blok" style="display:none;">
      <button class="btn btn-secondary denik-otevrit" type="button" id="spravovatDenik"><?php dracak_icon('book-open-text', 16); ?> Otevřít deník</button>
      <div class="denik-skoky" aria-label="Rovnou na sekci deníku">
        <button class="btn btn-ghost" type="button" data-denik-sekce="boj" title="Zbraně, obrana, stav v boji">Boj</button>
        <button class="btn btn-ghost" type="button" data-denik-sekce="kouzla" title="Kouzla (a schopnosti, když postava kouzla nemá)">Kouzla</button>
        <button class="btn btn-ghost" type="button" data-denik-sekce="vybaveni" title="Inventář, kontejnery, peníze">Výbava</button>
      </div>
    </div>
    <?php endif; ?>
    <!-- Dosah pohybu postavy mimo boj (PJ a vlastník): sáhy za kolo (poklus/běh/sprint) a za směnu (procházka/chůze/spěch) podle terénu -->
    <div id="dosahBlok" class="panel-sekce" style="display:none;">
      <button class="btn btn-ghost" type="button" id="dosahBtn">Dosah pohybu…</button>
      <div id="dosahObsah" style="display:none;margin-top:6px;">
        <label for="dosahPovrch">Povrch (náročnost, body na míli / 10 sáhů)</label>
        <select class="input" id="dosahPovrch"></select>
        <div id="dosahVysl" class="note" style="margin-top:6px;"></div>
        <button class="btn btn-secondary" type="button" id="dosahUkazat" style="width:100%;margin-top:6px;">Ukázat dosah na mapě</button>
      </div>
    </div>
    <!-- Deník nestvůry (jen PJ, jen u nestvůry): přehled z bestiáře a čísla pro boj — nahoře, ať se nemusí scrollovat -->
    <div id="denikNestvury" class="panel-sekce" style="display:none;"></div>

    <!-- Vzhled a poloha tokenu: otočení, velikost, barva, zámek, obrázek.
         Co z toho se zobrazí, řídí JS podle oprávnění (PJ vs. vlastník) a
         podle toho, jestli je v DB hotové ruční SQL (window.MAPA.funkce). -->
    <div id="vzhledSekce" class="panel-sekce" style="display:none;">
      <h4 class="panel-h4">Vzhled</h4>
      <div id="vzhledNazevRadek" class="vz-radek" style="display:none;">
        <input class="input" type="text" id="vzhledNazev" maxlength="100" placeholder="Název tokenu">
        <button class="btn btn-ghost" type="button" id="vzhledNazevUlozit">Uložit</button>
      </div>
      <div id="vzhledBarvaRadek" class="vz-radek" style="display:none;">
        <span class="vz-popisek">Barva</span>
        <div id="vzhledBarvy" class="vz-barvy"></div>
      </div>
      <div id="vzhledVelikostRadek" class="vz-radek" style="display:none;">
        <span class="vz-popisek">Velikost</span>
        <div id="vzhledVelikost" class="vz-seg"></div>
      </div>
      <div id="vzhledHexuRadek" class="vz-radek" style="display:none;">
        <span class="vz-popisek" title="Kolik hexů tvor zabírá na hexové mapě (1, zvířecí C = 2, D = 3, E = 5)">Zabírá hexů</span>
        <div id="vzhledHexu" class="vz-seg"></div>
      </div>
      <div id="vzhledOtoceniRadek" class="vz-radek" style="display:none;">
        <span class="vz-popisek">Otočení</span>
        <div class="vz-seg">
          <button class="btn btn-ghost" type="button" id="vzhledOtocL" title="Otočit doleva (Q)"><?php dracak_icon('rotate-ccw', 16); ?></button>
          <button class="btn btn-ghost" type="button" id="vzhledOtocR" title="Otočit doprava (E)"><?php dracak_icon('rotate-cw', 16); ?></button>
        </div>
      </div>
      <div id="vzhledObrazekRadek" class="vz-radek" style="display:none;">
        <span class="vz-popisek">Obrázek</span>
        <span id="vzhledObrazekNahled" class="vz-nahled"></span>
        <button class="btn btn-ghost" type="button" id="vzhledObrazekVybrat">Vybrat…</button>
        <button class="btn btn-ghost" type="button" id="vzhledObrazekOdebrat">Odebrat</button>
      </div>
      <label id="vzhledZamcenoRadek" class="vz-check" style="display:none;"><input type="checkbox" id="vzhledZamceno"> Zamknout polohu</label>
      <label id="vzhledSkrytyRadek" class="vz-check" style="display:none;"><input type="checkbox" id="vzhledSkryty"> Skrýt před hráči</label>
      <div id="vzhledSvetloRadek" class="vz-radek" style="display:none;">
        <label for="vzhledSvetlo">Světlo</label>
        <select class="input" id="vzhledSvetlo">
          <option value="">Nesvítí</option>
          <option value="pochoden:6">Pochodeň (6 sáhů)</option>
          <option value="lucerna:8">Lucerna (8 sáhů)</option>
          <option value="lucerna:4">Lucerna, poloviční spotřeba (4 sáhy)</option>
          <option value="lucerna:2">Lucerna, čtvrtinová spotřeba (2 sáhy)</option>
          <option value="hul:6">Čarodějova hůl jako pochodeň (6 sáhů)</option>
          <option value="hul:8">Čarodějova hůl jako lucerna (8 sáhů)</option>
          <option value="vlastni">Vlastní dosah…</option>
          <optgroup label="Zóna tmy (kouzlo, tma potlačí světlo i infravidění)">
            <option value="tma:vlastni">Tma / Obscurum (4 magy za sáh poloměru)…</option>
            <option value="temnota:300">Temnota druida (300 sáhů)</option>
          </optgroup>
        </select>
        <input class="input" type="number" id="vzhledSvetloSahy" min="0.5" max="300" step="0.5" placeholder="sáhů" style="display:none;width:6rem;">
      </div>
      <div id="vzhledZbyvaRadek" class="vz-radek" style="display:none;">
        <label for="vzhledZbyva">Vydrží ještě (směn; prázdné = bez limitu)</label>
        <input class="input" type="number" id="vzhledZbyva" min="1" max="9999" step="1" placeholder="např. 3" style="width:6rem;">
      </div>
      <div id="vzhledInfraRadek" class="vz-radek" style="display:none;">
        <label for="vzhledInfra">Infravidění (sáhů; prázdné = nemá)</label>
        <input class="input" type="number" id="vzhledInfra" min="1" max="200" step="1" placeholder="např. 20" style="width:6rem;">
        <label class="vz-check"><input type="checkbox" id="vzhledStudeny"> Studenokrevný (infravidění ho nevidí)</label>
      </div>
      <div id="vzhledNocRadek" class="vz-radek" style="display:none;">
        <label for="vzhledNoc">Noční vidění (sáhů; prázdné = nemá)</label>
        <input class="input" type="number" id="vzhledNoc" min="1" max="200" step="1" placeholder="dosah" style="width:6rem;">
      </div>
    </div>

    <div id="sekceEntita">
    <div id="spravovatPjStav" class="pj-stav" style="display:none;"></div>
    <div class="panel-hp-row">
      <button class="btn btn-ghost" type="button" data-delta="-5">−5</button>
      <button class="btn btn-ghost" type="button" data-delta="-1">−1</button>
      <button class="btn btn-ghost" type="button" data-delta="1">+1</button>
      <button class="btn btn-ghost" type="button" data-delta="5">+5</button>
    </div>
    <!-- Magenergie (content/pravidla-hrac.html h297 aj., viz migrace
         0050_vtt_postava_magenergie.sql) — jen postavy s nastaveným
         max_magenergie (povolání, co magenergii používá); schováno přes
         otevritSpravovat() JS, ne přes PHP, protože panel je společný
         pro všechny tokeny na mapě, ne per-token. -->
    <div id="panelMagenergieRow" style="display:none;margin-top:10px;">
      <label style="font-size:11px;font-weight:600;">Magenergie: <span id="spravovatMagenergieText">—</span></label>
      <div class="panel-hp-row">
        <button class="btn btn-ghost" type="button" data-mag-delta="-5">−5</button>
        <button class="btn btn-ghost" type="button" data-mag-delta="-1">−1</button>
        <button class="btn btn-ghost" type="button" data-mag-delta="1">+1</button>
        <button class="btn btn-ghost" type="button" data-mag-delta="5">+5</button>
      </div>
    </div>
    <?php if (!$souboj): ?>
    <div style="margin-top:10px;">
      <label style="font-size:11px;font-weight:600;">Iniciativa — bonusy/postihy (zaškrtni, co platí)</label>
      <div id="iniciativaAutoPozn" class="note" style="font-size:11px;margin:2px 0 4px;display:none;">U postavy se únava a naložení počítají samy podle jejího stavu.</div>
      <div id="iniciativaBonusyList" style="max-height:120px;overflow-y:auto;font-size:12px;margin-bottom:6px;">
        <?php foreach ($iniciativaBonusy as $b): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-bottom:2px;">
            <?php $autoSkupina = in_array($b['popis'], DRACAK_VTT_INICIATIVA_UNAVA, true) ? 'unava' : (in_array($b['popis'], DRACAK_VTT_INICIATIVA_NALOZENI, true) ? 'nalozeni' : ''); ?>
            <input type="checkbox" class="iniciativa-bonus-check" value="<?= (int)$b['id'] ?>"<?= $autoSkupina !== '' ? ' data-auto="' . $autoSkupina . '"' : '' ?>>
            <?= htmlspecialchars($b['popis']) ?> (<?= $b['bonus'] >= 0 ? '+' . (int)$b['bonus'] : (int)$b['bonus'] ?>)
          </label>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:6px;align-items:center;">
        <label style="font-size:11px;" for="iniciativaJinyBonus">jiný:</label>
        <input type="number" id="iniciativaJinyBonus" value="0" class="input" style="width:60px;">
        <button class="btn btn-secondary" type="button" id="iniciativaHoditBtn" style="flex:1;">Hoď iniciativu</button>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($isPjOrAdmin && $rychleEfekty): ?>
      <label style="font-size:11px;font-weight:600;">Rychlé efekty</label>
      <div id="rychleEfektyChips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
        <?php foreach ($rychleEfekty as $e): ?>
          <button type="button" class="btn btn-ghost chip-efekt" data-efekt-id="<?= (int)$e['id'] ?>" style="padding:4px 10px;font-size:12px;"><?= htmlspecialchars($e['nazev']) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div id="spravovatEfekty" class="panel-sekce" style="display:none;"></div>
    <?php if ($isPjOrAdmin && $efektyKatalog): ?>
      <label style="font-size:11px;font-weight:600;">Aplikovat efekt</label>
      <?php $efektZnacky = ['auto' => '⚙', 'cast' => '◐', 'pj' => '✋', 'past' => '✋', 'ceka' => '❓']; ?>
      <select id="efektSelect">
        <?php foreach ($efektyKatalog as $e): ?><option value="<?= (int)$e['id'] ?>" data-trvani="<?= isset($e['trvani_kol']) && $e['trvani_kol'] !== null ? (int)$e['trvani_kol'] : '' ?>" data-stav="<?= htmlspecialchars((string)($e['mechanika_stav'] ?? '')) ?>"><?= $efektyMech ? ($efektZnacky[$e['mechanika_stav']] ?? '✋') . ' ' : '' ?><?= htmlspecialchars($e['nazev']) ?> (<?= htmlspecialchars($e['typ']) ?>)</option><?php endforeach; ?>
      </select>
      <input type="number" id="efektKola" min="0" placeholder="kol (prázdné = výchozí, 0 = bez limitu)">
      <?php if ($efektyMech): ?>
        <div class="note" id="efektNapoveda" style="font-size:11px;margin:2px 0 6px;">⚙ boj vyhodnotí sám · ◐ čísla a akce boj, zbytek PJ · ✋ jen PJ podle popisu · ❓ v knize chybí údaj</div>
      <?php endif; ?>
      <button class="btn btn-secondary" type="button" id="efektBtn" style="width:100%;">Aplikovat efekt</button>
    <?php endif; ?>
    <div id="spravovatInventar" style="margin-top:14px;"></div>
    <div id="spravovatKorist" style="margin-top:14px;"></div>
    </div>
    <button class="btn btn-ghost" type="button" id="smazatTokenBtn" style="width:100%;margin-top:14px;">Smazat token</button>
  </div>

  <div id="kontextMenu" class="kontext-menu" style="display:none;"></div>

  <?php if ($souboj): ?>
  <div id="modalSouboj" class="modal-obrazky" style="display:none;">
    <div class="modal-obrazky-okno">
      <button class="close-x" type="button" id="modalSoubojZavrit" title="Zavřít"><?php dracak_icon('x', 16); ?></button>
      <h3 id="soubojNadpis">Zahájit souboj</h3>
      <p class="note" style="margin-top:0;">Zaškrtni, kdo se souboje účastní, a na které straně stojí. Překvapená strana má v 1. kole −6 k iniciativě.</p>
      <label class="vz-radek" for="soubojPrekvapeni" style="display:flex;"><span class="vz-popisek" style="min-width:90px;">Překvapení</span>
        <select id="soubojPrekvapeni" class="input" style="flex:1;">
          <option value="zadne">Nikdo (běžný začátek)</option>
          <option value="druzina">Družina je překvapena</option>
          <option value="nepratele">Nepřátelé jsou překvapeni</option>
          <option value="obe">Obě strany jsou překvapeny</option>
        </select>
      </label>
      <!-- Rychlý výběr: na mapě s desítkami tvorů je zaškrtávání po jednom nepoužitelné. „Jen družina“ je nejčastější případ —
           PJ si pak nestvůry, které mají vstoupit do iniciativy, doklikne. Zbytek NPC řídí ručně mimo pořadí tahů. -->
      <div class="vz-radek" style="gap:6px;margin:8px 0 4px;flex-wrap:wrap;align-items:center;">
        <span class="note" style="margin:0;">Rychle:</span>
        <button class="btn btn-ghost" type="button" data-souboj-vyber="druzina" style="font-size:12px;padding:3px 8px;">Jen družina</button>
        <button class="btn btn-ghost" type="button" data-souboj-vyber="vse" style="font-size:12px;padding:3px 8px;">Vše</button>
        <button class="btn btn-ghost" type="button" data-souboj-vyber="nic" style="font-size:12px;padding:3px 8px;">Nikoho</button>
        <span class="note" id="soubojPocet" style="margin:0 0 0 auto;"></span>
      </div>
      <div id="soubojUcastnici" class="souboj-seznam"></div>
      <div class="vz-radek" style="margin-top:10px;">
        <button class="btn btn-secondary" type="button" id="soubojUlozit" style="flex:1;">Zahájit souboj</button>
      </div>
      <p class="note" id="soubojChyba" style="color:#ff9a8a;display:none;"></p>
    </div>
  </div>

  <div id="modalHod" class="modal-obrazky" style="display:none;">
    <div class="modal-obrazky-okno" style="width:min(420px,100%);">
      <button class="close-x" type="button" id="modalHodZavrit" title="Zavřít"><?php dracak_icon('x', 16); ?></button>
      <h3 id="hodNadpis">Iniciativa</h3>
      <p class="note" id="hodPopis" style="margin-top:0;">Hoď 1k6. Únava, naložení, překvapení a kouzlení se přičtou samy.</p>
      <details class="hod-bonusy">
        <summary>Další bonusy a postihy</summary>
        <div id="hodBonusyList" style="max-height:150px;overflow-y:auto;font-size:12px;margin:6px 0;">
          <?php foreach ($hodBonusy as $b): ?>
            <?php $autoSkupina = in_array($b['popis'], DRACAK_VTT_INICIATIVA_UNAVA, true) ? 'unava' : (in_array($b['popis'], DRACAK_VTT_INICIATIVA_NALOZENI, true) ? 'nalozeni' : ($zbraneIni && $souboj && in_array($b['popis'], DRACAK_VTT_INI_RADKY_ZBRANE, true) ? 'zbran' : '')); ?>
            <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin-bottom:2px;">
              <input type="checkbox" class="hod-bonus-check" value="<?= (int)$b['id'] ?>"<?= $autoSkupina !== '' ? ' data-auto="' . $autoSkupina . '"' : '' ?>>
              <?= htmlspecialchars($b['popis']) ?> (<?= $b['bonus'] >= 0 ? '+' . (int)$b['bonus'] : (int)$b['bonus'] ?>)
            </label>
          <?php endforeach; ?>
        </div>
        <div class="vz-radek"><label for="hodJinyBonus" style="font-size:11px;">jiný bonus:</label>
          <input type="number" id="hodJinyBonus" value="0" class="input" style="width:70px;"></div>
      </details>
      <div class="hod-hlavni">
        <button class="btn btn-secondary" type="button" id="hodKostkaBtn" style="flex:1;font-size:15px;padding:10px;"><?php dracak_icon('dices', 18); ?> Hodit kostkou</button>
      </div>
      <div id="hodVysledek" class="hod-vysledek" aria-live="polite"></div>
      <div class="vz-radek hod-rucne">
        <label for="hodRucne" style="font-size:12px;">Hodil jsem na vlastní kostku:</label>
        <input type="number" id="hodRucne" min="1" max="6" class="input" style="width:64px;">
        <button class="btn btn-ghost" type="button" id="hodRucneBtn">Zapsat</button>
      </div>
      <p class="note" id="hodChyba" style="color:#ff9a8a;display:none;"></p>
    </div>
  </div>
  <?php endif; ?>


  <div id="modalObrazky" class="modal-obrazky" style="display:none;">
    <div class="modal-obrazky-okno">
      <button class="close-x" type="button" id="modalObrazkyZavrit" title="Zavřít"><?php dracak_icon('x', 16); ?></button>
      <h3>Obrázek tokenu</h3>
      <p class="note" style="margin-top:0;">Knihovna obrázků světa. Vyber obrázek, nebo nahraj nový (PNG, JPG, WEBP — zmenší se na 256 px).</p>
      <div id="obrazkyMrizka" class="obrazky-mrizka"></div>
      <div class="vz-radek" style="margin-top:10px;">
        <input type="file" id="obrazekSoubor" accept="image/png,image/jpeg,image/webp" style="flex:1;min-width:0;">
        <button class="btn btn-secondary" type="button" id="obrazekNahrat">Nahrát</button>
      </div>
      <p class="note" id="obrazekChyba" style="color:#ff9a8a;display:none;"></p>
    </div>
  </div>

  <div id="raidPanel" class="raid-panel" style="display:none;" role="dialog" aria-label="Tvorové na mapě a pořadí tahů">
    <div class="raid-hlava" id="raidHlava" title="Tažením přesuneš okno">
      <span class="raid-grip-okna" aria-hidden="true">⠿</span>
      <div class="raid-nadpis"><strong id="raidTitul">Na mapě</strong><span id="raidPodtitul"></span></div>
      <button type="button" class="raid-btn" id="raidReset" style="display:none;" title="Vrátit pořadí podle iniciativy (zahodit ruční přeřazení)">↺ pořadí</button>
      <button type="button" class="raid-btn" id="raidRezim" title="Přepnout na kompaktní řádky">Kompakt</button>
      <button type="button" class="raid-btn raid-ikona" id="raidSbalit" title="Sbalit na hlavičku" aria-label="Sbalit panel na hlavičku"><?php dracak_icon('minus', 16); ?></button>
      <button type="button" class="raid-btn raid-ikona" id="raidZavrit" title="Zavřít" aria-label="Zavřít panel"><?php dracak_icon('x', 16); ?></button>
    </div>
    <div class="raid-telo-okna">
      <div class="raid-faze" id="raidFaze"></div>
      <div class="raid-info" id="raidInfo"></div>
      <div class="raid-seznam" id="raidSeznam"></div>
      <div class="raid-akce" id="raidAkce"></div>
      <?php if ($isPjOrAdmin): ?>
        <div class="ini-pj" id="souborPj" style="display:none;">
          <?php if ($souboj): ?>
            <button class="btn btn-ghost" type="button" id="souborUcastniciBtn" title="Přidat nebo odebrat účastníky, změnit strany a překvapení">Účastníci…</button>
          <?php endif; ?>
          <button class="btn btn-ghost" type="button" id="souborjKonecBtn" title="Smaže pořadí tahů; hráči se zase mohou volně hýbat">Ukončit souboj</button>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($denikPostavy): ?>
  <div id="denikDock" class="denik-dock" style="display:none;" role="dialog" aria-label="Deník postavy">
    <?php foreach (['n', 'e', 's', 'w', 'ne', 'nw', 'se', 'sw'] as $hrana): ?>
      <div class="denik-hrana" data-hrana="<?= $hrana ?>" aria-hidden="true"></div>
    <?php endforeach; ?>
    <div class="denik-dock-hlava" id="denikHlava" title="Tažením přesuneš okno">
      <span class="denik-dock-titul"><span class="denik-dock-grip" aria-hidden="true">⠿</span> Deník — <strong id="denikTitul"></strong></span>
      <a id="denikOtevrit" class="denik-dock-btn" target="_blank" rel="noopener" title="Otevřít na samostatné stránce (nová záložka)">↗</a>
      <button id="denikSbalit" class="denik-dock-btn" type="button" title="Sbalit na hlavičku" aria-label="Sbalit deník na hlavičku"><?php dracak_icon('minus', 16); ?></button>
      <button id="denikCela" class="denik-dock-btn" type="button" title="Na celou obrazovku" aria-label="Deník na celou obrazovku"><span class="ik-max"><?php dracak_icon('maximize', 16); ?></span><span class="ik-min"><?php dracak_icon('minimize', 16); ?></span></button>
      <button id="denikZavrit" class="denik-dock-btn" type="button" title="Zavřít deník" aria-label="Zavřít deník"><?php dracak_icon('x', 16); ?></button>
    </div>
    <div class="denik-dock-volby">
      <select id="denikPostava" class="input" aria-label="Postava"></select>
      <select id="denikSledovat" class="input" aria-label="Kterou postavu deník ukazuje" title="Kterou postavu deník ukazuje">
        <option value="vyber">podle vybraného tokenu</option>
        <option value="tah">podle toho, kdo je na tahu</option>
        <option value="nic">pevně tuhle postavu</option>
      </select>
    </div>
    <iframe id="denikFrame" class="denik-dock-frame" title="Deník postavy"></iframe>
  </div>
  <?php endif; ?>

  <div id="diceBoxContainer" style="position:fixed;inset:0;pointer-events:none;z-index:9999;"></div>

<script nonce="<?= dracak_csp_nonce() ?>">
// Konfigurace a počáteční data pro assets/js/mapa.js (samotný skript je
// statický soubor, aby se dal cachovat).
window.MAPA = <?= json_encode([
    'svetId' => $svetId,
    'mapaId' => $mapaId,
    'jePj' => $isPjOrAdmin,
    'pohybAuto' => ['unava' => $pohybNastaveni['auto_unava'], 'nalozeni' => $pohybNastaveni['auto_naklad'], 'zbran' => $zbraneIni && $souboj],
    'funkce' => dracak_vtt_tokeny_funkce(),
    'cas' => $casSveta,
    'pravidla' => dracak_vtt_souboj_pravidla(),
    'verzeModulu' => max((int)@filemtime(__DIR__ . '/../assets/js/souboj.js'), (int)@filemtime(__DIR__ . '/../assets/js/kostky3d.js'), (int)@filemtime(__DIR__ . '/../assets/js/hexy.js'), (int)@filemtime(__DIR__ . '/../assets/js/denik_dock.js'), (int)@filemtime(__DIR__ . '/../assets/js/raid_panel.js'), (int)@filemtime(__DIR__ . '/../assets/js/svetlo_stiny.js'), (int)@filemtime(__DIR__ . '/../assets/js/token_bar.js')),
    'grid' => [
        'px' => (int)($mapa['grid_velikost_px'] ?? 0),
        'typ' => $mapa['grid_typ'] ?? 'ctverec',
        'offX' => (int)($mapa['grid_posun_x'] ?? 0),
        'offY' => (int)($mapa['grid_posun_y'] ?? 0),
    ],
    'zdi' => array_values($zdi),
    'dvere' => $zdiDvere,
    'osvetleni' => (string)($mapa['osvetleni'] ?? 'den'),
    'meritko' => isset($mapa['mil_na_1000px']) && $mapa['mil_na_1000px'] !== null ? (float)$mapa['mil_na_1000px'] : null,
    'piny' => array_values($mapaBody),
    'svetPostavy' => $svetPostavyKorist,
    'denikPostavy' => $denikPostavy,
    'mlhaAktivni' => (bool)$mapa['mlha_aktivni'],
    'stav' => $stav,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script type="module" src="../assets/js/mapa.js?v=<?= filemtime(__DIR__ . '/../assets/js/mapa.js') ?>"></script>
<?php endif; ?>
</body>
</html>
