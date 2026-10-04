<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/vtt.php';
require_once __DIR__ . '/../includes/vtt_predmety.php';
require_once __DIR__ . '/../includes/vtt_poznamky.php';

$user = dracak_require_login();
$svetId = (int)($_GET['id'] ?? 0);
$svet = dracak_vtt_require_svet($user, $svetId);
$isPjOrAdmin = dracak_vtt_je_pj_sveta($user, $svetId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $akce = $_POST['akce'] ?? '';

    if ($akce === 'shrnuti') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin může upravit shrnutí.'); }
        $stmt = dracak_db()->prepare('UPDATE svet SET posledni_shrnuti = ?, pripraveno_priste = ? WHERE id = ?');
        $stmt->execute([
            trim((string)($_POST['posledni_shrnuti'] ?? '')) ?: null,
            trim((string)($_POST['pripraveno_priste'] ?? '')) ?: null,
            $svetId,
        ]);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'pridat_hrace') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin přidává hráče.'); }
        $ucetId = (int)($_POST['ucet_id'] ?? 0);
        if ($ucetId > 0) {
            $stmt = dracak_db()->prepare('INSERT IGNORE INTO svet_hraci (svet_id, ucet_id) VALUES (?, ?)');
            $stmt->execute([$svetId, $ucetId]);
        }
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'nova_mapa') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin přidává mapy.'); }
        $nazev = trim((string)($_POST['nazev'] ?? ''));
        $typMapy = ($_POST['typ_mapy'] ?? 'zona') === 'svet' ? 'svet' : 'zona';
        if ($nazev === '') { http_response_code(422); die('Mapa musí mít název.'); }

        // Grid je nepovinný — prázdná velikost = grid_velikost_px zůstává
        // NULL a mapa.php ho nevykresluje (chování shodné se stavem před
        // touhle funkcí). Typ/offset dávají smysl jen spolu s velikostí,
        // ale ukládají se vždy (typ má DB DEFAULT 'ctverec', offsety 0).
        $gridPx = trim((string)($_POST['grid_velikost_px'] ?? ''));
        $gridVelikostPx = $gridPx !== '' ? max(1, (int)$gridPx) : null;
        $gridTyp = ($_POST['grid_typ'] ?? 'ctverec') === 'hex' ? 'hex' : 'ctverec';
        $gridPosunX = (int)($_POST['grid_posun_x'] ?? 0);
        $gridPosunY = (int)($_POST['grid_posun_y'] ?? 0);

        $obrazekCesta = null;
        $sirka = null;
        $vyska = null;
        if (!empty($_FILES['obrazek']['tmp_name']) && is_uploaded_file($_FILES['obrazek']['tmp_name'])) {
            $povoleneTypy = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $info = getimagesize($_FILES['obrazek']['tmp_name']);
            if ($info === false || !isset($povoleneTypy[$info['mime']])) {
                http_response_code(422);
                die('Obrázek mapy musí být PNG, JPG nebo WEBP.');
            }
            $sirka = $info[0];
            $vyska = $info[1];
            $nazevSouboru = bin2hex(random_bytes(16)) . '.' . $povoleneTypy[$info['mime']];
            $cil = __DIR__ . '/../uploads/mapy/' . $nazevSouboru;
            if (!move_uploaded_file($_FILES['obrazek']['tmp_name'], $cil)) {
                dracak_fail_safely('Nepodařilo se uložit nahraný obrázek mapy.');
            }
            $obrazekCesta = $nazevSouboru;
        }

        $stmt = dracak_db()->prepare(
            'INSERT INTO mapy (svet_id, nazev, typ_mapy, obrazek_cesta, sirka_px, vyska_px, grid_velikost_px, grid_typ, grid_posun_x, grid_posun_y)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$svetId, $nazev, $typMapy, $obrazekCesta, $sirka, $vyska, $gridVelikostPx, $gridTyp, $gridPosunX, $gridPosunY]);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'nova_postava') {
        $nazev = trim((string)($_POST['nazev'] ?? ''));
        if ($nazev === '') { http_response_code(422); die('Postava musí mít jméno.'); }
        $rasaId = !empty($_POST['rasa_id']) ? (int)$_POST['rasa_id'] : null;
        $povolaniId = !empty($_POST['povolani_id']) ? (int)$_POST['povolani_id'] : null;
        $maxHp = max(0, (int)($_POST['max_hp'] ?? 0));
        // Atributy jsou "stupeň" podle pravidel (h104), ne bonus — viz
        // migrace 0040. Nepovinné, starší/rychle založené postavy je
        // mohou mít NULL.
        $atributy = [];
        foreach (['sila', 'obratnost', 'odolnost', 'inteligence', 'charisma'] as $atr) {
            $atributy[$atr] = !empty($_POST[$atr]) ? max(1, (int)$_POST[$atr]) : null;
        }

        // Hráč zakládá vždycky sám sobě — neřeší se, co pošle v POSTu.
        // PJ/admin může založit rovnou pro kohokoliv u stolu (vlastnik_ucet_id
        // v POSTu), jinak taky sám sobě.
        $vlastnikId = $user['id'];
        if ($isPjOrAdmin && !empty($_POST['vlastnik_ucet_id'])) {
            $stmt = dracak_db()->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
            $stmt->execute([$svetId, (int)$_POST['vlastnik_ucet_id']]);
            if ($stmt->fetchColumn()) {
                $vlastnikId = (int)$_POST['vlastnik_ucet_id'];
            }
        }

        $stmt = dracak_db()->prepare(
            'INSERT INTO postavy (svet_id, vlastnik_ucet_id, nazev, rasa_id, povolani_id, uroven, sila, obratnost, odolnost, inteligence, charisma, aktualni_hp, max_hp)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $svetId, $vlastnikId, $nazev, $rasaId, $povolaniId,
            $atributy['sila'], $atributy['obratnost'], $atributy['odolnost'], $atributy['inteligence'], $atributy['charisma'],
            $maxHp, $maxHp,
        ]);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'pripojit_postavu') {
        // Připojení VLASTNÍ už existující, zatím nepřiřazené postavy (viz
        // hra/postavy_moje.php) rovnou z téhle stránky — alternativa k
        // zakládání nové, když si ji hráč připravil dřív bez vazby na
        // svět. Jen vlastník, žádná výjimka pro PJ/admin (ti postavu
        // nevlastní, takže by si tu nic nemohli vybrat).
        $postavaId = (int)($_POST['postava_id'] ?? 0);
        $stmt = dracak_db()->prepare('SELECT 1 FROM postavy WHERE id = ? AND vlastnik_ucet_id = ? AND svet_id IS NULL');
        $stmt->execute([$postavaId, $user['id']]);
        if ($stmt->fetchColumn()) {
            $stmt = dracak_db()->prepare('UPDATE postavy SET svet_id = ? WHERE id = ?');
            $stmt->execute([$svetId, $postavaId]);
        }
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'prevest_postavu') {
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin přiřazuje postavu jinému hráči.'); }
        $postavaId = (int)($_POST['postava_id'] ?? 0);
        $novyVlastnikId = (int)($_POST['novy_vlastnik_id'] ?? 0);
        $stmt = dracak_db()->prepare('SELECT 1 FROM svet_hraci WHERE svet_id = ? AND ucet_id = ?');
        $stmt->execute([$svetId, $novyVlastnikId]);
        if ($stmt->fetchColumn()) {
            $stmt = dracak_db()->prepare('UPDATE postavy SET vlastnik_ucet_id = ? WHERE id = ? AND svet_id = ?');
            $stmt->execute([$novyVlastnikId, $postavaId, $svetId]);
        }
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'pridat_polozku') {
        // PJ dá postavě startovní předmět/lektvar/kouzlo — v1 jednoduše
        // podle ID z katalogu (viz editor.php?tabulka=... pro dohledání ID),
        // ne přes plný výběrový katalog, ať je formulář malý (viz konverzace).
        if (!$isPjOrAdmin) { http_response_code(403); die('Jen PJ/admin přidává položky postavě.'); }
        $cilovaPostavaId = (int)($_POST['postava_id'] ?? 0);
        $typPolozky = (string)($_POST['typ_polozky'] ?? '');
        $polozkaId = (int)($_POST['polozka_id'] ?? 0);
        $mnozstvi = max(1, (int)($_POST['mnozstvi'] ?? 1));

        $cfg = dracak_vtt_polozka_config($typPolozky);
        if (!$cfg) { http_response_code(422); die('Neplatný typ položky.'); }

        $stmt = dracak_db()->prepare('SELECT 1 FROM postavy WHERE id = ? AND svet_id = ?');
        $stmt->execute([$cilovaPostavaId, $svetId]);
        if (!$stmt->fetchColumn()) { http_response_code(404); die('Postava nenalezena v tomhle světě.'); }

        if (!dracak_vtt_polozka_katalog($typPolozky, $polozkaId)) {
            http_response_code(404);
            die('Položka s tímhle ID v katalogu neexistuje.');
        }

        if ($typPolozky === 'kouzlo') {
            $ins = dracak_db()->prepare('INSERT IGNORE INTO postava_zna_kouzlo (postava_id, kouzlo_id) VALUES (?, ?)');
            $ins->execute([$cilovaPostavaId, $polozkaId]);
        } else {
            $ins = dracak_db()->prepare(
                "INSERT INTO {$cfg['inventar']} (postava_id, {$cfg['fk']}, mnozstvi) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE mnozstvi = mnozstvi + VALUES(mnozstvi)"
            );
            $ins->execute([$cilovaPostavaId, $polozkaId, $mnozstvi]);
        }
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'pridat_poznamku') {
        // Kdokoli s přístupem do světa (PJ i hráč) — žádná role-based
        // výjimka, viz includes/vtt_poznamky.php.
        $text = trim((string)($_POST['text'] ?? ''));
        if ($text === '') { http_response_code(422); die('Poznámka nemůže být prázdná.'); }
        $sdileno = array_map('intval', (array)($_POST['sdileno'] ?? []));
        // Místo nepovinné, musí ale patřit do TOHOTO světa — jinak by
        // šlo poznámku navázat na mapu z cizího světa.
        $mapaId = !empty($_POST['mapa_id']) ? (int)$_POST['mapa_id'] : null;
        if ($mapaId !== null) {
            $stmt = dracak_db()->prepare('SELECT 1 FROM mapy WHERE id = ? AND svet_id = ?');
            $stmt->execute([$mapaId, $svetId]);
            if (!$stmt->fetchColumn()) { $mapaId = null; }
        }
        dracak_vtt_poznamka_pridat(dracak_db(), $svetId, (int)$user['id'], $text, $sdileno, (int)$svet['aktualni_den_offset'], $mapaId);
        header("Location: svet.php?id=$svetId");
        exit;
    }

    if ($akce === 'smazat_poznamku') {
        dracak_vtt_poznamka_smazat(dracak_db(), (int)($_POST['poznamka_id'] ?? 0), $user);
        header("Location: svet.php?id=$svetId");
        exit;
    }
}


