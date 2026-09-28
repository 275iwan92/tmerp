<?php

namespace App\Support\V2;

use App\Models\Sdi\IqpInquiry;
use Illuminate\Http\Request;

// Isian form Inquiry v2 (nama field objek DevLib) -> bentuk input App\Services\Sales\InquiryWriter
// (= bentuk state v1). Validasi header = headerRules() tmerp.
final class InquiryPayload
{
    public static function rules(): array
    {
        return [
            'inquiry_date' => ['required', 'date'],
            'source' => ['required', 'in:EMAIL,WA,PHONE,VISIT,MARKETPLACE'],
            'pic_sales' => ['nullable', 'string', 'max:100'],
            'deadline_quotation' => ['nullable', 'date', 'after_or_equal:inquiry_date'],
            'product_draft_name' => ['required', 'string', 'max:150', 'regex:~^[^/@#%"\']+$~'],
            'sales_note' => ['nullable', 'string'],
            'cust_material_spec' => ['nullable', 'string', 'required_if:material_source,1'],
            'cust_material_eta' => ['nullable', 'date'],
            'customer_id' => ['required', 'integer', 'exists:tm2mst.cust_customer,id_customer'],
            'type_code' => ['required', 'exists:tm2mst.prod_type,type_code'],
        ];
    }

    // Kolom yang belum ada isiannya di layar v2 (pajak, PPh, diskon dokumen) dipertahankan dari dokumen lama; dokumen baru = default tmerp.
    public static function from(Request $r, ?IqpInquiry $old): array
    {
        $customerMaterial = $r->boolean('material_source');
        $type = (string) $r->input('type_code', '');

        return [
            'header' => [
                'inquiry_date' => $r->input('inquiry_date'),
                'source' => $r->input('source'),
                'pic_sales' => (string) $r->input('pic_sales', ''),
                'customer_id' => (int) $r->input('customer_id'),
                'is_prospect' => false,
                'prospect_name' => null,
                'deadline_quotation' => (string) $r->input('deadline_quotation', ''),
                'product_draft_name' => trim((string) $r->input('product_draft_name')),
                'sales_note' => (string) $r->input('sales_note', ''),
                'material_source' => $customerMaterial ? 'CUSTOMER' : 'OWN',
                'tax_scheme' => $old?->tax_scheme ?? 'PPN',
                'pph_applicable' => $old ? (bool) $old->pph_applicable : $customerMaterial,
                'cust_material_spec' => (string) $r->input('cust_material_spec', ''),
                'cust_material_eta' => (string) $r->input('cust_material_eta', ''),
                'discount_doc_type' => $old?->discount_doc_type ?? '',
                'discount_doc_value' => (string) ($old?->discount_doc_value ?? ''),
                'discount_qty_value' => (string) ($old?->discount_qty_value ?? ''),
                'discount_qty_min_ton' => (string) ($old?->discount_qty_min_ton ?? ''),
            ],
            'tiers' => array_map(fn ($t) => [
                'id' => self::id($t), 'qty' => self::num($t['qty'] ?? ''), 'is_primary' => self::bool($t['is_primary'] ?? false),
                'discount_item_type' => '', 'discount_item_value' => '',
            ], array_values($r->input('tiers', []))),
            'parts' => array_map(fn ($key) => self::part((string) $key, $r->input("parts.$key"), $type), array_keys($r->input('parts', []))),
            'toolings' => array_map(fn ($t) => [
                'id' => self::id($t), 'tooling_kind' => $t['tooling_kind'] ?? 'PLATE', 'tooling_status' => $t['tooling_status'] ?? 'ADA',
                'is_new_charge' => self::bool($t['is_new_charge'] ?? false), 'cost' => self::num($t['cost'] ?? ''), 'remark' => (string) ($t['remark'] ?? ''),
            ], array_values($r->input('toolings', []))),
            'external_costs' => array_map(fn ($c) => [
                'id' => self::id($c), 'cost_kind' => $c['cost_kind'] ?? 'LAINNYA', 'description' => (string) ($c['description'] ?? ''),
                'amount' => self::num($c['amount'] ?? ''), 'tax_treatment' => $c['tax_treatment'] ?? 'TAXABLE',
            ], array_values($r->input('external_costs', []))),
        ];
    }

    // Artwork per part, dikunci dengan index part di form (parts[<key>][artwork]).
    public static function artworks(Request $r): array
    {
        return collect($r->file('parts', []))->map(fn ($f) => $f['artwork'] ?? null)->filter()->all();
    }

    private static function part(string $key, array $p, string $type): array
    {
        $d = $p['dieline'] ?? [];

        return [
            'key' => $key,
            'id' => self::id($p),
            'part_name' => trim((string) ($p['part_name'] ?? '')),
            'part_type' => (string) ($p['part_type'] ?? ''),
            'type_code' => $type,
            'qty_per_sku' => self::num($p['qty_per_sku'] ?? ''),
            'pack_qty' => self::num($p['pack_qty'] ?? ''),
            'dims' => array_map(fn ($v) => is_numeric(self::num($v)) ? (float) self::num($v) : $v, $d['dims'] ?? []),
            'flat_w' => self::num($d['flat_w'] ?? ''),
            'flat_h' => self::num($d['flat_h'] ?? ''),
            'flat_area' => self::num($d['flat_area'] ?? ''),
            'cfg_color' => (string) ($p['cfg_color'] ?? ''),
            'cfg_ink' => (string) ($p['cfg_ink'] ?? ''),
            // Bahan dari Kategori Produk ("CAT:<kategori>") belum item persis -> disimpan sebagai nama saja (rawmat_id kosong).
            'materials' => array_map(fn ($m) => [
                'id' => self::id($m),
                'rawmat_id' => is_numeric($m['material'] ?? '') ? (int) $m['material'] : null,
                'display_name' => (string) ($m['material_label'] ?? ''),
                'gram_weight' => null, 'size_p' => null, 'size_l' => null,
                'is_primary' => self::bool($m['is_primary'] ?? false),
            ], array_values($p['materials'] ?? [])),
            'finishings' => array_values($p['finishings'] ?? []),
        ];
    }

    private static function id(array $row): ?int
    {
        return is_numeric($row['id'] ?? null) ? (int) $row['id'] : null;
    }

    private static function bool(mixed $v): bool
    {
        return in_array($v, [true, 1, '1', 'true', 'on'], true);
    }

    // "10.000" / "1,5" (tampilan id-ID) -> "10000" / "1.5".
    private static function num(mixed $v): string
    {
        $v = trim((string) $v);

        return str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : $v;
    }
}
