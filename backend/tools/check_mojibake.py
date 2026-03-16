from pathlib import Path

ROOTS = ['app', 'database', 'resources', 'lang', 'routes']
EXTS = {'.php', '.blade.php'}
SUSPICIOUS = set('\u0402\u0403\u0408\u0409\u040A\u040B\u040C\u040E\u0452\u0453\u0458\u0459\u045A\u045B\u045C\u045E\u201A\u201E\u2020\u2021\u20AC\u2122')

found = False
for root in ROOTS:
    for path in Path(root).rglob('*'):
        if path.suffix not in EXTS:
            continue
        text = path.read_text(encoding='utf-8')
        bad = sorted({ch for ch in text if ch in SUSPICIOUS})
        if not bad:
            continue
        found = True
        escaped = ''.join(f'\\u{ord(ch):04X}' for ch in bad)
        print(f'{path}: {escaped}')

raise SystemExit(1 if found else 0)
