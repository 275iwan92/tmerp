import json, os, re, sys, collections, html

which = sys.argv[1] if len(sys.argv) > 1 else 'id_in_en'
here = os.path.dirname(os.path.abspath(__file__))
base = 'C:/Users/iwanp/Herd/tmerp'
d = json.load(open(f'{here}/probe/{which}.json', encoding='utf-8'))
IGNORE = re.compile(r'translation-dictionary|setup-help|help-center|^dev$|daily-quotes')
roots = ['resources/views/v2', 'app/Support', 'app/Services', 'app/Models', 'app/Providers', 'app/Assistant', 'routes', 'packages/devlib/src', 'packages/devlib/resources',
         'packages/mockup-parts', 'packages/chat', 'packages/assistant', 'config']
files = {}
for r in roots:
    for dp, dn, fn in os.walk(f'{base}/{r}'):
        if 'node_modules' in dp or 'vendor' in dp:
            continue
        for f in fn:
            if f.endswith(('.php', '.js', '.json')):
                p = os.path.join(dp, f).replace('\\', '/')
                try:
                    files[p[len(base) + 1:]] = re.sub(r'\s+', ' ', open(p, encoding='utf-8', errors='ignore').read())
                except Exception:
                    pass
big = '\n'.join(files.values())
code, data = {}, {}
for s, v in d.items():
    tags = [t for t in v['at'] if not IGNORE.search(t)]
    if not tags:
        continue
    v['at'] = tags
    variants = {s, s.replace("'", "\\'"), html.escape(s, quote=True), html.escape(s, quote=False)}
    if any(x in big for x in variants):
        v['files'] = [p for p, src in files.items() if any(x in src for x in variants)][:4]
        code[s] = v
    else:
        data[s] = v
json.dump(code, open(f'{here}/probe/{which}_code.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
json.dump(data, open(f'{here}/probe/{which}_data.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(which, len(code), 'written in code;', len(data), 'not literal in code')
by = collections.defaultdict(list)
for s, v in code.items():
    by[v['files'][0]].append(s)
for f, lst in sorted(by.items(), key=lambda x: -len(x[1])):
    print(len(lst), f)
bt = collections.Counter()
for s, v in data.items():
    for t in v['at']:
        bt[t] += 1
print('--- not literal, by screen')
for t, n in bt.most_common(45):
    print(n, t)
