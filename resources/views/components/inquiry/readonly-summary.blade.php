{{--
    Ringkasan Inquiry Product cross-nav read-only (user 2026-09-04, koreksi
    eksplisit: "JANGAN pakai layar inquiry product yang original karena itu
    untuk inputan... boleh improve dari attachment #1", mockup Esko-style
    Step 1 -- mockup_FORM_prodev_!.png). Komponen TERPISAH dari
    inquiry/form.blade.php (bukan cabang kondisional di file yang sama) --
    ditemukan bug nyata 2026-09-04: di file induk yang >1900 baris, dua
    cabang kondisional (mode lihat vs mode edit) sempat merender KEDUANYA
    sekaligus (baik dgn nama properti $view maupun $crossView, jadi BUKAN
    soal nama variabel) -- akar penyebab pastinya directive php inline
    bentuk-singkat tanpa tag penutup PHP (lihat memory
    feedback_blade_inline_php_no_closing_tag_gotcha.md -- CATATAN: gotcha
    yang SAMA berlaku utk teks directive Blade apa pun yang ditulis literal
    di DALAM komentar Blade ini sendiri, bukan cuma di kode aktif --
    makanya nama directive di paragraf ini sengaja ditulis tanpa tanda "at"
    di depannya). Kalau nanti ada masalah serupa di layar lain, pola ini
    (pisah ke component kecil, bukan cabang besar di file 1000+ baris)
    adalah mitigasi terverifikasi.

    Follow-up 2026-09-04 (user: "kurang informatif... tidak ada gambar
    layout... tidak diecut nya yang sudah di define di inquiry product...
    sertakan lebih banyak informasi" + "informasi finishing, bahan baku"):
    - Diecut ASLI (bukan kotak polos) via window.TMDieline.svg() (dieline
      DSL yg SAMA dipakai preview live saat input Inquiry -- lihat
      resources/js/tmdieline.js + dieline-part.js), pakai dieline_spec+dims
      yang dibekukan di part ini.
    - Bahan Baku (iqp_part_material) -- data NYATA, terisi via UI di form
      input (search MatRaw -> pilih -> gramatur/ukuran ikut ter-snapshot).
    - Proses/Finishing (iqp_part_process) -- tabelnya ADA di skema & DIPAKAI
      (cloneStructureFrom() saat REVISE/REKALKULASI meng-clone dari inquiry
      induk), TAPI form Inquiry Product (mode NEW) ini SENDIRI belum punya
      UI utk mengisi baris proses/finishing -- jadi utk inquiry NEW kosong
      itu BUKAN bug tampilan, itu gap nyata di form input (dicatat, bukan
      diasumsikan/dikarang datanya). Ditampilkan apa adanya + pesan jujur.

    Props:
    - inquiry: ?IqpInquiry, null kalau job Mode #2 (manual, tidak ada Inquiry).
    - parts: array<int, array{part: IqpPart, summary: array}> -- summary dari
      IqpPart's dieline_spec (type_name/dims/flat_w/flat_h/flat_area/qty_per_sku/
      dieline_spec/dieline_dims/color/materials/processes), dihitung induk
      (Livewire) lewat method partSummary(), BUKAN di sini (anonymous
      component tak akses method Livewire).
--}}
@props(['inquiry' => null, 'parts' => []])

