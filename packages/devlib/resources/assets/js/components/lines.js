/* DevLib · Lines */
(function (Devlib) {
  'use strict';
  const {srcAttr, $, $$, esc, CASES, CASE_LABEL, applyCase, confirmBox, words, fmt, numStr, T, Tf} = Devlib.h;
  /* ============================ LINES (detail dokumen) ============================ */
  class Lines extends Devlib.Component {
    static selector = "[data-dl-lines]";
    init() {
      const self = this;
      this.cfg = JSON.parse(this.el.dataset.config);
      this.cols = this.cfg.columns;
      // 'more' => true: kolom tidak tampil di tabel, diisi lewat panel › di bawah baris (pola "Line details" Dynamics 365)
      // supaya tabel tetap berisi kolom inti saja walau kolom detail banyak.
      this.more = this.cols.filter(c => c.more);
      this.tcols = this.cols.filter(c => !c.more);
      this.open = new Set();
      this.name = this.cfg.name;
      // Di form terkunci (Confirmed/Cancelled/Closed) baris & isian footer otomatis read-only; tombol tab footer tetap aktif.
      this.ro = this.cfg.readonly || !!this.el.closest('form.is-view');
      if (this.ro) $$(this.el, '.dl-lines__footer input, .dl-lines__footer select').forEach(x => { if (x.type === 'checkbox' || x.tagName === 'SELECT') x.disabled = true; else x.readOnly = true; });
      this.rows = JSON.parse(this.el.dataset.rows || '[]');
      this.removed = [];
      // Rumus aman: hanya nama kolom, angka, dan + - * / ( ).
      this.cols.forEach(c => {
        if (!c.formula) return;
        if (!/^[\w\s+\-*/().]+$/.test(c.formula)) throw new Error('invalid formula: ' + c.formula);
        const body = c.formula.replace(/[A-Za-z_]\w*/g, k => { if (!this.cols.some(x => x.key === k)) throw new Error('unknown column: ' + k); return `(+r[${JSON.stringify(k)}]||0)`; });
        c.fn = new Function('r', 'return ' + body);
      });

      this.el.addEventListener('input', e => {
        const t = e.target;
        // Diskon/pajak: yang diketik (% atau Amount) jadi sumber, pasangannya dihitung ulang di refresh().
        if (t.matches('[data-l-pct]')) { t.value = t.value.replace(/[^\d.,]/g, ''); t.closest('tr').dataset.src = 'pct'; return this.refresh(); }
        if (t.matches('[data-l-amt], [data-l-cost]')) { if (t.matches('[data-l-amt]')) t.closest('tr').dataset.src = 'amt'; t.value = t.value.replace(/[^\d]/g, '') ? fmt.number(+t.value.replace(/[^\d]/g, '')) : ''; return this.refresh(); }
        if (!t.matches('.dl-lines__in') || t.dataset.lk !== undefined) return;
        const c = this.colOf(t.dataset.k), r = this.rowOf(t);
        if (c.type === 'text' && t.dataset.case) { const p = t.selectionStart; t.value = applyCase(t.value, t.dataset.case); try { t.setSelectionRange(p, p); } catch (x) {} }
        r[c.key] = this.num(c) ? this.raw(t.value) : t.value;
        t.closest('td').classList.remove('is-invalid');
        this.refresh();
      });
      this.el.addEventListener('change', e => { if (e.target.matches('[data-l-tx], [data-l-hpp], [data-l-alloc]')) this.refresh(); });
      // Ganti mata uang di header (tab Finance) -> simbol & terbilang di footer ikut.
      const cf = (this.cfg.totals && this.cfg.totals.currencyField) || 'currency';
      (this.el.closest('form') || document).addEventListener('change', e => { if (e.target.name === cf) this.refresh(); });
      this.el.addEventListener('change', e => { if (e.target.matches('select.dl-lines__in, input[type=date].dl-lines__in')) { this.rowOf(e.target)[e.target.dataset.k] = e.target.value; e.target.closest('td').classList.remove('is-invalid'); this.refresh(); } });
      this.el.addEventListener('focusin', e => { const t = e.target; if (t.classList.contains('dl-lines__num')) { t.value = fmt.raw(this.rowOf(t)[t.dataset.k] ?? ''); t.select(); } });
      this.el.addEventListener('focusout', e => { const t = e.target; if (t.classList.contains('dl-lines__num')) t.value = this.show(this.colOf(t.dataset.k), this.rowOf(t)[t.dataset.k] ?? ''); });
      this.el.addEventListener('keydown', e => {
        const t = e.target; if (e.key !== 'Enter' || !t.matches('.dl-lines__in') || t.dataset.lk !== undefined) return;
        e.preventDefault();
        const i = +t.closest('tr[data-i]').dataset.i, next = $(this.el, `tr[data-i="${i + 1}"] [data-k="${t.dataset.k}"]`);
        if (next) next.focus(); else if (!this.ro && this.cfg.addable !== false) { this.add(false); $(this.el, `tr[data-i="${i + 1}"] [data-k="${t.dataset.k}"]`)?.focus(); }
      });
      this.el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-l-add]')) return this.add();
        const ex = x.closest('[data-l-exp]');
        if (ex) { const i = +ex.dataset.lExp; this.open.has(i) ? this.open.delete(i) : this.open.add(i); return this.body(); }
        const pr = x.closest('[data-l-primary]');
        if (pr) { const i = +pr.closest('tr[data-i]').dataset.i; this.rows.forEach((r, j) => { r[pr.dataset.lPrimary] = j === i; }); this.body(); this.changed(); return; }
        const bo = x.closest('[data-l-bool]');
        if (bo) { const r = this.rowOf(bo); r[bo.dataset.lBool] = !r[bo.dataset.lBool]; this.body(); this.changed(); return; }
        if (x.closest('[data-l-dup]')) { const i = +x.closest('tr[data-i]').dataset.i; this.rows.splice(i + 1, 0, {...this.rows[i]}); this.body(); this.changed(); return; }
        const del = x.closest('[data-l-del]');
        if (del) {
          const i = +del.closest('tr[data-i]').dataset.i, go = () => { const [gone] = this.rows.splice(i, 1); if (this.cfg.keyField && gone && gone[this.cfg.keyField]) this.removed.push(gone[this.cfg.keyField]); this.keepPrimary(gone); this.body(); this.changed(); };
          if (this.filled(this.rows[i])) confirmBox({message: Tf('l_delete_row_n', {n: i + 1}), tone: 'danger'}).then(ok => ok && go()); else go();
          return;
        }
        const cb = x.closest('[data-l-case]');
        if (cb) { const inp = cb.previousElementSibling, idx = (CASES.indexOf(inp.dataset.case || 'none') + 1) % 3; inp.dataset.case = CASES[idx]; cb.textContent = CASE_LABEL[CASES[idx]]; inp.value = applyCase(inp.value, CASES[idx]); inp.dispatchEvent(new Event('input', {bubbles: true})); inp.focus(); }
      });

      this.pop = null;
      this.el.addEventListener('focusin', e => { if (e.target.dataset.lk !== undefined) this.openPop(e.target); });
      this.el.addEventListener('input', e => { if (e.target.dataset.lk !== undefined) this.openPop(e.target); });
      this.el.addEventListener('focusout', e => { if (e.target.dataset.lk !== undefined) { const r = this.rowOf(e.target), c = this.colOf(e.target.dataset.k); e.target.value = r[c.key + '_label'] ?? ''; setTimeout(this.closePop, 120); } });

      this.el.dlLines = {
        get rows() { return self.rows.filter(self.filled).map(r => ({...r})); },
        set(list) { self.rows = (list || []).map(r => ({...r})); while (self.rows.length < Math.max(1, self.cfg.minRows)) self.rows.push(self.blank()); self.body(); $$(self.el, '[data-l-err]').forEach(x => { x.textContent = ''; }); },
        validate: this.validate, total: this.total,
      };
      this.head();
      if (!this.rows.length && !this.ro && this.cfg.minRows > 0) this.rows.push(this.blank());
      this.body();
    }

    money(v) {
      return fmt.num(+v || 0, 3);
    }

    // Mata uang dokumen dari field form (Finance > Currency); IDR bila tidak ada.
    cur() {
      const el = $(this.el.closest('form') || document, `[name="${(this.cfg.totals && this.cfg.totals.currencyField) || 'currency'}"]`);
      const code = el && el.value ? el.value : 'IDR', w = $(this.el, '[data-l-words]'), all = w ? JSON.parse(w.dataset.currencies) : {};
      return {code, def: all[code] || all.IDR || ['Rp', 'rupiah', 'rupiah', 'sen', 'sen', 0], sym: (all[code] || ['Rp'])[0]};
    }

    // Landed cost (Purchasing): biaya bercentang HPP dibagi ke baris; HPP/unit = (subtotal - bagian diskon + landed) / qty.
    // PPN tidak masuk HPP (dapat dikreditkan).
    landed(t) {
      const cols = this.cols.filter(c => c.landed); if (!cols.length) return;
      const of = this.cfg.totals.of, mode = $(this.el, '[data-l-alloc]')?.value || 'value', rows = this.rows.filter(this.filled);
      const tq = rows.reduce((a, r) => a + (+r.qty || 0), 0), n = rows.length || 1;
      this.rows.forEach((r, i) => {
        const on = this.filled(r), share = !on ? 0 : mode === 'qty' ? (tq ? (+r.qty || 0) / tq : 0) : mode === 'equal' ? 1 / n : (t.sub ? (+r[of] || 0) / t.sub : 0);
        const land = Math.round(t.hpp * share * 100) / 100, net = (+r[of] || 0) * (t.sub ? 1 - t.disc / t.sub : 1);
        cols.forEach(c => {
          r[c.key] = !on ? '' : c.landed === 'unit' ? (+r.qty ? Math.round((net + land) / +r.qty * 100) / 100 : 0) : land;
          const td = $(this.el, `tr[data-i="${i}"] td[data-k="${c.key}"]`); if (td) td.textContent = this.show(c, r[c.key]);
        });
      });
    }

    pct(v) {
      return fmt.num(Math.round(v * 100) / 100, 2);
    }

    // Ketikan dalam bahasa tampilan -> nilai kanonik (1234.5) yang disimpan di baris & dikirim ke server (core numStr).
    raw(v) {
      return numStr(v);
    }

    // Kolom 'primary' (tepat satu baris bernilai true, mis. tier/bahan Utama): baris pertama otomatis Utama.
    blank() {
      return Object.fromEntries(this.cols.map(c => [c.key, c.type === 'primary' ? !this.rows.some(r => r[c.key]) : c.type === 'bool' ? !!c.default : c.default ?? '']));
    }

    // Baris Utama dihapus -> baris pertama yang tersisa menjadi Utama.
    keepPrimary(gone) {
      this.cols.filter(c => c.type === 'primary').forEach(c => { if (gone && gone[c.key] && this.rows.length && !this.rows.some(r => r[c.key])) this.rows[0][c.key] = true; });
    }

    filled(r) {
      return this.cols.some(c => !c.formula && !c.landed && !['display', 'primary', 'bool'].includes(c.type) && String(r[c.key] ?? '').trim() !== '');
    }

    calc(r) {
      return this.cols.forEach(c => { if (c.fn) r[c.key] = Math.round(c.fn(r) * 100) / 100; });
    }

    num(c) {
      return ['number', 'money'].includes(c.type) || !!c.fn;
    }

    show(c, v) {
      if (c.type === 'date') return v ? fmt.gridDate(v) : '';  // tampilan read-only: dd-Mmm-yyyy (baku grid)
      return (v === '' || v == null) && !c.fn ? '' : c.type === 'money' || (c.fn && c.type !== 'number') ? this.money(v) : c.type === 'number' ? (v === '' ? '' : fmt.num(+v, 3)) : v;
    }

    cell(c, r, i) {
      const html = this.cellRaw(c, r, i), fz = this.fz(c).trim();
      if (!fz) return html;
      const end = html.indexOf('>'), tag = html.slice(0, end);
      return (tag.includes('class="') ? tag.replace('class="', `class="${fz} `) : `${tag} class="${fz}"`) + html.slice(end);
    }

    cellRaw(c, r, i) {
      const v = r[c.key] ?? '', req = c.required ? ' data-req' : '', ph = c.placeholder ? ` placeholder="${esc(c.placeholder)}"` : '';
      // readonlyUnless: sel hanya bisa diisi bila kolom bool lain di baris yang sama bernilai true (mis. Biaya hanya bila Biaya Baru = Ya).
      const lock = c.readonlyUnless && !r[c.readonlyUnless] ? ' readonly tabindex="-1"' : '';
      if (c.type === 'primary' || c.type === 'bool') {
        const on = !!v, [yes, no] = c.labels || (c.type === 'primary' ? [T('l_primary'), T('l_pick')] : [T('l_yes'), T('l_no')]);
        return `<td class="dl-lines__pick"><button type="button" class="dl-btn dl-btn--sm ${on ? 'dl-btn--primary' : 'dl-btn--secondary'}" data-l-${c.type}="${esc(c.key)}" ${this.ro ? 'disabled' : ''}>${esc(on ? yes : no)}</button></td>`;
      }
      if (c.fn || c.landed || c.type === 'display' || this.ro) return `<td class="${this.num(c) ? 'num' : ''} dl-lines__ro" data-k="${esc(c.key)}">${esc(c.type === 'lookup' ? (r[c.key + '_label'] ?? v) : this.show(c, v))}</td>`;
      switch (c.type) {
        case 'select': return `<td${req}><select class="dl-lines__in" data-k="${esc(c.key)}"><option value=""></option>${Object.entries(c.options || {}).map(([k, l]) => `<option value="${esc(k)}" ${String(k) === String(v) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></td>`;
        case 'date': return `<td${req}><input type="date" class="dl-lines__in" data-k="${esc(c.key)}" value="${esc(v)}"></td>`;
        case 'number': case 'money': return `<td class="num"${req}><input type="text" inputmode="decimal" class="dl-lines__in dl-lines__num" data-k="${esc(c.key)}" value="${esc(this.show(c, v))}"${ph}${lock}></td>`;
        case 'lookup': return `<td${req} class="dl-lines__lk"><input type="text" class="dl-lines__in" data-k="${esc(c.key)}" data-lk value="${esc(r[c.key + '_label'] ?? '')}" placeholder="${esc(T('l_search'))}" autocomplete="off"><i class="fa-solid fa-magnifying-glass"></i></td>`;
        default: return `<td${req} class="dl-lines__txt"><input type="text" class="dl-lines__in" data-k="${esc(c.key)}" value="${esc(v)}"${ph}${lock}><button type="button" class="dl-lines__case" data-l-case tabindex="-1" title="Ganti huruf: kecil / Kapital Tiap Kata / BESAR">aa</button></td>`;
      }
    }

    // Kolom kunci diam saat tabel digeser ke samping (freeze panes): # + kolom pertama di kiri, kolom rumus terakhir + aksi di kanan.
    fz(c) {
      return c === this.tcols[0] ? ' dl-lines__fzl' : c === this.lastFn() ? ' dl-lines__fzr' : '';
    }

    lastFn() {
      return [...this.tcols].reverse().find(c => c.fn);
    }

    head() { $(this.el, '[data-l-head]').innerHTML = `<tr><th class="dl-lines__no">#</th>${this.tcols.map(c => `<th class="${this.num(c) ? 'num' : ''}${this.fz(c)}"${srcAttr(c)} ${c.width ? `style="width:${esc(c.width)};min-width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.required ? ' <span class="dl-field__req">*</span>' : ''}</th>`).join('')}${this.ro ? '' : '<th class="dl-lines__act"></th>'}</tr>`; this.el.classList.toggle('has-fzr', !!this.lastFn()); }

    // Panel › : kolom 'more' baris i sebagai isian kecil 4 kolom (label di atas nilai).
    panel(r, i) {
      return `<tr class="dl-lines__more" data-i="${i}"><td></td><td colspan="${this.tcols.length + (this.ro ? 0 : 1)}"><div class="dl-lines__moregrid">${this.more.map(c => `<label class="dl-lines__mf"><span>${esc(c.label)}${c.required ? ' <i class="dl-field__req">*</i>' : ''}</span><table class="dl-lines__mt"><tr>${this.cell(c, r, i)}</tr></table></label>`).join('')}</div></td></tr>`;
    }

    body() {
      const exp = i => this.more.length ? `<button type="button" class="dl-lines__exp ${this.open.has(i) ? 'is-open' : ''} ${this.more.some(c => String(this.rows[i][c.key] ?? '').trim() !== '') ? 'has-val' : ''}" data-l-exp="${i}" title="${esc(Tf('l_row_detail', {cols: this.more.map(c => c.label).join(', ')}))}"><i class="fa-solid fa-chevron-right"></i></button>` : '';
      $(this.el, '[data-l-body]').innerHTML = this.rows.map((r, i) => `<tr data-i="${i}"><td class="dl-lines__no">${exp(i)}${i + 1}</td>${this.tcols.map(c => this.cell(c, r, i)).join('')}${this.ro ? '' : `<td class="dl-lines__act">${this.cfg.duplicable === false ? '' : '<button type="button" class="dl-iconbtn dl-iconbtn--plain" data-l-dup title="Duplikat baris"><i class="fa-solid fa-copy"></i></button>'}${this.cfg.rowLock && !r[this.cfg.rowLock] ? '' : `<button type="button" class="dl-iconbtn dl-iconbtn--plain dl-iconbtn--danger" data-l-del title="${esc(T('l_delete_row'))}" ${this.rows.length <= this.cfg.minRows ? 'disabled' : ''}><i class="fa-solid fa-trash-can"></i></button>`}</td>`}</tr>${this.more.length && this.open.has(i) ? this.panel(r, i) : ''}`).join('')
        || `<tr><td colspan="${this.tcols.length + 2}" class="dl-lines__empty">${esc(T('l_no_rows'))}${$(this.el, '[data-l-add]') ? ' ' + Tf('l_click_x', {x: `<b>${esc($(this.el, '[data-l-add]').textContent.trim())}</b>`}) : ''}</td></tr>`;
      this.refresh();
    }

    refresh() {
      this.rows.forEach((r, i) => { this.calc(r); this.cols.filter(c => c.fn).forEach(c => { const td = $(this.el, `tr[data-i="${i}"] td[data-k="${c.key}"]`); if (td) td.textContent = this.show(c, r[c.key]); }); });
      const sums = this.cols.filter(c => c.sum);
      $(this.el, '[data-l-foot]').innerHTML = sums.length && this.rows.length ? `<tr><td></td>${this.tcols.map((c, i) => c.sum ? `<td class="num"><b>${esc(this.show(c, this.rows.reduce((t, r) => t + (+r[c.key] || 0), 0)))}</b></td>` : `<td>${i === 0 ? '<b>' + esc(T('l_sum')) + '</b>' : ''}</td>`).join('')}${this.ro ? '' : '<td></td>'}</tr>` : '';
      $(this.el, '[data-l-count]').textContent = Tf('g_rows_n', {n: this.rows.filter(this.filled).length});
      const t = this.total();
      this.landed(t);
      const cu = this.cur();
      if (this.cfg.totals && this.cfg.totals.of) Object.entries(t).forEach(([k, v]) => { const b = $(this.el, `[data-l-t="${k}"]`); if (b) b.textContent = k.endsWith('_pct') ? this.pct(v) + '%' : (k === 'disc' && v ? '− ' : '') + cu.sym + ' ' + this.money(v); });
      const wt = $(this.el, '[data-l-words-text]');
      if (wt) { const dec = 10 ** (cu.def[5] || 0); wt.textContent = t.grand ? words(String(Math.round(t.grand * dec) / dec), cu.def, cu.code === 'IDR' ? 'id' : 'en') : '—'; }
      // Ringkasan di atas form (No. dokumen & nominal penting) ikut hidup:
      // [data-l-val="<name>:<key>"] -- key: grand|sub|dpp|ppn (Rp), count (baris terisi), sum.<kolom> (jumlah kolom).
      const filled = this.rows.filter(this.filled), scope = this.el.closest('form') || document;
      $$(scope, `[data-l-val^="${this.name}:"]`).forEach(x => {
        const k = x.dataset.lVal.slice(this.name.length + 1);
        x.textContent = k === 'count' ? filled.length : k.startsWith('sum.') ? fmt.num(filled.reduce((a, r) => a + (+r[k.slice(4)] || 0), 0), 3) : cu.sym + ' ' + this.money(t[k] ?? 0);
      });
      const kf = this.cfg.keyField, rf = this.cfg.removeField;
      $(this.el, '[data-l-inputs]').innerHTML = this.rows.filter(this.filled).map((r, i) => Object.entries(r).map(([k, v]) => `<input type="hidden" name="${esc(this.name)}[${esc(kf && r[kf] ? r[kf] : (kf ? 'new' + i : i))}][${esc(k)}]" value="${esc(v)}">`).join('')).join('')
        + (rf ? this.removed.map(id => `<input type="hidden" name="${esc(rf)}[${esc(id)}]" value="1">`).join('') : '');
    }

    total() {
      const of = this.cfg.totals && this.cfg.totals.of, sub = this.rows.reduce((t, r) => t + (+r[of] || 0), 0);
      // Diskon: tiap baris % dari Details <-> Amount. Pajak: tiap baris % dari DPP <-> Amount, tanda +/- (PPh dipotong).
      // Biaya lain ditambahkan setelah pajak.
      const rowsOf = (g, base) => $$(this.el, `[data-l-grp="${g}"] tr[data-l-adj]`).map(tr => {
        const pi = $(tr, '[data-l-pct]'), ai = $(tr, '[data-l-amt]'), fromPct = tr.dataset.src !== 'amt';
        const amt = fromPct ? Math.round(base * (+this.raw(pi.value) || 0)) / 100 : +this.raw(ai.value) || 0;
        if (fromPct && document.activeElement !== ai) ai.value = amt ? this.money(amt) : '0';
        if (!fromPct && document.activeElement !== pi) pi.value = base ? this.pct(amt / base * 100) : '0';
        return {tr, amt: amt * (+tr.dataset.sign || 1)};
      });
      const sum = (list, f = () => true) => list.filter(f).reduce((a, x) => a + x.amt, 0);
      // UU PPN ps. 1 angka 18: biaya bercentang PPN masuk DPP; sisanya ditambahkan setelah pajak.
      const costs = rowsOf('cost', sub), isTx = x => !!$(x.tr, '[data-l-tx]')?.checked;
      const disc = Math.min(sub, sum(rowsOf('disc', sub))), costTx = sum(costs, isTx), costNt = sum(costs, x => !isTx(x)), cost = costTx + costNt;
      const dpp = sub - disc + costTx, tax = sum(rowsOf('tax', dpp));
      const hpp = sum(costs, x => !!$(x.tr, '[data-l-hpp]')?.checked);
      return {sub, disc, disc_amt: disc, disc_pct: sub ? disc / sub * 100 : 0, dpp, tax, tax_amt: tax, tax_pct: dpp ? tax / dpp * 100 : 0, cost, cost_tx: costTx, cost_nt: costNt, cost_amt: cost, cost_pct: sub ? cost / sub * 100 : 0, hpp, grand: dpp + tax + costNt};
    }

    changed() {
      return this.el.dispatchEvent(new Event('change', {bubbles: true}));
    }

    rowOf(t) {
      return this.rows[+t.closest('tr[data-i]').dataset.i];
    }

    colOf(k) {
      return this.cols.find(c => c.key === k);
    }

    add(focus = true) { this.rows.push(this.blank()); this.body(); this.changed(); if (focus) { const f = $(this.el, `tr[data-i="${this.rows.length - 1}"] .dl-lines__in`); if (f) f.focus(); } }
    closePop() { if (this.pop) { this.pop.remove(); this.pop = null; } }

    // Lookup remote (data besar, mis. Master Bahan 11 rb baris): ketik min. 2 huruf -> cari ke server (route devlib.lookup).
    openPop(inp) {
      const c = this.colOf(inp.dataset.k), L = c.lookup, q = inp.value.trim().toLowerCase();
      if (!L.remote) return this.showPop(inp, c, L.rows.filter(r => !q || Object.keys(L.columns).some(k => String(r[k] ?? '').toLowerCase().includes(q))).slice(0, 50));
      clearTimeout(this.lkTimer);
      if (q.length < 2) return this.showPop(inp, c, [], 'Ketik min. 2 huruf...');
      const my = this.lkSeq = (this.lkSeq || 0) + 1;
      this.lkTimer = setTimeout(async () => {
        const u = new URL(L.url, location.origin); u.searchParams.set('q', q);
        const res = await fetch(u, {headers: {Accept: 'application/json'}, credentials: 'same-origin'}).then(x => x.ok ? x.json() : []).catch(() => []);
        if (my !== this.lkSeq || document.activeElement !== inp) return;
        L.rows = res; this.showPop(inp, c, res);
      }, 250);
    }

    showPop(inp, c, list, hint = T('l_not_found')) {
      const L = c.lookup;
      this.closePop();
      this.pop = document.createElement('div'); this.pop.className = 'dl-lines__pop';
      this.pop.innerHTML = `<table class="dl-lookup__table"><thead><tr>${Object.values(L.columns).map(h => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${list.map(r => `<tr class="dl-dd__item" data-key="${esc(r[L.key])}">${Object.keys(L.columns).map(k => `<td>${esc(r[k] ?? '')}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="9" class="dl-lines__empty">${esc(hint)}</td></tr>`}</tbody></table>`;
      const rc = inp.getBoundingClientRect();
      this.pop.style.cssText = `left:${rc.left + scrollX}px;top:${rc.bottom + scrollY + 2}px;min-width:${Math.max(rc.width, 320)}px`;
      this.pop.addEventListener('mousedown', e => {
        const tr = e.target.closest('tr[data-key]'); if (!tr) return;
        e.preventDefault();
        const src = L.rows.find(r => String(r[L.key]) === tr.dataset.key), row = this.rowOf(inp);
        row[c.key] = src[L.key]; row[c.key + '_label'] = src[L.label];
        Object.entries(L.fill || {}).forEach(([to, from]) => { row[to] = src[from] ?? ''; });
        const i = +inp.closest('tr[data-i]').dataset.i; this.closePop(); this.body(); this.changed();
        const nextCol = this.cols[this.cols.indexOf(c) + 1]; $(this.el, `tr[data-i="${i}"] [data-k="${nextCol ? nextCol.key : c.key}"]`)?.focus();
      });
      document.body.appendChild(this.pop);
    }

    validate() {
      let bad = 0;
      $$(this.el, 'td.is-invalid').forEach(td => td.classList.remove('is-invalid'));
      // Isian wajib kosong di panel › -> buka panel baris itu dulu supaya selnya terlihat & bisa ditandai.
      const hidden = this.rows.map((r, i) => this.filled(r) && this.more.some(c => c.required && String(r[c.key] ?? '').trim() === '') ? i : -1).filter(i => i >= 0 && !this.open.has(i));
      if (hidden.length) { hidden.forEach(i => this.open.add(i)); this.body(); }
      this.rows.forEach((r, i) => { if (!this.filled(r)) return; this.cols.filter(c => c.required).forEach(c => { if (String(r[c.key] ?? '').trim() === '') { bad++; $(this.el, `tr[data-i="${i}"] [data-k="${c.key}"]`)?.closest('td').classList.add('is-invalid'); } }); });
      const n = this.rows.filter(this.filled).length, err = $(this.el, '[data-l-err]') || this.el.appendChild(Object.assign(document.createElement('div'), {className: 'dl-lines__err'}));
      err.dataset.lErr = ''; err.textContent = n < this.cfg.minRows ? Tf('l_min_rows', {n: this.cfg.minRows}) : bad ? Tf('l_required_cells', {n: bad}) : '';
      return bad + (n < this.cfg.minRows ? 1 : 0);
    }
  }
  Devlib.define("Lines", Lines);
})(window.Devlib);
