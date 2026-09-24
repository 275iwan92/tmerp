/* DevLib · Chart */
(function (Devlib) {
  'use strict';
  const {$, esc, CH_TONES, chNum, niceMax} = Devlib.h;

  class Chart extends Devlib.Component {
    static selector = "[data-dl-chart]";
    init() {
      const el = this.el;
      const type = el.dataset.type, fmt2 = el.dataset.format, labels = JSON.parse(el.dataset.labels || '[]');
      const series = JSON.parse(el.dataset.series || '[]').map((s, i) => ({...s, tone: s.tone || CH_TONES[i % CH_TONES.length], on: true}));
      const plot = $(el, '[data-ch-plot]'), tip = $(el, '[data-ch-tip]');
      let W = 640, H = 300;  // diukur ulang dari kotak plot tiap gambar -> teks tetap ukuran asli
      const P = {l: 56, r: 16, t: 14, b: 40};
      const pieLike = ['pie', 'donut', 'funnel'].includes(type);
      const tipOn = (e, html) => { tip.innerHTML = html; tip.hidden = false; const r = el.getBoundingClientRect(); tip.style.left = Math.min(e.clientX - r.left + 12, r.width - 180) + 'px'; tip.style.top = (e.clientY - r.top + 12) + 'px'; };
      const esc2 = esc;
      function axes(max, horizontal) {
        let g = '';
        const N = horizontal && W < 520 ? 2 : 4;  // sumbu mendatar sempit: lebih sedikit angka supaya tidak berdempet
        for (let i = 0; i <= N; i++) {
          const v = max * i / N;
          if (horizontal) { const x = P.l + (W - P.l - P.r) * i / N; g += `<line x1="${x}" y1="${P.t}" x2="${x}" y2="${H - P.b}" class="dl-chart__grid"/><text x="${x}" y="${H - P.b + 16}" class="dl-chart__ax" text-anchor="middle">${esc2(chNum(v, fmt2))}</text>`; }
          else { const y = H - P.b - (H - P.t - P.b) * i / N; g += `<line x1="${P.l}" y1="${y}" x2="${W - P.r}" y2="${y}" class="dl-chart__grid"/><text x="${P.l - 8}" y="${y + 4}" class="dl-chart__ax" text-anchor="end">${esc2(chNum(v, fmt2))}</text>`; }
        }
        return g;
      }
      const xLabels = (cx) => labels.map((l, i) => `<text x="${cx(i)}" y="${H - P.b + 16}" class="dl-chart__ax" text-anchor="middle">${esc2(l)}</text>`).join('');
      function cartesian() {
        const vis = series.filter(s => s.on), n = labels.length, iw = (W - P.l - P.r) / Math.max(1, n);
        const stacked = type === 'stacked', bars = vis.filter(s => type === 'bar' || stacked || (type === 'combo' && (s.type || 'bar') === 'bar')), lines = vis.filter(s => ['line', 'area'].includes(type) || (type === 'combo' && s.type === 'line'));
        const max = niceMax(Math.max(1, ...labels.map((_, i) => stacked ? bars.reduce((t, s) => t + (+s.data[i] || 0), 0) : Math.max(0, ...vis.map(s => +s.data[i] || 0)))));
        const y = v => H - P.b - (H - P.t - P.b) * (v / max), cx = i => P.l + iw * (i + 0.5);
        let g = axes(max) + xLabels(cx);
        const bw = Math.min(46, iw * 0.7 / (stacked ? 1 : Math.max(1, bars.length)));
        labels.forEach((l, i) => {
          let acc = 0;
          bars.forEach((s, k) => {
            const v = +s.data[i] || 0, x = stacked ? cx(i) - bw / 2 : cx(i) - (bw * bars.length) / 2 + k * bw, top = stacked ? y(acc + v) : y(v), h = stacked ? y(acc) - y(acc + v) : H - P.b - y(v);
            g += `<rect x="${x + 1}" y="${top}" width="${Math.max(1, bw - 2)}" height="${Math.max(0, h)}" rx="3" class="dl-chart--${s.tone} dl-chart__bar" data-tip="${esc2(l)}|${esc2(s.name)}|${v}"/>`;
            acc += v;
          });
        });
        lines.forEach(s => {
          const pts = labels.map((_, i) => [cx(i), y(+s.data[i] || 0)]);
          if (type === 'area') g += `<path d="M${pts[0][0]},${H - P.b} L${pts.map(p => p.join(',')).join(' L')} L${pts[pts.length - 1][0]},${H - P.b} Z" class="dl-chart--${s.tone} dl-chart__area"/>`;
          g += `<polyline points="${pts.map(p => p.join(',')).join(' ')}" class="dl-chart--${s.tone} dl-chart__line"/>` + pts.map((p, i) => `<circle cx="${p[0]}" cy="${p[1]}" r="4" class="dl-chart--${s.tone} dl-chart__dot" data-tip="${esc2(labels[i])}|${esc2(s.name)}|${s.data[i]}"/>`).join('');
        });
        return g;
      }
      function hbar() {
        const vis = series.filter(s => s.on), n = labels.length, ih = (H - P.t - P.b) / Math.max(1, n), L = 120;
        const max = niceMax(Math.max(1, ...vis.flatMap(s => s.data.map(Number))));
        const x = v => L + (W - L - P.r) * v / max, bh = Math.min(26, ih * 0.7 / Math.max(1, vis.length));
        let g = ''; const P0 = P.l; P.l = L; g += axes(max, true); P.l = P0;
        labels.forEach((l, i) => {
          const cy = P.t + ih * (i + 0.5);
          g += `<text x="${L - 8}" y="${cy + 4}" class="dl-chart__ax" text-anchor="end">${esc2(l)}</text>`;
          vis.forEach((s, k) => { const v = +s.data[i] || 0, yy = cy - (bh * vis.length) / 2 + k * bh; g += `<rect x="${L}" y="${yy + 1}" width="${Math.max(0, x(v) - L)}" height="${bh - 2}" rx="3" class="dl-chart--${s.tone} dl-chart__bar" data-tip="${esc2(l)}|${esc2(s.name)}|${v}"/><text x="${x(v) + 5}" y="${yy + bh / 2 + 4}" class="dl-chart__val">${esc2(chNum(v, fmt2))}</text>`; });
        });
        return g;
      }
      function pie() {
        const s = series[0] || {data: []}, vals = labels.map((_, i) => series[0] && hidden.has(i) ? 0 : +s.data[i] || 0), tot = vals.reduce((a, b) => a + b, 0) || 1;
        const R = Math.max(50, Math.min(H / 2 - 12, (W - 190) / 2 - 10)), cx = R + 14, cy = H / 2, r = type === 'donut' ? R * 0.6 : 0, lx0 = cx + R + 22;
        let a0 = -Math.PI / 2, g = '';
        vals.forEach((v, i) => {
          if (!v) return;
          const a1 = a0 + v / tot * Math.PI * 2, large = a1 - a0 > Math.PI ? 1 : 0, p = (a, rr) => [cx + rr * Math.cos(a), cy + rr * Math.sin(a)];
          const [x0, y0] = p(a0, R), [x1, y1] = p(a1, R), [x2, y2] = p(a1, r), [x3, y3] = p(a0, r);
          const d = v / tot > 0.9999 ? `M${cx - R},${cy} a${R},${R} 0 1,0 ${2 * R},0 a${R},${R} 0 1,0 ${-2 * R},0` + (r ? ` M${cx - r},${cy} a${r},${r} 0 1,1 ${2 * r},0 a${r},${r} 0 1,1 ${-2 * r},0` : '')
            : `M${x0},${y0} A${R},${R} 0 ${large} 1 ${x1},${y1} ` + (r ? `L${x2},${y2} A${r},${r} 0 ${large} 0 ${x3},${y3} Z` : `L${cx},${cy} Z`);
          const mid = (a0 + a1) / 2, [lx, ly] = p(mid, r ? (R + r) / 2 : R * 0.62);
          g += `<path d="${d}" fill-rule="evenodd" class="dl-chart--${CH_TONES[i % CH_TONES.length]} dl-chart__slice" data-tip="${esc2(labels[i])}|${esc2(s.name || '')}|${v}|${(v / tot * 100).toFixed(1)}"/>`;
          if (v / tot >= 0.05) g += `<text x="${lx}" y="${ly + 4}" class="dl-chart__pct" text-anchor="middle">${Math.round(v / tot * 100)}%</text>`;
          a0 = a1;
        });
        if (r) g += `<text x="${cx}" y="${cy - 4}" class="dl-chart__total" text-anchor="middle">${esc2(chNum(tot, fmt2))}</text><text x="${cx}" y="${cy + 16}" class="dl-chart__ax" text-anchor="middle">Total</text>`;
        g += labels.map((l, i) => `<g class="dl-chart__pl ${hidden.has(i) ? 'is-off' : ''}" data-ch-slice="${i}"><rect x="${lx0}" y="${40 + i * 24}" width="12" height="12" rx="3" class="dl-chart--${CH_TONES[i % CH_TONES.length]} dl-chart__sw"/><text x="${lx0 + 20}" y="${50 + i * 24}" class="dl-chart__lt"><title>${esc2(l)} · ${esc2(chNum(+s.data[i] || 0, fmt2))}</title>${esc2(l.length > Math.max(8, (W - lx0 - 90) / 7) ? l.slice(0, Math.max(8, (W - lx0 - 90) / 7)) + '…' : l)} · ${esc2(chNum(+s.data[i] || 0, fmt2))}</text></g>`).join('');
        return g;
      }
      function funnel() {
        // Label tahap di kiri, corong di tengah, % dari tahap pertama di kanan (teks tidak pernah terpotong).
        const s = series[0] || {data: []}, vals = s.data.map(Number), max = Math.max(1, ...vals), n = vals.length, h = (H - 16) / Math.max(1, n);
        const L = Math.min(130, W * 0.3), R0 = W - 54, cx = (L + R0) / 2, fw = R0 - L - 8;
        return vals.map((v, i) => {
          const w0 = fw * v / max, w1 = fw * (vals[i + 1] ?? v * 0.85) / max, y0 = 8 + i * h, ym = y0 + (h - 4) / 2 + 4;
          return `<text x="${L - 6}" y="${ym}" class="dl-chart__ax" text-anchor="end">${esc2(labels[i])}</text>`
            + `<path d="M${cx - w0 / 2},${y0} L${cx + w0 / 2},${y0} L${cx + w1 / 2},${y0 + h - 4} L${cx - w1 / 2},${y0 + h - 4} Z" class="dl-chart--${CH_TONES[i % CH_TONES.length]} dl-chart__slice" data-tip="${esc2(labels[i])}|${i ? (v / vals[i - 1] * 100).toFixed(1) + '% dari tahap sebelumnya' : 'tahap awal'}|${v}"/>`
            + (Math.min(w0, w1) > 44 ? `<text x="${cx}" y="${ym}" class="dl-chart__pct" text-anchor="middle">${esc2(chNum(v, fmt2))}</text>` : `<text x="${cx + Math.max(w0, w1) / 2 + 6}" y="${ym}" class="dl-chart__val">${esc2(chNum(v, fmt2))}</text>`)
            + `<text x="${W - 4}" y="${ym}" class="dl-chart__ax" text-anchor="end">${(v / (vals[0] || 1) * 100).toFixed(0)}%</text>`;
        }).join('');
      }
      function heatmap() {
        const rows = series, n = labels.length, L = 110, cw = (W - L - P.r) / Math.max(1, n), rh = (H - P.t - 30) / Math.max(1, rows.length);
        const all = rows.flatMap(s => s.data.map(Number)), mn = Math.min(...all), mx = Math.max(...all);
        let g = labels.map((l, i) => `<text x="${L + cw * (i + 0.5)}" y="${H - 10}" class="dl-chart__ax" text-anchor="middle">${esc2(l)}</text>`).join('');
        rows.forEach((s, r) => {
          g += `<text x="${L - 8}" y="${P.t + rh * (r + 0.5) + 4}" class="dl-chart__ax" text-anchor="end">${esc2(s.name)}</text>`;
          s.data.forEach((v, i) => { const k = mx > mn ? (v - mn) / (mx - mn) : 0.5; g += `<rect x="${L + cw * i + 1}" y="${P.t + rh * r + 1}" width="${cw - 2}" height="${rh - 2}" rx="4" class="dl-chart__heat" style="--k:${(0.08 + k * 0.92).toFixed(2)}" data-tip="${esc2(s.name)}|${esc2(labels[i])}|${v}"/><text x="${L + cw * (i + 0.5)}" y="${P.t + rh * (r + 0.5) + 4}" class="dl-chart__hv ${k > 0.55 ? 'is-dark' : ''}" text-anchor="middle">${esc2(chNum(v, fmt2))}</text>`; });
        });
        return g;
      }
      function radar() {
        const vis = series.filter(s => s.on), n = labels.length, cx = W / 2, cy = H / 2 + 4, R = Math.min(H / 2 - 26, W / 3), max = niceMax(Math.max(1, ...vis.flatMap(s => s.data.map(Number))));
        const pt = (i, v) => { const a = -Math.PI / 2 + i / n * Math.PI * 2; return [cx + R * v / max * Math.cos(a), cy + R * v / max * Math.sin(a)]; };
        let g = '';
        for (let k = 1; k <= 4; k++) g += `<polygon points="${labels.map((_, i) => pt(i, max * k / 4).join(',')).join(' ')}" class="dl-chart__grid dl-chart__web"/>`;
        labels.forEach((l, i) => { const [x, y] = pt(i, max * 1.14); g += `<line x1="${cx}" y1="${cy}" x2="${pt(i, max)[0]}" y2="${pt(i, max)[1]}" class="dl-chart__grid"/><text x="${x}" y="${y + 4}" class="dl-chart__ax" text-anchor="middle">${esc2(l)}</text>`; });
        vis.forEach(s => { g += `<polygon points="${s.data.map((v, i) => pt(i, +v || 0).join(',')).join(' ')}" class="dl-chart--${s.tone} dl-chart__area dl-chart__radar"/>` + s.data.map((v, i) => `<circle cx="${pt(i, +v || 0)[0]}" cy="${pt(i, +v || 0)[1]}" r="4" class="dl-chart--${s.tone} dl-chart__dot" data-tip="${esc2(labels[i])}|${esc2(s.name)}|${v}"/>`).join(''); });
        return g;
      }
      const hidden = new Set();
      function draw() {
        W = Math.max(280, Math.round(plot.clientWidth || 640)); H = Math.max(180, Math.round(plot.clientHeight || 300));
        P.l = type === 'hbar' || type === 'heatmap' ? 56 : Math.min(64, Math.max(44, W * 0.08));
        const body = type === 'hbar' ? hbar() : type === 'pie' || type === 'donut' ? pie() : type === 'funnel' ? funnel() : type === 'heatmap' ? heatmap() : type === 'radar' ? radar() : cartesian();
        plot.innerHTML = `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="${esc2($(el, '.dl-chart__head b')?.textContent || 'Grafik')}">${body}</svg>`;
        const lg = $(el, '[data-ch-legend]');
        if (lg) lg.innerHTML = pieLike || type === 'heatmap' ? '' : series.map((s, i) => `<button type="button" class="dl-chart__li ${s.on ? '' : 'is-off'}" data-ch-series="${i}"><i class="dl-chart--${s.tone}"></i>${esc2(s.name)}</button>`).join('');
      }
      el.addEventListener('mousemove', e => {
        const t = e.target.closest('[data-tip]'); if (!t) { tip.hidden = true; return; }
        const [a, b, v, pct] = t.dataset.tip.split('|');
        tipOn(e, `<b>${esc2(a)}</b>${b ? `<span>${esc2(b)}</span>` : ''}<em>${esc2(chNum(+v, fmt2))}${pct ? ` · ${pct}%` : ''}</em>`);
      });
      el.addEventListener('mouseleave', () => { tip.hidden = true; });
      el.addEventListener('click', e => {
        const s = e.target.closest('[data-ch-series]'); if (s) { const k = series[+s.dataset.chSeries]; if (series.filter(x => x.on).length > 1 || !k.on) k.on = !k.on; return draw(); }
        const p = e.target.closest('[data-ch-slice]'); if (p) { const i = +p.dataset.chSlice; hidden.has(i) ? hidden.delete(i) : hidden.add(i); draw(); }
      });
      el.dlChart = {series, labels, draw};
      draw();
      let rw = plot.clientWidth; new ResizeObserver(() => { if (Math.abs(plot.clientWidth - rw) > 4) { rw = plot.clientWidth; draw(); } }).observe(plot);
    }
  }
  Devlib.define("Chart", Chart);
})(window.Devlib);
