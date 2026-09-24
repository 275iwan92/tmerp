/* DevLib · Import */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, parseCsv, downloadCsv, parseNum, parseDate} = Devlib.h;

  class Import extends Devlib.Component {
    static selector = "[data-dl-import]";
    init() {
      this.cfg = JSON.parse(this.el.dataset.config);
      this.cols = this.cfg.columns;
      this.file = null;
      this.map = {};
      this.result = [];
      this.el.addEventListener('change', e => {
        if (e.target.matches('[data-i-file]') && e.target.files[0]) this.load(e.target.files[0]);
        if (e.target.matches('[data-i-col]')) { this.map[e.target.dataset.iCol] = +e.target.value; this.drawMap(); }
        if (e.target.matches('[data-i-onlybad]')) this.drawPreview();
      });
      this.drop = $(this.el, '[data-i-drop]');
      ['dragover', 'dragenter'].forEach(t => this.drop.addEventListener(t, e => { e.preventDefault(); this.drop.classList.add('is-over'); }));
      ['dragleave', 'drop'].forEach(t => this.drop.addEventListener(t, () => this.drop.classList.remove('is-over')));
      this.drop.addEventListener('drop', e => { e.preventDefault(); const f = e.dataTransfer.files[0]; if (f) this.load(f); });
      this.el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-i-template]')) downloadCsv(this.cfg.template + '.csv', [this.cols.map(c => c.label), this.cols.map(c => c.example ?? '')]);
        const b = x.closest('[data-i-back]'); if (b) this.step(+b.dataset.iBack);
        if (x.closest('[data-i-preview]')) {
          const miss = this.cols.filter(c => c.required && this.map[c.key] < 0);
          if (miss.length) { toast(`Kolom wajib belum dicocokkan: ${miss.map(c => c.label).join(', ')}.`, 'danger'); return; }
          this.validate(); this.drawPreview(); this.step(3);
        }
        if (x.closest('[data-i-failed]')) downloadCsv(this.cfg.template + '-gagal.csv', [[...this.file.head, 'Alasan'], ...this.result.filter(r => !r.ok).map(r => [...r.raw, Object.entries(r.err).map(([k, v]) => `${this.cols.find(c => c.key === k).label}: ${v}`).join('; ')])]);
        if (x.closest('[data-i-go]')) {
          const good = this.result.filter(r => r.ok).map(r => r.out), failed = this.result.length - good.length;
          confirmBox({message: `Import ${good.length} baris valid${failed ? ` (${failed} baris bermasalah dilewati)` : ''}?`, tone: 'warn'}).then(ok => {
            if (!ok) return;
            this.el.dispatchEvent(new CustomEvent('dl:import', {bubbles: true, detail: {rows: good, failed}}));
            toast(`${good.length} baris berhasil diimpor${failed ? `, ${failed} dilewati` : ''}.`, 'success');
            this.step(1); $(this.el, '[data-i-file]').value = '';
          });
        }
      });
      this.step(1);
      this.el.dlImport = {load: text => { const f = new File([text], 'data.csv', {type: 'text/csv'}); this.load(f); }};
    }

    norm(s) {
      return String(s).toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    step(n) { $$(this.el, '[data-i-step]').forEach(s => { s.hidden = s.dataset.iStep !== String(n); }); $$(this.el, '[data-i-steps] li').forEach((li, i) => li.className = i + 1 < n ? 'is-done' : i + 1 === n ? 'is-active' : ''); }

    load(f) {
      const rd = new FileReader();
      rd.onload = () => {
        const {delim, rows} = parseCsv(String(rd.result));
        if (rows.length < 2) { toast('File kosong atau hanya berisi judul kolom.', 'danger'); return; }
        this.file = {name: f.name, head: rows[0].map(h => h.trim()), body: rows.slice(1), delim};
        this.map = {}; this.cols.forEach(c => { const names = [c.key, c.label, ...c.aliases].map(this.norm); const i = this.file.head.findIndex(h => names.includes(this.norm(h))); this.map[c.key] = i; });
        this.drawMap(); this.step(2);
      };
      rd.readAsText(f, 'utf-8');
    }

    drawMap() {
      $(this.el, '[data-i-fileinfo]').textContent = `${this.file.name} · ${this.file.body.length} baris data · ${this.file.head.length} kolom · pemisah "${this.file.delim === '\t' ? 'tab' : this.file.delim}"`;
      $(this.el, '[data-i-map]').innerHTML = this.cols.map(c => `<label class="dl-import__mrow ${this.map[c.key] < 0 && c.required ? 'is-bad' : ''}"><span><b>${esc(c.label)}</b>${c.required ? ' <span class="dl-field__req">*</span>' : ''}<small>${esc({text: 'teks', number: 'angka', date: 'tanggal', lookup: 'pilihan master'}[c.type] || c.type)}</small></span><i class="fa-solid fa-arrow-left"></i>
        <select data-i-col="${esc(c.key)}"><option value="-1">— tidak diimpor —</option>${this.file.head.map((h, i) => `<option value="${i}" ${this.map[c.key] === i ? 'selected' : ''}>${esc(h)}</option>`).join('')}</select>
        <small class="dl-import__sample">${this.map[c.key] >= 0 ? 'contoh: ' + esc(this.file.body[0][this.map[c.key]] ?? '') : ''}</small></label>`).join('');
    }

    validate() {
      this.result = this.file.body.map((r, n) => {
        const out = {}, err = {};
        this.cols.forEach(c => {
          const raw = this.map[c.key] >= 0 ? String(r[this.map[c.key]] ?? '').trim() : '';
          if (raw === '') { if (c.required) err[c.key] = 'wajib diisi'; out[c.key] = ''; return; }
          if (c.type === 'number') { const v = parseNum(raw); if (isNaN(v)) err[c.key] = 'bukan angka'; else out[c.key] = v; }
          else if (c.type === 'date') { const v = parseDate(raw); if (!v) err[c.key] = 'format tanggal (DD/MM/YYYY atau YYYY-MM-DD)'; else out[c.key] = v; }
          else if (c.type === 'lookup') { const hit = c.lookup.rows.find(([k, l]) => this.norm(k) === this.norm(raw) || this.norm(l) === this.norm(raw)); if (!hit) err[c.key] = 'tidak ada di master'; else out[c.key] = hit[0]; }
          else out[c.key] = raw;
        });
        return {line: n + 2, raw: r, out, err, ok: !Object.keys(err).length};
      });
    }

    drawPreview() {
      const good = this.result.filter(r => r.ok).length, bad = this.result.length - good, only = $(this.el, '[data-i-onlybad]').checked;
      $(this.el, '[data-i-stats]').innerHTML = `<span class="dl-badge dl-badge--pill dl-badge--neutral">${this.result.length} baris</span><span class="dl-badge dl-badge--pill dl-badge--success">${good} valid</span><span class="dl-badge dl-badge--pill dl-badge--danger">${bad} bermasalah</span>`;
      const list = this.result.filter(r => !only || !r.ok).slice(0, 200);
      $(this.el, '[data-i-table]').innerHTML = `<thead><tr><th>Baris</th>${this.cols.map(c => `<th>${esc(c.label)}</th>`).join('')}<th>Status</th></tr></thead><tbody>${list.map(r => `<tr class="${r.ok ? '' : 'is-bad'}"><td class="num">${r.line}</td>${this.cols.map(c => { const e = r.err[c.key], v = this.map[c.key] >= 0 ? r.raw[this.map[c.key]] ?? '' : ''; return `<td class="${e ? 'is-err' : ''}" ${e ? `title="${esc(e)}"` : ''}>${esc(v)}${e ? `<small>${esc(e)}</small>` : ''}</td>`; }).join('')}<td>${r.ok ? '<span class="dl-badge dl-badge--success">OK</span>' : `<span class="dl-badge dl-badge--danger">${Object.keys(r.err).length} error</span>`}</td></tr>`).join('')}</tbody>`;
      const go = $(this.el, '[data-i-go]'); go.disabled = !good; go.lastChild.textContent = good ? `Import ${good} baris valid` : 'Tidak ada baris valid';
      $(this.el, '[data-i-failed]').disabled = !bad;
    }
  }
  Devlib.define("Import", Import);
})(window.Devlib);
