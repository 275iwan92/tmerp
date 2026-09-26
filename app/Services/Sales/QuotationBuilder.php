<?php

namespace App\Services\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationItem;
use App\Services\Inquiry\TierPricingResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    // Isi "Choose inquiry window": inquiry berharga (costing published) yang belum
    // ditarik ke quotation mana pun.
    public static function availableInquiries()
    {
        $quoted = QuoQuotationItem::where('source_type', 'INQUIRY_TIER')->whereNotNull('inquiry_id')->pluck('inquiry_id')->unique();

        return IqpInquiry::whereHas('costing', fn ($q) => $q->where('published_flag', true))
            ->whereNotNull('customer_id')
            ->whereNotIn('id_inquiry', $quoted);
    }

    // Nilai header yang diisi otomatis dari customer + inquiry pertama; officer boleh override.
    // Deadline = tanggal dokumen + 14 hari (user 2026-09-26).
    public static function autoHeader(?CustCustomer $customer, ?IqpInquiry $inquiry, $issuedDate): array
    {
        $issued = Carbon::parse($issuedDate);

        return [
            'valid_until' => $issued->copy()->addDays(30)->toDateString(),
            'deadline_date' => $issued->copy()->addDays(14)->toDateString(),
            'payment_term' => $customer?->payment_term ?: null,
            'currency_code' => $customer?->currency_code ?: 'IDR',
            'tax_scheme' => 'EXCL',
            'attn_contact' => $customer?->contact_name ?: null,
            'sales_pic' => $inquiry?->pic_sales ?: null,
        ];
    }

    // Quotation baru dari inquiry terpilih (semua harus 1 customer); semua tier jadi baris.
    public static function createFromInquiries(array $inquiryIds): QuoQuotation
    {
        $inquiries = static::chosenInquiries($inquiryIds);
        $first = $inquiries->first();
        $login = Auth::user()->login;
        $today = now()->toDateString();

        return DB::connection('tm2sdi')->transaction(function () use ($inquiries, $first, $login, $today) {
            $quotation = QuoQuotation::create([
                'quotation_no' => QuoQuotation::nextQuotationNo(),
                'customer_id' => $first->customer_id,
                'revision_no' => 0,
                'issued_date' => $today,
                'doc_status' => 'DRAFT',
                'issued_by' => $login,
                'created_by' => $login,
                'updated_by' => $login,
            ] + static::autoHeader(CustCustomer::find($first->customer_id), $first, $today));

            foreach ($inquiries as $inquiry) {
                static::addInquiry($quotation, $inquiry, true);
            }

            return $quotation;
        });
    }

    // Pilih ulang inquiry: customer sama -> ditambahkan; customer beda -> semua baris
    // diganti & header otomatis diisi ulang dari customer baru (customer tidak diketik).
    public static function applyInquiries(QuoQuotation $quotation, array $inquiryIds): void
    {
        $inquiries = static::chosenInquiries($inquiryIds);
        $first = $inquiries->first();

        DB::connection('tm2sdi')->transaction(function () use ($quotation, $inquiries, $first) {
            if ((int) $first->customer_id !== (int) $quotation->customer_id) {
                $quotation->items()->delete();
                $quotation->update(['customer_id' => $first->customer_id, 'updated_by' => Auth::user()->login]
                    + static::autoHeader(CustCustomer::find($first->customer_id), $first, $quotation->issued_date));
            }

            foreach ($inquiries as $inquiry) {
                static::addInquiry($quotation, $inquiry, true);
            }
        });
    }

    protected static function chosenInquiries(array $inquiryIds)
    {
        $inquiries = IqpInquiry::whereIn('id_inquiry', $inquiryIds)->whereNotNull('customer_id')->orderBy('id_inquiry')->get();

        if ($inquiries->isEmpty() || $inquiries->pluck('customer_id')->unique()->count() > 1) {
            throw new InvalidArgumentException('Inquiry terpilih harus dari 1 customer.');
        }

        return $inquiries;
    }

    // Idempotent per tier -- tier yang sudah ada sbg INQUIRY_TIER tidak dobel.
    // $allTiers=false = hanya tier utama (dipakai tombol di Costing).
    public static function addInquiry(QuoQuotation $quotation, IqpInquiry $inquiry, bool $allTiers = false): ?QuoQuotationItem
    {
        $existingTiers = QuoQuotationItem::where('quotation_id', $quotation->id_quotation)
            ->where('inquiry_id', $inquiry->id_inquiry)
            ->where('source_type', 'INQUIRY_TIER')
            ->pluck('tier_id');

        if (! $allTiers && $existingTiers->isNotEmpty()) {
            return null;
        }

        $inquiry->loadMissing(['costing.priceTiers', 'tiers', 'parts.materials', 'parts.processes']);

        $priced = (bool) ($inquiry->costing?->published_flag) && $inquiry->costing->priceTiers->isNotEmpty();
        $rows = TierPricingResolver::rows($inquiry, $priced);
        $rows = $allTiers ? $rows : collect([TierPricingResolver::primary($rows)])->filter();
        $rows = $rows->filter(fn ($r) => $r->price_excl !== null && ! $existingTiers->contains($r->tier->id_tier));

        if ($rows->isEmpty()) {
            return null;
        }

        $nextSeq = ((int) QuoQuotationItem::where('quotation_id', $quotation->id_quotation)->max('line_seq')) + 1;
        $login = Auth::user()->login;
        $first = null;

        foreach ($rows as $row) {
            $item = QuoQuotationItem::create([
                'quotation_id' => $quotation->id_quotation,
                'line_seq' => $nextSeq++,
                'source_type' => 'INQUIRY_TIER',
                'inquiry_id' => $inquiry->id_inquiry,
                'tier_id' => $row->tier->id_tier,
                'description' => $inquiry->product_draft_name ?: ('Inquiry '.$inquiry->inquiry_no),
                'spec_note' => static::specNote($inquiry),
                'qty' => $row->tier->qty,
                'qty_unit' => 'Pcs',
                'unit_price' => $row->price_excl,
                'discount_pct' => 0,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
            $item->recalcSubtotal();
            $item->save();
            $first ??= $item;
        }

        if ($existingTiers->isEmpty()) {
            static::addToolingLines($quotation, $inquiry, $nextSeq);
        }

        return $first;
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
