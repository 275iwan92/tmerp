/* DevLib · Print */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, T, words, val} = Devlib.h;
  /* ============================ PRINT OUT ============================ */
  // Menyusun dokumen ke lembar A4/A5: isi baris sampai lembar penuh (diukur, bukan ditebak), header tabel diulang,
  // total + catatan + tanda tangan hanya di lembar terakhir.
  class Print extends Devlib.Component {
    static selector = "[data-dl-print]";
    init() {
      const el = this.el;
      const cfg = JSON.parse(el.dataset.config), cols = cfg.columns, rows = cfg.rows, T = cfg.totals || {}, pages = $(el, '[data-p-pages]');
      const tpl = n => $(el, `template[data-p-${n}]`).innerHTML;
      const num = v => (+v || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
      const vis = () => cols.filter(c => el.dataset.prices === '1' || !c.price);
      const isNum = c => c.type === 'money' || c.type === 'number';
      const fmtN = (c, v) => { if (v === '' || v == null) return ''; const n = +v; return (c.signed && n > 0 && c.plus ? '+' : '') + (c.signed && n < 0 ? '−' + num(-n) : num(n)); };
      const tone = (c, v) => c.signed ? (+v > 0 ? 'is-pos' : +v < 0 ? 'is-neg' : 'is-zero') : '';
      const cell = (c, r) => {
        const v = r[c.key];
        if (c.type === 'badge') return `<td class="dl-print__c">${v ? `<span class="dl-print__badge is-${esc((c.tones || {})[v] || 'neutral')}">${esc(v)}</span>` : ''}</td>`;
        const txt = isNum(c) ? fmtN(c, v) : esc(v ?? '');
        return `<td class="${isNum(c) ? 'num' : ''} ${tone(c, v)} ${c.bold ? 'is-b' : ''}">${txt}${c.sub && r[c.sub] ? `<small>${esc(r[c.sub])}</small>` : ''}</td>`;
      };
      // Rumus aman untuk kolom berjalan (hanya nama kolom + angka + - * / ( )).
      const compile = expr => { if (!/^[\w\s+\-*/().]+$/.test(expr)) throw new Error('rumus tidak sah: ' + expr); return new Function('r', 'return ' + expr.replace(/[A-Za-z_]\w*/g, k => `(+r[${JSON.stringify(k)}]||0)`)); };
      const data = rows.map(r => ({...r}));
      cols.filter(c => c.running).forEach(c => { const f = compile(c.running); let b = cfg.opening ?? 0; data.forEach(r => { if (c.prev) r[c.prev] = b; b = Math.round((b + f(r)) * 100) / 100; r[c.key] = b; }); });
      const sumOf = (list, k) => list.reduce((t, r) => t + (+r[k] || 0), 0);
      // Baris tabel (html) sesuai kelompok terpilih: judul kelompok, baris, subtotal; lalu total akhir.
      function items() {
        const v = vis(), sums = v.filter(c => c.sum), g = el.dataset.group, out = [];
        const rowHtml = (r, i) => { const rt = cols.map(c => c.rowTones && c.rowTones[r[c.key]]).find(Boolean); return `<tr class="${rt ? 'is-row-' + rt : ''}"><td class="dl-print__no">${i + 1}</td>${v.map(c => cell(c, r)).join('')}</tr>`; };
        const subRow = (label, list, cls) => sums.length ? `<tr class="${cls}"><td></td>${v.map((c, i) => c.sum ? `<td class="num ${tone(c, sumOf(list, c.key))}">${fmtN(c, sumOf(list, c.key))}</td>` : `<td>${i === 0 ? esc(label) : ''}</td>`).join('')}</tr>` : '';
        let n = 0;
        if (g) {
          const order = [...new Set(data.map(r => r[g]))];
          order.forEach(k => { const list = data.filter(r => r[g] === k); out.push(`<tr class="dl-print__grp"><td colspan="${v.length + 1}">${esc(k)} · ${list.length} baris</td></tr>`); list.forEach(r => out.push(rowHtml(r, n++))); out.push(subRow('Subtotal ' + k, list, 'dl-print__sub')); });
        } else data.forEach(r => out.push(rowHtml(r, n++)));
        out.push(subRow('Total', data, 'dl-print__grand'));
        return out.filter(Boolean);
      }
      function summary() {
        const S = cfg.summary || []; if (!S.length) return '';
        return `<div class="dl-print__kpis" style="--n:${S.length}">${S.map(s => { const val = s.sum ? sumOf(data, s.sum) : s.last ? (+data[data.length - 1]?.[s.last] || 0) : s.value; const c = {signed: s.signed, plus: s.plus}; return `<div><small>${esc(s.label)}</small><b class="${tone(c, val)}">${typeof val === 'number' ? fmtN(c, val) : esc(val)}</b></div>`; }).join('')}</div>`;
      }
      function thead() {
        const v = vis(), grouped = v.some(c => c.group);
        if (!grouped) return `<tr><th class="dl-print__no">No</th>${v.map(c => `<th class="${c.type === 'text' ? '' : 'num'}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}</th>`).join('')}</tr>`;
        let top = '<tr><th rowspan="2" class="dl-print__no">No</th>', bottom = '<tr>';
        v.forEach((c, i) => {
          if (!c.group) { top += `<th rowspan="2">${esc(c.label)}</th>`; return; }
          if (i && v[i - 1].group === c.group) { bottom += `<th>${esc(c.label)}</th>`; return; }
          top += `<th colspan="${v.filter(x => x.group === c.group).length}">${esc(c.group)}</th>`; bottom += `<th>${esc(c.label)}</th>`;
        });
        return top + '</tr>' + bottom + '</tr>';
      }
      function totals() {
        if (!T.of || el.dataset.prices !== '1') return '';
        const sub = sumOf(data, T.of), disc = +T.discount || 0, dpp = sub - disc;
        const ppnOn = T.ppn && (!cfg.hasPpnKop || el.dataset.ppn === '1'), dec = 10 ** (cfg.currency ? cfg.currency[5] : 2), ppn = ppnOn ? Math.round(dpp * T.ppn / 100 * dec) / dec : 0, grand = dpp + ppn;
        const extra = (T.extra || []).reduce((t, [, v]) => t + (+v || 0), 0);
        const line = (l, v, cls = '') => `<div class="${cls}"><span>${esc(l)}</span><b>${v < 0 ? `(${num(-v)})` : num(v)}</b></div>`;
        const final = T.net ? grand + extra : grand;
        return `<div class="dl-print__totals">${line('Subtotal', sub)}${disc ? line('Diskon', -disc) + line('DPP', dpp, 'is-strong') : ''}${ppnOn ? line(`PPN ${T.ppn}%`, ppn, 'is-muted') : ''}${line(T.net ? 'Total Tagihan' : 'Total', grand, 'is-strong')}${T.net ? (T.extra || []).map(([l, v]) => line(l, v, 'is-violet')).join('') + line(T.net, final, 'is-net') : ''}</div>
          <p class="dl-print__words"><span>Terbilang:</span> <i># ${esc(words(String(Math.round(final * 100) / 100), cfg.currency))} #</i></p>`;
      }
      function sheet() {
        const s = document.createElement('section'); s.className = 'dl-print__sheet';
        s.innerHTML = `<div class="dl-print__inner">${tpl('head')}<div data-p-body></div>${tpl('foot')}</div>`;
        const ppn = el.dataset.ppn === '1';
        $$(s, '[data-p-kop]').forEach(k => { k.hidden = (k.dataset.pKop === '1') !== (ppn && cfg.hasPpnKop); });
        pages.appendChild(s); return s;
      }
      const fits = s => { const inner = $(s, '.dl-print__inner'); return inner.scrollHeight <= inner.clientHeight + 1; };
      function build() {
        pages.innerHTML = '';
        el.dataset.paperNow = el.dataset.paper;
        let s = sheet(), body = $(s, '[data-p-body]');
        body.insertAdjacentHTML('beforeend', tpl('parties') + tpl('criteria') + summary());
        const table = () => { body.insertAdjacentHTML('beforeend', `<table class="dl-print__table"><thead>${thead()}</thead><tbody></tbody></table>`); return $(body, 'tbody:last-of-type'); };
        let tb = table();
        items().forEach(html => {
          tb.insertAdjacentHTML('beforeend', html);
          if (!fits(s) && tb.rows.length > 1) { const last = tb.lastElementChild; last.remove(); s = sheet(); body = $(s, '[data-p-body]'); tb = table(); tb.appendChild(last); }
        });
        // Penutup (total, terbilang, catatan, tanda tangan) harus utuh di satu lembar; bila tidak muat, baris terakhir ikut pindah.
        const tail = totals() + tpl('notes') + tpl('sign');
        body.insertAdjacentHTML('beforeend', `<div class="dl-print__tail">${tail}</div>`);
        if (!fits(s)) {
          $(body, '.dl-print__tail').remove();
          const n = Math.min(2, tb.rows.length - 1), carry = n > 0 ? [...tb.rows].slice(-n) : [];
          s = sheet(); body = $(s, '[data-p-body]');
          if (carry.length) { const nt = table(); carry.forEach(tr => nt.appendChild(tr)); }
          body.insertAdjacentHTML('beforeend', `<div class="dl-print__tail">${tail}</div>`);
        }
        const all = $$(pages, '.dl-print__sheet');
        all.forEach((sh, i) => $$(sh, '[data-p-pg]').forEach(x => { x.textContent = `${i + 1}/${all.length}`; }));
        let st = document.getElementById('dl-print-page'); if (!st) { st = document.createElement('style'); st.id = 'dl-print-page'; document.head.appendChild(st); }
        st.textContent = `@page{size:${{a5: 'A5 landscape', a4l: 'A4 landscape'}[el.dataset.paper] || 'A4 portrait'};margin:0}`;
        const seg = (sel, v) => $$(el, `${sel} .dl-segment__opt`).forEach(b => b.classList.toggle('is-active', b.dataset.value === v));
        seg('[data-p-paper]', el.dataset.paper); seg('[data-p-prices]', el.dataset.prices); seg('[data-p-ppn]', el.dataset.ppn); seg('[data-p-group]', el.dataset.group || '');
        el.dispatchEvent(new CustomEvent('dl:print-built', {bubbles: true, detail: {pages: all.length}}));
      }
      el.addEventListener('click', e => {
        const o = e.target.closest('.dl-segment__opt');
        if (o) { const k = o.closest('[data-p-paper]') ? 'paper' : o.closest('[data-p-prices]') ? 'prices' : o.closest('[data-p-ppn]') ? 'ppn' : o.closest('[data-p-group]') ? 'group' : null; if (k) { el.dataset[k] = o.dataset.value; build(); } return; }
        if (e.target.closest('[data-p-bw]')) { el.classList.toggle('is-bw'); e.target.closest('[data-p-bw]').classList.toggle('is-on'); return; }
        if (e.target.closest('[data-p-print]')) window.print();
      });
      build();
      el.dlPrint = {build};
    }
  }
  Devlib.define("Print", Print);
})(window.Devlib);
