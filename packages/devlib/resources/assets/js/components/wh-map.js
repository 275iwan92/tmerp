/* DevLib · WhMap */
(function (Devlib) {
  'use strict';
  const {$, $$, esc} = Devlib.h;

  // Peta gudang: klik bin = panel isi (kapasitas, QR, item, aksi); cari = sorot bin yang cocok; legenda = saring status;
  // tab lantai; mode Edit Layout = geser / ubah ukuran area lalu simpan ke server (posisi yang berlaku = yang tersimpan).
  class WhMap extends Devlib.Component {
    static selector = "[data-dl-whmap]";
    init() {
      try { this.cfg = JSON.parse(this.el.dataset.config || '{}'); } catch (e) { this.cfg = {}; }
      this.bins = this.cfg.bins || {}; this.tx = this.cfg.text || {}; this.panel = $(this.el, '[data-wm-panel]');
      this.on('click', '[data-wm-cell]', (e, c) => { if (!this.el.classList.contains('is-edit')) this.pick(c); });
      this.on('keydown', '[data-wm-cell]', (e, c) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.pick(c); } });
      this.on('click', '[data-wm-floor]', (e, b) => this.floor(b.dataset.wmFloor));
      this.on('click', '[data-wm-only]', (e, b) => {
        const on = !b.classList.contains('is-active');
        $$(this.el, '[data-wm-only]').forEach(x => x.classList.remove('is-active'));
        b.classList.toggle('is-active', on); this.only = on ? b.dataset.wmOnly : null; this.filter();
      });
      this.on('input', '[data-wm-search]', (e, i) => { this.q = i.value.trim().toLowerCase(); this.filter(); });
      this.on('click', '[data-wm-level]', (e, b) => this.show(b.dataset.wmLevel));
      this.on('click', '[data-wm-post]', (e, b) => this.post(b));
      this.on('click', '[data-wm-edit]', (e, b) => { const on = this.el.classList.toggle('is-edit'); b.classList.toggle('dl-btn--primary', on); b.classList.toggle('dl-btn--secondary', !on); });
      this.drag();
      if (this.cfg.selected != null && this.bins[this.cfg.selected]) {
        const cell = $$(this.el, '[data-wm-cell]').find(c => c.dataset.wmCell.split(',').includes(String(this.cfg.selected)));
        if (cell) { const svg = cell.closest('[data-wm-svg]'); if (svg) this.floor(svg.dataset.wmSvg); this.pick(cell, String(this.cfg.selected)); }
      }
    }

    floor(no) {
      $$(this.el, '[data-wm-svg]').forEach(s => { s.hidden = s.dataset.wmSvg !== String(no); });
      $$(this.el, '[data-wm-floor]').forEach(b => b.classList.toggle('is-active', b.dataset.wmFloor === String(no)));
    }

    // Cari + saring status: sel yang tidak cocok diredupkan (tetap terlihat supaya posisi tetap terbaca).
    filter() {
      let hit = 0, firstFloor = null;
      const active = !!(this.q || this.only);
      $$(this.el, '[data-wm-cell]').forEach(c => {
        const okQ = !this.q || c.dataset.find.includes(this.q);
        const okS = !this.only || (this.only === 'DEAD' ? c.dataset.dead === '1' : this.only === 'EXPIRY' ? c.dataset.expiry === '1' : (c.dataset.status === this.only && c.dataset.dead !== '1'));
        const ok = okQ && okS;
        c.classList.toggle('is-dim', !ok); c.classList.toggle('is-hit', ok && active);
        if (ok && active) { hit++; firstFloor ??= c.closest('[data-wm-svg]').dataset.wmSvg; }
      });
      const found = $(this.el, '[data-wm-found]');
      if (found) found.textContent = active ? (hit ? hit + ' ' + (this.tx.matches || '') : (this.tx.nomatch || '')) : '';
      // Hasil hanya ada di lantai lain: pindah ke lantai itu.
      if (hit && firstFloor && !$$(this.el, '[data-wm-svg]:not([hidden]) .is-hit').length) this.floor(firstFloor);
    }

    pick(cell, id = null) {
      $$(this.el, '[data-wm-cell].is-sel').forEach(c => c.classList.remove('is-sel'));
      cell.classList.add('is-sel');
      this.ids = cell.dataset.wmCell.split(',').filter(Boolean);
      this.show(id && this.ids.includes(id) ? id : this.ids[0]);
    }

    show(id) {
      const b = this.bins[id]; if (!b) return;
      const t = this.tx, st = b.dead ? 'DEAD' : (b.status || 'EMPTY'), stText = b.dead ? t.dead : (this.cfg.status[b.status] || b.status);
      const levels = this.ids && this.ids.length > 1
        ? '<div class="dl-whmap__levels">' + this.ids.map(i => {
          const x = this.bins[i] || {};
          return `<button type="button" class="${String(i) === String(id) ? 'is-active' : ''} ${x.dead ? 'is-dead' : ''}" data-wm-level="${esc(i)}">${esc(x.level || x.code || i)}</button>`;
        }).join('') + '</div>'
        : '';
      // Baris kapasitas: caps (berat, volume, luas tapak, ...) atau satu baris lama capacity + pct.
      const fill = pct => pct != null ? `<i><em class="dl-whmap__fill dl-whmap__fill--${pct > 100 ? 'over' : (st === 'DEAD' ? 'dead' : st.toLowerCase())}" style="width:${Math.min(100, Math.max(0, pct))}%"></em></i>` : '';
      const caps = (b.caps && b.caps.length ? b.caps : (b.capacity ? [{label: t.capacity, value: b.capacity, pct: b.pct}] : []));
      const cap = caps.length ? '<div class="dl-whmap__cap">' + caps.map(c => `<span>${esc(c.label || '')}</span><b>${esc(c.value || '')}</b>${fill(c.pct)}${c.note ? `<small>${esc(c.note)}</small>` : ''}`).join('') + '</div>' : '';
      const tags = i => (i.tags || []).map(g => `<em class="dl-whmap__tag ${g.tone ? 'dl-whmap__tag--' + esc(g.tone) : ''}">${esc(g.label || '')}</em>`).join('');
      const rows = (b.items || []).map(i => {
        const idle = i.dead ? `<span class="dl-badge dl-badge--danger dl-badge--pill">${esc(t.dead)}</span><small>${esc(i.idle || '')}</small>` : esc(i.idle || '');
        return `<tr class="${i.dead ? 'is-dead' : ''}"><td><b>${esc(i.name || '')}</b><small>${esc(i.code || '')}${i.lot ? ' · ' + esc(i.lot) : ''}</small>${tags(i) ? `<small>${tags(i)}</small>` : ''}</td><td class="num">${esc(i.qty || '')}</td><td>${idle}</td></tr>`;
      }).join('');
      const table = rows
        ? `<table class="dl-whmap__items"><thead><tr><th>${esc(t.item)}</th><th class="num">${esc(t.qty)}</th><th>${esc(t.idle)}</th></tr></thead><tbody>${rows}</tbody></table>`
        : `<p class="dl-whmap__hint">${esc(t.nostock)}</p>`;
      const has = (b.items || []).length > 0;
      const acts = (this.cfg.actions || [])
        .filter(a => !a.when || (a.when === 'stock' && has) || (a.when === 'usable' && b.status !== 'BLOCKED') || (a.when === 'blocked' && b.status === 'BLOCKED'))
        .map(a => {
          const url = String(a.url || '').replace('{id}', encodeURIComponent(id)).replace('{code}', encodeURIComponent(b.code || ''));
          const cls = `dl-btn dl-btn--sm dl-btn--${a.tone || 'secondary'}`, ic = a.icon ? `<i class="fa-solid ${esc(a.icon)}"></i> ` : '';
          return a.post
            ? `<button type="button" class="${cls}" data-wm-post="${esc(url)}" data-wm-confirm="${esc(a.confirm || '')}" data-wm-reason="${a.reason ? 1 : 0}">${ic}${esc(a.label)}</button>`
            : `<a class="${cls}" href="${esc(url)}"${a.blank ? ' target="_blank"' : ''}>${ic}${esc(a.label)}</a>`;
        }).join('');
      const qr = b.qr
        ? `<div class="dl-whmap__qr"><span data-dl-qrcode data-text="${esc(b.qr)}" data-size="92"></span><div><small>${esc(t.label)}</small><b>${esc(b.code || '')}</b>${b.capacityLabel ? `<span>${esc(b.capacityLabel)}</span>` : ''}</div></div>`
        : '';
      this.panel.innerHTML = `<div class="dl-whmap__phead"><div><small>${esc(t.bin)}</small><b>${esc(b.code || '')}</b><span>${esc(b.title || '')}</span></div><span class="dl-badge dl-badge--pill dl-whmap__st dl-whmap__st--${st.toLowerCase()}">${esc(stText)}</span></div>`
        + levels + cap + (b.note ? `<p class="dl-whmap__note">${esc(b.note)}</p>` : '') + qr + table + (acts ? `<div class="dl-whmap__acts">${acts}</div>` : '');
      Devlib.mountOne('Qrcode', this.panel);
      this.emit('dl:whmap-select', {id, code: b.code});
    }

    send(url, body) {
      return fetch(url, {method: 'POST', credentials: 'same-origin', body: JSON.stringify(body),
        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf || window.DL_CSRF || ''}})
        .then(r => r.json().catch(() => ({})).then(j => r.ok ? j : Promise.reject(j.message || '')));
    }

    post(btn) {
      const go = why => this.send(btn.dataset.wmPost, {reason: why === true ? '' : why})
        .then(j => { if (j.redirect) location.href = j.redirect; else location.reload(); })
        .catch(m => Devlib.toast(typeof m === 'string' && m ? m : this.tx.failed, 'danger'));
      if (!btn.dataset.wmConfirm) return go(true);
      Devlib.confirm({message: btn.dataset.wmConfirm, reason: btn.dataset.wmReason === '1'}).then(ok => { if (ok) go(ok); });
    }

    // Edit Layout: geser area (badan) atau ubah ukurannya (pegangan sudut kanan-bawah); lompat tiap 0,5 m, tidak keluar kanvas.
    drag() {
      if (!this.cfg.saveUrl) return;
      const S = this.cfg.scale || 10, snap = v => Math.round(v * 2) / 2;
      let cur = null;
      const pt = (svg, e) => { const p = svg.createSVGPoint(); p.x = e.clientX; p.y = e.clientY; const m = p.matrixTransform(svg.getScreenCTM().inverse()); return {x: m.x / S, y: m.y / S}; };
      this.el.addEventListener('pointerdown', e => {
        if (!this.el.classList.contains('is-edit')) return;
        const z = e.target.closest('[data-wm-zone]'); if (!z) return;
        const svg = z.closest('svg'), p = pt(svg, e);
        cur = {z, svg, resize: !!e.target.closest('[data-wm-grip]'), sx: p.x, sy: p.y, x: +z.dataset.x, y: +z.dataset.y, w: +z.dataset.w, h: +z.dataset.h};
        z.classList.add('is-drag'); e.preventDefault();
      });
      this.el.addEventListener('pointermove', e => {
        if (!cur) return;
        const p = pt(cur.svg, e), dx = p.x - cur.sx, dy = p.y - cur.sy, rect = $(cur.z, '.dl-whmap__zrect'), grip = $(cur.z, '[data-wm-grip]');
        if (cur.resize) {
          cur.nw = Math.max(1, snap(cur.w + dx)); cur.nh = Math.max(1, snap(cur.h + dy));
          rect.setAttribute('width', cur.nw * S); rect.setAttribute('height', cur.nh * S);
          if (grip) { grip.setAttribute('x', cur.nw * S - 3); grip.setAttribute('y', cur.nh * S - 3); }
        } else {
          cur.nx = Math.max(0, snap(cur.x + dx)); cur.ny = Math.max(0, snap(cur.y + dy));
          cur.z.setAttribute('transform', `translate(${cur.nx * S} ${cur.ny * S})`);
        }
      });
      const end = () => {
        if (!cur) return;
        const c = cur; cur = null; c.z.classList.remove('is-drag');
        const body = {zone: c.z.dataset.wmZone, x: c.nx ?? c.x, y: c.ny ?? c.y, w: c.nw ?? c.w, h: c.nh ?? c.h};
        if (body.x === c.x && body.y === c.y && body.w === c.w && body.h === c.h) return;
        this.send(this.cfg.saveUrl, body)
          .then(() => {
            Object.assign(c.z.dataset, {x: body.x, y: body.y, w: body.w, h: body.h}); Devlib.toast(this.tx.saved, 'success');
            // Ukuran berubah = letak bin di dalam area dihitung ulang server.
            if (c.resize) { try { sessionStorage.setItem('dl-whmap-edit', '1'); } catch (e) {} location.reload(); }
          })
          .catch(m => { Devlib.toast(typeof m === 'string' && m ? m : this.tx.failed, 'danger'); location.reload(); });
      };
      this.el.addEventListener('pointerup', end); this.el.addEventListener('pointercancel', end);
      try { if (sessionStorage.getItem('dl-whmap-edit')) { sessionStorage.removeItem('dl-whmap-edit'); $(this.el, '[data-wm-edit]')?.click(); } } catch (e) {}
    }
  }
  Devlib.define("WhMap", WhMap);
})(window.Devlib);
