<?php

/*
 * REMARK
 * Tujuan       : Aturan entri ProCost Mode Instant (kalkulator standalone, dipindah apa adanya dari komponen v1 pages::inquiry.costing-instant): dokumen baru, ubah header / tier / part / coating (setiap perubahan = hitung ulang), Hapus, Confirm (menunggu approval Direksi), Cancel, Reverse (jadi Integrated). Hanya dokumen yang belum PENDING_APPROVAL / PUBLISHED / CANCELLED yang bisa diubah.
 * Sumber hulu  : IqpCosting (mode INSTANT) + part + coating + price tier, CostingEngine (rumus), ReverseCostingService.
 * Input        : IqpCosting + nilai field / id baris.
 * Output       : iqp_costing, iqp_costing_part, iqp_costing_part_coating, iqp_costing_price_tier; Reverse membuat Inquiry + ProDev draft (ReverseCostingService).
 * Dipakai oleh : App\Support\V2\Data\ProCostInstant (layar v2 procost-instant), routes/v2_procost.php, tests/Feature/CostingInstantModeTest.
 * Status       : Aktif (T10 tahap 3 pindahan v1, 2026-10-21). Rumus tetap di CostingEngine.
 */

namespace App\Services\Costing;

use App\Models\Master\CustCustomer;
use App\Models\Master\MatCoatingRate;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatJoinType;
use App\Models\Master\MatPackingType;
use App\Models\Master\MatSheetRate;
use App\Models\Master\ProdPartType;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPartCoating;
use App\Models\Sdi\IqpCostingPriceTier;
use Illuminate\Support\Facades\Auth;