$mapy = dracak_db()->prepare('SELECT * FROM mapy WHERE svet_id = ? ORDER BY typ_mapy DESC, nazev');
$mapy->execute([$svetId]);
$mapy = $mapy->fetchAll();

// Světová mapa pro velký proklik — přednostně ta aktivní, jinak první
// světová. Zóny řadíme podle poslední aktivity v logu (svet_udalosti),
// mapa bez jakékoliv události spadne na datum vytvoření.
$svetovaMapa = null;
foreach ($mapy as $m) {
    if ($m['typ_mapy'] === 'svet' && ($svetovaMapa === null || (int)$m['id'] === (int)$svet['aktivni_mapa_id'])) {
        $svetovaMapa = $m;
    }
}
$stmt = dracak_db()->prepare('SELECT mapa_id, MAX(vytvoreno) AS naposledy FROM svet_udalosti WHERE svet_id = ? AND mapa_id IS NOT NULL GROUP BY mapa_id');
$stmt->execute([$svetId]);
$aktivitaMap = array_column($stmt->fetchAll(), 'naposledy', 'mapa_id');
$zony = array_values(array_filter($mapy, fn($m) => $m['typ_mapy'] === 'zona'));
foreach ($zony as &$z) {
    $z['naposledy'] = $aktivitaMap[$z['id']] ?? $z['vytvoreno'];
}
unset($z);
usort($zony, fn($a, $b) => strcmp((string)$b['naposledy'], (string)$a['naposledy']));
$posledniZony = array_slice($zony, 0, 4);

