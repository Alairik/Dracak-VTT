-- 0083: souřadnice tokenu (tokeny.x, tokeny.y) jsou od teď STŘED tokenu.
--
-- Dřív to byl levý horní roh pevného 40px čtverečku (střed = x + 20, y + 20).
-- Token se teď přizpůsobuje velikosti gridu a otáčí se, takže levý horní roh
-- už nedává smysl; střed je stejný způsob zápisu jako u zdí, pinů a pingu.
-- Jednorázový posun stávajících tokenů o 20 px, ať zůstanou vizuálně na místě.
-- (Spustí se jen jednou díky migrace_log.)
UPDATE tokeny SET x = x + 20, y = y + 20;
