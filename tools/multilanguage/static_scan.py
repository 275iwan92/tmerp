"""Static scan: lines of v2 code that still carry Indonesian display text (outside comments).
Usage: python static_scan.py [path-prefix-filter]   -> prints file:line: flagged words | line
"""
import os, re, sys

here = os.path.dirname(os.path.abspath(__file__))
base = 'C:/Users/iwanp/Herd/tmerp'
vocab = set(open(f'{here}/probe/vocab_id.txt', encoding='utf-8').read().split())
extra = open(f'{here}/vocab_extra.txt', encoding='utf-8').read().split() if os.path.exists(f'{here}/vocab_extra.txt') else []
vocab |= set(extra)
# words that are also normal English / technical tokens / identifiers -> not evidence of Indonesian
STOP = set('''pak plat mis hpp per data status total item master stok set unit kode detail list info via format tab bin lot rim rol
gram kali besar sel seri kas bank log model dua tim sub non pos air die pin top bar pro baca jam mm cm kg pcs
api dom url min max sum id en no ok up of or to in on at is it by an as be do go so we us
pasang takaran'''.split())
vocab -= STOP
roots = sys.argv[2:] or ['resources/views/v2', 'app/Support/V2', 'routes/v2.php', 'packages/devlib/src', 'packages/devlib/resources/views', 'packages/devlib/resources/assets/js',
                          'packages/mockup-parts/src', 'packages/mockup-parts/resources', 'packages/chat/src', 'packages/chat/resources', 'packages/assistant/src', 'packages/assistant/resources']
flt = sys.argv[1] if len(sys.argv) > 1 and sys.argv[1] != '-' else ''


def strip_comments(src, ext):
    if ext == '.php':
        src = re.sub(r'\{\{--.*?--\}\}', lambda m: '\n' * m.group(0).count('\n'), src, flags=re.S)
    src = re.sub(r'/\*.*?\*/', lambda m: '\n' * m.group(0).count('\n'), src, flags=re.S)
    out = []
    for ln in src.split('\n'):
        # line comments: // ... (not inside a URL / string heuristically: require start or whitespace before)
        ln = re.sub(r'(^|\s)//(?!/).*$', r'\1', ln)
        ln = re.sub(r'^\s*#(?!\[).*$', '', ln)
        out.append(ln)
    return out


def texts(ln):
    found = re.findall(r"'((?:[^'\\]|\\.)*)'|\"((?:[^\"\\]|\\.)*)\"|`([^`]*)`|>([^<>{}]+)<", ln)
    res = [x for t in found for x in t if x]
    # plain Blade text line (no tag at all)
    if not re.search(r'[<>=;$(){}\[\]]', ln) and ln.strip():
        res.append(ln)
    # text after a closing tag / before an opening tag on the same line
    for m in re.finditer(r'>([^<>]+)$|^([^<>]+)<', ln):
        res.append(m.group(1) or m.group(2))
    return res


total = 0
files = []
for r in roots:
    p = f'{base}/{r}'
    if os.path.isfile(p):
        files.append(p)
    for dp, dn, fn in os.walk(p):
        for f in fn:
            if f.endswith(('.php', '.js')):
                files.append(os.path.join(dp, f).replace('\\', '/'))
per = {}
for p in sorted(files):
    rel = p[len(base) + 1:]
    if flt and flt not in rel:
        continue
    lines = strip_comments(open(p, encoding='utf-8', errors='ignore').read(), os.path.splitext(p)[1])
    for i, ln in enumerate(lines, 1):
        hits = set()
        for t in texts(ln):
            if re.fullmatch(r'[\w.\-:/\[\]]+', t.strip()) and ' ' not in t.strip() and not t.strip()[0].isupper():
                continue  # identifier-like (keys, slugs, column names)
            for w in re.findall(r'[^\W\d_]+', t.lower()):
                if w in vocab:
                    hits.add(w)
        if hits:
            total += 1
            per[rel] = per.get(rel, 0) + 1
            if flt:
                print(f'{rel}:{i}: [{",".join(sorted(hits))}] {ln.strip()[:260]}')
if not flt:
    for f, n in sorted(per.items(), key=lambda x: -x[1]):
        print(n, f)
print('TOTAL lines', total, 'in', len(per), 'files')
