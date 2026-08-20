<?php

namespace App\Services\Sales;

use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationItem;
use App\Services\Inquiry\TierPricingResolver;
use Illuminate\Support\Facades\Auth;

// Logika "tambah inquiry ke quotation" dipakai dari 2 tempat: tombol
// "+ Tambahkan ke Quotation" di Costing, dan "+ Tarik Inquiry Lain" di
// Quotation Entry (docs/mockups/mockup_sales_quotation_entry.html) -- satu
// sumber kebenaran supaya perilaku (auto-tambah baris tooling, dst) konsisten.
class QuotationBuilder
{
    public static function findOrCreateDraft(int $customerId): QuoQuotation
    {
        $draft = QuoQuotation::where('customer_id', $customerId)->where('doc_status', 'DRAFT')->latest('id_quotation')->first();

        if ($draft) {
            return $draft;
        }

        return QuoQuotation::create([
            'quotation_no' => QuoQuotation::nextQuotationNo(),
            'customer_id' => $customerId,
            'revision_no' => 0,
            'issued_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'tax_scheme' => 'EXCL',
            'currency_code' => 'IDR',
            'doc_status' => 'DRAFT',
            'issued_by' => Auth::user()->login,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);
    }

    // Idempotent -- kalau inquiry ini sudah ada sbg INQUIRY_TIER, tidak dobel.
    public static function addInquiry(QuoQuotation $quotation, IqpInquiry $inquiry): ?QuoQuotationItem
    {
        $alreadyAdded = QuoQuotationItem::where('quotation_id', $quotation->id_quotation)
            ->where('inquiry_id', $inquiry->id_inquiry)
            ->where('source_type', 'INQUIRY_TIER')
            ->exists();

        if ($alreadyAdded) {
            return null;
        }

        $inquiry->loadMissing(['costing.priceTiers', 'tiers', 'parts.materials', 'parts.processes']);

        $priced = (bool) ($inquiry->costing?->published_flag) && $inquiry->costing->priceTiers->isNotEmpty();
        $rows = TierPricingResolver::rows($inquiry, $priced);
        $primary = TierPricingResolver::primary($rows);

        if (! $primary || $primary->price_excl === null) {
            return null;
        }

        $nextSeq = ((int) QuoQuotationItem::where('quotation_id', $quotation->id_quotation)->max('line_seq')) + 1;
        $login = Auth::user()->login;

        $item = QuoQuotationItem::create([
            'quotation_id' => $quotation->id_quotation,
            'line_seq' => $nextSeq,
            'source_type' => 'INQUIRY_TIER',
            'inquiry_id' => $inquiry->id_inquiry,
            'tier_id' => $primary->tier->id_tier,
            'description' => $inquiry->product_draft_name ?: ('Inquiry '.$inquiry->inquiry_no),
            'spec_note' => static::specNote($inquiry),
            'qty' => $primary->tier->qty,
            'qty_unit' => 'Pcs',
            'unit_price' => $primary->price_excl,
            'discount_pct' => 0,
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $item->recalcSubtotal();
        $item->save();

        static::addToolingLines($quotation, $inquiry, $nextSeq + 1);

        return $item;
    }

    // Pecah tooling per jenis (PISAU+MATRAS = "die", PLATE, FILM) jadi baris terpisah
    // -- sesuai docs/mockups/mockup_sales_quotation_letter_print.html (baris "Biaya
    // pisau/matras (die)" dan "Biaya plat cetak" tampil sendiri-sendiri, bukan digabung).
    protected static function addToolingLines(QuoQuotation $quotation, IqpInquiry $inquiry, int $startSeq): void
    {
        $toolings = $inquiry->toolings()->where('is_new_charge', true)->get();
        $login = Auth::user()->login;

        $groups = [
            'DIE' => ['label' => 'Biaya pisau/matras (die) — sekali', 'kinds' => ['PISAU', 'MATRAS'], 'unit' => 'set'],
            'PLATE' => ['label' => 'Biaya plat cetak — sekali', 'kinds' => ['PLATE'], 'unit' => 'plat'],
            'FILM' => ['label' => 'Biaya film — sekali', 'kinds' => ['FILM'], 'unit' => 'film'],
        ];

        $seq = $startSeq;

        foreach ($groups as $g) {
            $rows = $toolings->whereIn('tooling_kind', $g['kinds']);
            $total = (float) $rows->sum('cost');

            if ($total <= 0) {
                continue;
            }

            $line = QuoQuotationItem::create([
                'quotation_id' => $quotation->id_quotation,
                'line_seq' => $seq++,
                'source_type' => 'INQUIRY_TOOLING',
                'inquiry_id' => $inquiry->id_inquiry,
                'description' => $g['label'],
                'spec_note' => $rows->count().' '.$g['unit'].'. Dibebankan satu kali di order pertama.',
                'qty' => $rows->count(),
                'qty_unit' => $g['unit'],
                'unit_price' => $total / max(1, $rows->count()),
                'discount_pct' => 0,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
            $line->recalcSubtotal();
            $line->save();
        }
    }

    protected static function specNote(IqpInquiry $inquiry): ?string
    {
        $bits = $inquiry->parts->map(function ($part) {
            $material = $part->materials->firstWhere('is_primary', true) ?? $part->materials->first();

            return implode(', ', array_filter([
                $material ? $material->rawmat_name.($material->gram_weight ? ' '.$material->gram_weight.'gsm' : '') : null,
                $part->cfg_color,
                $part->processes->sortBy('step_seq')->pluck('process_name')->filter()->implode(', ') ?: null,
            ]));
        })->filter()->implode('; ');

        return $bits ?: null;
    }
}
