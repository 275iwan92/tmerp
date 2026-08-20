<?php

use App\Models\Master\OrgCompany;
use App\Models\Master\SysConstant;
use App\Models\Sdi\QuoQuotation;
use App\Services\Inquiry\TierPricingResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quotation — TELASIH MAKMUR ERP')] class extends Component
{
    public int $quotationId;

    public function mount(int $quotation): void
    {
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

<div class="quo-wrap">
    <div class="bar">
        <button type="button" onclick="window.print()" class="pr">🖶 Cetak / Simpan PDF</button>
        @if ($this->quotation->doc_status === 'DRAFT')
            <a href="{{ route('quotation.entry', ['quotation' => $this->quotationId]) }}" class="sc" style="text-decoration:none">✎ Edit</a>
        @endif
        <a href="{{ route('quotation.list') }}" class="sc" style="text-decoration:none">← Daftar Quotation</a>
        <span class="info">{{ $this->quotation->quotation_no }} · {{ $this->quotation->doc_status }}</span>
    </div>

    <div class="page">

        <div class="kop">
            <div class="kop-l">
                <div class="logo">{{ strtoupper(substr($this->company?->company_code ?? 'TM', 0, 2)) }}</div>
                <div>
                    <div class="co-name">{{ $this->company?->company_name ?? 'PT Telasih Makmur' }}</div>
                    <div class="co-addr">
                        {{ $this->company?->address ?? '—' }}
                        @if ($this->company?->phone) <br>Telp {{ $this->company->phone }} @endif
                        @if ($this->company?->email) · {{ $this->company->email }} @endif
                        @if ($this->company?->npwp_number) · NPWP {{ $this->company->npwp_number }} @endif
                    </div>
                </div>
            </div>
            <div class="kop-r">
                <div class="doc-title">SURAT PENAWARAN HARGA</div>
                <div class="doc-no">{{ $this->quotation->quotation_no }}</div>
                <div class="doc-meta">
                    Tanggal Terbit: {{ $this->quotation->issued_date?->translatedFormat('d F Y') }}
                    · Rev. {{ str_pad($this->quotation->revision_no, 2, '0', STR_PAD_LEFT) }}<br>
                    Ref. Inquiry: <span class="ref">{{ $this->inquiryRefs ?: '—' }}</span>
                </div>
            </div>
        </div>

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

        <div class="parties">
            <div class="party">
                <h4>KEPADA YTH.</h4>
                <div class="nm">{{ $this->quotation->customer?->customer_name ?? '—' }}</div>
                <div class="dt">
                    Kode Customer: <b>{{ $this->quotation->customer?->customer_code ?? '—' }}</b><br>
                    @if ($this->quotation->customer?->contact_name) UP. <b>{{ $this->quotation->customer->contact_name }}</b> @endif
                    @if ($this->quotation->customer?->phone) · {{ $this->quotation->customer->phone }} @endif
                </div>
            </div>
            <div class="party">
                <h4>DARI</h4>
                <div class="nm">{{ $this->company?->company_name ?? 'PT Telasih Makmur' }}</div>
                <div class="dt">
                    PIC Sales: <b>{{ $this->quotation->issued_by ?? '—' }}</b><br>
                    Mata Uang: <b>{{ $this->quotation->currency_code }}</b> · Skema Pajak:
                    <b>{{ $this->quotation->tax_scheme === 'INCL' ? 'Inclusive' : 'Exclusive' }} PPN {{ number_format($this->totals['ppn_pct'], 0) }}%</b>
                </div>
            </div>
        </div>

        <div class="sec">
            <div class="sec-h"><span class="n">1</span><h3>Rincian Penawaran</h3>
                <span class="rule"></span>
                <span class="hint">{{ $this->quotation->tax_scheme === 'INCL' ? 'harga inclusive PPN' : 'harga exclusive PPN' }}</span>
            </div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:12%">Ref.</th>
                        <th class="desc">Deskripsi Produk &amp; Spesifikasi</th>
                        <th style="width:11%">Qty</th>
                        <th style="width:13%">Harga Satuan</th>
                        <th style="width:16%">Subtotal</th>
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
                            <td class="q">{{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}</td>
                            <td>{{ number_format($item->unit_price, 0, ',', '.') }}{{ $item->discount_pct > 0 ? ' (-'.number_format($item->discount_pct, 1).'%)' : '' }}</td>
                            <td class="amt">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
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
                    <div style="margin-top:5px;color:var(--muted)">
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
                                <th>Qty (pcs)</th>
                                <th>Harga Satuan</th>
                                <th>Subtotal Produk</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->altRows as $row)
                                <tr class="{{ $row->is_best ? 'best' : '' }}">
                                    <td>{{ number_format($row->tier->qty, 0, ',', '.') }} pcs</td>
                                    <td>{{ number_format($row->tier->qty, 0, ',', '.') }}</td>
                                    <td class="p">{{ number_format($row->price_excl, 0, ',', '.') }}</td>
                                    <td>{{ number_format($row->value_total, 0, ',', '.') }}</td>
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
        </div>

        <div class="sec">
            <div class="sec-h"><span class="n">2</span><h3>Termin &amp; Pembayaran</h3><span class="rule"></span></div>
            <div class="two">
                <div class="box">
                    <h5>TERMIN PEMBAYARAN</h5>
                    <div class="pay-row"><span class="k">Termin</span><span class="v">{{ $this->quotation->payment_term ?? '—' }}</span></div>
                    <div class="pay-row"><span class="k">Lead time produksi</span><span class="v">menyesuaikan kapasitas</span></div>
                    <div class="pay-row"><span class="k">Mulai dihitung</span><span class="v">setelah proof disetujui</span></div>
                    @php
                        $bankName = \App\Models\Master\SysConstant::text('BANK_NAME');
                        $bankAcctName = \App\Models\Master\SysConstant::text('BANK_ACCOUNT_NAME');
                        $bankAcctNo = \App\Models\Master\SysConstant::text('BANK_ACCOUNT_NO');
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
                <div class="box">
                    <h5>SYARAT &amp; KETENTUAN</h5>
                    @if ($this->quotation->notes)
                        <p style="white-space:pre-line">{{ $this->quotation->notes }}</p>
                    @else
                        <ol class="terms">
                            <li>Penawaran berlaku sampai tanggal yang tertera pada strip masa berlaku di atas.</li>
                            <li>Harga {{ $this->quotation->tax_scheme === 'INCL' ? 'inclusive' : 'exclusive' }} PPN {{ number_format($this->totals['ppn_pct'], 0) }}%.</li>
                            <li>Lead time produksi terhitung setelah artwork final &amp; digital proof (warna) disetujui.</li>
                            <li>Toleransi pengiriman ±5% dari qty order, ditagih sesuai jumlah aktual.</li>
                            <li>Penawaran ini menjadi mengikat setelah PO customer diterbitkan.</li>
                        </ol>
                    @endif
                </div>
            </div>
        </div>

        <div class="sign-wrap">
            <div class="sign-h">PENGESAHAN &amp; PERSETUJUAN</div>
            <div class="sign-b">
                <div class="sign-intro">
                    Penawaran ini diterbitkan secara resmi oleh {{ $this->company?->company_name ?? 'PT Telasih Makmur' }}.
                    Untuk melanjutkan ke pesanan produksi, mohon terbitkan Purchase Order dengan mengacu nomor quotation
                    <b>{{ $this->quotation->quotation_no }}</b>, atau tandatangani kolom persetujuan customer
                    di bawah dan kirim kembali sebelum masa berlaku berakhir.
                </div>
                <div class="sign">
                    <div class="cell">
                        <div class="co">Hormat kami, {{ $this->company?->company_name ?? 'PT Telasih Makmur' }}</div>
                        <div class="line"></div>
                        <div class="rol">Direksi</div>
                        <div class="nm">( ................................. )</div>
                        <div class="sub">Authorized signatory &amp; stempel</div>
                    </div>
                    <div class="cell">
                        <div class="co">{{ $this->company?->company_name ?? 'PT Telasih Makmur' }}</div>
                        <div class="line"></div>
                        <div class="rol">Disiapkan</div>
                        <div class="nm">{{ $this->quotation->issued_by ?? '—' }}</div>
                        <div class="sub">Sales Executive</div>
                    </div>
                    <div class="cell">
                        <div class="co">Disetujui oleh customer</div>
                        <div class="line"></div>
                        <div class="rol">Customer</div>
                        <div class="nm">( ................................. )</div>
                        <div class="sub">Nama jelas, jabatan &amp; stempel</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="foot">
            <span>{{ $this->quotation->quotation_no }} · {{ $this->quotation->customer?->customer_name }} ·
                  dicetak {{ now()->format('d/m/Y H:i') }} · dokumen sah terbit sistem TMERP</span>
            <span>Halaman 1 dari 1</span>
        </div>
    </div>

    <style>
        {{-- Palet navy/accent TMERP (bukan hijau spt Nota Inquiry) -- sesuai
             docs/mockups/mockup_sales_quotation_letter_print.html, dikoreksi 2026-08-20
             dari draf pertama yang salah pakai palet lama. --}}
        .quo-wrap{
          --navy:#2c3e69; --navy-soft:#eef1f7; --accent:#2f6fed; --accent-soft:#eaf0ff;
          --ink:#1a1d21; --muted:#6b7280; --line:#d4d8dd; --line2:#eceff2;
          --gold:#9a7b18; --danger:#a3241f; --warn:#8a5a00;
        }
        .quo-wrap{background:#8e959c;font-family:"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
             font-size:10pt;color:var(--ink);padding:24px 0;
             -webkit-print-color-adjust:exact;print-color-adjust:exact}
        .quo-wrap *{box-sizing:border-box}
        .quo-wrap .page{width:210mm;min-height:297mm;margin:0 auto 18px;background:#fff;
              padding:12mm 12mm 18mm;position:relative;box-shadow:0 6px 24px rgba(0,0,0,.28)}
        .quo-wrap .bar{width:210mm;margin:0 auto 12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .quo-wrap .bar button{border:0;border-radius:5px;padding:8px 16px;font-size:9.5pt;font-weight:600;
                    cursor:pointer;font-family:inherit}
        .quo-wrap .bar .pr{background:var(--navy);color:#fff}
        .quo-wrap .bar .sc{background:#fff;color:var(--ink);border:1px solid var(--line);padding:8px 16px;border-radius:5px;font-size:9.5pt;font-weight:600}
        .quo-wrap .bar .info{color:#fff;font-size:8.5pt;margin-left:auto;opacity:.9}

        .quo-wrap .nopr{border:1.5px dashed var(--warn);background:#fffbf2;padding:14px 16px}
        .quo-wrap .nopr .t{font-size:11pt;font-weight:700;color:var(--warn)}
        .quo-wrap .nopr .d{font-size:9pt;color:#5f4a1c;line-height:1.6;margin-top:5px}

        .quo-wrap .kop{display:flex;justify-content:space-between;align-items:flex-start;
             border-bottom:2.5px solid var(--navy);padding-bottom:7px}
        .quo-wrap .logo{width:42px;height:42px;border-radius:7px;background:var(--navy);color:#fff;
              display:flex;align-items:center;justify-content:center;font-weight:700;
              font-size:14pt;letter-spacing:-1px;flex:0 0 auto}
        .quo-wrap .kop-l{display:flex;gap:10px;align-items:center}
        .quo-wrap .co-name{font-size:13pt;font-weight:700;letter-spacing:-.3px;line-height:1.1;color:var(--navy)}
        .quo-wrap .co-addr{font-size:7.2pt;color:var(--muted);line-height:1.4;margin-top:2px}
        .quo-wrap .kop-r{text-align:right}
        .quo-wrap .doc-title{font-size:11.5pt;font-weight:700;color:var(--navy);letter-spacing:.5px}
        .quo-wrap .doc-no{font-size:14pt;font-weight:700;font-family:Consolas,monospace;color:var(--accent);
                letter-spacing:-.5px;margin-top:1px}
        .quo-wrap .doc-meta{font-size:7.6pt;color:var(--muted);margin-top:2px;line-height:1.45}
        .quo-wrap .doc-meta .ref{color:var(--navy);font-weight:600}

        .quo-wrap .valid{display:flex;align-items:stretch;margin-top:9px;border:1.5px solid var(--navy)}
        .quo-wrap .valid .v-main{flex:1;background:var(--navy);color:#fff;padding:6px 12px;
                       display:flex;align-items:center;gap:10px}
        .quo-wrap .valid .v-main .ic{font-size:15pt;line-height:1}
        .quo-wrap .valid .v-main .txt{line-height:1.25}
        .quo-wrap .valid .v-main .txt b{font-size:11pt;font-weight:700}
        .quo-wrap .valid .v-main .txt small{display:block;font-size:6.8pt;letter-spacing:.6px;
                                  opacity:.82;font-weight:600;text-transform:uppercase}
        .quo-wrap .valid .v-side{flex:0 0 auto;padding:6px 12px;display:flex;flex-direction:column;
                       justify-content:center;text-align:right;background:var(--navy-soft)}
        .quo-wrap .valid .v-side .k{font-size:6.6pt;letter-spacing:.6px;color:var(--navy);
                          font-weight:700;text-transform:uppercase}
        .quo-wrap .valid .v-side .d{font-size:9pt;font-weight:600;color:var(--ink)}

        .quo-wrap .parties{display:grid;grid-template-columns:1.2fr 1fr;margin-top:9px;border:1px solid var(--line)}
        .quo-wrap .party{padding:6px 9px}
        .quo-wrap .party+.party{border-left:1px solid var(--line)}
        .quo-wrap .party h4{font-size:6.6pt;letter-spacing:1px;color:var(--muted);font-weight:700;margin-bottom:2px}
        .quo-wrap .party .nm{font-size:10pt;font-weight:600;line-height:1.25}
        .quo-wrap .party .dt{font-size:7.6pt;color:#4b5158;line-height:1.55;margin-top:2px}
        .quo-wrap .party .dt b{color:var(--ink)}

        .quo-wrap .sec{margin-top:10px}
        .quo-wrap .sec-h{display:flex;align-items:center;gap:6px;margin-bottom:5px}
        .quo-wrap .sec-h .n{width:15px;height:15px;border-radius:3px;background:var(--navy);color:#fff;
                  font-size:7.6pt;font-weight:700;display:flex;align-items:center;
                  justify-content:center;flex:0 0 auto}
        .quo-wrap .sec-h h3{font-size:8.6pt;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:var(--navy)}
        .quo-wrap .sec-h .rule{flex:1;height:1px;background:var(--line)}
        .quo-wrap .sec-h .hint{font-size:7pt;color:var(--muted);font-weight:400;letter-spacing:0}

        .quo-wrap table.items{width:100%;border-collapse:collapse;font-size:8.4pt;border:1px solid var(--line)}
        .quo-wrap table.items th{background:var(--navy);color:#fff;font-size:6.8pt;letter-spacing:.5px;
                       padding:5px 8px;text-align:right;font-weight:700}
        .quo-wrap table.items th:first-child{text-align:left}
        .quo-wrap table.items th.desc{text-align:left;width:40%}
        .quo-wrap table.items td{padding:6px 8px;border-bottom:1px solid var(--line2);text-align:right;
                       vertical-align:top;font-variant-numeric:tabular-nums}
        .quo-wrap table.items td.desc{text-align:left}
        .quo-wrap table.items td.desc .nm{font-weight:700;font-size:8.8pt}
        .quo-wrap table.items td.desc .sp{color:var(--muted);font-size:7.4pt;line-height:1.5;margin-top:2px}
        .quo-wrap table.items td.ref{font-family:Consolas,monospace;font-weight:700;color:var(--accent);font-size:7.8pt;text-align:left}
        .quo-wrap table.items td.q{font-weight:600}
        .quo-wrap table.items td.amt{font-weight:700}
        .quo-wrap table.items tr.main td{background:var(--accent-soft)}

        .quo-wrap .totwrap{display:grid;grid-template-columns:1fr 78mm;gap:10px;margin-top:8px}
        .quo-wrap .tot-note{border:1px solid var(--line);padding:7px 9px;font-size:7.6pt;line-height:1.55}
        .quo-wrap .tot-note h5{font-size:6.6pt;letter-spacing:.9px;color:var(--muted);font-weight:700;margin-bottom:3px}
        .quo-wrap .tot-note .ppn{display:inline-block;margin-top:3px;padding:1.5px 7px;border-radius:3px;
                       background:var(--accent-soft);color:var(--navy);font-weight:700;font-size:7.4pt}
        .quo-wrap table.tot{width:100%;border-collapse:collapse;font-size:8.6pt}
        .quo-wrap table.tot td{padding:4px 9px;font-variant-numeric:tabular-nums}
        .quo-wrap table.tot td.k{color:var(--muted);text-align:left}
        .quo-wrap table.tot td.v{text-align:right;font-weight:600}
        .quo-wrap table.tot tr.sub td{border-bottom:1px solid var(--line2)}
        .quo-wrap table.tot tr.grand td{background:var(--navy);color:#fff;font-weight:700;font-size:11pt;
                              padding:7px 9px}
        .quo-wrap table.tot tr.grand td.k{color:#fff;letter-spacing:.5px}

        .quo-wrap .alt{margin-top:8px;border:1px dashed var(--line);padding:6px 9px}
        .quo-wrap .alt h5{font-size:6.6pt;letter-spacing:.9px;color:var(--muted);font-weight:700;margin-bottom:4px}
        .quo-wrap table.alt-t{width:100%;border-collapse:collapse;font-size:8pt}
        .quo-wrap table.alt-t th{color:var(--muted);font-size:6.6pt;letter-spacing:.4px;padding:3px 7px;
                       text-align:right;font-weight:700;border-bottom:1px solid var(--line)}
        .quo-wrap table.alt-t th:first-child{text-align:left}
        .quo-wrap table.alt-t td{padding:3px 7px;text-align:right;border-bottom:1px solid var(--line2);
                       font-variant-numeric:tabular-nums}
        .quo-wrap table.alt-t td:first-child{text-align:left;color:var(--muted)}
        .quo-wrap table.alt-t td.p{font-weight:700}
        .quo-wrap table.alt-t tr.best td.p{color:var(--gold)}
        .quo-wrap .alt .cap{font-size:6.9pt;color:var(--muted);margin-top:4px;line-height:1.5}

        .quo-wrap .two{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:2px}
        .quo-wrap .box{border:1px solid var(--line);padding:6px 9px;font-size:7.7pt;line-height:1.6}
        .quo-wrap .box h5{font-size:6.6pt;letter-spacing:.9px;color:var(--muted);font-weight:700;margin-bottom:4px}
        .quo-wrap ol.terms{margin-left:13px;font-size:7.7pt;line-height:1.65}
        .quo-wrap ol.terms li{margin-bottom:1.5px}
        .quo-wrap .pay-row{display:flex;justify-content:space-between;gap:8px;padding:2px 0;
                 border-bottom:1px dotted var(--line2)}
        .quo-wrap .pay-row:last-child{border-bottom:0}
        .quo-wrap .pay-row .k{color:var(--muted)}
        .quo-wrap .pay-row .v{font-weight:600;text-align:right}
        .quo-wrap .bank{margin-top:5px;padding:5px 7px;background:var(--navy-soft);border-radius:4px;
              font-size:7.6pt;line-height:1.6}
        .quo-wrap .bank b{font-size:8.4pt;color:var(--navy)}

        .quo-wrap .sign-wrap{margin-top:10px;border:1.5px solid var(--navy);page-break-inside:avoid}
        .quo-wrap .sign-h{background:var(--navy);color:#fff;padding:4px 9px;font-size:7.8pt;
                font-weight:700;letter-spacing:.8px}
        .quo-wrap .sign-b{padding:9px}
        .quo-wrap .sign-intro{font-size:7.8pt;line-height:1.55;color:#4b5158;margin-bottom:8px}
        .quo-wrap .sign{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;text-align:center}
        .quo-wrap .sign .cell .rol{font-size:7.4pt;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--navy)}
        .quo-wrap .sign .cell .co{font-size:6.8pt;color:var(--muted);margin-bottom:2px}
        .quo-wrap .sign .cell .line{border-bottom:1px solid var(--ink);height:44px;margin-bottom:3px}
        .quo-wrap .sign .cell .nm{font-size:7.8pt;font-weight:600}
        .quo-wrap .sign .cell .sub{font-size:6.6pt;color:var(--muted)}

        .quo-wrap .foot{position:absolute;bottom:8mm;left:12mm;right:12mm;border-top:1px solid var(--line);
              padding-top:4px;font-size:6.6pt;color:var(--muted);display:flex;
              justify-content:space-between;gap:10px}

        @media print{
          .quo-wrap{background:#fff;padding:0}
          .quo-wrap .bar{display:none}
          .quo-wrap .page{box-shadow:none;margin:0;width:auto;min-height:auto;padding:11mm 11mm 16mm}
          @page{size:A4 portrait;margin:0}
        }
    </style>
</div>
