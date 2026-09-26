/* =============================================================================
 * TMBox3D -- pratinjau 3D statis (isometrik) + tempel artwork ke dieline.
 * Rumus isometrik sama dgn x-mp::dieline-v3 (packages/mockup-parts, preview3d):
 * kotak L x W x H, silinder (diameter), atau lembaran (bentang flat).
 * Artwork (JPG/PNG yang di-upload di Inquiry) dianggap digambar persis seukuran
 * bentang dieline -> panel depan/samping kotak dipotong dari artwork memakai
 * posisi garis lipat hasil TMDieline. Tanpa panel yang cocok = kotak polos.
 * ============================================================================= */
(function (root) {
    const NS = 'http://www.w3.org/2000/svg';
    const C = Math.cos(Math.PI / 6), S = 0.5;
    const iso = (x, y, z) => [(x - y) * C, (x + y) * S - z];
    let uid = 0;

    // Bingkai potong dieline (tanpa bleed) + posisi garis lipat vertikal/horizontal.
    function panels(svg) {
        const ST = root.TMDieline.STROKE, crease = ST.CREASE.toLowerCase(), bleed = ST.BLEED.toLowerCase();
        let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
        const xs = [], ys = [];
        svg.querySelectorAll('line,rect,path,circle,polyline,polygon').forEach((n) => {
            const st = (n.getAttribute('stroke') || '').toLowerCase();
            if (st === bleed || !n.getBBox) return;
            const b = n.getBBox();
            x0 = Math.min(x0, b.x); y0 = Math.min(y0, b.y); x1 = Math.max(x1, b.x + b.width); y1 = Math.max(y1, b.y + b.height);
            if (st === crease && n.tagName === 'line') {
                const a = +n.getAttribute('x1'), c = +n.getAttribute('x2'), d = +n.getAttribute('y1'), f = +n.getAttribute('y2');
                if (Math.abs(a - c) < 0.01) xs.push(a); else if (Math.abs(d - f) < 0.01) ys.push(d);
            }
        });
        if (!isFinite(x0)) return null;
        const cuts = (arr, lo, hi) => [lo, ...arr.filter((v) => v > lo + 0.5 && v < hi - 0.5), hi].sort((a, b) => a - b).filter((v, i, a) => !i || v - a[i - 1] > 0.5);
        return { x0, y0, x1, y1, cx: cuts(xs, x0, x1), cy: cuts(ys, y0, y1) };
    }

    // Tempel artwork di bawah garis dieline, seukuran bingkai potong + bleed.
    function overlay(svg, url) {
        const g = svg.querySelector('g') || svg;
        const b = g.getBBox();
        const img = document.createElementNS(NS, 'image');
        img.setAttribute('href', url);
        img.setAttribute('x', b.x); img.setAttribute('y', b.y);
        img.setAttribute('width', b.width); img.setAttribute('height', b.height);
        img.setAttribute('preserveAspectRatio', 'none');
        g.insertBefore(img, g.firstChild);
        return b;
    }

    // Cari ruas panel ~L lalu ~W bersebelahan (depan + samping) dan ruas tinggi ~H.
    function faces(p, L, W, H) {
        const near = (a, b) => Math.abs(a - b) < Math.max(1.5, b * 0.03);
        for (let i = 0; i < p.cx.length - 2; i++) {
            const w1 = p.cx[i + 1] - p.cx[i], w2 = p.cx[i + 2] - p.cx[i + 1];
            if (near(w1, L) && near(w2, W)) {
                for (let j = 0; j < p.cy.length - 1; j++) {
                    if (near(p.cy[j + 1] - p.cy[j], H)) return { fx: p.cx[i], sx: p.cx[i + 1], y: p.cy[j] };
                }
            }
        }
        return null;
    }

    function textured(id, url, img, px, py, w, h, m) {
        return `<g transform="matrix(${m.join(' ')})"><clipPath id="${id}"><rect x="0" y="0" width="${w}" height="${h}"/></clipPath>`
            + `<image href="${url}" x="${img.x - px}" y="${img.y - py}" width="${img.width}" height="${img.height}" preserveAspectRatio="none" clip-path="url(#${id})"/></g>`;
    }

    // el = wadah 3D; dims = dims part; dielineSvg = svg hasil TMDieline (boleh null); url = artwork (boleh null).
    function render(el, dims, dielineSvg, url) {
        const L = +dims.product_length || 0, W = +dims.product_width || 0, H = +dims.product_height || +dims.lid_height || 0, D = +dims.diameter || 0;
        const poly = (pts, fill, extra = '') => `<polygon points="${pts.map((p) => p.join(',')).join(' ')}" fill="${fill}" stroke="#2c3e69" stroke-width="0.8" stroke-linejoin="round" ${extra}/>`;
        let body = '';
        const pan = dielineSvg ? panels(dielineSvg) : null;
        const img = url && dielineSvg ? (dielineSvg.querySelector('g') || dielineSvg).getBBox() : null;

        if (D && H) {
            const r = D / 2, top = iso(0, 0, H), bot = iso(0, 0, 0), rx = r * 1.22, ry = r * 0.7;
            body = `<path d="M${bot[0] - rx},${top[1]} L${bot[0] - rx},${bot[1]} A${rx},${ry} 0 0 0 ${bot[0] + rx},${bot[1]} L${bot[0] + rx},${top[1]} Z" fill="#dfe6f3" stroke="#2c3e69" stroke-width="0.8"/>`
                + `<ellipse cx="${top[0]}" cy="${top[1]}" rx="${rx}" ry="${ry}" fill="#f4f6fb" stroke="#2c3e69" stroke-width="0.8"/>`;
        } else if (L && W && H) {
            const front = [iso(0, W, 0), iso(L, W, 0), iso(L, W, H), iso(0, W, H)];
            const side = [iso(L, 0, 0), iso(L, W, 0), iso(L, W, H), iso(L, 0, H)];
            const top = [iso(0, 0, H), iso(L, 0, H), iso(L, W, H), iso(0, W, H)];
            const f = url && pan ? faces(pan, L, W, H) : null;
            body = poly(front, '#e8edf7') + poly(side, '#d3dbeb') + poly(top, '#f4f6fb');
            if (f) {
                const id = 'tmb' + (++uid), o1 = iso(0, W, H), o2 = iso(L, W, H);
                body += textured(id + 'f', url, img, f.fx, f.y, L, H, [C, S, 0, 1, o1[0], o1[1]])
                    + textured(id + 's', url, img, f.sx, f.y, W, H, [C, -S, 0, 1, o2[0], o2[1]])
                    + poly(side, 'rgba(20,30,60,.18)') + poly(front, 'none') + poly(top, '#f4f6fb');
            }
        } else {
            const b = pan ? { w: pan.x1 - pan.x0, h: pan.y1 - pan.y0 } : { w: 100, h: 70 };
            const pts = [iso(0, 0, 0), iso(b.w, 0, 0), iso(b.w, b.h, 0), iso(0, b.h, 0)];
            body = poly(pts, '#f4f6fb');
            if (url && pan) {
                const o = iso(0, 0, 0);
                body += textured('tmb' + (++uid), url, img, pan.x0, pan.y0, b.w, b.h, [C, S, -C, S, o[0], o[1]]) + poly(pts, 'none');
            }
        }

        el.innerHTML = `<svg xmlns="${NS}" viewBox="0 0 10 10" style="width:100%;height:100%"><g>${body}</g></svg>`;
        const svg = el.querySelector('svg'), g = svg.querySelector('g'), bb = g.getBBox(), m = Math.max(bb.width, bb.height) * 0.08;
        svg.setAttribute('viewBox', `${bb.x - m} ${bb.y - m} ${bb.width + 2 * m} ${bb.height + 2 * m}`);
    }

    root.TMBox3D = { render, overlay };
})(window);
