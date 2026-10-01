/* DevLib · Print */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, T, words, val, fmt} = Devlib.h;
  /* ============================ PRINT OUT ============================ */
  // Menyusun dokumen ke lembar A4/A5: isi baris sampai lembar penuh (diukur, bukan ditebak), header tabel diulang,
  // total + catatan + tanda tangan hanya di lembar terakhir.
  class Print extends Devlib.Component {
    static selector = "[data-dl-print]";
    init() {
      this.cfg = JSON.parse(this.el.dataset.config);
      this.cols = this.cfg.columns;
      this.rows = this.cfg.rows;
      this.T = this.cfg.totals || {};
      this.pages = $(this.el, '[data-p-pages]');
      this.data = this.rows.map(r => ({...r}));
      this.cols.filter(c => c.running).forEach(c => { const f = this.compile(c.running); let b = this.cfg.opening ?? 0; this.data.forEach(r => { if (c.prev) r[c.prev] = b; b = Math.round((b + f(r)) * 100) / 100; r[c.key] = b; }); });
      this.el.addEventListener('click', e => {
        const o = e.target.closest('.dl-segment__opt');
        if (o) { const k = o.closest('[data-p-paper]') ? 'paper' : o.closest('[data-p-prices]') ? 'prices' : o.closest('[data-p-ppn]') ? 'ppn' : o.closest('[data-p-group]') ? 'group' : null; if (k) { this.el.dataset[k] = o.dataset.value; this.build(); } return; }
        if (e.target.closest('[data-p-bw]')) { this.el.classList.toggle('is-bw'); e.target.closest('[data-p-bw]').classList.toggle('is-on'); return; }
        if (e.target.closest('[data-p-print]')) window.print();
      });
      this.build();
      this.el.dlPrint = {build: this.build};
    }

    tpl(n) {
      return $(this.el, `template[data-p-${n}]`).innerHTML;
    }

    num(v) {
      return fmt.num(+v || 0, 2);
    }

    vis() {
      return this.cols.filter(c => this.el.dataset.prices === '1' || !c.price);
    }

    isNum(c) {
      return c.type === 'money' || c.type === 'number';
    }

    fmtN(c, v) { if (v === '' || v == null) return ''; const n = +v; return (c.signed && n > 0 && c.plus ? '+' : '') + (c.signed && n < 0 ? '−' + this.num(-n) : this.num(n)); }

    tone(c, v) {
      return c.signed ? (+v > 0 ? 'is-pos' : +v < 0 ? 'is-neg' : 'is-zero') : '';
    }

    cell(c, r) {
      const v = r[c.key];
      if (c.type === 'badge') return `<td class="dl-print__c">${v ? `<span class="dl-print__badge is-${esc((c.tones || {})[v] || 'neutral')}">${esc(v)}</span>` : ''}</td>`;
      const txt = this.isNum(c) ? this.fmtN(c, v) : esc(v ?? '');
      return `<td class="${this.isNum(c) ? 'num' : ''} ${this.tone(c, v)} ${c.bold ? 'is-b' : ''}">${txt}${c.sub && r[c.sub] ? `<small>${esc(r[c.sub])}</small>` : ''}</td>`;
    }

    compile(expr) { if (!/^[\w\s+\-*/().]+$/.test(expr)) throw new Error('invalid formula: ' + expr); return new Function('r', 'return ' + expr.replace(/[A-Za-z_]\w*/g, k => `(+r[${JSON.stringify(k)}]||0)`)); }

    sumOf(list, k) {
      return list.reduce((t, r) => t + (+r[k] || 0), 0);
    }

    items() {
      const v = this.vis(), sums = v.filter(c => c.sum), g = this.el.dataset.group, out = [];
      const rowHtml = (r, i) => { const rt = this.cols.map(c => c.rowTones && c.rowTones[r[c.key]]).find(Boolean); return `<tr class="${rt ? 'is-row-' + rt : ''}"><td class="dl-print__no">${i + 1}</td>${v.map(c => this.cell(c, r)).join('')}</tr>`; };
      const subRow = (label, list, cls) => sums.length ? `<tr class="${cls}"><td></td>${v.map((c, i) => c.sum ? `<td class="num ${this.tone(c, this.sumOf(list, c.key))}">${this.fmtN(c, this.sumOf(list, c.key))}</td>` : `<td>${i === 0 ? esc(label) : ''}</td>`).join('')}</tr>` : '';
      let n = 0;
      if (g) {
        const order = [...new Set(this.data.map(r => r[g]))];
        order.forEach(k => { const list = this.data.filter(r => r[g] === k); out.push(`<tr class="dl-print__grp"><td colspan="${v.length + 1}">${esc(k)} · ${list.length} ${esc(T('pr_rows'))}</td></tr>`); list.forEach(r => out.push(rowHtml(r, n++))); out.push(subRow(T('pr_subtotal') + ' ' + k, list, 'dl-print__sub')); });
      } else this.data.forEach(r => out.push(rowHtml(r, n++)));
      out.push(subRow(T('pr_total'), this.data, 'dl-print__grand'));
      return out.filter(Boolean);
    }

    summary() {
      const S = this.cfg.summary || []; if (!S.length) return '';
      return `<div class="dl-print__kpis" style="--n:${S.length}">${S.map(s => { const val = s.sum ? this.sumOf(this.data, s.sum) : s.last ? (+this.data[this.data.length - 1]?.[s.last] || 0) : s.value; const c = {signed: s.signed, plus: s.plus}; return `<div><small>${esc(s.label)}</small><b class="${this.tone(c, val)}">${typeof val === 'number' ? this.fmtN(c, val) : esc(val)}</b></div>`; }).join('')}</div>`;
    }

    thead() {
      const v = this.vis(), grouped = v.some(c => c.group);
      if (!grouped) return `<tr><th class="dl-print__no">${esc(T('pr_no'))}</th>${v.map(c => `<th class="${c.type === 'text' ? '' : 'num'}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}</th>`).join('')}</tr>`;
      let top = '<tr><th rowspan="2" class="dl-print__no">No</th>', bottom = '<tr>';
      v.forEach((c, i) => {
        if (!c.group) { top += `<th rowspan="2">${esc(c.label)}</th>`; return; }
        if (i && v[i - 1].group === c.group) { bottom += `<th>${esc(c.label)}</th>`; return; }
        top += `<th colspan="${v.filter(x => x.group === c.group).length}">${esc(c.group)}</th>`; bottom += `<th>${esc(c.label)}</th>`;
      });
      return top + '</tr>' + bottom + '</tr>';
    }

    totals() {
      if (!this.T.of || this.el.dataset.prices !== '1') return '';
      const sub = this.sumOf(this.data, this.T.of), disc = +this.T.discount || 0, dpp = sub - disc;
      const ppnOn = this.T.ppn && (!this.cfg.hasPpnKop || this.el.dataset.ppn === '1'), dec = 10 ** (this.cfg.currency ? this.cfg.currency[5] : 2), ppn = ppnOn ? Math.round(dpp * this.T.ppn / 100 * dec) / dec : 0, grand = dpp + ppn;
      const extra = (this.T.extra || []).reduce((t, [, v]) => t + (+v || 0), 0);
      const line = (l, v, cls = '') => `<div class="${cls}"><span>${esc(l)}</span><b>${v < 0 ? `(${this.num(-v)})` : this.num(v)}</b></div>`;
      const final = this.T.net ? grand + extra : grand;
      return `<div class="dl-print__totals">${line(T('pr_subtotal'), sub)}${disc ? line(T('pr_discount'), -disc) + line(T('pr_dpp'), dpp, 'is-strong') : ''}${ppnOn ? line(`${T('pr_vat')} ${this.T.ppn}%`, ppn, 'is-muted') : ''}${line(this.T.net ? T('pr_total_due') : T('pr_total'), grand, 'is-strong')}${this.T.net ? (this.T.extra || []).map(([l, v]) => line(l, v, 'is-violet')).join('') + line(this.T.net, final, 'is-net') : ''}</div>
        <p class="dl-print__words"><span>${esc(T('pr_words'))}:</span> <i># ${esc(words(String(Math.round(final * 100) / 100), this.cfg.currency))} #</i></p>`;
    }

    sheet() {
      const s = document.createElement('section'); s.className = 'dl-print__sheet';
      s.innerHTML = `<div class="dl-print__inner">${this.tpl('head')}<div data-p-body></div>${this.tpl('foot')}</div>`;
      const ppn = this.el.dataset.ppn === '1';
      $$(s, '[data-p-kop]').forEach(k => { k.hidden = (k.dataset.pKop === '1') !== (ppn && this.cfg.hasPpnKop); });
      this.pages.appendChild(s); return s;
    }

    fits(s) { const inner = $(s, '.dl-print__inner'); return inner.scrollHeight <= inner.clientHeight + 1; }

    build() {
      this.pages.innerHTML = '';
      this.el.dataset.paperNow = this.el.dataset.paper;
      let s = this.sheet(), body = $(s, '[data-p-body]');
      body.insertAdjacentHTML('beforeend', this.tpl('parties') + this.tpl('criteria') + this.summary());
      const table = () => { body.insertAdjacentHTML('beforeend', `<table class="dl-print__table"><thead>${this.thead()}</thead><tbody></tbody></table>`); return $(body, 'tbody:last-of-type'); };
      let tb = table();
      this.items().forEach(html => {
        tb.insertAdjacentHTML('beforeend', html);
        if (!this.fits(s) && tb.rows.length > 1) { const last = tb.lastElementChild; last.remove(); s = this.sheet(); body = $(s, '[data-p-body]'); tb = table(); tb.appendChild(last); }
      });
      // Penutup (total, terbilang, catatan, tanda tangan) harus utuh di satu lembar; bila tidak muat, baris terakhir ikut pindah.
      const tail = this.totals() + this.tpl('notes') + this.tpl('sign');
      body.insertAdjacentHTML('beforeend', `<div class="dl-print__tail">${tail}</div>`);
      if (!this.fits(s)) {
        $(body, '.dl-print__tail').remove();
        const n = Math.min(2, tb.rows.length - 1), carry = n > 0 ? [...tb.rows].slice(-n) : [];
        s = this.sheet(); body = $(s, '[data-p-body]');
        if (carry.length) { const nt = table(); carry.forEach(tr => nt.appendChild(tr)); }
        body.insertAdjacentHTML('beforeend', `<div class="dl-print__tail">${tail}</div>`);
      }
      const all = $$(this.pages, '.dl-print__sheet');
      all.forEach((sh, i) => $$(sh, '[data-p-pg]').forEach(x => { x.textContent = `${i + 1}/${all.length}`; }));
      let st = document.getElementById('dl-print-page'); if (!st) { st = document.createElement('style'); st.id = 'dl-print-page'; document.head.appendChild(st); }
      st.textContent = `@page{size:${{a5: 'A5 landscape', a4l: 'A4 landscape'}[this.el.dataset.paper] || 'A4 portrait'};margin:0}`;
      const seg = (sel, v) => $$(this.el, `${sel} .dl-segment__opt`).forEach(b => b.classList.toggle('is-active', b.dataset.value === v));
      seg('[data-p-paper]', this.el.dataset.paper); seg('[data-p-prices]', this.el.dataset.prices); seg('[data-p-ppn]', this.el.dataset.ppn); seg('[data-p-group]', this.el.dataset.group || '');
      this.el.dispatchEvent(new CustomEvent('dl:print-built', {bubbles: true, detail: {pages: all.length}}));
    }
  }
  Devlib.define("Print", Print);
})(window.Devlib);
