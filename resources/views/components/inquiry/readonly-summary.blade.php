{{--
    Ringkasan Inquiry Product cross-nav read-only (user 2026-09-04, koreksi
    eksplisit: "JANGAN pakai layar inquiry product yang original karena itu
    untuk inputan... boleh improve dari attachment #1", mockup Esko-style
    Step 1 -- mockup_FORM_prodev_!.png). Komponen TERPISAH dari
    inquiry/form.blade.php (bukan cabang @if di file yang sama) -- ditemukan
    bug nyata 2026-09-04: di file induk yang >1900 baris, percabangan
    @if($x)/@if(!$x) dua-cabang sempat merender KEDUANYA sekaligus (baik
    dgn nama properti $view maupun $crossView, jadi BUKAN soal nama
    variabel) -- akar penyebab pastinya tidak bisa dipastikan dalam waktu
    yang tersedia, tapi memisahkan markup besar ini ke file baru yang kecil
    & bersih TERBUKTI menghilangkan gejalanya. Kalau nanti ada masalah
    serupa di layar lain, pola ini (pisah ke component kecil, bukan cabang
    besar di file 1000+ baris) adalah mitigasi yang sudah terverifikasi.

    Props:
    - inquiry: ?IqpInquiry, null kalau job Mode #2 (manual, tidak ada Inquiry).
    - parts: array<int, array{part: IqpPart, summary: array}> -- summary dari
      IqpPart's dieline_spec (type_name/dims/flat_w/flat_h/flat_area/qty_per_sku),
      dihitung induk (Livewire) lewat method partSummary(), BUKAN di sini
      (anonymous component tak akses method Livewire).
--}}
@props(['inquiry' => null, 'parts' => []])

<div class="max-w-[1100px] mx-auto py-6 px-4">
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
            </div>
        </div>

        @foreach ($parts as $row)
            @php([$part, $ps] = [$row['part'], $row['summary']])
            <div class="flex flex-col lg:flex-row gap-4 mb-4 items-start">
                <div class="flex-1 min-w-0 w-full bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
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
                <div class="w-full lg:w-[300px] shrink-0 bg-[var(--pd-surface)] border border-[var(--pd-border)] rounded-md overflow-hidden">
                    <h3 class="text-xs text-[var(--pd-ink-strong)] uppercase font-bold px-3.5 py-2 bg-[var(--pd-surface-alt)] border-b border-[var(--pd-border)] flex items-center justify-between">
                        <span>✎ Unfolded 2D Dieline</span>
                        <span class="text-[9px] font-bold text-accent">1:1 PARAMETRIC</span>
                    </h3>
                    <div class="p-3.5">
                        @if ($ps['flat_w_px'] > 0)
                            <div class="bg-[var(--pd-surface-alt)] border border-[var(--pd-border)] rounded flex items-center justify-center mb-2.5" style="height:170px">
                                <div class="bg-white border-2 border-navy" style="width:{{ $ps['flat_w_px'] }}px;height:{{ $ps['flat_h_px'] }}px"></div>
                            </div>
                            <div class="text-[11px] text-muted mb-0.5">🔵 Cut · 🔴 Crease (referensi Esko ArtiosCAD)</div>
                            <div class="text-[12px]">Unfolded Size: <b class="text-[var(--pd-ink-strong)]">{{ number_format($ps['flat_w'], 1) }} × {{ number_format($ps['flat_h'], 1) }} mm</b></div>
                            <div class="text-[12px]">Net Area: <b class="text-[var(--pd-ink-strong)]">{{ $ps['flat_area'] ? number_format($ps['flat_area'] / 100, 1) : '—' }} cm²</b></div>
                        @else
                            <div class="text-[11.5px] text-muted">Belum ada dimensi flat tersimpan.</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
