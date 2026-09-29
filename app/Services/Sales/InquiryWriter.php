<?php

namespace App\Services\Sales;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartAttachment;
use App\Models\Master\ProdPartFinishing;
use App\Models\Master\ProdType;
use App\Models\Master\RefFinishingType;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

// Simpan Inquiry Product BARU. Isi = salinan baris-per-baris persist*() di
// pages/inquiry/form.blade.php (v1, zona beku) supaya layar v2 menulis data yang
// sama persis. v1 sendiri belum memakai kelas ini -- ubah keduanya bersamaan.
// Input = bentuk array state v1 (header, tiers, parts{materials,finishings}, toolings, external_costs).
final class InquiryWriter
{
    public function __construct(private string $login) {}

    public function save(array $in, ?IqpInquiry $inquiry, array $artworks = []): IqpInquiry
    {
        if ($inquiry?->stat_confirm) {
            throw ValidationException::withMessages(['inquiry' => 'Inkuiri sudah dikonfirmasi — tidak bisa diubah.']);
        }

        $this->assertTierInvariant($in['tiers']);

        return DB::connection('tm2sdi')->transaction(function () use ($in, $inquiry, $artworks) {
            $attributes = $this->headerAttributes($in['header']);

            if ($inquiry) {
                $inquiry->update($attributes);
            } else {
                $attributes['inquiry_type'] = 'NEW';
                $attributes['created_by'] = $this->login;
                $attributes['inquiry_no'] = IqpInquiry::nextInquiryNo();
                // refresh(): kolom ber-DEFAULT DB (sub_state, revision_no) belum ada di instance setelah create().
                $inquiry = IqpInquiry::create($attributes)->refresh();
            }

            $this->persistTiers($inquiry, $in['tiers']);
            $this->persistParts($inquiry, $in['parts'], $in['header']['material_source'], $artworks);
            $this->persistToolings($inquiry, $in['toolings']);
            $this->persistExternalCosts($inquiry, $in['external_costs']);

            return $inquiry->refresh();
        });
    }

    public function confirm(IqpInquiry $inquiry): void
    {
        $inquiry->update(['stat_confirm' => true, 'sub_state' => 'SUBMITTED', 'updated_by' => $this->login]);
    }

    public function cancel(IqpInquiry $inquiry, string $reason): void
    {
        if (! $inquiry->stat_confirm || $inquiry->stat_cancel) {
            throw ValidationException::withMessages(['cancel_reason' => 'Hanya inkuiri yang sudah Confirm & belum batal yang bisa dibatalkan.']);
        }

        $inquiry->update(['stat_cancel' => true, 'cancel_reason' => $reason, 'sub_state' => 'CANCELLED', 'updated_by' => $this->login]);
    }

    // LOST boleh dari sub_state mana pun selama sudah Confirm & belum terminal (markLost() v1).
    public function markLost(IqpInquiry $inquiry, string $reason): void
    {
        if (! $inquiry->stat_confirm || in_array($inquiry->sub_state, ['CANCELLED', 'CONVERTED', 'LOST'], true)) {
            throw ValidationException::withMessages(['lost_reason' => 'Hanya inkuiri yang sudah Confirm & belum selesai yang bisa ditandai kalah.']);
        }

        $inquiry->update(['cancel_reason' => $reason, 'sub_state' => 'LOST', 'updated_by' => $this->login]);
    }

    // Artwork = bahan desain, tetap boleh diganti setelah Confirm (keputusan user 2026-09-28).
    // $inner = sisi dalam (kemasan cetak 2 sisi, user 2026-09-29); sisi luar yang tampil di dieline & 3D.
    public function storeArtwork(IqpPart $part, UploadedFile $file, bool $inner = false): void
    {
        [$col, $name] = $inner ? ['artwork_inner_path', 'artwork_inner_original_filename'] : ['artwork_path', 'artwork_original_filename'];
        $old = $part->{$col};
        $path = $file->store("inquiry/{$part->id_part}/artwork".($inner ? '-inner' : ''), 'public');

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        $part->update([$col => $path, $name => $file->getClientOriginalName(), 'updated_by' => $this->login]);
    }

