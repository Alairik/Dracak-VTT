<?php
declare(strict_types=1);

// VTT — rozšířený (hex) soubojový systém: hod na iniciativu, počet akcí a
// pořadí tahů (content/pravidla-hrac.html, h1619-h1625 — "Rozšířený
// soubojový systém", str. 77-78). Základní systém (h1619) iniciativu
// nepočítá takhle (jen "kdo hodí víc na 1k6, je první"), tenhle soubor
// je čistě pro rozšířený systém a tabulky kolo_stav/kolo_iniciativa
// (database/migrations/0044_vtt_iniciativa.sql).
//
// Obecná Tabulka bonusů a postihů k iniciativě (str. 78) NENÍ v žádném
// zdroji přepsaná — jen nečitelný obrázek v předloze, viz
// docs/kontrolni-seznam-neuplnych-mist.md. Dokud se nenajde lepší sken,
// modifikátor se zadává ručně (viz hra/api/iniciativa_hod.php) — tenhle
// soubor NEHÁDÁ žádná čísla za tuhle chybějící tabulku.
require_once __DIR__ . '/vtt.php';

// TABULKA INICIATIVY A AKCÍ, h1623, str. 77 — přepsáno doslova ze zdroje:
//   výsledek 0 a méně -> 0 akcí
//   výsledek 1–6      -> 2 akce
//   výsledek 7–12     -> 4 akce
//   výsledek 13–18    -> 6 akcí
//   výsledek 19–24    -> 8 akcí
//   výsledek 25–30    -> 10 akcí
// (Pravidla u výsledku 0 a méně navíc říkají "snižuje postih v dalším
// kole" — carry-over postihu mezi koly se v týhle dávce netrackuje,
// stejně jako zbytek "Tabulky bonusů a postihů k iniciativě" na str. 78,
// viz komentář výš. Tady se řeší jen počet akcí pro AKTUÁLNÍ kolo.)
//
// Vzorec akce = 2 * ceil(vysledek / 6) je čistě aritmetické zobecnění
// těch šesti řádků výše (ne vymyšlené číslo) — pro vysledek nad 30
// (možné při vysokém ručním modifikátoru, viz výš) pokračuje stejný krok
// "+2 akce na každých +6 výsledku", protože žádná jiná hodnota není v
// pravidlech ani naznačená a tabulka výslovně nikde neříká, že by nad 30
// mělo dojít k nějakému stropu.
function dracak_vtt_iniciativa_akce(int $vysledek): int
{
    if ($vysledek <= 0) {
        return 0;
    }
    return 2 * (int)ceil($vysledek / 6);
}

// Kdo smí hodit iniciativu za daný token — stejný tvar oprávnění jako
// "kdo smí hýbat tokenem" (dracak_vtt_can_move_token, includes/vtt.php),
// jen čteno, ne upravováno (viz zadání téhle dávky): PJ/admin za
// kohokoliv, hráč jen za svou vlastní postavu.
function dracak_vtt_can_roll_iniciativa(array $user, array $token): bool
{
    return dracak_vtt_can_move_token($user, $token);
}

// Sestaví pořadí účastníků AKTUÁLNÍHO kola na mapě, sestupně podle
// vysledek. Remíza: h1622 — "Pokud dvěma postavám padne na iniciativu
// stejně, je dřív na řadě postava s větší obratností, nebo při stejné
// obratnosti rozhodne PJ." Obratnost má jen postavy (postavy.obratnost,
// migrace 0040) — nestvura_instance ji nemá, takže se při shodě
// vysledek řadí až za postavy se stejným výsledkem (obratnost = -1,
// nižší než jakákoliv reálná hodnota 1+). Finální remízu (dvě nestvůry,
// nebo stejná obratnost) řeší stabilní řazení podle token_id — je to jen
// ZOBRAZOVACÍ pořadí bez enforcementu (viz zadání), "rozhodne PJ" z
// pravidel se tu nedá automatizovat, PJ pořadí vidí a řídí ho ručně přes
// dalsi_tah.php.
function dracak_vtt_iniciativa_poradi(PDO $pdo, int $mapaId): array
{
    $stmt = $pdo->prepare(
        'SELECT ki.token_id, ki.hod, ki.modifikator, ki.vysledek, ki.akce_celkem, ki.akce_zbyvajici,
                t.typ_entity, t.entita_id,
                p.nazev AS postava_nazev, p.obratnost,
                ni.nazev_instance
         FROM kolo_iniciativa ki
         JOIN tokeny t ON t.id = ki.token_id
         LEFT JOIN postavy p ON p.id = t.entita_id AND t.typ_entity = "postava"
         LEFT JOIN nestvura_instance ni ON ni.id = t.entita_id AND t.typ_entity = "nestvura_instance"
         WHERE ki.mapa_id = ?'
    );
    $stmt->execute([$mapaId]);
    $radky = $stmt->fetchAll();
    foreach ($radky as &$r) {
        $r['token_id'] = (int)$r['token_id'];
        $r['hod'] = (int)$r['hod'];
        $r['modifikator'] = (int)$r['modifikator'];
        $r['vysledek'] = (int)$r['vysledek'];
        $r['akce_celkem'] = (int)$r['akce_celkem'];
        $r['akce_zbyvajici'] = (int)$r['akce_zbyvajici'];
        $r['label'] = $r['typ_entity'] === 'postava' ? $r['postava_nazev'] : $r['nazev_instance'];
        $r['obratnost'] = $r['obratnost'] !== null ? (int)$r['obratnost'] : -1;
    }
    unset($r);
    usort($radky, function (array $a, array $b): int {
        return $b['vysledek'] <=> $a['vysledek']
            ?: $b['obratnost'] <=> $a['obratnost']
            ?: $a['token_id'] <=> $b['token_id'];
    });
    return $radky;
}
