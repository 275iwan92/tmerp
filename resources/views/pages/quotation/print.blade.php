<?php

use App\Models\Master\OrgCompany;
use App\Models\Sys\SysConstant;
use App\Models\Sdi\QuoQuotation;
use App\Services\Inquiry\TierPricingResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quotation — TELASIH MAKMUR ERP')] class extends Component
{
    public int $quotationId;

    // Cetak (Nota/Quotation Letter) cuma boleh setelah quotation diterbitkan
    // (PUBLISHED) -- instruksi user 2026-08-24: draft belum final, jangan bisa
    // dicetak dulu. Diblok di sini (bukan cuma sembunyikan tombol di entry)
    // supaya akses langsung lewat URL pun ikut ketutup.
    public function mount(int $quotation): void
    {
        $doc = QuoQuotation::findOrFail($quotation);

        if ($doc->doc_status !== 'PUBLISHED') {
            session()->flash('error', 'Quotation ini belum diterbitkan — terbitkan (Publish) dulu sebelum bisa dicetak.');
            $this->redirect(route('quotation.entry', ['quotation' => $quotation]));

            return;
        }

        $this->quotationId = $quotation;
    }

    #[Computed]
    public function quotation(): QuoQuotation
    {
        return QuoQuotation::with(['customer', 'items.inquiry'])->findOrFail($this->quotationId);
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('company_code', 'TM')->first();
    }

    #[Computed]
    public function totals(): array
    {
        return $this->quotation->totals();
    }

    #[Computed]
    public function inquiryRefs(): string
    {
        return $this->quotation->items->pluck('inquiry.inquiry_no')->filter()->unique()->implode(', ');
    }

    // Alternatif jenjang qty (info) -- diambil dari inquiry produk UTAMA (baris
    // INQUIRY_TIER pertama), sesuai mockup_sales_quotation_letter_print.html §Alt Tier.
    // Kalau quotation ini gabungan >1 produk, cuma produk pertama yang ditampilkan --
    // panel ini murni informasi tambahan, bukan bagian harga resmi.
    #[Computed]
    public function altRows()
    {
        $mainItem = $this->quotation->items->firstWhere('source_type', 'INQUIRY_TIER');

        if (! $mainItem || ! $mainItem->inquiry) {
            return collect();
        }

        $inquiry = $mainItem->inquiry->loadMissing(['tiers', 'costing.priceTiers']);
        $priced = (bool) ($inquiry->costing?->published_flag) && $inquiry->costing->priceTiers->isNotEmpty();

        return TierPricingResolver::rows($inquiry, $priced)
            ->reject(fn ($r) => (float) $r->tier->qty === (float) $mainItem->qty)
            ->filter(fn ($r) => $r->price_excl !== null)
            ->values();
    }
};
?>

