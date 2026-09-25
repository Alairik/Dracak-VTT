<?php
declare(strict_types=1);

// Registr entit pro generický editor. Každý klíč = název tabulky v DB.
// 'quick' u pole = patří do rychlé šablony (vidět hned); ostatní pole
// jsou schovaná pod "Zobrazit všechna pole", ale ukládají se ve stejném
// formuláři/tabulce — žádná druhá entita navíc.
//
// M:N vazby (obory_magie u kouzla, efekty u schopnosti apod.) se v tomhle
// v1 editoru NEEDITUJÍ přes UI — na to je zatím potřeba phpMyAdmin.
// Skalární pole (vlastní sloupce tabulky) editovat jdou.

// Sdílená 4 pole pro entity, co dávají zranění/léčení kostkou (kouzlo,
// schopnost, lektvar, finta) — řeší "2k6+2" i "modré blesky: lze seslat
// vícekrát, každé seslání přidá dalších 1k6". Sloupce zavádí migrace
// database/0003_kostky_a_bonusy.sql.
function dracak_kostky_pole(): array
{
    $typyKostek = ['k3', 'k4', 'k6', 'k8', 'k10', 'k12', 'k20', 'k100'];
    return [
        ['name' => 'pocet_kostek', 'label' => 'Počet kostek', 'type' => 'number', 'quick' => true],
        ['name' => 'typ_kostky', 'label' => 'Typ kostky', 'type' => 'select', 'options' => $typyKostek, 'quick' => true],
        ['name' => 'pevny_bonus', 'label' => 'Pevný bonus (např. +2 u "2k6+2")', 'type' => 'number', 'quick' => true],
        ['name' => 'vicenasobne', 'label' => 'Lze provést/seslat vícekrát (škáluje počet kostek, např. modré blesky)', 'type' => 'checkbox', 'quick' => true],
        ['name' => 'max_pouziti', 'label' => 'Max. počet použití (prázdné = bez limitu, např. "3x denně")', 'type' => 'text', 'quick' => true],
    ];
}

