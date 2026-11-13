#!/usr/bin/env python3
"""Génère assets/css/admin.min.css depuis la source lisible assets/css/admin.css.

La source reste dans le dépôt et dans le zip (code lisible exigé par WordPress.org) ;
le plugin charge admin.min.css, ou admin.css quand SCRIPT_DEBUG est vrai.
Budget : 6 Ko (6 144 octets) au maximum.
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'assets', 'css', 'admin.css')
DST = os.path.join(ROOT, 'assets', 'css', 'admin.min.css')
BUDGET = 6144

css = open(SRC, encoding='utf-8').read()
css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
css = re.sub(r'\s+', ' ', css)
css = re.sub(r'\s*([{};:,>])\s*', r'\1', css).replace(';}', '}').strip()
with open(DST, 'w', encoding='utf-8') as f:
    f.write(css + '\n')
size = os.path.getsize(DST)
print('admin.min.css: %d octets (source %d)' % (size, os.path.getsize(SRC)))
sys.exit(0 if size <= BUDGET else 1)
