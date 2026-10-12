-- Piny dostávají hloubku (viz konverzace): dosud jen svět → pin →
-- jedna zónová mapa, teď i zóna → pin → další zóna (budova, místnost,
-- sklep...), libovolně hluboko. Datově to nevyžaduje nic nového —
-- mapa_body.mapa_id už dnes ukazuje na libovolnou mapu, omezení "jen
-- na typ_mapy='svet'" bylo čistě v hra/mapa.php (viz ta samá komituje
-- co rozšiřuje tuhle migraci).
--
-- Jediné, co fakticky chybí, jsou typy pro hlubší úrovně — dosud jen
-- mesto/vesnice/tajne_misto/jine (myšlené pro piny NA světové mapě).
-- Přidáno: budova, mistnost, sklep, prechod.
--
-- Záměrně beze změny: žádný UNIQUE na cilova_mapa_id. Jedna mapa (např.
-- sklepení) smí mít klidně dva piny, co na ni vedou (dva vchody) —
-- žádný strom s jedním rodičem, ale obecný graf. Breadcrumb/strom v
-- UI proto půjde podle toho, kterým pinem se tam hráč reálně dostal,
-- ne podle jediného odvoditelného rodiče z dat.
ALTER TABLE mapa_body
    MODIFY COLUMN typ ENUM('mesto','vesnice','tajne_misto','budova','mistnost','sklep','prechod','jine') NOT NULL DEFAULT 'jine';