final class InstantCostingService
{
    public const HEADER_FIELDS = ['doc_date', 'calc_type', 'customer_id', 'customer_name_draft', 'product_name_draft', 'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'i_lidah_lem_mm', 'ppn_mode', 'ppn_pct', 'currency_code', 'calc_notes'];

    public const TIER_FIELDS = ['qty_pcs', 'margin_pct', 'tolerance_pct', 'rounding_mode'];

    public const PART_FIELDS = [
        'no_urut', 'part_name', 'part_type_code', 'qty_per_set', 'i_jumlah_warna', 'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'i_lidah_lem_mm',
        'grammage_id', 'i_paper_price_override', 'i_dc_persen_override', 'i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'i_isi_per_lb_kerja',
        'i_uk_cetak_p_cm', 'i_uk_cetak_l_cm', 'sheet_rate_id', 'supplier_sheet_code', 'flute_type', 'i_sheet_panjang_cm', 'i_sheet_lebar_cm', 'i_lebar_trim_cm', 'i_isi_per_trim', 'i_discount_rm',
        'i_ongkos_cetak_rim', 'i_ongkos_plong_lb', 'i_ongkos_lem_pc', 'i_ongkos_hotprint_pc', 'i_ongkos_kupas_pc', 'i_ongkos_qc_pack_pc', 'i_ongkos_kirim_pc', 'packing_type_code', 'i_ongkos_packing_pc',
        'pakai_laminasi', 'i_lam_sheet_flat_pc', 'sf_ef_rate_id', 'i_sf_ef_rate_override', 'i_lam_pakai_ukuran_sf', 'i_ongkos_lam_rp_cm2_sh', 'i_ongkos_lam_rp_cm2_sf', 'i_ongkos_laminasi_pc',
        'i_film_rate', 'i_film_faktor', 'i_ongkos_plate_warna', 'i_pisau_faktor', 'i_pisau_rate_rp',
        'i_ongkos_print_pc', 'i_ongkos_diecut_pc', 'i_ongkos_slitter_pc', 'i_ongkos_slotter_pc', 'join_type_code', 'i_ongkos_join_pc', 'i_ongkos_partisi_pc', 'i_ongkos_layer_pc', 'i_ongkos_pisau_pc',
        'calc_notes',
    ];

    public static function readOnly(IqpCosting $c): bool
    {
        return in_array($c->doc_status, CostingEntryService::READ_ONLY_STATUSES, true);
    }

    // Buka dokumen Instant; tanpa price tier dibuatkan 1 tier default.
    public static function open(int $costingId): IqpCosting
    {
        $row = IqpCosting::where('mode', IqpCosting::MODE_INSTANT)->findOrFail($costingId);

        if ($row->priceTiers()->count() === 0) {
            $login = Auth::user()->login;
            IqpCostingPriceTier::create(['costing_id' => $row->id_costing, 'qty_pcs' => 1000, 'is_primary' => true, 'created_by' => $login, 'updated_by' => $login]);
        }

        return $row;
    }

    public static function tier(IqpCosting $c): IqpCostingPriceTier
    {
        $c->loadMissing('priceTiers');

        return $c->priceTiers->firstWhere('is_primary', true) ?? $c->priceTiers->first();
    }

    // Hitung ulang (CostingEngine). Balikan = pesan galat engine ('' = baik).
    public static function recalc(IqpCosting $c): string
    {
        $fresh = IqpCosting::with(['parts.coatings', 'priceTiers', 'customer'])->findOrFail($c->id_costing);
        if ($fresh->parts->isEmpty()) {
            return '';
        }

        try {
            (new CostingEngine)->run($fresh);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return '';
    }

    public static function newDoc(): IqpCosting
    {
        $login = Auth::user()->login;

        return IqpCosting::create(['mode' => IqpCosting::MODE_INSTANT, 'calc_type' => 'NEW', 'doc_status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
    }

    public static function updateHeader(IqpCosting $c, string $field, mixed $value): string
    {
        if (self::readOnly($c) || ! in_array($field, self::HEADER_FIELDS, true)) {
            return '';
        }

        $data = [$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login];
        // Legacy Ajax customer_id onChange: auto-fill customer_name.
        if ($field === 'customer_id' && $value) {
            $data['customer_name_draft'] = CustCustomer::whereKey($value)->value('customer_name');
        }
        IqpCosting::whereKey($c->id_costing)->update($data);

        return self::recalc($c);
    }

    public static function updateTier(IqpCosting $c, string $field, mixed $value): string
    {
        if (self::readOnly($c) || ! in_array($field, self::TIER_FIELDS, true)) {
            return '';
        }

        self::tier($c)->update([$field => $value === '' ? 0 : $value, 'updated_by' => Auth::user()->login]);

        return self::recalc($c);
    }

    // Hapus dokumen beserta semua part-nya. Balikan = false bila tidak boleh (sudah PENDING_APPROVAL / PUBLISHED / CANCELLED).
    public static function delete(IqpCosting $c): bool
    {
        if (self::readOnly($c)) {
            return false;
        }

        $partIds = $c->parts()->pluck('id_costing_part');
        IqpCostingPartCoating::whereIn('costing_part_id', $partIds)->delete();
        IqpCostingPart::whereIn('id_costing_part', $partIds)->delete();
        IqpCostingPriceTier::where('costing_id', $c->id_costing)->delete();
        IqpCosting::whereKey($c->id_costing)->delete();

        return true;
    }

    // Confirm = ajukan ke Direksi; Hitung HPP harus berhasil dulu. Balikan = pesan galat ('' = baik).
    public static function confirm(IqpCosting $c): string
    {
        $c->refresh();
        if (self::readOnly($c)) {
            return 'Dokumen sudah '.$c->doc_status.' — lihat saja.';
        }
        $err = self::recalc($c);
        $c->refresh();
        if ($err || $c->doc_status !== 'CALCULATED') {
            return $err ?: 'Hitung HPP harus berhasil dulu sebelum Confirm.';
        }
        $c->update(['doc_status' => 'PENDING_APPROVAL', 'updated_by' => Auth::user()->login]);

        return '';
    }

    public static function cancel(IqpCosting $c): void
    {
        $c->refresh();
        if ($c->doc_status !== 'PUBLISHED') {
            $c->update(['doc_status' => 'CANCELLED', 'updated_by' => Auth::user()->login]);
        }
    }

    // Reverse: Inquiry + ProDev draft + Master Product draft dari data ini; dokumen jadi Integrated. Balikan = id inquiry; galat = exception.
    public static function reverse(IqpCosting $c): int
    {
        return (new ReverseCostingService)->reverse($c, Auth::user()->login)->id_inquiry;
    }

    // ───────────── part ─────────────
    public static function partAt(IqpCosting $c, int $no): ?IqpCostingPart
    {
        $parts = $c->parts()->get()->values();

        return $parts[max(0, min($no, $parts->count()) - 1)] ?? null;
    }

    public static function addPart(IqpCosting $c): ?IqpCostingPart
    {
        if (self::readOnly($c)) {
            return null;
        }

        $login = Auth::user()->login;

        return IqpCostingPart::create([
            'costing_id' => $c->id_costing,
            'no_urut' => $c->parts()->count() + 1,
            'part_name' => '',
            'part_type_code' => 'DC',
            'data_source' => 'MANUAL',
            'qty_per_set' => 1,
            'i_dim_panjang_mm' => $c->i_dim_panjang_mm,
            'i_dim_lebar_mm' => $c->i_dim_lebar_mm,
            'i_dim_tinggi_mm' => $c->i_dim_tinggi_mm,
            'i_lidah_lem_mm' => $c->i_lidah_lem_mm,
            'i_cut_per_plano' => 1,
            'i_isi_per_lb' => 1,
            'i_isi_per_lb_kerja' => 1,
            'i_jumlah_warna' => 4,
            'i_ongkos_plate_warna' => 90000,
            'supplier_sheet_code' => 'UJK',
            'created_by' => $login,
            'updated_by' => $login,
        ]);
    }

    public static function removePart(IqpCosting $c, int $partId): string
    {
        $part = IqpCostingPart::where('costing_id', $c->id_costing)->find($partId);
        if (self::readOnly($c) || ! $part) {
            return '';
        }

        IqpCostingPartCoating::where('costing_part_id', $part->id_costing_part)->delete();
        $part->delete();

        return self::recalc($c);
    }

    public static function updatePart(IqpCosting $c, int $partId, string $field, mixed $value): string
    {
        $part = IqpCostingPart::where('costing_id', $c->id_costing)->find($partId);
        if (self::readOnly($c) || ! $part || ! in_array($field, self::PART_FIELDS, true)) {
            return '';
        }

        $value = $value === '' ? null : $value;
        $data = [$field => $value, 'updated_by' => Auth::user()->login];

        // Auto-fill ala Ajax event legacy (map_hpp_parts / katalog Ajax Gemini §II).
        match ($field) {
            'part_type_code' => $data['i_pisau_rate_rp'] = ProdPartType::where('part_type_code', $value)->value('pisau_rate_rp'),
            'grammage_id' => ($dc = (float) MatGrammage::whereKey($value)->value('duplex_dc_pct')) > 0 ? $data['i_dc_persen_override'] = $dc : null,
            'sf_ef_rate_id' => $data['i_sf_ef_rate_override'] = $value ? MatSheetRate::whereKey($value)->value('index_substance') : null,
            'join_type_code' => $data['i_ongkos_join_pc'] = $value ? MatJoinType::where('code', $value)->value('default_rate') : 0,
            'packing_type_code' => $data['i_ongkos_packing_pc'] = $value ? MatPackingType::where('code', $value)->value('default_rate') : 0,
            default => null,
        };
        $part->update($data);

        return self::recalc($c);
    }

    // ───────────── coating ─────────────
    public static function addCoating(IqpCosting $c, int $partId): string
    {
        $part = IqpCostingPart::where('costing_id', $c->id_costing)->find($partId);
        if (self::readOnly($c) || ! $part) {
            return '';
        }

        IqpCostingPartCoating::create([
            'costing_part_id' => $part->id_costing_part,
            'seq' => $part->coatings()->count() + 1,
            'coating_type' => 'UV',
            'index_coating' => MatCoatingRate::where('coating_type', 'UV')->where('activate', true)->value('index_coating') ?? 0,
        ]);

        return self::recalc($c);
    }

    public static function updateCoating(IqpCosting $c, int $partId, int $id, string $field, mixed $value): string
    {
        if (self::readOnly($c) || ! in_array($field, ['coating_type', 'index_coating', 'notes'], true)) {
            return '';
        }

        $coating = IqpCostingPartCoating::whereKey($id)->where('costing_part_id', $partId)->whereHas('costingPart', fn ($q) => $q->where('costing_id', $c->id_costing))->first();
        if (! $coating) {
            return '';
        }

        if ($field === 'coating_type') {
            $coating->update(['coating_type' => $value, 'index_coating' => MatCoatingRate::where('coating_type', $value)->where('activate', true)->value('index_coating') ?? 0]);
        } else {
            $coating->update([$field === 'notes' ? 'notes_coating' : $field => $value === '' ? 0 : $value]);
        }

        return self::recalc($c);
    }

    public static function removeCoating(IqpCosting $c, int $partId, int $id): string
    {
        if (self::readOnly($c)) {
            return '';
        }

        IqpCostingPartCoating::whereKey($id)->where('costing_part_id', $partId)->whereHas('costingPart', fn ($q) => $q->where('costing_id', $c->id_costing))->delete();

        return self::recalc($c);
    }
}
