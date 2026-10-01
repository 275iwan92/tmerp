// PrintDoc = kelas induk semua cetakan dokumen pendek. Tata letak (kop, judul, nomor, tanggal, status, tabel, total,
// tanda tangan, kaki) dikunci di sini; kelas turunan (documents.js) hanya mendeklarasikan field yang berbeda.
// Data masuk mentah (angka desimal, tanggal ISO); format tampilan dikerjakan di sini.
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));

const Fmt = {
    MONTHS: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
    num: (v, d = 2) => Number(v).toLocaleString('id-ID', {minimumFractionDigits: d, maximumFractionDigits: d}),
    date: iso => { if (!iso) return ''; const [y, m, d] = iso.split('-'); return `${d}-${Fmt.MONTHS[m - 1]}-${y}`; },
    say(n) {
        const s = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'], f = Math.floor;
        if (n < 12) return s[n];
        if (n < 20) return s[n - 10] + ' belas';
        if (n < 100) return s[f(n / 10)] + ' puluh ' + Fmt.say(n % 10);
        if (n < 200) return 'seratus ' + Fmt.say(n - 100);
        if (n < 1e3) return s[f(n / 100)] + ' ratus ' + Fmt.say(n % 100);
        if (n < 2e3) return 'seribu ' + Fmt.say(n - 1e3);
        if (n < 1e6) return Fmt.say(f(n / 1e3)) + ' ribu ' + Fmt.say(n % 1e3);
        if (n < 1e9) return Fmt.say(f(n / 1e6)) + ' juta ' + Fmt.say(n % 1e6);
        return Fmt.say(f(n / 1e9)) + ' miliar ' + Fmt.say(n % 1e9);
    },
    words(v) {
        const whole = Math.floor(v), cents = Math.round((v - whole) * 100);
        const t = (Fmt.say(whole) + ' rupiah' + (cents ? ' ' + Fmt.say(cents) + ' sen' : '')).replace(/\s+/g, ' ').trim();
        return t.charAt(0).toUpperCase() + t.slice(1);
    },
};

class PrintDoc {
    static ROWS = {a5: 5, a4: 22};                  // kapasitas baris per lembar
    static EXTRA_WIDTH = {plain: 84, priced: 38};   // mm, lebar zona kolom varian per jenis dokumen
    // Dokumen berharga: HARGA dan SUBTOTAL selalu di posisi yang sama, di kanan zona varian.
    static PRICE_COLS = [{key: 'price', label: 'HARGA', width: 21, type: 'money'}, {key: 'subtotal', label: 'SUBTOTAL', width: 25, type: 'money'}];
    // Kop: dokumen Non PPN = Telasih Makmur; dokumen PPN = badan hukum + NPWP (keputusan user 2026-09-23).
    // Alamat Telasih Makmur = alamat CV. Langgeng Jaya Makmur (keterangan user 2026-10-01).
    static ADDRESS = ['Pondok Blimbing Indah Blok A5 No. 5, Malang 65126', 'Telp 62-341-471268 &middot; Fax 62-341-471268'];
    // Telasih Makmur tidak memakai NPWP; NPWP hanya di kop dokumen PPN (CV. Langgeng Jaya Makmur).
    static company = {initials: 'TM', name: 'TELASIH MAKMUR', lines: ['packaging solution', ...PrintDoc.ADDRESS]};
    static companyPpn = {initials: 'LJM', name: 'CV. LANGGENG JAYA MAKMUR', lines: PrintDoc.ADDRESS, missing: 'NPWP (Settings &rarr; Identification)'};
    static registry = {};
    static register(c) { PrintDoc.registry[c.name] = c; return c; }

    // ---- yang boleh berbeda per jenis dokumen ----
    static title = '';
    static subtitle = '';
    static numberLabel = 'No';
    static refs = [];                   // 5 slot kiri (baris 2-6): {key, label, hand?, type?}
    static partyLabel = '';             // kanan baris 2-5: nama + 3 baris alamat
    static side = null;                 // 1 slot kanan (baris 6)
    static nb = '';
    static nameLabel = 'NAMA BARANG';
    static extra = [];                  // kolom zona varian: {key, label, width(mm), type: text|date|money|int, hand?}
    static priced = false;              // true = kolom harga + kotak total rupiah + terbilang
    static receivers = [];              // tanda tangan slot 1-2: [peran, keterangan]
    static internal = ['', '', ''];     // keterangan di bawah Mengetahui, Menyetujui, Pembuat
    static signReversed = false;        // true = Pembuat, Mengetahui, Menyetujui dari kiri, pihak luar paling kanan (PO)
    static qrHint = 'dokumen online';

    constructor(data) {
        this.d = data;
        this.f = this.constructor;
        const w = this.f.extra.reduce((t, c) => t + c.width, 0), must = PrintDoc.EXTRA_WIDTH[this.f.priced ? 'priced' : 'plain'];
        if (w !== must) throw new Error(`${this.f.name}: lebar kolom varian ${w} mm, harus ${must} mm`);
        this.cols = [...this.f.extra, ...(this.f.priced ? PrintDoc.PRICE_COLS : [])];
    }

