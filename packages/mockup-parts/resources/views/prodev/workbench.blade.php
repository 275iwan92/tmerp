{{-- ProDev workbench: markup salinan PERSIS tmerp (semua id io-* dipertahankan; perilaku di JS ProdevWorkbench). --}}
@once
    <link rel="stylesheet" href="{{ \MockupParts\MockupPartsServiceProvider::asset('prodev.css') }}">
    {{-- Kulit terang (user 2026-10-01): hanya menimpa warna prodev.css lewat token DevLib. --}}
    <link rel="stylesheet" href="{{ \MockupParts\MockupPartsServiceProvider::asset('prodev-light.css') }}">
    <script src="{{ \MockupParts\MockupPartsServiceProvider::asset('tmdieline.js') }}"></script>
@endonce
<div {{ $attributes->class('io-app') }} {!! $dl() !!} data-mp-prodev data-config="{{ json_encode($config()) }}">
    <input type="file" id="io-artFileReal" accept="image/jpeg,image/png,application/pdf,.ai,.eps" hidden>
    <div class="io-shell">
        <div class="io-top">
            <div>
                <h1>Imposition Plano Optimizer</h1>
                <p class="io-sub">Pro-Dev — nesting manual &amp; optimasi plano per job cetak (artwork vector + garis dieline struktural)</p>
            </div>
            <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                <!-- Label "Back" dikunci berbahasa Inggris di kedua mode bahasa
                (FORM_UI_STANDARD.md §1). href diisi JS dari window.__PRODEV.backUrl
                karena blok ini ada di dalam blok verbatim -- route() Blade tidak jalan. -->
                <a class="io-btn" id="io-back" href="#">← Back</a>
                <!-- 3 tombol aksi di top bar (permintaan user 2026-09-18):
                Save All, Confirm, dan pratinjau/cetak Master Card. -->
                <button class="io-btn" id="io-save">💾 Save All</button>
                <button class="io-btn green" id="io-confirm">✓ Confirm</button>
                <button class="io-btn" id="io-printMc">🖨 Preview &amp; Print Master Card</button>
                <span class="io-status" id="io-status"></span>
                <div class="io-badge" id="io-jobcount">LIVE PACKAGING JOBS</div>
            </div>
        </div>

        <div class="io-layout">
            <!-- Accordion horizontal (permintaan user 2026-09-17, dari mockup
            draft): SATU bagian terbuka lebar, sisanya menyempit jadi strip
            judul vertikal. Hemat ruang tanpa panel yang meluber ke samping,
            dan area gambar tetap di bawah selebar penuh. Semua id elemen
            dipertahankan, jadi logika JS di bawah tidak berubah. -->
            <div class="io-acc" id="io-acc">
                <section class="io-sec" data-sec="impo">
                    <button type="button" class="io-sec-head"><span>Imposition &amp; Nesting</span></button>
                    <div class="io-sec-body">
                        <div class="io-group">
                            <h2>Job</h2>
                            <!-- Job sudah pasti sejak dipilih di grid (user 2026-09-16),
                            jadi ini identitas, BUKAN dropdown. -->
                            <div id="io-jobFixed" class="io-jobfix"></div>
                            <select id="io-product" style="display:none"></select>
                            <div id="io-metrics" class="io-metric-grid"></div>
                        </div>
                        <div class="io-group">
                            <h2>1. Jumlah Up</h2>
                            <div class="io-auto-box">
                                <div class="io-toggle-row">
                                    <div>
                                        <b>Auto Up</b>
                                        <div class="io-note" id="io-modeText">Aktif: gunakan up maksimum yang memungkinkan</div>
                                    </div>
                                    <label class="io-toggle"><input id="io-autoUp" type="checkbox" checked><span class="io-slider"></span></label>
                                </div>
                                <!-- Saat Auto Up, jumlah up itu HASIL perhitungan, bukan angka
                                yang diketik. Kotak isian baru muncul di mode Manual.
                                Blok ini ada di dalam blok verbatim: komentar Blade tidak diproses
                                dan akan bocor jadi teks -- WAJIB komentar HTML. -->
                                <div id="io-autoResult" class="io-auto-result">
                                    <span>Hasil optimasi</span><b id="io-autoResultValue">—</b>
                                    <div class="io-note" id="io-autoResultNote">Susunan dihitung dari ukuran canvas &amp; bentuk dieline.</div>
                                </div>
                                <div id="io-manualGroup" style="display:none">
                                    <label>Target up (manual)</label>
                                    <input id="io-manualUp" type="number" min="1">
                                    <p class="io-note">Canvas akan dicarikan ukuran terkecil yang memuat jumlah ini.</p>
                                </div>
                            </div>
                            <h2 style="margin-top:14px">2. Gap</h2>
                            <label>Jarak antar artwork / dieline (cm)</label>
                            <input id="io-gap" type="number" min="0" step=".05" value=".3">
                        </div>
                        <div class="io-group">
                            <h2>3. Lembar Cetak / Canvas</h2>
                            <div class="io-row">
                                <div><label>Length (cm)</label><input id="io-sl" type="number" min="1" step=".1"></div>
                                <div><label>Width (cm)</label><input id="io-sw" type="number" min="1" step=".1"></div>
                            </div>
                            <button id="io-suggest" class="io-btn green" style="width:100%;margin-top:9px" title="Mengubah ukuran canvas DAN plano">✦ Suggestion</button>
                            <p id="io-suggestNote" class="io-note"></p>
                            <label>Grain direction</label>
                            <select id="io-grain">
                                <option value="L">Along dieline length</option>
                                <option value="W">Along dieline width</option>
                                <option value="none">No grain constraint</option>
                            </select>
                        </div>
                        <div class="io-group">
                            <h2>Data Kalkulasi</h2>
                            <div class="io-mini-grid" style="margin-top:0">
                                <div class="io-mini"><span>Canvas utilization</span><b id="io-yCanvas">—</b></div>
                                <div class="io-mini"><span>Canvas waste</span><b id="io-yCanvasW">—</b></div>
                                <div class="io-mini"><span>Plano sheet yield</span><b id="io-yPlano">—</b></div>
                                <div class="io-mini"><span>Overall efficiency</span><b id="io-yAll">—</b></div>
                            </div>
                            <div class="io-bar"><i id="io-barFill"></i><span id="io-barTxt"></span></div>
                            <div id="io-impoDetail" class="io-spec"></div>
                        </div>
                        <div class="io-group">
                            <h2>Spesifikasi &amp; Artwork</h2>
                            <div id="io-spec" class="io-spec" style="margin-top:0"></div>
                            <label>Artwork asset</label>
                            <label class="io-upload">Upload artwork<input id="io-artFile" type="file" accept="image/*,.pdf,.ai,.svg"></label>
                            <div id="io-artName" class="io-file">Artwork bawaan job aktif</div>
                            <label>Separate dieline</label>
                            <label class="io-upload">Upload dieline<input id="io-dieFile" type="file" accept="image/*,.pdf,.ai,.svg,.dxf"></label>
                            <div id="io-dieName" class="io-file">Dieline struktural bawaan job aktif</div>
                        </div>
                    </div>
                </section>

                <section class="io-sec" data-sec="plano">
                    <button type="button" class="io-sec-head"><span>Plano Configuration</span></button>
                    <div class="io-sec-body">
                        <div class="io-group">
                            <h2>Mesin</h2>
                            <label>Printing machine</label>
                            <select id="io-machine"></select>
                            <div id="io-machineInfo" class="io-notice info"></div>
                            <label>Mesin plong / ponds</label>
                            <select id="io-diecut"></select>
                            <div id="io-diecutInfo" class="io-notice info"></div>
                        </div>
                        <div class="io-group">
                            <h2>Plano Optimization</h2>
                            <label>Market plano size</label>
                            <select id="io-plano"></select>
                            <button id="io-calcPlano" class="io-btn green" style="width:100%;margin-top:8px" title="Pilih plano paling hemat untuk ukuran canvas sekarang">⟳ Calculate Plano</button>
                            <div class="io-row">
                                <div><label>Custom L</label><input id="io-cpl" type="number" step=".1" placeholder="cm"></div>
                                <div><label>Custom W</label><input id="io-cpw" type="number" step=".1" placeholder="cm"></div>
                            </div>
                            <button id="io-custom" class="io-btn" style="width:100%;margin-top:8px">Use custom plano</button>
                            <div id="io-planoNote" class="io-notice info"></div>
                            <div id="io-cut" class="io-cut"></div>
                        </div>
                        <div class="io-group">
                            <h2>Ranking Plano</h2>
                            <div id="io-planoTable" class="io-spec" style="margin-top:0"></div>
                        </div>
                        <div class="io-group">
                            <h2>PCS ↔ PLANO Calculator</h2>
                            <div class="io-mode-switch">
                                <button id="io-pcsMode" class="active">PCS → PLANO</button>
                                <button id="io-planoMode">PLANO → PCS</button>
                            </div>
                            <div class="io-compact">
                                <b id="io-inputLabel">Qty order (pcs)</b>
                                <label for="io-converterInput" class="io-note" id="io-inputHint">Masukkan jumlah produk yang harus diproduksi</label>
                                <input id="io-converterInput" type="number" min="0" value="100">
                                <div class="io-result">
                                    <div class="caption" id="io-resultCaption">Plano required</div>
                                    <b id="io-resultValue">—</b> <small id="io-resultUnit">sheets</small>
                                </div>
                                <div class="io-mini-grid">
                                    <div class="io-mini"><span>Active up</span><b id="io-mUp">—</b></div>
                                    <div class="io-mini"><span>Yield / plano</span><b id="io-mYield">—</b></div>
                                    <div class="io-mini"><span>Products / plano</span><b id="io-mPerPlano">—</b></div>
                                    <div class="io-mini"><span>Waste / plano</span><b id="io-mWaste">—</b></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="io-sec" data-sec="bom">
                    <button type="button" class="io-sec-head"><span>Bill of Material</span><em class="io-sec-count" id="io-cnt-bom"></em></button>
                    <div class="io-sec-body io-sec-body--single"><div id="io-bom"></div></div>
                </section>

                <section class="io-sec" data-sec="ink">
                    <button type="button" class="io-sec-head"><span>Ink Configuration</span><em class="io-sec-count" id="io-cnt-ink"></em></button>
                    <div class="io-sec-body io-sec-body--single"><div id="io-ink"></div></div>
                </section>

                <section class="io-sec" data-sec="bop">
                    <button type="button" class="io-sec-head"><span>Bill of Process</span><em class="io-sec-count" id="io-cnt-bop"></em></button>
                    <div class="io-sec-body io-sec-body--single"><div id="io-bop"></div></div>
                </section>
            </div>

            <main class="io-canvas-card">
                <div class="io-toolbar">
                    <button class="io-btn" id="io-zout">− Zoom</button>
                    <span class="io-pill" id="io-zoom">100%</span>
                    <button class="io-btn" id="io-zin">+ Zoom</button>
                    <button class="io-btn" data-io-r="90">↻ +90°</button>
                    <button class="io-btn" data-io-r="-90">↺ −90°</button>
                    <button class="io-btn" data-io-r="180">↻ 180°</button>
                    <button class="io-btn" data-io-f="x">↔ Mirror X</button>
                    <button class="io-btn" data-io-f="y">↕ Mirror Y</button>
                    <button class="io-btn warn" id="io-resetLayout">Reset layout</button>
                    <button class="io-btn" id="io-autoNest">✦ Auto Nesting</button>
                </div>
                <!-- Status susunan + PERINGATAN (tabrakan garis potong, target up
                tidak muat) sengaja ditaruh di sini, bukan di dalam accordion:
                peringatan tidak boleh ikut tersembunyi saat bagiannya ditutup. -->
                <div id="io-impoMode" class="io-notice info" style="margin:0 0 9px"></div>
                <div class="io-stage">
                    <div class="io-rulerx">CANVAS / PRINT SHEET RULER — cm</div>
                    <div class="io-rulery"></div>
                    <div id="io-sheetLabel" class="io-sheetlabel"></div>
                    <div id="io-warning" class="io-warn">⚠ Artwork melewati batas canvas / print sheet</div>
                    <div class="io-viewport" id="io-viewport">
                        <div id="io-wrap" class="io-wrap">
                            <svg id="io-svg" width="650" height="500" viewBox="0 0 650 500">
                                <defs>
                                    <marker id="io-arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto">
                                        <path d="M0 0L6 3 0 6Z" fill="#9c6b12"/>
                                    </marker>
                                </defs>
                                <rect id="io-sheet" class="io-print"/>
                                <rect id="io-safeRect" class="io-safe"/>
                                <g id="io-grainG"></g>
                                <g id="io-arts"></g>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="io-legend">
                    <span><i class="io-dot" style="background:#c7f1ff;border:1px solid #056c90"></i>Artwork surface</span>
                    <span><i class="io-dot" style="background:#fc2458"></i>Die-cut / trim line</span>
                    <span><i class="io-dot" style="background:#27a560"></i>Fold / crease line</span>
                    <span><i class="io-dot" style="background:#9c6b12"></i>Grain</span>
                    <span><i class="io-dot" style="background:#7732e2"></i>Selected object</span>
                </div>
                <div class="io-instructions">
                    <b>Manual nesting:</b> pilih lalu drag artwork/dieline satu per satu. Gunakan Rotate dan Mirror untuk
                    mensimulasikan susunan berlawanan serta mengisi ruang kosong sesuai bentuk dieline. Setelah susunan
                    diatur, tombol <b>✦ Suggestion</b> tidak me-reset layout — susunan dirapatkan sesuai gap (antar-object
                    &amp; keliling), lalu ukuran canvas dan plano dipilih ulang. Tekan <b>Reset layout</b> bila ingin kembali ke grid otomatis.
                </div>
                <div id="io-valid" class="io-notice info">Set a valid canvas size.</div>
            </main>
        </div>
    </div>

    <!-- Pratinjau & cetak MASTER CARD (permintaan user 2026-09-18): pop-up
    besar, isinya seluruh informasi ProDev, dicetak A4 LANDSCAPE 1 halaman.
    Ditaruh DI LUAR .io-shell supaya saat mencetak cukup sembunyikan shell-nya
    (lihat @media print di style atas). Cap "NOT CONFIRMED" muncul selama
    layout belum di-Confirm. -->
    <div class="io-mc-modal" id="io-mcModal" role="dialog" aria-modal="true" aria-label="Master Card">
        <div class="io-mc-bar">
            <b>Preview Master Card</b>
            <span id="io-mcState" class="io-mc-state"></span>
            <span style="flex:1"></span>
            <button class="io-btn" id="io-mcPrint">🖨 Print (A4 landscape)</button>
            <button class="io-btn warn" id="io-mcClose">✕ Tutup</button>
        </div>
        <div class="io-mc-scroll">
            <div class="io-mc-sheet"><div class="io-mc-pad" id="io-mcSheet"></div></div>
        </div>
    </div>
</div>