return [

    // --- OBSAH: kouzlo/příšera/dovednost/lektvar/vybavení, editovatelné hráčem dle práv ---

    'kouzla' => [
        'label' => 'Kouzla',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'nazev',
        // Řádek štítků v seznamu, ve stylu "SPECIFIKACE KOUZEL" z pravidel
        // (Mana/Dosah/Rozsah/Trvání pohromadě, ne zahrabané v odstavci).
        'summary_fields' => ['uroven_kouzla' => 'Lv.', 'cena_magenergie' => 'Mana', 'cena_dalsi_seslani' => '+ mana/další', 'dosah' => 'Dosah', 'rozsah' => 'Rozsah', 'doba_trvani' => 'Trvání'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'uroven_kouzla', 'label' => 'Úroveň kouzla', 'type' => 'number', 'quick' => true],
            ['name' => 'cena_magenergie', 'label' => 'Cena magenergie (první/jediné seslání)', 'type' => 'text', 'quick' => true],
            ['name' => 'cena_dalsi_seslani', 'label' => 'Cena za každé další seslání (jen u vícenásobných, pokud se liší)', 'type' => 'text', 'quick' => true],
            ...dracak_kostky_pole(),
            ['name' => 'popis', 'label' => 'Popis / efekt', 'type' => 'textarea', 'quick' => true],
            ['name' => 'seznam_kouzel_id', 'label' => 'Seznam kouzel', 'type' => 'select_fk', 'ref_table' => 'seznamy_kouzel', 'ref_label' => 'nazev'],
            ['name' => 'dosah', 'label' => 'Dosah (vzdálenost)', 'type' => 'text'],
            ['name' => 'rozsah', 'label' => 'Rozsah (počet cílů / oblast)', 'type' => 'text'],
            ['name' => 'doba_seslani', 'label' => 'Doba seslání', 'type' => 'text'],
            ['name' => 'doba_trvani', 'label' => 'Doba trvání', 'type' => 'text'],
            ['name' => 'typ_unavy', 'label' => 'Typ únavy', 'type' => 'select', 'options' => ['vycerpavajici', 'nevycerpavajici', 'udrzovaci', 'zaostreni_vule']],
            ['name' => 'past_id', 'label' => 'Past (záchranný hod)', 'type' => 'select_fk', 'ref_table' => 'pasti', 'ref_label' => 'vlastnosti'],
        ],
    ],

    'zvlastni_schopnosti' => [
        'label' => 'Schopnosti a dovednosti',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'nazev',
        'summary_fields' => ['druh' => 'Druh', 'uroven_od' => 'Od Lv.'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'druh', 'label' => 'Druh', 'type' => 'select', 'options' => ['schopnost', 'dovednost'], 'quick' => true],
            ['name' => 'uroven_od', 'label' => 'Od úrovně', 'type' => 'number', 'quick' => true],
            ...dracak_kostky_pole(),
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'mechanika', 'label' => 'Mechanika (poznámka navíc, cíl efektu...)', 'type' => 'text'],
            ['name' => 'vyzaduje_id', 'label' => 'Vyžaduje (prerekvizita)', 'type' => 'select_fk', 'ref_table' => 'zvlastni_schopnosti', 'ref_label' => 'nazev'],
            ['name' => 'past_id', 'label' => 'Past (záchranný hod)', 'type' => 'select_fk', 'ref_table' => 'pasti', 'ref_label' => 'vlastnosti'],
        ],
    ],

    'predmety' => [
        'label' => 'Vybavení',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'nazev',
        'summary_fields' => ['typ' => 'Typ', 'uc' => 'ÚČ', 'utocnost' => 'Útočnost', 'oc' => 'OČ'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'options' => ['zbran', 'zbroj', 'surovina', 'artefakt'], 'quick' => true],
            ['name' => 'uc', 'label' => 'ÚČ', 'type' => 'number', 'quick' => true],
            ['name' => 'utocnost', 'label' => 'Útočnost', 'type' => 'number', 'quick' => true],
            ['name' => 'oc', 'label' => 'OČ', 'type' => 'number', 'quick' => true],
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'kategorie_zbrane', 'label' => 'Kategorie zbraně (sečná/bodná/tupá)', 'type' => 'text'],
            ['name' => 'typ_pro_vyrazeni_dveri', 'label' => 'Typ pro vyražení dveří', 'type' => 'select', 'options' => ['ostre_bodne', 'ostre_drtive', 'tupe_drtive']],
            ['name' => 'vaha', 'label' => 'Váha', 'type' => 'number'],
            ['name' => 'cena', 'label' => 'Cena', 'type' => 'number'],
        ],
    ],

    'lektvary' => [
        'label' => 'Lektvary a elixíry',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'nazev',
        'summary_fields' => ['cena' => 'Cena'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ...dracak_kostky_pole(),
            ['name' => 'popis', 'label' => 'Popis / efekt', 'type' => 'textarea', 'quick' => true],
            ['name' => 'cena', 'label' => 'Cena', 'type' => 'number', 'quick' => true],
            ['name' => 'doba_pripravy', 'label' => 'Doba přípravy', 'type' => 'text'],
            ['name' => 'vyrobce_povolani_id', 'label' => 'Vyrábí povolání', 'type' => 'select_fk', 'ref_table' => 'povolani', 'ref_label' => 'nazev'],
            ['name' => 'past_id', 'label' => 'Past (záchranný hod)', 'type' => 'select_fk', 'ref_table' => 'pasti', 'ref_label' => 'vlastnosti'],
        ],
    ],

    'finty' => [
        'label' => 'Finty',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'nazev',
        'summary_fields' => ['typ' => 'Typ', 'pocet_akci' => 'Počet akcí'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'options' => ['utocna', 'obranna', 'kombinovana'], 'quick' => true],
            ...dracak_kostky_pole(),
            ['name' => 'poznamky', 'label' => 'Poznámky', 'type' => 'textarea', 'quick' => true],
            ['name' => 'bonus_iniciativa', 'label' => 'Bonus k iniciativě', 'type' => 'number'],
            ['name' => 'pocet_akci', 'label' => 'Počet akcí', 'type' => 'number'],
            ['name' => 'pozadovana_zbran', 'label' => 'Požadovaná zbraň', 'type' => 'text'],
            ['name' => 'pozadovany_protivnik', 'label' => 'Požadovaný protivník', 'type' => 'text'],
        ],
    ],

    'povolani' => [
        'label' => 'Povolání',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'nazev',
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'options' => ['zakladni', 'vetev'], 'quick' => true],
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'rodic_povolani_id', 'label' => 'Rodičovské povolání (větev od)', 'type' => 'select_fk', 'ref_table' => 'povolani', 'ref_label' => 'nazev'],
            ['name' => 'odemyka_se_od_urovne', 'label' => 'Odemyká se od úrovně', 'type' => 'number'],
            ['name' => 'pouziva_magenergii', 'label' => 'Používá magenergii', 'type' => 'checkbox'],
            ['name' => 'primarni_vlastnost_id', 'label' => 'Primární vlastnost', 'type' => 'select_fk', 'ref_table' => 'vlastnosti', 'ref_label' => 'nazev'],
            ['name' => 'sum_zaklad', 'label' => 'SUM základ', 'type' => 'number'],
            ['name' => 'som_zaklad', 'label' => 'SOM základ', 'type' => 'number'],
            ['name' => 'zsm_zaklad', 'label' => 'ZSM základ', 'type' => 'number'],
        ],
    ],

    'rasy' => [
        'label' => 'Rasy',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'nazev',
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'rodic_rasa_id', 'label' => 'Rodičovská rasa (klan/varianta od)', 'type' => 'select_fk', 'ref_table' => 'rasy', 'ref_label' => 'nazev'],
        ],
    ],

    'efekty' => [
        'label' => 'Efekty (buff/debuff...)',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'nazev',
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'options' => ['buff', 'debuff', 'dot', 'hot', 'modifikator', 'imunita', 'zranitelnost', 'odolnost'], 'quick' => true],
            ['name' => 'hodnota_vzorec', 'label' => 'Hodnota / vzorec', 'type' => 'text', 'quick' => true],
            ['name' => 'cil', 'label' => 'Cíl (životy/OČ/ÚČ...)', 'type' => 'text'],
            ['name' => 'mechanika_aplikace', 'label' => 'Kdy se aplikuje', 'type' => 'select', 'options' => ['jednorazove', 'na_zacatku_kola', 'na_konci_kola', 'pri_zasahu']],
            ['name' => 'trvani', 'label' => 'Trvání', 'type' => 'text'],
            ['name' => 'tooltip_text', 'label' => 'Tooltip text (mouseover v boji)', 'type' => 'textarea'],
        ],
    ],

    'pasti' => [
        'label' => 'Pasti (záchranné hody)',
        'group' => 'obsah',
        'hidden_from_players' => false,
        'order_by' => 'id',
        'fields' => [
            ['name' => 'vlastnosti', 'label' => 'Vlastnosti (např. "Odl" nebo "Sil+Odl")', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'obtiznost', 'label' => 'Obtížnost/nebezpečnost', 'type' => 'text', 'quick' => true],
            ['name' => 'poznamka', 'label' => 'Poznámka', 'type' => 'textarea', 'quick' => true],
            ['name' => 'efekt_uspech_id', 'label' => 'Efekt při úspěchu', 'type' => 'select_fk', 'ref_table' => 'efekty', 'ref_label' => 'nazev'],
            ['name' => 'efekt_neuspech_id', 'label' => 'Efekt při neúspěchu', 'type' => 'select_fk', 'ref_table' => 'efekty', 'ref_label' => 'nazev'],
        ],
    ],

    // --- BESTIÁŘ A PJ OBSAH: vždy skryté hráčům, editovatelné jen PJ/adminem ---

    'nestvury' => [
        'label' => 'Příšery (bestiář)',
        'group' => 'bestiar',
        'hidden_from_players' => true,
        'order_by' => 'nazev',
        'summary_fields' => ['velikost' => 'Velikost', 'zivotaschopnost' => 'Živ.', 'uc' => 'ÚČ', 'oc' => 'OČ'],
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'zivotaschopnost', 'label' => 'Životaschopnost', 'type' => 'text', 'quick' => true],
            ['name' => 'uc', 'label' => 'ÚČ', 'type' => 'text', 'quick' => true],
            ['name' => 'oc', 'label' => 'OČ', 'type' => 'text', 'quick' => true],
            ['name' => 'popis', 'label' => 'Popis', 'type' => 'textarea', 'quick' => true],
            ['name' => 'odolnost', 'label' => 'Odolnost', 'type' => 'text'],
            ['name' => 'velikost', 'label' => 'Velikost', 'type' => 'text'],
            ['name' => 'bojovnost', 'label' => 'Bojovnost', 'type' => 'text'],
            ['name' => 'pohyblivost', 'label' => 'Pohyblivost', 'type' => 'text'],
            ['name' => 'vytrvalost', 'label' => 'Vytrvalost', 'type' => 'text'],
            ['name' => 'inteligence', 'label' => 'Inteligence', 'type' => 'text'],
            ['name' => 'charisma', 'label' => 'Charisma', 'type' => 'text'],
            ['name' => 'poklady', 'label' => 'Poklady', 'type' => 'text'],
            ['name' => 'zkusenost', 'label' => 'Zkušenost', 'type' => 'number'],
            ['name' => 'ochoceni', 'label' => 'Ochočení', 'type' => 'text'],
            ['name' => 'prostredi', 'label' => 'Prostředí', 'type' => 'text'],
        ],
    ],

    'pj_poznamky' => [
        'label' => 'PJ pravidla a poznámky',
        'group' => 'bestiar',
        'hidden_from_players' => true,
        'order_by' => 'nazev',
        'fields' => [
            ['name' => 'nazev', 'label' => 'Název', 'type' => 'text', 'required' => true, 'quick' => true],
            ['name' => 'obsah', 'label' => 'Obsah', 'type' => 'textarea', 'quick' => true],
        ],
    ],

    // --- ČÍSELNÍKY: systémová referenční data, jen admin/PJ, generický formulář ---

    'vlastnosti' => ['label' => 'Vlastnosti', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'id'],
    'vysledky_testu' => ['label' => 'Výsledky testu (škála)', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'poradi'],
    // 'velikosti' vynechané záměrně — primární klíč je `kod`, ne `id`,
    // generický editor v1 počítá jen s `id`. Uprav přes phpMyAdmin.
    'obory_magie' => ['label' => 'Obory magie', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'kod'],
    'skupiny_kouzel' => ['label' => 'Skupiny kouzel', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
    'kody_zranitelnosti' => ['label' => 'Kódy zranitelnosti', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'kod'],
    'jazyky' => ['label' => 'Jazyky', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
    'presvedceni' => ['label' => 'Přesvědčení', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
    'zkusenostni_tabulky' => ['label' => 'Zkušenostní tabulky', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
    'seznamy_kouzel' => ['label' => 'Seznamy kouzel', 'group' => 'ciselniky', 'hidden_from_players' => false, 'order_by' => 'nazev'],
];
