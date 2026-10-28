#!/usr/bin/env python3
"""Jednobarevné PNG podklady pro testovací mapy (bez knihoven, jen zlib). Použití: make-images.py <složka uploads/mapy>."""
import os
import struct
import sys
import zlib


def png(sirka, vyska, rgb):
    radek = b"\x00" + bytes(rgb) * sirka
    data = zlib.compress(radek * vyska, 9)

    def blok(typ, obsah):
        return struct.pack(">I", len(obsah)) + typ + obsah + struct.pack(">I", zlib.crc32(typ + obsah) & 0xFFFFFFFF)

    return b"\x89PNG\r\n\x1a\n" + blok(b"IHDR", struct.pack(">IIBBBBB", sirka, vyska, 8, 2, 0, 0, 0)) + blok(b"IDAT", data) + blok(b"IEND", b"")


slozka = sys.argv[1]
os.makedirs(slozka, exist_ok=True)
for nazev, (sirka, vyska, barva) in {
    "testmapa_ctverec.png": (2400, 1800, (96, 122, 88)),
    "testmapa_hex.png": (3200, 2200, (84, 90, 118)),
}.items():
    cesta = os.path.join(slozka, nazev)
    if not os.path.exists(cesta):
        with open(cesta, "wb") as f:
            f.write(png(sirka, vyska, barva))