    // Mode #1 Inquiry(BARU) -> ProDev: seed prod_product draft + prod_part (sendToProDev() v1).
    public function sendToProDev(IqpInquiry $inquiry, IqpPart $part): int
    {
        if (! $inquiry->stat_confirm) {
            throw ValidationException::withMessages(['part' => 'Confirm inquiry ini dulu sebelum kirim ke ProDev (spec harus FIX).']);
        }

        $productId = $inquiry->ensureDraftProduct($this->login);

        if (! $part->prod_part_id) {
            $dims = $part->dieline_spec['dims'] ?? [];
            $cm = fn (string $k) => isset($dims[$k]) ? round(((float) $dims[$k]) / 10, 2) : null;

            $prodPart = ProdPart::create([
                'product_id' => $productId,
                'part_code' => 'P'.$part->id_part,
                'part_name' => $part->part_name,
                'cm_length' => $cm('product_length'),
                'cm_width' => $cm('product_width'),
                'cm_height' => $cm('product_height'),
                'activate' => true,
                'created_by' => $this->login,
                'updated_by' => $this->login,
            ]);
            $part->update(['prod_part_id' => $prodPart->id_part]);
        }

        ProdPart::whereKey($part->prod_part_id)->update(array_filter([
            'pack_qty' => $part->pack_qty,
            'cfg_color' => $part->cfg_color,
            'updated_by' => $this->login,
        ], fn ($v) => $v !== null));

        ProdPartFinishing::where('part_id', $part->prod_part_id)->delete();
        foreach ($part->finishings as $fin) {
            ProdPartFinishing::create([
                'part_id' => $part->prod_part_id,
                'seq' => $fin->seq,
                'finishing_type_id' => $fin->finishing_type_id,
                'attribute' => $fin->attribute,
                'notes' => $fin->notes,
                'created_by' => $this->login,
                'updated_by' => $this->login,
            ]);
        }

        if ($part->artwork_path
            && ! ProdPartAttachment::where('part_id', $part->prod_part_id)->where('file_path', $part->artwork_path)->exists()) {
            ProdPartAttachment::create([
                'part_id' => $part->prod_part_id,
                'file_type' => 'JPEG',
                'file_path' => $part->artwork_path,
                'original_filename' => $part->artwork_original_filename,
                'uploaded_at' => now(),
                'uploaded_by' => $this->login,
            ]);
        }

        return (int) $part->prod_part_id;
    }

