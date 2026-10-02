<?php

namespace App\Support\V2\Data;

use App\Models\Master\ProdMasterCard;
use App\Services\MasterCard\MasterCardShape;
use Illuminate\Support\Facades\DB;

// Data layar v2 Master Card Collection (grid + opsi form). Aturan = App\Services\MasterCard\MasterCardService.
final class MasterCardCollection
{
    public const TONES = ['Incomplete' => 'warn', 'Ready' => 'info', 'Master Product' => 'success', 'Inquiry' => 'accent'];

    public static function rows(): array
    {
        $cust = DB::connection('tm2mst')->table('cust_customer')->pluck('customer_name', 'id_customer');
        $types = Sales::dielineTypes();
        $mm = fn ($v) => $v ? rtrim(rtrim(number_format($v / 10, 1, ',', ''), '0'), ',') : '?';

        return ProdMasterCard::with('files')->orderByDesc('updated_at')->get()->map(function (ProdMasterCard $c) use ($cust, $types, $mm) {
            $img = $c->files->firstWhere('file_kind', 'PROOF') ?? $c->files->firstWhere('file_kind', 'PHOTO') ?? $c->files->firstWhere('file_kind', 'ARTWORK');
            $check = $c->type_code && $c->dims_json ? MasterCardShape::check($c->toArray(), $c->type_code, $c->dims_json) : null;

            return [
                'id' => $c->id_master_card,
                'photo' => $img?->url(),
                'name' => $c->product_name ?: t('(no name)'),
                'code' => trim(($c->design_no ?? '—').' · '.($c->card_code ?? '—')),
                'customer' => $c->customer_id ? trim((string) ($cust[$c->customer_id] ?? '')) : ($c->customer_text ? $c->customer_text.' '.t('(not matched)') : '?'),
                'part' => implode(' + ', array_map(fn ($p) => $p === '' ? '1' : $p, MasterCardShape::parts($c->toArray()))),
                'dims' => $c->dim_l ? $mm($c->dim_l).' × '.$mm($c->dim_w).' × '.$mm($c->dim_h).' cm' : '?',
                'type' => $c->type_code ? ($types[$c->type_code]['label'] ?? $c->type_code) : '?',
                'knife' => $c->knife_w ? $mm($c->knife_w).' × '.$mm($c->knife_h).' · '.($c->up_text ?? '?').($check ? ($check['ok'] ? ' ✓' : ' ≠') : '') : '?',
                'source' => ($kind = ['XLSX' => 'Excel', 'PHOTO' => 'Photo', 'MANUAL' => 'Manual'][$c->source_kind] ?? null) ? t($kind) : $c->source_kind,
                'status' => ProdMasterCard::STATUS[$c->status] ?? $c->status,
                'svg' => $c->dieline_svg ? 'Ada' : 'Belum',
                'updated' => $c->updated_at?->toDateString(),
            ];
        })->all();
    }

    // Kategori bahan baku (Paper & Sheet, sampai tingkat 3) untuk memastikan tebakan bahan kartu.
    public static function categoryOptions(): array
    {
        $rows = DB::connection('tm2mst')->table('mat_category')->where('activate', 1)->get(['id_category', 'parent_id', 'level', 'category_name'])->keyBy('id_category');
        $label = function ($r) use ($rows) {
            $p = [];
            while ($r) {
                array_unshift($p, $r->category_name);
                $r = $r->parent_id ? ($rows[$r->parent_id] ?? null) : null;
            }

            return implode(' › ', $p);
        };

        return $rows->filter(fn ($r) => $r->level >= 2)->map($label)->filter(fn ($l) => str_starts_with($l, 'Paper') || str_starts_with($l, 'Sheet'))->sort()->all();
    }

    public static function typeOptions(): array
    {
        return array_map(fn ($t) => $t['label'], Sales::dielineTypes());
    }

    public static function joinOptions(): array
    {
        return DB::connection('tm2mst')->table('mat_join_type')->where('activate', 1)->orderBy('sort_order')->pluck('label', 'id_join_type')->all();
    }
}
