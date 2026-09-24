/* DevLib · Lines */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, CASES, CASE_LABEL, applyCase, confirmBox} = Devlib.h;
  /* ============================ LINES (detail dokumen) ============================ */
  class Lines extends Devlib.Component {
    static selector = "[data-dl-lines]";
    init() {
      const el = this.el;
      const cfg = JSON.parse(el.dataset.config), cols = cfg.columns, name = cfg.name, ro = cfg.readonly;
      let rows = JSON.parse(el.dataset.rows || '[]');
      const money = v => (+v || 0).toLocaleString('id-ID');
      const raw = v => String(v ?? '').replace(/\./g, '').replace(',', '.');
      const blank = () => Object.fromEntries(cols.map(c => [c.key, c.default ?? '']));
      const filled = r => cols.some(c => !c.formula && c.type !== 'display' && String(r[c.key] ?? '').trim() !== '');
      // Rumus aman: hanya nama kolom, angka, dan + - * / ( ).
      cols.forEach(c => {
        if (!c.formula) return;
        if (!/^[\w\s+\-*/().]+$/.test(c.formula)) throw new Error('formula tidak sah: ' + c.formula);
        const body = c.formula.replace(/[A-Za-z_]\w*/g, k => { if (!cols.some(x => x.key === k)) throw new Error('kolom tidak dikenal: ' + k); return `(+r[${JSON.stringify(k)}]||0)`; });
        c.fn = new Function('r', 'return ' + body);
      });
      const calc = r => cols.forEach(c => { if (c.fn) r[c.key] = Math.round(c.fn(r) * 100) / 100; });
      const num = c => ['number', 'money'].includes(c.type) || !!c.fn;
      const show = (c, v) => (v === '' || v == null) && !c.fn ? '' : c.type === 'money' || (c.fn && c.type !== 'number') ? money(v) : c.type === 'number' ? (v === '' ? '' : (+v).toLocaleString('id-ID')) : v;

      function cell(c, r, i) {
        const v = r[c.key] ?? '', req = c.required ? ' data-req' : '';
        if (c.fn || c.type === 'display' || ro) return `<td class="${num(c) ? 'num' : ''} dl-lines__ro" data-k="${esc(c.key)}">${esc(c.type === 'lookup' ? (r[c.key + '_label'] ?? v) : show(c, v))}</td>`;
        switch (c.type) {
          case 'select': return `<td${req}><select class="dl-lines__in" data-k="${esc(c.key)}"><option value=""></option>${Object.entries(c.options || {}).map(([k, l]) => `<option value="${esc(k)}" ${String(k) === String(v) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></td>`;
          case 'date': return `<td${req}><input type="date" class="dl-lines__in" data-k="${esc(c.key)}" value="${esc(v)}"></td>`;
          case 'number': case 'money': return `<td class="num"${req}><input type="text" inputmode="decimal" class="dl-lines__in dl-lines__num" data-k="${esc(c.key)}" value="${esc(show(c, v))}"></td>`;
          case 'lookup': return `<td${req} class="dl-lines__lk"><input type="text" class="dl-lines__in" data-k="${esc(c.key)}" data-lk value="${esc(r[c.key + '_label'] ?? '')}" placeholder="Cari..." autocomplete="off"><i class="fa-solid fa-magnifying-glass"></i></td>`;
          default: return `<td${req} class="dl-lines__txt"><input type="text" class="dl-lines__in" data-k="${esc(c.key)}" value="${esc(v)}"><button type="button" class="dl-lines__case" data-l-case tabindex="-1" title="Ganti huruf: kecil / Kapital Tiap Kata / BESAR">aa</button></td>`;
        }
      }
      const head = () => { $(el, '[data-l-head]').innerHTML = `<tr><th class="dl-lines__no">#</th>${cols.map(c => `<th class="${num(c) ? 'num' : ''}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.required ? ' <span class="dl-field__req">*</span>' : ''}</th>`).join('')}${ro ? '' : '<th class="dl-lines__act"></th>'}</tr>`; };
      function body() {
        $(el, '[data-l-body]').innerHTML = rows.map((r, i) => `<tr data-i="${i}"><td class="dl-lines__no">${i + 1}</td>${cols.map(c => cell(c, r, i)).join('')}${ro ? '' : `<td class="dl-lines__act"><button type="button" class="dl-iconbtn dl-iconbtn--plain" data-l-dup title="Duplikat baris"><i class="fa-solid fa-copy"></i></button><button type="button" class="dl-iconbtn dl-iconbtn--plain dl-iconbtn--danger" data-l-del title="Hapus baris"><i class="fa-solid fa-trash-can"></i></button></td>`}</tr>`).join('')
          || `<tr><td colspan="${cols.length + 2}" class="dl-lines__empty">Belum ada baris. Klik <b>${esc($(el, '[data-l-add]')?.textContent.trim() || '+ Tambah Baris')}</b>.</td></tr>`;
        refresh();
      }
      // Hitung ulang rumus, jumlah kolom, total dokumen, dan input tersembunyi (tanpa merender ulang input yang sedang diketik).
      function refresh() {
        rows.forEach((r, i) => { calc(r); cols.filter(c => c.fn).forEach(c => { const td = $(el, `tr[data-i="${i}"] td[data-k="${c.key}"]`); if (td) td.textContent = show(c, r[c.key]); }); });
        const sums = cols.filter(c => c.sum);
        $(el, '[data-l-foot]').innerHTML = sums.length && rows.length ? `<tr><td></td>${cols.map((c, i) => c.sum ? `<td class="num"><b>${esc(show(c, rows.reduce((t, r) => t + (+r[c.key] || 0), 0)))}</b></td>` : `<td>${i === 0 ? '<b>Jumlah</b>' : ''}</td>`).join('')}${ro ? '' : '<td></td>'}</tr>` : '';
        $(el, '[data-l-count]').textContent = `${rows.filter(filled).length} baris`;
        const t = total();
        if (cfg.totals && cfg.totals.of) Object.entries(t).forEach(([k, v]) => { const b = $(el, `[data-l-t="${k}"]`); if (b) b.textContent = 'Rp ' + money(v); });
        $(el, '[data-l-inputs]').innerHTML = rows.filter(filled).map((r, i) => Object.entries(r).map(([k, v]) => `<input type="hidden" name="${esc(name)}[${i}][${esc(k)}]" value="${esc(v)}">`).join('')).join('');
      }
      function total() {
        const of = cfg.totals && cfg.totals.of, sub = rows.reduce((t, r) => t + (+r[of] || 0), 0);
        const d = $(el, '[data-l-disc]'), disc = d ? +raw(d.value) || 0 : 0, dpp = sub - disc;
        const p = $(el, '[data-l-ppn]'), ppn = p && p.checked ? Math.round(dpp * (+cfg.totals.ppn || 0)) / 100 : 0;
        return {sub, dpp, ppn, grand: dpp + ppn};
      }
      const changed = () => el.dispatchEvent(new Event('change', {bubbles: true}));
      const rowOf = t => rows[+t.closest('tr').dataset.i];
      const colOf = k => cols.find(c => c.key === k);
      const add = (focus = true) => { rows.push(blank()); body(); changed(); if (focus) { const f = $(el, `tr[data-i="${rows.length - 1}"] .dl-lines__in`); if (f) f.focus(); } };

      el.addEventListener('input', e => {
        const t = e.target;
        if (t.matches('[data-l-disc]')) { t.value = t.value.replace(/[^\d]/g, '') ? (+t.value.replace(/[^\d]/g, '')).toLocaleString('id-ID') : ''; return refresh(); }
        if (!t.matches('.dl-lines__in') || t.dataset.lk !== undefined) return;
        const c = colOf(t.dataset.k), r = rowOf(t);
        if (c.type === 'text' && t.dataset.case) { const p = t.selectionStart; t.value = applyCase(t.value, t.dataset.case); try { t.setSelectionRange(p, p); } catch (x) {} }
        r[c.key] = num(c) ? raw(t.value) : t.value;
        t.closest('td').classList.remove('is-invalid');
        refresh();
      });
      el.addEventListener('change', e => { if (e.target.matches('select.dl-lines__in, input[type=date].dl-lines__in')) { rowOf(e.target)[e.target.dataset.k] = e.target.value; e.target.closest('td').classList.remove('is-invalid'); refresh(); } if (e.target.matches('[data-l-ppn]')) refresh(); });
      el.addEventListener('focusin', e => { const t = e.target; if (t.classList.contains('dl-lines__num')) { t.value = rowOf(t)[t.dataset.k] ?? ''; t.select(); } });
      el.addEventListener('focusout', e => { const t = e.target; if (t.classList.contains('dl-lines__num')) t.value = show(colOf(t.dataset.k), rowOf(t)[t.dataset.k] ?? ''); });
      el.addEventListener('keydown', e => {
        const t = e.target; if (e.key !== 'Enter' || !t.matches('.dl-lines__in') || t.dataset.lk !== undefined) return;
        e.preventDefault();
        const i = +t.closest('tr').dataset.i, next = $(el, `tr[data-i="${i + 1}"] [data-k="${t.dataset.k}"]`);
        if (next) next.focus(); else if (!ro) { add(false); $(el, `tr[data-i="${i + 1}"] [data-k="${t.dataset.k}"]`)?.focus(); }
      });
      el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-l-add]')) return add();
        if (x.closest('[data-l-dup]')) { const i = +x.closest('tr').dataset.i; rows.splice(i + 1, 0, {...rows[i]}); body(); changed(); return; }
        const del = x.closest('[data-l-del]');
        if (del) {
          const i = +del.closest('tr').dataset.i, go = () => { rows.splice(i, 1); body(); changed(); };
          if (filled(rows[i])) confirmBox({message: `Hapus baris ${i + 1}?`, tone: 'danger'}).then(ok => ok && go()); else go();
          return;
        }
        const cb = x.closest('[data-l-case]');
        if (cb) { const inp = cb.previousElementSibling, idx = (CASES.indexOf(inp.dataset.case || 'none') + 1) % 3; inp.dataset.case = CASES[idx]; cb.textContent = CASE_LABEL[CASES[idx]]; inp.value = applyCase(inp.value, CASES[idx]); inp.dispatchEvent(new Event('input', {bubbles: true})); inp.focus(); }
      });

      // Lookup di dalam sel: ketik untuk mencari, pilih -> kolom lain di baris terisi (fill).
      let pop = null;
      const closePop = () => { if (pop) { pop.remove(); pop = null; } };
      function openPop(inp) {
        const c = colOf(inp.dataset.k), L = c.lookup, q = inp.value.trim().toLowerCase();
        const list = L.rows.filter(r => !q || Object.keys(L.columns).some(k => String(r[k] ?? '').toLowerCase().includes(q))).slice(0, 50);
        closePop();
        pop = document.createElement('div'); pop.className = 'dl-lines__pop';
        pop.innerHTML = `<table class="dl-lookup__table"><thead><tr>${Object.values(L.columns).map(h => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${list.map(r => `<tr class="dl-dd__item" data-key="${esc(r[L.key])}">${Object.keys(L.columns).map(k => `<td>${esc(r[k] ?? '')}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="9" class="dl-lines__empty">Tidak ditemukan</td></tr>`}</tbody></table>`;
        const rc = inp.getBoundingClientRect();
        pop.style.cssText = `left:${rc.left + scrollX}px;top:${rc.bottom + scrollY + 2}px;min-width:${Math.max(rc.width, 320)}px`;
        pop.addEventListener('mousedown', e => {
          const tr = e.target.closest('tr[data-key]'); if (!tr) return;
          e.preventDefault();
          const src = L.rows.find(r => String(r[L.key]) === tr.dataset.key), row = rowOf(inp);
          row[c.key] = src[L.key]; row[c.key + '_label'] = src[L.label];
          Object.entries(L.fill || {}).forEach(([to, from]) => { row[to] = src[from] ?? ''; });
          const i = +inp.closest('tr').dataset.i; closePop(); body(); changed();
          const nextCol = cols[cols.indexOf(c) + 1]; $(el, `tr[data-i="${i}"] [data-k="${nextCol ? nextCol.key : c.key}"]`)?.focus();
        });
        document.body.appendChild(pop);
      }
      el.addEventListener('focusin', e => { if (e.target.dataset.lk !== undefined) openPop(e.target); });
      el.addEventListener('input', e => { if (e.target.dataset.lk !== undefined) openPop(e.target); });
      el.addEventListener('focusout', e => { if (e.target.dataset.lk !== undefined) { const r = rowOf(e.target), c = colOf(e.target.dataset.k); e.target.value = r[c.key + '_label'] ?? ''; setTimeout(closePop, 120); } });

      // Validasi: sel wajib pada baris yang terisi + jumlah baris minimal. Kembalian = jumlah masalah.
      function validate() {
        let bad = 0;
        $$(el, 'td.is-invalid').forEach(td => td.classList.remove('is-invalid'));
        rows.forEach((r, i) => { if (!filled(r)) return; cols.filter(c => c.required).forEach(c => { if (String(r[c.key] ?? '').trim() === '') { bad++; $(el, `tr[data-i="${i}"] [data-k="${c.key}"]`)?.closest('td').classList.add('is-invalid'); } }); });
        const n = rows.filter(filled).length, err = $(el, '[data-l-err]') || el.appendChild(Object.assign(document.createElement('div'), {className: 'dl-lines__err'}));
        err.dataset.lErr = ''; err.textContent = n < cfg.minRows ? `Minimal ${cfg.minRows} baris terisi.` : bad ? `${bad} sel wajib belum diisi.` : '';
        return bad + (n < cfg.minRows ? 1 : 0);
      }
      el.dlLines = {
        get rows() { return rows.filter(filled).map(r => ({...r})); },
        set(list) { rows = (list || []).map(r => ({...r})); while (rows.length < Math.max(1, cfg.minRows)) rows.push(blank()); body(); $$(el, '[data-l-err]').forEach(x => { x.textContent = ''; }); },
        validate, total,
      };
      head();
      if (!rows.length && !ro) rows.push(blank());
      body();
    }
  }
  Devlib.define("Lines", Lines);
})(window.Devlib);