    // Quick-add customer dari Inquiry: status DRAFT & default konservatif (saveNewCustomer() v1).
    public function quickCustomer(array $d): CustCustomer
    {
        $prefix = 'QC'.now()->format('ym');
        $last = CustCustomer::where('customer_code', 'like', "{$prefix}%")->orderByDesc('customer_code')->value('customer_code');

        return CustCustomer::create([
            'customer_code' => $prefix.str_pad((string) ($last ? ((int) substr($last, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT),
            'customer_name' => $d['name'],
            'customer_type' => $d['type'],
            'phone' => $d['phone'] ?: null,
            'email' => $d['email'] ?: null,
            'default_address' => $d['address'] ?: null,
            'approval_status' => 'DRAFT',
            'tax_profile' => 'PPN_11',
            'credit_rating' => 'C',
            'kyc_status' => 'PENDING',
            'is_prospect' => false,
            'activate' => true,
            'created_by' => $this->login,
            'updated_by' => $this->login,
        ]);
    }

    private function assertTierInvariant(array $tiers): void
    {
        if (count($tiers) === 0) {
            throw ValidationException::withMessages(['tiers' => 'Minimal 1 baris tier qty diperlukan.']);
        }

        $primary = collect($tiers)->where('is_primary', true)->count();
        if ($primary !== 1) {
            throw ValidationException::withMessages(['tiers' => 'Harus tepat 1 tier UTAMA (saat ini: '.$primary.').']);
        }

        foreach ($tiers as $i => $t) {
            if ($t['qty'] === '' || ! is_numeric($t['qty']) || (float) $t['qty'] <= 0) {
                throw ValidationException::withMessages(['tiers' => 'Qty tier baris '.($i + 1).' wajib diisi angka lebih besar dari 0.']);
            }
        }
    }

    private function headerAttributes(array $h): array
    {
        $customerMaterial = $h['material_source'] === 'CUSTOMER';

        return [
            'inquiry_date' => $h['inquiry_date'],
            'source' => $h['source'],
            'pic_sales' => $h['pic_sales'] ?: null,
            'customer_id' => $h['is_prospect'] ? null : $h['customer_id'],
            'is_prospect' => $h['is_prospect'],
            'prospect_name' => $h['is_prospect'] ? $h['prospect_name'] : null,
            'deadline_quotation' => $h['deadline_quotation'] ?: null,
            'product_draft_name' => $h['product_draft_name'],
            'sales_note' => $h['sales_note'] ?: null,
            'material_source' => $h['material_source'],
            'job_nature' => $customerMaterial ? 'JASA' : 'PRODUCT',
            'tax_scheme' => $h['tax_scheme'],
            'pph_applicable' => $h['pph_applicable'],
            'cust_material_spec' => $customerMaterial ? ($h['cust_material_spec'] ?: null) : null,
            'cust_material_eta' => $customerMaterial ? ($h['cust_material_eta'] ?: null) : null,
            'discount_doc_type' => $h['discount_doc_type'] ?: null,
            'discount_doc_value' => $h['discount_doc_type'] && $h['discount_doc_value'] !== '' ? $h['discount_doc_value'] : null,
            'discount_qty_value' => $h['discount_qty_value'] !== '' ? $h['discount_qty_value'] : null,
            'discount_qty_min_ton' => $h['discount_qty_min_ton'] !== '' ? $h['discount_qty_min_ton'] : null,
            'updated_by' => $this->login,
        ];
    }

    private function persistTiers(IqpInquiry $inquiry, array $tiers): void
    {
        $keep = [];

        foreach ($tiers as $i => $row) {
            $data = [
                'tier_seq' => $i + 1,
                'qty' => $row['qty'],
                'is_primary' => $row['is_primary'],
                'discount_item_type' => $row['discount_item_type'] ?: null,
                'discount_item_value' => $row['discount_item_value'] !== '' ? $row['discount_item_value'] : null,
                'updated_by' => $this->login,
            ];

            $keep[] = $this->upsert($inquiry->tiers(), $row['id'], $data);
        }

        $inquiry->tiers()->whereNotIn('id_tier', $keep)->delete();
    }

    private function persistParts(IqpInquiry $inquiry, array $parts, string $materialSource, array $artworks): void
    {
        $keep = [];

        foreach ($parts as $i => $row) {
            // Snapshot dieline (Tier1 §5.3): DSL prod_type.dieline_spec + dims user dibekukan ke iqp_part.
            $spec = null;
            if ($row['type_code'] !== '' && ($type = ProdType::find($row['type_code'])) && $type->dieline_spec) {
                $spec = $type->dieline_spec;
                $spec['dims'] = $row['dims'];
                $spec['type_code'] = $row['type_code'];
            }

            $data = [
                'part_seq' => $i + 1,
                'part_name' => $row['part_name'] !== '' ? $row['part_name'] : 'Part '.($i + 1),
                'part_type' => $row['part_type'] ?: null,
                'qty_per_sku' => $row['qty_per_sku'] !== '' ? $row['qty_per_sku'] : 1,
                'pack_qty' => $row['pack_qty'] !== '' ? (int) $row['pack_qty'] : null,
                'cfg_color' => $row['cfg_color'] !== '' ? $row['cfg_color'] : null,
                'cfg_ink' => $row['cfg_ink'] !== '' ? $row['cfg_ink'] : null,
                'dieline_spec' => $spec,
                'flat_w' => $row['flat_w'] !== '' ? $row['flat_w'] : null,
                'flat_h' => $row['flat_h'] !== '' ? $row['flat_h'] : null,
                'flat_area' => $row['flat_area'] !== '' ? $row['flat_area'] : null,
                'updated_by' => $this->login,
            ];

            $part = $inquiry->parts()->find($this->upsert($inquiry->parts(), $row['id'], $data));
            $keep[] = $part->id_part;

            foreach (['artwork' => false, 'artwork_inner' => true] as $field => $inner) {
                if (isset($artworks[$row['key']][$field])) {
                    $this->storeArtwork($part, $artworks[$row['key']][$field], $inner);
                }
            }
            $this->persistMaterials($part, $row['materials'], $materialSource);
            $this->persistFinishings($part, $row['finishings']);
        }

        $inquiry->parts()->whereNotIn('id_part', $keep)->delete();
    }

    private function persistMaterials(IqpPart $part, array $rows, string $materialSource): void
    {
        $keep = [];

        foreach ($rows as $row) {
            if (! empty($row['keep_only'])) {
                $keep[] = $row['id'];
            }
        }

        $filled = array_filter($rows, fn ($r) => empty($r['keep_only']) && (($r['rawmat_id'] ?? null) || ($r['category_id'] ?? null) || $r['display_name'] !== ''));
        foreach (array_values($filled) as $i => $row) {
            $data = [
                'layer_seq' => $row['layer_seq'] ?? $i + 1,
                'rawmat_id' => $row['rawmat_id'] ?: null,
                'category_id' => $row['category_id'] ?? null,
                'rawmat_name' => $row['display_name'] !== '' ? $row['display_name'] : null,
                'gram_weight' => $row['gram_weight'] ?: null,
                'flute_type' => $row['flute_type'] ?? null,
                'size_p' => $row['size_p'] ?: null,
                'size_l' => $row['size_l'] ?: null,
                'is_primary' => $row['is_primary'],
                // B1 (Tier1 §6.2): granularitas per-layer belum diputuskan, ikut flag header.
                'material_source' => $materialSource,
                'updated_by' => $this->login,
            ];

            $keep[] = $this->upsert($part->materials(), $row['id'], $data);
        }

        $part->materials()->whereNotIn('id_part_material', $keep)->delete();
    }

    // Finishing terstruktur (BACKLOG §18.2); iqp_part_process milik engine BOP, tidak disentuh di sini.
    private function persistFinishings(IqpPart $part, array $rows): void
    {
        $part->finishings()->delete();
        $names = RefFinishingType::pluck('finishing_name', 'id_finishing_type');

        foreach (array_values(array_filter($rows, fn ($r) => ! empty($r['finishing_type_id']) && isset($names[$r['finishing_type_id']]))) as $seq => $row) {
            $part->finishings()->create([
                'seq' => ($seq + 1) * 10,
                'finishing_type_id' => (int) $row['finishing_type_id'],
                'finishing_name' => $names[$row['finishing_type_id']],
                'attribute' => trim($row['attribute'] ?? '') ?: null,
                'created_by' => $this->login,
                'updated_by' => $this->login,
            ]);
        }
    }

    private function persistToolings(IqpInquiry $inquiry, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $data = [
                'tooling_kind' => $row['tooling_kind'],
                'tooling_status' => $row['tooling_status'],
                'is_new_charge' => $row['is_new_charge'],
                'cost' => $row['is_new_charge'] && $row['cost'] !== '' ? $row['cost'] : 0,
                'remark' => $row['remark'] !== '' ? $row['remark'] : null,
                'updated_by' => $this->login,
            ];

            $keep[] = $this->upsert($inquiry->toolings(), $row['id'], $data);
        }

        $inquiry->toolings()->whereNotIn('id_tooling', $keep)->delete();
    }

    private function persistExternalCosts(IqpInquiry $inquiry, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $data = [
                'cost_kind' => $row['cost_kind'],
                'description' => $row['description'] !== '' ? $row['description'] : null,
                'amount' => $row['amount'] !== '' ? $row['amount'] : 0,
                'tax_treatment' => $row['tax_treatment'],
                'updated_by' => $this->login,
            ];

            $keep[] = $this->upsert($inquiry->externalCosts(), $row['id'], $data);
        }

        $inquiry->externalCosts()->whereNotIn('id_external_cost', $keep)->delete();
    }

    // Baris lama (id milik dokumen ini) diperbarui, selain itu dibuat baru; kembalikan PK-nya.
    private function upsert(HasMany $rel, mixed $id, array $data): int
    {
        $row = $id ? (clone $rel)->find($id) : null;
        $row ? $row->update($data) : $row = $rel->create($data + ['created_by' => $this->login]);

        return (int) $row->getKey();
    }
}