$postavy = dracak_db()->prepare(
    'SELECT p.*, r.nazev AS rasa_nazev, pv.nazev AS povolani_nazev, u.jmeno AS vlastnik_jmeno
     FROM postavy p
     LEFT JOIN rasy r ON r.id = p.rasa_id
     LEFT JOIN povolani pv ON pv.id = p.povolani_id
     JOIN ucty u ON u.id = p.vlastnik_ucet_id
     WHERE p.svet_id = ? ORDER BY p.nazev'
);
$postavy->execute([$svetId]);
$postavy = $postavy->fetchAll();

// Vlastní zatím nepřiřazené postavy (viz hra/postavy_moje.php) — nabídka
// "připojit existující" místo zakládání nové od nuly.
$mojeVolnePostavy = dracak_db()->prepare('SELECT id, nazev FROM postavy WHERE vlastnik_ucet_id = ? AND svet_id IS NULL ORDER BY nazev');
$mojeVolnePostavy->execute([$user['id']]);
$mojeVolnePostavy = $mojeVolnePostavy->fetchAll();

// Inventář za postavu — jen krátký přehled do karty, ne plná správa (ta je
// přes hra/api/pouzij_predmet.php na mapě). Zvlášť dotaz na postavu (max pár
// desítek postav ve světě), ne JOIN přes 3 tabulky najednou.
$inventarePostav = [];
foreach ($postavy as $p) {
    $inventarePostav[(int)$p['id']] = dracak_vtt_inventar('postava', (int)$p['id']);
}

$poznamky = dracak_vtt_poznamky_viditelne(dracak_db(), $user, $svetId);
$moznostiSdileni = dracak_vtt_poznamky_moznosti_sdileni(dracak_db(), $svetId, (int)$user['id']);

$rasyOptions = dracak_db()->query('SELECT id, nazev FROM rasy ORDER BY nazev')->fetchAll();
$povolaniOptions = dracak_db()->query('SELECT id, nazev FROM povolani ORDER BY nazev')->fetchAll();

$stmt = dracak_db()->prepare('SELECT jmeno FROM ucty WHERE id = ?');
$stmt->execute([(int)$svet['pj_ucet_id']]);
$pjJmeno = (string)($stmt->fetchColumn() ?: '—');

// Hráči ve světě — dnes potřeba i pro hráčský pohled (místa u stolu),
// ne jen pro PJ formuláře.
$stmt = dracak_db()->prepare(
    'SELECT u.id, u.jmeno FROM svet_hraci sh JOIN ucty u ON u.id = sh.ucet_id WHERE sh.svet_id = ? ORDER BY u.jmeno'
);
$stmt->execute([$svetId]);
$hraciVeSvete = $stmt->fetchAll();

if ($isPjOrAdmin) {
    $stmt = dracak_db()->prepare(
        "SELECT u.id, u.jmeno FROM ucty u
         WHERE u.role = 'hrac' AND u.id NOT IN (SELECT ucet_id FROM svet_hraci WHERE svet_id = ?)
         ORDER BY u.jmeno"
    );
    $stmt->execute([$svetId]);
    $volniHraci = $stmt->fetchAll();
}