    // Aturan kertas: 1-5 baris = A5 landscape, lebih dari 5 = A4 portrait.
    get paper() { return this.d.rows.length <= PrintDoc.ROWS.a5 ? 'a5' : 'a4'; }

    get company() { return this.d.ppn > 0 ? PrintDoc.companyPpn : PrintDoc.company; }

    val(def, src = this.d) {
        const v = src[def.key];
        if (v == null || v === '') return def.hand ? '<span class="hand"></span>' : '&mdash;';
        return def.type === 'date' ? Fmt.date(v) : def.type === 'money' ? Fmt.num(v) : def.type === 'int' ? Fmt.num(v, 0) : esc(v);
    }

    row(def) { return def && def.label ? `<div class="f" data-k="${def.key}"><span>${esc(def.label)}</span><i>:</i><b>${this.val(def)}</b></div>` : '<div class="f"></div>'; }

    kop() {
        const c = this.company;
        return `<header class="kop">
            <div class="co"><span class="logo">${c.initials}</span><div><b>${c.name}</b>${c.lines.map(l => `<small>${l}</small>`).join('')}${c.missing ? `<small class="ph">${c.missing}</small>` : ''}</div></div>
            <h1>${this.f.title}${this.f.subtitle ? `<small>${this.f.subtitle}</small>` : ''}</h1>
            <div class="kopr"><small>Scan untuk<br>${this.f.qrHint}</small><div class="qr">${PrintDoc.qr()}</div></div>
        </header>`;
    }

    info() {
        const d = this.d, p = d.party ?? {}, slot = i => this.row(this.f.refs[i]);
        const line = i => `<div class="f addr"><span></span><i></i><b>${esc(p.lines?.[i])}</b></div>`;
        return `<section class="info">
            <div>
                <div class="f big" data-k="number"><span>${this.f.numberLabel}</span><i>:</i><b>${esc(d.number)}</b></div>
                ${[0, 1, 2, 3, 4].map(slot).join('')}
            </div>
            <div>
                <div class="f st" data-k="date"><span>Tanggal</span><i>:</i><b>${Fmt.date(d.date)}</b><span class="stamp">${esc(d.status)}</span></div>
                <div class="f" data-k="party"><span>${this.f.partyLabel}</span><i>:</i><b><strong>${esc(p.name)}</strong>${p.code ? ' &middot; ' + esc(p.code) : ''}</b></div>
                ${line(0)}${line(1)}${line(2)}${this.row(this.f.side)}
            </div>
        </section>`;
    }

    table() {
        const x = this.cols, n = PrintDoc.ROWS[this.paper], cls = c => c.type === 'money' || c.type === 'int' ? 'num' : c.hand ? 'hd' : c.wrap ? 'wr' : '';
        const head = `<th></th><th>${this.f.nameLabel}</th><th class="num">JUMLAH</th><th>SATUAN</th>` + x.map(c => `<th class="${cls(c)}">${c.label}</th>`).join('');
        const cell = (c, r) => `<td class="${cls(c)}">${r[c.key] == null || r[c.key] === '' ? '' : this.val(c, r)}</td>`;
        const body = Array.from({length: n}, (_, i) => {
            const r = this.d.rows[i];
            // Nama: 2 baris. Tanpa `sub` nama boleh turun baris; dengan `sub` baris kedua = keterangan kecil.
            return `<tr><td class="no">${i + 1}.</td>` + (r
                ? `<td class="nm ${r.sub ? 'two' : ''}"><div class="n1">${esc(r.name)}</div>${r.sub ? `<div class="n2">${esc(r.sub)}</div>` : ''}</td>
                   <td class="num">${r.qty == null ? '' : Fmt.num(r.qty)}</td><td>${esc(r.unit)}</td>` + x.map(c => cell(c, r)).join('')
                : '<td></td>'.repeat(3 + x.length)) + '</tr>';
        }).join('');
        return `<table class="items"><colgroup><col style="width:7mm"><col><col style="width:21mm"><col style="width:15mm">${x.map(c => `<col style="width:${c.width}mm">`).join('')}</colgroup>
            <thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
    }

    // Kotak total: [label, teks, tebal?]. Hanya dokumen berharga; dokumen tanpa nilai uang (Surat Jalan, dst) tidak
    // mencetak total (keputusan user 2026-10-01). Zona penutup tetap setinggi itu supaya tanda tangan tidak bergeser.
    totals() {
        const rows = this.d.rows;
        if (!this.f.priced) return {lines: []};
        const r2 = v => Math.round(v * 100) / 100, sub = r2(rows.reduce((t, r) => t + +r.subtotal, 0)), disc = r2(sub * (this.d.disc ?? 0) / 100), dpp = sub - disc;
        const ppn = r2(dpp * (this.d.ppn ?? 0) / 100), pph = r2(dpp * (this.d.pph ?? 0) / 100), grand = r2(dpp + ppn - pph);
        const lines = [['Subtotal', Fmt.num(sub)]];
        if (disc) lines.push([`Diskon ${this.d.disc}%`, `(${Fmt.num(disc)})`]);
        lines.push([`PPN ${this.d.ppn ?? 0}%`, Fmt.num(ppn)]);
        if (pph) lines.push([`PPh 23 ${this.d.pph}%`, `(${Fmt.num(pph)})`]);
        lines.push(['TOTAL (IDR)', Fmt.num(grand), true]);
        return {lines, grand};
    }

    tail() {
        const t = this.totals(), notes = [...(t.grand ? [['Terbilang', `# ${Fmt.words(t.grand)} #`]] : []), ...(this.d.notes ?? [])];
        return `<section class="tail">
            <div class="notes">${notes.map(([k, v]) => `<p><b>${esc(k)}:</b> ${esc(v)}</p>`).join('')}</div>
            <div class="tot">${t.lines.map(([k, v, g]) => `<div class="${g ? 'g' : ''}"><span>${esc(k)}</span><b>${esc(v)}</b></div>`).join('')}</div>
        </section>`;
    }

