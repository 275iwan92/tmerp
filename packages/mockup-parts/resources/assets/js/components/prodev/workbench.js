/* MockupParts · ProDev · ProdevWorkbench — Imposition Plano Optimizer (tmerp pages/production/prodev.blade.php).
   Isi run() = salinan PERSIS skrip tmerp (zona beku: esensi & flow tidak diubah). Yang dijadikan method kelas
   hanya titik sambung ke luar, supaya turunan cukup meng-override:
     data           : data awal (jobs, machines, dieCutters, planos, lookups, gripper/margin) dari data-config
     server(m, ...a): panggilan server (saveLayout, generateSpec, saveArtworkColors, searchMaterials,
                      createPurchase, subcontract) — di sini jawaban CONTOH tanpa database
     uploadArtwork(): upload artwork job aktif (tmerp: Livewire WithFileUploads) */
(function (Devlib) {
  'use strict';

  class ProdevWorkbench extends Devlib.Component {
    static selector = '[data-mp-prodev]';

    init() {
      this.data = JSON.parse(this.el.dataset.config);
      const real = this.el.querySelector('#io-artFileReal');
      if (real) real.addEventListener('change', () => { if (real.files[0]) this.uploadArtwork(real.files[0]); real.value = ''; });
      this.run();
    }

    // Adaptor bentuk Livewire (call/set) supaya kode tmerp di run() tidak perlu diubah.
    wire() {
      return {call: (m, ...a) => this.data.serverUrl ? this.post(m, a) : Promise.resolve(this.server(m, ...a)), set: (k, v) => { this.data[k] = v; }};
    }

    job() {
      return (this.data.jobs || [])[this.data.initialJob || 0] || {};
    }

    server(method, ...args) {
      const job = this.job(), mc = job.design || job.itemCode || 'MC-DEMO';
      switch (method) {
        case 'saveLayout': {
          const confirm = !!args[1];
          return {ok: true, confirmed: confirm, mastercardCode: mc, knifeCode: 'KN-' + String(job.partId || 0).padStart(5, '0'),
            hasInquiry: !!job.canUpload, specSkipped: [], costingUrl: confirm ? (this.data.costingUrl || null) : null};
        }
        case 'generateSpec': return this.data.spec || {ok: false};
        case 'saveArtworkColors': return args[1];
        case 'searchMaterials': {
          const q = String(args[0] || '').toLowerCase();
          return (this.data.materials || []).filter(m => (m.material_name + ' ' + m.material_code).toLowerCase().includes(q)).slice(0, 20);
        }
        case 'createPurchase': return {ok: true, message: 'PR contoh dibuat (mockup, tanpa database).', pr: 'PR-DEMO-' + Date.now().toString().slice(-4)};
        case 'subcontract': return {ok: true, message: 'Subkon contoh dicatat (mockup, tanpa database).', pr: 'PR-DEMO-' + Date.now().toString().slice(-4), do: 'DO-DEMO'};
        default: return null;
      }
    }

    // Dengan serverUrl (layar v2 di atas database): method komponen tmerp yang sama lewat route v2.prodev.call.
    async post(method, args) {
      const r = await fetch(this.data.serverUrl, {method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.data.csrf},
        body: JSON.stringify({method, args})});
      const res = await r.json().catch(() => null);
      if (!r.ok) return {ok: false, message: (res && res.message) || ('Server error ' + r.status)};
      return res;
    }

    // Tanpa server: file dibaca di browser; analisa warna berjalan di sisi browser (colors null).
    async uploadArtwork(file) {
      if (this.data.uploadUrl) {
        const f = new FormData();
        f.append('file', file);
        const r = await fetch(this.data.uploadUrl, {method: 'POST', body: f, credentials: 'same-origin',
          headers: {Accept: 'application/json', 'X-CSRF-TOKEN': this.data.csrf}});
        const res = await r.json().catch(() => null);
        if (!r.ok || !res || !res.ok) { alert((res && res.message) || ('Upload gagal (' + r.status + ')')); return; }
        window.dispatchEvent(new CustomEvent('prodev-artwork-updated', {detail: res}));
        return;
      }
      const job = this.job();
      window.dispatchEvent(new CustomEvent('prodev-artwork-updated', {detail: {partId: job.partId, url: URL.createObjectURL(file), name: file.name, colors: null}}));
    }

    run() {
      const self = this;
      const DATA = self.data;
      const products = DATA.jobs;
      // Index 4 (machine id) / index 3 (plano id) ditambahkan supaya saveLayout()
      // bisa kirim plano_size_id yang benar ke server -- sebelumnya array ini
      // cuma bawa angka tampilan, id-nya hilang begitu sampai di client.
      const machines = DATA.machines.map(m => [m.name, m.L, m.W, m.est, m.id]);
      const planos = DATA.planos.map(p => [p.name, p.L, p.W, p.id]);
      // L/W bisa null = ukuran meja plong belum diisi di Master Mesin.
      const diecutters = (DATA.dieCutters || []).map(d => [d.name, d.L, d.W, d.id]);
      const GRIPPER = (DATA.gripperMm || 0) / 10;   // cm
      const MARGIN = (DATA.marginMm || 0) / 10;     // cm

      // Sistem koordinat gambar. Dulu dipatok 650x500: begitu area canvas
      // dibuat selebar layar, gambarnya TIDAK ikut membesar -- rasionya
      // terkunci, jadi yang bertambah cuma ruang kosong kiri-kanan. Sekarang
      // lebar viewBox mengikuti bentuk wadahnya, sehingga artwork benar-benar
      // memakai ruang yang ada (permintaan user: artwork besar & mudah di-drag).
      // Tingginya tetap 500 supaya skala angka di seluruh kode tidak berubah.
      const VB = { w: 650, h: 500 };
      const PAD_X = 50, PAD_Y = 70;   // sisa ruang tepi di dalam viewBox

      function usableVB() {
          return [VB.w - PAD_X, VB.h - PAD_Y];
      }

      function fitViewBox() {
          const box = $('viewport').getBoundingClientRect();
          if (!box.width || !box.height) return false;

          const lebarBaru = Math.round(Math.max(420, Math.min(2400, VB.h * box.width / box.height)));
          if (lebarBaru === VB.w) return false;

          VB.w = lebarBaru;
          $('svg').setAttribute('viewBox', '0 0 ' + VB.w + ' ' + VB.h);

          return true;
      }
      if (!products.length || !machines.length || !planos.length) {
          const box = document.getElementById('io-valid');
          box.className = 'io-notice bad';
          // Alasan spesifik dari server (mis. part belum punya dimensi dieline)
          // lebih berguna daripada pesan umum -- jangan biarkan layar diam.
          box.textContent = DATA.jobIssue
              || 'Data master belum lengkap: butuh minimal 1 part berdimensi, 1 mesin cetak, dan 1 ukuran plano aktif.';
          return;
      }

      const $ = x => document.getElementById('io-' + x);
      const p0 = products[DATA.initialJob] || products[0];
      const m0 = machines[0];
      // Canvas awal tidak boleh lahir dalam keadaan invalid: history job bisa
      // lebih besar dari bed mesin aktif, jadi dipotong ke bed.
      const S = {
          p: p0, m: m0,
          L: Math.min(p0.history[0] || m0[1], m0[1]),
          W: Math.min(p0.history[1] || m0[2], m0[2]),
          g: p0.gap || .3,
          zoom: 1, auto: true, manualUp: 6, objs: [], selected: null, pl: planos[0], drag: null,
          conv: 'pcs', touched: false, sc0: 0, sx0: 0, sy0: 0,
          d: diecutters[0] || null,
          // Batas canvas (user 2026-10-01): CUSTOM = ikut dieline, tanpa batas meja (bawaan part yang belum punya
          // layout); PRESS / DIECUT / BOTH = batas meja. Layout lama tanpa catatan = BOTH (aturan 16-Sep).
          limit: ['CUSTOM', 'PRESS', 'DIECUT', 'BOTH'].includes(p0.canvasLimit) ? p0.canvasLimit : 'CUSTOM',
      };
      const NOLIM = 1e6;   // cm -- "tanpa batas meja"
      const app = self.el;

      // Jembatan ke komponen Livewire induk: layar ini client-side, tapi
      // upload artwork tetap lewat Livewire (lihat updatedArtworkUpload()).
      function wire() {
          return self.wire();
      }
      function syncActiveJob() {
          const w = wire();
          if (w) w.set('activeJobPartId', S.p.partId, false);
      }

      // Susunan object di canvas dipegang dalam satuan PIXEL SVG (skala
      // S.sc0 + offset S.sx0/sy0 yang berubah tiap canvas di-resize) --
      // dikembalikan ke CM relatif sudut canvas sebelum dikirim ke server,
      // supaya isi layout_detail tetap bermakna walau nanti dibuka di
      // zoom/ukuran layar berbeda.
      function gatherState() {
          const sc = S.sc0 || 1;
          const cu = S.L * S.W ? upValid() * S.p.l * S.p.w / (S.L * S.W) * 100 : 0;
          const c = cut(S.pl);
          const sah = S.objs.filter(inCanvas);
          const b = (sah.length ? sah : S.objs).map(obox);

          return {
              partId: S.p.partId,
              canvasL: S.L,
              canvasW: S.W,
              canvasLimit: S.limit,
              gapCm: S.g,
              upCount: upValid(),
              method: S.touched ? 'MANUAL_NESTING' : 'AUTO_GRID',
              planoId: S.pl[3] || null,
              // Efisiensi yang disimpan = pemakaian material MENYELURUH
              // (canvas utilization x plano yield), bukan cuma utilisasi
              // canvas -- ini angka yang dipakai Costing utk waste.
              efficiencyPct: +(cu * (c.util || 0)).toFixed(2),
              machine: { id: S.m[4] || null, name: S.m[0], bedL: S.m[1], bedW: S.m[2] },
              // Ukuran pisau = bentang seluruh susunan di canvas.
              knifeW: b.length ? +((Math.max(...b.map(v => v.x + v.w)) - Math.min(...b.map(v => v.x))) / sc).toFixed(2) : null,
              knifeH: b.length ? +((Math.max(...b.map(v => v.y + v.h)) - Math.min(...b.map(v => v.y))) / sc).toFixed(2) : null,
              spec: specCtx(),
              specOverride: overridesOf(S.p),
              specReset: S.p.reset || [],
              objects: S.objs.map(o => ({
                  x: +((o.x - S.sx0) / sc).toFixed(2),
                  y: +((o.y - S.sy0) / sc).toFixed(2),
                  w: +(o.w / sc).toFixed(2),
                  h: +(o.h / sc).toFixed(2),
                  r: o.r, fx: o.fx, fy: o.fy,
              })),
          };
      }

      // Kanvas & spec bukan field form biasa -> dirty-guard pakai adaptor:
      // baseline per job diambil tepat sebelum interaksi user pertama
      // (hasil auto-engine saat load tidak dihitung perubahan).
      const savedState = {};
      let persistOk = false;
      const stateKey = () => JSON.stringify(gatherState());

      function registerDirtyGuard() {
          if (!window.tmerpDirty) return;
          ['pointerdown', 'keydown'].forEach(t => document.addEventListener(t, (e) => {
              if (e.isTrusted && S.p.partId && savedState[S.p.partId] === undefined) savedState[S.p.partId] = stateKey();
          }, true));
          window.tmerpDirty.register({
              isDirty: () => !!S.p.partId && savedState[S.p.partId] !== undefined && savedState[S.p.partId] !== stateKey(),
              save: async () => { persistOk = false; await persist(false); return persistOk; },
          });
      }

      async function persist(confirm) {
          const w = wire();

          if (!w) { msg('Komponen belum siap, coba lagi sebentar.', 'bad'); return; }
          if (!S.p.partId) { msg('Job demo tidak bisa disimpan — pilih job nyata dari daftar Part.', 'bad'); return; }
          if (!ok()) { msg('Canvas masih di luar batas mesin — perbaiki dulu sebelum simpan.', 'bad'); return; }

          const btn = confirm ? $('confirm') : $('save');
          const label = btn.innerHTML;   // tombol DevLib berisi ikon + label
          btn.disabled = true;
          btn.textContent = confirm ? 'Menyimpan & confirm…' : 'Menyimpan…';

          try {
              const res = await w.call('saveLayout', gatherState(), confirm);

              if (!res || !res.ok) {
                  msg((res && res.message) || 'Gagal menyimpan layout.', 'bad');
                  return;
              }

              // Yang tersimpan sekarang jadi acuan: reset sudah dieksekusi,
              // override yang tersimpan berstatus OVERRIDE.
              if (res.confirmed) S.p.confirmed = true;
              if ($('mcModal').classList.contains('open')) openMasterCard();
              S.p.reset = [];
              SECS.forEach(k => { if (S.p.edit && S.p.edit[k]) { S.p.editSrc[k] = 'OVERRIDE'; S.p.saved = S.p.saved || {}; S.p.saved[k] = { source: 'OVERRIDE', rows: S.p.edit[k] }; } });
              specKey = ''; specSoon();
              persistOk = true;
              savedState[S.p.partId] = stateKey();

              if (!confirm) {
                  msg('Layout tersimpan sebagai DRAFT (' + res.mastercardCode + ', ' + res.knifeCode + ')'
                      + (res.hasInquiry ? ' — master + snapshot Inquiry, termasuk BOM/Ink/BOP.' : ' — master saja, job ini tidak punya Inquiry asal.')
                      + (res.specSkipped && res.specSkipped.length ? ' Tidak ditimpa: ' + res.specSkipped.join('; ') + '.' : ''),
                      res.specSkipped && res.specSkipped.length ? 'info' : 'ok');
                  return;
              }

              if (res.costingUrl) {
                  msg('Layout CONFIRMED (' + res.mastercardCode + ') — membuka Product Costing…', 'ok');
                  window.location.href = res.costingUrl;
                  return;
              }

              msg('Layout CONFIRMED (' + res.mastercardCode + '). Job ini Mode #2 (tanpa Inquiry), '
                  + 'jadi belum ada layar Product Costing yang bisa dituju.', 'ok');
          } catch (e) {
              msg('Gagal menyimpan: ' + (e && e.message ? e.message : e), 'bad');
          } finally {
              btn.disabled = false;
              btn.innerHTML = label;
              docState();
          }
      }

      // Badge status di panel atas (istilah baku form: Draft / Confirmed).
      function docState() {
          const b = $('docState');
          b.textContent = S.p.confirmed ? 'Confirmed' : 'Draft';
          b.className = 'dl-badge dl-badge--' + (S.p.confirmed ? 'success' : 'neutral');
      }

      // Salinan online / e-mail Master Card tidak memuat prodev.css -> aturan .io-mc-* ikut di dalam lembarnya.
      function mcInlineCss() {
          const sheet = document.querySelector('.io-mc-sheet');
          if (!sheet || sheet.querySelector('style[data-mc]')) return;
          let css = '';
          for (const ss of document.styleSheets) {
              try { for (const r of ss.cssRules) if (r.selectorText && r.selectorText.includes('.io-mc-')) css += r.cssText; } catch (e) { /* stylesheet lintas-asal */ }
          }
          const st = document.createElement('style');
          st.setAttribute('data-mc', ''); st.textContent = css;
          sheet.prepend(st);
      }

      // Print ▾ / Email ▾ di panel atas: Master Card = pratinjau ini (Email -> dialog e-mail doc-share dengan lembar ini).
      function panelMenu(act) {
          if (act !== 'print-mc' && act !== 'email-mc') return;
          openMasterCard();
          if (act === 'email-mc') {
              mcInlineCss();
              const ds = $('mcShare'), share = ds && ds.dlDocShare;
              if (!share) return;
              // Master Card digambar di layar ini (teksnya tidak ikut kamus), jadi bahasa cetak = bahasa layar:
              // pilihan bahasa di dialog e-mail dibuang supaya tidak mengirim lembar kosong dari salinan halaman.
              share.lang = Devlib.h.NUM.lang;
              if (share.mail) share.mail.querySelectorAll('[data-ds-langs]').forEach(x => x.remove());
              share.run('email');
          }
      }

      // ===================== AUTO NESTING (interlock sadar-bentuk) =====================
      // Cara kerja (opsi B yang dipilih user 2026-09-16): dieline ASLI
      // (DSL dieline_spec, renderer TMDieline yang sama dgn canvas) di-
      // rasterisasi jadi peta piksel 1 px = 1 mm, lalu dua salinan didorong
      // saling mendekat sampai PERSIS bersentuhan -- meniru cara officer
      // pre-press mendorong dua blank sampai mentok. Jarak mentok dihitung
      // lewat profil tepi (skyline) per kolom/baris, jadi O(lebar) bukan
      // O(luas): eksak untuk translasi, dan cukup cepat buat dipanggil
      // berkali-kali saat menjajal banyak pola.
      //
      // Pola yang dijajal: grid biasa, selang-seling 180° antar BARIS, dan
      // selang-seling 180° antar KOLOM (ini yang menghasilkan susunan
      // berhadapan seperti contoh user), masing-masing dgn 4 nilai geseran
      // samping + 2 orientasi dasar (0°/90°). Yang menang = up terbanyak.
      //
      // Hasilnya SARAN, bukan paksaan (pola "engine suggests, officer
      // confirms") -- object tetap bisa digeser manual sesudahnya, dan
      // rotasi tiap object ikut tersimpan lewat Save/Confirm.
      const MASK_RES = 1;      // px per mm
      const MASK_MAX = 700;    // batas sisi bitmap, jaga-jaga blank raksasa
      const maskCache = new Map();

      function rasterize(svg, w, h) {
          return new Promise((resolve, reject) => {
              const img = new Image();
              const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));
              img.onload = () => {
                  const cv = document.createElement('canvas');
                  cv.width = w; cv.height = h;
                  const ctx = cv.getContext('2d', { willReadFrequently: true });
                  ctx.drawImage(img, 0, 0, w, h);
                  URL.revokeObjectURL(url);
                  resolve(ctx.getImageData(0, 0, w, h));
              };
              img.onerror = e => { URL.revokeObjectURL(url); reject(e); };
              img.src = url;
          });
      }

      function solidMask(w, h) {
          return { w, h, data: new Uint8Array(w * h).fill(1), shaped: false };
      }

      // Garis dieline itu OUTLINE (stroke), bukan bidang terisi -- jadi
      // badan blank dicari dgn flood-fill dari pinggir: apa pun yang TIDAK
      // terjangkau dari luar = bagian dalam blank. Stroke ditebalkan dulu
      // supaya celah kecil antar segmen DSL tidak bikin isian bocor keluar.
      function maskFromPixels(px, w, h) {
          const ink = new Uint8Array(w * h);
          for (let i = 0; i < w * h; i++) ink[i] = px.data[i * 4 + 3] > 40 ? 1 : 0;

          const ext = new Uint8Array(w * h);
          const stack = [];
          const push = (x, y) => {
              if (x < 0 || y < 0 || x >= w || y >= h) return;
              const i = y * w + x;
              if (ext[i] || ink[i]) return;
              ext[i] = 1; stack.push(i);
          };
          for (let x = 0; x < w; x++) { push(x, 0); push(x, h - 1); }
          for (let y = 0; y < h; y++) { push(0, y); push(w - 1, y); }
          while (stack.length) {
              const i = stack.pop(), x = i % w, y = (i - x) / w;
              push(x + 1, y); push(x - 1, y); push(x, y + 1); push(x, y - 1);
          }

          const data = new Uint8Array(w * h);
          let filled = 0;
          for (let i = 0; i < w * h; i++) if (!ext[i]) { data[i] = 1; filled++; }

          // Isian terlalu tipis = outline bocor (DSL tidak tertutup rapat).
          // Jangan diam-diam pakai hasil salah -- jatuh balik ke kotak penuh.
          if (filled / (w * h) < 0.15) return null;

          return { w, h, data, shaped: true };
      }

      async function maskFor(job) {
          if (maskCache.has(job.id)) return maskCache.get(job.id);

          const scale = Math.min(1, MASK_MAX / Math.max(job.l * 10, job.w * 10));
          const w = Math.max(8, Math.round(job.l * 10 * MASK_RES * scale));
          const h = Math.max(8, Math.round(job.w * 10 * MASK_RES * scale));
          let mask = null;

          if (job.dieline && window.TMDieline) {
              try {
                  // Elemen BLEED itu kotak penuh mengelilingi blank, BUKAN garis
                  // potong -- kalau ikut dirender, flood-fill langsung menganggap
                  // seluruh kotak sebagai badan dan bentuk aslinya hilang (jadi
                  // tidak pernah ada peluang interlock). Siluet hanya ditentukan
                  // garis CUT, jadi bleed dibuang dari salinan spec di sini.
                  const spec = Object.assign({}, job.dieline, {
                      elements: (job.dieline.elements || []).filter(e => e.t !== 'bleed' && e.k !== 'BLEED'),
                  });
                  let svg = window.TMDieline.svg(spec, job.dielineDims || {});

                  if (svg) {
                      // Buang margin bawaan viewBox TMDieline supaya bitmap 1:1
                      // dengan badan blank.
                      const vb = (svg.match(/viewBox="([^"]+)"/) || [])[1];
                      let dslW = w;
                      if (vb) {
                          const v = vb.trim().split(/\s+/).map(Number);
                          dslW = v[2] + 2 * v[0];
                          svg = svg.replace(/viewBox="[^"]+"/, 'viewBox="0 0 ' + dslW + ' ' + (v[3] + 2 * v[1]) + '"');
                      }
                      // Stroke dinaikkan mengikuti rasio penyusutan: DSL sering
                      // jauh lebih besar dari bitmap target, kalau stroke dibiarkan
                      // tipis garisnya putus-putus saat diperkecil -> flood-fill
                      // bocor keluar dan bentuknya batal terbaca.
                      const sw = Math.max(2.5, 2.5 * (dslW / Math.max(1, w)));
                      svg = svg
                          .replace(/stroke-width="[\d.]+"/g, 'stroke-width="' + sw.toFixed(2) + '"')
                          .replace(/width="100%" height="100%"/, 'width="' + w + '" height="' + h + '" preserveAspectRatio="none"');
                      mask = maskFromPixels(await rasterize(svg, w, h), w, h);
                  }
              } catch (e) { mask = null; }
          }

          if (!mask) mask = solidMask(w, h);
          mask.mmPerPx = (job.l * 10) / mask.w;
          maskCache.set(job.id, mask);

          return mask;
      }

      function rotMask(m, deg) {
          if (!deg) return m;
          const w = m.w, h = m.h;
          const rw = deg === 180 ? w : h, rh = deg === 180 ? h : w;
          const out = new Uint8Array(rw * rh);
          for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
              if (!m.data[y * w + x]) continue;
              let nx, ny;
              if (deg === 90) { nx = h - 1 - y; ny = x; }
              else if (deg === 180) { nx = w - 1 - x; ny = h - 1 - y; }
              else { nx = y; ny = w - 1 - x; }
              out[ny * rw + nx] = 1;
          }
          return { w: rw, h: rh, data: out, shaped: m.shaped, mmPerPx: m.mmPerPx };
      }

      // Tabrakan diuji EKSAK, bukan lewat profil tepi (leftmost/rightmost
      // per baris). Profil hanya sah untuk bentuk cembung: pada blank
      // BERGERIGI -- justru bentuk yang bisa saling mengunci -- profil
      // membaca gigi terluar sebagai dinding penuh, sehingga menolak
      // susunan yang sebenarnya muat. Terbukti pada contoh user: dua blank
      // ini sesungguhnya bisa berjarak 8 mm (offset vertikal 30 mm), tapi
      // versi profil menuntut 71 mm dan hasilnya cuma 1 up.
      //
      // Supaya tetap murah, tiap baris mask dikemas jadi bit (32 px per
      // word), jadi satu uji tabrakan = beberapa operasi AND per baris.
      function packMask(m) {
          if (m._p) return m._p;
          const words = (m.w + 31) >> 5;
          const rows = new Uint32Array(m.h * words);
          for (let y = 0; y < m.h; y++)
              for (let x = 0; x < m.w; x++)
                  if (m.data[y * m.w + x]) rows[y * words + (x >> 5)] |= (1 << (x & 31));
          return (m._p = { words, rows, w: m.w, h: m.h });
      }

      // Apakah A bertabrakan dengan B yang digeser dx ke kanan & sy ke bawah.
      function hits(A, B, dx, sy) {
          const PA = packMask(A), PB = packMask(B);
          const ws = PA.words, wShift = dx >> 5, bShift = dx & 31;

          for (let y = 0; y < PA.h; y++) {
              const by = y - sy;
              if (by < 0 || by >= PB.h) continue;
              const aOff = y * ws, bOff = by * PB.words;
              for (let i = 0; i < ws; i++) {
                  const j = i - wShift;
                  let bw = 0;
                  if (j >= 0 && j < PB.words) bw = PB.rows[bOff + j] << bShift;
                  if (bShift && j - 1 >= 0 && j - 1 < PB.words) bw |= PB.rows[bOff + j - 1] >>> (32 - bShift);
                  if (PA.rows[aOff + i] & bw) return true;
              }
          }
          return false;
      }

      // Jarak horizontal minimum supaya B (pada offset vertikal sy) bebas dari A.
      function minDx(A, B, sy) {
          for (let dx = 0; dx <= A.w; dx++) if (!hits(A, B, dx, sy)) return Math.max(1, dx);
          return A.w;
      }

      // Jarak vertikal minimum supaya B (pada offset horizontal sx) bebas dari A.
      function minDy(A, B, sx) {
          for (let dy = 0; dy <= A.h; dy++) if (!hits(A, B, sx, dy)) return Math.max(1, dy);
          return A.h;
      }

      // Pitch tidak boleh dihitung dari SATU pasangan saja: di dalam kisi,
      // satu object juga bertetangga dgn object di atas/bawah kolom
      // sebelahnya (offset sx ± n×pitch). Kalau cuma offset utama yang
      // diperiksa, pasangan lain bisa saling tembus -- terbukti nyata saat
      // uji (27.662 piksel bertabrakan sebelum ini diperbaiki). Jadi ambil
      // jarak TERBESAR dari beberapa offset tetangga.
      // `span` = berapa object yang BENAR-BENAR ada sepanjang sumbu itu.
      // Penting: kalau canvas cuma muat 1 object per baris/kolom, tetangga
      // ke-2/ke-3 tidak pernah ada, jadi jaraknya tidak boleh ikut
      // membatasi. Versi sebelumnya memaksa n = -2..2 tanpa syarat ->
      // jarak jadi kebesaran dan susunan yang sebetulnya muat malah
      // ditolak (kasus nyata: canvas 9,6 x 8,4 cm cuma dapat 1 up padahal
      // 2 up muat -- ditemukan dari contoh susunan manual user).
      function needDy(A, B, sx, pitchX, span) {
          const lim = Math.min(2, Math.max(0, (span || 1) - 1));
          let need = 1;
          for (let n = -lim; n <= lim; n++) need = Math.max(need, minDy(A, B, sx + n * pitchX));
          return need;
      }

      function needDx(A, B, sy, pitchY, span) {
          const lim = Math.min(2, Math.max(0, (span || 1) - 1));
          let need = 1;
          for (let n = -lim; n <= lim; n++) need = Math.max(need, minDx(A, B, sy + n * pitchY));
          return need;
      }

      // Hitung piksel garis potong yang saling tembus. earlyExit dipakai saat
      // menyaring kandidat (cukup tahu ADA/TIDAK), tanpa earlyExit dipakai
      // untuk memberi tahu user seberapa parah tabrakan susunan manualnya.
      function overlapPixels(placements, Lmm, Wmm, earlyExit) {
          const grid = new Uint8Array(Lmm * Wmm);
          let n = 0;

          for (const p of placements) {
              const m = p.m;
              for (let y = 0; y < m.h; y++) {
                  const gy = p.y + y;
                  if (gy < 0 || gy >= Wmm) continue;
                  const row = y * m.w, grow = gy * Lmm;
                  for (let x = 0; x < m.w; x++) {
                      if (!m.data[row + x]) continue;
                      const gx = p.x + x;
                      if (gx < 0 || gx >= Lmm) continue;
                      if (grid[grow + gx]) { if (earlyExit) return 1; n++; }
                      else grid[grow + gx] = 1;
                  }
              }
          }
          return n;
      }

      // Jaring pengaman: susunan yang masih saling tembus DIBUANG, tidak
      // pernah ditawarkan ke user.
      function collides(placements, Lmm, Wmm) {
          return overlapPixels(placements, Lmm, Wmm, true) > 0;
      }

      function mirrorMask(m, fx, fy) {
          if (fx > 0 && fy > 0) return m;
          const out = new Uint8Array(m.w * m.h);
          for (let y = 0; y < m.h; y++) for (let x = 0; x < m.w; x++) {
              if (!m.data[y * m.w + x]) continue;
              out[(fy < 0 ? m.h - 1 - y : y) * m.w + (fx < 0 ? m.w - 1 - x : x)] = 1;
          }
          return { w: m.w, h: m.h, data: out, shaped: m.shaped };
      }

      // Transform object di canvas: rotate(r) scale(fx fy) -- mirror berlaku
      // di koordinat lokal object, jadi dicerminkan dulu baru diputar.
      function variantFor(base, r, fx, fy) {
          const key = r + '|' + fx + '|' + fy;
          const cache = base._v || (base._v = {});
          return cache[key] || (cache[key] = rotMask(mirrorMask(base, fx, fy), r));
      }

      // Susunan manual TIDAK divalidasi apa pun sebelumnya: user bisa saja
      // menumpuk dua blank sampai garis potongnya saling makan, dan panel
      // yield tetap menampilkan angka yang kelihatan bagus (utilisasi bisa
      // >100% karena dihitung dari kotak pembatas, bukan bentuk asli). Di
      // sinilah susunan manual ikut diperiksa dengan alat yang sama seperti
      // auto nesting, supaya salah susun ketahuan langsung.
      async function checkManualOverlap() {
          if (!S.objs.length || !ok()) { S.overlapPx = 0; return; }

          try {
              const base = await maskFor(S.p);
              const sc = S.sc0 || 1;
              const placements = [];

              for (const o of S.objs) {
                  const r = ((Math.round(o.r) % 360) + 360) % 360;
                  if (r % 90) return;  // sudut bebas (tak mungkin dari tombol) -- lewati
                  const m = variantFor(base, r, o.fx, o.fy);
                  placements.push({
                      x: Math.round((o.x + o.w / 2 - S.sx0) / sc * 10 - m.w / 2),
                      y: Math.round((o.y + o.h / 2 - S.sy0) / sc * 10 - m.h / 2),
                      m,
                  });
              }

              // Diperiksa pada kotak pembatas SELURUH object, bukan pada area
              // canvas: object yang melewati tepi canvas tetap saling menabrak,
              // dan kalau gridnya sebatas canvas, tabrakan di luar tepi ikut
              // terpotong dan lolos tanpa peringatan.
              let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
              for (const p of placements) {
                  minX = Math.min(minX, p.x); minY = Math.min(minY, p.y);
                  maxX = Math.max(maxX, p.x + p.m.w); maxY = Math.max(maxY, p.y + p.m.h);
              }

              const gw = maxX - minX, gh = maxY - minY;
              if (gw <= 0 || gh <= 0 || gw * gh > 25e6) { S.overlapPx = 0; return; }

              S.overlapPx = overlapPixels(
                  placements.map(p => ({ x: p.x - minX, y: p.y - minY, m: p.m })), gw, gh, false);
              calculator();
          } catch (e) { S.overlapPx = 0; }
      }

      // Pitch tidak bergantung ukuran canvas -- cuma pada bentuk, geseran, gap,
      // dan berapa tetangga yang ada. Disimpan supaya penyapuan ribuan
      // kombinasi canvas x plano tidak menghitung ulang uji tabrakan yang sama.
      let maskSeq = 0;
      const pitchMemo = new Map();

      function maskId(m) {
          return m._id || (m._id = ++maskSeq);
      }

      function memoNeed(kind, A, B, off, pitch, span) {
          const lim = Math.min(3, Math.max(1, span || 1));
          const key = kind + '|' + maskId(A) + '|' + maskId(B) + '|' + off + '|' + pitch + '|' + lim;
          if (pitchMemo.has(key)) return pitchMemo.get(key);
          const val = kind === 'x' ? needDx(A, B, off, pitch, lim) : needDy(A, B, off, pitch, lim);
          pitchMemo.set(key, val);
          return val;
      }

      // collect=false -> hanya menghitung jumlah up (dipakai saat menyapu banyak
      // kombinasi); penempatan sesungguhnya baru dibangun untuk pemenangnya.
      function plainLattice(M, r, Lmm, Wmm, gap, label, collect) {
          const out = [];
          let n = 0;
          for (let y = 0; y + M.h <= Wmm + .001; y += M.h + gap)
              for (let x = 0; x + M.w <= Lmm + .001; x += M.w + gap) {
                  n++;
                  if (collect !== false) out.push({ x: Math.round(x), y: Math.round(y), w: M.w, h: M.h, r, m: M });
              }
          return { up: n, placements: out, label };
      }

      // Posisi tiap baris/kolom ditentukan BERTAHAP terhadap tetangga yang
      // benar-benar sudah ditempatkan (k-1 dan k-2), bukan lewat satu jarak
      // seragam + koreksi. Sebelumnya koreksi "baris k vs k+2" ditambahkan
      // tanpa syarat, padahal di canvas kecil baris ke-3 tidak pernah ada --
      // akibatnya susunan yang sebetulnya muat ikut tertolak (kasus nyata:
      // canvas 9,6 x 8,4 cm mentok 1 up padahal 2 up muat).
      function rowAltLattice(M0, M1, r, Lmm, Wmm, gap, sx, label, collect) {
          const pitchX = Math.max(M0.w, M1.w) + gap;
          const cols = Math.max(1, Math.floor((Lmm - Math.max(M0.w, M1.w)) / pitchX) + 1);
          const dAlt = [memoNeed('y', M0, M1, sx, pitchX, cols) + gap, memoNeed('y', M1, M0, -sx, pitchX, cols) + gap];
          const dSame = [memoNeed('y', M0, M0, 0, pitchX, cols) + gap, memoNeed('y', M1, M1, 0, pitchX, cols) + gap];
          const out = [], ys = [];
          let n = 0;

          for (let k = 0; k < 400; k++) {
              let y = 0;
              if (k) {
                  y = ys[k - 1] + dAlt[(k - 1) % 2];
                  if (k >= 2) y = Math.max(y, ys[k - 2] + dSame[k % 2]);
              }
              const odd = k % 2, M = odd ? M1 : M0, off = odd ? Math.max(0, sx) : 0;
              if (y + M.h > Wmm + .001) break;
              ys.push(y);
              for (let x = off; x + M.w <= Lmm + .001; x += pitchX) {
                  n++;
                  if (collect !== false) out.push({ x: Math.round(x), y: Math.round(y), w: M.w, h: M.h, r: odd ? (r + 180) % 360 : r, m: M });
              }
          }

          return { up: n, placements: out, label };
      }

      function colAltLattice(M0, M1, r, Lmm, Wmm, gap, sy, label, collect) {
          const pitchY = Math.max(M0.h, M1.h) + gap;
          const rows = Math.max(1, Math.floor((Wmm - Math.max(M0.h, M1.h)) / pitchY) + 1);
          const dAlt = [memoNeed('x', M0, M1, sy, pitchY, rows) + gap, memoNeed('x', M1, M0, -sy, pitchY, rows) + gap];
          const dSame = [memoNeed('x', M0, M0, 0, pitchY, rows) + gap, memoNeed('x', M1, M1, 0, pitchY, rows) + gap];
          const out = [], xs = [];
          let n = 0;

          for (let k = 0; k < 400; k++) {
              let x = 0;
              if (k) {
                  x = xs[k - 1] + dAlt[(k - 1) % 2];
                  if (k >= 2) x = Math.max(x, xs[k - 2] + dSame[k % 2]);
              }
              const odd = k % 2, M = odd ? M1 : M0, off = odd ? Math.max(0, sy) : 0;
              if (x + M.w > Lmm + .001) break;
              xs.push(x);
              for (let y = off; y + M.h <= Wmm + .001; y += pitchY) {
                  n++;
                  if (collect !== false) out.push({ x: Math.round(x), y: Math.round(y), w: M.w, h: M.h, r: odd ? (r + 180) % 360 : r, m: M });
              }
          }

          return { up: n, placements: out, label };
      }

      // Daftar pola + geseran yang dijajal, dihitung sekali per bentuk blank.
      function patternConfigs(base) {
          const cfgs = [];

          for (const b of [0, 90]) {
              const M0 = rotMask(base, b), M1 = rotMask(base, (b + 180) % 360);
              const tag = b ? ' · rotate 90°' : '';
              cfgs.push({ kind: 'plain', M0, M1, r: b, s: 0, label: 'Grid standar' + tag });

              const stepX = Math.max(1, Math.round(M0.w / 16)), stepY = Math.max(1, Math.round(M0.h / 16));
              for (let sx = 0; sx < M0.w; sx += stepX)
                  cfgs.push({ kind: 'row', M0, M1, r: b, s: sx, label: 'Interlock antar-baris (180°)' + (sx ? ' geser ' + Math.round(sx / M0.w * 100) + '%' : '') + tag });
              for (let sy = 0; sy < M0.h; sy += stepY)
                  cfgs.push({ kind: 'col', M0, M1, r: b, s: sy, label: 'Interlock antar-kolom (180°)' + (sy ? ' geser ' + Math.round(sy / M0.h * 100) + '%' : '') + tag });
          }

          return cfgs;
      }

      function buildLattice(cfg, Lmm, Wmm, gap, collect) {
          if (cfg.kind === 'plain') return plainLattice(cfg.M0, cfg.r, Lmm, Wmm, gap, cfg.label, collect);
          if (cfg.kind === 'row') return rowAltLattice(cfg.M0, cfg.M1, cfg.r, Lmm, Wmm, gap, cfg.s, cfg.label, collect);
          return colAltLattice(cfg.M0, cfg.M1, cfg.r, Lmm, Wmm, gap, cfg.s, cfg.label, collect);
      }

      // Pola terbaik untuk SATU ukuran canvas. Saat menyapu (collect=false)
      // tabrakan belum diuji demi kecepatan -- pemenangnya diuji belakangan.
      function bestLattice(cfgs, Lmm, Wmm, gap, collect) {
          let best = null, plainBest = 0, ditolak = 0;

          for (const cfg of cfgs) {
              const c = buildLattice(cfg, Lmm, Wmm, gap, collect);
              if (!c.up) continue;
              if (collect !== false && collides(c.placements, Lmm, Wmm)) { ditolak++; continue; }
              if (cfg.kind === 'plain' && c.up > plainBest) plainBest = c.up;
              if (!best || c.up > best.up) best = c;
          }

          return best ? Object.assign(best, { plainBest, ditolak }) : null;
      }

      function applyPlacements(placements, label, offX, offY) {
          noteShortfall(Math.min(placements.length, S.auto ? placements.length : S.manualUp));
          if (!S.auto && S.manualUp < placements.length) placements = placements.slice(0, S.manualUp);

          const dx = offX || 0, dy = offY || 0;
          const sc = S.sc0, ow = S.p.l * sc, oh = S.p.w * sc;
          S.objs = placements.map((p, i) => ({
              id: 'a' + i,
              x: S.sx0 + (p.x + dx + p.w / 2) / 10 * sc - ow / 2,
              y: S.sy0 + (p.y + dy + p.h / 2) / 10 * sc - oh / 2,
              w: ow, h: oh, r: p.r, fx: 1, fy: 1,
          }));
          S.selected = null;
          S.touched = true;
          S.manualEdit = false;
          S.autoNestInfo = label;
          S.overlapPx = 0;   // sudah lolos verifikasi collides()
          // Mode Manual Up: kekurangan object dilengkapi SEBELUM digambar,
          // kalau sesudah render object tambahannya tidak ikut tampil.
          ensureManualCount();
          render(); calculator();
      }

      // Optimasi MENYELURUH: canvas & plano ikut dipilih engine (disetujui user
      // 2026-09-16). Ukuran canvas dicoba dari pembagian plano jadi nc x nr
      // bagian sama besar -- itu cara potong guillotine yang lazim dan tidak
      // menyisakan trim. Yang diperbandingkan BUKAN up per canvas (itu memihak
      // canvas besar) melainkan LUAS BAHAN PER PCS: plano besar otomatis
      // menghasilkan lebih banyak pcs tapi juga memakan bahan lebih banyak.
      // Sejalan dengan proxy biaya yang sudah dipakai engine lama
      // (ImpositionEngine::bestSheet: luas_plano / up, karena mat_plano_size
      // memang tidak menyimpan harga).
      async function optimizeSheet() {
          const btn = $('suggest'), label = btn.textContent;
          btn.disabled = true; btn.textContent = 'Menghitung…';

          try {
              const base = await maskFor(S.p);
              const cfgs = patternConfigs(base);
              const gap = Math.max(0, Math.round(S.g * 10));
              const [limL, limW] = bedLimits();
              const bedL = limL * 10, bedW = limW * 10;
              // Area yang benar-benar bisa dicetak = canvas dikurangi gripper
              // (1 sisi jepitan) dan margin keliling. Tanpa ini optimizer
              // sempat memilih canvas 54,5 x 6 cm -- strip setinggi 6 cm yang
              // mustahil dijalankan di mesin offset.
              const grip = Math.round(GRIPPER * 10), marg = Math.round(MARGIN * 10);
              const usable = (l, w) => [l - 2 * marg, w - grip - marg];
              const upCache = new Map();
              let best = null;

              for (let pi = 0; pi < planos.length; pi++) {
                  const P = planos[pi];
                  const area = P[1] * P[2];   // cm2

                  for (const [pl, pw] of [[P[1] * 10, P[2] * 10], [P[2] * 10, P[1] * 10]]) {
                      for (let nc = 1; nc <= 14; nc++) {
                          const cl = Math.floor(pl / nc);
                          if (cl > bedL || cl < 10) continue;

                          for (let nr = 1; nr <= 14; nr++) {
                              const cw = Math.floor(pw / nr);
                              if (cw > bedW || cw < 10) continue;

                              const [ul, uw] = usable(cl, cw);
                              if (ul <= 0 || uw <= 0) continue;

                              // Banyak kombinasi (plano, nc, nr) menghasilkan ukuran
                              // canvas yang sama persis -- hasil nestingnya pun sama,
                              // jadi cukup dihitung sekali.
                              const key = ul + 'x' + uw;
                              let cand = upCache.get(key);
                              if (cand === undefined) {
                                  cand = bestLattice(cfgs, ul, uw, gap, false);
                                  upCache.set(key, cand);
                              }
                              if (!cand) continue;

                              const pcs = nc * nr * cand.up;
                              const perPc = area / pcs;   // cm2 bahan per pcs -- makin kecil makin hemat
                              if (!best || perPc < best.perPc - 1e-9) {
                                  best = { perPc, pcs, up: cand.up, planoIndex: pi, L: cl / 10, W: cw / 10, nc, nr, label: cand.label };
                              }
                          }
                      }
                  }
              }

              if (!best) { msg('Tidak ada kombinasi canvas/plano yang muat di mesin ini.', 'bad'); return; }

              const sebelum = hasilPerPlano();

              S.pl = planos[best.planoIndex];
              $('plano').value = String(best.planoIndex);
              S.L = +best.L.toFixed(1); S.W = +best.W.toFixed(1);
              $('sl').value = S.L; $('sw').value = S.W;
              S.objs = [];
              update(true);

              // Pemenang baru dibangun penempatannya + diuji tabrakan di sini,
              // lalu digeser masuk ke area cetak (gripper + margin).
              const [ul, uw] = usable(Math.round(S.L * 10), Math.round(S.W * 10));
              const win = bestLattice(cfgs, ul, uw, gap, true);
              if (win) applyPlacements(win.placements, win.label, marg, grip);

              const sesudah = hasilPerPlano();
              $('suggestNote').textContent = 'Canvas ' + S.L + ' × ' + S.W + ' cm (plano dibagi ' + best.nc + ' × ' + best.nr + ')'
                  + ' · plano ' + S.pl[0] + ' · ' + (win ? win.up : best.up) + ' up/canvas.';
              msg('Optimasi bahan: ' + S.pl[0] + ', canvas ' + S.L + ' × ' + S.W + ' cm, ' + (win ? win.label : best.label)
                  + ' → ' + sesudah + ' pcs/plano (' + best.perPc.toFixed(1) + ' cm² bahan per pcs)'
                  + (sebelum && sesudah > sebelum ? ' · sebelumnya ' + sebelum + ' pcs/plano' : ''), 'ok');
          } catch (e) {
              msg('Optimasi gagal: ' + (e && e.message ? e.message : e), 'bad');
          } finally {
              btn.disabled = false; btn.textContent = label;
          }
      }

      // Plano terbaik untuk canvas SAAT INI. Ukuran canvas sudah tetap, jadi
      // "bahan per pcs paling kecil" (aturan optimizeSheet, disetujui user
      // 2026-09-16) sama dengan sheet yield tertinggi; seri -> canvas lebih banyak.
      function bestPlanoIndex() {
          let bi = -1, bc = null;
          planos.forEach((q, i) => {
              const c = cut(q);
              if (c.n && (!bc || c.util > bc.util + 1e-9 || (Math.abs(c.util - bc.util) <= 1e-9 && c.n > bc.n))) { bi = i; bc = c; }
          });
          return bi;
      }

      // Pilih plano paling hemat untuk canvas sekarang. Hasilnya selalu
      // dilaporkan DIGANTI atau TETAP -- kalau plano lama sudah yang paling
      // hemat, dropdown memang tidak berubah dan officer mengira tidak jalan.
      function pickBestPlano() {
          const lama = S.pl[0], i = bestPlanoIndex();
          if (i < 0) return 'tidak ada plano standar yang muat canvas ' + S.L + ' × ' + S.W + ' cm';
          S.pl = planos[i]; $('plano').value = String(i);
          return lama === S.pl[0] ? 'plano tetap ' + S.pl[0] + ' (sudah paling hemat)' : 'plano diganti ' + lama + ' → ' + S.pl[0];
      }

      function hasilPerPlano() {
          if (!ok()) return 0;
          const c = cut(S.pl);
          return c.n * upValid();
      }

      // Susunan rapat dijalankan OTOMATIS saat job/mesin dibuka, bukan cuma
      // kalau tombolnya ditekan (koreksi user 2026-09-16: yang muncul pertama
      // masih grid renggang 9 up, padahal engine sanggup lebih rapat). Grid
      // polos tidak ada gunanya ditampilkan lebih dulu -- itu justru hasil
      // terburuk. Tidak dijalankan kalau user sudah menyusun manual, supaya
      // kerjaan tangannya tidak ditimpa diam-diam.
      let autoNestTimer = null;
      function autoNestSoon() {
          clearTimeout(autoNestTimer);
          autoNestTimer = setTimeout(() => {
              if (!S.touched && ok()) autoNest(true).then(samakanTinggiAcc);
          }, 120);
      }

      async function autoNest(silent) {
          if (!ok()) {
              if (!silent) msg('Canvas masih di luar batas mesin — perbaiki dulu sebelum auto nesting.', 'bad');
              return;
          }

          const btn = $('autoNest'), label = btn.textContent;
          btn.disabled = true; btn.textContent = 'Menghitung…';

          try {
              const base = await maskFor(S.p);
              const gap = Math.max(0, Math.round(S.g * 10));
              const best = bestLattice(patternConfigs(base), Math.round(S.L * 10), Math.round(S.W * 10), gap, true);

              if (!best) {
                  if (!silent) msg('Auto nesting tidak menemukan susunan yang muat di canvas ini.', 'bad');
                  return;
              }

              applyPlacements(best.placements, best.label);

              const gain = best.plainBest ? Math.round((best.up - best.plainBest) / best.plainBest * 100) : 0;
              msg('Auto nesting: ' + best.label + ' → ' + best.up + ' up'
                  + (gain > 0 ? ' (+' + gain + '% vs grid standar, ' + (best.up - best.plainBest) + ' up lebih banyak)'
                      : ' (grid standar sudah paling padat untuk bentuk ini)')
                  + (best.ditolak ? ' · ' + best.ditolak + ' pola ditolak krn masih bertabrakan' : '')
                  + (base.shaped ? '' : ' · tipe ini belum punya dieline, dihitung sebagai kotak polos'), 'ok');
          } catch (e) {
              if (!silent) msg('Auto nesting gagal: ' + (e && e.message ? e.message : e), 'bad');
          } finally {
              btn.disabled = false; btn.textContent = label;
          }
      }

      function init() {
          products.forEach((x, i) => $('product').add(new Option(x.name, i)));
          machines.forEach((x, i) => $('machine').add(new Option(x[0], i)));
          diecutters.forEach((x, i) => $('diecut').add(new Option(x[0], i)));
          planos.forEach((x, i) => $('plano').add(new Option(x[0], i)));
          $('product').value = String(products.indexOf(S.p));
          $('sl').value = S.L; $('sw').value = S.W; $('gap').value = S.g; $('grain').value = S.p.grain;
          $('back').href = DATA.backUrl || '#';
          $('back').setAttribute('data-back', '');
          registerDirtyGuard();
          askSidebarMini(true);
          accInit();
          fitViewBox();
          jobIdentity();
          docState();
          if (S.p.orderQty > 0) $('converterInput').value = S.p.orderQty;
          bindSpecEvents();
          renderSpecSections();
          bind();
          upModeUi();
          artworkLabel();
          syncActiveJob();
          $('limit').value = S.limit;
          if (S.limit === 'CUSTOM') setLimit('CUSTOM', true); else update(true);
          samakanTinggiAcc();
          autoNestSoon();
          analyzeArtwork();
      }

      // Minta shell mengecilkan sidebar selama layar ini terbuka (permintaan
      // user 2026-09-17) -- area gambar butuh lebar sebanyak mungkin. Nilai
      // sidebar sebelumnya disimpan shell dan dikembalikan saat halaman ini
      // ditinggalkan, jadi preferensi user tidak ikut hilang.
      function askSidebarMini(mini) {
          if (window.parent === window) return;   // dibuka langsung, bukan di shell
          try { window.parent.postMessage({ tmerp: 'sidebar', mini: !!mini }, window.location.origin); } catch (e) { /* abaikan */ }
      }

      window.addEventListener('pagehide', () => askSidebarMini(false));

      // Bentuk wadah berubah (sidebar mengecil, jendela di-resize) -> lebar
      // viewBox ikut disesuaikan supaya gambar tetap memakai ruang yang ada.
      let resizeTimer = null;
      window.addEventListener('resize', () => {
          clearTimeout(resizeTimer);
          resizeTimer = setTimeout(() => { samakanTinggiAcc(); if (fitViewBox()) render(), calculator(); }, 150);
      });

      // Accordion: satu bagian terbuka. Bagian terakhir yang dibuka diingat per
      // browser (bukan data bersama, jadi cukup localStorage).
      function accInit() {
          const secs = [...document.querySelectorAll('.io-sec')];
          let awal = 'impo';
          try { awal = localStorage.getItem('prodev.acc') || 'impo'; } catch (e) { /* abaikan */ }
          if (!secs.some(s => s.dataset.sec === awal)) awal = 'impo';

          const buka = key => {
              secs.forEach(s => {
                  const on = s.dataset.sec === key;
                  s.classList.toggle('open', on);
                  s.querySelector('.io-sec-head').setAttribute('aria-expanded', on ? 'true' : 'false');
              });
              try { localStorage.setItem('prodev.acc', key); } catch (e) { /* abaikan */ }
              // Tinggi baris accordion bisa berubah -> area gambar ikut menyesuaikan.
              setTimeout(() => { if (fitViewBox()) { render(); calculator(); } }, 60);
          };

          secs.forEach(s => { s.querySelector('.io-sec-head').onclick = () => buka(s.dataset.sec); });
          buka(awal);
      }

      // Tinggi accordion DIKUNCI = bagian tertinggi (user 2026-09-17: tidak
      // mau naik-turun tiap ganti bagian). Tiap bagian dibuka sesaat HANYA
      // untuk diukur -- semua sinkron sebelum browser menggambar, jadi tidak
      // berkedip. Dilewati saat layar sempit (accordion tersusun ke bawah).
      function samakanTinggiAcc() {
          const acc = document.getElementById('io-acc');
          const secs = [...acc.querySelectorAll('.io-sec')];
          acc.style.minHeight = '';
          if (window.matchMedia('(max-width: 900px)').matches) return;

          const aktif = secs.find(s => s.classList.contains('open'));
          let maks = 0;
          secs.forEach(s => {
              secs.forEach(x => x.classList.toggle('open', x === s));
              maks = Math.max(maks, acc.offsetHeight);
          });
          secs.forEach(x => x.classList.toggle('open', x === aktif));
          acc.style.minHeight = maks + 'px';
      }

      // ===================== BOM / INK / BOP (engine, BACKLOG §18) =====================
      // Ketiganya HASIL ENGINE (App\Services\ProDev\SpecEngine) dari input
      // Inquiry + keputusan layout di layar ini, dihitung ulang tiap kali
      // lembar/plano/mesin/up/qty berubah. Officer boleh meng-override tiap
      // section (tombol Edit, pakai dropdown); override tersimpan 2 arah saat
      // Save dan tidak ditimpa engine sampai dipilih "Kembali ke hasil engine".
      const LK = DATA.lookups || { processes: [], machines: [], subcons: [], uoms: [], roles: [], inks: [] };
      const SECS = ['bop', 'bom', 'inks'];
      let specTimer = null, specKey = '', specSeq = 0;

      function specInit(p) {
          if (p.edit) return;
          p.edit = {}; p.editSrc = {}; p.editing = {}; p.reset = []; p.flash = {}; p.subconAt = null;
          SECS.forEach(k => {
              const sv = p.saved && p.saved[k];
              p.edit[k] = sv ? sv.rows.map(r => ({ ...r })) : null;
              p.editSrc[k] = sv ? sv.source : null;
          });
      }

      function overridesOf(p) {
          const o = {};
          SECS.forEach(k => { if (p.edit && p.edit[k]) o[k] = p.edit[k]; });
          return o;
      }

      function specCtx() {
          const c = cut(S.pl);
          return {
              sheetL: S.L, sheetW: S.W, planoL: S.pl[1], planoW: S.pl[2], planoName: S.pl[0], sheetsPerPlano: c.n || 1,
              printMachineId: S.m[4] || null, diecutMachineId: S.d ? (S.d[3] || null) : null,
              upCount: upValid(),
              orderQty: S.conv === 'pcs' ? (+$('converterInput').value || 0) : (S.p.orderQty || 0),
              artworkColors: S.p.artColors || null,
          };
      }

      function specSoon() {
          if (!S.p.partId) return;
          specInit(S.p);
          const ctx = specCtx(), ov = overridesOf(S.p);
          const key = S.p.partId + '|' + JSON.stringify(ctx) + '|' + JSON.stringify(ov);
          if (key === specKey) return;
          clearTimeout(specTimer);
          specTimer = setTimeout(() => refreshSpec(ctx, ov, key), 450);
      }

      async function refreshSpec(ctx, ov, key) {
          const w = wire();
          if (!w) return;
          const my = ++specSeq, job = S.p;
          try {
              const res = await w.call('generateSpec', job.partId, ctx, ov);
              if (my !== specSeq || S.p !== job || !res || !res.ok) return;
              specKey = key;
              job.spec = res;
              renderSpecSections();
          } catch (e) { /* tampilan terakhir dibiarkan */ }
      }

      // ---- Analisa warna artwork: gambar yang SAMA dengan canvas (file upload
      // atau artwork vector sampel) dirasterisasi, lalu tiap piksel diurai ke
      // C/M/Y/K (tutupan tinta) dan dikelompokkan per nama warna (luas warna).
      // Persen dihitung terhadap SELURUH bidang blank, jadi server tinggal
      // mengalikan dengan luas blank untuk dapat cm².
      const ANALYSIS_PX = 360;

      function artSource(p) {
          if (p.artworkUrl) return 'file:' + p.artworkUrl;
          if (p.type) return 'sample:' + p.type + ':' + p.l + 'x' + p.w;
          return null;
      }

      function loadArtPixels(p) {
          return new Promise((resolve, reject) => {
              const k = ANALYSIS_PX / Math.max(p.l, p.w);
              const W = Math.max(1, Math.round(p.l * k)), H = Math.max(1, Math.round(p.w * k));
              const img = new Image();
              let url = null;
              img.onload = () => {
                  const cv = document.createElement('canvas');
                  cv.width = W; cv.height = H;
                  const cx = cv.getContext('2d', { willReadFrequently: true });
                  cx.drawImage(img, 0, 0, W, H);
                  if (url) URL.revokeObjectURL(url);
                  try { resolve(cx.getImageData(0, 0, W, H)); } catch (e) { reject(e); }
              };
              img.onerror = () => { if (url) URL.revokeObjectURL(url); reject(new Error('artwork tidak bisa dibaca')); };
              if (p.artworkUrl) { img.src = p.artworkUrl; return; }
              // Garis potong/lipat bukan tinta -- disembunyikan saat dianalisa.
              const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '">'
                  + '<style>.io-die,.io-fold,.io-cutmark{display:none}.io-artfill{fill:#fff}'
                  + '.io-lbl{font:700 ' + Math.max(6, W * .028).toFixed(1) + 'px Inter,Arial,sans-serif}</style>'
                  + shape({ w: W, h: H }) + '</svg>';
              url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));
              img.src = url;
          });
      }

      function colorName(r, g, b) {
          const mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, d = mx - mn;
          const s = d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1));
          // Kroma (d), bukan saturasi HSL, untuk putih/abu: saturasi HSL
          // membesar di warna sangat terang, jadi #fafbff terbaca "biru".
          if (l > .86 && d < .12) return 'Putih';
          if (l < .13) return 'Hitam';
          if (d < .08 || s < .12) return 'Abu-abu';
          let hue = mx === r ? ((g - b) / d) % 6 : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
          hue = (hue * 60 + 360) % 360;
          if (hue >= 30 && hue < 55 && s < .8 && l < .62) return 'Emas';
          if (hue < 15 || hue >= 345) return l < .3 ? 'Merah Tua' : 'Merah';
          if (hue < 40) return l < .4 ? 'Coklat' : 'Oranye';
          if (hue < 70) return 'Kuning';
          if (hue < 165) return 'Hijau';
          if (hue < 200) return 'Tosca';
          if (hue < 255) return l < .3 ? 'Biru Tua' : 'Biru';
          if (hue < 290) return 'Ungu';
          return 'Pink';
      }

      function analyzePixels(img) {
          const d = img.data, total = img.width * img.height, ch = { C: 0, M: 0, Y: 0, K: 0 }, groups = {};
          for (let i = 0; i < d.length; i += 4) {
              const a = d[i + 3] / 255;
              if (a < .04) continue;
              // Semi-transparan ditimpa di atas kertas putih.
              const r = (d[i] * a + 255 * (1 - a)) / 255, g = (d[i + 1] * a + 255 * (1 - a)) / 255, b = (d[i + 2] * a + 255 * (1 - a)) / 255;
              const k = 1 - Math.max(r, g, b);
              if (k < 1) {
                  ch.C += (1 - r - k) / (1 - k);
                  ch.M += (1 - g - k) / (1 - k);
                  ch.Y += (1 - b - k) / (1 - k);
              }
              ch.K += k;
              const n = colorName(r, g, b), grp = groups[n] || (groups[n] = { n: 0, r: 0, g: 0, b: 0 });
              grp.n++; grp.r += r; grp.g += g; grp.b += b;
          }
          const hx = v => Math.round(v * 255).toString(16).padStart(2, '0');
          return {
              channels: Object.fromEntries(Object.entries(ch).map(([k, v]) => [k, +(v / total * 100).toFixed(2)])),
              colors: Object.entries(groups)
                  .map(([name, g]) => ({ name, hex: '#' + hx(g.r / g.n) + hx(g.g / g.n) + hx(g.b / g.n), pct: +(g.n / total * 100).toFixed(2) }))
                  .filter(c => c.pct >= .3)
                  .sort((a, b) => b.pct - a.pct)
                  .slice(0, 12),
          };
      }

      async function analyzeArtwork(force) {
          const p = S.p, src = artSource(p);
          if (!src || !p.partId) { renderSpecSections(); return; }
          if (!force && p.artColors && (p.artColors.source === src || String(p.artColors.source || '').startsWith('server:'))) return;
          p.artBusy = true;
          renderSpecSections();
          try {
              const res = analyzePixels(await loadArtPixels(p));
              res.source = src;
              const w = wire();
              p.artColors = w ? await w.call('saveArtworkColors', p.partId, res) : res;
              p.artError = null;
          } catch (e) {
              p.artError = e && e.message ? e.message : String(e);
          }
          p.artBusy = false;
          specKey = '';
          specSoon();
          renderSpecSections();
      }

      // ---- Tampilan & edit
      const fmtAngka = v => (v === null || v === undefined || v === '') ? '—'
          : (+parseFloat(v).toFixed(4)).toLocaleString('id-ID', { maximumFractionDigits: 4 });
      const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
      const notes = list => list && list.length
          ? '<ul class="io-warns">' + list.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul>' : '';
      const opts = (list, val, blank) => (blank !== undefined ? '<option value="">' + esc(blank) + '</option>' : '')
          + list.map(o => '<option value="' + esc(o.id) + '"' + (String(o.id) === String(val ?? '') ? ' selected' : '') + '>' + esc(o.name) + '</option>').join('');
      const optsTxt = (list, val) => {
          const all = val && !list.includes(val) ? [val, ...list] : list;
          return all.map(o => '<option' + (o === val ? ' selected' : '') + '>' + esc(o) + '</option>').join('');
      };
      const field = (sec, i, f, val, attrs) => ' data-sec="' + sec + '" data-i="' + i + '" data-f="' + f + '" ' + (attrs || '') + ' value="' + esc(val ?? '') + '"';
      const STATUS_CLS = { 'CUKUP': 'ok', 'KURANG': 'warn', 'TIDAK ADA': 'bad', 'BELUM ADA ITEM': 'bad', 'CEK SATUAN': 'mute' };

      function rowsOf(p, k) {
          const sp = p.spec;
          if (!sp) return [];
          // Override = salinan hasil engine + perubahan officer. Selama hitungan
          // override belum kembali dari server, baris engine tetap ditampilkan.
          return (p.edit[k] && sp.override[k]) || sp[k] || [];
      }

      function tools(p, k) {
          const src = p.edit[k]
              ? (p.editSrc[k] === 'MANUAL' && !p.edit[k].__touched ? '<span class="io-src warn">DATA MANUAL TERSIMPAN</span>' : '<span class="io-src warn">OVERRIDE OFFICER</span>')
              : '<span class="io-src">HASIL ENGINE</span>';
          const fl = p.flash[k];
          return '<div class="io-sectools">' + src
              + '<span class="io-note-s">tersimpan ke master &amp; Inquiry saat Save</span>'
              + '<span class="io-grow"></span>'
              + (p.edit[k] ? '<button type="button" class="io-mbtn" data-act="reset" data-sec="' + k + '">↺ Kembali ke hasil engine</button>' : '')
              + '<button type="button" class="io-mbtn ' + (p.editing[k] ? 'on' : '') + '" data-act="edit" data-sec="' + k + '">'
              + (p.editing[k] ? '✓ Selesai edit' : '✎ Edit') + '</button></div>'
              + (fl ? '<div class="io-notice ' + (fl.ok ? 'ok' : 'bad') + '">' + esc(fl.text)
                  + (fl.url ? ' <a href="' + esc(fl.url) + '" target="_blank" rel="noopener">Buka dokumen ↗</a>' : '') + '</div>' : '');
      }

      function bopHtml(p, sp) {
          const rows = rowsOf(p, 'bop'), ed = p.editing.bop, raw = p.edit.bop || [];
          const qty = (r, v) => v === null || v === undefined ? '—' : fmtAngka(v) + ' ' + esc(r.uom || '');
          let h = tools(p, 'bop')
              + '<div class="io-inputs"><b>Finishing dari Inquiry:</b> '
              + (sp.inputs.finishings.length ? sp.inputs.finishings.map(f => '<span class="io-chip">' + esc(f) + '</span>').join(' ') : '—')
              + ' <b style="margin-left:10px">Qty order:</b> ' + (sp.inputs.orderQty ? fmtAngka(sp.inputs.orderQty) + ' pcs' : '—')
              + ' <b style="margin-left:10px">Isi / pack:</b> ' + esc(sp.inputs.packQty || '—') + '</div>';

          if (ed) {
              h += '<div class="io-tblwrap"><table class="io-tbl io-edit"><tr><th>#</th><th>Proses</th><th>Label di routing</th><th>Mesin</th>'
                  + '<th>Target / jam</th><th>Waste %</th><th>Toleransi %</th><th>Subkon ke</th><th></th></tr>'
                  + raw.map((r, i) => '<tr><td>' + (i + 1) + '</td>'
                      + '<td><select' + field('bop', i, 'process_id', '') + '>' + opts(LK.processes, r.process_id, '— proses —') + '</select></td>'
                      + '<td><input' + field('bop', i, 'label', r.label) + '></td>'
                      + '<td><select' + field('bop', i, 'machine_id', '') + '>' + opts(LK.machines, r.machine_id, '— tanpa mesin —') + '</select></td>'
                      + '<td><input type="number" min="0" step="1"' + field('bop', i, 'target_qty_per_hour', r.target_qty_per_hour) + '></td>'
                      + '<td><input type="number" min="0" max="100" step="0.1"' + field('bop', i, 'waste_pct', r.waste_pct) + '></td>'
                      + '<td><input type="number" min="0" max="100" step="0.1"' + field('bop', i, 'tolerance_pct', r.tolerance_pct) + '></td>'
                      + '<td><select' + field('bop', i, 'subcon_supplier_id', '') + '>' + opts(LK.subcons, r.subcon_supplier_id, '— in-house —') + '</select></td>'
                      + '<td class="io-nowrap"><button type="button" class="io-ibtn" data-act="up" data-sec="bop" data-i="' + i + '" title="Naik">▲</button>'
                      + '<button type="button" class="io-ibtn" data-act="down" data-sec="bop" data-i="' + i + '" title="Turun">▼</button>'
                      + '<button type="button" class="io-ibtn bad" data-act="del" data-sec="bop" data-i="' + i + '" title="Hapus">✕</button></td></tr>').join('')
                  + '</table></div><button type="button" class="io-mbtn" data-act="add" data-sec="bop">＋ Tambah langkah</button>';
          } else if (rows.length) {
              h += '<div class="io-tblwrap"><table class="io-tbl"><tr><th>#</th><th>Urutan Pekerjaan</th><th>Mesin</th><th>Target default</th>'
                  + '<th>Qty masuk</th><th>Waste</th><th>Toleransi</th><th>Est. jam</th><th>Subkon</th></tr>'
                  + rows.map((r, i) => '<tr><td>' + (i + 1) + '</td><td><b>' + esc(r.label) + '</b>'
                      + (r.note ? '<div class="io-sub">' + esc(r.note) + '</div>' : '') + '</td>'
                      + '<td>' + esc(r.machine || '—') + '</td>'
                      + '<td>' + (r.target_qty_per_hour ? fmtAngka(r.target_qty_per_hour) + ' ' + esc(r.uom || '') + '/jam'
                          + '<div class="io-sub">' + esc(r.target_source || '') + '</div>' : '<span class="io-sub">belum ada</span>') + '</td>'
                      + '<td>' + qty(r, r.qty_in) + '</td>'
                      + '<td>' + fmtAngka(r.waste_pct ?? 0) + '%<div class="io-sub">' + qty(r, r.qty_waste) + '</div></td>'
                      + '<td>' + fmtAngka(r.tolerance_pct ?? 0) + '%<div class="io-sub">' + qty(r, r.qty_tolerance) + '</div></td>'
                      + '<td>' + (r.est_hours ?? '—') + '</td>'
                      + '<td>' + ((!r.inhouse || r.subcon_supplier_id)
                          ? (r.subcon_supplier ? '<div class="io-sub">' + esc(r.subcon_supplier) + '</div>' : '')
                            + (r.subcon_pr ? '<div class="io-sub">' + esc(r.subcon_pr) + '</div>' : '')
                            + (r.subcon_do ? '<div class="io-sub">' + esc(r.subcon_do) + '</div>' : '')
                            + '<button type="button" class="io-mbtn warn" data-act="subcon" data-i="' + i + '">⇄ Subkon</button>'
                          : '<span class="io-sub">in-house</span>') + '</td></tr>').join('')
                  + '</table></div>';
          } else {
              h += '<div class="io-empty">Tidak ada langkah proses.</div>';
          }

          if (!ed && p.subconAt !== null && rows[p.subconAt]) h += subconDialog(p, rows[p.subconAt]);

          return h + notes(sp.warnings.bop);
      }

      function subconDialog(p, r) {
          const co = p.coOptions || [];
          return '<div class="io-dialog"><b>Subkon: ' + esc(r.label) + '</b>'
              + '<div class="io-sub">Qty dikirim: ' + fmtAngka(r.qty_in) + ' ' + esc(r.uom || '') + ' · PR subkon dibuat DRAFT (lewat approval PR).</div>'
              + '<div class="io-row3">'
              + '<label>Supplier subkon<select id="io-sc-sup">' + opts(LK.subcons, r.subcon_supplier_id, '— pilih supplier —') + '</select></label>'
              + '<label>CO (wajib untuk surat jalan)<select id="io-sc-co">' + opts(co.map(c => ({ id: c.id, name: c.label })), '', co.length ? '— pilih CO —' : '— belum ada CO untuk Inquiry ini —') + '</select></label>'
              + '<label>Tanggal kirim<input type="date" id="io-sc-date"></label>'
              + '<label>Estimasi biaya (Rp)<input type="number" min="0" id="io-sc-cost"></label>'
              + '</div><div class="io-sub">Tanpa CO: PR subkon tetap dibuat, surat jalan menyusul setelah CO terbit (klik Subkon lagi).</div>'
              + '<div style="margin-top:8px"><button type="button" class="io-mbtn on" data-act="subcon-go">Proses Subkon</button> '
              + '<button type="button" class="io-mbtn" data-act="subcon-cancel">Batal</button></div></div>';
      }

      function bomHtml(p, sp) {
          const rows = rowsOf(p, 'bom'), ed = p.editing.bom, raw = p.edit.bom || [];
          let h = tools(p, 'bom')
              + '<div class="io-inputs"><b>Qty order:</b> ' + (sp.inputs.orderQty ? fmtAngka(sp.inputs.orderQty) + ' pcs' : '—')
              + ' <b style="margin-left:10px">Lembar cetak dibutuhkan:</b> ' + (sp.inputs.printSheets ? fmtAngka(sp.inputs.printSheets) + ' lembar (termasuk waste & toleransi BOP)' : '—') + '</div>';

          if (ed) {
              h += '<div class="io-tblwrap"><table class="io-tbl io-edit"><tr><th>Peran</th><th>Bahan</th><th>Gramatur</th><th>P (cm)</th><th>L (cm)</th>'
                  + '<th>Qty / lembar</th><th>Satuan</th><th>Kebutuhan</th><th></th></tr>'
                  + raw.map((r, i) => {
                      const c = rows[i] || {};
                      return '<tr><td><select' + field('bom', i, 'role', '') + '>' + optsTxt(LK.roles, r.role) + '</select></td>'
                          + '<td class="io-rel"><input placeholder="ketik nama / kode bahan…"' + field('bom', i, 'search', r.name) + ' autocomplete="off">'
                          + (p.dd && p.dd.i === i ? ddHtml(p.dd) : '') + '</td>'
                          + '<td><input type="number" min="0"' + field('bom', i, 'gsm', r.gsm) + '></td>'
                          + '<td><input type="number" min="0" step="0.1"' + field('bom', i, 'size_p', r.size_p) + '></td>'
                          + '<td><input type="number" min="0" step="0.1"' + field('bom', i, 'size_l', r.size_l) + '></td>'
                          + '<td><input type="number" min="0" step="any"' + field('bom', i, 'qty_per_sheet', r.qty_per_sheet) + '></td>'
                          + '<td><select' + field('bom', i, 'uom', '') + '>' + optsTxt(LK.uoms, r.uom) + '</select></td>'
                          + '<td>' + (c.qty_need !== undefined && c.qty_need !== null ? fmtAngka(c.qty_need) + ' ' + esc(c.uom || '') : '—') + '</td>'
                          + '<td><button type="button" class="io-ibtn bad" data-act="del" data-sec="bom" data-i="' + i + '" title="Hapus">✕</button></td></tr>';
                  }).join('')
                  + '</table></div><button type="button" class="io-mbtn" data-act="add" data-sec="bom">＋ Tambah bahan</button>';
          } else if (rows.length) {
              h += '<div class="io-tblwrap"><table class="io-tbl"><tr><th>Peran</th><th>Bahan</th><th>Merek</th><th>Spesifikasi</th><th>Per lembar cetak</th>'
                  + '<th>Qty kebutuhan</th><th>Stok tersedia (gudang)</th><th>Status</th><th></th></tr>'
                  + rows.map((r, i) => {
                      const spec = [r.gsm ? fmtAngka(r.gsm) + ' gsm' : '', r.size_p ? fmtAngka(r.size_p) + (r.size_l ? ' × ' + fmtAngka(r.size_l) : '') + ' cm' : ''].filter(Boolean).join(' · ');
                      const need = r.qty_need !== null ? fmtAngka(r.qty_need) + ' ' + esc(r.basis === 'PLANO' ? 'lembar plano' : (r.uom || ''))
                          + (r.qty_need_stock_uom !== null && r.stock_uom && r.stock_uom !== r.uom ? '<div class="io-sub">= ' + fmtAngka(r.qty_need_stock_uom) + ' ' + esc(r.stock_uom) + '</div>' : '')
                          : '<span class="io-sub">' + (r.name === 'None' ? '—' : 'belum bisa dihitung') + '</span>';
                      return '<tr><td>' + esc(r.role) + '</td><td><b>' + esc(r.name || '—') + '</b>'
                          + (r.note ? '<div class="io-sub">' + esc(r.note) + '</div>' : '') + '</td>'
                          + '<td>' + esc(r.brand || '—') + '</td>'
                          + '<td>' + (spec || '—') + '</td>'
                          + '<td>' + (r.qty_per_sheet !== null ? fmtAngka(r.qty_per_sheet) + ' ' + esc(r.uom || '') : '—') + '</td>'
                          + '<td>' + need + '</td>'
                          + '<td>' + (r.material_id ? fmtAngka(r.stock_available ?? 0) + ' ' + esc(r.stock_uom || '') : '—') + '</td>'
                          + '<td>' + (r.stock_status ? '<span class="io-stat ' + (STATUS_CLS[r.stock_status] || 'mute') + '">' + esc(r.stock_status) + '</span>'
                              + (r.shortage ? '<div class="io-sub">kurang ' + fmtAngka(r.shortage) + ' ' + esc(r.stock_uom || '') + '</div>' : '') : '—') + '</td>'
                          + '<td>' + (r.material_id && ['KURANG', 'TIDAK ADA'].includes(r.stock_status) && (r.shortage > 0 || r.qty_need > 0)
                              ? '<button type="button" class="io-mbtn warn" data-act="pr" data-i="' + i + '">＋ Buat PR</button>' : '') + '</td></tr>';
                  }).join('')
                  + '</table></div>';
          } else {
              h += '<div class="io-empty">Belum ada bahan.</div>';
          }

          return h + notes(sp.warnings.bom);
      }

      function ddHtml(dd) {
          if (dd.busy) return '<div class="io-dd"><div class="io-sub">Mencari…</div></div>';
          if (!dd.items.length) return '<div class="io-dd"><div class="io-sub">Tidak ada bahan cocok.</div></div>';
          return '<div class="io-dd">' + dd.items.map((m, k) => '<button type="button" data-act="pick-mat" data-k="' + k + '">'
              + '<b>' + esc(m.name) + '</b><span>' + esc(m.code) + ' · ' + esc(m.category || '') + ' · stok ' + fmtAngka(m.stock) + ' ' + esc(m.uom || '') + '</span></button>').join('') + '</div>';
      }

      // Warna khusus (user 2026-10-01): 4 slot tetap -- Unit 5..8 pass pertama -- yang diisi officer langsung di
      // tampilan biasa, tanpa masuk mode Edit. Slot kosong tidak disimpan (server membuang tinta tanpa nama);
      // slot terisi = override officer, ikut Save All ke prod_part_ink + iqp_part_ink seperti tinta lain.
      const SPOT_UNITS = [5, 6, 7, 8];
      const isSpot = r => +r.pass_seq === 1 && SPOT_UNITS.includes(+r.unit_no);
      const spotOf = (list, u) => (list || []).find(r => +r.pass_seq === 1 && +r.unit_no === u);

      function spotHtml(p, rows) {
          const src = p.edit.inks || rows;
          const tr = SPOT_UNITS.map((u, n) => {
              const r = spotOf(src, u) || {}, c = spotOf(rows, u) || {};
              const at = f => ' data-spot="' + u + '" data-f="' + f + '"';
              return '<tr><td class="io-nowrap">Special ' + (n + 1) + '</td><td class="io-nowrap">Unit ' + u + '</td>'
                  + '<td><input list="io-inknames" placeholder="e.g. PANTONE 485 C"' + at('ink_name') + ' value="' + esc(r.ink_name || '') + '"></td>'
                  + '<td><input type="color"' + at('color_hex') + ' value="' + esc(r.color_hex || '#888888') + '"></td>'
                  + '<td><input type="number" min="0" max="100" step="0.1"' + at('coverage_pct') + ' value="' + esc(r.coverage_pct ?? '') + '"></td>'
                  + '<td class="io-nowrap">' + (c.coverage_cm2 !== undefined && c.coverage_cm2 !== null ? fmtAngka(c.coverage_cm2) + ' cm²' : '—') + '</td>'
                  + '<td class="io-nowrap">' + (c.coverage_cm2_sheet !== undefined && c.coverage_cm2_sheet !== null ? fmtAngka(c.coverage_cm2_sheet) + ' cm²' : '—') + '</td></tr>';
          }).join('');
          return '<div class="io-subh">Special colours — filled in by the officer</div>'
              + '<div class="io-tblwrap"><table class="io-tbl io-edit"><tr><th>Slot</th><th>Unit</th><th>Ink name</th><th>Colour</th><th>Coverage %</th><th>Area / pcs</th><th>Area / sheet</th></tr>' + tr + '</table></div>'
              + '<div class="io-note-s">Empty slots are not saved. A filled slot is saved with Save; clear the ink name to remove it.</div>';
      }

      function inkHtml(p, sp) {
          const rows = rowsOf(p, 'inks'), ed = p.editing.inks, raw = p.edit.inks || [];
          const art = p.artBusy ? '<div class="io-empty">Menganalisa warna artwork…</div>'
              : p.artError ? '<div class="io-notice bad">Analisa artwork gagal: ' + esc(p.artError) + '</div>' : '';
          let h = tools(p, 'inks')
              + '<div class="io-inputs"><b>Notasi warna:</b> ' + esc(sp.inputs.color || '—')
              + (artSource(p) ? ' <button type="button" class="io-mbtn" data-act="reanalyze" style="margin-left:10px">↻ Analisa ulang artwork</button>' : '') + '</div>'
              + '<datalist id="io-inknames">' + LK.inks.map(n => '<option value="' + esc(n) + '">').join('') + '</datalist>';

          if (ed) {
              const units = [1, 2, 3, 4, 5, 6, 7, 8].map(n => ({ id: n, name: 'Unit ' + n }));
              h += '<div class="io-tblwrap"><table class="io-tbl io-edit"><tr><th>Pass</th><th>Jenis</th><th>Unit</th><th>Tinta</th><th>Warna</th><th>Tutupan %</th><th>Luas / pcs</th><th></th></tr>'
                  + raw.map((r, i) => {
                      const c = rows[i] || {};
                      return '<tr><td><select' + field('inks', i, 'pass_seq', '') + '>' + opts([{ id: 1, name: 'Warna Pertama' }, { id: 2, name: 'Warna Kedua' }], r.pass_seq) + '</select></td>'
                          + '<td><select' + field('inks', i, 'pass_label', '') + '>' + optsTxt(['CS', 'SPOT'], r.pass_label) + '</select></td>'
                          + '<td><select' + field('inks', i, 'unit_no', '') + '>' + opts(units, r.unit_no) + '</select></td>'
                          + '<td><input list="io-inknames"' + field('inks', i, 'ink_name', r.ink_name) + '></td>'
                          + '<td><input type="color"' + field('inks', i, 'color_hex', r.color_hex || '#888888') + '></td>'
                          + '<td><input type="number" min="0" max="100" step="0.1"' + field('inks', i, 'coverage_pct', r.coverage_pct) + '></td>'
                          + '<td>' + (c.coverage_cm2 !== undefined && c.coverage_cm2 !== null ? fmtAngka(c.coverage_cm2) + ' cm²' : '—') + '</td>'
                          + '<td><button type="button" class="io-ibtn bad" data-act="del" data-sec="inks" data-i="' + i + '" title="Hapus">✕</button></td></tr>';
                  }).join('')
                  + '</table></div><button type="button" class="io-mbtn" data-act="add" data-sec="inks">＋ Tambah tinta</button>';
          } else if (rows.length) {
              h += '<div class="io-tblwrap"><table class="io-tbl"><tr><th>Pass</th><th>Unit</th><th>Tinta</th><th>Tutupan</th><th>Luas / pcs</th><th>Luas / lembar</th></tr>'
                  + rows.filter(r => !isSpot(r)).map(r => '<tr><td>' + (+r.pass_seq === 1 ? 'Warna Pertama' : 'Warna Kedua') + ': ' + esc(r.pass_label) + '</td><td>Unit ' + r.unit_no + '</td><td>'
                      + '<i class="io-sw" style="background:' + esc(r.color_hex || '#888') + '"></i><b>' + esc(r.ink_name) + '</b></td><td>'
                      + (r.coverage_pct !== null ? fmtAngka(r.coverage_pct) + '%' : '—') + '</td><td>'
                      + (r.coverage_cm2 !== null ? fmtAngka(r.coverage_cm2) + ' cm²' : '—') + '</td><td>'
                      + (r.coverage_cm2_sheet !== null ? fmtAngka(r.coverage_cm2_sheet) + ' cm²' : '—') + '</td></tr>').join('') + '</table></div>';
          }

          if (!ed) h += spotHtml(p, rows);

          return h + art
              + (sp.colors.length
                  ? '<div class="io-subh">Warna di artwork (luas per pcs)</div><div class="io-colors">'
                    + sp.colors.map(c => '<span class="io-colorchip"><i class="io-sw" style="background:' + esc(c.hex || '#888') + '"></i>'
                        + esc(c.name) + ' <b>' + (c.cm2 !== null ? fmtAngka(c.cm2) + ' cm²' : fmtAngka(c.pct) + '%') + '</b></span>').join('') + '</div>'
                  : '')
              + notes(sp.warnings.ink);
      }

      function renderSpecSections() {
          const p = S.p, sp = p.spec;
          specInit(p);
          const tunggu = '<div class="io-empty">' + (p.partId ? 'Menghitung dari data Inquiry…' : 'Job demo — engine butuh part nyata.') + '</div>';

          // Pertahankan fokus kursor saat tabel edit digambar ulang.
          const a = document.activeElement, keep = a && a.dataset && a.dataset.f
              ? { sec: a.dataset.sec, i: a.dataset.i, f: a.dataset.f, pos: a.selectionStart } : null;

          $('cnt-bop').textContent = sp ? rowsOf(p, 'bop').length : '';
          $('cnt-bom').textContent = sp ? rowsOf(p, 'bom').length : '';
          $('cnt-ink').textContent = sp ? rowsOf(p, 'inks').length : '';
          $('bop').innerHTML = sp ? bopHtml(p, sp) : tunggu;
          $('bom').innerHTML = sp ? bomHtml(p, sp) : tunggu;
          $('ink').innerHTML = sp ? inkHtml(p, sp) : tunggu;

          if (keep) {
              const el = document.querySelector('#io-acc [data-sec="' + keep.sec + '"][data-i="' + keep.i + '"][data-f="' + keep.f + '"]');
              if (el) { el.focus(); try { if (keep.pos !== null && keep.pos !== undefined) el.setSelectionRange(keep.pos, keep.pos); } catch (e) { /* input number */ } }
          }
          samakanTinggiAcc();
      }

      const NUM_FIELDS = ['process_id', 'machine_id', 'target_qty_per_hour', 'waste_pct', 'tolerance_pct', 'subcon_supplier_id',
          'gsm', 'size_p', 'size_l', 'qty_per_sheet', 'pass_seq', 'unit_no', 'coverage_pct'];

      function blankRow(k, p) {
          if (k === 'bop') return { process_id: null, label: 'Langkah baru', machine_id: null, target_qty_per_hour: null, waste_pct: 0, tolerance_pct: 0, subcon_supplier_id: null, note: null };
          if (k === 'bom') return { role: 'BAHAN LAIN', material_id: null, name: 'BAHAN BARU', gsm: null, size_p: null, size_l: null, qty_per_sheet: null, uom: 'PCS', primary: false, note: null };
          const used = (p.edit.inks || []).filter(r => +r.pass_seq === 1).map(r => +r.unit_no);
          return { pass_seq: 1, pass_label: 'SPOT', unit_no: Math.min(8, (used.length ? Math.max(...used) : 0) + 1), ink_name: 'PANTONE', color_hex: null, coverage_pct: null };
      }

      // Salinan baris tampilan -> baris yang bisa diedit (hanya field input).
      function toEditable(k, rows) {
          const pick = {
              bop: ['process_id', 'label', 'machine_id', 'target_qty_per_hour', 'waste_pct', 'tolerance_pct', 'subcon_supplier_id', 'note'],
              bom: ['role', 'material_id', 'name', 'brand', 'gsm', 'size_p', 'size_l', 'qty_per_sheet', 'uom', 'basis', 'rate', 'primary', 'note'],
              inks: ['pass_seq', 'pass_label', 'unit_no', 'ink_name', 'color_hex', 'coverage_pct'],
          }[k];
          return rows.map(r => Object.fromEntries(pick.map(f => [f, r[f] ?? null])));
      }

      function touch(p, k) {
          p.edit[k].__touched = true;
          p.editSrc[k] = 'OVERRIDE';
          specSoon();
      }

      let ddTimer = null;
      function bindSpecEvents() {
          const acc = document.getElementById('io-acc');

          acc.addEventListener('click', async e => {
              const b = e.target.closest('[data-act]');
              if (!b) return;
              const p = S.p, k = b.dataset.sec, i = +b.dataset.i;
              specInit(p);

              switch (b.dataset.act) {
                  case 'edit':
                      if (!p.editing[k]) {
                          if (!p.edit[k]) p.edit[k] = toEditable(k, rowsOf(p, k));
                          p.editing[k] = true;
                          specSoon();
                      } else {
                          p.editing[k] = false;
                          // Tidak ada yang diubah -> tetap ikut engine, bukan override.
                          if (!p.edit[k].__touched && !(p.saved && p.saved[k])) p.edit[k] = null;
                          specSoon();
                      }
                      p.dd = null;
                      break;
                  case 'reset':
                      p.edit[k] = null; p.editing[k] = false; p.editSrc[k] = null;
                      if (p.saved && p.saved[k] && !p.reset.includes(k)) p.reset.push(k);
                      p.flash[k] = { ok: true, text: 'Dikembalikan ke hasil engine — tersimpan saat Save.' };
                      specSoon();
                      break;
                  case 'add':
                      p.edit[k].push(blankRow(k, p)); touch(p, k);
                      break;
                  case 'del':
                      p.edit[k].splice(i, 1); touch(p, k);
                      break;
                  case 'up':
                  case 'down': {
                      const j = b.dataset.act === 'up' ? i - 1 : i + 1, arr = p.edit[k];
                      if (j < 0 || j >= arr.length) return;
                      [arr[i], arr[j]] = [arr[j], arr[i]]; touch(p, k);
                      break;
                  }
                  case 'pick-mat': {
                      const m = p.dd && p.dd.items[+b.dataset.k], row = p.dd && p.edit.bom[p.dd.i];
                      if (!m || !row) return;
                      Object.assign(row, { material_id: m.id, name: m.name, gsm: m.gsm ?? row.gsm, uom: m.uom || row.uom });
                      p.dd = null; touch(p, 'bom');
                      break;
                  }
                  case 'reanalyze':
                      analyzeArtwork(true);
                      return;
                  case 'pr': {
                      const r = rowsOf(p, 'bom')[i], w = wire();
                      if (!r || !w) return;
                      b.disabled = true;
                      // Satuan stok tak bisa dikonversi -> minta sebanyak kebutuhan, satuan kebutuhan.
                      const byStock = r.shortage !== null && r.shortage > 0;
                      const res = (await w.call('createPurchase', p.partId, r.material_id,
                          byStock ? r.shortage : r.qty_need, byStock ? r.stock_uom : r.uom).catch(() => null))
                          || { ok: false, message: 'Server tidak merespons, coba lagi.' };
                      p.flash.bom = { ok: res.ok, text: res.message, url: res.url };
                      break;
                  }
                  case 'subcon':
                      p.subconAt = p.subconAt === i ? null : i;
                      break;
                  case 'subcon-cancel':
                      p.subconAt = null;
                      break;
                  case 'subcon-go': {
                      const r = rowsOf(p, 'bop')[p.subconAt], w = wire();
                      const sup = +document.getElementById('io-sc-sup').value;
                      if (!r || !w) return;
                      if (!sup) { p.flash.bop = { ok: false, text: 'Pilih supplier subkon dulu.' }; break; }
                      b.disabled = true;
                      const res = await w.call('subcontract', p.partId,
                          { process_id: r.process_id, label: r.label, qty_in: r.qty_in, uom: r.uom },
                          sup, +document.getElementById('io-sc-co').value || null,
                          document.getElementById('io-sc-date').value || null,
                          +document.getElementById('io-sc-cost').value || null).catch(() => null)
                          || { ok: false, message: 'Server tidak merespons, coba lagi.' };
                      p.flash.bop = { ok: res.ok, text: res.message, url: res.prUrl };
                      if (res.ok) { p.subconAt = null; specKey = ''; specSoon(); }
                      break;
                  }
                  default:
                      return;
              }
              renderSpecSections();
          });

          acc.addEventListener('change', e => {
              const el = e.target, f = el.dataset && el.dataset.f;
              if (!f) return;
              const p = S.p, k = el.dataset.sec, row = p.edit[k] && p.edit[k][+el.dataset.i];
              if (!row) return;
              const v = el.value;
              if (f === 'search') {
                  // Nama diketik bebas (tidak dipilih dari daftar) = bahan di luar master.
                  if (v.trim() !== (row.name || '')) { row.name = v.trim(); row.material_id = null; touch(p, k); }
                  return;
              }
              row[f] = NUM_FIELDS.includes(f) ? (v === '' ? null : +v) : v;
              // Proses baru dipilih -> label ikut nama proses bila label masih bawaan.
              if (f === 'process_id' && (!row.label || row.label === 'Langkah baru')) {
                  const pr = LK.processes.find(x => +x.id === +v);
                  if (pr) row.label = pr.name;
              }
              touch(p, k);
          });

          // Slot warna khusus: isian pertama membuat baris tinta SPOT di unit itu; nama dikosongkan = baris dibuang.
          acc.addEventListener('change', e => {
              const el = e.target, u = el.dataset ? +el.dataset.spot : 0;
              if (!u) return;
              const p = S.p, f = el.dataset.f, v = el.value;
              specInit(p);
              if (!p.spec) return;
              if (!p.edit.inks) p.edit.inks = toEditable('inks', rowsOf(p, 'inks'));
              let row = spotOf(p.edit.inks, u);
              if (!row) {
                  row = { pass_seq: 1, pass_label: 'SPOT', unit_no: u, ink_name: '', color_hex: null, coverage_pct: null };
                  p.edit.inks.push(row);
              }
              row[f] = f === 'coverage_pct' ? (v === '' ? null : +v) : f === 'ink_name' ? v.trim().toUpperCase() : v;
              if (f === 'ink_name' && !row.ink_name) p.edit.inks.splice(p.edit.inks.indexOf(row), 1);
              touch(p, 'inks');
              renderSpecSections();
          });

          acc.addEventListener('input', e => {
              const el = e.target;
              if (!el.dataset || el.dataset.f !== 'search') return;
              const p = S.p, i = +el.dataset.i, q = el.value.trim();
              clearTimeout(ddTimer);
              if (q.length < 2) { if (p.dd) { p.dd = null; renderSpecSections(); } return; }
              ddTimer = setTimeout(async () => {
                  const w = wire();
                  if (!w) return;
                  p.dd = { i, busy: true, items: [] };
                  renderSpecSections();
                  const items = await w.call('searchMaterials', q);
                  if (p.dd && p.dd.i === i) { p.dd = { i, busy: false, items: items || [] }; renderSpecSections(); }
              }, 300);
          });
      }

      // ===================== MASTER CARD (pratinjau & cetak) =====================
      // Semua informasi ProDev yang sedang tampil dirangkum jadi 1 lembar
      // A4 landscape mengikuti bentuk Master Card / Kartu Induk pabrik.
      // Selama layout belum di-Confirm, lembar ini dicap "NOT CONFIRMED".
      const GRAIN_LABEL = { L: 'LG', W: 'SG', none: '—' };

      // Gambar layout untuk MASTER CARD: versi HEMAT TINTA (permintaan user
      // 2026-09-18 -- "jangan ada block warna gelap di canvas"). Latar &
      // permukaan artwork dibuat putih, blok gelap diturunkan jadi abu muda,
      // garis potong/lipat tetap terbaca. Gambarnya diperbesar sebesar lembar
      // cetak lalu diberi anak panah ukuran panjang & lebar (ukuran canvas
      // tidak lagi ditulis sebagai judul).
      const MC_LIGHT = '#f0f0f0';

      function mcIsDark(color) {
          if (!color) return false;
          let r, g, b;
          const hex = color.trim().replace(/^#/, '');
          if (/^[0-9a-f]{6}$/i.test(hex)) {
              [r, g, b] = [0, 2, 4].map(i => parseInt(hex.substr(i, 2), 16));
          } else if (/^[0-9a-f]{3}$/i.test(hex)) {
              [r, g, b] = [0, 1, 2].map(i => parseInt(hex[i] + hex[i], 16));
          } else {
              const m = color.match(/rgba?\(([^)]+)\)/i);
              if (!m) return false;
              [r, g, b] = m[1].split(',').map(v => parseFloat(v));
          }
          return (0.299 * r + 0.587 * g + 0.114 * b) / 255 < 0.72;
      }

      function mcLighten(el) {
          const tag = el.tagName.toLowerCase();

          if (['svg', 'defs', 'marker', 'g', 'clippath', 'lineargradient', 'pattern', 'stop'].includes(tag)) {
              return;
          }

          const cs = getComputedStyle(el);
          const fill = cs.fill;
          const cls = el.getAttribute('class') || '';

          if (tag === 'text' || cls.includes('io-lbl') || cls.includes('io-graintext')) {
              el.style.fill = '#333';
              return;
          }
          if (cls.includes('io-artfill')) {
              el.style.fill = '#fff';
              el.style.fillOpacity = '1';
              el.style.stroke = '#8a98a6';
              el.style.strokeWidth = '.8';
              return;
          }
          if (cls.includes('io-die') || cls.includes('io-fold') || cls.includes('io-cutmark')) {
              // Garis potong/lipat tetap terbaca, tapi TIDAK boleh punya isian
              // (path terbuka yang ter-fill hitam = blok tinta saat dicetak).
              el.style.fill = 'none';
              return;
          }
          if (tag === 'g' || tag === 'svg') {
              return;
          }
          // Garis (line/polyline) tidak punya bidang -> isian diabaikan.
          if (['line', 'polyline'].includes(tag)) {
              el.style.fill = 'none';
          } else if (fill && fill !== 'none' && mcIsDark(fill)) {
              el.style.fill = MC_LIGHT;
              if (cs.stroke === 'none') {
                  el.style.stroke = '#b9c2cb';
                  el.style.strokeWidth = '.6';
              }
          }

          if (cs.stroke !== 'none' && mcIsDark(cs.stroke)) {
              el.style.stroke = '#555';
          }
      }

      // Ruang untuk anak panah + labelnya, dipakai bersama oleh viewBox &
      // penggambaran panah supaya label tidak terpotong tepi gambar.
      function mcBox(sheet) {
          const x = +sheet.getAttribute('x'), y = +sheet.getAttribute('y');
          const w = +sheet.getAttribute('width'), h = +sheet.getAttribute('height');
          const off = Math.max(18, Math.min(w, h) * .06);
          const fs = Math.max(11, w * .022);

          return { x, y, w, h, off, fs, gap: off * .5, room: off + fs * 3.8 };
      }

      function mcArrows(sheet) {
          const { x, y, w, h, off, fs } = mcBox(sheet);
          const tick = off * .45;
          const L = fmtAngka(S.L) + ' cm', W = fmtAngka(S.W) + ' cm';

          return ''
              + '<defs><marker id="io-mcArw" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto">'
              + '<path d="M0 0L7 3.5 0 7Z" fill="#333"/></marker></defs>'
              + '<g stroke="#333" stroke-width="1" fill="none">'
              // panah ukuran panjang (bawah)
              + '<path d="M' + x + ' ' + (y + h + off) + 'H' + (x + w) + '" marker-start="url(#io-mcArw)" marker-end="url(#io-mcArw)"/>'
              + '<path d="M' + x + ' ' + (y + h + off - tick) + 'v' + (tick * 2) + 'M' + (x + w) + ' ' + (y + h + off - tick) + 'v' + (tick * 2) + '"/>'
              // panah ukuran lebar (kanan)
              + '<path d="M' + (x + w + off) + ' ' + y + 'V' + (y + h) + '" marker-start="url(#io-mcArw)" marker-end="url(#io-mcArw)"/>'
              + '<path d="M' + (x + w + off - tick) + ' ' + y + 'h' + (tick * 2) + 'M' + (x + w + off - tick) + ' ' + (y + h) + 'h' + (tick * 2) + '"/>'
              + '</g>'
              + '<g fill="#111" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="' + fs + '">'
              + '<text x="' + (x + w / 2) + '" y="' + (y + h + off + fs * 1.5) + '" text-anchor="middle">' + L + '</text>'
              + '<text x="' + (x + w + off + fs * 1.4) + '" y="' + (y + h / 2) + '" text-anchor="middle" '
              + 'transform="rotate(90 ' + (x + w + off + fs * 1.4) + ' ' + (y + h / 2) + ')">' + W + '</text>'
              + '</g>';
      }

      function masterCardSvg() {
          const src = $('svg');
          if (!src) return '';

          const clone = src.cloneNode(true);
          clone.removeAttribute('width');
          clone.removeAttribute('height');
          clone.removeAttribute('id');
          clone.style.background = '#fff';
          clone.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));
          S.objs.filter(o => !inCanvas(o)).forEach(o => {
              const el = clone.querySelector('[data-id="' + o.id + '"]');
              if (el) el.remove();
          });

          const sheet = clone.querySelector('#io-sheet');
          if (sheet) {
              sheet.removeAttribute('class');
              sheet.setAttribute('fill', '#fff');
              sheet.setAttribute('stroke', '#333');
              sheet.setAttribute('stroke-width', '1.2');
              const safe = clone.querySelector('#io-safeRect');
              if (safe) {
                  safe.setAttribute('stroke', '#c8ced6');
                  safe.setAttribute('fill', 'none');
              }

              // Perbesar: bidang gambar = lembar cetak + ruang anak panah/label.
              const b = mcBox(sheet);
              clone.setAttribute('viewBox', (b.x - b.gap) + ' ' + (b.y - b.gap) + ' '
                  + (b.w + b.gap + b.room) + ' ' + (b.h + b.gap + b.room));
              clone.insertAdjacentHTML('beforeend', mcArrows(sheet));
          }

          return clone.outerHTML;
      }


      function knifeInfo() {
          const st = gatherState();
          const up = st.upCount || 0;
          const nama = (S.p.productName || S.p.name || '').toUpperCase();
          return {
              code: nama ? 'PISAU ' + nama + ' ISI ' + up + ' MATA' : '—',
              size: st.knifeW && st.knifeH ? fmtAngka(st.knifeW) + ' × ' + fmtAngka(st.knifeH) + ' cm' : '—',
              up: up,
          };
      }

      function mcRow(label, value, mono) {
          return '<tr><th>' + esc(label) + '</th><td' + (mono ? ' class="mono"' : '') + '>' + (value ?? '—') + '</td></tr>';
      }

      // Routing di Master Card cukup "Proses - Mesin": label engine untuk
      // cetak berbunyi "Cetak di mesin: X" padahal mesinnya juga tertulis
      // terpisah -> dulu tampil dobel dan kolomnya harus lebar.
      function mcStepName(r) {
          const name = String(r.label || '').replace(/\s*(di mesin)?\s*:.*$/i, '').trim() || r.label || '';
          return r.machine ? name + ' - ' + r.machine : name;
      }

      // Baris TINTA disebut warnanya (dari catatan engine "Unit n · CYAN"),
      // kalau tidak semua baris tinta tampil sama.
      function mcBomRow(r) {
          let role = r.role || '';
          if (role === 'TINTA') {
              const m = String(r.note || '').match(/Unit\s*\d+\s*·\s*([^·]+)/);
              role += ' ' + (m ? m[1].trim() : String(r.name || '').replace(/^.*\bINK\b\s*/i, '')).toUpperCase();
          }
          return mcRow(role.trim(), r.qty_need !== null && r.qty_need !== undefined ? fmtAngka(r.qty_need) + ' ' + esc(r.uom || '') : '—', true);
      }

      function masterCardHtml() {
          const p = S.p, sp = p.spec, k = knifeInfo();
          const bom = sp ? (p.edit && p.edit.bom && sp.override.bom ? sp.override.bom : sp.bom) : [];
          const bop = sp ? (p.edit && p.edit.bop && sp.override.bop ? sp.override.bop : sp.bop) : [];
          const inks = sp ? (p.edit && p.edit.inks && sp.override.inks ? sp.override.inks : sp.inks) : [];
          const kertas = bom.find(r => r.basis === 'PLANO' && r.material_id) || bom[0] || {};
          const pass1 = inks.filter(r => +r.pass_seq === 1);
          const finishing = sp && sp.inputs.finishings.length ? sp.inputs.finishings.join(', ') : (p.finishingText || '—');
          const svg = masterCardSvg();
          // Routing dicetak dalam 15 baris TETAP (permintaan user 2026-09-18):
          // sisanya dibiarkan kosong supaya tata letaknya selalu sama.
          const ROUTING_ROWS = 15;
          const cetak = fmtAngka(S.L) + ' × ' + fmtAngka(S.W) + ' cm';

          return ''
              + '<div class="io-mc-head">'
              + '<div><div class="brand">Telasih Makmur <i>offset printing &amp; packaging</i></div>'
              + '<h2>MASTER CARD / KARTU INDUK</h2></div>'
              + '<div class="item">Kode Item: ' + esc(p.itemCode || p.design || '—') + '</div></div>'

              + '<div class="io-mc-body"><div>'
              + '<table class="io-mc-tbl">'
              + '<tr><th>No. Desain</th><td class="mono">' + esc(p.designNo || '—') + '</td>'
              + '<th style="width:18%">Nama Barang</th><td><b>' + esc(p.productName || p.name) + '</b></td>'
              + '<th style="width:12%">Tanggal</th><td class="mono">' + esc(p.today || '') + '</td></tr>'
              + '</table>'
              + '<table class="io-mc-tbl">'
              + mcRow('Nama Pelanggan', '<b>' + esc(p.customer) + '</b>')
              + '<tr><th>Dimensi P</th><td class="mono">' + fmtAngka(p.fl) + ' cm</td>'
              + '<th>Dimensi L</th><td class="mono">' + fmtAngka(p.fw) + ' cm</td>'
              + '<th>Dimensi T</th><td class="mono">' + fmtAngka(p.fh) + ' cm</td></tr>'
              + '<tr><th>Kode Pisau</th><td colspan="3"><b>' + esc(k.code) + '</b></td>'
              + '<th>Ukuran Pisau</th><td class="mono">' + k.size + '</td></tr>'
              + '<tr><th>Material</th><td colspan="3"><b>' + esc(kertas.name || '—') + '</b>'
              + (kertas.brand ? ' <span style="color:#555">(' + esc(kertas.brand) + ')</span>' : '') + '</td>'
              + '<th>Kualitas</th><td class="mono">' + (kertas.gsm ? fmtAngka(kertas.gsm) + ' gsm' : '—') + '</td></tr>'
              + '<tr><th>Ukuran Bahan</th><td colspan="3" class="mono">' + cetak + ' (' + (GRAIN_LABEL[p.grain] || '—') + ')</td>'
              + '<th>Ukuran Cetak</th><td class="mono">' + cetak + '</td></tr>'
              + mcRow('Jenis Finishing', esc(finishing))
              + mcRow('Struktur / Joint', esc(p.structure || '—'))
              + '</table>'

              + '<div class="io-mc-sub"><div>'
              + '<div class="io-mc-cap">Warna Pertama: CS' + (S.m ? ' (' + esc(S.m[0]) + ')' : '') + '</div>'
              + '<table class="io-mc-tbl">'
              + (pass1.length
                  ? pass1.map(r => '<tr><th style="width:34%">Unit ' + r.unit_no + '</th><td><b>' + esc(r.ink_name) + '</b>'
                      + (r.coverage_pct !== null && r.coverage_pct !== undefined ? ' <span style="color:#555">' + fmtAngka(r.coverage_pct) + '% · ' + fmtAngka(r.coverage_cm2) + ' cm²</span>' : '')
                      + '</td></tr>').join('')
                  : '<tr><td colspan="2" style="text-align:center;color:#777">Susunan tinta belum ada</td></tr>')
              + '<tr><th>Isi / Lembar</th><td class="mono"><b>' + k.up + ' UP</b></td></tr>'
              + '</table>'
              // Mesin plong tidak diulang di sini -- sudah tertulis di Routing.
              + '<table class="io-mc-tbl" style="margin-top:5px">'
              + mcRow('Dieline / blank', fmtAngka(p.l) + ' × ' + fmtAngka(p.w) + ' cm · gap ' + fmtAngka(S.g) + ' cm', true)
              + mcRow('Plano', esc(S.pl[0]) + ' · ' + cut(S.pl).n + ' lembar / plano', true)
              + mcRow('Efisiensi material', ($('yAll') ? $('yAll').textContent : '—'))
              + '</table></div><div>'
              + '<div class="io-mc-cap">Urutan Pekerjaan (Routing)</div>'
              + '<table class="io-mc-tbl">'
              + Array.from({ length: ROUTING_ROWS }, (_, i) => {
                  const r = bop[i];
                  return '<tr><th style="width:22px;text-align:center">' + (i + 1) + '</th><td>'
                      + (r ? esc(mcStepName(r))
                          + (r.subcon_supplier ? ' <span style="color:#b45309">· subkon ' + esc(r.subcon_supplier) + '</span>' : '')
                        : '&nbsp;')
                      + '</td></tr>';
              }).join('')
              + '</table></div></div>'
              // Ringkasan order & BOM di kolom kiri, tanda tangan di kolom kanan
              // bawah layout -- tidak ada lagi baris selebar halaman yang
              // menyisakan ruang kosong di tengah (permintaan user 2026-09-18).
              + '<div class="io-mc-extra"><table class="io-mc-tbl">'
              + mcRow('Qty order', sp && sp.inputs.orderQty ? fmtAngka(sp.inputs.orderQty) + ' pcs' : '—', true)
              + mcRow('Lembar cetak', sp && sp.inputs.printSheets ? fmtAngka(sp.inputs.printSheets) + ' lbr' : '—', true)
              + mcRow('Isi / pack', sp && sp.inputs.packQty ? sp.inputs.packQty + ' pcs' : '—', true)
              + mcRow('No. Inquiry', esc(p.reg || '—'), true)
              + '</table><table class="io-mc-tbl">'
              + bom.slice(0, 6).map(mcBomRow).join('')
              + '</table><table class="io-mc-tbl">'
              + bom.slice(6, 12).map(mcBomRow).join('')
              + '</table></div>'
              + '</div>'

              + '<div><div class="io-mc-layout">'
              + '<div class="ttl">LAYOUT LEMBAR CETAK — ' + k.up + ' UP</div>'
              + svg + '</div>'
              + '<div class="io-mc-note">* ACC PROOF PRINT (DIGITAL + CETAK) HARUS DILAMPIRKAN BERSAMA MASTER CARD</div>'
              + '<div class="io-mc-sign">'
              + ['Mengetahui (Desainer)|Product Development', 'Marketing / Sales|Sales Officer',
                 'PPIC / QA|Quality Control', 'Plant Manager|Kepala Pabrik']
                  .map(x => { const [who, role] = x.split('|');
                      return '<div><div class="who">' + who + '</div><div class="line">' + role + '</div></div>'; }).join('')
              + '</div>'
              + '</div></div>'

              + (p.confirmed ? '' : '<div class="io-mc-stamp"><span>NOT CONFIRMED</span></div>');
      }

      function openMasterCard() {
          if (!ok()) { msg('Canvas masih di luar batas mesin — perbaiki dulu sebelum cetak Master Card.', 'bad'); return; }
          $('mcSheet').innerHTML = masterCardHtml();
          // Blok gelap diturunkan SETELAH masuk DOM: warna yang diwarisi dari
          // grup induk hanya terbaca lewat getComputedStyle.
          $('mcSheet').querySelectorAll('.io-mc-layout svg *').forEach(mcLighten);
          const st = $('mcState');
          st.textContent = S.p.confirmed ? 'CONFIRMED' : 'BELUM CONFIRM';
          st.className = 'io-mc-state' + (S.p.confirmed ? ' ok' : '');
          $('mcModal').classList.add('open');
      }

      function closeMasterCard() {
          $('mcModal').classList.remove('open');
      }

      // Job dipilih di grid, jadi di sini cuma DITAMPILKAN. Dropdown hanya
      // dipakai di mode ?demo=1 (banyak sampel), bukan di alur normal.
      function jobIdentity() {
          const banyak = products.length > 1;
          $('jobFixed').style.display = banyak ? 'none' : '';
          $('product').style.display = banyak ? '' : 'none';

          if (!banyak) {
              $('jobFixed').innerHTML = '<b>' + S.p.name + '</b><span>'
                  + S.p.design + (S.p.reg && S.p.reg !== '—' ? ' · ' + S.p.reg : '') + '</span>';
          }

          $('jobcount').textContent = banyak
              ? products.length + ' SAMPEL DEMO'
              : (S.p.design || 'JOB TERPILIH');
      }

      // Auto Up  -> tampilkan angka HASIL, sembunyikan kotak isian.
      // Manual Up -> tampilkan kotak isian (up jadi target), sembunyikan hasil.
      function upModeUi() {
          $('autoResult').style.display = S.auto ? '' : 'none';
          $('manualGroup').style.display = S.auto ? 'none' : '';
          $('modeText').textContent = S.auto
              ? 'Aktif: pakai up maksimum yang muat di canvas'
              : 'Nonaktif: tentukan sendiri jumlah up yang dikejar';
      }

      function artworkLabel() {
          $('artName').textContent = S.p.artworkName
              || (S.p.artworkUrl ? 'Artwork tersimpan di Inquiry Part' : (S.p.canUpload
                  ? 'Belum ada artwork — klik Upload artwork'
                  : 'Job Mode #2 / demo: tidak terhubung ke Inquiry Part'));
          $('dieName').textContent = S.p.dieline
              ? 'Dieline dari spesifikasi Tipe Produk (dieline_spec)'
              : 'Tipe produk ini belum punya definisi dieline';
      }

      function bind() {
          ['sl', 'sw', 'gap', 'grain', 'converterInput'].forEach(id => $(id).oninput = () => {
              S.L = +$('sl').value; S.W = +$('sw').value; S.g = +$('gap').value; update(false);
          });
          $('product').onchange = e => {
              S.p = products[e.target.value]; S.g = S.p.gap; $('gap').value = S.g;
              $('grain').value = S.p.grain; S.objs = [];
              jobIdentity(); renderSpecSections(); artworkLabel(); syncActiveJob(); suggest(); samakanTinggiAcc(); autoNestSoon();
          };
          $('machine').onchange = e => { S.m = machines[e.target.value]; S.objs = []; suggest(); autoNestSoon(); };
          $('diecut').onchange = e => { S.d = diecutters[e.target.value]; S.objs = []; suggest(); autoNestSoon(); };
          $('limit').onchange = e => setLimit(e.target.value, false);
          $('autoUp').onchange = e => {
              S.auto = e.target.checked;
              upModeUi();
              S.objs = [];
              // Pindah ke Manual Up: canvas yang sudah diketik officer
              // dipertahankan (suggest() akan mengecilkannya ke ukuran minimum
              // target). Ukuran otomatis tetap lewat tombol ✦ Suggest.
              if (S.auto) { suggest(); } else { S.touched = false; update(true); autoNestSoon(); }
          };
          $('manualUp').oninput = e => {
              S.manualUp = Math.max(1, +e.target.value || 1);
              // Target berubah -> susunan dibangun ulang lalu jumlah object
              // dicocokkan ke target (termasuk yang harus di luar canvas).
              if (!S.auto) { S.objs = []; S.touched = false; if (unlimited()) suggest(); else update(true); autoNestSoon(); }
          };
          // Ditekan user = optimasi menyeluruh (canvas + plano + pola).
          // Kalau susunan sudah diatur MANUAL (bukan hasil auto nesting),
          // perilaku lama dipertahankan: canvas dipaskan ke susunan itu.
          // Susunan yang sudah ada (Manual Up, atau digeser/diputar officer)
          // dipadatkan dan canvas dipaskan ke situ -- BUKAN dibuang lalu
          // dioptimasi ulang dari nol (dulu kembali ke canvas 1 up).
          $('suggest').onclick = () => {
              if (S.objs.length && (!S.auto || S.manualEdit)) { compactFit(); return; }
              optimizeSheet();
          };
          $('plano').onchange = e => { S.pl = planos[e.target.value]; update(false); };
          // Hitung ulang plano secara eksplisit (permintaan user 2026-09-18),
          // tidak bergantung pada tombol Suggestion.
          $('calcPlano').onclick = () => {
              const info = pickBestPlano();
              update(false);
              $('planoNote').innerHTML += '<br><b>Calculate Plano: ' + esc(info) + '.</b>';
          };
          $('custom').onclick = () => {
              const l = +$('cpl').value, w = +$('cpw').value;
              if (l && w) { S.pl = ['Custom ' + l + ' × ' + w + ' cm', l, w]; $('plano').value = ''; update(false); }
          };
          $('pcsMode').onclick = () => setMode('pcs');
          $('planoMode').onclick = () => setMode('plano');
          $('zout').onclick = () => z(-.15);
          $('zin').onclick = () => z(.15);
          app.querySelectorAll('[data-io-r]').forEach(b => b.onclick = () => transform('r', +b.dataset.ioR));
          app.querySelectorAll('[data-io-f]').forEach(b => b.onclick = () => transform('f', b.dataset.ioF));
          $('resetLayout').onclick = () => { S.objs = []; S.selected = null; update(true); };
          $('save').onclick = () => persist(false);
          $('confirm').onclick = () => persist(true);
          app.addEventListener('dl:menu', e => panelMenu(e.detail.act));
          $('mcPrint').onclick = () => window.print();
          $('mcClose').onclick = closeMasterCard;
          $('mcModal').onclick = e => { if (e.target === $('mcModal')) closeMasterCard(); };
          document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMasterCard(); });
          // Bukan `= autoNest` langsung: event klik akan terkirim sebagai
          // argumen pertama (silent) dan pesan hasilnya ikut tertelan.
          $('autoNest').onclick = () => autoNest(false);
          // Artwork: file input aslinya milik Livewire (di luar wire:ignore).
          $('artFile').onclick = e => {
              e.preventDefault();
              if (!S.p.canUpload) {
                  msg('Job ini tidak terhubung ke Inquiry Part (Mode #2 / demo) — artwork belum bisa disimpan.', 'bad');
                  return;
              }
              document.getElementById('io-artFileReal').click();
          };
          window.addEventListener('prodev-artwork-updated', e => {
              const d = e.detail || {};
              const job = products.find(j => j.partId === d.partId);
              if (!job) return;
              job.artworkUrl = d.url; job.artworkName = d.name;
              // Server sudah menganalisa (termasuk PDF/AI) -> pakai hasilnya.
              job.artColors = d.colors || null;
              if (S.p === job) {
                  artworkLabel(); render();
                  if (job.artColors) { specKey = ''; specSoon(); renderSpecSections(); } else { analyzeArtwork(); }
              }
          });
          $('dieFile').onchange = e => {
              // Dieline terpisah belum punya tempat simpan -- lihat catatan diskusi.
              $('dieName').textContent = (e.target.files[0] && e.target.files[0].name + ' (belum disimpan)') || 'Dieline struktural bawaan job aktif';
          };
      }

      function setMode(mode) {
          S.conv = mode;
          $('pcsMode').classList.toggle('active', mode === 'pcs');
          $('planoMode').classList.toggle('active', mode === 'plano');
          $('inputLabel').textContent = mode === 'pcs' ? 'Qty order (pcs)' : 'Available plano stock (sheets)';
          $('inputHint').textContent = mode === 'pcs'
              ? 'Masukkan jumlah produk yang harus diproduksi'
              : 'Masukkan jumlah lembar plano yang tersedia';
          $('converterInput').value = mode === 'pcs' ? 100 : 10;
          update(false);
      }

      function msg(t, c) { $('valid').className = 'io-notice ' + (c || 'info'); $('valid').textContent = t; }
      function z(x) {
          S.zoom = Math.max(.5, Math.min(1.8, S.zoom + x));
          $('wrap').style.transform = 'scale(' + S.zoom + ')';
          $('zoom').textContent = Math.round(S.zoom * 100) + '%';
      }
      // Canvas wajib muat di MEJA MESIN CETAK **dan** MEJA MESIN PLONG
      // (instruksi user 2026-09-16) -- lembar yang muat di press tapi tidak
      // muat di plong tetap tidak bisa diproduksi. Meja plong yang belum
      // diisi di Master Mesin tidak dikarang: batasnya sekadar tidak
      // diberlakukan, dan panel infonya mengatakan itu terang-terangan.
      // Sejak 2026-10-01 batasnya mengikuti pilihan "Canvas limit" (S.limit):
      // BOTH = aturan di atas; PRESS = meja cetak saja; DIECUT = meja pond
      // saja; CUSTOM = tanpa batas meja (canvas mengikuti dieline).
      function dieBed() { return S.d && S.d[1] && S.d[2] ? [S.d[1], S.d[2]] : null; }
      function bedLimits() {
          const die = dieBed();
          if (S.limit === 'CUSTOM') return [NOLIM, NOLIM];
          if (S.limit === 'DIECUT') return die || [NOLIM, NOLIM];
          if (S.limit === 'PRESS' || !die) return [S.m[1], S.m[2]];
          return [Math.min(S.m[1], die[0]), Math.min(S.m[2], die[1])];
      }
      function unlimited() { return bedLimits()[0] >= NOLIM; }
      function limTxt() { const [bl, bw] = bedLimits(); return bl >= NOLIM ? 'tanpa batas meja' : bl + ' × ' + bw + ' cm'; }

      function ok() {
          const [bl, bw] = bedLimits();
          return S.L > 0 && S.W > 0 && S.L <= bl && S.W <= bw;
      }

      // Mesin mana yang sedang jadi pembatas -- supaya peringatannya menyebut
      // nama mesinnya, bukan cuma angka.
      function bedOwner() {
          const [bl, bw] = bedLimits();
          if (S.limit === 'DIECUT') return S.d ? S.d[0] + ' (plong)' : S.m[0];
          return (S.limit === 'BOTH' && dieBed() && (bl < S.m[1] || bw < S.m[2]))
              ? S.d[0] + ' (plong)'
              : S.m[0];
      }

      // Mode Custom: canvas = dieline x jumlah up (grid, dua orientasi dicoba) + gap antar-up
      // + margin keliling & gripper (aturan usable() di optimizeSheet). Tanpa minimum 25 x 20.
      function customCanvas(n) {
          const up1 = v => Math.ceil(v * 10 - 1e-6) / 10;
          let bestC = null;
          for (const [a, b] of [[S.p.l, S.p.w], [S.p.w, S.p.l]]) for (let c = 1; c <= n; c++) {
              const r = Math.ceil(n / c), sx = c * a + (c - 1) * S.g, sy = r * b + (r - 1) * S.g;
              // Length = sisi panjang lembar; gripper di sisi panjang (menambah Width).
              const L = up1(Math.max(sx, sy) + 2 * MARGIN), W = up1(Math.min(sx, sy) + GRIPPER + MARGIN);
              if (!bestC || L * W < bestC.L * bestC.W - 1e-9) bestC = { L, W };
          }
          return bestC;
      }

      // Keterangan di bawah pilihan Canvas limit + saran bila 1 up saja tidak muat di meja terpilih.
      function limitNote() {
          const box = $('limitNote'), [bl, bw] = bedLimits(), die = dieBed(), c1 = customCanvas(1);
          const fits = (L, W) => (L <= bl && W <= bw) || (W <= bl && L <= bw);
          let cls = 'info', html;
          if (S.limit === 'CUSTOM') html = 'Canvas = dieline × up + gap + margin &amp; gripper. No bed limit. Default 1 up; raise it in <b>1. Jumlah Up</b>.';
          else if (S.limit === 'DIECUT' && !die) { cls = 'bad'; html = 'The bed size of <b>' + esc(S.d ? S.d[0] : 'die-cut machine') + '</b> is not filled in Master Machine yet, so there is no limit. Fill it in first for this choice to work.'; }
          else html = 'Limit: <b>' + esc(bedOwner()) + '</b> ' + bl + ' × ' + bw + ' cm' + (S.limit === 'BOTH' && !die ? ' (die-cut bed size not filled in yet: press bed only)' : '') + '.';
          if (!unlimited() && !fits(c1.L, c1.W)) {
              cls = 'bad';
              html = 'Dieline 1 up needs <b>' + c1.L + ' × ' + c1.W + ' cm</b>, larger than ' + esc(bedOwner()) + ' (' + bl + ' × ' + bw + ' cm). Consider splitting the part left / right (<b>Double Join</b>) in Inquiry / Master Product, or go back to Custom.';
          }
          box.className = 'io-notice ' + cls; box.innerHTML = html;
      }

      // Ganti batas canvas: Custom -> Manual Up (bawaan 1 up saat layar dibuka), batas meja -> Auto Up seperti semula.
      function setLimit(v, first) {
          S.limit = v; $('limit').value = v;
          S.objs = []; S.touched = false;
          if (unlimited()) { S.auto = false; if (first) S.manualUp = 1; $('manualUp').value = S.manualUp; } else S.auto = true;
          $('autoUp').checked = S.auto;
          upModeUi();
          suggest();
          autoNestSoon();
      }
      function fit(L, W, a, b, g) {
          const c = Math.floor((L + g) / (a + g)), r = Math.floor((W + g) / (b + g));
          return { ups: c * r, c, r, rot: false };
      }
      function best() {
          const a = fit(S.L, S.W, S.p.l, S.p.w, S.g), b = fit(S.L, S.W, S.p.w, S.p.l, S.g);
          if (b.ups > a.ups) { b.rot = true; return b; }
          return a;
      }
      function selectedUp() { return S.auto ? best().ups : S.manualUp; }

      // Object yang keluar canvas TIDAK sah (tidak bisa dicetak) -- tidak
      // boleh ikut dihitung sebagai up, disimpan, atau dicetak di Master Card.
      function inCanvas(o) {
          const v = obox(o), sc = S.sc0 || 1, sw = S.L * sc, sh = S.W * sc;
          return !(v.x < S.sx0 - .6 || v.y < S.sy0 - .6 || v.x + v.w > S.sx0 + sw + .6 || v.y + v.h > S.sy0 + sh + .6);
      }

      function upValid() {
          return S.objs.length ? S.objs.filter(inCanvas).length : selectedUp();
      }

      function obox(o) {
          const a = o.r * Math.PI / 180, c = Math.abs(Math.cos(a)), s = Math.abs(Math.sin(a)),
              W = o.w * c + o.h * s, H = o.w * s + o.h * c, cx = o.x + o.w / 2, cy = o.y + o.h / 2;
          return { x: cx - W / 2, y: cy - H / 2, w: W, h: H };
      }

      // Mask diperlebar `g` px ke segala arah (lingkaran): dua object dianggap
      // bertabrakan bila jaraknya < gap. Cukup piksel tepi yang dicap --
      // piksel dalam sudah tertutup dengan sendirinya.
      function dilateMask(m, g) {
          if (g <= 0) return m;
          const cache = m._dil || (m._dil = {});
          if (cache[g]) return cache[g];

          const W = m.w + 2 * g, H = m.h + 2 * g, out = new Uint8Array(W * H);
          const disc = [];
          for (let dy = -g; dy <= g; dy++) for (let dx = -g; dx <= g; dx++) if (dx * dx + dy * dy <= g * g) disc.push(dy * W + dx);
          const on = (x, y) => x >= 0 && y >= 0 && x < m.w && y < m.h && m.data[y * m.w + x];

          for (let y = 0; y < m.h; y++) for (let x = 0; x < m.w; x++) {
              if (!m.data[y * m.w + x]) continue;
              const c = (y + g) * W + x + g;
              if (on(x - 1, y) && on(x + 1, y) && on(x, y - 1) && on(x, y + 1)) { out[c] = 1; continue; }
              for (const d of disc) out[c + d] = 1;
          }

          return (cache[g] = { w: W, h: H, data: out, shaped: m.shaped });
      }

      // Tombol Suggest pada susunan yang sudah ada (permintaan user
      // 2026-09-18): object dirapatkan ke kiri & ke atas sampai jarak antar
      // object = gap, lalu canvas = kotak pembatas + gap keliling. Uji
      // jarak memakai bentuk dieline asli (mask), jadi blank yang saling
      // mengunci tetap boleh masuk ke cekungan tetangganya.
      async function compactFit() {
          if (S.objs.some(o => (((Math.round(o.r) % 360) + 360) % 360) % 90)) { fitToLayout(); return; }

          const base = await maskFor(S.p);
          const f = base.mmPerPx || 1;                      // mm per piksel mask
          const g = Math.max(0, Math.round(S.g * 10 / f));  // gap dalam piksel
          const sc = S.sc0 || 1;

          const it = S.objs.map(o => {
              const r = ((Math.round(o.r) % 360) + 360) % 360;
              const m = variantFor(base, r, o.fx, o.fy);
              return {
                  o, m, d: dilateMask(m, g), r, r0: r,
                  x: Math.round((o.x + o.w / 2 - S.sx0) / sc * 10 / f - m.w / 2),
                  y: Math.round((o.y + o.h / 2 - S.sy0) / sc * 10 / f - m.h / 2),
              };
          });

          // a (diperlebar gap) di (ax,ay) vs b (mask asli) di posisinya
          const clash = (a, ax, ay, b) => {
              const x0 = ax - g, y0 = ay - g;
              if (b.x >= x0 + a.d.w || b.x + b.m.w <= x0 || b.y >= y0 + a.d.h || b.y + b.m.h <= y0) return false;
              const dx = b.x - x0, dy = b.y - y0;
              return dx >= 0 ? hits(a.d, b.m, dx, dy) : hits(b.m, a.d, -dx, -dy);
          };
          const free = (a, x, y) => it.every(b => b === a || !clash(a, x, y, b));

          const norm = () => {
              const mx = Math.min(...it.map(a => a.x)), my = Math.min(...it.map(a => a.y));
              it.forEach(a => { a.x -= mx; a.y -= my; });
          };

          const slide = (a, axis) => {
              let moved = false;
              for (const step of [8, 1]) {
                  for (;;) {
                      const nx = axis === 'x' ? a.x - step : a.x, ny = axis === 'y' ? a.y - step : a.y;
                      if ((axis === 'x' ? nx : ny) < 0 || !free(a, nx, ny)) break;
                      a.x = nx; a.y = ny; moved = true;
                  }
              }
              return moved;
          };

          const compact = () => {
              // 1) Yang terlalu rapat / bertumpuk didorong ke kanan sampai berjarak gap.
              norm();
              const placed = [];
              for (const a of [...it].sort((p, q) => p.x - q.x || p.y - q.y)) {
                  let guard = 0;
                  while (placed.some(b => clash(a, a.x, a.y, b)) && guard++ < 5000) a.x++;
                  placed.push(a);
              }
              // 2) Rapatkan ke kiri lalu ke atas, berulang sampai tidak ada yang bergeser.
              for (let pass = 0; pass < 8; pass++) {
                  norm();
                  let moved = false;
                  for (const a of [...it].sort((p, q) => p.x - q.x)) moved = slide(a, 'x') || moved;
                  for (const a of [...it].sort((p, q) => p.y - q.y)) moved = slide(a, 'y') || moved;
                  if (!moved) break;
              }
              norm();
          };

          // Susunan awal bisa saja bertumpuk ke bawah (mis. sisa object yang
          // tadinya di luar canvas) sehingga hasil rapatnya melebihi meja
          // mesin, padahal disusun menyamping muat. Bila begitu, object
          // disusun ulang per baris / per kolom dalam batas meja -- juga
          // dicoba dengan semua object diputar 90° -- lalu dirapatkan lagi;
          // dipilih yang muat dengan luas terkecil.
          const [bedL, bedW] = bedLimits();
          const lim = [(bedL - 2 * S.g) * 10 / f, (bedW - 2 * S.g) * 10 / f];
          const span = () => [Math.max(...it.map(a => a.x + a.m.w)), Math.max(...it.map(a => a.y + a.m.h))];
          const over = sp => Math.max(0, sp[0] - lim[0]) + Math.max(0, sp[1] - lim[1]);
          const snap = () => it.map(a => [a.x, a.y, a.r]);
          const turn = add => it.forEach(a => {
              a.r = (a.r0 + add) % 360;
              a.m = variantFor(base, a.r, a.o.fx, a.o.fy);
              a.d = dilateMask(a.m, g);
          });
          const reflow = byRow => {
              const ax = byRow ? 0 : 1, len = a => byRow ? a.m.w : a.m.h, thick = a => byRow ? a.m.h : a.m.w;
              let pos = 0, line = 0, lineT = 0;
              for (const a of [...it].sort((p, q) => byRow ? p.y - q.y || p.x - q.x : p.x - q.x || p.y - q.y)) {
                  if (pos > 0 && pos + len(a) > lim[ax]) { pos = 0; line += lineT + g; lineT = 0; }
                  if (byRow) { a.x = pos; a.y = line; } else { a.x = line; a.y = pos; }
                  pos += len(a) + g; lineT = Math.max(lineT, thick(a));
              }
          };

          compact();
          let best = { pos: snap(), sp: span() };
          if (over(best.sp) > 0) {
              for (const add of [0, 90]) {
                  turn(add);
                  for (const byRow of [true, false]) {
                      reflow(byRow);
                      compact();
                      const sp = span(), o = over(sp);
                      if (o < over(best.sp) || (o === 0 && sp[0] * sp[1] < best.sp[0] * best.sp[1])) best = { pos: snap(), sp };
                  }
              }
              it.forEach((a, i) => {
                  [a.x, a.y, a.r] = best.pos[i];
                  a.m = variantFor(base, a.r, a.o.fx, a.o.fy);
                  a.o.r = a.r;
              });
          }

          const spanX = Math.max(...it.map(a => a.x + a.m.w)) * f / 10;   // cm
          const spanY = Math.max(...it.map(a => a.y + a.m.h)) * f / 10;
          const ceil1 = v => Math.ceil(v * 10 - 1e-6) / 10;
          const rl = ceil1(spanX + 2 * S.g), rw = ceil1(spanY + 2 * S.g);
          const [bl, bw] = bedLimits();
          const L = Math.min(rl, bl), W = Math.min(rw, bw);

          S.L = L; S.W = W; $('sl').value = L; $('sw').value = W;
          const [uw, uh] = usableVB();
          const n = Math.min(uw / L, uh / W), nx = (VB.w - L * n) / 2, ny = (VB.h - W * n) / 2;
          it.forEach(a => {
              a.o.w = S.p.l * n; a.o.h = S.p.w * n;
              a.o.x = nx + (S.g + (a.x + a.m.w / 2) * f / 10) * n - a.o.w / 2;
              a.o.y = ny + (S.g + (a.y + a.m.h / 2) * f / 10) * n - a.o.h / 2;
          });
          S.sc0 = n; S.sx0 = nx; S.sy0 = ny; S.touched = true;
          // Susunan sudah bukan hasil auto nesting lagi; peringatan target up
          // dihitung ulang dari object yang kini benar-benar di dalam canvas.
          S.autoNestInfo = null;
          noteShortfall(S.objs.filter(inCanvas).length);

          // Plano Configuration ikut canvas baru. Plano custom (diketik
          // officer sendiri) tidak ditimpa.
          const planoInfo = planos.includes(S.pl) ? pickBestPlano() : 'plano custom ' + S.pl[0] + ' tidak diubah';

          const use = (S.objs.length * S.p.l * S.p.w / (L * W) * 100).toFixed(1);
          $('suggestNote').textContent = (rl > L || rw > W)
              ? 'Susunan ' + S.objs.length + ' object butuh ' + rl + ' × ' + rw + ' cm — melebihi meja ' + bedOwner() + ' (' + bl + ' × ' + bw + ' cm). Canvas dipotong ke ' + L + ' × ' + W + ' cm, object di luar batas ditandai merah.'
              : 'Canvas dipaskan ke susunan: ' + S.objs.length + ' object dirapatkan, gap ' + S.g + ' cm antar-object & keliling = ' + L + ' × ' + W + ' cm · pemakaian area ' + use + '%'
                + ' · ' + planoInfo + ' (' + cut(S.pl).n + ' canvas/plano).';
          update(false);
          msg('Suggestion: canvas ' + L + ' × ' + W + ' cm · ' + planoInfo + '.', 'ok');
          checkManualOverlap();
      }

      // Suggest saat susunan sudah disentuh user: canvas dihitung ulang dari
      // bounding box susunan + bleed (TIDAK me-reset nesting manual).
      function fitToLayout() {
          const [uw, uh] = usableVB();
          const sc = Math.min(uw / S.L, uh / S.W), sx = (VB.w - S.L * sc) / 2, sy = (VB.h - S.W * sc) / 2, m = S.p.bleed,
              b = S.objs.map(o => { const q = obox(o); return { x: (q.x - sx) / sc, y: (q.y - sy) / sc, X: (q.x + q.w - sx) / sc, Y: (q.y + q.h - sy) / sc }; }),
              minx = Math.min(...b.map(v => v.x)), miny = Math.min(...b.map(v => v.y)),
              maxx = Math.max(...b.map(v => v.X)), maxy = Math.max(...b.map(v => v.Y)),
              cen = S.objs.map(o => ({ o, cx: (o.x + o.w / 2 - sx) / sc - minx + m, cy: (o.y + o.h / 2 - sy) / sc - miny + m })),
              rl = +(maxx - minx + 2 * m).toFixed(1), rw = +(maxy - miny + 2 * m).toFixed(1),
              L = Math.min(rl, bedLimits()[0]), W = Math.min(rw, bedLimits()[1]),
              area = S.objs.length * S.p.l * S.p.w, use = (area / (L * W) * 100).toFixed(1);

          S.L = L; S.W = W; $('sl').value = L; $('sw').value = W;
          const n = Math.min(uw / L, uh / W), nx = (VB.w - L * n) / 2, ny = (VB.h - W * n) / 2, k = n / sc;
          cen.forEach(r => { r.o.w *= k; r.o.h *= k; r.o.x = nx + r.cx * n - r.o.w / 2; r.o.y = ny + r.cy * n - r.o.h / 2; });
          S.sc0 = n; S.sx0 = nx; S.sy0 = ny;
          $('suggestNote').textContent = (rl > L || rw > W)
              ? 'Susunan custom butuh ' + rl + ' × ' + rw + ' cm — melebihi meja ' + bedOwner() + ' (' + limTxt() + '). Canvas dipotong ke ' + L + ' × ' + W + ' cm, object di luar batas ditandai merah.'
              : 'Custom nesting: bounding box ' + S.objs.length + ' object + bleed ' + m + ' cm/sisi = ' + L + ' × ' + W + ' cm · pemakaian area ' + use + '%.';
          update(false);
      }

      function suggest() {
          if (S.touched && S.objs.length) { fitToLayout(); return; }
          const target = S.auto ? null : S.manualUp, p = S.p;
          // Batas pencarian = meja mesin cetak DAN meja plong, bukan bed
          // mesin cetak saja -- canvas yang muat di press tapi tidak muat
          // di plong tetap tidak bisa diproduksi.
          const m = bedLimits();
          if (m[0] >= NOLIM) {
              // Tanpa batas meja: Manual Up -> canvas pas untuk jumlah up itu; Auto Up -> canvas yang diketik dipertahankan.
              if (!S.auto) {
                  const c = customCanvas(Math.max(1, Math.round(S.manualUp) || 1));
                  S.L = c.L; S.W = c.W; $('sl').value = S.L; $('sw').value = S.W;
              }
              $('suggestNote').textContent = S.auto
                  ? 'Auto Up tanpa batas meja: jumlah up terbanyak di canvas yang diketik.'
                  : 'Custom: canvas ' + S.L + ' × ' + S.W + ' cm mengikuti dieline untuk ' + S.manualUp + ' up (termasuk gap, margin & gripper).';
              update(true);
              return;
          }
          let bestC = null;
          for (let L = 25; L <= m[0]; L += .5) for (let W = 20; W <= m[1]; W += .5) {
              const a = fit(L, W, p.l, p.w, S.g), b = fit(L, W, p.w, p.l, S.g), f = b.ups > a.ups ? b : a;
              if (target ? f.ups >= target : f.ups > 0) {
                  const area = L * W, h = Math.abs(L - p.history[0]) + Math.abs(W - p.history[1]),
                      score = target ? area + h * .15 : -(f.ups / area) + h * .002;
                  if (!bestC || score < bestC.score) bestC = { L, W, score };
              }
          }
          if (!bestC) {
              update(true);
              msg('Target ' + target + ' up tidak muat: butuh canvas melebihi meja ' + bedOwner()
                  + ' (maks ' + m[0] + ' × ' + m[1] + ' cm).', 'bad');
              return;
          }
          S.L = +bestC.L.toFixed(1); S.W = +bestC.W.toFixed(1);
          $('sl').value = S.L; $('sw').value = S.W; S.objs = [];
          $('suggestNote').textContent = S.auto
              ? 'Auto Up: canvas disarankan dari history job, packing efficiency, dan machine bed.'
              : 'Manual ' + target + '-up: canvas minimum yang dapat memuat jumlah object tersebut adalah ' + S.L + ' × ' + S.W + ' cm.';
          update(true);
      }

      function update(reset) {
          metrics();
          $('machineInfo').innerHTML = '<b>' + S.m[0] + '</b><br>Maximum printable bed: <b>' + S.m[1] + ' × ' + S.m[2] + ' cm</b>'
              + (S.m[3] ? '<br><span class="io-warntxt">⚠ max_paper_size belum diisi di Master Mesin — angka di atas asumsi.</span>' : '');
          $('diecutInfo').innerHTML = !S.d
              ? '<span class="io-warntxt">⚠ Belum ada mesin plong aktif di Master Mesin.</span>'
              : '<b>' + S.d[0] + '</b><br>' + (S.d[1] && S.d[2]
                  ? 'Maksimum meja plong: <b>' + S.d[1] + ' × ' + S.d[2] + ' cm</b>'
                  : '<span class="io-warntxt">⚠ Ukuran meja belum diisi di Master Mesin — batas plong belum bisa ditegakkan, canvas baru dibatasi bed mesin cetak saja.</span>');
          if (reset) S.objs = [];
          limitNote();
          render();
          calculator();
      }

      function metrics() {
          const p = S.p;
          $('metrics').innerHTML =
              '<div class="io-metric"><span>Finished L × W × H</span><b>' + p.fl + ' × ' + p.fw + ' × ' + p.fh + ' cm</b></div>' +
              '<div class="io-metric"><span>Dieline / blank</span><b>' + p.l + ' × ' + p.w + ' cm</b></div>' +
              '<div class="io-metric"><span>Blank area</span><b>' + (p.l * p.w).toFixed(1) + ' cm²</b></div>' +
              '<div class="io-metric"><span>Bleed / Gap</span><b>' + p.bleed + ' / ' + p.gap + ' cm</b></div>';
          $('spec').innerHTML = '<table>' +
              '<tr><td>Customer</td><td>' + p.customer + '</td></tr>' +
              '<tr><td>Design No.</td><td>' + p.design + '</td></tr>' +
              '<tr><td>Category</td><td>' + p.category + '</td></tr>' +
              '<tr><td>Structure</td><td>' + p.structure + '</td></tr>' +
              '<tr><td>Material</td><td>' + p.material + '</td></tr>' +
              '<tr><td>Artwork</td><td>' + p.art + '</td></tr>' +
              '<tr><td>Reg / Note</td><td>' + p.reg + '</td></tr></table>';
      }

      function render() {
          if (!ok()) {
              const [bl, bw] = bedLimits();
              const pembatas = bedOwner();
              $('arts').innerHTML = ''; $('grainG').innerHTML = '';
              $('sheetLabel').textContent = 'CANVAS ' + S.L + ' × ' + S.W + ' cm — DI LUAR BATAS MESIN';
              if (!(S.L > 0 && S.W > 0)) {
                  $('sheetLabel').textContent = 'CANVAS belum diisi';
                  $('warning').classList.remove('show');
                  msg('Isi Length dan Width canvas (lebih besar dari 0).', 'bad');
                  $('status').textContent = 'Validation failed';
                  return;
              }
              $('warning').innerHTML = '⚠ Canvas ' + S.L + ' × ' + S.W + ' cm melebihi meja ' + pembatas +
                  '<br>Maksimum ' + bl + ' × ' + bw + ' cm — ' + S.objs.length + ' object disembunyikan';
              $('warning').classList.add('show');
              msg('Invalid: canvas tidak boleh melebihi ' + bl + ' × ' + bw + ' cm (batas ' + pembatas + ').', 'bad');
              $('status').textContent = 'Validation failed';
              return;
          }
          const [uw, uh] = usableVB();
          const sc = Math.min(uw / S.L, uh / S.W), w = S.L * sc, h = S.W * sc, x = (VB.w - w) / 2, y = (VB.h - h) / 2;
          if (S.objs.length && S.sc0 && (S.sc0 !== sc || S.sx0 !== x || S.sy0 !== y)) {
              const k = sc / S.sc0;
              S.objs.forEach(o => {
                  const cx = (o.x + o.w / 2 - S.sx0) / S.sc0, cy = (o.y + o.h / 2 - S.sy0) / S.sc0;
                  o.w *= k; o.h *= k; o.x = x + cx * sc - o.w / 2; o.y = y + cy * sc - o.h / 2;
              });
          }
          S.sc0 = sc; S.sx0 = x; S.sy0 = y;
          [['sheet', 'x', x], ['sheet', 'y', y], ['sheet', 'width', w], ['sheet', 'height', h],
           ['safeRect', 'x', x + 4], ['safeRect', 'y', y + 4], ['safeRect', 'width', w - 8], ['safeRect', 'height', h - 8]]
              .forEach(a => $(a[0]).setAttribute(a[1], a[2]));
          $('sheetLabel').textContent = 'CANVAS / PRINT SHEET: ' + S.L + ' × ' + S.W + ' cm';
          $('grainG').innerHTML = grain(x, y, w, h);
          if (!S.objs.length) createObjects(x, y, sc);
          arts(x, y, w, h);
          if (S.auto && S.objs.length) {
              // Angka up = HASIL, jadi ditulis ke panel hasil. S.manualUp ikut
              // disamakan supaya begitu user pindah ke mode Manual, titik awalnya
              // angka nyata -- bukan angka bawaan mockup (dulu selalu 6).
              S.manualUp = S.objs.length;
              $('manualUp').value = S.objs.length;
              $('autoResultValue').textContent = S.objs.length + ' up';
              $('autoResultNote').textContent = S.autoNestInfo
                  ? S.autoNestInfo
                  : 'Grid otomatis dari ukuran canvas & bentuk blank. Tekan ✦ Auto Nesting untuk coba susunan interlock.';
          }
          msg('Valid canvas. Drag setiap object secara independen untuk manual nesting berdasarkan bentuk dieline.', 'ok');
          $('status').textContent = S.p.category + ' · ' + S.objs.length + ' object(s) · ' +
              (S.auto ? 'Auto Up' : 'Manual Up') + ' · ' + (S.selected ? 'selected: ' + S.selected.toUpperCase() : 'no selection');
      }

      function grain(x, y, w, h) {
          const d = $('grain').value;
          if (d === 'none') return '';
          return d === 'L'
              ? '<line class="io-grain" x1="' + (x + 16) + '" y1="' + (y + h - 18) + '" x2="' + (x + 16) + '" y2="' + (y + 18) + '"/>' +
                '<text class="io-graintext" x="' + (x + 23) + '" y="' + (y + 30) + '">GRAIN · DIELINE LENGTH</text>'
              : '<line class="io-grain" x1="' + (x + 18) + '" y1="' + (y + 16) + '" x2="' + (x + w - 18) + '" y2="' + (y + 16) + '"/>' +
                '<text class="io-graintext" x="' + (x + 32) + '" y="' + (y + 30) + '">GRAIN · DIELINE WIDTH</text>';
      }

      // Target up yang tidak tercapai HARUS diberitahukan (user 2026-09-17:
      // "kalau dengan jumlah up ukurannya melebihi meja mesin, harus di
      // warning"). Tanpa ini layar diam-diam memangkas jumlahnya dan angka
      // yield di panel kanan terlihat wajar padahal bukan yang diminta.
      // Canvas sudah dibatasi meja mesin cetak & meja plong (bedLimits),
      // jadi "tidak muat di canvas" = tidak muat di mesin.
      function noteShortfall(tercapai) {
          if (S.auto || S.manualUp <= tercapai) { S.upShort = null; return; }

          // Dibedakan: "canvas sekarang kekecilan" (masih bisa diperbesar)
          // vs "memang tidak muat di mesin" (canvas terbesar yang diizinkan
          // meja pun tidak cukup) -- dua hal itu butuh tindakan berbeda.
          const [bl, bw] = bedLimits();
          const a = fit(bl, bw, S.p.l, S.p.w, S.g), b = fit(bl, bw, S.p.w, S.p.l, S.g);

          S.upShort = { target: S.manualUp, dapat: tercapai, maxMesin: Math.max(a.ups, b.ups) };
      }

      function createObjects(x, y, sc) {
          // Grid otomatis dibangun ulang = hasil auto nesting sebelumnya sudah
          // tidak berlaku, labelnya harus ikut hilang (jangan jadi klaim basi).
          S.touched = false;
          S.manualEdit = false;
          S.autoNestInfo = null;
          S.overlapPx = 0;
          const f = best();
          // Manual Up: SEMUA object yang diminta officer ditempatkan, walau
          // sebagian keluar canvas -- canvas ini memang dipakai untuk
          // menggeser object sendiri (instruksi user 2026-09-18). Auto Up
          // tetap memakai jumlah yang benar-benar muat.
          const n = S.auto ? Math.min(selectedUp(), f.ups) : Math.max(1, Math.round(S.manualUp) || 1);
          const cols = Math.max(1, f.c);
          noteShortfall(Math.min(n, f.ups));

          for (let i = 0; i < n; i++) {
              const r = Math.floor(i / cols), c = i % cols;
              const ww = (f.rot ? S.p.w : S.p.l) * sc, hh = (f.rot ? S.p.l : S.p.w) * sc;
              S.objs.push({
                  id: 'a' + i,
                  x: x + c * ((f.rot ? S.p.w : S.p.l) + S.g) * sc,
                  y: y + r * ((f.rot ? S.p.l : S.p.w) + S.g) * sc,
                  w: ww, h: hh, r: 0, fx: 1, fy: 1,
              });
          }
      }

      // Mode Manual Up: jumlah object di canvas HARUS sama dengan target
      // officer. Hasil auto nesting yang cuma memuat sebagian tetap dipakai,
      // sisanya ditaruh di ruang kosong SAMPING canvas yang terlihat di layar
      // (dulu di bawah canvas -> jatuh di luar bidang gambar, tak terlihat),
      // supaya tinggal digeser ke tempat yang benar.
      function ensureManualCount() {
          if (S.auto || !S.sc0) return;

          const target = Math.max(1, Math.round(S.manualUp) || 1);

          if (S.objs.length > target) {
              S.objs = S.objs.slice(0, target);
              if (S.selected && !S.objs.some(o => o.id === S.selected)) S.selected = null;
              return;
          }

          const sc = S.sc0, w = S.p.l * sc, h = S.p.w * sc, gap = Math.max(4, S.g * sc);
          const right = S.sx0 + S.L * sc + gap;
          // Kolom samping yang terlihat: [kiri kolom, lebar tersedia]
          const cols = [[right, VB.w - right - 4], [4, S.sx0 - gap - 4]].filter(c => c[1] >= Math.min(w, h));
          let seq = S.objs.length, ci = 0, cy = Math.max(4, S.sy0), colX = cols.length ? cols[0][0] : 0, k = 0;

          while (S.objs.length < target) {
              let o;
              if (ci < cols.length) {
                  const [cx0, room] = cols[ci];
                  const rot = w > room;                        // tidak muat melintang -> putar 90°
                  const bw = rot ? h : w, bh = rot ? w : h;
                  if (cy + bh > VB.h - 4 && cy > Math.max(4, S.sy0)) {
                      // kolom penuh -> kolom berikutnya
                      colX += bw + gap;
                      if (colX + bw > cx0 + room) { ci++; colX = ci < cols.length ? cols[ci][0] : 0; }
                      cy = Math.max(4, S.sy0);
                      continue;
                  }
                  // kolom kiri diisi dari tepi canvas ke arah luar
                  const left = ci === 1 ? cx0 + room - bw - (colX - cx0) : colX;
                  o = { x: left + bw / 2 - w / 2, y: cy + bh / 2 - h / 2, r: rot ? 90 : 0 };
                  cy += bh + gap;
              } else {
                  o = { x: S.sx0 + (k % Math.max(1, Math.floor(S.L * sc / (w + gap)))) * (w + gap),
                        y: S.sy0 + S.W * sc + gap + Math.floor(k / Math.max(1, Math.floor(S.L * sc / (w + gap)))) * (h + gap), r: 0 };
                  k++;
              }
              S.objs.push({ id: 'a' + (seq++), x: o.x, y: o.y, w, h, r: o.r, fx: 1, fy: 1 });
          }
      }

      // Artwork job NYATA: pakai diecut asli dari DSL dieline_spec
      // (resources/js/tmdieline.js, renderer yang sama dengan layar
      // Inquiry Product) -- bukan kotak polos, kecuali tipe produknya
      // memang belum punya elements DSL.
      function realShape(p, w, h) {
          let out = '<rect class="io-artfill" width="' + w + '" height="' + h + '" rx="1"/>';
          // Artwork asli (iqp_part.artwork_path) jadi permukaan cetak, garis
          // dieline digambar DI ATASNYA supaya batas potong tetap terbaca.
          if (p.artworkUrl) {
              out += '<image href="' + p.artworkUrl + '" x="0" y="0" width="' + w + '" height="' + h + '" preserveAspectRatio="none"/>';
          }
          let inner = null;
          if (p.dieline && window.TMDieline) {
              try { inner = window.TMDieline.svg(p.dieline, p.dielineDims || {}); } catch (e) { inner = null; }
          }
          if (inner) {
              return out + inner
                  .replace('width="100%" height="100%"', 'x="0" y="0" width="' + w + '" height="' + h + '" preserveAspectRatio="none"')
                  .replace(/stroke-width="1"/g, 'stroke-width="1.9" vector-effect="non-scaling-stroke"');
          }
          return out + '<rect class="io-die" x="' + (w * .012) + '" y="' + (h * .018) + '" width="' + (w * .976) + '" height="' + (h * .964) + '"/>' +
              '<text class="io-lbl" x="' + (w * .5) + '" y="' + (h * .52) + '" text-anchor="middle">' + (p.design || '') + '</text>';
      }

      function shape(o) {
          const w = o.w, h = o.h, f = Math.min(w, h);
          const cut = '<path class="io-cutmark" d="M-5 5H0M-5 ' + (h - 5) + 'H0M' + w + ' 5H' + (w + 5) + 'M' + w + ' ' + (h - 5) + 'H' + (w + 5) + '"/>';
          // Sampel UAT punya artwork vector bawaan (type terisi) -- itu yang
          // dipakai, karena struktur Tray Lid/2-Piece Wrap/Lock Bottom belum
          // ada di pustaka dieline DSL. Job lain pakai diecut asli dari DSL.
          if (!S.p.type) return realShape(S.p, w, h) + cut;

          if (S.p.type === 'alsamsi_top') {
              const x1 = w * .185, x2 = w * .815, y1 = h * .22, y2 = h * .78, n = f * .11;
              return '<path class="io-artfill" d="M' + n + ' 0H' + (w - n) + 'L' + w + ' ' + n + 'V' + (h - n) + 'L' + (w - n) + ' ' + h + 'H' + n + 'L0 ' + (h - n) + 'V' + n + 'Z" style="fill:#fbfcff"/>' +
                  '<rect x="' + x1 + '" y="' + y1 + '" width="' + (x2 - x1) + '" height="' + (y2 - y1) + '" fill="#17111a"/>' +
                  '<g fill="#413a4d"><circle cx="' + (w * .33) + '" cy="' + (h * .35) + '" r="' + (f * .075) + '"/><circle cx="' + (w * .67) + '" cy="' + (h * .65) + '" r="' + (f * .075) + '"/><circle cx="' + (w * .5) + '" cy="' + (h * .5) + '" r="' + (f * .05) + '"/><circle cx="' + (w * .33) + '" cy="' + (h * .66) + '" r="' + (f * .045) + '"/><circle cx="' + (w * .67) + '" cy="' + (h * .34) + '" r="' + (f * .045) + '"/></g>' +
                  '<rect x="' + x1 + '" y="' + y1 + '" width="' + (w * .022) + '" height="' + (y2 - y1) + '" fill="#c89b40"/><rect x="' + (x2 - w * .022) + '" y="' + y1 + '" width="' + (w * .022) + '" height="' + (y2 - y1) + '" fill="#c89b40"/>' +
                  '<rect x="' + (w * .36) + '" y="' + (h * .45) + '" width="' + (w * .28) + '" height="' + (h * .1) + '" fill="#0b0910" stroke="#c89b40" stroke-width=".7"/>' +
                  '<text class="io-lbl" x="' + (w * .5) + '" y="' + (h * .515) + '" text-anchor="middle" fill="#eacf94">AL SAMSI</text>' +
                  '<g fill="#0a0a12" stroke="#c89b40" stroke-width=".7">' +
                  '<rect x="' + (w * .055) + '" y="' + (h * .415) + '" width="' + (f * .2) + '" height="' + (f * .2) + '" transform="rotate(45 ' + (w * .055 + f * .1) + ' ' + (h * .415 + f * .1) + ')"/>' +
                  '<rect x="' + (w * .945 - f * .2) + '" y="' + (h * .415) + '" width="' + (f * .2) + '" height="' + (f * .2) + '" transform="rotate(45 ' + (w * .945 - f * .1) + ' ' + (h * .415 + f * .1) + ')"/></g>' +
                  '<text class="io-lbl" x="' + (w * .055 + f * .1) + '" y="' + (h * .53) + '" text-anchor="middle" fill="#3fa9e8">ALSAMSI</text>' +
                  '<text class="io-lbl" x="' + (w * .945 - f * .1) + '" y="' + (h * .53) + '" text-anchor="middle" fill="#3fa9e8">ALSAMSI</text>' +
                  '<text class="io-lbl" x="' + (w * .5) + '" y="' + (h * .13) + '" text-anchor="middle" fill="#6d5a2c">EXCLUSIVE · SARUNG TENUN</text>' +
                  // Hang tab (struktur: Tray Lid + Hang Tab) -- lubang gantung ikut dipotong.
                  '<circle class="io-die" cx="' + (w * .5) + '" cy="' + (h * .105) + '" r="' + (f * .045) + '"/>' +
                  '<path class="io-fold" d="M' + x1 + ' 0V' + h + 'M' + x2 + ' 0V' + h + 'M0 ' + y1 + 'H' + w + 'M0 ' + y2 + 'H' + w + '"/>' +
                  // 4 corner lock: sudut tray dipotong diagonal, bukan lurus.
                  '<path class="io-die" d="M' + x1 + ' ' + y1 + 'L0 ' + y1 + 'M' + x2 + ' ' + y1 + 'L' + w + ' ' + y1 + 'M' + x1 + ' ' + y2 + 'L0 ' + y2 + 'M' + x2 + ' ' + y2 + 'L' + w + ' ' + y2 + '"/>' +
                  '<path class="io-die" d="M' + n + ' ' + (h * .03) + 'H' + (w - n) + 'L' + (w * .97) + ' ' + n + 'V' + (h - n) + 'L' + (w - n) + ' ' + (h * .97) + 'H' + n + 'L' + (w * .03) + ' ' + (h - n) + 'V' + n + 'Z"/>' + cut;
          }

          if (S.p.type === 'osima') {
              const fy = h * .27, gx = w * .83;
              return '<rect class="io-artfill" width="' + w + '" height="' + h + '" rx="1" style="fill:#e9edf2"/>' +
                  '<rect width="' + gx + '" height="' + fy + '" fill="#1d2f56"/>' +
                  '<rect x="' + (w * .28) + '" y="' + (fy * .3) + '" width="' + (w * .11) + '" height="' + (fy * .42) + '" rx="2" fill="#fff"/>' +
                  '<rect x="' + (w * .52) + '" y="' + (fy * .16) + '" width="' + (w * .23) + '" height="' + (fy * .66) + '" rx="1" fill="#fff" opacity=".92"/>' +
                  '<text class="io-lbl" x="' + (w * .05) + '" y="' + (fy * .55) + '" fill="#fff">OSIMA MS · PREMIUM</text>' +
                  '<path d="M0 ' + fy + 'H' + (w * .27) + 'L' + (w * .11) + ' ' + h + 'H0Z" fill="#1d2f56"/>' +
                  '<path d="M' + (w * .27) + ' ' + fy + 'H' + gx + 'V' + h + 'H' + (w * .11) + 'Z" fill="#dde3ea"/>' +
                  '<rect x="' + (w * .40) + '" y="' + (h * .42) + '" width="' + (w * .11) + '" height="' + (h * .17) + '" rx="2" fill="#fff"/>' +
                  '<text x="' + (w * .22) + '" y="' + (h * .72) + '" style="font:800 ' + (f * .155) + 'px Inter,sans-serif;fill:#1d2f56">OSIMA</text>' +
                  '<text x="' + (w * .23) + '" y="' + (h * .83) + '" style="font:700 ' + (f * .08) + 'px Inter,sans-serif;fill:#1d2f56;letter-spacing:.08em">PREMIUM</text>' +
                  '<text x="' + (w * .22) + '" y="' + (h * .4) + '" style="font:800 ' + (f * .07) + 'px Inter,sans-serif;fill:#e2338c">MS</text>' +
                  '<ellipse cx="' + (w * .67) + '" cy="' + (h * .88) + '" rx="' + (f * .06) + '" ry="' + (f * .04) + '" fill="#d8342b"/>' +
                  '<text x="' + (w * .78) + '" y="' + (h * .62) + '" transform="rotate(-90 ' + (w * .78) + ' ' + (h * .62) + ')" style="font:700 ' + (f * .055) + 'px Inter,sans-serif;fill:#1d2f56">Japanese Technology</text>' +
                  // Hand hole (struktur: 2-Piece Wrap + Hand Hole) -- lubang jinjing.
                  '<rect class="io-die" x="' + (w * .44) + '" y="' + (h * .12) + '" width="' + (w * .12) + '" height="' + (h * .05) + '" rx="' + (h * .025) + '"/>' +
                  '<path class="io-fold" d="M0 ' + fy + 'H' + w + 'M' + gx + ' 0V' + h + 'M' + (w * .27) + ' ' + fy + 'V' + h + '"/>' +
                  // Glue flap panel B di sisi kanan (lebar gx..w) = area lem.
                  '<path class="io-die" d="M' + gx + ' ' + (h * .018) + 'V' + (h * .982) + '"/>' +
                  '<rect class="io-die" x="' + (w * .012) + '" y="' + (h * .018) + '" width="' + (w * .976) + '" height="' + (h * .964) + '"/>' + cut;
          }

          const x1 = w * .16, x2 = w * .62, x3 = w * .82, y1 = h * .62;
          return '<path class="io-artfill" d="M' + x1 + ' 0H' + w + 'V' + h + 'H' + x1 + 'L0 ' + (h * .78) + 'V' + (h * .22) + 'Z" style="fill:#efe7d2"/>' +
              '<rect x="' + x1 + '" y="0" width="' + (x2 - x1) + '" height="' + y1 + '" fill="#f4eedd"/>' +
              '<path d="M' + x1 + ' ' + (h * .06) + 'q' + (w * .18) + ' ' + (h * .2) + ' ' + (w * .12) + ' ' + (h * .5) + 'h' + (w * .07) + 'q' + (w * .05) + '-' + (h * .34) + '-' + (w * .09) + '-' + (h * .5) + 'z" fill="#8b6535" opacity=".85"/>' +
              '<rect x="' + (w * .3) + '" y="' + (h * .12) + '" width="' + (w * .2) + '" height="' + (h * .22) + '" fill="#c99a3d" stroke="#7d5c1c" stroke-width=".7"/>' +
              '<text class="io-lbl" x="' + (w * .4) + '" y="' + (h * .24) + '" text-anchor="middle" fill="#2a1c05">AL SAMSI</text>' +
              '<text class="io-lbl" x="' + (w * .4) + '" y="' + (h * .42) + '" text-anchor="middle" fill="#6b5522">Sarung Tenun · Signature</text>' +
              '<g fill="#fff" stroke="#6f6247" stroke-width=".6">' + [0, 1, 2, 3].map(i =>
                  '<rect x="' + (x2 + (x3 - x2) * .3) + '" y="' + (h * (.07 + i * .13)) + '" width="' + ((x3 - x2) * .4) + '" height="' + (h * .09) + '" rx="2"/>').join('') + '</g>' +
              '<rect x="' + x3 + '" y="0" width="' + (w - x3) + '" height="' + h + '" fill="#e6dcc2"/>' +
              '<g fill="#9a8348">' + [0, 1, 2, 3, 4, 5, 6, 7].map(i =>
                  '<rect x="' + (x3 + (w - x3) * .2) + '" y="' + (h * (.04 + i * .12)) + '" width="' + ((w - x3) * .6) + '" height="' + (h * .02) + '"/>').join('') + '</g>' +
              '<rect x="' + (w * .21) + '" y="' + (h * .7) + '" width="' + (w * .16) + '" height="' + (h * .22) + '" fill="none" stroke="#4a4130" stroke-width=".7"/>' +
              '<path d="M' + (w * .21) + ' ' + (h * .775) + 'h' + (w * .16) + 'M' + (w * .21) + ' ' + (h * .85) + 'h' + (w * .16) + '" stroke="#4a4130" stroke-width=".5"/>' +
              '<text class="io-lbl" x="' + (w * .29) + '" y="' + (h * .752) + '" text-anchor="middle" fill="#3b3325">CODE / DESIGN</text>' +
              '<text class="io-lbl" x="' + (w * .29) + '" y="' + (h * .827) + '" text-anchor="middle" fill="#3b3325">CONTENT: 10 PCS</text>' +
              '<rect x="' + (w * .45) + '" y="' + (h * .72) + '" width="' + (w * .13) + '" height="' + (h * .16) + '" fill="#c99a3d" stroke="#7d5c1c" stroke-width=".6"/>' +
              '<text class="io-lbl" x="' + (w * .515) + '" y="' + (h * .81) + '" text-anchor="middle" fill="#2a1c05">AL SAMSI</text>' +
              '<path class="io-fold" d="M' + x1 + ' 0V' + h + 'M' + x2 + ' 0V' + h + 'M' + x3 + ' 0V' + h + 'M' + x1 + ' ' + y1 + 'H' + w + '"/>' +
              // Lock bottom: lidah pengunci dasar dipotong di bawah garis y1.
              '<path class="io-die" d="M' + x1 + ' ' + y1 + 'H' + w + 'M' + ((x1 + x2) / 2) + ' ' + y1 + 'V' + (h * .98) + 'M' + ((x2 + x3) / 2) + ' ' + y1 + 'V' + (h * .98) + '"/>' +
              '<path class="io-die" d="M' + x1 + ' ' + (h * .02) + 'H' + (w * .985) + 'V' + (h * .98) + 'H' + x1 + 'L' + (w * .015) + ' ' + (h * .77) + 'V' + (h * .23) + 'Z"/>' + cut;
      }

      function arts(sx, sy, sw, sh) {
          const g = $('arts');
          g.innerHTML = '';
          const isOut = o => {
              const b = obox(o);
              return b.x < sx - .6 || b.y < sy - .6 || b.x + b.w > sx + sw + .6 || b.y + b.h > sy + sh + .6;
          };
          S.objs.forEach(o => {
              const out = isOut(o), el = document.createElementNS('http://www.w3.org/2000/svg', 'g'),
                  cx = o.x + o.w / 2, cy = o.y + o.h / 2;
              el.dataset.id = o.id;
              el.setAttribute('class', 'io-art ' + (out ? 'io-out ' : '') + (S.selected === o.id ? 'selected' : ''));
              el.setAttribute('transform', 'translate(' + cx + ' ' + cy + ') rotate(' + o.r + ') scale(' + o.fx + ' ' + o.fy + ') translate(' + (-o.w / 2) + ' ' + (-o.h / 2) + ')');
              el.innerHTML = shape(o);
              el.addEventListener('pointerdown', start);
              g.appendChild(el);
          });
          $('warning').innerHTML = '⚠ Artwork melewati batas canvas / print sheet';
          $('warning').classList.toggle('show', S.objs.some(isOut));
      }

      function start(e) {
          e.preventDefault(); e.stopPropagation();
          const o = S.objs.find(q => q.id === e.currentTarget.dataset.id);
          if (!o) return;
          S.selected = o.id;
          const p = point(e);
          S.drag = { id: o.id, sx: p.x, sy: p.y, ox: o.x, oy: o.y };
          app.classList.add('dragging');
          document.addEventListener('pointermove', move);
          document.addEventListener('pointerup', end);
          document.addEventListener('pointercancel', end);
          render();
      }
      function point(e) {
          const r = $('svg').getBoundingClientRect();
          return { x: (e.clientX - r.left) * VB.w / r.width, y: (e.clientY - r.top) * VB.h / r.height };
      }
      function move(e) {
          if (!S.drag) return;
          e.preventDefault();
          const o = S.objs.find(q => q.id === S.drag.id), p = point(e);
          o.x = S.drag.ox + p.x - S.drag.sx; o.y = S.drag.oy + p.y - S.drag.sy;
          S.touched = true; S.manualEdit = true;
          render();
      }
      function end() {
          S.drag = null;
          app.classList.remove('dragging');
          document.removeEventListener('pointermove', move);
          document.removeEventListener('pointerup', end);
          document.removeEventListener('pointercancel', end);
          if (S.objs.length) noteShortfall(S.objs.filter(inCanvas).length);
          render(); calculator(); checkManualOverlap();
      }
      function transform(k, v) {
          const o = S.objs.find(x => x.id === S.selected);
          if (!o) { msg('Pilih artwork object pada canvas terlebih dahulu.', 'info'); return; }
          if (k === 'r') o.r = (o.r + v) % 360;
          else if (v === 'x') o.fx *= -1;
          else o.fy *= -1;
          S.touched = true; S.manualEdit = true;
          render(); calculator(); checkManualOverlap();
      }

      function cut(pl) {
          const n = Math.floor(pl[1] / S.L) * Math.floor(pl[2] / S.W),
              r = Math.floor(pl[1] / S.W) * Math.floor(pl[2] / S.L), rot = r > n;
          return { n: Math.max(n, r), rot, util: Math.max(n, r) ? Math.max(n, r) * S.L * S.W / (pl[1] * pl[2]) : 0 };
      }

      function impo(c, up) {
          const p = S.p, A = p.l * p.w, ac = S.L * S.W, ap = S.pl[1] * S.pl[2],
              cu = ac ? up * A / ac * 100 : 0, pu = c.util * 100, all = cu * pu / 100;
          $('yCanvas').textContent = cu.toFixed(1) + '%';
          $('yCanvasW').textContent = (100 - cu).toFixed(1) + '%';
          $('yPlano').textContent = pu.toFixed(1) + '%';
          $('yAll').textContent = all.toFixed(1) + '%';
          $('barFill').style.width = Math.max(0, Math.min(100, all)) + '%';
          $('barTxt').textContent = 'Material terpakai ' + all.toFixed(1) + '% · terbuang ' + (100 - all).toFixed(1) + '%';

          const sc = S.sc0 || Math.min(usableVB()[0] / S.L, usableVB()[1] / S.W), sx = S.sx0, sy = S.sy0, sw = S.L * sc, sh = S.W * sc,
              b = S.objs.map(obox), rot = S.objs.filter(o => o.r % 360 !== 0).length,
              mir = S.objs.filter(o => o.fx < 0 || o.fy < 0).length,
              out = b.filter(v => v.x < sx - .6 || v.y < sy - .6 || v.x + v.w > sx + sw + .6 || v.y + v.h > sy + sh + .6).length,
              bl = b.length ? ((Math.max(...b.map(v => v.x + v.w)) - Math.min(...b.map(v => v.x))) / sc).toFixed(1) : '—',
              bw = b.length ? ((Math.max(...b.map(v => v.y + v.h)) - Math.min(...b.map(v => v.y))) / sc).toFixed(1) : '—',
              g = best();

          // Tabrakan garis potong lebih gawat daripada object keluar canvas:
          // angka yield di panel ini TETAP kelihatan bagus (utilisasi malah
          // bisa >100%, dihitung dari kotak pembatas bukan bentuk asli), jadi
          // tanpa peringatan ini susunan yang tidak bisa dicetak terbaca
          // seolah paling efisien.
          const bentrok = S.overlapPx > 0;
          const kurang = S.upShort;
          $('impoMode').className = 'io-notice ' + (bentrok || out || kurang ? 'bad' : S.touched ? 'ok' : 'info');
          $('impoMode').innerHTML = (S.touched
              ? '<b>' + (S.autoNestInfo ? 'AUTO NESTING — ' + S.autoNestInfo : 'CUSTOM IMPOSITION — nesting manual') + '</b><br>Susunan: ' + rot + ' object rotate, ' + mir + ' mirror' + (out ? ' · <b>' + out + ' di luar canvas (tidak terhitung sah)</b>' : '') + '.'
              : '<b>AUTO GRID IMPOSITION</b><br>Susunan otomatis ' + g.c + ' kolom × ' + g.r + ' baris' + (g.rot ? ' (blank dirotate 90°)' : '')
                + ' · mode ' + (S.auto ? 'Auto Up' : 'Manual Up') + (out ? ' · <b>' + out + ' object di luar canvas — geser manual</b>' : '') + '.')
              + (bentrok ? '<br><b>⚠ GARIS POTONG BERTABRAKAN</b> — ' + S.overlapPx + ' mm² blank saling makan, susunan ini tidak bisa dicetak. Angka yield di bawah ikut tidak sah.' : '')
              + (kurang
                  ? (kurang.target > kurang.maxMesin
                      ? '<br><b>⚠ TARGET ' + kurang.target + ' UP TIDAK MUAT DI MESIN</b> — blank ' + S.p.l + ' × ' + S.p.w
                        + ' cm sebanyak itu melebihi meja ' + bedOwner() + '. Canvas terbesar yang diizinkan ('
                        + limTxt() + ') hanya memuat ' + kurang.maxMesin + ' up.'
                      : '<br><b>⚠ TARGET ' + kurang.target + ' UP BELUM MUAT DI CANVAS INI</b> — canvas ' + S.L + ' × ' + S.W
                        + ' cm baru memuat ' + kurang.dapat + ' up, sisanya ditempatkan DI LUAR canvas (tandai merah) untuk digeser manual. '
                        + 'Bisa juga diputar/di-mirror, perbesar canvas (' + (unlimited() ? 'tanpa batas meja' : 'meja ' + bedOwner() + ' masih mengizinkan sampai ' + limTxt())
                        + '), atau tekan ✦ Suggestion.')
                  : '');

          $('impoDetail').innerHTML = '<table>' +
              '<tr><td>Object di canvas</td><td>' + up + ' up</td></tr>' +
              '<tr><td>Bounding box susunan</td><td>' + bl + ' × ' + bw + ' cm</td></tr>' +
              '<tr><td>Area blank total</td><td>' + (up * A).toFixed(0) + ' cm²</td></tr>' +
              '<tr><td>Area canvas</td><td>' + ac.toFixed(0) + ' cm²</td></tr>' +
              '<tr><td>Sisa canvas kosong</td><td>' + (ac - up * A).toFixed(0) + ' cm² (' + (100 - cu).toFixed(1) + '%)</td></tr>' +
              '<tr><td>Canvas / plano</td><td>' + c.n + ' lembar</td></tr>' +
              '<tr><td>Trim plano terbuang</td><td>' + (ap - c.n * ac).toFixed(0) + ' cm²</td></tr>' +
              '<tr><td>Hasil / plano</td><td>' + (c.n * up) + ' pcs</td></tr></table>';

          const rows = planos.map(q => ({ q, c: cut(q) })).filter(x => x.c.n).sort((a, b2) => b2.c.util - a.c.util || b2.c.n - a.c.n);
          $('planoTable').innerHTML = rows.length
              ? '<table class="rank"><tr><th>Plano</th><th>Cvs</th><th>Yield</th><th>Overall</th><th>Pcs</th></tr>' +
                rows.map(r => '<tr class="' + (r.q[0] === S.pl[0] ? 'best' : '') + '"><td>' + r.q[0].replace(' cm', '') + '</td><td>' + r.c.n + '</td><td>' + (r.c.util * 100).toFixed(1) + '%</td><td>' + (cu * r.c.util).toFixed(1) + '%</td><td>' + (r.c.n * up) + '</td></tr>').join('') +
                '</table>'
              : '<table><tr><td>Tidak ada plano standar yang muat canvas ' + S.L + ' × ' + S.W + ' cm</td></tr></table>';
      }

      function calculator() {
          if (!ok()) return;
          const c = cut(S.pl), up = upValid(), val = +$('converterInput').value || 0;
          if (!c.n) {
              // Angka & gambar lama jangan dibiarkan tampil -- dulu preview
              // masih menunjukkan pembagian canvas SEBELUMNYA.
              const bi = bestPlanoIndex();
              $('planoNote').className = 'io-notice bad';
              $('planoNote').innerHTML = 'Plano ' + esc(S.pl[0]) + ' tidak muat canvas ' + S.L + ' × ' + S.W + ' cm.'
                  + (bi >= 0 ? ' Pilih <b>' + planos[bi][0] + '</b> (' + (cut(planos[bi]).util * 100).toFixed(1) + '% sheet yield) atau tekan ✦ Suggestion.' : '');
              $('mUp').textContent = up; $('mYield').textContent = 0; $('mPerPlano').textContent = 0; $('mWaste').textContent = '—';
              $('resultValue').textContent = '—';
              drawPlano(S.pl, c);
              impo(c, up);
              // BOM / Ink / BOP tidak bergantung pada plano yang muat (specCtx memakai sheetsPerPlano 1):
              // tanpa ini ketiga bagian itu macet di "Menghitung dari data Inquiry…" (user 2026-10-01).
              specSoon();
              return;
          }
          const bi = bestPlanoIndex(), bp = { p: planos[bi], c: cut(planos[bi]) };
          $('planoNote').className = 'io-notice ok';
          $('planoNote').innerHTML = 'Recommended: <b>' + bp.p[0] + '</b> gives ' + bp.c.n + ' canvas(es) / plano (' +
              (bp.c.util * 100).toFixed(1) + '% sheet yield, bahan per pcs paling hemat). ' + (S.pl[0] === bp.p[0] ? 'Selected.' : 'Manual plano selection active.');
          const perPlano = c.n * up, waste = (100 - c.util * 100).toFixed(1) + '%';
          $('mUp').textContent = up; $('mYield').textContent = c.n;
          $('mPerPlano').textContent = perPlano; $('mWaste').textContent = waste;
          if (S.conv === 'pcs') {
              const print = Math.ceil(val / up), need = Math.ceil(print / c.n);
              $('resultCaption').textContent = 'Plano required';
              $('resultValue').textContent = need;
              $('resultUnit').textContent = 'sheets';
          } else {
              $('resultCaption').textContent = 'Production capacity';
              $('resultValue').textContent = val * perPlano;
              $('resultUnit').textContent = 'pcs';
          }
          drawPlano(S.pl, c);
          impo(c, up);
          specSoon();
      }

      // Pembagian 1 plano: tiap canvas bernomor + ukurannya, sisa trim
      // diarsir, ukuran plano di tepi. Label ditaruh DI LUAR kotak plano
      // (dulu tertimpa kotaknya sehingga tidak terbaca).
      function drawPlano(p, c) {
          const VW = 280, VH = 190, top = 14, left = 16, areaW = VW - left - 8, areaH = 140;
          const sc = Math.min(areaW / p[1], areaH / p[2]), W = p[1] * sc, H = p[2] * sc,
              x = left + (areaW - W) / 2, y = top + (areaH - H) / 2,
              cols = c.rot ? Math.floor(p[1] / S.W) : Math.floor(p[1] / S.L),
              rows = c.rot ? Math.floor(p[2] / S.L) : Math.floor(p[2] / S.W),
              cl = c.rot ? S.W : S.L, cwid = c.rot ? S.L : S.W,
              cw = cl * sc, ch = cwid * sc;
          const t = (tx, ty, str, extra) => '<text x="' + tx + '" y="' + ty + '" text-anchor="middle" ' + (extra || '') + '>' + str + '</text>';
          let s = '';
          for (let r = 0; r < rows; r++) for (let col = 0; col < cols; col++) {
              const cx = x + col * cw, cy = y + r * ch;
              s += '<rect x="' + cx + '" y="' + cy + '" width="' + cw + '" height="' + ch + '" fill="#73ddff" fill-opacity=".8" stroke="#0e5776"/>'
                  + t(cx + cw / 2, cy + ch / 2 - 4, 'CANVAS ' + (r * cols + col + 1), 'font-size="9" font-weight="700" fill="#063049"')
                  + t(cx + cw / 2, cy + ch / 2 + 8, fmtAngka(cl) + ' × ' + fmtAngka(cwid), 'font-size="8" fill="#063049"');
          }
          if (!c.n) s = t(x + W / 2, y + H / 2, 'Canvas ' + fmtAngka(S.L) + ' × ' + fmtAngka(S.W) + ' cm tidak muat', 'font-size="10" font-weight="700" fill="#c0392b"');
          const trim = p[1] * p[2] - c.n * S.L * S.W;
          $('cut').innerHTML = '<svg viewBox="0 0 ' + VW + ' ' + VH + '" width="100%" style="max-width:' + VW + 'px">'
              + '<defs><pattern id="io-trimHatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
              + '<rect width="6" height="6" fill="#dfe9ee"/><line x1="0" y1="0" x2="0" y2="6" stroke="#b7c7d1" stroke-width="2"/></pattern></defs>'
              + '<rect x="' + x + '" y="' + y + '" width="' + W + '" height="' + H + '" fill="url(#io-trimHatch)" stroke="#8fa8b6"/>' + s
              + t(x + W / 2, y - 4, fmtAngka(p[1]) + ' cm', 'font-size="9" fill="#abc6d5"')
              + '<text x="' + (x - 4) + '" y="' + (y + H / 2) + '" text-anchor="middle" font-size="9" fill="#abc6d5" transform="rotate(-90 ' + (x - 4) + ' ' + (y + H / 2) + ')">' + fmtAngka(p[2]) + ' cm</text>'
              + t(VW / 2, VH - 22, p[0] + ' → ' + c.n + ' canvas (' + cols + ' × ' + rows + (c.rot ? ', canvas diputar' : '') + ')', 'font-size="10" font-weight="700" fill="#dbeaf3"')
              + t(VW / 2, VH - 8, 'Sheet yield ' + (c.util * 100).toFixed(1) + '% · trim terbuang ' + Math.max(0, trim).toFixed(0) + ' cm² (arsir)', 'font-size="9" fill="#abc6d5"')
              + '</svg>';
      }

      init();
    }
  }
  Devlib.define('ProdevWorkbench', ProdevWorkbench);
})(window.Devlib);
