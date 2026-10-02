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
        {{-- Panel atas = panel form standar DevLib (user 2026-10-01): judul + kode + status, lalu Email, Print, Confirm, Save, Back di ujung.
             Print / Email punya dua dokumen: Master Card dan ACC Digital. id io-* dipertahankan (dipakai workbench.js). --}}
        @php
            $cfg = $config();   // properti bernama $data tidak diteruskan Laravel ke view komponen
            $accUrl = $cfg['accUrl'] ?? null;
            $noAcc = t('This part has no product yet, so there is no ACC Digital document.');
            $docs = fn (string $verb) => [
                ['label' => 'Master Card', 'icon' => 'fa-id-card', 'sub' => t('Layout, BOM, ink and BOP on one A4 landscape sheet'), 'act' => $verb.'-mc'],
                ['label' => 'ACC Digital', 'icon' => 'fa-stamp', 'sub' => t('Digital proof / customer ACC sheet'), 'href' => $accUrl ? $accUrl.($verb === 'email' ? '&ds_do=email' : '') : null,
                    'disabled' => ! $accUrl, 'title' => $accUrl ? null : $noAcc],
            ];
        @endphp
        <div class="dl-form__panel">
            <div class="dl-form__heading"><div class="dl-form__title">{{ t('Imposition Plano Optimizer') }}</div><small id="io-jobcount"></small></div>
            <x-dl::badge label="Draft" tone="neutral" id="io-docState" />
            <span class="io-status" id="io-status"></span>
            <span class="dl-print__spacer"></span>
            <x-dl::menu-button preset="email" size="sm" :items="$docs('email')" />
            <x-dl::menu-button preset="print" size="sm" :items="$docs('print')" />
            <x-dl::button preset="confirm" size="sm" id="io-confirm" />
            <x-dl::button preset="save" size="sm" type="button" id="io-save" />
            <span class="dl-form__sep"></span>
            <x-dl::button variant="secondary" size="sm" icon="back" label="Back" href="#" id="io-back" />
        </div>

        <div class="io-layout">
            <!-- Accordion horizontal (permintaan user 2026-09-17, dari mockup
            draft): SATU bagian terbuka lebar, sisanya menyempit jadi strip
            judul vertikal. Hemat ruang tanpa panel yang meluber ke samping,
            dan area gambar tetap di bawah selebar penuh. Semua id elemen
            dipertahankan, jadi logika JS di bawah tidak berubah. -->
            <div class="io-acc" id="io-acc">
                <section class="io-sec" data-sec="impo">
                    <button type="button" class="io-sec-head"><span>{{ t('Imposition & Nesting') }}</span></button>
                    <div class="io-sec-body">
                        <div class="io-group">
                            <h2>{{ t('Job') }}</h2>
                            <!-- Job sudah pasti sejak dipilih di grid (user 2026-09-16),
                            jadi ini identitas, BUKAN dropdown. -->
                            <div id="io-jobFixed" class="io-jobfix"></div>
                            <select id="io-product" style="display:none"></select>
                            <div id="io-metrics" class="io-metric-grid"></div>
                        </div>
                        <div class="io-group">
                            <h2>{{ t('1. Number of Ups') }}</h2>
                            <div class="io-auto-box">
                                <div class="io-toggle-row">
                                    <div>
                                        <b>{{ t('Auto Up') }}</b>
                                        <div class="io-note" id="io-modeText">{{ t('Active: use the maximum number of ups that fits') }}</div>
                                    </div>
                                    <label class="io-toggle"><input id="io-autoUp" type="checkbox" checked><span class="io-slider"></span></label>
                                </div>
                                <!-- Saat Auto Up, jumlah up itu HASIL perhitungan, bukan angka
                                yang diketik. Kotak isian baru muncul di mode Manual.
                                Blok ini ada di dalam blok verbatim: komentar Blade tidak diproses
                                dan akan bocor jadi teks -- WAJIB komentar HTML. -->
                                <div id="io-autoResult" class="io-auto-result">
                                    <span>{{ t('Optimization result') }}</span><b id="io-autoResultValue">—</b>
                                    <div class="io-note" id="io-autoResultNote">{{ t('The arrangement is calculated from the canvas size & the dieline shape.') }}</div>
                                </div>
                                <div id="io-manualGroup" style="display:none">
                                    <label>{{ t('Target up (manual)') }}</label>
                                    <input id="io-manualUp" type="number" min="1">
                                    <p class="io-note">{{ t('The smallest canvas size that fits this number will be searched.') }}</p>
                                </div>
                            </div>
                            <h2 style="margin-top:14px">{{ t('2. Gap') }}</h2>
                            <label>{{ t('Distance between artwork / dieline (cm)') }}</label>
                            <input id="io-gap" type="number" min="0" step=".05" value=".3">
                        </div>
                        <div class="io-group">
                            <h2>{{ t('3. Print Sheet / Canvas') }}</h2>
                            <!-- Batas canvas pilihan officer (user 2026-10-01): Custom = ikut dieline tanpa batas meja (bawaan part
                            baru); selain itu batas meja cetak, meja pond, atau terkecil dari keduanya (aturan 16-Sep). v2 saja. -->
                            <label>{{ t('Canvas limit') }}</label>
                            <select id="io-limit">
                                <option value="CUSTOM">{{ t('Custom (follow dieline)') }}</option>
                                <option value="PRESS">{{ t('Press bed') }}</option>
                                <option value="DIECUT">{{ t('Die-cut bed (Pond)') }}</option>
                                <option value="BOTH">{{ t('Press + Die-cut (smallest of both)') }}</option>
                            </select>
                            <div id="io-limitNote" class="io-notice info"></div>
                            <div class="io-row">
                                <div><label>{{ t('Length (cm)') }}</label><input id="io-sl" type="number" min="1" step=".1"></div>
                                <div><label>{{ t('Width (cm)') }}</label><input id="io-sw" type="number" min="1" step=".1"></div>
                            </div>
                            <button id="io-suggest" class="io-btn green" style="width:100%;margin-top:9px" title="{{ t('Changes the canvas size AND the plano') }}">✦ Suggestion</button>
                            <p id="io-suggestNote" class="io-note"></p>
                            <label>{{ t('Grain direction') }}</label>
                            <select id="io-grain">
                                <option value="L">{{ t('Along dieline length') }}</option>
                                <option value="W">{{ t('Along dieline width') }}</option>
                                <option value="none">{{ t('No grain constraint') }}</option>
                            </select>
                        </div>
                        <div class="io-group">
                            <h2>{{ t('Calculation Data') }}</h2>
                            <div class="io-mini-grid" style="margin-top:0">
                                <div class="io-mini"><span>{{ t('Canvas utilization') }}</span><b id="io-yCanvas">—</b></div>
                                <div class="io-mini"><span>{{ t('Canvas waste') }}</span><b id="io-yCanvasW">—</b></div>
                                <div class="io-mini"><span>{{ t('Plano sheet yield') }}</span><b id="io-yPlano">—</b></div>
                                <div class="io-mini"><span>{{ t('Overall efficiency') }}</span><b id="io-yAll">—</b></div>
                            </div>
                            <div class="io-bar"><i id="io-barFill"></i><span id="io-barTxt"></span></div>
                            <div id="io-impoDetail" class="io-spec"></div>
                        </div>
                        <div class="io-group">
                            <h2>{{ t('Specification & Artwork') }}</h2>
                            <div id="io-spec" class="io-spec" style="margin-top:0"></div>
                            <label>{{ t('Artwork asset') }}</label>
                            <label class="io-upload">{{ t('Upload artwork') }}<input id="io-artFile" type="file" accept="image/*,.pdf,.ai,.svg"></label>
                            <div id="io-artName" class="io-file">{{ t('Default artwork of the active job') }}</div>
                            <label>{{ t('Separate dieline') }}</label>
                            <label class="io-upload">{{ t('Upload dieline') }}<input id="io-dieFile" type="file" accept="image/*,.pdf,.ai,.svg,.dxf"></label>
                            <div id="io-dieName" class="io-file">{{ t('Default structural dieline of the active job') }}</div>
                        </div>
                    </div>
                </section>

                <section class="io-sec" data-sec="plano">
                    <button type="button" class="io-sec-head"><span>{{ t('Plano Configuration') }}</span></button>
                    <div class="io-sec-body">
                        <div class="io-group">
                            <h2>{{ t('Machine') }}</h2>
                            <label>{{ t('Printing machine') }}</label>
                            <select id="io-machine"></select>
                            <div id="io-machineInfo" class="io-notice info"></div>
                            <label>{{ t('Die-cut machine (plong / pond)') }}</label>
                            <select id="io-diecut"></select>
                            <div id="io-diecutInfo" class="io-notice info"></div>
                        </div>
                        <div class="io-group">
                            <h2>{{ t('Plano Optimization') }}</h2>
                            <label>{{ t('Market plano size') }}</label>
                            <select id="io-plano"></select>
                            <button id="io-calcPlano" class="io-btn green" style="width:100%;margin-top:8px" title="{{ t('Choose the most economical plano for the current canvas size') }}">⟳ Calculate Plano</button>
                            <div class="io-row">
                                <div><label>{{ t('Custom L') }}</label><input id="io-cpl" type="number" step=".1" placeholder="cm"></div>
                                <div><label>{{ t('Custom W') }}</label><input id="io-cpw" type="number" step=".1" placeholder="cm"></div>
                            </div>
                            <button id="io-custom" class="io-btn" style="width:100%;margin-top:8px">Use custom plano</button>
                            <div id="io-planoNote" class="io-notice info"></div>
                            <div id="io-cut" class="io-cut"></div>
                        </div>
                        <div class="io-group">
                            <h2>{{ t('Plano Ranking') }}</h2>
                            <div id="io-planoTable" class="io-spec" style="margin-top:0"></div>
                        </div>
                        <div class="io-group">
                            <h2>{{ t('PCS ↔ PLANO Calculator') }}</h2>
                            <div class="io-mode-switch">
                                <button id="io-pcsMode" class="active">PCS → PLANO</button>
                                <button id="io-planoMode">PLANO → PCS</button>
                            </div>
                            <div class="io-compact">
                                <b id="io-inputLabel">{{ t('Qty order (pcs)') }}</b>
                                <label for="io-converterInput" class="io-note" id="io-inputHint">{{ t('Enter the number of products that must be produced') }}</label>
                                <input id="io-converterInput" type="number" min="0" value="100">
                                <div class="io-result">
                                    <div class="caption" id="io-resultCaption">{{ t('Plano required') }}</div>
                                    <b id="io-resultValue">—</b> <small id="io-resultUnit">{{ t('sheets') }}</small>
                                </div>
                                <div class="io-mini-grid">
                                    <div class="io-mini"><span>{{ t('Active up') }}</span><b id="io-mUp">—</b></div>
                                    <div class="io-mini"><span>{{ t('Yield / plano') }}</span><b id="io-mYield">—</b></div>
                                    <div class="io-mini"><span>{{ t('Products / plano') }}</span><b id="io-mPerPlano">—</b></div>
                                    <div class="io-mini"><span>{{ t('Waste / plano') }}</span><b id="io-mWaste">—</b></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="io-sec" data-sec="bom">
                    <button type="button" class="io-sec-head"><span>{{ t('Bill of Material') }}</span><em class="io-sec-count" id="io-cnt-bom"></em></button>
                    <div class="io-sec-body io-sec-body--single"><div id="io-bom"></div></div>
                </section>

                <section class="io-sec" data-sec="ink">
                    <button type="button" class="io-sec-head"><span>{{ t('Ink Configuration') }}</span><em class="io-sec-count" id="io-cnt-ink"></em></button>
                    <div class="io-sec-body io-sec-body--single"><div id="io-ink"></div></div>
                </section>

                <section class="io-sec" data-sec="bop">
                    <button type="button" class="io-sec-head"><span>{{ t('Bill of Process') }}</span><em class="io-sec-count" id="io-cnt-bop"></em></button>
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
                    <div class="io-rulerx">{{ t('CANVAS / PRINT SHEET RULER — cm') }}</div>
                    <div class="io-rulery"></div>
                    <div id="io-sheetLabel" class="io-sheetlabel"></div>
                    <div id="io-warning" class="io-warn">⚠ {{ t('The artwork goes beyond the canvas / print sheet boundary') }}</div>
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
                    <span><i class="io-dot" style="background:#c7f1ff;border:1px solid #056c90"></i>{{ t('Artwork surface') }}</span>
                    <span><i class="io-dot" style="background:#fc2458"></i>{{ t('Die-cut / trim line') }}</span>
                    <span><i class="io-dot" style="background:#27a560"></i>{{ t('Fold / crease line') }}</span>
                    <span><i class="io-dot" style="background:#9c6b12"></i>{{ t('Grain') }}</span>
                    <span><i class="io-dot" style="background:#7732e2"></i>{{ t('Selected object') }}</span>
                </div>
                <div class="io-instructions">
                    {!! t('<b>Manual nesting:</b> select and drag the artwork/dieline one by one.') !!}
                    {{ t('Use Rotate and Mirror to simulate opposing arrangements and to fill empty space according to the dieline shape.') }}
                    {!! t('Once the arrangement is set, the <b>✦ Suggestion</b> button does not reset the layout — the arrangement is tightened to the gap (between objects & around the edge), then the canvas size and the plano are chosen again.') !!}
                    {!! t('Press <b>Reset layout</b> to go back to the automatic grid.') !!}
                </div>
                <div id="io-valid" class="io-notice info">{{ t('Set a valid canvas size.') }}</div>
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
            <b>{{ t('Preview Master Card') }}</b>
            <span id="io-mcState" class="io-mc-state"></span>
            <span style="flex:1"></span>
            <button class="io-btn" id="io-mcPrint">🖨 Print (A4 landscape)</button>
            {{-- Email Master Card: lembar pratinjau ini jadi dokumennya (menu cetak doc-share disembunyikan: cetak = tombol di kiri). --}}
            <x-dl::doc-share id="io-mcShare" target=".io-mc-sheet" type="prodev-mastercard" :doc-id="$cfg['partId'] ?? null" :number="$cfg['docNo'] ?? null" title="Master Card" orientation="landscape" size="sm" />
            <button class="io-btn warn" id="io-mcClose">✕ Close</button>
        </div>
        <div class="io-mc-scroll">
            <div class="io-mc-sheet"><div class="io-mc-pad" id="io-mcSheet"></div></div>
        </div>
    </div>
</div>
