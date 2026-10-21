<?php

/*
 * REMARK
 * Tujuan       : Aturan entri ProCost Mode Integrasi (dipindah apa adanya dari komponen v1 pages::inquiry.costing): buka / buat costing dari Inquiry (tier, part dari layout ProDev, saran engine), ubah part / coating / tier / header, pilih layout ProDev, Hitung, Submit ke Direksi, Publish, Add to Quotation, Book / Release hold. Hanya status DRAFT atau CALCULATED yang bisa diubah.
 * Sumber hulu  : IqpCosting (+ part, coating, price tier), IqpInquiry, ProdLayoutGroup, ProCostContextService, CostingEngine (rumus), CostingAdvisor (saran + belajar), MaterialPlanning (Book), QuotationBuilder.
 * Input        : IqpCosting + id baris + nilai field (+ id inquiry untuk open).
 * Output       : iqp_costing, iqp_costing_part, iqp_costing_part_coating, iqp_costing_price_tier, iqp_costing_recommendation, iqp_inquiry (sub_state PRICE_CALCULATED saat Publish), rm_booking (Book).
 * Dipakai oleh : App\Support\V2\Data\ProCost (layar v2 procost), routes/v2_procost.php, tests/Feature/Costing*Test.
 * Status       : Aktif (T10 tahap 3 pindahan v1, 2026-10-21). Rumus tetap di CostingEngine; ATTENTION-ENGINE tidak disentuh.
 */

namespace App\Services\Costing;

