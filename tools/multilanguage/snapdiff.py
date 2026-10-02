"""Compare the probe snapshots before / after. Usage: python snapdiff.py id|en [before] [after]
Prints the distinct strings that appeared and disappeared on the same URL (numbers / dates / ids normalised)."""
import json, os, re, sys, collections

here = os.path.dirname(os.path.abspath(__file__))
lang = sys.argv[1] if len(sys.argv) > 1 else 'id'
a_name = sys.argv[2] if len(sys.argv) > 2 else 'before'
b_name = sys.argv[3] if len(sys.argv) > 3 else 'after'


def norm(s):
    return re.sub(r'\d+', '#', s)


def load(name):
    out = {}
    for ln in open(f'{here}/probe/snap_{name}.jsonl', encoding='utf-8'):
        r = json.loads(ln)
        if r['l'] == lang:
            out[r['u']] = set(norm(x) for x in r['s'])
    return out


A, B = load(a_name), load(b_name)
gone, new = collections.Counter(), collections.Counter()
where_new, where_gone = {}, {}
for u in A.keys() & B.keys():
    for s in A[u] - B[u]:
        gone[s] += 1
        where_gone.setdefault(s, u)
    for s in B[u] - A[u]:
        new[s] += 1
        where_new.setdefault(s, u)
print(f'{lang}: {len(A.keys() & B.keys())} urls compared; {len(gone)} distinct strings gone, {len(new)} distinct strings new')
json.dump({'gone': {s: [n, where_gone[s]] for s, n in gone.most_common()}, 'new': {s: [n, where_new[s]] for s, n in new.most_common()}},
          open(f'{here}/probe/diff_{lang}.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
vocab = set(open(f'{here}/probe/vocab_en.txt', encoding='utf-8').read().split()) if lang == 'id' else set(open(f'{here}/probe/vocab_id.txt', encoding='utf-8').read().split())
sus = [(s, n) for s, n in new.most_common() if any(w in vocab for w in re.findall(r'[^\W\d_]+', s.lower()))]
print('new strings containing', 'English' if lang == 'id' else 'Indonesian', 'words:', len(sus))
for s, n in sus[: int(sys.argv[4]) if len(sys.argv) > 4 else 150]:
    print(f'  {n:3d} {s[:170]}  @{where_new[s][:50]}')
