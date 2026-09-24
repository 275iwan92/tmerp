/* DevLib · Import */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, parseCsv, downloadCsv, parseNum, parseDate} = Devlib.h;

  class Import extends Devlib.Component {
    static selector = "[data-dl-import]";
    init() {
      const el = this.el;
      const cfg = JSON.parse(el.dataset.config), cols = cfg.columns;
      let file = null, map = {}, result = [];
      const norm = s => String(s).toLowerCase().replace(/[^a-z0-9]/g, '');
      const step = n => { $$(el, '[data-i-step]').forEach(s => { s.hidden = s.dataset.iStep !== String(n); }); $$(el, '[data-i-steps] li').forEach((li, i) => li.className = i + 1 < n ? 'is-done' : i + 1 === n ? 'is-active' : ''); };
      function load(f) {
        const rd = new FileReader();
        rd.onload = () => {
          const {delim, rows} = parseCsv(String(rd.result));
          if (rows.length < 2) { toast('File kosong atau hanya berisi judul kolom.', 'danger'); return; }
          file = {name: f.name, head: rows[0].map(h => h.trim()), body: rows.slice(1), delim};
          map = {}; cols.forEach(c => { const names = [c.key, c.label, ...c.aliases].map(norm); const i = file.head.findIndex(h => names.includes(norm(h))); map[c.key] = i; });
          drawMap(); step(2);
        };
        rd.readAsText(f, 'utf-8');
      }
      function drawMap() {
        $(el, '[data-i-fileinfo]').textContent = `${file.name} · ${file.body.length} baris data · ${file.head.length} kolom · pemisah "${file.delim === '\t' ? 'tab' : file.delim}"`;
        $(el, '[data-i-map]').innerHTML = cols.map(c => `<label class="dl-import__mrow ${map[c.key] < 0 && c.required ? 'is-bad' : ''}"><span><b>${esc(c.label)}</b>${c.required ? ' <span class="dl-field__req">*</span>' : ''}<small>${esc({text: 'teks', number: 'angka', date: 'tanggal', lookup: 'pilihan master'}[c.type] || c.type)}</small></span><i class="fa-solid fa-arrow-left"></i>
          <select data-i-col="${esc(c.key)}"><option value="-1">— tidak diimpor —</option>${file.head.map((h, i) => `<option value="${i}" ${map[c.key] === i ? 'selected' : ''}>${esc(h)}</option>`).join('')}</select>
          <small class="dl-import__sample">${map[c.key] >= 0 ? 'contoh: ' + esc(file.body[0][map[c.key]] ?? '') : ''}</small></label>`).join('');
      }
      function validate() {
        result = file.body.map((r, n) => {
          const out = {}, err = {};
          cols.forEach(c => {
            const raw = map[c.key] >= 0 ? String(r[map[c.key]] ?? '').trim() : '';
            if (raw === '') { if (c.required) err[c.key] = 'wajib diisi'; out[c.key] = ''; return; }
            if (c.type === 'number') { const v = parseNum(raw); if (isNaN(v)) err[c.key] = 'bukan angka'; else out[c.key] = v; }
            else if (c.type === 'date') { const v = parseDate(raw); if (!v) err[c.key] = 'format tanggal (DD/MM/YYYY atau YYYY-MM-DD)'; else out[c.key] = v; }
            else if (c.type === 'lookup') { const hit = c.lookup.rows.find(([k, l]) => norm(k) === norm(raw) || norm(l) === norm(raw)); if (!hit) err[c.key] = 'tidak ada di master'; else out[c.key] = hit[0]; }
            else out[c.key] = raw;
          });
          return {line: n + 2, raw: r, out, err, ok: !Object.keys(err).length};
        });
      }
      function drawPreview() {
        const good = result.filter(r => r.ok).length, bad = result.length - good, only = $(el, '[data-i-onlybad]').checked;
        $(el, '[data-i-stats]').innerHTML = `<span class="dl-badge dl-badge--pill dl-badge--neutral">${result.length} baris</span><span class="dl-badge dl-badge--pill dl-badge--success">${good} valid</span><span class="dl-badge dl-badge--pill dl-badge--danger">${bad} bermasalah</span>`;
        const list = result.filter(r => !only || !r.ok).slice(0, 200);
        $(el, '[data-i-table]').innerHTML = `<thead><tr><th>Baris</th>${cols.map(c => `<th>${esc(c.label)}</th>`).join('')}<th>Status</th></tr></thead><tbody>${list.map(r => `<tr class="${r.ok ? '' : 'is-bad'}"><td class="num">${r.line}</td>${cols.map(c => { const e = r.err[c.key], v = map[c.key] >= 0 ? r.raw[map[c.key]] ?? '' : ''; return `<td class="${e ? 'is-err' : ''}" ${e ? `title="${esc(e)}"` : ''}>${esc(v)}${e ? `<small>${esc(e)}</small>` : ''}</td>`; }).join('')}<td>${r.ok ? '<span class="dl-badge dl-badge--success">OK</span>' : `<span class="dl-badge dl-badge--danger">${Object.keys(r.err).length} error</span>`}</td></tr>`).join('')}</tbody>`;
        const go = $(el, '[data-i-go]'); go.disabled = !good; go.lastChild.textContent = good ? `Import ${good} baris valid` : 'Tidak ada baris valid';
        $(el, '[data-i-failed]').disabled = !bad;
      }
      el.addEventListener('change', e => {
        if (e.target.matches('[data-i-file]') && e.target.files[0]) load(e.target.files[0]);
        if (e.target.matches('[data-i-col]')) { map[e.target.dataset.iCol] = +e.target.value; drawMap(); }
        if (e.target.matches('[data-i-onlybad]')) drawPreview();
      });
      const drop = $(el, '[data-i-drop]');
      ['dragover', 'dragenter'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('is-over'); }));
      ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, () => drop.classList.remove('is-over')));
      drop.addEventListener('drop', e => { e.preventDefault(); const f = e.dataTransfer.files[0]; if (f) load(f); });
      el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-i-template]')) downloadCsv(cfg.template + '.csv', [cols.map(c => c.label), cols.map(c => c.example ?? '')]);
        const b = x.closest('[data-i-back]'); if (b) step(+b.dataset.iBack);
        if (x.closest('[data-i-preview]')) {
          const miss = cols.filter(c => c.required && map[c.key] < 0);
          if (miss.length) { toast(`Kolom wajib belum dicocokkan: ${miss.map(c => c.label).join(', ')}.`, 'danger'); return; }
          validate(); drawPreview(); step(3);
        }
        if (x.closest('[data-i-failed]')) downloadCsv(cfg.template + '-gagal.csv', [[...file.head, 'Alasan'], ...result.filter(r => !r.ok).map(r => [...r.raw, Object.entries(r.err).map(([k, v]) => `${cols.find(c => c.key === k).label}: ${v}`).join('; ')])]);
        if (x.closest('[data-i-go]')) {
          const good = result.filter(r => r.ok).map(r => r.out), failed = result.length - good.length;
          confirmBox({message: `Import ${good.length} baris valid${failed ? ` (${failed} baris bermasalah dilewati)` : ''}?`, tone: 'warn'}).then(ok => {
            if (!ok) return;
            el.dispatchEvent(new CustomEvent('dl:import', {bubbles: true, detail: {rows: good, failed}}));
            toast(`${good.length} baris berhasil diimpor${failed ? `, ${failed} dilewati` : ''}.`, 'success');
            step(1); $(el, '[data-i-file]').value = '';
          });
        }
      });
      step(1);
      el.dlImport = {load: text => { const f = new File([text], 'data.csv', {type: 'text/csv'}); load(f); }};
    }
  }
  Devlib.define("Import", Import);
})(window.Devlib);