// Místa u stolu: PJ nahoře, pak každá postava hráče (hráč bez postavy =
// prázdná židle), nakonec postavy, které patří PJ/adminovi (NPC apod.).
$postavyPodleVlastnika = [];
foreach ($postavy as $p) {
    $postavyPodleVlastnika[(int)$p['vlastnik_ucet_id']][] = $p;
}
$barvySedadel = ['var(--color-accent-600)', 'var(--color-accent-2-600)', 'var(--color-accent-400)', 'var(--color-neutral-700)', 'var(--color-accent-800)', 'var(--color-accent-2-700)'];
$inicialy = static function (string $jmeno): string {
    $slova = preg_split('/\s+/u', trim($jmeno), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $out = '';
    foreach (array_slice($slova, 0, 2) as $s) {
        $out .= mb_strtoupper(mb_substr($s, 0, 1));
    }
    return $out;
};
$sedadloPostavy = static function (array $p) use ($user, $isPjOrAdmin, $inicialy): array {
    $maxHp = (int)$p['max_hp'];
    $hpPct = $maxHp > 0 ? max(0, min(100, (int)round((int)$p['aktualni_hp'] / $maxHp * 100))) : null;
    $popis = array_filter([$p['rasa_nazev'] ?? null, $p['povolani_nazev'] ?? null]);
    return [
        'jmeno' => $p['nazev'],
        'popis' => $popis ? implode(' · ', $popis) : $p['vlastnik_jmeno'],
        'title' => $p['nazev'] . ' (' . $p['vlastnik_jmeno'] . ') — život ' . (int)$p['aktualni_hp'] . '/' . $maxHp,
        'inicialy' => $inicialy($p['nazev']),
        'hpPct' => $hpPct,
        'odkaz' => ($isPjOrAdmin || (int)$p['vlastnik_ucet_id'] === (int)$user['id']) ? 'postava.php?id=' . (int)$p['id'] : null,
    ];
};
$sedadla = [[
    'jmeno' => $pjJmeno, 'popis' => 'Pán jeskyně', 'title' => 'PJ: ' . $pjJmeno,
    'inicialy' => 'PJ', 'barva' => 'var(--color-neutral-800)', 'hpPct' => null, 'odkaz' => null,
]];
$hracIds = [];
foreach ($hraciVeSvete as $h) {
    $hracIds[(int)$h['id']] = true;
    $jeho = $postavyPodleVlastnika[(int)$h['id']] ?? [];
    if (!$jeho) {
        $sedadla[] = [
            'prazdne' => true, 'jmeno' => $h['jmeno'], 'popis' => 'Zatím bez postavy', 'title' => $h['jmeno'] . ' — zatím bez postavy',
            'odkaz' => (int)$h['id'] === (int)$user['id'] ? 'postava_nova.php?id=' . $svetId : null,
        ];
    }
    foreach ($jeho as $p) {
        $sedadla[] = $sedadloPostavy($p);
    }
}
foreach ($postavy as $p) {
    if (!isset($hracIds[(int)$p['vlastnik_ucet_id']])) {
        $sedadla[] = $sedadloPostavy($p);
    }
}
$pocetSedadel = count($sedadla);
foreach ($sedadla as $i => &$s) {
    $uhel = deg2rad(-90 + $i * (360 / $pocetSedadel));
    $s['left'] = round(50 + 43 * cos($uhel), 2);
    $s['top'] = round(50 + 40 * sin($uhel), 2);
    $s['barva'] ??= $barvySedadel[($i - 1) % count($barvySedadel)];
}
unset($s);

// Reálný čas poslední aktivity na mapě ("Dnes, 14:20", "Před 2 dny") —
// na rozdíl od poznámek, které se počítají v herních dnech.
$kdyText = static function (?string $ts): string {
    if (!$ts) {
        return '—';
    }
    $t = new DateTimeImmutable($ts);
    $dni = (int)(new DateTimeImmutable('today'))->diff($t->setTime(0, 0))->days;
    return match (true) {
        $dni === 0 => 'Dnes, ' . $t->format('H:i'),
        $dni === 1 => 'Včera, ' . $t->format('H:i'),
        $dni < 7 => "Před $dni dny",
        $dni < 14 => 'Před týdnem',
        $dni < 31 => 'Před ' . intdiv($dni, 7) . ' týdny',
        default => $t->format('j. n. Y'),
    };
};

dracak_vtt_page_start($svet['nazev'], $user, true);
?>
  <header class="svet-header">
    <a class="svet-pill" href="../dashboard.php"><?php dracak_icon('home', 16); ?> Domů</a>
    <a class="svet-pill svet-pill-ghost" href="svety.php">← Světy</a>
    <div class="svet-title-wrap">
      <h1 class="svet-title"><?= htmlspecialchars($svet['nazev']) ?></h1>
      <div class="svet-meta">
        <span>PJ: <?= htmlspecialchars($pjJmeno) ?></span>
        <span class="role-badge"><?= $isPjOrAdmin ? 'PJ / Admin' : 'Hráč' ?></span>
      </div>
    </div>
    <?php if ($isPjOrAdmin): ?>
      <a class="svet-pill svet-pill-ghost" href="svet_administrace.php?id=<?= $svetId ?>">⚙ Administrace</a>
    <?php endif; ?>
  </header>

  <main class="svet-main">

    <section class="svet-panel svet-table-card">
      <div style="flex:none;">
        <h2 class="svet-h2">U stolu</h2>
        <p class="svet-sub">Kdo dnes hraje</p>
      </div>
      <div class="svet-table">
        <div class="svet-table-top"></div>
        <?php foreach ($sedadla as $s): $tag = $s['odkaz'] ? 'a' : 'div'; ?>
          <<?= $tag ?> class="svet-seat" style="left:<?= $s['left'] ?>%;top:<?= $s['top'] ?>%;"<?= $s['odkaz'] ? ' href="' . htmlspecialchars($s['odkaz']) . '"' : '' ?> title="<?= htmlspecialchars($s['title']) ?>">
            <?php if (!empty($s['prazdne'])): ?>
              <div class="svet-avatar svet-avatar-empty">+</div>
            <?php else: ?>
              <div class="svet-avatar" style="background:<?= $s['barva'] ?>;"><?= htmlspecialchars($s['inicialy']) ?></div>
            <?php endif; ?>
            <div class="svet-seat-label">
              <div class="svet-seat-name"><?= htmlspecialchars($s['jmeno']) ?></div>
              <div class="svet-seat-sub"><?= htmlspecialchars($s['popis']) ?></div>
            </div>
            <?php if (($s['hpPct'] ?? null) !== null): ?>
              <div class="svet-hp<?= $s['hpPct'] < 35 ? ' low' : '' ?>"><div style="width:<?= $s['hpPct'] ?>%;"></div></div>
            <?php endif; ?>
          </<?= $tag ?>>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="svet-grid">

      <div class="svet-col">
        <?php if ($svetovaMapa): ?>
          <a class="svet-mapcard" href="mapa.php?id=<?= (int)$svetovaMapa['id'] ?>">
            <?php if ($svetovaMapa['obrazek_cesta']): ?>
              <div class="svet-mapcard-img" style="background-image:url('mapa_obrazek.php?id=<?= (int)$svetovaMapa['id'] ?>');"></div>
            <?php else: ?>
              <div class="svet-mapcard-img"><span>Mapa zatím bez obrázku</span></div>
            <?php endif; ?>
            <div class="svet-mapcard-foot">
              <div>
                <div class="svet-mapcard-title"><?= htmlspecialchars($svetovaMapa['nazev']) ?></div>
                <div class="svet-sub" style="font-size:12px;">Světová mapa</div>
              </div>
              <span class="btn btn-primary">Otevřít mapu →</span>
            </div>
          </a>
        <?php else: ?>
          <div class="empty-state" style="padding:40px 20px;">
            Svět zatím nemá světovou mapu.
            <?php if ($isPjOrAdmin): ?><br><button class="btn btn-secondary" type="button" data-dialog="dlg-mapa" style="margin-top:12px;">+ Přidat mapu</button><?php endif; ?>
          </div>
        <?php endif; ?>

        <div>
          <div class="svet-zones-head">
            <h2 class="svet-h2">Naposledy navštívené zóny</h2>
            <?php if (!empty($svet['posledni_shrnuti'])): ?>
              <p class="svet-sub">Posledně: <?= htmlspecialchars($svet['posledni_shrnuti']) ?></p>
            <?php endif; ?>
          </div>
          <?php if (!$posledniZony): ?>
            <p class="svet-note-empty">Zatím žádná zóna.</p>
          <?php else: ?>
            <div class="svet-zones">
              <?php foreach ($posledniZony as $z): ?>
                <a class="svet-zone" href="mapa.php?id=<?= (int)$z['id'] ?>">
                  <div class="svet-zone-thumb"<?= $z['obrazek_cesta'] ? ' style="background-image:url(\'mapa_obrazek.php?id=' . (int)$z['id'] . '\');"' : '' ?>></div>
                  <div class="svet-zone-body">
                    <div class="svet-zone-name"><?= htmlspecialchars($z['nazev']) ?></div>
                    <div class="svet-zone-when">
                      <?= $kdyText($z['naposledy']) ?>
                      <?php if ((int)$z['id'] === (int)$svet['aktivni_mapa_id']): ?><span class="tag tag-accent">aktivní</span><?php endif; ?>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (count($mapy) > count($posledniZony) + ($svetovaMapa ? 1 : 0)): ?>
            <details class="svet-allmaps">
              <summary>Všechny mapy (<?= count($mapy) ?>)</summary>
              <ul>
                <?php foreach ($mapy as $m): ?>
                  <li><a href="mapa.php?id=<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['nazev']) ?></a>
                    <span class="note"><?= $m['typ_mapy'] === 'svet' ? 'světová' : 'zóna' ?><?php if ($m['grid_velikost_px']): ?>, grid <?= $m['grid_typ'] === 'hex' ? 'hex' : 'čtverec' ?> <?= (int)$m['grid_velikost_px'] ?> px<?php endif; ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php endif; ?>
        </div>

        <?php if ($postavy): ?>
        <details class="svet-allmaps">
          <summary>Postavy ve světě (<?= count($postavy) ?>) — atributy a inventář</summary>
          <div class="records-grid" style="margin-top:10px;">
            <?php foreach ($postavy as $p): ?>
              <div class="card elev-sm rec-card">
                <h3 class="rec-title" style="font-size:16px;"><?= htmlspecialchars($p['nazev']) ?></h3>
                <div class="rec-grid">
                  <div class="k">Hráč:</div><div><?= htmlspecialchars($p['vlastnik_jmeno']) ?></div>
                  <div class="k">Rasa/Povolání:</div><div><?= htmlspecialchars(($p['rasa_nazev'] ?? '—') . ' / ' . ($p['povolani_nazev'] ?? '—')) ?></div>
                  <div class="k">Život:</div><div><?= (int)$p['aktualni_hp'] ?> / <?= (int)$p['max_hp'] ?></div>
                  <?php if ($p['sila'] !== null): ?>
                  <div class="k">Atributy:</div><div>
                    S <?= (int)$p['sila'] ?> · Obr <?= (int)$p['obratnost'] ?> · Odl <?= (int)$p['odolnost'] ?> · Int <?= (int)$p['inteligence'] ?> · Cha <?= (int)$p['charisma'] ?>
                  </div>
                  <?php endif; ?>
                </div>
                <?php $inv = $inventarePostav[(int)$p['id']] ?? []; if ($inv): ?>
                  <ul style="margin:0 0 8px;padding-left:18px;font-size:12.5px;">
                    <?php foreach ($inv as $i): ?>
                      <li>
                        <?= htmlspecialchars($i['nazev']) ?>
                        <?php if ($i['typ_polozky'] !== 'kouzlo'): ?>× <?= (int)$i['mnozstvi'] ?><?php endif; ?>
                        <span style="opacity:.6;">(<?= htmlspecialchars($i['typ_polozky']) ?>)</span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <?php if ($isPjOrAdmin || (int)$p['vlastnik_ucet_id'] === (int)$user['id']): ?>
                  <div class="rec-actions"><a class="btn btn-secondary" href="postava.php?id=<?= (int)$p['id'] ?>">Upravit</a></div>
                <?php endif; ?>
                <?php if ($isPjOrAdmin && count($hraciVeSvete) > 1): ?>
                  <form method="post" style="display:flex;gap:6px;margin-top:8px;">
                    <input type="hidden" name="akce" value="prevest_postavu">
                    <input type="hidden" name="postava_id" value="<?= (int)$p['id'] ?>">
                    <select class="input" name="novy_vlastnik_id" style="flex:1;font-size:12px;">
                      <?php foreach ($hraciVeSvete as $h): ?>
                        <option value="<?= (int)$h['id'] ?>" <?= (int)$h['id'] === (int)$p['vlastnik_ucet_id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['jmeno']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-ghost" type="submit" style="font-size:12px;">Přiřadit</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>
      </div>

      <section class="svet-panel svet-notes">
        <h2 class="svet-h2">Poznámky</h2>
        <p class="svet-sub">Komu ji ukážeš, je na tobě — PJ ji vidí jen když mu ji nasdílíš.</p>
        <?php if (!$poznamky): ?>
          <p class="svet-note-empty">Zatím žádná poznámka, kterou bys viděl.</p>
        <?php else: ?>
          <div class="svet-note-list">
            <?php foreach ($poznamky as $p): $jeAutor = (int)$p['autor_ucet_id'] === (int)$user['id'];
              $dniPred = (int)$svet['aktualni_den_offset'] - (int)$p['den_pri_vytvoreni']; ?>
              <div class="svet-note">
                <div class="svet-note-meta">
                  <span><?= htmlspecialchars($p['autor_jmeno']) ?></span><span><?= dracak_vtt_pocet_dni_text($dniPred) ?></span>
                </div>
                <p><?= nl2br(htmlspecialchars($p['text'])) ?></p>
                <?php if ($p['mapa_nazev'] || $jeAutor || $user['role'] === 'admin'): ?>
                <div class="svet-note-foot">
                  <span>
                    <?php if ($p['mapa_nazev']): ?>Místo: <?= htmlspecialchars($p['mapa_nazev']) ?><?php endif; ?>
                    <?php if ($jeAutor || $user['role'] === 'admin'): ?>
                      <?= $p['mapa_nazev'] ? ' · ' : '' ?>Nasdíleno: <?= $p['sdileno_s'] ? htmlspecialchars(implode(', ', $p['sdileno_s'])) : 'nikomu' ?>
                    <?php endif; ?>
                  </span>
                  <?php if ($jeAutor || $user['role'] === 'admin'): ?>
                    <form method="post" onsubmit="return confirm('Smazat tuhle poznámku?');">
                      <input type="hidden" name="akce" value="smazat_poznamku">
                      <input type="hidden" name="poznamka_id" value="<?= (int)$p['id'] ?>">
                      <button class="btn btn-ghost" type="submit">Smazat</button>
                    </form>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="akce" value="pridat_poznamku">
          <textarea name="text" placeholder="Napsat poznámku…" aria-label="Nová poznámka" required></textarea>
          <?php if ($mapy || $moznostiSdileni): ?>
          <details class="svet-note-opts">
            <summary>Místo a sdílení</summary>
            <?php if ($mapy): ?>
            <div class="field"><label for="poznamka_mapa_id">Místo (nepovinné)</label>
              <select class="input" id="poznamka_mapa_id" name="mapa_id">
                <option value="">— bez místa —</option>
                <?php foreach ($mapy as $m): ?>
                  <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['nazev']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($moznostiSdileni): ?>
            <div class="field"><label>Nasdílet komu (nepovinné, lze víc)</label>
              <?php foreach ($moznostiSdileni as $m): ?>
                <label class="chk"><input type="checkbox" name="sdileno[]" value="<?= (int)$m['id'] ?>"> <?= htmlspecialchars($m['jmeno']) ?></label>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </details>
          <?php endif; ?>
          <div class="svet-note-actions"><button class="btn btn-primary" type="submit">Uložit</button></div>
        </form>
      </section>

    </div>
  </main>

  <div class="svet-fab" id="svet-fab">
    <div class="svet-fab-menu">
      <a class="svet-fab-item" href="postava_nova.php?id=<?= $svetId ?>"><?php dracak_icon('user-round-plus', 15); ?> Nová postava</a>
      <?php if ($mojeVolnePostavy): ?>
        <button class="svet-fab-item" type="button" data-dialog="dlg-pripojit"><?php dracak_icon('undo-2', 15); ?> Připojit postavu</button>
      <?php endif; ?>
      <button class="svet-fab-item" type="button" data-dialog="dlg-rychla-postava"><?php dracak_icon('dices', 15); ?> Rychlá postava / NPC</button>
      <?php if ($isPjOrAdmin): ?>
        <button class="svet-fab-item" type="button" data-dialog="dlg-hraci"><?php dracak_icon('user-round-plus', 15); ?> Přidat hráče do světa</button>
        <button class="svet-fab-item" type="button" data-dialog="dlg-mapa"><?php dracak_icon('grid-2x2', 15); ?> Nová mapa / zóna</button>
        <?php if ($postavy): ?>
          <button class="svet-fab-item" type="button" data-dialog="dlg-polozka"><?php dracak_icon('dices', 15); ?> Přidat položku postavě</button>
        <?php endif; ?>
        <button class="svet-fab-item" type="button" data-dialog="dlg-shrnuti"><?php dracak_icon('scroll-text', 15); ?> Upravit shrnutí session</button>
      <?php endif; ?>
    </div>
    <button class="svet-fab-btn" type="button" aria-label="Akce" aria-expanded="false">+</button>
  </div>

  <?php if ($mojeVolnePostavy): ?>
  <dialog class="svet-dialog" id="dlg-pripojit">
    <div class="svet-dialog-head"><h2>Připojit postavu</h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Zavřít">✕</button></div>
    <form method="post">
      <input type="hidden" name="akce" value="pripojit_postavu">
      <div class="field"><label for="pripojit_postava_id">Tvoje už založená postava</label>
        <select class="input" id="pripojit_postava_id" name="postava_id">
          <?php foreach ($mojeVolnePostavy as $pp): ?>
            <option value="<?= (int)$pp['id'] ?>"><?= htmlspecialchars($pp['nazev']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Připojit</button>
    </form>
  </dialog>
  <?php endif; ?>

  <dialog class="svet-dialog" id="dlg-rychla-postava">
    <div class="svet-dialog-head"><h2>Rychlá postava / NPC</h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Zavřít">✕</button></div>
    <p class="note" style="margin-top:0;">Rychlá/nouzová cesta beze hodu. Plné založení podle pravidel (rasa, povolání, hod na atributy) je přes <a href="postava_nova.php?id=<?= $svetId ?>">Nová postava</a>.</p>
    <form method="post">
      <input type="hidden" name="akce" value="nova_postava">
      <div class="field"><label for="p_nazev">Jméno postavy *</label>
        <input class="input" type="text" id="p_nazev" name="nazev" required>
      </div>
      <?php if ($isPjOrAdmin && $hraciVeSvete): ?>
      <div class="field"><label for="vlastnik_ucet_id">Založit pro hráče</label>
        <select id="vlastnik_ucet_id" name="vlastnik_ucet_id">
          <option value="">Sebe (<?= htmlspecialchars($user['jmeno']) ?>)</option>
          <?php foreach ($hraciVeSvete as $h): ?>
            <option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['jmeno']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
        <div class="field"><label for="rasa_id">Rasa</label>
          <select id="rasa_id" name="rasa_id"><option value="">—</option>
            <?php foreach ($rasyOptions as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nazev']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="povolani_id">Povolání</label>
          <select id="povolani_id" name="povolani_id"><option value="">—</option>
            <?php foreach ($povolaniOptions as $pv): ?><option value="<?= (int)$pv['id'] ?>"><?= htmlspecialchars($pv['nazev']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="max_hp">Max. život</label>
          <input class="input" type="number" id="max_hp" name="max_hp" min="0" value="10">
        </div>
      </div>
      <h3 class="rel-label">Atributy (stupeň, nepovinné)</h3>
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;">
        <div class="field"><label for="sila">Síla</label>
          <input class="input" type="number" id="sila" name="sila" min="1" max="30"></div>
        <div class="field"><label for="obratnost">Obr.</label>
          <input class="input" type="number" id="obratnost" name="obratnost" min="1" max="30"></div>
        <div class="field"><label for="odolnost">Odol.</label>
          <input class="input" type="number" id="odolnost" name="odolnost" min="1" max="30"></div>
        <div class="field"><label for="inteligence">Int.</label>
          <input class="input" type="number" id="inteligence" name="inteligence" min="1" max="30"></div>
        <div class="field"><label for="charisma">Char.</label>
          <input class="input" type="number" id="charisma" name="charisma" min="1" max="30"></div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:10px;">Založit postavu</button>
    </form>
  </dialog>

  <?php if ($isPjOrAdmin): ?>
  <dialog class="svet-dialog" id="dlg-hraci">
    <div class="svet-dialog-head"><h2>Hráči ve světě</h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Zavřít">✕</button></div>
    <p style="margin:0 0 10px;font-size:13px;"><?= $hraciVeSvete ? htmlspecialchars(implode(', ', array_column($hraciVeSvete, 'jmeno'))) : 'Zatím žádný hráč nepřidán.' ?></p>
    <?php if ($volniHraci): ?>
    <form method="post" style="display:flex;gap:10px;">
      <input type="hidden" name="akce" value="pridat_hrace">
      <select class="input" name="ucet_id" aria-label="Hráč">
        <?php foreach ($volniHraci as $h): ?>
          <option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['jmeno']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit" style="flex:none;">+ Přidat</button>
    </form>
    <?php else: ?>
      <p class="note">Všichni hráči už jsou ve světě.</p>
    <?php endif; ?>
  </dialog>

  <dialog class="svet-dialog" id="dlg-mapa">
    <div class="svet-dialog-head"><h2>Nová mapa / zóna</h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Zavřít">✕</button></div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="akce" value="nova_mapa">
      <div class="field"><label for="mapa_nazev">Název *</label>
        <input class="input" type="text" id="mapa_nazev" name="nazev" required>
      </div>
      <div class="field"><label for="typ_mapy">Typ</label>
        <select id="typ_mapy" name="typ_mapy">
          <option value="zona">Zóna (bitevní mapa)</option>
          <option value="svet"<?= $svetovaMapa ? '' : ' selected' ?>>Světová mapa</option>
        </select>
      </div>
      <div class="field"><label for="obrazek">Obrázek mapy (PNG/JPG/WEBP)</label>
        <input type="file" id="obrazek" name="obrazek" accept="image/png,image/jpeg,image/webp">
      </div>
      <h3 class="rel-label">Grid (nepovinné)</h3>
      <p class="note" style="margin:-4px 0 10px;">Prázdná velikost = bez gridu.</p>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;">
        <div class="field"><label for="grid_velikost_px">Pole (px)</label>
          <input class="input" type="number" id="grid_velikost_px" name="grid_velikost_px" min="1" step="1" placeholder="např. 70"></div>
        <div class="field"><label for="grid_typ">Typ gridu</label>
          <select id="grid_typ" name="grid_typ">
            <option value="ctverec">Čtverec</option>
            <option value="hex">Hex</option>
          </select></div>
        <div class="field"><label for="grid_posun_x">Posun X</label>
          <input class="input" type="number" id="grid_posun_x" name="grid_posun_x" step="1" value="0"></div>
        <div class="field"><label for="grid_posun_y">Posun Y</label>
          <input class="input" type="number" id="grid_posun_y" name="grid_posun_y" step="1" value="0"></div>
      </div>
      <button class="btn btn-primary" type="submit">Přidat mapu</button>
    </form>
  </dialog>

  <?php if ($postavy): ?>
  <dialog class="svet-dialog" id="dlg-polozka">
    <div class="svet-dialog-head"><h2>Přidat položku postavě</h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Zavřít">✕</button></div>
    <form method="post">
      <input type="hidden" name="akce" value="pridat_polozku">
      <p class="note" style="margin-top:0;">
        ID najdeš v katalogu (<a href="../editor.php?tabulka=predmety" target="_blank">předměty</a>,
        <a href="../editor.php?tabulka=lektvary" target="_blank">lektvary</a>,
        <a href="../editor.php?tabulka=kouzla" target="_blank">kouzla</a>) — u záznamu klikni na "Upravit", ID je v URL.
      </p>
      <div class="field"><label for="polozka_postava_id">Postava *</label>
        <select class="input" id="polozka_postava_id" name="postava_id" required>
          <?php foreach ($postavy as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nazev']) ?> (<?= htmlspecialchars($p['vlastnik_jmeno']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
        <div class="field"><label for="typ_polozky">Typ *</label>
          <select class="input" id="typ_polozky" name="typ_polozky" required>
            <option value="predmet">Předmět</option>
            <option value="lektvar">Lektvar</option>
            <option value="kouzlo">Kouzlo</option>
          </select>
        </div>
        <div class="field"><label for="polozka_id">ID v katalogu *</label>
          <input class="input" type="number" id="polozka_id" name="polozka_id" min="1" required>
        </div>
        <div class="field"><label for="polozka_mnozstvi">Množství</label>
          <input class="input" type="number" id="polozka_mnozstvi" name="mnozstvi" min="1" value="1">
        </div>
      </div>
      <p class="note" style="margin:0 0 10px;">U kouzla se množství ignoruje — znalost kouzla se nepočítá na kusy.</p>
      <button class="btn btn-primary" type="submit">Přidat</button>
    </form>
  </dialog>
  <?php endif; ?>

  <dialog class="svet-dialog" id="dlg-shrnuti">
    <div class="svet-dialog-head"><h2>Shrnutí session</h2><button class="btn btn-ghost btn-icon" type="button" data-close aria-label="Zavřít">✕</button></div>
    <form method="post">
      <input type="hidden" name="akce" value="shrnuti">
      <div class="field"><label for="posledni_shrnuti">Kde jsme skončili (vidí všichni)</label>
        <textarea id="posledni_shrnuti" name="posledni_shrnuti"><?= htmlspecialchars((string)($svet['posledni_shrnuti'] ?? '')) ?></textarea>
      </div>
      <div class="field"><label for="pripraveno_priste">Co bude příště (jen PJ/admin vidí)</label>
        <textarea id="pripraveno_priste" name="pripraveno_priste"><?= htmlspecialchars((string)($svet['pripraveno_priste'] ?? '')) ?></textarea>
      </div>
      <button class="btn btn-primary" type="submit">Uložit</button>
    </form>
  </dialog>
  <?php endif; ?>

  <script>
  (function () {
    var fab = document.getElementById('svet-fab');
    var fabBtn = fab.querySelector('.svet-fab-btn');
    function setOpen(open) {
      fab.classList.toggle('open', open);
      fabBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    fabBtn.addEventListener('click', function () { setOpen(!fab.classList.contains('open')); });
    document.addEventListener('click', function (e) {
      if (!fab.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setOpen(false);
    });
    document.querySelectorAll('[data-dialog]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var dlg = document.getElementById(btn.getAttribute('data-dialog'));
        if (!dlg) return;
        setOpen(false);
        dlg.showModal();
      });
    });
    document.querySelectorAll('.svet-dialog').forEach(function (dlg) {
      dlg.querySelectorAll('[data-close]').forEach(function (b) {
        b.addEventListener('click', function () { dlg.close(); });
      });
      // Klik na backdrop (mimo obsah dialogu) zavře.
      dlg.addEventListener('click', function (e) {
        if (e.target === dlg) dlg.close();
      });
    });
  })();
  </script>
<?php dracak_vtt_page_end(); ?>