    // Menyetujui dan Pembuat mencetak nama orang dari dokumen (approved_by / created_by) secara otomatis.
    sign() {
        const [r1, r2] = this.f.receivers, [a, b, c] = this.f.internal;
        const slot = s => s ? `<div><span>${esc(s[0])}</span><small>${esc(s[1])}</small><i></i><b>(${s[2] ? `<u class="nm">${esc(s[2])}</u>` : '<u></u>'})</b></div>` : '<div></div>';
        const know = ['Mengetahui', a, this.d.knownBy], agree = ['Menyetujui', b, this.d.approvedBy], maker = ['Pembuat', c, this.d.createdBy];
        return `<section class="sign">${(this.f.signReversed ? [maker, know, agree, r2, r1] : [r1, r2, know, agree, maker]).map(slot).join('')}</section>`;
    }

    foot() {
        // Nama di kaki = nama Pembuat di tanda tangan, satu sumber (createdBy, nama orang bukan login) supaya selalu sama;
        // tanpa nama aplikasi (keputusan user 2026-10-01).
        return `<footer class="foot"><span>Dicetak pada ${esc(this.d.printedAt)} oleh ${esc(this.d.createdBy)}</span>
            <span>Lembar 1/1</span></footer>`;
    }

    render() {
        return `<div class="sheet ${this.paper}">${this.kop()}${this.info()}<p class="nb">${this.f.nb}</p>${this.table()}${this.tail()}${this.sign()}${this.foot()}</div>`;
    }

    // Teks sel yang terlalu panjang dikecilkan bertahap sampai muat (batas 6,5 pt), bukan dipotong.
    static fit(root) {
        const shrink = (el, over) => { let s = parseFloat(getComputedStyle(el).fontSize) * .75; while (over() && s > 6.5) el.style.fontSize = (s -= .25) + 'pt'; };
        root.querySelectorAll('.items .n1, .items .n2').forEach(el => shrink(el, () => el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1));
        root.querySelectorAll('.items td:not(.nm):not(.no):not(.wr)').forEach(td => shrink(td, () => td.scrollWidth > td.clientWidth));
    }

    // QR tiruan untuk mockup (di aplikasi: QR asli dari x-dl::doc-share).
    static qr() {
        const N = 25; let seed = 20261001, out = '';
        const rnd = () => (seed = (seed * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff, F = [[0, 0], [N - 7, 0], [0, N - 7]];
        const finder = (x, y) => F.some(([fx, fy]) => x >= fx - 1 && x <= fx + 7 && y >= fy - 1 && y <= fy + 7);
        for (let y = 0; y < N; y++) for (let x = 0; x < N; x++) if (!finder(x, y) && rnd() > .52) out += `<rect x="${x}" y="${y}" width="1" height="1"/>`;
        F.forEach(([x, y]) => { out += `<path fill-rule="evenodd" d="M${x} ${y}h7v7h-7zM${x + 1} ${y + 1}v5h5v-5z"/><rect x="${x + 2}" y="${y + 2}" width="3" height="3"/>`; });
        return `<svg viewBox="0 0 ${N} ${N}" width="100%" height="100%" shape-rendering="crispEdges">${out}</svg>`;
    }

    // Mode deviasi: lingkaran merah bernomor pada elemen + legenda di bawah lembar.
    static annotate(root, dev) {
        dev.marks.forEach(([n, sel, left, top]) => {
            const el = root.querySelector(sel); if (!el) return;
            el.classList.add('rel'); el.insertAdjacentHTML('afterbegin', `<i class="mk" style="left:${left}mm;top:${top}mm">${n}</i>`);
        });
        root.insertAdjacentHTML('beforeend', `<div class="legend"><h2>${dev.title}</h2>${dev.legend.map((t, i) => `<p><i>${i + 1}</i>${t}</p>`).join('')}</div>`);
    }
}