use App\Models\Inventory\RmBooking;
use App\Models\Master\MatCoatingRate;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatJoinType;
use App\Models\Master\MatPackingType;
use App\Models\Master\MatSheetRate;
use App\Models\Master\ProdLayoutGroup;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdPartProcess;
use App\Models\Master\ProdPartType;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPartCoating;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Models\Sdi\IqpCostingRecommendation;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpLayout;
use App\Models\Sdi\IqpPart;
use App\Models\Sdi\QuoQuotation;
use App\Services\Sales\QuotationBuilder;
use App\Support\V2\Data\MaterialPlanning;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CostingEntryService
{
    public const READ_ONLY_STATUSES = ['PENDING_APPROVAL', 'PUBLISHED', 'CANCELLED'];

    public const PART_FIELDS = [
        'part_name', 'part_type_code', 'qty_per_set', 'i_jumlah_warna', 'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'i_lidah_lem_mm',
        'grammage_id', 'i_paper_price_override', 'i_dc_persen_override', 'i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'i_isi_per_lb_kerja',
        'i_uk_cetak_p_cm', 'i_uk_cetak_l_cm', 'sheet_rate_id', 'supplier_sheet_code', 'flute_type', 'i_sheet_panjang_cm', 'i_sheet_lebar_cm', 'i_lebar_trim_cm', 'i_isi_per_trim', 'i_discount_rm',
        'i_ongkos_cetak_rim', 'i_ongkos_plong_lb', 'i_ongkos_lem_pc', 'i_ongkos_hotprint_pc', 'i_ongkos_kupas_pc', 'i_ongkos_qc_pack_pc', 'i_ongkos_kirim_pc', 'packing_type_code', 'i_ongkos_packing_pc', 'jenis_packing',
        'pakai_laminasi', 'i_lam_sheet_flat_pc', 'sf_ef_rate_id', 'i_sf_ef_rate_override', 'i_lam_pakai_ukuran_sf', 'i_ongkos_lam_rp_cm2_sh', 'i_ongkos_lam_rp_cm2_sf', 'i_ongkos_laminasi_pc',
        'i_film_rate', 'i_film_faktor', 'i_ongkos_plate_warna', 'i_pisau_faktor', 'i_pisau_rate_rp',
        'i_ongkos_print_pc', 'i_ongkos_diecut_pc', 'i_ongkos_slitter_pc', 'i_ongkos_slotter_pc', 'join_type_code', 'i_ongkos_join_pc', 'i_ongkos_partisi_pc', 'i_ongkos_layer_pc', 'i_ongkos_pisau_pc', 'calc_notes',
    ];

    // Mode Ambil Data: geometri sumbernya layout ProDev, bukan input manual.
    public const GEOMETRY_FIELDS = ['i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'i_uk_cetak_p_cm', 'i_uk_cetak_l_cm'];

    public const TIER_FIELDS = ['tolerance_pct', 'margin_pct', 'rounding_mode', 'rounding_step'];

    public const HEADER_FIELDS = ['ppn_pct', 'ppn_mode', 'calc_notes'];

    private const DC_PROCESS_FIELD_MAP = [
        'CETAK' => ['field' => 'i_ongkos_cetak_rim', 'label' => 'Ongkos Cetak /rim'],
        'PLONG' => ['field' => 'i_ongkos_plong_lb', 'label' => 'Ongkos Plong /lb'],
        'LEM MESIN' => ['field' => 'i_ongkos_lem_pc', 'label' => 'Ongkos Lem /pc'],
    ];

    public static function readOnly(?IqpCosting $c): bool
    {
        return in_array($c?->doc_status, self::READ_ONLY_STATUSES, true);
    }

    public static function editable(?IqpCosting $c): bool
    {
        return $c !== null && ! self::readOnly($c);
    }

    // Publish = deklasifikasi harga dari Sisi A (rahasia) ke Sisi B (Sales), CLAUDE.md §4: admin atau grup DIREKSI.
    public static function canPublish(): bool
    {
        $u = Auth::user();

        return $u && ((bool) $u->is_admin || $u->hasGroup('DIREKSI'));
    }

    // Lihat saja (navigasi pasif): tidak membuat draft.
    public static function find(int $inquiryId): ?IqpCosting
    {
        return IqpCosting::where('inquiry_id', $inquiryId)->first();
    }

    // Buka costing inquiry ini; belum ada = dibuat (tier dari Inquiry, part dari layout ProDev, saran engine).
    public static function open(int $inquiryId): IqpCosting
    {
        $inq = IqpInquiry::with(['parts', 'tiers'])->findOrFail($inquiryId);
        $login = Auth::user()->login;
        $costing = IqpCosting::firstOrCreate(['inquiry_id' => $inquiryId], [
            'mode' => IqpCosting::MODE_INTEGRATED,
            'calc_type' => $inq->inquiry_type ?: 'NEW',
            'doc_status' => 'DRAFT',
            'customer_id' => $inq->customer_id,
            'product_name_draft' => $inq->product_draft_name,
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        if (! $costing->i_dim_panjang_mm && ($first = $inq->parts->sortBy('part_seq')->first())) {
            $costing->fill(['i_dim_panjang_mm' => $first->flat_w, 'i_dim_lebar_mm' => $first->flat_h])->save();
        }

        if ($costing->priceTiers()->count() === 0) {
            foreach ($inq->tiers as $tier) {
                IqpCostingPriceTier::create([
                    'costing_id' => $costing->id_costing, 'tier_id' => $tier->id_tier, 'qty_pcs' => $tier->qty,
                    'is_primary' => $tier->is_primary, 'created_by' => $login, 'updated_by' => $login,
                ]);
            }
            // Inquiry tanpa tier qty -> 1 tier default supaya hitungan tidak nol (ditandai di layar).
            if ($inq->tiers->isEmpty()) {
                IqpCostingPriceTier::create(['costing_id' => $costing->id_costing, 'qty_pcs' => 1000, 'is_primary' => true, 'created_by' => $login, 'updated_by' => $login]);
            }
        }

        // Mode #1 "Ambil Data": part Inquiry yang SUDAH punya layout ProDev langsung jadi baris costing dengan parameter layout terisi.
        if ($costing->parts()->count() === 0) {
            foreach ($inq->parts->sortBy('part_seq') as $ip) {
                $group = self::layoutGroupOf($ip);
                if (! $group) {
                    continue;
                }
                $cp = self::createPartFromInquiryPart($costing, $ip);
                self::applyLayout($cp, $group, ProdPart::find($ip->prod_part_id));
            }
        }

        // Dokumen yang belum diajukan dibuka: engine mengisi kotak kosong (isian officer tidak disentuh). Yang sudah diajukan / terbit tidak diubah.
        if (in_array($costing->doc_status, ['DRAFT', 'CALCULATED'], true)) {
            (new CostingAdvisor)->prefill($costing);
        }

        return $costing;
    }

    // Engine mengisi kotak kosong (tidak menimpa isian officer).
    public static function advise(IqpCosting $c): void
    {
        (new CostingAdvisor)->prefill(IqpCosting::findOrFail($c->id_costing));
    }

    // "Memang tidak dipakai": isian dikosongkan sengaja -> 0, tidak dipulihkan lagi. $notice = ['part','tier','field'].
    public static function acceptZero(IqpCosting $c, array $notice): void
    {
        [$partId, $tierId, $field] = [$notice['part'] ?? null, $notice['tier'] ?? null, $notice['field'] ?? null];
        if (! self::editable($c) || ! $field) {
            return;
        }

        $tierId ? self::updateTier($c, (int) $tierId, $field, 0) : self::updatePart($c, (int) $partId, $field, 0);
    }

    // ───────────── part dari Inquiry / ProDev ─────────────
    public static function layoutGroupOf(IqpPart $ip): ?ProdLayoutGroup
    {
        return $ip->prod_part_id ? ProdPart::find($ip->prod_part_id)?->layoutGroups()->orderByRaw("status = 'CONFIRMED' desc")->orderByDesc('id_layout_group')->first() : null;
    }

    private static function createPartFromInquiryPart(IqpCosting $costing, IqpPart $ip): IqpCostingPart
    {
        $login = Auth::user()->login;

        return IqpCostingPart::create([
            'costing_id' => $costing->id_costing, 'part_id' => $ip->id_part, 'no_urut' => $costing->parts()->count() + 1,
            'part_name' => $ip->part_name, 'part_type_code' => ProdPartType::where('activate', true)->where('part_type_code', $ip->part_type)->exists() ? $ip->part_type : 'DC',
            'data_source' => 'MANUAL', 'qty_per_set' => $ip->qty_per_sku ?: 1,
            'i_dim_panjang_mm' => $costing->i_dim_panjang_mm, 'i_dim_lebar_mm' => $costing->i_dim_lebar_mm, 'i_dim_tinggi_mm' => $costing->i_dim_tinggi_mm,
            'i_cut_per_plano' => 1, 'i_isi_per_lb' => 1, 'i_isi_per_lb_kerja' => 1, 'i_jumlah_warna' => 4, 'supplier_sheet_code' => 'UJK',
            'created_by' => $login, 'updated_by' => $login,
        ]);
    }

    // Tarik plano/potong/isi/print size/pisau dari layout ProDev (Mode #1 "Ambil Data").
    private static function applyLayout(IqpCostingPart $part, ProdLayoutGroup $group, ?ProdPart $prodPart): void
    {
        $geo = (new ProCostContextService)->layoutGeometry($group->loadMissing('parts'), $prodPart);

        $pisauFaktor = (float) ($part->i_pisau_faktor ?? 0); // 0 = engine pakai KNIFE_FACTOR_DEFAULT
        if ($group->die_rule_meter !== null) {
            $isMulti = $group->parts->count() > 1;
            $own = $prodPart ? ($group->parts->firstWhere('id_part', $prodPart->id_part)?->pivot->qty_up_in_group ?? $group->up_count) : $group->up_count;
            $eff = $prodPart ? $group->effectiveCutFor($prodPart) : ($group->up_count ?: 1);
            $pisauFaktor = $isMulti && $eff > 0 ? round((float) $group->die_rule_meter * $own / $eff, 4) : (float) $group->die_rule_meter;
        }

        // Layout ProDev menggantikan perkiraan engine (geometri).
        IqpCostingRecommendation::where('costing_part_id', $part->id_costing_part)->where('source', 'ESTIMATE')->delete();

        $part->update([
            'data_source' => 'PRODEV',
            'prod_layout_group_id' => $group->id_layout_group,
            'prodev_part_id' => $prodPart?->id_part,
            'i_panjang_plano_cm' => round($geo['plano_l'], 4),
            'i_lebar_plano_cm' => round($geo['plano_w'], 4),
            'i_cut_per_plano' => round($geo['cut'], 4),
            'i_isi_per_lb' => $geo['isi'],
            'i_uk_cetak_p_cm' => $geo['print_l'],
            'i_uk_cetak_l_cm' => $geo['print_w'],
            'i_pisau_faktor' => $pisauFaktor,
        ]);
    }

    public static function addPart(IqpCosting $c, string $partType = 'DC'): ?IqpCostingPart
    {
        if (! self::editable($c)) {
            return null;
        }

        $login = Auth::user()->login;

        return IqpCostingPart::create([
            'costing_id' => $c->id_costing, 'no_urut' => $c->parts()->count() + 1, 'part_name' => '',
            'part_type_code' => ProdPartType::where('activate', true)->where('part_type_code', $partType)->exists() ? $partType : 'DC',
            'data_source' => 'MANUAL', 'qty_per_set' => 1, 'i_cut_per_plano' => 1, 'i_isi_per_lb' => 1, 'i_isi_per_lb_kerja' => 1,
            'i_jumlah_warna' => 4, 'supplier_sheet_code' => 'UJK',
            'created_by' => $login, 'updated_by' => $login,
        ]);
    }

    // Tambah part dari part Inquiry, ambil layout ProDev (atau layout Imposition terkonfirmasi), saran engine, lalu Hitung. Balikan = galat engine ('' = baik).
    public static function addPartFromInquiry(IqpCosting $c, int $inquiryPartId): string
    {
        if (! self::editable($c)) {
            return '';
        }

        $ip = IqpPart::where('inquiry_id', $c->inquiry_id)->findOrFail($inquiryPartId);
        $cp = self::createPartFromInquiryPart($c, $ip);

        if ($group = self::layoutGroupOf($ip)) {
            self::applyLayout($cp, $group, ProdPart::find($ip->prod_part_id));
        } elseif ($layout = IqpLayout::where('part_id', $ip->id_part)->where('stat_confirm', true)->first()) {
            $cp->update(['i_panjang_plano_cm' => round($layout->plano_w_mm / 10, 4), 'i_lebar_plano_cm' => round($layout->plano_h_mm / 10, 4), 'i_cut_per_plano' => $layout->up_count ?: 1]);
        }

        self::advise($c);

        return self::hitung($c);
    }

    // Toggle Manual <-> Ambil Data per part. Balik ke MANUAL cuma lepas link, nilai yang sudah terisi tetap.
    public static function toggleDataSource(IqpCosting $c, int $partId): void
    {
        if (! self::editable($c)) {
            return;
        }

        $part = IqpCostingPart::where('costing_id', $c->id_costing)->findOrFail($partId);
        $part->update($part->data_source === 'PRODEV' ? ['data_source' => 'MANUAL', 'prod_layout_group_id' => null] : ['data_source' => 'PRODEV']);
    }

    // $prodPartId WAJIB bila group menaungi >1 part (Tool #10): alokasi biaya proporsional by luas, tidak boleh ditebak.
    // Balikan = pesan galat ('' = baik).
    public static function pickLayoutGroup(IqpCosting $c, int $partId, int $groupId, ?int $prodPartId = null): string
    {
        if (! self::editable($c)) {
            return '';
        }

        $part = IqpCostingPart::where('costing_id', $c->id_costing)->findOrFail($partId);
        $group = ProdLayoutGroup::with('parts')->find($groupId);
        if (! $group) {
            return '';
        }

        $isMulti = $group->parts->count() > 1;
        $prodPart = $prodPartId ? $group->parts->firstWhere('id_part', $prodPartId) : ($isMulti ? null : $group->parts->first());
        if ($isMulti && ! $prodPart) {
            return 'Layout ini menaungi lebih dari 1 part (Tool #10) -- pilih part yang direpresentasikan baris costing ini dulu.';
        }

        self::applyLayout($part, $group, $prodPart);
        self::advise($c);
        self::hitung($c);

        return '';
    }

    public static function removePart(IqpCosting $c, int $partId): void
    {
        if (! self::editable($c)) {
            return;
        }

        IqpCostingPartCoating::where('costing_part_id', $partId)->delete();
        IqpCostingPart::whereKey($partId)->where('costing_id', $c->id_costing)->delete();
    }

    // Balikan = pemberitahuan pemulihan (isian saran engine dikosongkan tidak sengaja -> nilai saran dipulihkan) atau null.
    public static function updatePart(IqpCosting $c, int $partId, string $field, mixed $value): ?array
    {
        if (! self::editable($c) || ! in_array($field, self::PART_FIELDS, true)) {
            return null;
        }

        if (in_array($field, self::GEOMETRY_FIELDS, true)
            && IqpCostingPart::where('costing_id', $c->id_costing)->whereKey($partId)->value('data_source') === 'PRODEV') {
            return null;
        }

        $value = $value === '' ? null : $value;

        // Isian disarankan engine dikosongkan: anggap tidak sengaja, pulihkan nilai saran (kecuali officer mengetik 0).
        $advisor = new CostingAdvisor;
        $managed = in_array($field, CostingAdvisor::managedPartFields(), true);
        $restored = false;
        $notice = null;
        if ($managed && $value === null && ($old = $advisor->valueToRestore($c->id_costing, $partId, null, $field)) !== null) {
            $value = $old;
            $restored = true;
            $notice = ['part' => $partId, 'tier' => null, 'field' => $field, 'label' => CostingAdvisor::LABELS[$field] ?? $field, 'value' => $old];
        }

        // Kolom NOT NULL yang dikosongkan tanpa nilai saran = sengaja tidak dipakai (0), bukan galat SQL.
        if ($value === null && ! $restored && in_array($field, CostingAdvisor::NOT_NULL, true)) {
            $value = 0;
        }

        $data = [$field => $value, 'updated_by' => Auth::user()->login];
        match ($field) {
            'part_type_code' => $data['i_pisau_rate_rp'] = ProdPartType::where('part_type_code', $value)->value('pisau_rate_rp'),
            'grammage_id' => ($dc = (float) MatGrammage::whereKey($value)->value('duplex_dc_pct')) > 0 ? $data['i_dc_persen_override'] = $dc : null,
            'sf_ef_rate_id' => $data['i_sf_ef_rate_override'] = $value ? MatSheetRate::whereKey($value)->value('index_substance') : null,
            'join_type_code' => $data['i_ongkos_join_pc'] = $value ? MatJoinType::where('code', $value)->value('default_rate') : 0,
            'packing_type_code' => $data['i_ongkos_packing_pc'] = $value ? MatPackingType::where('code', $value)->value('default_rate') : 0,
            default => null,
        };

        IqpCostingPart::where('costing_id', $c->id_costing)->whereKey($partId)->update($data);
        if ($managed && ! $restored) {
            $advisor->markTouched($c->id_costing, $partId, null, $field, $value, IqpCostingPart::whereKey($partId)->value('part_type_code'));
        }

        // Mode manual (tanpa Inquiry/ProDev): nama atau tipe part diketik -> engine memilih template terdekat dan mengisi tarif.
        if (in_array($field, ['part_name', 'part_type_code'], true)) {
            self::advise($c);
        }

        return $notice;
    }

    public static function addCoating(IqpCosting $c, int $partId): void
    {
        if (! self::editable($c)) {
            return;
        }

        $part = IqpCostingPart::where('costing_id', $c->id_costing)->findOrFail($partId);
        IqpCostingPartCoating::create([
            'costing_part_id' => $part->id_costing_part, 'seq' => $part->coatings()->count() + 1, 'coating_type' => 'UV',
            'index_coating' => MatCoatingRate::where('coating_type', 'UV')->where('activate', true)->value('index_coating') ?? 0,
        ]);
    }

    public static function removeCoating(IqpCosting $c, int $coatingId): void
    {
        if (self::editable($c)) {
            IqpCostingPartCoating::whereKey($coatingId)->whereHas('costingPart', fn ($q) => $q->where('costing_id', $c->id_costing))->delete();
        }
    }

    public static function updateCoating(IqpCosting $c, int $coatingId, string $field, mixed $value): void
    {
        if (! self::editable($c) || ! in_array($field, ['coating_type', 'index_coating'], true)) {
            return;
        }

        $coating = IqpCostingPartCoating::whereKey($coatingId)->whereHas('costingPart', fn ($q) => $q->where('costing_id', $c->id_costing))->first();
        if (! $coating) {
            return;
        }

        $coating->update($field === 'coating_type'
            ? ['coating_type' => $value, 'index_coating' => MatCoatingRate::where('coating_type', $value)->where('activate', true)->value('index_coating')]
            : [$field => $value === '' ? 0 : $value]);
    }

    // Balikan = pemberitahuan pemulihan atau null. $partTypeCode = tipe part aktif (konteks belajar engine).
    public static function updateTier(IqpCosting $c, int $tierId, string $field, mixed $value, ?string $partTypeCode = null): ?array
    {
        if (! self::editable($c) || ! in_array($field, self::TIER_FIELDS, true)) {
            return null;
        }

        $advisor = new CostingAdvisor;
        $tracked = in_array($field, ['margin_pct', 'tolerance_pct'], true);
        $restored = false;
        $notice = null;
        if ($tracked && $value === '' && ($old = $advisor->valueToRestore($c->id_costing, null, $tierId, $field)) !== null) {
            $value = $old;
            $restored = true;
            $notice = ['part' => null, 'tier' => $tierId, 'field' => $field, 'label' => CostingAdvisor::LABELS[$field], 'value' => $old];
        }

        IqpCostingPriceTier::where('costing_id', $c->id_costing)->whereKey($tierId)->update([$field => $value === '' ? 0 : $value]);
        if ($tracked && ! $restored) {
            $advisor->markTouched($c->id_costing, null, $tierId, $field, $value, $partTypeCode);
        }

        return $notice;
    }

    public static function updateHeader(IqpCosting $c, string $field, mixed $value): void
    {
        if (! self::editable($c) || ! in_array($field, self::HEADER_FIELDS, true)) {
            return;
        }

        IqpCosting::whereKey($c->id_costing)->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
    }

    // "Paper Index (Rp/Rim)" = override harga kertas part aktif (snapshot); lalu Hitung.
    public static function setPaperIndex(IqpCosting $c, int $partId, mixed $value): string
    {
        self::updatePart($c, $partId, 'i_paper_price_override', $value);

        return self::hitung($c);
    }

    // Hitung (CostingEngine) dan catat isian final officer sebagai bahan belajar. Balikan = pesan galat engine ('' = baik).
    public static function hitung(IqpCosting $c): string
    {
        try {
            $fresh = IqpCosting::with(['parts.coatings', 'priceTiers', 'inquiry.customer'])->findOrFail($c->id_costing);
            (new CostingEngine)->run($fresh);
            (new CostingAdvisor)->learn($fresh);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return '';
    }

    public static function submitToDireksi(IqpCosting $c): void
    {
        $c->refresh();
        if ($c->doc_status === 'CALCULATED') {
            $c->update(['doc_status' => 'PENDING_APPROVAL']);
        }
    }

    public static function publish(IqpCosting $c): void
    {
        $c->refresh();
        if (! self::canPublish() || $c->doc_status !== 'PENDING_APPROVAL') {
            return;
        }

        $login = Auth::user()->login;
        $c->update(['doc_status' => 'PUBLISHED', 'published_flag' => true, 'is_approved' => true, 'approved_by' => $login, 'approved_at' => now(), 'updated_by' => $login]);
        $c->inquiry?->update(['sub_state' => 'PRICE_CALCULATED']);
    }

    public static function addToQuotation(IqpCosting $c): ?QuoQuotation
    {
        $inquiry = $c->inquiry;
        if (! $inquiry || ! $inquiry->customer_id) {
            return null;
        }

        $quotation = QuotationBuilder::findOrCreateDraft($inquiry->customer_id);
        QuotationBuilder::addInquiry($quotation, $inquiry);

        return $quotation;
    }

    // ───────────── Book (MRP) ─────────────
    // Pre-booking (tanpa CO) rantai inquiry ini: sumber status tombol Book.
    public static function booking(int $inquiryId): ?RmBooking
    {
        return RmBooking::where('chain_key', (string) $inquiryId)->whereNull('co_id')->where('stat_cancel', 0)->orderByDesc('booking_id')->first();
    }

    // Book = Hold pre-booking yang ada (kertas saja); hanya sesudah Publish. Balikan = pesan ('' = baik).
    public static function book(IqpCosting $c): string
    {
        if ($c->doc_status !== 'PUBLISHED') {
            return t('Book is available after Publish.');
        }
        $engine = MaterialPlanning::engine();
        $b = $engine->persistPre($c->inquiry_id);
        if (! $b || $b->co_id) {
            return t('No pre-booking for this inquiry (a Customer Order or an ended quotation owns it).');
        }
        $msg = '';
        try {
            $engine->hold($b, t('Booked from ProCost'));
        } catch (HttpException $e) {
            $msg = $e->getMessage();
        }
        MaterialPlanning::changed();

        return $msg;
    }

    public static function releaseBook(IqpCosting $c): void
    {
        $b = self::booking($c->inquiry_id);
        if ($b?->doc_status === 'HELD') {
            MaterialPlanning::engine()->releaseHold($b);
            MaterialPlanning::changed();
        }
    }

    // ───────────── routing ProDev vs ongkos manual (peringatan, bukan blokir) ─────────────
    public static function routingNamesFor(IqpCostingPart $part)
    {
        if (! $part->prodev_part_id) {
            return collect();
        }

        return ProdPartProcess::where('part_id', $part->prodev_part_id)->with('process')->orderBy('step_seq')->get()->pluck('process.process_name')->filter()
            ->map(fn ($n) => strtoupper(trim($n)))->unique()->values();
    }

    public static function routingWarningsFor(IqpCostingPart $part): array
    {
        if ($part->part_type_code !== 'DC' || ! $part->prodev_part_id) {
            return [];
        }

        $names = self::routingNamesFor($part);
        if ($names->isEmpty()) {
            return [];
        }

        $warnings = [];
        foreach (self::DC_PROCESS_FIELD_MAP as $proc => $meta) {
            $in = $names->contains($proc);
            $filled = (float) $part->{$meta['field']} > 0;
            if ($in && ! $filled) {
                $warnings[] = "Proses {$proc} ada di routing ProDev tapi \"{$meta['label']}\" masih 0 — isi dulu.";
            } elseif (! $in && $filled) {
                $warnings[] = "\"{$meta['label']}\" terisi tapi proses {$proc} tidak ada di routing ProDev — cek kembali, mungkin sisa data lama.";
            }
        }

        return $warnings;
    }
}
