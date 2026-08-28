#!/usr/bin/env python3
"""styles.css'te tanımsız CSS değişkeni var mı?

Tanımsız bir değişken, yedek değer verilmediğinde özelliği geçersiz kılıyor:
kural sessizce hiç uygulanmıyor. Bozuk görünmediği için gözden kaçıyor.

Yedekli kullanımlar (`var(--x, #fff)`) ayrı raporlanıyor: onlar çalışıyor ama
değişken tanımlı değilse hep yedeğe düşüyor demektir.
"""

import re
import sys
from pathlib import Path

yol = Path(sys.argv[1] if len(sys.argv) > 1 else 'public/assets/admin/css/styles.css')
metin = yol.read_text()

# --- Tanımlar: "--ad:" biçimi (bildirim tarafı) ---
# var(--ad) kullanımını yakalamamak için başında "var(" olmayanlar alınıyor.
tanimlar = set()
for m in re.finditer(r'(^|[;{\s])(--[A-Za-z0-9_-]+)\s*:', metin, re.MULTILINE):
    tanimlar.add(m.group(2))

# --- Kullanımlar: var(--ad) ve var(--ad, yedek) ---
yedeksiz = {}   # ad -> satır numaraları
yedekli = {}

for m in re.finditer(r'var\(\s*(--[A-Za-z0-9_-]+)\s*(,)?', metin):
    ad = m.group(1)
    yedek_var = m.group(2) is not None
    satir = metin.count('\n', 0, m.start()) + 1
    hedef = yedekli if yedek_var else yedeksiz
    hedef.setdefault(ad, []).append(satir)

print(f'Dosya: {yol}')
print(f'Tanımlı değişken: {len(tanimlar)}')
print(f'Kullanılan değişken: {len(set(yedeksiz) | set(yedekli))}')
print()

eksik = {ad: satirlar for ad, satirlar in yedeksiz.items() if ad not in tanimlar}

if eksik:
    print('!! TANIMSIZ ve YEDEKSİZ (kural hiç uygulanmıyor):')
    for ad in sorted(eksik):
        satirlar = eksik[ad]
        gosterilen = ', '.join(str(s) for s in satirlar[:12])
        artan = f' … (+{len(satirlar) - 12})' if len(satirlar) > 12 else ''
        print(f'  {ad}  → {len(satirlar)} yerde: satır {gosterilen}{artan}')
else:
    print('✓ Tanımsız ve yedeksiz değişken yok.')

print()

eksik_yedekli = {ad: s for ad, s in yedekli.items() if ad not in tanimlar}

if eksik_yedekli:
    print('~ TANIMSIZ ama YEDEKLİ (çalışıyor, hep yedeğe düşüyor):')
    for ad in sorted(eksik_yedekli):
        satirlar = eksik_yedekli[ad]
        print(f'  {ad}  → {len(satirlar)} yerde: satır {", ".join(str(x) for x in satirlar[:12])}')
else:
    print('✓ Tanımsız-yedekli değişken yok.')

print()

kullanilmayan = sorted(t for t in tanimlar if t not in yedeksiz and t not in yedekli)
print(f'Tanımlı ama hiç kullanılmayan: {len(kullanilmayan)}')
if kullanilmayan:
    print('  ' + ', '.join(kullanilmayan))

sys.exit(1 if eksik else 0)
