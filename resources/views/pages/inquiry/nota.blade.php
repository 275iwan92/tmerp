<?php

use App\Models\Master\OrgCompany;
use App\Models\Sdi\IqpInquiry;
use App\Services\Inquiry\TierPricingResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Nota Inquiry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $inquiryId;

    #[Url(as: 'bw')]
    public bool $bw = false;

    public function mount(int $inquiry): void
    {
        $this->inquiryId = $inquiry;
    }

    #[Computed]
    public function inquiry(): IqpInquiry
    {
        return IqpInquiry::with([
            'customer',
            'product',
            'parts.materials',
            'parts.processes',
            'tiers',
            'costing.priceTiers',
            'toolings',
        ])->findOrFail($this->inquiryId);
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('company_code', 'TM')->first();
    }

    // Sisi A/B (CLAUDE.md §4): HPP/margin rahasia sampai Direksi publish. Dokumen
    // pelanggan ini HANYA boleh nampilkan harga jual kalau costing.published_flag=1.
    #[Computed]
    public function priced(): bool
    {
        return (bool) ($this->inquiry->costing?->published_flag) && $this->inquiry->costing->priceTiers->isNotEmpty();
    }

    #[Computed]
    public function tierRows()
    {
        return TierPricingResolver::rows($this->inquiry, $this->priced);
    }

    #[Computed]
    public function primaryRow()
    {
        return TierPricingResolver::primary($this->tierRows);
    }

    #[Computed]
    public function toolingTotal(): float
    {
        return (float) $this->inquiry->toolings->sum('cost');
    }

    #[Computed]
    public function partsData(): array
    {
        return $this->inquiry->parts->sortBy('part_seq')->values()->map(function ($part) {
            $dims = $part->dieline_spec['dims'] ?? [];
            $material = $part->materials->firstWhere('is_primary', true) ?? $part->materials->first();

            return [
                'seq' => $part->part_seq,
                'name' => $part->part_name,
                'type_code' => $part->dieline_spec['type_code'] ?? $part->part_type,
                'product_length' => $dims['product_length'] ?? null,
                'product_width' => $dims['product_width'] ?? null,
                'product_height' => $dims['product_height'] ?? null,
                'glue_flap' => $dims['glue_flap'] ?? null,
                'flat_w' => (float) $part->flat_w,
                'flat_h' => (float) $part->flat_h,
                'flat_area_m2' => $part->flat_area ? round(((float) $part->flat_area) / 1_000_000, 4) : null,
                'material_name' => $material?->rawmat_name,
                'material_gram' => $material?->gram_weight,
                'cfg_color' => $part->cfg_color,
                'cfg_ink' => $part->cfg_ink,
                'processes' => $part->processes->sortBy('step_seq')->pluck('process_name')->filter()->implode(', '),
                // spec+dims mentah -> dirender window.TMDieline.render() (resources/js/tmdieline.js),
                // renderer DSL yang sama dipakai Inquiry New (dieline-part.js), bukan reimplementasi lokal.
                'spec' => $part->dieline_spec,
                'dims' => $dims,
            ];
        })->values()->all();
    }
};
?>

