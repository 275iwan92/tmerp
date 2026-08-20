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
            'parentProduct',
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

<div class="nota-wrap {{ $this->bw ? 'bw' : '' }}">
    <div class="bar">
        <button type="button" onclick="window.print()" class="pr">🖶 Cetak / Simpan PDF</button>
        <button type="button" wire:click="$set('bw', {{ $this->bw ? 'false' : 'true' }})" class="sc">{{ $this->bw ? '🎨 Versi Warna' : '🖨 Versi Hemat Tinta' }}</button>
        <a href="{{ route('inquiry.costing', ['inquiry' => $this->inquiryId]) }}" class="sc" style="text-decoration:none">→ Costing</a>
        <a href="{{ route('inquiry.hub') }}" class="sc" style="text-decoration:none">← Inquiry Hub</a>
        <span class="info">{{ $this->inquiry->inquiry_no }} · {{ $this->inquiry->parts->count() }} part · {{ $this->inquiry->tiers->count() }} jenjang qty</span>
    </div>

    {{-- ================= HALAMAN 1 ================= --}}
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
                <div class="doc-title">NOTA INQUIRY</div>
                <div class="doc-no">{{ $this->inquiry->inquiry_no }}</div>
                <div class="doc-meta">
                    {{ $this->inquiry->inquiry_date?->translatedFormat('d F Y') }}
                    @if ($this->inquiry->revision_no > 0) · Rev. {{ str_pad($this->inquiry->revision_no, 2, '0', STR_PAD_LEFT) }} @endif
                    @php
                        // Badge per tipe dokumen (inquiry_nota_new/recalc/revise.html mockup) --
                        // ketiga mockup itu TERNYATA sama persis strukturnya, beda cuma warna
                        // badge tipe (hijau/ungu/oranye) -- bukan 3 layar terpisah.
                        $typeBadgeClass = match (true) {
                            $this->inquiry->is_cancelled => 'b-cancel',
                            $this->inquiry->inquiry_type === 'RECALC' => 'b-recalc',
                            $this->inquiry->inquiry_type === 'REVISE' => 'b-revise',
                            default => 'b-new',
                        };
                    @endphp
                    <span class="badge {{ $typeBadgeClass }}">{{ $this->inquiry->is_cancelled ? 'CANCELLED' : $this->inquiry->inquiry_type }}</span>
                    @if ($this->inquiry->is_prospect) <span class="badge b-prospect">PROSPECT</span> @endif
                    <br>
                    @if ($this->inquiry->deadline_quotation) Berlaku s/d {{ $this->inquiry->deadline_quotation->translatedFormat('d F Y') }} @endif
                </div>
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <h4>KEPADA YTH.</h4>
                <div class="nm">{{ $this->inquiry->customer?->customer_name ?? $this->inquiry->prospect_name ?? '—' }}</div>
                <div class="dt">
                    {{ $this->inquiry->customer?->default_address ?? '—' }}<br>
                    @if ($this->inquiry->customer?->contact_name) UP. {{ $this->inquiry->customer->contact_name }} @endif
                    @if ($this->inquiry->customer?->phone) · {{ $this->inquiry->customer->phone }} @endif
                </div>
            </div>
            <div class="party">
                <h4>PRODUK</h4>
                <div class="nm">{{ $this->inquiry->product_draft_name ?? $this->inquiry->parentProduct?->product_name ?? '—' }}</div>
                <div class="dt">
                    {{ $this->inquiry->parts->count() }} part
                    @if ($this->inquiry->deadline_quotation) · Deadline quotation: {{ $this->inquiry->deadline_quotation->translatedFormat('d M Y') }} @endif
                </div>
            </div>
            <div class="party">
                <h4>PIC SALES</h4>
                <div class="nm">{{ $this->inquiry->pic_sales ?? '—' }}</div>
                <div class="dt">
                    Sumber: {{ $this->inquiry->source ?? '—' }}<br>
                    Mata uang: {{ $this->inquiry->costing?->currency_code ?? 'IDR' }}
                </div>
            </div>
        </div>

        {{-- ============ 1. HARGA ============ --}}
        <div class="sec">
            <div class="sec-h"><span class="n">1</span><h3>Penawaran Harga</h3>
                <span class="rule"></span>
                <span class="hint">per pcs · IDR · PPN {{ $this->inquiry->costing?->ppn_pct ?? $this->inquiry->ppn_percent ?? 11 }}%</span>
            </div>

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
                <div class="nopr">
                    <div class="t">⚠ Harga belum tersedia</div>
                    <div class="d">
                        Costing untuk inquiry ini belum dipublish oleh Direksi. Dokumen ini hanya berisi
                        spesifikasi teknis (Bagian 2) — hubungi tim Costing untuk penawaran harga resmi
                        sebelum dokumen ini dijadikan acuan negosiasi.
                    </div>
                </div>
            @endif
        </div>

        {{-- ============ 2. SPESIFIKASI ============ --}}
        <div class="sec">
            <div class="sec-h"><span class="n">2</span><h3>Spesifikasi Produk</h3>
                <span class="rule"></span>
                <span class="hint">dieline ukuran penuh di halaman 2</span>
            </div>

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
                <div class="nopr"><div class="d">Belum ada part di inquiry ini.</div></div>
            @endforelse
        </div>

        {{-- ============ 3. CATATAN ============ --}}
        <div class="sec">
            <div class="sec-h"><span class="n">3</span><h3>Catatan</h3><span class="rule"></span></div>
            <div class="two">
                <div class="box">
                    <h5>CATATAN SALES</h5>
                    {{ $this->inquiry->sales_note ?: '—' }}
                </div>
                <div class="box">
                    <h5>INFO TAMBAHAN</h5>
                    @if ($this->inquiry->design_number) No. Desain: <b>{{ $this->inquiry->design_number }}</b><br> @endif
                    @if ($this->inquiry->material_source) Sumber material: {{ $this->inquiry->material_source }}<br> @endif
                    @if ($this->inquiry->job_nature) Sifat pekerjaan: {{ $this->inquiry->job_nature }} @endif
                    @if (! $this->inquiry->design_number && ! $this->inquiry->material_source && ! $this->inquiry->job_nature) — @endif
                </div>
            </div>
        </div>

        <div class="foot">
            <span>{{ $this->inquiry->inquiry_no }} · {{ $this->inquiry->customer?->customer_name ?? $this->inquiry->prospect_name }} ·
                  dicetak {{ now()->format('d/m/Y H:i') }} · syarat &amp; konfirmasi di halaman 2</span>
            <span>Halaman 1 dari 2</span>
        </div>
    </div>

    {{-- ================= HALAMAN 2 ================= --}}
    <div class="page">

        <div class="sec" style="margin-top:0">
            <div class="sec-h"><span class="n">4</span><h3>Dieline Ukuran Penuh</h3>
                <span class="rule"></span>
                <span class="hint">skematik, tidak berskala · —— potong · - - - rill/lipat</span>
            </div>
            <div class="two">
                @foreach ($this->partsData as $p)
                    <div class="box" style="padding:9px">
                        <h5>PART {{ $p['seq'] }} — {{ strtoupper($p['name'] ?: '') }}</h5>
                        <div class="dl-big" data-part="{{ $p['seq'] }}" wire:ignore></div>
                        <div style="font-size:7.4pt;color:var(--muted);margin-top:4px">
                            Bentangan {{ number_format($p['flat_w'], 0) }} × {{ number_format($p['flat_h'], 0) }} mm
                            @if ($p['material_name']) · {{ $p['material_name'] }} @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="sec">
            <div class="sec-h"><span class="n">5</span><h3>Syarat &amp; Ketentuan</h3><span class="rule"></span></div>
            <ol class="terms">
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
        </div>

        <div class="confirm">
            <div class="confirm-h">KONFIRMASI CUSTOMER</div>
            <div class="confirm-b">
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
                <div class="sign">
                    <div><div class="line"></div><div class="rol">Customer</div>
                         <div class="sub">Nama jelas, jabatan &amp; stempel</div></div>
                    <div><div class="line"></div><div class="rol">Sales</div>
                         <div class="sub">{{ $this->inquiry->pic_sales ?? '—' }}</div></div>
                    <div><div class="line"></div><div class="rol">Mengetahui</div>
                         <div class="sub">Sales Manager</div></div>
                </div>
            </div>
        </div>

        <div class="foot">
            <span>{{ $this->inquiry->inquiry_no }} · Dokumen dibuat otomatis oleh sistem TMERP —
                  sah tanpa tanda tangan basah pihak penerbit.</span>
            <span>Halaman 2 dari 2</span>
        </div>
    </div>

    <style>
        .nota-wrap{
          --ink:#1a1d21; --muted:#6b7280; --line:#d4d8dd; --line2:#eceff2;
          --accent:#0d5c46; --accent-soft:#eaf3f0; --warn:#8a5a00; --gold:#9a7b18; --danger:#a3241f;
        }
        .nota-wrap.bw{
          --ink:#000; --muted:#555; --line:#000; --line2:#999;
          --accent:#000; --accent-soft:#fff; --warn:#000; --gold:#000; --danger:#000;
        }
        .nota-wrap{background:#8e959c;font-family:"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
             font-size:10pt;color:var(--ink);padding:24px 0;
             -webkit-print-color-adjust:exact;print-color-adjust:exact}
        .nota-wrap.bw{background:#c9ccd0}
        .nota-wrap *{box-sizing:border-box}

        .nota-wrap .page{width:210mm;min-height:297mm;margin:0 auto 18px;background:#fff;
              padding:12mm 12mm 18mm;position:relative;box-shadow:0 6px 24px rgba(0,0,0,.28)}

        .nota-wrap .bar{width:210mm;margin:0 auto 12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .nota-wrap .bar button{border:0;border-radius:5px;padding:8px 16px;font-size:9.5pt;font-weight:600;
                    cursor:pointer;font-family:inherit}
        .nota-wrap .bar .pr{background:var(--accent);color:#fff}
        .nota-wrap.bw .bar .pr{border:1px solid #333;background:#fff;color:#111}
        .nota-wrap .bar .sc{background:#fff;color:var(--ink);border:1px solid var(--line)}
        .nota-wrap .bar .info{color:#fff;font-size:8.5pt;margin-left:auto;opacity:.9}
        .nota-wrap.bw .bar .info{color:#111}

        .nota-wrap .kop{display:flex;justify-content:space-between;align-items:flex-start;
             border-bottom:2.5px solid var(--accent);padding-bottom:7px}
        .nota-wrap.bw .kop{border-bottom:2px solid var(--ink)}
        .nota-wrap .logo{width:42px;height:42px;border-radius:7px;background:var(--accent);color:#fff;
              display:flex;align-items:center;justify-content:center;font-weight:700;
              font-size:14pt;letter-spacing:-1px;flex:0 0 auto}
        .nota-wrap.bw .logo{background:none;border:2px solid var(--ink);color:var(--ink)}
        .nota-wrap .kop-l{display:flex;gap:10px;align-items:center}
        .nota-wrap .co-name{font-size:13pt;font-weight:700;letter-spacing:-.3px;line-height:1.1}
        .nota-wrap .co-addr{font-size:7.2pt;color:var(--muted);line-height:1.4;margin-top:2px}
        .nota-wrap .kop-r{text-align:right}
        .nota-wrap .doc-title{font-size:11.5pt;font-weight:700;color:var(--accent);letter-spacing:.5px}
        .nota-wrap.bw .doc-title{color:var(--ink)}
        .nota-wrap .doc-no{font-size:14pt;font-weight:700;font-family:Consolas,monospace;
                letter-spacing:-.5px;margin-top:1px}
        .nota-wrap .doc-meta{font-size:7.6pt;color:var(--muted);margin-top:2px;line-height:1.45}
        .nota-wrap .badge{display:inline-block;padding:1.5px 7px;border-radius:20px;font-size:6.8pt;
               font-weight:700;letter-spacing:.6px;vertical-align:2px;background:var(--accent-soft);color:var(--accent);border:1px solid var(--line)}
        .nota-wrap.bw .badge{border:1px solid var(--ink);background:none;color:var(--ink)}
        {{-- Warna per tipe dokumen (inquiry_nota_new/recalc/revise.html) -- .bw di atas
             tetap menang (hitam-putih) krn urutan cascade, ini hanya utk mode warna. --}}
        .nota-wrap .b-new{background:var(--accent-soft);color:var(--accent);border-color:#b8d8ce}
        .nota-wrap .b-recalc{background:#e2e0ff;color:#4b3fa8;border-color:#c4bef0}
        .nota-wrap .b-revise{background:#ffe0b2;color:#8a4b00;border-color:#ffcf8a}
        .nota-wrap .b-prospect{background:#fff5e0;color:var(--warn);border-color:#f0d9a8}
        .nota-wrap .b-cancel{background:#fdeaea;color:var(--danger);border-color:#f0bdbb}

        .nota-wrap .parties{display:grid;grid-template-columns:1.25fr 1fr .85fr;margin-top:8px;
                 border:1px solid var(--line)}
        .nota-wrap .party{padding:6px 9px}
        .nota-wrap .party+.party{border-left:1px solid var(--line)}
        .nota-wrap .party h4{font-size:6.6pt;letter-spacing:1px;color:var(--muted);font-weight:700;margin-bottom:2px}
        .nota-wrap .party .nm{font-size:10pt;font-weight:600;line-height:1.25}
        .nota-wrap .party .dt{font-size:7.6pt;color:#4b5158;line-height:1.5;margin-top:1px}
        .nota-wrap.bw .party .dt{color:#333}

        .nota-wrap .sec{margin-top:10px}
        .nota-wrap .sec-h{display:flex;align-items:center;gap:6px;margin-bottom:5px}
        .nota-wrap .sec-h .n{width:15px;height:15px;border-radius:3px;background:var(--accent);color:#fff;
                  font-size:7.6pt;font-weight:700;display:flex;align-items:center;
                  justify-content:center;flex:0 0 auto}
        .nota-wrap.bw .sec-h .n{background:none;border:1.5px solid var(--ink);color:var(--ink)}
        .nota-wrap .sec-h h3{font-size:8.6pt;font-weight:700;letter-spacing:.7px;text-transform:uppercase}
        .nota-wrap .sec-h .rule{flex:1;height:1px;background:var(--line)}
        .nota-wrap .sec-h .hint{font-size:7pt;color:var(--muted);font-weight:400;letter-spacing:0}

        .nota-wrap .pricing{display:grid;grid-template-columns:66mm 1fr;border:1.5px solid var(--accent);
                 page-break-inside:avoid}
        .nota-wrap.bw .pricing{border:1.5px solid var(--ink)}
        .nota-wrap .hero{background:var(--accent);color:#fff;padding:9px 11px;display:flex;
              flex-direction:column;justify-content:center}
        .nota-wrap.bw .hero{background:none;color:var(--ink);border-right:1.5px solid var(--ink)}
        .nota-wrap .hero .lbl{font-size:6.8pt;letter-spacing:1.2px;opacity:.82;font-weight:600}
        .nota-wrap.bw .hero .lbl{opacity:1;color:var(--muted);font-weight:700}
        .nota-wrap .hero .qty{font-size:12pt;font-weight:600;margin-top:1px;line-height:1.15}
        .nota-wrap .hero .qty span{font-size:8pt;opacity:.8;font-weight:400}
        .nota-wrap .hero .amt{font-size:24pt;font-weight:700;letter-spacing:-1.2px;line-height:1;
                   margin-top:6px;font-variant-numeric:tabular-nums}
        .nota-wrap .hero .amt small{font-size:10pt;font-weight:600;letter-spacing:0;opacity:.85}
        .nota-wrap .hero .excl{font-size:8pt;opacity:.85;margin-top:3px;font-variant-numeric:tabular-nums}
        .nota-wrap.bw .hero .excl{opacity:1;color:var(--muted)}
        .nota-wrap .hero .unit{font-size:7.2pt;opacity:.78;margin-top:2px;line-height:1.35}
        .nota-wrap .hero .tot{margin-top:7px;padding-top:6px;border-top:1px solid rgba(255,255,255,.3);
                   font-size:8.2pt;line-height:1.45}
        .nota-wrap.bw .hero .tot{border-top:1px solid var(--ink)}
        .nota-wrap .hero .tot b{font-size:10pt;font-variant-numeric:tabular-nums}
        .nota-wrap .hero .dlt{font-size:7.2pt;opacity:.85;display:block;margin-top:1px}
        .nota-wrap.bw .hero .dlt{opacity:1;color:var(--muted)}

        .nota-wrap table.tt{width:100%;border-collapse:collapse;font-size:8.2pt}
        .nota-wrap table.tt th{background:#f5f7f8;color:var(--muted);font-size:6.6pt;letter-spacing:.5px;
                    padding:4px 7px;text-align:right;font-weight:700;border-bottom:1px solid var(--line)}
        .nota-wrap.bw table.tt th{background:none}
        .nota-wrap table.tt th:first-child{text-align:left}
        .nota-wrap table.tt td{padding:4px 7px;border-bottom:1px solid var(--line2);text-align:right;
                    font-variant-numeric:tabular-nums}
        .nota-wrap table.tt td:first-child{text-align:left;color:var(--muted);font-size:7.6pt}
        .nota-wrap table.tt td.q{font-weight:600;color:var(--ink)}
        .nota-wrap table.tt td.p{font-weight:700}
        .nota-wrap table.tt tr.cur td{background:var(--accent-soft)}
        .nota-wrap.bw table.tt tr.cur td{background:none;border-top:1.5px solid var(--ink);border-bottom:1.5px solid var(--ink)}
        .nota-wrap table.tt tr.cur td:first-child{color:var(--accent);font-weight:700}
        .nota-wrap.bw table.tt tr.cur td:first-child{color:var(--ink)}
        .nota-wrap table.tt tr.best td.p{color:var(--gold)}
        .nota-wrap.bw table.tt tr.best td.p{color:var(--ink);text-decoration:underline}
        .nota-wrap .tier-foot{padding:4px 7px;font-size:6.9pt;color:var(--muted);line-height:1.5;
                   border-top:1px solid var(--line);margin-top:auto}

        .nota-wrap .nopr{border:1.5px dashed var(--warn);background:#fffbf2;padding:10px 12px;
              page-break-inside:avoid}
        .nota-wrap.bw .nopr{border:1.5px dashed var(--ink);background:none}
        .nota-wrap .nopr .t{font-size:10.5pt;font-weight:700;color:var(--warn)}
        .nota-wrap.bw .nopr .t{color:var(--ink)}
        .nota-wrap .nopr .d{font-size:8.4pt;color:#5f4a1c;line-height:1.55;margin-top:3px}
        .nota-wrap.bw .nopr .d{color:var(--ink)}

        .nota-wrap .part{border:1px solid var(--line);margin-bottom:6px;page-break-inside:avoid}
        .nota-wrap .part-h{background:#f5f7f8;border-bottom:1px solid var(--line);padding:4px 8px;
                display:flex;justify-content:space-between;align-items:center;gap:8px}
        .nota-wrap.bw .part-h{background:none}
        .nota-wrap .part-h .t{font-size:8.8pt;font-weight:700}
        .nota-wrap .part-h .t small{font-weight:400;color:var(--muted);font-size:7.6pt}
        .nota-wrap .part-h .tag{font-size:6.8pt;background:#fff;border:1px solid var(--line);
                     padding:1px 5px;border-radius:3px;color:#4b5158;font-weight:600;
                     white-space:nowrap;flex:0 0 auto}
        .nota-wrap.bw .part-h .tag{color:#333}
        .nota-wrap .part-b{display:grid;grid-template-columns:1fr 1fr 34mm}
        .nota-wrap .part-col{padding:5px 8px}
        .nota-wrap .part-col+.part-col{border-left:1px solid var(--line2)}
        .nota-wrap .part-dl{border-left:1px solid var(--line2);padding:4px;display:flex;
                 flex-direction:column;align-items:center;justify-content:center;background:#fcfdfd}
        .nota-wrap.bw .part-dl{background:none}
        .nota-wrap .part-dl svg{width:100%;height:auto;max-height:26mm}
        /* TMDieline (resources/js/tmdieline.js) pakai warna cut/crease tetap
           (merah/hijau) -- desaturate di mode hemat-tinta, jangan ubah modul
           JS bersama (dipakai juga Inquiry New yg butuh warna asli). */
        .nota-wrap.bw .part-dl svg,
        .nota-wrap.bw .dl-big svg{filter:grayscale(1)}
        .nota-wrap .dl-cap{font-size:6pt;color:var(--muted);margin-top:2px;text-align:center;line-height:1.3}
        .nota-wrap .dl-none{font-size:6.4pt;color:#aab0b6;text-align:center;line-height:1.4;padding:8px 2px}

        .nota-wrap table.spec{width:100%;border-collapse:collapse;font-size:7.9pt}
        .nota-wrap table.spec td{padding:1.6px 0;vertical-align:top}
        .nota-wrap table.spec td.k{color:var(--muted);width:42%;padding-right:5px}
        .nota-wrap table.spec td.v{font-weight:600}

        .nota-wrap .two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .nota-wrap .box{border:1px solid var(--line);padding:6px 8px;font-size:7.9pt;line-height:1.55}
        .nota-wrap .box h5{font-size:6.6pt;letter-spacing:.9px;color:var(--muted);font-weight:700;margin-bottom:3px}
        .nota-wrap ol.terms{margin-left:13px;font-size:7.9pt;line-height:1.6}
        .nota-wrap ol.terms li{margin-bottom:1px}

        .nota-wrap .confirm{border:1.5px solid var(--accent);margin-top:10px;page-break-inside:avoid}
        .nota-wrap.bw .confirm{border:1.5px solid var(--ink)}
        .nota-wrap .confirm-h{background:var(--accent);color:#fff;padding:4px 9px;font-size:7.8pt;
                   font-weight:700;letter-spacing:.8px}
        .nota-wrap.bw .confirm-h{background:none;color:var(--ink);border-bottom:1.5px solid var(--ink)}
        .nota-wrap .confirm-b{padding:7px 9px;font-size:8pt;line-height:1.55}
        .nota-wrap .sign{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:10px;text-align:center}
        .nota-wrap .sign div{font-size:7.8pt}
        .nota-wrap .sign .line{border-bottom:1px solid var(--ink);height:40px;margin-bottom:3px}
        .nota-wrap .sign .rol{font-weight:700}
        .nota-wrap .sign .sub{font-size:6.8pt;color:var(--muted)}

        .nota-wrap .foot{position:absolute;bottom:8mm;left:12mm;right:12mm;border-top:1px solid var(--line);
              padding-top:4px;font-size:6.6pt;color:var(--muted);display:flex;
              justify-content:space-between;gap:10px}

        @media print{
          .nota-wrap{background:#fff;padding:0}
          .nota-wrap .bar{display:none}
          .nota-wrap .page{box-shadow:none;margin:0;width:auto;min-height:auto;padding:11mm 11mm 16mm}
          @page{size:A4 portrait;margin:0}
        }
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
                var root = scriptEl.closest('.nota-wrap');

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
</div>
