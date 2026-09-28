<?php

namespace App\Services\Sales;

use App\Models\Sdi\IqpInquiry;
use Illuminate\Support\Facades\DB;

// Duplicate = spec sama, customer lain (keputusan user 2026-09-28). Lahir sebagai
// NEW DRAFT; struktur fisik disalin lewat cloneStructureFrom() (mekanisme RECALC).
// Tier qty & harga TIDAK ikut: customer lain = negosiasi baru lewat Pro-Dev/Pro-Cost.
final class InquiryDuplicator
{
    public static function duplicate(IqpInquiry $source, int $customerId, string $login): IqpInquiry
    {
        $source->load([
            'parts' => fn ($q) => $q->orderBy('part_seq'),
            'parts.materials', 'parts.processes', 'parts.finishings', 'parts.inks',
        ]);

        return DB::connection('tm2sdi')->transaction(function () use ($source, $customerId, $login) {
            $new = IqpInquiry::create([
                'inquiry_no' => IqpInquiry::nextInquiryNo(),
                'inquiry_date' => now()->toDateString(),
                'source' => $source->source,
                'pic_sales' => $source->pic_sales,
                'customer_id' => $customerId,
                'is_prospect' => false,
                'product_draft_name' => $source->product_draft_name,
                'sales_note' => 'Duplicate dari '.$source->inquiry_no,
                'inquiry_type' => 'NEW',
                'revision_no' => 0,
                'material_source' => $source->material_source,
                'job_nature' => $source->job_nature,
                'cust_material_spec' => $source->cust_material_spec,
                'tax_scheme' => $source->tax_scheme,
                'pph_applicable' => $source->pph_applicable,
                'sub_state' => 'DRAFT',
                'created_by' => $login,
                'updated_by' => $login,
            ])->refresh();

            $new->cloneStructureFrom($source);

            return $new;
        });
    }
}