{{-- Kerangka umum (bar cetak, page A4, kop, parties, section bernomor, tanda
     tangan, footer) datang dari resources/views/components/print/* --
     docs/printnotastandard.md. Palet hijau khas Nota Inquiry (beda dari navy
     Quotation) dikirim lewat prop `theme`; mode hemat-tinta (bw) TIDAK dibuat
     lewat override warna tema (background solid -> hitam pekat, buruk utk
     print) tapi lewat class modifier `.bw` + rule CSS lokal di bawah -- pola
     sama seperti versi asli sebelum diekstrak. --}}
<x-print.document
    :ppn="$this->inquiry->tax_scheme === 'PPN'"
    :theme="['accent' => '#0d5c46', 'accent-soft' => '#eaf3f0']"
    class="{{ $this->bw ? 'bw' : '' }}"
    info="{{ $this->inquiry->inquiry_no }} · {{ $this->inquiry->parts->count() }} part · {{ $this->inquiry->tiers->count() }} jenjang qty">
    <x-slot:toolbar>
        <button type="button" wire:click="$set('bw', {{ $this->bw ? 'false' : 'true' }})" class="sc">{{ $this->bw ? '🎨 Versi Warna' : '🖨 Versi Hemat Tinta' }}</button>
        <a href="{{ route('inquiry.costing', ['inquiry' => $this->inquiryId]) }}" class="sc">→ Costing</a>
        <a href="{{ route('inquiry.hub') }}" class="sc">← Inquiry Hub</a>
    </x-slot:toolbar>

    {{-- ================= HALAMAN 1 ================= --}}
    <x-print.sheet>
        <x-print.header :company="$this->company" :ppn="$this->inquiry->tax_scheme === 'PPN'" doc-title="NOTA INQUIRY" :doc-no="$this->inquiry->inquiry_no">
            <x-slot:meta>
                {{ $this->inquiry->inquiry_date?->translatedFormat('d F Y') }}
                @if ($this->inquiry->revision_no > 0) · Rev. {{ str_pad($this->inquiry->revision_no, 2, '0', STR_PAD_LEFT) }} @endif
                @php
                    // Badge per tipe dokumen (inquiry_nota_new/recalc/revise.html mockup) --
                    // ketiga mockup itu TERNYATA sama persis strukturnya, beda cuma warna
                    // badge tipe (hijau/ungu/oranye) -- bukan 3 layar terpisah.
                    $typeBadgeClass = match (true) {
                        $this->inquiry->stat_cancel => 'b-cancel',
                        $this->inquiry->inquiry_type === 'RECALC' => 'b-recalc',
                        $this->inquiry->inquiry_type === 'REVISE' => 'b-revise',
                        default => 'b-new',
                    };
                @endphp
                <span class="badge {{ $typeBadgeClass }}">{{ $this->inquiry->stat_cancel ? 'CANCELLED' : $this->inquiry->inquiry_type }}</span>
                @if ($this->inquiry->is_prospect) <span class="badge b-prospect">PROSPECT</span> @endif
                <br>
                @if ($this->inquiry->deadline_quotation) Berlaku s/d {{ $this->inquiry->deadline_quotation->translatedFormat('d F Y') }} @endif
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.25fr 1fr .85fr">
            <x-print.party label="KEPADA YTH.">
                <div class="nm">{{ $this->inquiry->customer?->customer_name ?? $this->inquiry->prospect_name ?? '—' }}</div>
                <div class="dt">
                    {{ $this->inquiry->customer?->default_address ?? '—' }}<br>
                    @if ($this->inquiry->customer?->contact_name) UP. {{ $this->inquiry->customer->contact_name }} @endif
                    @if ($this->inquiry->customer?->phone) · {{ $this->inquiry->customer->phone }} @endif
                </div>
            </x-print.party>
            <x-print.party label="PRODUK">
                <div class="nm">{{ $this->inquiry->product_draft_name ?? $this->inquiry->product?->product_name ?? '—' }}</div>
                <div class="dt">
                    {{ $this->inquiry->parts->count() }} part
                    @if ($this->inquiry->deadline_quotation) · Deadline quotation: {{ $this->inquiry->deadline_quotation->translatedFormat('d M Y') }} @endif
                </div>
            </x-print.party>
            <x-print.party label="PIC SALES">
                <div class="nm">{{ $this->inquiry->pic_sales ?? '—' }}</div>
                <div class="dt">
                    Sumber: {{ $this->inquiry->source ?? '—' }}<br>
                    Mata uang: {{ $this->inquiry->costing?->currency_code ?? 'IDR' }}
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Penawaran Harga" hint="per pcs · IDR · PPN {{ $this->inquiry->costing?->ppn_pct ?? $this->inquiry->ppn_percent ?? 11 }}%">
            @if ($this->priced && $this->primaryRow)
                <div class="pricing">
                    <div class="hero">
                        <div class="lbl">TIER YANG DIMINTA</div>
                        <div class="qty">{{ number_format($this->primaryRow->tier->qty, 0, ',', '.') }} <span>pcs</span></div>
                        <div class="amt"><small>Rp</small> {{ number_format($this->primaryRow->price_incl, 0, ',', '.') }}</div>
                        <div class="excl">excl. PPN &nbsp;Rp {{ number_format($this->primaryRow->price_excl, 0, ',', '.') }}</div>
                        <div class="unit">harga incl. PPN per pcs</div>
                        <div class="tot">
                            Nilai order &nbsp;<b>Rp {{ number_format($this->primaryRow->value_total, 0, ',', '.') }}</b>
                            @if ($this->primaryRow->tier->price_excl !== null)
                                @php $delta = $this->primaryRow->price_excl - $this->primaryRow->tier->price_excl; @endphp
                                <span class="dlt">Target customer Rp {{ number_format($this->primaryRow->tier->price_excl, 0, ',', '.') }}
                                    — selisih Rp {{ number_format(abs($delta), 0, ',', '.') }} {{ $delta > 0 ? 'di atas' : 'di bawah' }} target</span>
                            @endif
                        </div>
                    </div>

                    <div class="tiers">
                        <table class="tt">
                            <thead>
                                <tr>
                                    <th style="width:20%">Jenjang</th>
                                    <th>Qty (pcs)</th>
                                    <th>Target</th>
                                    <th>Harga excl.</th>
                                    <th>Harga incl.</th>
                                    <th style="width:23%">Nilai Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->tierRows as $row)
                                    <tr class="{{ $row->tier->is_primary ? 'cur' : '' }} {{ $row->is_best ?? false ? 'best' : '' }}">
                                        <td>Tier {{ $loop->iteration }}{{ $row->tier->is_primary ? ' ★' : '' }}</td>
                                        <td class="q">{{ number_format($row->tier->qty, 0, ',', '.') }}</td>
                                        <td>{{ $row->tier->price_excl !== null ? number_format($row->tier->price_excl, 0, ',', '.') : '—' }}</td>
                                        <td class="p">{{ $row->price_excl !== null ? number_format($row->price_excl, 0, ',', '.') : '—' }}</td>
                                        <td>{{ $row->price_incl !== null ? number_format($row->price_incl, 0, ',', '.') : '—' }}</td>
                                        <td>{{ $row->value_total !== null ? number_format($row->value_total, 0, ',', '.') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="tier-foot">
                            ★ tier utama yang diminta customer.
                            Harga berbasis spesifikasi Bagian 2. Perubahan material, gramatur, jumlah warna,
                            atau finishing akan mengubah harga.
                        </div>
                    </div>
                </div>
            @else
                <div class="print-warn">
                    <div class="t">⚠ Harga belum tersedia</div>
                    <div class="d">
                        Costing untuk inquiry ini belum dipublish oleh Direksi. Dokumen ini hanya berisi
                        spesifikasi teknis (Bagian 2) — hubungi tim Costing untuk penawaran harga resmi
                        sebelum dokumen ini dijadikan acuan negosiasi.
                    </div>
                </div>
            @endif
        </x-print.section>

        <x-print.section number="2" title="Spesifikasi Produk" hint="dieline ukuran penuh di halaman 2">
            @forelse ($this->partsData as $p)
                <div class="part">
                    <div class="part-h">
                        <div class="t">Part {{ $p['seq'] }} — {{ $p['name'] ?: 'Part #'.$p['seq'] }}
                            @if ($p['type_code']) <small>/ {{ $p['type_code'] }}</small> @endif
                        </div>
                        <div class="tag">{{ $p['type_code'] ?: '—' }}</div>
                    </div>
                    <div class="part-b">
                        <div class="part-col">
                            <table class="spec">
                                <tr><td class="k">Dimensi P×L×T</td><td class="v">{{ $p['product_length'] ?? '—' }} × {{ $p['product_width'] ?? '—' }} × {{ $p['product_height'] ?? '—' }} mm</td></tr>
                                @if ($p['glue_flap'])
                                    <tr><td class="k">Lidah lem</td><td class="v">{{ $p['glue_flap'] }} mm</td></tr>
                                @endif
                                <tr><td class="k">Bentangan flat</td><td class="v">{{ number_format($p['flat_w'], 0) }} × {{ number_format($p['flat_h'], 0) }} mm</td></tr>
                                @if ($p['flat_area_m2'])
                                    <tr><td class="k">Luas flat</td><td class="v">{{ number_format($p['flat_area_m2'], 3) }} m²</td></tr>
                                @endif
                            </table>
                        </div>
                        <div class="part-col">
                            <table class="spec">
                                <tr><td class="k">Material</td><td class="v">{{ $p['material_name'] ?? '—' }}{{ $p['material_gram'] ? ' '.$p['material_gram'].' gsm' : '' }}</td></tr>
                                <tr><td class="k">Warna</td><td class="v">{{ $p['cfg_color'] ?? '—' }}{{ $p['cfg_ink'] ? ' ('.$p['cfg_ink'].')' : '' }}</td></tr>
                                <tr><td class="k">Proses/Finishing</td><td class="v">{{ $p['processes'] ?: '—' }}</td></tr>
                            </table>
                        </div>
                        <div class="part-dl">
                            <div class="dl-mini" data-part="{{ $p['seq'] }}" wire:ignore></div>
                            <div class="dl-cap">{{ $p['type_code'] }}</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="print-warn"><div class="d">Belum ada part di inquiry ini.</div></div>
            @endforelse
        </x-print.section>

        <x-print.section number="3" title="Catatan">
            <div class="print-two">
                <div class="print-box">
                    <h5>CATATAN SALES</h5>
                    {{ $this->inquiry->sales_note ?: '—' }}
                </div>
                <div class="print-box">
                    <h5>INFO TAMBAHAN</h5>
                    @if ($this->inquiry->design_number) No. Desain: <b>{{ $this->inquiry->design_number }}</b><br> @endif
                    @if ($this->inquiry->material_source) Sumber material: {{ $this->inquiry->material_source }}<br> @endif
                    @if ($this->inquiry->job_nature) Sifat pekerjaan: {{ $this->inquiry->job_nature }} @endif
                    @if (! $this->inquiry->design_number && ! $this->inquiry->material_source && ! $this->inquiry->job_nature) — @endif
                </div>
            </div>
        </x-print.section>

        <x-print.footer page="Halaman 1 dari 2">
            {{ $this->inquiry->inquiry_no }} · {{ $this->inquiry->customer?->customer_name ?? $this->inquiry->prospect_name }} ·
            dicetak {{ now()->format('d/m/Y H:i') }} · syarat &amp; konfirmasi di halaman 2
        </x-print.footer>
    </x-print.sheet>

    {{-- ================= HALAMAN 2 ================= --}}
    <x-print.sheet>
        <x-print.section number="4" title="Dieline Ukuran Penuh" hint="skematik, tidak berskala · —— potong · - - - rill/lipat">
            <div class="print-two">
                @foreach ($this->partsData as $p)
                    <div class="print-box" style="padding:9px">
                        <h5>PART {{ $p['seq'] }} — {{ strtoupper($p['name'] ?: '') }}</h5>
                        <div class="dl-big" data-part="{{ $p['seq'] }}" wire:ignore></div>
                        <div style="font-size:7.4pt;color:var(--print-muted);margin-top:4px">
                            Bentangan {{ number_format($p['flat_w'], 0) }} × {{ number_format($p['flat_h'], 0) }} mm
                            @if ($p['material_name']) · {{ $p['material_name'] }} @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-print.section>

        <x-print.section number="5" title="Syarat &amp; Ketentuan">
            <ol class="print-terms">
                <li>Harga berlaku 30 hari sejak tanggal nota ini diterbitkan.</li>
                <li>Toleransi jumlah pengiriman ±5% dari qty order (standar industri percetakan).</li>
                <li>Lead time produksi menyesuaikan kapasitas produksi setelah artwork final &amp; digital proof disetujui.</li>
                @if ($this->toolingTotal > 0)
                    <li>Biaya tooling (pisau/matras/plate) sebesar Rp {{ number_format($this->toolingTotal, 0, ',', '.') }} dibebankan satu kali di order pertama.</li>
                @endif
                <li>Pembayaran sesuai payment term yang berlaku untuk customer terdaftar / DP untuk order pertama.</li>
                <li>Harga belum termasuk biaya pengiriman kecuali disebutkan lain.</li>
                <li>Perubahan spesifikasi setelah nota disetujui dapat mengubah harga dan lead time.</li>
            </ol>
        </x-print.section>

        <x-print.signatures title="KONFIRMASI CUSTOMER">
            <x-slot:intro>
                Mohon periksa harga (Bagian 1) dan spesifikasi (Bagian 2) pada halaman 1. Bila telah sesuai,
                tandatangani kolom di bawah dan kirim kembali ke PIC Sales.
                <b>Bila ada koreksi, tuliskan langsung pada dokumen ini</b> — kami akan terbitkan revisi.
                <div style="margin-top:6px">
                    <label>☐ <b>SESUAI</b> — lanjutkan ke penawaran resmi (quotation), qty tier:
                        @foreach ($this->inquiry->tiers->sortBy('tier_seq') as $t)
                            &nbsp;☐ {{ number_format($t->qty, 0, ',', '.') }}
                        @endforeach
                    </label><br>
                    <label>☐ Perlu <b>REVISI</b> — catatan:
                        <span style="border-bottom:1px dotted #999;display:inline-block;width:56%">&nbsp;</span>
                    </label>
                </div>
            </x-slot:intro>

            <x-print.signature role="Customer" sub="Nama jelas, jabatan &amp; stempel" />
            <x-print.signature role="Sales" :sub="$this->inquiry->pic_sales ?? '—'" />
            <x-print.signature role="Mengetahui" sub="Sales Manager" />
        </x-print.signatures>

        <x-print.footer page="Halaman 2 dari 2">
            {{ $this->inquiry->inquiry_no }} · Dokumen dibuat otomatis oleh sistem TMERP —
            sah tanpa tanda tangan basah pihak penerbit.
        </x-print.footer>
    </x-print.sheet>

    <style>
        {{-- Hanya style KONTEN spesifik Nota Inquiry (pricing hero, tiers table,
             part spec, dieline, badge tipe dokumen) + override mode hemat-tinta
             (.bw) -- kerangka umum sudah dari resources/views/components/print/*. --}}
        .print-doc.bw{
          --print-ink:#000; --print-muted:#555; --print-line:#000; --print-line2:#999;
          --print-accent:#000; --print-accent-soft:#fff; --print-warn:#000; --print-gold:#000; --print-danger:#000;
        }
        .print-doc.bw{background:#c9ccd0}
        .print-doc.bw .print-kop{border-bottom:2px solid var(--print-ink)}
        .print-doc.bw .print-kop .logo{background:none;border:2px solid var(--print-ink);color:var(--print-ink)}
        .print-doc.bw .print-kop .doc-title{color:var(--print-ink)}
        .print-doc.bw .badge{border:1px solid var(--print-ink);background:none;color:var(--print-ink)}
        .print-doc.bw .print-party .dt{color:#333}
        .print-doc.bw .print-sec-h .n{background:none;border:1.5px solid var(--print-ink);color:var(--print-ink)}
        .print-doc.bw .pricing{border:1.5px solid var(--print-ink)}
        .print-doc.bw .hero{background:none;color:var(--print-ink);border-right:1.5px solid var(--print-ink)}
        .print-doc.bw .hero .lbl{opacity:1;color:var(--print-muted);font-weight:700}
        .print-doc.bw .hero .excl{opacity:1;color:var(--print-muted)}
        .print-doc.bw .hero .tot{border-top:1px solid var(--print-ink)}
        .print-doc.bw .hero .dlt{opacity:1;color:var(--print-muted)}
        .print-doc.bw table.tt th{background:none}
        .print-doc.bw table.tt tr.cur td{background:none;border-top:1.5px solid var(--print-ink);border-bottom:1.5px solid var(--print-ink)}
        .print-doc.bw table.tt tr.cur td:first-child{color:var(--print-ink)}
        .print-doc.bw table.tt tr.best td.p{color:var(--print-ink);text-decoration:underline}
        .print-doc.bw .print-warn{border:1.5px dashed var(--print-ink);background:none}
        .print-doc.bw .print-warn .t{color:var(--print-ink)}
        .print-doc.bw .print-warn .d{color:var(--print-ink)}
        .print-doc.bw .part-h{background:none}
        .print-doc.bw .part-h .tag{color:#333}
        .print-doc.bw .part-dl{background:none}
        .print-doc.bw .print-sign{border:1.5px solid var(--print-ink)}
        .print-doc.bw .print-sign-h{background:none;color:var(--print-ink);border-bottom:1.5px solid var(--print-ink)}
        {{-- TMDieline (resources/js/tmdieline.js) pakai warna cut/crease tetap
             (merah/hijau) -- desaturate di mode hemat-tinta, jangan ubah modul
             JS bersama (dipakai juga Inquiry New yg butuh warna asli). --}}
        .print-doc.bw .part-dl svg, .print-doc.bw .dl-big svg{filter:grayscale(1)}

        .print-doc .badge{display:inline-block;padding:1.5px 7px;border-radius:20px;font-size:6.8pt;
               font-weight:700;letter-spacing:.6px;vertical-align:2px;background:var(--print-accent-soft);color:var(--print-accent);border:1px solid var(--print-line)}
        .print-doc .b-new{background:var(--print-accent-soft);color:var(--print-accent);border-color:#b8d8ce}
        .print-doc .b-recalc{background:#e2e0ff;color:#4b3fa8;border-color:#c4bef0}
        .print-doc .b-revise{background:#ffe0b2;color:#8a4b00;border-color:#ffcf8a}
        .print-doc .b-prospect{background:#fff5e0;color:var(--print-warn);border-color:#f0d9a8}
        .print-doc .b-cancel{background:#fdeaea;color:var(--print-danger);border-color:#f0bdbb}

        .print-doc .pricing{display:grid;grid-template-columns:66mm 1fr;border:1.5px solid var(--print-accent);
                 page-break-inside:avoid}
        .print-doc .hero{background:var(--print-accent);color:#fff;padding:9px 11px;display:flex;
              flex-direction:column;justify-content:center}
        .print-doc .hero .lbl{font-size:6.8pt;letter-spacing:1.2px;opacity:.82;font-weight:600}
        .print-doc .hero .qty{font-size:12pt;font-weight:600;margin-top:1px;line-height:1.15}
        .print-doc .hero .qty span{font-size:8pt;opacity:.8;font-weight:400}
        .print-doc .hero .amt{font-size:24pt;font-weight:700;letter-spacing:-1.2px;line-height:1;
                   margin-top:6px;font-variant-numeric:tabular-nums}
        .print-doc .hero .amt small{font-size:10pt;font-weight:600;letter-spacing:0;opacity:.85}
        .print-doc .hero .excl{font-size:8pt;opacity:.85;margin-top:3px;font-variant-numeric:tabular-nums}
        .print-doc .hero .unit{font-size:7.2pt;opacity:.78;margin-top:2px;line-height:1.35}
        .print-doc .hero .tot{margin-top:7px;padding-top:6px;border-top:1px solid rgba(255,255,255,.3);
                   font-size:8.2pt;line-height:1.45}
        .print-doc .hero .tot b{font-size:10pt;font-variant-numeric:tabular-nums}
        .print-doc .hero .dlt{font-size:7.2pt;opacity:.85;display:block;margin-top:1px}

        .print-doc table.tt{width:100%;border-collapse:collapse;font-size:8.2pt}
        .print-doc table.tt th{background:#f5f7f8;color:var(--print-muted);font-size:6.6pt;letter-spacing:.5px;
                    padding:4px 7px;text-align:right;font-weight:700;border-bottom:1px solid var(--print-line)}
        .print-doc table.tt th:first-child{text-align:left}
        .print-doc table.tt td{padding:4px 7px;border-bottom:1px solid var(--print-line2);text-align:right;
                    font-variant-numeric:tabular-nums}
        .print-doc table.tt td:first-child{text-align:left;color:var(--print-muted);font-size:7.6pt}
        .print-doc table.tt td.q{font-weight:600;color:var(--print-ink)}
        .print-doc table.tt td.p{font-weight:700}
        .print-doc table.tt tr.cur td{background:var(--print-accent-soft)}
        .print-doc table.tt tr.cur td:first-child{color:var(--print-accent);font-weight:700}
        .print-doc table.tt tr.best td.p{color:var(--print-gold)}
        .print-doc .tier-foot{padding:4px 7px;font-size:6.9pt;color:var(--print-muted);line-height:1.5;
                   border-top:1px solid var(--print-line);margin-top:auto}

        .print-doc .part{border:1px solid var(--print-line);margin-bottom:6px;page-break-inside:avoid}
        .print-doc .part-h{background:#f5f7f8;border-bottom:1px solid var(--print-line);padding:4px 8px;
                display:flex;justify-content:space-between;align-items:center;gap:8px}
        .print-doc .part-h .t{font-size:8.8pt;font-weight:700}
        .print-doc .part-h .t small{font-weight:400;color:var(--print-muted);font-size:7.6pt}
        .print-doc .part-h .tag{font-size:6.8pt;background:#fff;border:1px solid var(--print-line);
                     padding:1px 5px;border-radius:3px;color:#4b5158;font-weight:600;
                     white-space:nowrap;flex:0 0 auto}
        .print-doc .part-b{display:grid;grid-template-columns:1fr 1fr 34mm}
        .print-doc .part-col{padding:5px 8px}
        .print-doc .part-col+.part-col{border-left:1px solid var(--print-line2)}
        .print-doc .part-dl{border-left:1px solid var(--print-line2);padding:4px;display:flex;
                 flex-direction:column;align-items:center;justify-content:center;background:#fcfdfd}
        .print-doc .part-dl svg{width:100%;height:auto;max-height:26mm}
        .print-doc .dl-cap{font-size:6pt;color:var(--print-muted);margin-top:2px;text-align:center;line-height:1.3}
        .print-doc .dl-none{font-size:6.4pt;color:#aab0b6;text-align:center;line-height:1.4;padding:8px 2px}

        .print-doc table.spec{width:100%;border-collapse:collapse;font-size:7.9pt}
        .print-doc table.spec td{padding:1.6px 0;vertical-align:top}
        .print-doc table.spec td.k{color:var(--print-muted);width:42%;padding-right:5px}
        .print-doc table.spec td.v{font-weight:600}
    </style>

    <script>
    // Render dieline pakai window.TMDieline (resources/js/tmdieline.js) -- DSL
    // interpreter yang sama dipakai Inquiry New (lihat dieline-part.js), bukan
    // reimplementasi lokal. spec/dims = snapshot asli iqp_part.dieline_spec,
    // jadi crease/tuck/glue-flap di sini presisi sesuai desain, bukan tebakan
    // kotak generik dari nama part_type.
    (function () {
        var scriptEl = document.currentScript;
        var PARTDATA = @json($this->partsData);

        var renderAll = function () {
            var root = scriptEl.closest('.print-doc');

            ['dl-mini', 'dl-big'].forEach(function (cls) {
                root.querySelectorAll('.' + cls).forEach(function (el) {
                    var seq = el.getAttribute('data-part');
                    var part = PARTDATA.find(function (p) { return String(p.seq) === String(seq); });
                    if (part) {
                        window.TMDieline.render(el, part.spec, part.dims);
                    }
                });
            });
        };

        // window.TMDieline datang dari app.js (Vite, dimuat sbg <script type="module">
        // di <head> -- otomatis deferred). Skrip inline ini jalan lebih dulu saat parsing,
        // jadi tunggu module deferred selesai (DOMContentLoaded) sebelum panggil TMDieline.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', renderAll);
        } else {
            renderAll();
        }
    })();
    </script>
</x-print.document>
