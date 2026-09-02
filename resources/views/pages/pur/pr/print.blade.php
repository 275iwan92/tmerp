<?php

use App\Models\Master\OrgCompany;
use App\Models\Pur\PrRequisition;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/BACKLOG.md §12.7 "print PR/PO/PQ" -- PR = dokumen INTERNAL (departemen
// peminta -> approval), beda dari PO (eksternal ke supplier). Pola cetak sama
// (resources/views/components/print/*), gate: APPROVED ke atas.
new #[Title('Purchase Requisition — TELASIH MAKMUR ERP')] class extends Component
{
    public int $prId;

    public function mount(int $pr): void
    {
        $doc = PrRequisition::findOrFail($pr);

        if (in_array($doc->status, ['DRAFT', 'SUBMITTED'], true)) {
            session()->flash('error', 'PR ini belum di-approve — approve dulu sebelum bisa dicetak.');
            $this->redirect(route('pur.pr.entry', ['pr' => $pr]));

            return;
        }

        $this->prId = $pr;
    }

    #[Computed]
    public function pr(): PrRequisition
    {
        return PrRequisition::with(['lines' => fn ($q) => $q->orderBy('line_no'), 'department'])->findOrFail($this->prId);
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('activate', true)->first();
    }
};
?>

<x-print.document :theme="['accent' => '#2c3e69', 'accent-soft' => '#eef1f7']" info="{{ $this->pr->doc_no }} · {{ $this->pr->status }}">
    <x-slot:toolbar>
        <a href="{{ route('pur.pr.entry', ['pr' => $this->prId]) }}" class="sc">← Kembali ke PR</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="PURCHASE REQUISITION" :doc-no="$this->pr->doc_no">
            <x-slot:meta>
                Tanggal: {{ $this->pr->i_doc_date?->translatedFormat('d F Y') }}
                · Dibutuhkan: <span class="ref">{{ $this->pr->i_need_date?->translatedFormat('d F Y') ?? '—' }}</span>
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1fr 1fr">
            <x-print.party label="DEPARTEMEN PEMINTA">
                <div class="nm">{{ $this->pr->department?->department_name ?? '—' }}</div>
                <div class="dt">Diajukan oleh: <b>{{ $this->pr->created_by ?? '—' }}</b></div>
            </x-print.party>
            <x-print.party label="SUMBER">
                <div class="dt">
                    Asal: <b>{{ $this->pr->i_source ?? 'Manual' }}</b><br>
                    Estimasi Total: <b>Rp {{ number_format($this->pr->c_est_total, 0, ',', '.') }}</b>
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Rincian Permintaan">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:6%">No.</th>
                        <th class="desc">Deskripsi</th>
                        <th style="width:13%">Qty</th>
                        <th style="width:15%">Est. Harga</th>
                        <th style="width:16%">Est. Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pr->lines as $line)
                        <tr>
                            <td class="ref">{{ $line->line_no }}</td>
                            <td class="desc">
                                <div class="nm">{{ $line->i_description }}</div>
                                @if ($line->c_item_code)
                                    <div class="sp">Kode: {{ $line->c_item_code }}</div>
                                @endif
                                @if ($line->i_notes)
                                    <div class="sp">{{ $line->i_notes }}</div>
                                @endif
                            </td>
                            <td class="q">{{ number_format($line->i_qty, 2, ',', '.') }} {{ $line->i_uom }}</td>
                            <td>{{ number_format($line->i_est_price, 2, ',', '.') }}</td>
                            <td class="amt">{{ number_format($line->c_est_subtotal, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($this->pr->i_notes)
                <div class="totwrap" style="grid-template-columns:1fr">
                    <div class="tot-note">
                        <h5>CATATAN</h5>
                        {{ $this->pr->i_notes }}
                    </div>
                </div>
            @endif
        </x-print.section>

        <x-print.signatures>
            <x-print.signature company="Diajukan oleh" role="{{ $this->pr->department?->department_name ?? 'Departemen' }}"
                name="{{ $this->pr->created_by ?? '—' }}" sub="Requester" />
            <x-print.signature company="{{ $this->company?->company_name ?? 'PT Telasih Makmur' }}"
                role="Approval" name="( ................................. )" sub="Kepala Purchasing/Direksi" />
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">
            {{ $this->pr->doc_no }} · {{ $this->pr->department?->department_name }} ·
            dicetak {{ now()->format('d/m/Y H:i') }} · dokumen sah terbit sistem TMERP
        </x-print.footer>
    </x-print.sheet>
</x-print.document>