{{-- Kerangka umum (bar cetak, page A4, kop, parties, section bernomor, tanda
     tangan, footer) datang dari resources/views/components/print/* --
     docs/printnotastandard.md. Palet navy/accent TMERP di sini (bukan hijau
     spt Nota Inquiry) sesuai docs/mockups/mockup_sales_quotation_letter_print.html. --}}
<x-print.document :theme="['accent' => '#2c3e69', 'accent-soft' => '#eef1f7']" info="{{ $this->quotation->quotation_no }} · {{ $this->quotation->doc_status }}">
    <x-slot:toolbar>
        <a href="{{ route('quotation.list') }}" class="sc">← Daftar Quotation</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="SURAT PENAWARAN HARGA" :doc-no="$this->quotation->quotation_no">
            <x-slot:meta>
                Tanggal Terbit: {{ $this->quotation->issued_date?->translatedFormat('d F Y') }}
                · Rev. {{ str_pad($this->quotation->revision_no, 2, '0', STR_PAD_LEFT) }}<br>
                Ref. Inquiry: <span class="ref">{{ $this->inquiryRefs ?: '—' }}</span>
            </x-slot:meta>
        </x-print.header>

        <div class="valid">
            <div class="v-main">
                <span class="ic">⏰</span>
                <span class="txt">
                    <small>Masa Berlaku Penawaran</small>
                    <b>Berlaku sampai dengan {{ $this->quotation->valid_until?->translatedFormat('d F Y') }}</b>
                </span>
            </div>
            <div class="v-side">
                <span class="k">Termin Pembayaran</span>
                <span class="d">{{ $this->quotation->payment_term ?? '—' }}</span>
            </div>
        </div>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party label="KEPADA YTH.">
                <div class="nm">{{ $this->quotation->customer?->customer_name ?? '—' }}</div>
                <div class="dt">
                    Kode Customer: <b>{{ $this->quotation->customer?->customer_code ?? '—' }}</b><br>
                    @if ($this->quotation->customer?->contact_name) UP. <b>{{ $this->quotation->customer->contact_name }}</b> @endif
                    @if ($this->quotation->customer?->phone) · {{ $this->quotation->customer->phone }} @endif
                </div>
            </x-print.party>
            <x-print.party label="DARI">
                <div class="nm">{{ \App\Support\Branding\Brand::companyName() }}</div>
                <div class="dt">
                    PIC Sales: <b>{{ $this->quotation->issued_by ?? '—' }}</b><br>
                    Mata Uang: <b>{{ $this->quotation->currency_code }}</b> · Skema Pajak:
                    <b>{{ $this->quotation->tax_scheme === 'INCL' ? 'Inclusive' : 'Exclusive' }} PPN {{ number_format($this->totals['ppn_pct'], 0) }}%</b>
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Rincian Penawaran" :hint="$this->quotation->tax_scheme === 'INCL' ? 'harga inclusive PPN' : 'harga exclusive PPN'">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:12%">Ref.</th>
                        <th class="desc">Deskripsi Produk &amp; Spesifikasi</th>
                        <th class="num" style="width:11%">Qty</th>
                        <th class="num" style="width:13%">Harga Satuan</th>
                        <th class="num" style="width:16%">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->quotation->items as $item)
                        <tr class="{{ $item->source_type === 'INQUIRY_TIER' ? 'main' : '' }}">
                            <td class="ref">{{ $item->inquiry?->inquiry_no ?? '—' }}</td>
                            <td class="desc">
                                <div class="nm">{{ $item->description }}</div>
                                @if ($item->spec_note)
                                    <div class="sp">{{ $item->spec_note }}</div>
                                @endif
                            </td>
                            <td class="num q">{{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}</td>
                            <td class="num">{{ number_format($item->unit_price, 0, ',', '.') }}{{ $item->discount_pct > 0 ? ' (-'.number_format($item->discount_pct, 1).'%)' : '' }}</td>
                            <td class="num amt">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totwrap">
                <div class="tot-note">
                    <h5>KETERANGAN HARGA</h5>
                    Harga di atas <b>{{ $this->quotation->tax_scheme === 'INCL' ? 'sudah termasuk' : 'belum termasuk' }} PPN</b>
                    ({{ $this->quotation->tax_scheme === 'INCL' ? 'inclusive' : 'exclusive' }} PPN {{ number_format($this->totals['ppn_pct'], 0) }}%).
                    <br><span class="ppn">Harga {{ $this->quotation->tax_scheme === 'INCL' ? 'INCLUSIVE' : 'EXCLUSIVE' }} PPN</span>
                    <div style="margin-top:5px;color:var(--print-muted)">
                        Toleransi kuantitas ±5% (standar industri percetakan). Kelebihan/kekurangan dalam
                        rentang toleransi ditagihkan sesuai jumlah aktual terkirim.
                    </div>
                </div>
                <table class="tot">
                    <tr class="sub"><td class="k">Subtotal</td><td class="v">{{ number_format($this->totals['subtotal'], 0, ',', '.') }}</td></tr>
                    <tr class="sub"><td class="k">Dasar Pengenaan Pajak (DPP)</td><td class="v">{{ number_format($this->totals['dpp'], 0, ',', '.') }}</td></tr>
                    <tr class="sub"><td class="k">PPN {{ number_format($this->totals['ppn_pct'], 0) }}%</td><td class="v">{{ number_format($this->totals['ppn'], 0, ',', '.') }}</td></tr>
                    <tr class="grand"><td class="k">GRAND TOTAL</td><td class="v">Rp {{ number_format($this->totals['grand'], 0, ',', '.') }}</td></tr>
                </table>
            </div>

            @if ($this->altRows->isNotEmpty())
                <div class="alt">
                    <h5>ALTERNATIF KUANTITAS (harga satuan excl. PPN, di luar tooling — produk utama)</h5>
                    <table class="alt-t">
                        <thead>
                            <tr>
                                <th style="width:34%">Opsi Qty</th>
                                <th class="num">Qty (pcs)</th>
                                <th class="num">Harga Satuan</th>
                                <th class="num">Subtotal Produk</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->altRows as $row)
                                <tr class="{{ $row->is_best ? 'best' : '' }}">
                                    <td>{{ number_format($row->tier->qty, 0, ',', '.') }} pcs</td>
                                    <td class="num">{{ number_format($row->tier->qty, 0, ',', '.') }}</td>
                                    <td class="num p">{{ number_format($row->price_excl, 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format($row->value_total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="cap">
                        Opsi kuantitas lain untuk produk utama penawaran ini. Biaya tooling tetap dibebankan satu kali,
                        tidak berubah antar opsi. Untuk memilih opsi lain, hubungi PIC Sales sebelum masa berlaku berakhir.
                    </div>
                </div>
            @endif
        </x-print.section>

        <x-print.section number="2" title="Termin &amp; Pembayaran">
            <div class="print-two">
                <div class="print-box">
                    <h5>TERMIN PEMBAYARAN</h5>
                    <div class="pay-row"><span class="k">Termin</span><span class="v">{{ $this->quotation->payment_term ?? '—' }}</span></div>
                    <div class="pay-row"><span class="k">Lead time produksi</span><span class="v">menyesuaikan kapasitas</span></div>
                    <div class="pay-row"><span class="k">Mulai dihitung</span><span class="v">setelah proof disetujui</span></div>
                    @php
                        $bankName = \App\Models\Sys\SysConstant::text('BANK_NAME');
                        $bankAcctName = \App\Models\Sys\SysConstant::text('BANK_ACCOUNT_NAME');
                        $bankAcctNo = \App\Models\Sys\SysConstant::text('BANK_ACCOUNT_NO');
                    @endphp
                    <div class="bank">
                        @if ($bankName || $bankAcctNo)
                            <b>Transfer ke:</b><br>
                            {{ $bankName ?? '—' }} @if ($bankAcctName) · a.n. {{ $bankAcctName }} @endif<br>
                            No. Rek. <b>{{ $bankAcctNo ?? '—' }}</b> · Pembayaran dianggap sah setelah dana efektif diterima.
                        @else
                            Instruksi pembayaran (rekening tujuan) akan dikirim terpisah oleh tim Finance
                            setelah PO diterbitkan.
                        @endif
                    </div>
                </div>
                <div class="print-box">
                    <h5>SYARAT &amp; KETENTUAN</h5>
                    @if ($this->quotation->notes)
                        <p style="white-space:pre-line">{{ $this->quotation->notes }}</p>
                    @else
                        <ol class="print-terms">
                            <li>Penawaran berlaku sampai tanggal yang tertera pada strip masa berlaku di atas.</li>
                            <li>Harga {{ $this->quotation->tax_scheme === 'INCL' ? 'inclusive' : 'exclusive' }} PPN {{ number_format($this->totals['ppn_pct'], 0) }}%.</li>
                            <li>Lead time produksi terhitung setelah artwork final &amp; digital proof (warna) disetujui.</li>
                            <li>Toleransi pengiriman ±5% dari qty order, ditagih sesuai jumlah aktual.</li>
                            <li>Penawaran ini menjadi mengikat setelah PO customer diterbitkan.</li>
                        </ol>
                    @endif
                </div>
            </div>
        </x-print.section>

        <x-print.signatures>
            <x-slot:intro>
                Penawaran ini diterbitkan secara resmi oleh {{ \App\Support\Branding\Brand::companyName() }}.
                Untuk melanjutkan ke pesanan produksi, mohon terbitkan Purchase Order dengan mengacu nomor quotation
                <b>{{ $this->quotation->quotation_no }}</b>, atau tandatangani kolom persetujuan customer
                di bawah dan kirim kembali sebelum masa berlaku berakhir.
            </x-slot:intro>

            <x-print.signature company="Hormat kami, {{ \App\Support\Branding\Brand::companyName() }}"
                role="Direksi" name="( ................................. )" sub="Authorized signatory &amp; stempel" />
            <x-print.signature company="{{ \App\Support\Branding\Brand::companyName() }}"
                role="Disiapkan" name="{{ $this->quotation->issued_by ?? '—' }}" sub="Sales Executive" />
            <x-print.signature company="Disetujui oleh customer" role="Customer"
                name="( ................................. )" sub="Nama jelas, jabatan &amp; stempel" />
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">
            {{ $this->quotation->quotation_no }} · {{ $this->quotation->customer?->customer_name }} ·
            dicetak {{ now()->format('d/m/Y H:i') }} · dokumen sah terbit sistem TMERP
        </x-print.footer>
    </x-print.sheet>

    <style>
        {{-- Hanya style KONTEN spesifik quotation -- kerangka (bar/page/kop/
             parties/sec-h/sign/foot) sudah dari resources/views/components/print/*. --}}
        .print-doc .nopr{border:1.5px dashed var(--print-warn);background:#fffbf2;padding:14px 16px}
        .print-doc .nopr .t{font-size:11pt;font-weight:700;color:var(--print-warn)}
        .print-doc .nopr .d{font-size:9pt;color:#5f4a1c;line-height:1.6;margin-top:5px}

        .print-doc .valid{display:flex;align-items:stretch;margin-top:9px;border:1.5px solid var(--print-accent)}
        .print-doc .valid .v-main{flex:1;background:var(--print-accent);color:#fff;padding:6px 12px;
                       display:flex;align-items:center;gap:10px}
        .print-doc .valid .v-main .ic{font-size:15pt;line-height:1}
        .print-doc .valid .v-main .txt{line-height:1.25}
        .print-doc .valid .v-main .txt b{font-size:11pt;font-weight:700}
        .print-doc .valid .v-main .txt small{display:block;font-size:6.8pt;letter-spacing:.6px;
                                  opacity:.82;font-weight:600;text-transform:uppercase}
        .print-doc .valid .v-side{flex:0 0 auto;padding:6px 12px;display:flex;flex-direction:column;
                       justify-content:center;text-align:right;background:var(--print-accent-soft)}
        .print-doc .valid .v-side .k{font-size:6.6pt;letter-spacing:.6px;color:var(--print-accent);
                          font-weight:700;text-transform:uppercase}
        .print-doc .valid .v-side .d{font-size:9pt;font-weight:600;color:var(--print-ink)}

        .print-doc .print-kop .doc-meta .ref{color:var(--print-accent);font-weight:600}

        .print-doc table.items{width:100%;border-collapse:collapse;font-size:8.4pt;border:1px solid var(--print-line)}
        .print-doc table.items th{background:var(--print-accent);color:#fff;font-size:6.8pt;letter-spacing:.5px;
                       padding:5px 8px;text-align:right;font-weight:700}
        .print-doc table.items th:first-child{text-align:left}
        .print-doc table.items th.desc{text-align:left;width:40%}
        .print-doc table.items td{padding:6px 8px;border-bottom:1px solid var(--print-line2);text-align:right;
                       vertical-align:top;font-variant-numeric:tabular-nums}
        .print-doc table.items td.desc{text-align:left}
        .print-doc table.items td.desc .nm{font-weight:700;font-size:8.8pt}
        .print-doc table.items td.desc .sp{color:var(--print-muted);font-size:7.4pt;line-height:1.5;margin-top:2px}
        .print-doc table.items td.ref{font-family:Consolas,monospace;font-weight:700;color:var(--print-accent);font-size:7.8pt;text-align:left}
        .print-doc table.items td.q{font-weight:600}
        .print-doc table.items td.amt{font-weight:700}
        .print-doc table.items tr.main td{background:var(--print-accent-soft)}

        .print-doc .totwrap{display:grid;grid-template-columns:1fr 78mm;gap:10px;margin-top:8px}
        .print-doc .tot-note{border:1px solid var(--print-line);padding:7px 9px;font-size:7.6pt;line-height:1.55}
        .print-doc .tot-note h5{font-size:6.6pt;letter-spacing:.9px;color:var(--print-muted);font-weight:700;margin-bottom:3px}
        .print-doc .tot-note .ppn{display:inline-block;margin-top:3px;padding:1.5px 7px;border-radius:3px;
                       background:var(--print-accent-soft);color:var(--print-accent);font-weight:700;font-size:7.4pt}
        .print-doc table.tot{width:100%;border-collapse:collapse;font-size:8.6pt}
        .print-doc table.tot td{padding:4px 9px;font-variant-numeric:tabular-nums}
        .print-doc table.tot td.k{color:var(--print-muted);text-align:left}
        .print-doc table.tot td.v{text-align:right;font-weight:600}
        .print-doc table.tot tr.sub td{border-bottom:1px solid var(--print-line2)}
        .print-doc table.tot tr.grand td{background:var(--print-accent);color:#fff;font-weight:700;font-size:11pt;
                              padding:7px 9px}
        .print-doc table.tot tr.grand td.k{color:#fff;letter-spacing:.5px}

        .print-doc .alt{margin-top:8px;border:1px dashed var(--print-line);padding:6px 9px}
        .print-doc .alt h5{font-size:6.6pt;letter-spacing:.9px;color:var(--print-muted);font-weight:700;margin-bottom:4px}
        .print-doc table.alt-t{width:100%;border-collapse:collapse;font-size:8pt}
        .print-doc table.alt-t th{color:var(--print-muted);font-size:6.6pt;letter-spacing:.4px;padding:3px 7px;
                       text-align:right;font-weight:700;border-bottom:1px solid var(--print-line)}
        .print-doc table.alt-t th:first-child{text-align:left}
        .print-doc table.alt-t td{padding:3px 7px;text-align:right;border-bottom:1px solid var(--print-line2);
                       font-variant-numeric:tabular-nums}
        .print-doc table.alt-t td:first-child{text-align:left;color:var(--print-muted)}
        .print-doc table.alt-t td.p{font-weight:700}
        .print-doc table.alt-t tr.best td.p{color:var(--print-gold)}
        .print-doc .alt .cap{font-size:6.9pt;color:var(--print-muted);margin-top:4px;line-height:1.5}

        .print-doc .pay-row{display:flex;justify-content:space-between;gap:8px;padding:2px 0;
                 border-bottom:1px dotted var(--print-line2)}
        .print-doc .pay-row:last-child{border-bottom:0}
        .print-doc .pay-row .k{color:var(--print-muted)}
        .print-doc .pay-row .v{font-weight:600;text-align:right}
        .print-doc .bank{margin-top:5px;padding:5px 7px;background:var(--print-accent-soft);border-radius:4px;
              font-size:7.6pt;line-height:1.6}
        .print-doc .bank b{font-size:8.4pt;color:var(--print-accent)}
    </style>
</x-print.document>