<div class="max-w-[1200px] mx-auto py-6 px-4">
    @if (! $inquiry)
        <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md p-5">
            <div class="text-[11px] font-bold text-accent uppercase tracking-wide mb-1.5">Job Inquiry</div>
            <div class="text-[13px] leading-relaxed">
                📋 Part ini dibuat <b class="text-[var(--pd-ink-strong)]">manual (Mode #2 / standalone)</b>
                langsung di Pro-Dev — tidak pernah lewat Inquiry Product, jadi tidak ada data
                Inquiry untuk diringkas di sini.
            </div>
        </div>
    @else
        <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md p-5 mb-4 flex items-start justify-between gap-4 flex-wrap">
            <div>
                <span class="inline-block text-[9.5px] font-bold text-accent bg-accent/10 border border-accent/30 rounded-full px-2.5 py-0.5 uppercase tracking-wide mb-2">Step 1 · Product Inquiry (lihat-saja)</span>
                <h2 class="text-lg font-black text-[var(--pd-ink-strong)] m-0">{{ $inquiry->product_draft_name ?: $inquiry->inquiry_no }}</h2>
                <div class="text-[11.5px] text-muted mt-1">{{ $inquiry->inquiry_no }} · {{ $inquiry->customer?->customer_name ?? $inquiry->prospect_name }} · status <b class="text-[var(--pd-ink-strong)]">{{ $inquiry->sub_state }}</b></div>
            </div>
        </div>

        <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden mb-4">
            <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">1. Job Information (Kartu Induk Reference)</h3>
            <div class="p-3.5 grid three gap-x-4 gap-y-3 text-[12.5px]">
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Customer</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->customer?->customer_name ?? $inquiry->prospect_name ?? '—' }}</b></div>
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Nama Barang</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->product_draft_name ?: '—' }}</b></div>
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">No. Inkuiri</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->inquiry_no }}</b></div>
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Tipe</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->inquiry_type }}</b></div>
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Target Quantity</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->tiers->firstWhere('is_primary', true)?->qty ?? $inquiry->tiers->first()?->qty ?? '—' }} pcs</b></div>
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Tanggal Inkuiri</div><b class="text-[var(--pd-ink-strong)]">{{ optional($inquiry->inquiry_date)->format('d M Y') ?? '—' }}</b></div>
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Sumber Bahan</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->material_source === 'CUSTOMER' ? 'Customer sediakan' : 'Perusahaan (OWN)' }}</b></div>
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Sifat Pekerjaan</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->job_nature === 'JASA' ? 'JASA (maklon)' : 'PRODUCT' }}</b></div>
                <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Deadline Quotation</div><b class="text-[var(--pd-ink-strong)]">{{ optional($inquiry->deadline_quotation)->format('d M Y') ?? '—' }}</b></div>
                @if ($inquiry->design_number)
                    <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">No. Desain</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->design_number }}</b></div>
                @endif
                @if ($inquiry->due_date)
                    <div><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Due Date</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->due_date->format('d M Y') }}</b></div>
                @endif
                @if ($inquiry->material_source === 'CUSTOMER' && $inquiry->cust_material_spec)
                    <div class="col-span-3"><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Spesifikasi Bahan Customer</div><b class="text-[var(--pd-ink-strong)]">{{ $inquiry->cust_material_spec }}</b></div>
                @endif
                @if ($inquiry->sales_note)
                    <div class="col-span-3"><div class="text-[10.5px] text-muted uppercase font-semibold mb-0.5">Catatan Sales</div><span class="text-[var(--pd-ink-strong)]">{{ $inquiry->sales_note }}</span></div>
                @endif
            </div>
        </div>

        @foreach ($parts as $row)
            @php [$part, $ps] = [$row['part'], $row['summary']]; @endphp
            <div class="flex flex-col lg:flex-row gap-4 mb-4 items-start">
                <div class="flex-1 min-w-0 w-full flex flex-col gap-4">
                    {{-- 2. Box Style --}}
                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">2. Box Style — {{ $part->part_name }}</h3>
                        <div class="p-3.5">
                            <div class="inline-block text-[11.5px] font-bold text-[var(--pd-ink-strong)] bg-[var(--pd-surface-alt)] border border-[var(--pd-border)] rounded-md px-3 py-1.5 mb-3">
                                📦 {{ $ps['type_name'] ?? 'Tipe belum ditentukan' }}
                            </div>
                            <div class="text-[10.5px] text-muted uppercase font-semibold mb-1.5">Dimension Parameters</div>
                            <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-[12.5px]">
                                @forelse ($ps['dims'] as $d)
                                    <span>{{ $d['label'] }}: <b class="text-[var(--pd-ink-strong)]">{{ $d['value'] }}{{ $d['uom'] ? ' '.$d['uom'] : '' }}</b></span>
                                @empty
                                    <span class="text-muted">Belum ada parameter dimensi.</span>
                                @endforelse
                                @if ($ps['qty_per_sku'])<span>Qty/Set: <b class="text-[var(--pd-ink-strong)]">{{ $ps['qty_per_sku'] }}</b></span>@endif
                            </div>
                        </div>
                    </div>

                    {{-- 3. Warna & Cetak --}}
                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">3. Warna &amp; Cetak</h3>
                        <div class="p-3.5">
                            @if ($ps['color'])
                                <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-[12.5px] mb-2">
                                    <span>Kode: <b class="text-[var(--pd-ink-strong)]">{{ $ps['cfg_color'] }}</b></span>
                                    <span>Front: <b class="text-[var(--pd-ink-strong)]">{{ $ps['color']['front'] }}</b></span>
                                    <span>Back: <b class="text-[var(--pd-ink-strong)]">{{ $ps['color']['back'] }}</b></span>
                                    <span>Total: <b class="text-[var(--pd-ink-strong)]">{{ $ps['color']['total'] }}</b></span>
                                    <span>Sisi: <b class="text-[var(--pd-ink-strong)]">{{ $ps['color']['side'] }}</b></span>
                                </div>
                            @else
                                <div class="text-[11.5px] text-muted mb-2">Belum ada kode warna (front/back) tersimpan.</div>
                            @endif
                            @if ($ps['cfg_ink'])
                                <div class="text-[12px]">Catatan Pantone: <b class="text-[var(--pd-ink-strong)]">{{ $ps['cfg_ink'] }}</b></div>
                            @endif
                        </div>
                    </div>

                    {{-- 4. Bahan Baku --}}
                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">4. Bahan Baku (Material)</h3>
                        @if (empty($ps['materials']))
                            <div class="p-3.5 text-[11.5px] text-muted">Belum ada baris bahan baku tersimpan untuk part ini.</div>
                        @else
                            <table class="w-full text-[12px]">
                                <thead>
                                    <tr class="text-[10px] text-muted uppercase [&>th]:text-left [&>th]:font-semibold [&>th]:px-3.5 [&>th]:py-1.5 [&>th]:border-b [&>th]:border-[var(--pd-border)]">
                                        <th style="width:32px">#</th>
                                        <th>Nama Bahan</th>
                                        <th style="width:90px">Gramatur</th>
                                        <th style="width:110px">Ukuran</th>
                                        <th style="width:110px">Sumber</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ps['materials'] as $m)
                                        <tr class="[&>td]:px-3.5 [&>td]:py-1.5 [&>td]:border-b [&>td]:border-[var(--pd-border)] last:[&>td]:border-b-0">
                                            <td class="text-muted">{{ $m['layer_seq'] ?? $loop->iteration }}</td>
                                            <td class="text-[var(--pd-ink-strong)] font-semibold">
                                                {{ $m['name'] ?: '—' }}
                                                @if ($m['is_primary'])
                                                    <span class="ml-1 text-[9px] font-bold text-accent bg-accent/10 border border-accent/30 rounded-full px-1.5 py-0.5 align-middle">UTAMA</span>
                                                @endif
                                                @if ($m['flute_type'])<span class="text-muted"> · {{ $m['flute_type'] }}</span>@endif
                                            </td>
                                            <td>{{ $m['gram_weight'] ? number_format((float) $m['gram_weight'], 0).' gsm' : '—' }}</td>
                                            <td>{{ ($m['size_p'] && $m['size_l']) ? number_format((float) $m['size_p'], 1).'×'.number_format((float) $m['size_l'], 1).' cm' : '—' }}</td>
                                            <td>{{ $m['material_source'] === 'CUSTOMER' ? 'Customer' : 'Perusahaan' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    {{-- 5. Proses / Finishing --}}
                    <div class="bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                        <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)]">5. Proses / Finishing (Routing)</h3>
                        @if (empty($ps['processes']))
                            <div class="p-3.5 text-[11.5px] text-muted leading-relaxed">
                                Belum ada baris proses/finishing tersimpan. Form Inquiry Product (mode BARU) saat
                                ini memang belum punya input untuk mengisi routing proses per part — tabel ini baru
                                otomatis terisi (clone dari inquiry induk) saat dokumen berjenis REVISI/REKALKULASI.
                            </div>
                        @else
                            <table class="w-full text-[12px]">
                                <thead>
                                    <tr class="text-[10px] text-muted uppercase [&>th]:text-left [&>th]:font-semibold [&>th]:px-3.5 [&>th]:py-1.5 [&>th]:border-b [&>th]:border-[var(--pd-border)]">
                                        <th style="width:56px">Urutan</th>
                                        <th>Proses</th>
                                        <th style="width:90px">Alur</th>
                                        <th style="width:90px">Qty Factor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ps['processes'] as $p)
                                        <tr class="[&>td]:px-3.5 [&>td]:py-1.5 [&>td]:border-b [&>td]:border-[var(--pd-border)] last:[&>td]:border-b-0">
                                            <td class="text-muted">{{ $p['step_seq'] }}</td>
                                            <td class="text-[var(--pd-ink-strong)] font-semibold">{{ $p['process_name'] ?: '—' }}</td>
                                            <td>{{ $p['flow_type'] ?: '—' }}</td>
                                            <td>{{ $p['qty_factor'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>

                {{-- Dieline viewer --}}
                <div class="w-full lg:w-[340px] shrink-0 bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden lg:sticky lg:top-4">
                    <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)] flex items-center justify-between">
                        <span>✎ Unfolded 2D Dieline</span>
                        <span class="text-[9px] font-bold text-accent">1:1 PARAMETRIC</span>
                    </h3>
                    <div class="p-3.5">
                        @if ($ps['dieline_spec'])
                            <div class="bg-white border border-[var(--pd-border)] rounded flex items-center justify-center mb-2.5 p-2"
                                 style="height:230px"
                                 x-data="{ svg: null }"
                                 x-init="svg = window.TMDieline ? window.TMDieline.svg(@js($ps['dieline_spec']), @js($ps['dieline_dims'])) : null">
                                <div x-show="svg" x-html="svg" class="w-full h-full"></div>
                                <div x-show="!svg" class="text-[11px] text-muted">Gagal memuat gambar dieline.</div>
                            </div>
                            <div class="text-[11px] text-muted mb-2">🔴 potong · 🟢 lipat · ⚪ overflow · 🔵 lubang/jilid</div>
                        @elseif ($ps['flat_w_px'] > 0)
                            <div class="bg-[var(--pd-surface-alt)] border border-[var(--pd-border)] rounded flex items-center justify-center mb-2.5" style="height:170px">
                                <div class="bg-white border-2 border-navy" style="width:{{ $ps['flat_w_px'] }}px;height:{{ $ps['flat_h_px'] }}px"></div>
                            </div>
                            <div class="text-[11px] text-muted mb-0.5">⚠️ Tipe ini belum punya definisi diecut (dieline_spec) — kotak polos dari flat W×H.</div>
                        @else
                            <div class="text-[11.5px] text-muted mb-2">Belum ada dimensi flat tersimpan.</div>
                        @endif
                        <div class="text-[12px]">Unfolded Size: <b class="text-[var(--pd-ink-strong)]">{{ number_format($ps['flat_w'], 1) }} × {{ number_format($ps['flat_h'], 1) }} mm</b></div>
                        <div class="text-[12px]">Net Area: <b class="text-[var(--pd-ink-strong)]">{{ $ps['flat_area'] ? number_format($ps['flat_area'] / 100, 1) : '—' }} cm²</b></div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
