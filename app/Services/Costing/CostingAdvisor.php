<?php

namespace App\Services\Costing;

use App\Models\Master\MatGrammage;
use App\Models\Master\MatPlanoSize;
use App\Models\Master\ProdPartTemplate;
use App\Models\Master\ProdPartType;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Models\Sdi\IqpCostingRecommendation;
use App\Models\Sys\SysConstant;
use App\Services\Imposition\ImpositionEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/*
 * REMARK
 * Tujuan: ENGINE+ML saran ProCost (Jo 2026-10-16). Mengisi kotak ProCost yang masih kosong dari Inquiry, ProDev, template tarif,
 *         konstanta COSTING dan master; bila ada cukup riwayat costing serupa, nilai saran diganti statistik (median) lalu
 *         machine learning ringan. Setiap Hitung mencatat isian final officer sebagai bahan belajar dan menyusun Laporan Hitung.
 *         Rumus harga TIDAK di sini: tetap CostingEngine::calculateTier (trace() hanya menyalin rumusnya untuk penjelasan
 *         dan diverifikasi sama dengan hasil tersimpan).
 * Sumber hulu: Inquiry (BOM, part), ProDev (layout, BOP waste), prod_part_template, sys_constant COSTING, riwayat iqp_costing_recommendation.
 * Input: IqpCosting beserta parts dan priceTiers. Output: nilai isian + baris iqp_costing_recommendation; array Laporan Hitung.
 * Dipakai oleh: pages::inquiry.costing (Mode #1), costing-instant (Mode #2, belum disambung).
 * Status: BARU 2026-10-16, tahap 1. Mode Instant dan layar master template belum dibuat (butir terbuka).
 */
class CostingAdvisor
{
    /** Kolom TARIF yang boleh diisi dari template; geometri produk (plano, UP, ukuran cetak) milik Inquiry/ProDev, tidak disalin. */
    public const TARIF = [
        'i_ongkos_cetak_rim', 'i_ongkos_plong_lb', 'i_ongkos_lem_pc', 'i_ongkos_hotprint_pc', 'i_ongkos_kupas_pc', 'i_ongkos_qc_pack_pc', 'i_ongkos_kirim_pc',
        'i_film_rate', 'i_film_faktor', 'i_ongkos_plate_warna', 'i_pisau_faktor', 'i_pisau_rate_rp',
        'i_ongkos_print_pc', 'i_ongkos_diecut_pc', 'i_ongkos_slitter_pc', 'i_ongkos_slotter_pc', 'i_ongkos_join_pc', 'i_ongkos_partisi_pc', 'i_ongkos_layer_pc', 'i_ongkos_pisau_pc',
        'i_ongkos_laminasi_pc', 'i_lam_sheet_flat_pc', 'i_ongkos_lam_rp_cm2_sh', 'i_ongkos_lam_rp_cm2_sf',
    ];

    /** Kolom iqp_costing_part yang NOT NULL (dikosongkan = 0). */
    public const NOT_NULL = [
        'i_ongkos_cetak_rim', 'i_ongkos_plong_lb', 'i_ongkos_lem_pc', 'i_ongkos_hotprint_pc', 'i_ongkos_kupas_pc', 'i_pisau_faktor',
        'i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'i_lam_sheet_flat_pc', 'i_ongkos_laminasi_pc', 'i_ongkos_pisau_pc',
    ];

    public const LABELS = [
        'i_ongkos_cetak_rim' => 'Ongkos Cetak/RIM', 'i_ongkos_plong_lb' => 'Ongkos Plong/LB', 'i_ongkos_lem_pc' => 'Ongkos LEM/PC', 'i_ongkos_hotprint_pc' => 'Ongkos Hot Print/PC',
        'i_ongkos_kupas_pc' => 'Ongkos Kupas/PC', 'i_ongkos_qc_pack_pc' => 'Ongkos QC/PC', 'i_ongkos_kirim_pc' => 'Ongkos Kirim/PC', 'i_film_rate' => 'Film Rate', 'i_film_faktor' => 'Film Faktor',
        'i_ongkos_plate_warna' => 'Plate (Rp/warna)', 'i_pisau_faktor' => 'Pisau Faktor', 'i_pisau_rate_rp' => 'Pisau Rate (Rp/order)',
        'i_panjang_plano_cm' => 'Panjang Plano', 'i_lebar_plano_cm' => 'Lebar Plano', 'i_cut_per_plano' => '1 Plano dipotong jadi', 'i_isi_per_lb' => 'Isi dalam 1 LB',
        'i_uk_cetak_p_cm' => 'Ukuran Cetak P', 'i_uk_cetak_l_cm' => 'Ukuran Cetak L', 'grammage_id' => 'Gramatur / Bahan', 'i_dc_persen_override' => '% DC',
        'margin_pct' => 'Margin', 'tolerance_pct' => 'Toleransi',
    ];

    public const SOURCES = ['INQUIRY' => 'Inquiry', 'PRODEV' => 'ProDev', 'TEMPLATE' => 'Template', 'CONSTANT' => 'Konstanta', 'MASTER' => 'Master', 'ESTIMATE' => 'Perkiraan', 'STAT' => 'Statistik', 'ML' => 'ML', 'OFFICER' => 'Officer'];

    private const EPS = 0.00005;

    private array $ctxMemo = [];

    // ───────────── isi otomatis ─────────────

    /** Isi semua kotak kosong costing; kembalikan jumlah kotak yang terisi/diperbarui. */
    public function prefill(IqpCosting $costing): int
    {
        $costing->load(['parts', 'priceTiers']);
        $n = 0;
        foreach ($costing->parts as $part) {
            $n += $this->prefillPart($costing, $part);
        }
        foreach ($costing->priceTiers as $tier) {
            $n += $this->prefillTier($costing, $tier, $costing->parts->first());
        }

        return $n;
    }

    public function prefillPart(IqpCosting $c, IqpCostingPart $part): int
    {
        $ctx = $this->ctx($part);
        $type = $part->part_type_code;
        $qty = (float) ($c->priceTiers->firstWhere('is_primary', true)?->qty_pcs ?? $c->priceTiers->first()?->qty_pcs ?? 0);
        $n = 0;

        $tpl = $this->template($part, $c);

        // Gramatur: BOM Inquiry; bila BOM kosong, gramatur template terdekat. Selalu gramatur AKTIF terdekat di master (harga tmhpp).
        if ((! $part->grammage_id || ! MatGrammage::whereKey($part->grammage_id)->value('activate')) && $this->isDcFamily($type)) {
            [$g, $from] = $this->grammageFor($ctx['bom'], $tpl[0]->defaults ?? []);
            if ($g) {
                $n += (int) $this->fill($c, $part, null, $part, 'grammage_id', $g->id_grammage, $from === 'BOM' ? 'INQUIRY' : 'TEMPLATE', 'DERIVED', ($from === 'BOM' ? 'BOM Inquiry' : 'Template '.$tpl[0]->template_code).': gramatur aktif terdekat '.$g->grammage_name, 0, (float) $part->grammage_id);
                if ((float) $g->duplex_dc_pct > 0) {
                    $n += (int) $this->fill($c, $part, null, $part, 'i_dc_persen_override', (float) $g->duplex_dc_pct, 'MASTER', 'DERIVED', 'Master gramatur '.$g->grammage_name);
                }
            }
        }

        // Geometri: layout ProDev sudah mengisi (applyLayout); tanpa layout = simulasi imposisi dari dimensi produk
        if ($part->data_source !== 'PRODEV' && ! $ctx['geometry'] && $this->isDcFamily($type)) {
            $n += $this->estimateGeometry($c, $part);
        }

        // Tarif: statistik/ML > template > konstanta/master
        foreach (self::TARIF as $f) {
            $cand = $this->learned($f, $type, $qty, $c->id_costing)
                ?? ($tpl && array_key_exists($f, $tpl[0]->defaults) && ! $this->zeroToolFactor($f, $tpl[0]->defaults[$f])
                    ? [(float) $tpl[0]->defaults[$f], 'TEMPLATE', 'DEFAULT', 'Template '.$tpl[0]->template_code.($tpl[1] > 0 ? '' : ' (tipe part sama, nama belum cocok)'), 0]
                    : $this->constantFor($f, $type));
            if ($cand) {
                $n += (int) $this->fill($c, $part, null, $part, $f, $cand[0], $cand[1], $cand[2], $cand[3], $cand[4] ?? 0);
            }
        }

        return $n;
    }

    public function prefillTier(IqpCosting $c, IqpCostingPriceTier $tier, ?IqpCostingPart $part): int
    {
        $type = $part?->part_type_code;
        $qty = (float) $tier->qty_pcs;
        $n = 0;

        $m = $this->learned('margin_pct', $type, $qty, $c->id_costing)
            ?? [SysConstant::number('MARGIN_DEFAULT_PCT', 18), 'CONSTANT', 'DEFAULT', 'Konstanta COSTING MARGIN_DEFAULT_PCT', 0];
        $n += (int) $this->fill($c, null, $tier, $tier, 'margin_pct', $m[0], $m[1], $m[2], $m[3], $m[4] ?? 0);

        // Toleransi umum: median 9 dokumen HPP lama tmhpp (0 sampai 11, median 5) sampai riwayat cukup.
        $tol = $this->learned('tolerance_pct', $type, $qty, $c->id_costing)
            ?? [SysConstant::number('TOLERANCE_DEFAULT_PCT', 5), 'CONSTANT', 'DEFAULT', 'Konstanta COSTING TOLERANCE_DEFAULT_PCT (median HPP lama tmhpp)', 0];
        $n += (int) $this->fill($c, null, $tier, $tier, 'tolerance_pct', $tol[0], $tol[1], $tol[2], $tol[3], $tol[4] ?? 0);

        return $n;
    }

    private function fill(IqpCosting $c, ?IqpCostingPart $part, ?IqpCostingPriceTier $tier, Model $model, string $field, float $value, string $source, string $method, string $note, int $n = 0, float $blank = 0.0): bool
    {
        if ($value === 0.0) {
            return false; // saran 0 = "tidak dipakai", biarkan kosong tanpa penanda
        }

        $rec = $this->rec($c->id_costing, $part?->id_costing_part, $tier?->id_price_tier, $field);
        if ($rec && ($rec->is_override || $rec->zero_intent)) {
            return false;
        }

        $cur = $model->{$field};
        $empty = $cur === null || (float) $cur === 0.0 || (float) $cur === $blank;
        if ($rec && $rec->recommended_value !== null && ! $empty && abs((float) $cur - $rec->recommended_value) > self::EPS) {
            $rec->update(['is_override' => true]); // officer mengubah lewat jalur lain

            return false;
        }
        if (! $rec && ! $empty) {
            return false;
        }

        $same = ! $empty && abs((float) $cur - $value) < self::EPS;
        if (! $same) {
            $model->setAttribute($field, $value);
            $model->save();
        }
        $login = Auth::user()?->login ?? 'advisor';
        IqpCostingRecommendation::updateOrCreate(
            ['costing_id' => $c->id_costing, 'costing_part_id' => $part?->id_costing_part, 'tier_id' => $tier?->id_price_tier, 'field_key' => $field],
            ['recommended_value' => $value, 'source' => $source, 'method' => $method, 'source_note' => mb_substr($note, 0, 200), 'sample_count' => $n,
                'part_type_code' => $part?->part_type_code, 'created_by' => $login, 'updated_by' => $login],
        );

        return ! $same;
    }

    private function rec(int $costingId, ?int $partId, ?int $tierId, string $field): ?IqpCostingRecommendation
    {
        return IqpCostingRecommendation::where(['costing_id' => $costingId, 'costing_part_id' => $partId, 'tier_id' => $tierId, 'field_key' => $field])->first();
    }

    private function isDcFamily(?string $type): bool
    {
        return in_array($type, ['DC', 'DC+SF', 'DC+SHEET', 'KRAFT'], true);
    }

    // Faktor tooling 0 di template = "pakai default konstanta", bukan "tanpa tooling".
    private function zeroToolFactor(string $field, $v): bool
    {
        return in_array($field, ['i_film_rate', 'i_film_faktor', 'i_ongkos_plate_warna', 'i_pisau_faktor', 'i_pisau_rate_rp'], true) && (float) $v === 0.0;
    }

    private function constantFor(string $field, ?string $type): ?array
    {
        $c = fn (string $k, float $d) => SysConstant::number($k, $d);

        return match ($field) {
            'i_film_rate' => [$c('FILM_RATE_DEFAULT', 55), 'CONSTANT', 'DEFAULT', 'Konstanta COSTING FILM_RATE_DEFAULT', 0],
            'i_film_faktor' => [$c('FILM_FACTOR_DEFAULT', 0.25), 'CONSTANT', 'DEFAULT', 'Konstanta COSTING FILM_FACTOR_DEFAULT', 0],
            'i_ongkos_plate_warna' => [$c('PLATE_PER_COLOR', 90000), 'CONSTANT', 'DEFAULT', 'Konstanta COSTING PLATE_PER_COLOR', 0],
            'i_pisau_faktor' => [$c('KNIFE_FACTOR_DEFAULT', 0.25), 'CONSTANT', 'DEFAULT', 'Konstanta COSTING KNIFE_FACTOR_DEFAULT', 0],
            'i_pisau_rate_rp' => ($r = (float) ProdPartType::where('part_type_code', $type)->value('pisau_rate_rp')) > 0 ? [$r, 'MASTER', 'DEFAULT', 'Master tipe part '.$type, 0] : null,
            default => null,
        };
    }

    /** Template tarif terdekat: tipe part sama, nama paling mirip. [template, skor] atau null. */
    private function template(IqpCostingPart $part, IqpCosting $c): ?array
    {
        $words = fn (?string $s) => collect(preg_split('/[^a-z0-9]+/', strtolower((string) $s)))->filter(fn ($w) => strlen($w) >= 3)->unique();
        $mine = $words($part->part_name.' '.$c->product_name_draft.' '.$c->inquiry?->product_draft_name);

        return ProdPartTemplate::where('activate', true)->where('part_type_code', $part->part_type_code)->get()
            ->map(fn ($t) => [$t, $words($t->template_name.' '.$t->template_code)->intersect($mine)->count()])
            ->sortByDesc(fn ($x) => $x[1])->first();
    }

    /** [MatGrammage|null, 'BOM'|'TEMPLATE']: jenis dan gsm dari BOM Inquiry, bila kosong dari template; gramatur aktif terdekat. */
    private function grammageFor(array $bom, array $tplDefaults): array
    {
        $row = collect($bom)->firstWhere('primary', true) ?? collect($bom)->first();
        $name = strtoupper((string) ($row['material'] ?? ''));
        if (preg_match('/(\d{2,3})\s*GSM/', $name.' '.strtoupper((string) ($row['spec'] ?? '')), $m)) {
            $kind = str_contains($name, 'DUPLEX') ? 'DUPLEX' : (str_contains($name, 'KRAFT') ? 'KRAFT' : (str_contains($name, 'IVORY') ? 'IVORY' : null));

            return [$this->nearestGrammage($kind, (float) $m[1]), 'BOM'];
        }

        return [$this->nearestGrammage($tplDefaults['grammage_kind'] ?? null, (float) ($tplDefaults['grammage_value'] ?? 0)), 'TEMPLATE'];
    }

    /** Gramatur aktif sejenis dengan gsm terdekat (seri harga terbaru); bila seimbang pilih yang lebih tebal. */
    private function nearestGrammage(?string $kind, float $gsm): ?MatGrammage
    {
        if (! $kind || $gsm <= 0) {
            return null;
        }
        $key = fn ($g) => [abs((float) $g->grammage_value - $gsm), (float) $g->grammage_value < $gsm, -strtotime((string) $g->effective_date)];

        return MatGrammage::where('activate', true)->where('paper_kind', 'like', $kind.'%')->get()->sort(fn ($a, $b) => $key($a) <=> $key($b))->first();
    }

    /** Konteks Inquiry/ProDev per part, dihitung sekali per instance (prefill memanggilnya berulang). */
    private function ctx(IqpCostingPart $part): array
    {
        return $this->ctxMemo[$part->id_costing_part] ??= (new ProCostContextService)->forPart($part);
    }

    /** Isian inti yang bila masih kosong membuat hasil Hitung tidak berarti; dipakai peringatan "belum terisi". */
    public function missing(IqpCostingPart $part): array
    {
        if (! $this->isDcFamily($part->part_type_code)) {
            return [];
        }
        $zero = IqpCostingRecommendation::where('costing_part_id', $part->id_costing_part)->where('zero_intent', true)->pluck('field_key');

        return collect(['grammage_id', 'i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_ongkos_cetak_rim', 'i_ongkos_plong_lb'])
            ->reject(fn ($f) => $zero->contains($f) || (float) $part->{$f} > 0)->map(fn ($f) => self::LABELS[$f] ?? $f)->values()->all();
    }

    /** Kolom part yang dilacak (disarankan engine, officer boleh mengubah). */
    public static function managedPartFields(): array
    {
        return array_merge(self::TARIF, ['grammage_id', 'i_dc_persen_override', 'i_panjang_plano_cm', 'i_lebar_plano_cm', 'i_cut_per_plano', 'i_isi_per_lb', 'i_uk_cetak_p_cm', 'i_uk_cetak_l_cm']);
    }

    /**
     * Simulasi imposisi bila layout ProDev belum ada: ImpositionEngine (hanya dibaca, tidak menulis ke ProDev) mencoba semua plano
     * aktif dan memilih yang luas per pcs terkecil. Tepi memakai nilai layar Pro-Dev (gripper 12, margin 8, jarak 3 mm). Ditandai ESTIMATE.
     */
    private function estimateGeometry(IqpCosting $c, IqpCostingPart $part): int
    {
        $flatW = (float) ($part->i_dim_panjang_mm ?: $c->i_dim_panjang_mm);
        $flatH = (float) ($part->i_dim_lebar_mm ?: $c->i_dim_lebar_mm);
        $hasOwnPlano = (float) $part->i_panjang_plano_cm > 0 && ! $this->rec($c->id_costing, $part->id_costing_part, null, 'i_panjang_plano_cm'); // diisi officer, bukan perkiraan lama
        if ($flatW <= 0 || $flatH <= 0 || $hasOwnPlano) {
            return 0;
        }

        $engine = new ImpositionEngine;
        $best = null;
        foreach (MatPlanoSize::where('activate', true)->get() as $plano) {
            $r = $engine->run((float) $plano->width_mm, (float) $plano->height_mm, $flatW, $flatH, 3.0, 12.0, 8.0);
            if ($r['up'] < 1) {
                continue;
            }
            $perPcs = (float) $plano->width_mm * (float) $plano->height_mm / $r['up'];
            if (! $best || $perPcs < $best['per_pcs']) {
                $best = ['per_pcs' => $perPcs, 'plano' => $plano, 'r' => $r];
            }
        }
        if (! $best) {
            return 0;
        }

        $plano = $best['plano'];
        $lay = $best['r']['layout'];
        $printL = $lay['type'] === 'grid' ? $lay['cols'] * $lay['iw'] / 10 : $best['r']['area_w'] / 10;
        $printW = $lay['type'] === 'grid' ? $lay['rows'] * $lay['ih'] / 10 : $best['r']['area_h'] / 10;
        $note = "Simulasi imposisi (layout ProDev belum ada): plano {$plano->plano_name}, {$best['r']['up']} UP, {$best['r']['method']}, efisiensi ".$best['r']['efficiency_pct'].'%';
        $n = 0;
        // cut dan isi bernilai awal 1 (bawaan baris baru) = belum diisi
        foreach ([
            'i_panjang_plano_cm' => (float) $plano->width_mm / 10, 'i_lebar_plano_cm' => (float) $plano->height_mm / 10,
            'i_uk_cetak_p_cm' => round($printL, 2), 'i_uk_cetak_l_cm' => round($printW, 2), 'i_cut_per_plano' => 1, 'i_isi_per_lb' => $best['r']['up'],
        ] as $f => $v) {
            $n += (int) $this->fill($c, $part, null, $part, $f, (float) $v, 'ESTIMATE', 'DERIVED', $note, 0, 1.0);
        }

        return $n;
    }

    // ───────────── statistik dan ML ringan ─────────────

    /** Saran dari riwayat Hitung (costing lain, tipe part sama). [nilai, STAT|ML, DEFAULT-style tuple] atau null bila sampel kurang. */
    private function learned(string $field, ?string $type, float $qty, int $excludeCostingId): ?array
    {
        $min = (int) SysConstant::number('ADVISOR_MIN_SAMPLE_STAT', 5);
        $minMl = (int) SysConstant::number('ADVISOR_MIN_SAMPLE_ML', 30);
        $pts = $this->history($field, $type, $excludeCostingId);
        $n = $pts->count();
        if ($n < $min) {
            return null;
        }

        $median = $this->median($pts->pluck(0));
        if ($n >= $minMl && $qty > 0) {
            $mlLoo = $pts->map(fn ($p, $i) => abs($this->knn($pts->except($i), (float) $p[1]) - $p[0]))->avg();
            $medLoo = $pts->map(fn ($p, $i) => abs($this->median($pts->except($i)->pluck(0)) - $p[0]))->avg();
            if ($mlLoo < $medLoo) {
                return [round($this->knn($pts, $qty), 4), 'ML', 'ML', "Machine learning ringan (tetangga terdekat per qty), belajar dari {$n} costing serupa", $n];
            }
        }

        return [round($median, 4), 'STAT', 'STAT', "Median nilai officer, belajar dari {$n} costing serupa", $n];
    }

    /** [[nilai akhir, qty], ...] satu baris terakhir per costing/tier/part. */
    private function history(string $field, ?string $type, int $excludeCostingId): Collection
    {
        if (! $type) {
            return collect();
        }

        return IqpCostingRecommendation::where('field_key', $field)->where('part_type_code', $type)->whereNotNull('learned_at')->whereNotNull('final_value')
            ->whereIn('costing_id', IqpCosting::query()->select('id_costing')) // costing yang sudah dihapus tidak ikut belajar
            ->where('costing_id', '!=', $excludeCostingId)->orderByDesc('learned_at')->get()
            ->unique(fn ($r) => $r->costing_id.'-'.$r->costing_part_id.'-'.$r->tier_id)
            ->map(fn ($r) => [(float) $r->final_value, (float) $r->qty_pcs])->values();
    }

    private function median(Collection $v): float
    {
        $s = $v->sort()->values();
        $k = $s->count();

        return $k ? ($k % 2 ? $s[intdiv($k, 2)] : ($s[$k / 2 - 1] + $s[$k / 2]) / 2) : 0.0;
    }

    private function knn(Collection $pts, float $qty): float
    {
        $k = max(3, min(7, intdiv($pts->count(), 6)));

        return (float) $pts->sortBy(fn ($p) => abs(log(max($p[1], 1)) - log(max($qty, 1))))->take($k)->avg(0);
    }

    // ───────────── jejak officer ─────────────

    /** Officer mengubah isian: tandai override; nilai 0 = sengaja tidak dipakai. */
    public function markTouched(int $costingId, ?int $partId, ?int $tierId, string $field, $value, ?string $type = null): void
    {
        $rec = $this->rec($costingId, $partId, $tierId, $field);
        $login = Auth::user()?->login ?? 'advisor';
        $isZero = $value !== null && $value !== '' && (float) $value === 0.0;
        $same = $rec && $rec->recommended_value !== null && is_numeric($value) && abs((float) $value - $rec->recommended_value) < self::EPS;
        if ($same) {
            return;
        }
        if (! $rec) {
            IqpCostingRecommendation::create(['costing_id' => $costingId, 'costing_part_id' => $partId, 'tier_id' => $tierId, 'field_key' => $field,
                'source' => 'OFFICER', 'method' => 'DEFAULT', 'part_type_code' => $type, 'is_override' => true, 'zero_intent' => $isZero, 'created_by' => $login, 'updated_by' => $login]);

            return;
        }
        $rec->update(['is_override' => true, 'zero_intent' => $isZero, 'updated_by' => $login]);
    }

    /** Isian dikosongkan: bila ada nilai saran, kembalikan nilai itu (kosong tidak sengaja). */
    public function valueToRestore(int $costingId, ?int $partId, ?int $tierId, string $field): ?float
    {
        $rec = $this->rec($costingId, $partId, $tierId, $field);

        return $rec && $rec->recommended_value !== null && ! $rec->zero_intent ? (float) $rec->recommended_value : null;
    }

    /** Catat isian final saat Hitung sebagai bahan belajar. */
    public function learn(IqpCosting $c): int
    {
        $c->load(['parts', 'priceTiers']);
        $primaryQty = (float) ($c->priceTiers->firstWhere('is_primary', true)?->qty_pcs ?? $c->priceTiers->first()?->qty_pcs ?? 0);
        $now = now();
        $n = 0;
        $login = Auth::user()?->login ?? 'advisor';
        $save = function (?IqpCostingPart $p, ?IqpCostingPriceTier $t, string $field, $value, float $qty) use ($c, $now, $login, &$n) {
            $rec = $this->rec($c->id_costing, $p?->id_costing_part, $t?->id_price_tier, $field);
            if (! $rec && ($value === null || (float) $value === 0.0)) {
                return;
            }
            IqpCostingRecommendation::updateOrCreate(
                ['costing_id' => $c->id_costing, 'costing_part_id' => $p?->id_costing_part, 'tier_id' => $t?->id_price_tier, 'field_key' => $field],
                ['final_value' => $value === null ? null : (float) $value, 'qty_pcs' => $qty, 'learned_at' => $now, 'part_type_code' => $p?->part_type_code ?? $c->parts->first()?->part_type_code,
                    'source' => $rec->source ?? 'OFFICER', 'method' => $rec->method ?? 'DEFAULT', 'updated_by' => $login],
            );
            $n++;
        };
        foreach ($c->parts as $p) {
            foreach (self::TARIF as $f) {
                $save($p, null, $f, $p->{$f}, $primaryQty);
            }
        }
        foreach ($c->priceTiers as $t) {
            $type = $c->parts->first();
            foreach (['margin_pct', 'tolerance_pct'] as $f) {
                $save($type, $t, $f, $t->{$f}, (float) $t->qty_pcs);
            }
        }

        return $n;
    }

    // ───────────── Laporan Hitung ─────────────

    /** Rekaman sumber per isian (untuk penanda di layar): "costing_part_id|tier_id|field" => rec. */
    public function recsFor(int $costingId): Collection
    {
        return IqpCostingRecommendation::where('costing_id', $costingId)->get()->keyBy(fn ($r) => ($r->costing_part_id ?? 0).'|'.($r->tier_id ?? 0).'|'.$r->field_key);
    }

    public function report(IqpCosting $c, IqpCostingPriceTier $t): array
    {
        $c->load(['parts', 'priceTiers']);
        $qty = max((float) $t->qty_pcs, 0.0001);
        $now = $this->chain($c, $t, $qty, 0.0);
        $ok = abs($now['final'] - (float) $t->c_after_ppn_pc) < 0.01 && $now['final'] > 0;
        $recs = IqpCostingRecommendation::where('costing_id', $c->id_costing)->get();
        $withPpn = $c->ppn_mode !== 'NON_PPN';
        $mPct = (float) $t->margin_pct;
        $type = $c->parts->first()?->part_type_code;

        $split = ['Bahan (kertas dan sheet)' => 0.0, 'Proses (cetak, plong, QC, kirim, coating)' => 0.0, 'Tooling (film, plate, pisau)' => $now['tooling']];
        foreach ($c->parts as $p) {
            $ps = max((float) $p->qty_per_set, 1);
            $split['Bahan (kertas dan sheet)'] += ((float) $p->c_paper_cost_pc + (float) $p->c_sheet_cost_pc) * $ps;
            $split['Proses (cetak, plong, QC, kirim, coating)'] += ((float) $p->c_process_cost_pc + (float) $p->c_total_coating_pc) * $ps;
        }
        $other = $now['hpp'] - array_sum($split);
        if (abs($other) >= 0.005) {
            $split['Lain-lain (laminasi, SF EF)'] = $other;
        }

        $steps = [];
        if ($ok) {
            $steps = [
                ['HPP kotor', $now['hpp'], 'sum'],
                ...($withPpn ? [['Keluarkan PPN masukan bahan', -$now['credit'], 'minus']] : []),
                ['Toleransi', $now['tolNet'] - ($now['hpp'] - ($withPpn ? $now['credit'] : 0)), 'plus'],
                ['Pembulatan '.$t->rounding_mode, $now['rounded'] - $now['tolNet'], $now['rounded'] - $now['tolNet'] < 0 ? 'minus' : 'plus'],
                ['Dasar sebelum margin', $now['rounded'], 'sum'],
                ['Margin '.rtrim(rtrim(number_format($mPct, 2), '0'), '.').'%', $now['dpp'] - $now['rounded'], 'plus'],
                ...($withPpn ? [['PPN '.rtrim(rtrim(number_format((float) $c->ppn_pct, 2), '0'), '.').'%', $now['final'] - $now['dpp'], 'plus']] : []),
                ['Harga akhir', $now['final'], 'sum'],
            ];
        }

        $notes = [];
        $est = $recs->where('source', 'ESTIMATE')->where('is_override', false)->pluck('field_key')->map(fn ($f) => self::LABELS[$f] ?? $f)->unique()->values();
        if ($est->isNotEmpty()) {
            $notes[] = ['warn', 'Perhatian', 'Isian masih perkiraan ('.$est->implode(', ').'): layout ProDev belum ada. Nilainya otomatis diganti saat layout ProDev CONFIRMED, cek dulu sebelum diajukan.'];
        }
        $mrec = $recs->first(fn ($r) => $r->field_key === 'margin_pct' && $r->tier_id === $t->id_price_tier);
        if ($ok && $mrec && ! $mrec->is_override) {
            $up = $this->chain($c, $t, $qty, 1.0)['final'] - $now['final'];
            $notes[] = ['info', 'Info', 'Margin '.rtrim(rtrim(number_format($mPct, 2), '0'), '.').'% adalah nilai '.($mrec->method === 'DEFAULT' ? 'default' : 'saran engine ('.$mrec->method.')').', belum Anda ubah. Tiap tambah 1 poin margin, harga naik '.number_format($up, 2).' per pcs.'];
        }
        if ($ok && $now['hpp'] > 0 && $now['tooling'] / $now['hpp'] >= 0.1) {
            $dbl = $this->chain($c, $t, $qty * 2, 0.0)['final'];
            $notes[] = ['tip', 'Saran', 'Tooling '.number_format($now['tooling'] / $now['hpp'] * 100, 0).'% dari HPP. Pada qty '.number_format($qty * 2).' pcs harga sekitar '.number_format($dbl, 2).' per pcs ('.number_format(($now['final'] - $dbl) / $now['final'] * 100, 1).'% lebih murah).'];
        }
        if ($ok && (float) $t->tolerance_pct <= 0) {
            $notes[] = ['warn', 'Perhatian', 'Toleransi masih 0%. Engine belum punya sumber (waste proses BOP ProDev kosong, riwayat belum cukup); isi manual bila perlu.'];
        }

        return [
            'ok' => $ok, 'qty' => $qty, 'final' => (float) $t->c_after_ppn_pc, 'per_order' => (float) $t->c_after_ppn_pc * $qty,
            'steps' => $steps, 'split' => $split, 'hpp' => $now['hpp'], 'notes' => $notes, 'compare' => $this->compare($c, $t, $type, $qty),
            'sim' => $ok ? [
                ['Qty '.number_format($qty * 2).' pcs (tooling terbagi dua, biaya lain per pcs tetap)', $this->chain($c, $t, $qty * 2, 0.0)['final']],
                ['Margin +1 poin', $this->chain($c, $t, $qty, 1.0)['final']],
                ['Margin -1 poin', $this->chain($c, $t, $qty, -1.0)['final']],
            ] : [],
            'sources' => $recs->where('source', '!=', 'OFFICER')->where('is_override', false)->groupBy('source')->map->count()->all(),
            'overrides' => $recs->where('is_override', true)->count(),
            'learned' => $recs->whereNotNull('learned_at')->count(),
        ];
    }

    /** Pembanding: harga akhir costing lain, tipe part sama, qty setengah sampai dua kali. */
    private function compare(IqpCosting $c, IqpCostingPriceTier $t, ?string $type, float $qty): array
    {
        $ids = IqpCostingPart::where('part_type_code', $type)->where('costing_id', '!=', $c->id_costing)->pluck('costing_id');
        $vals = IqpCostingPriceTier::whereIn('costing_id', $ids)->where('c_after_ppn_pc', '>', 0)->whereBetween('qty_pcs', [$qty / 2, $qty * 2])->pluck('c_after_ppn_pc')->map(fn ($v) => (float) $v);
        $n = $vals->count();
        $final = (float) $t->c_after_ppn_pc;

        return $n >= 3
            ? ['n' => $n, 'median' => $m = $this->median($vals), 'min' => $vals->min(), 'max' => $vals->max(), 'pct' => $m > 0 ? ($final - $m) / $m * 100 : null]
            : ['n' => $n, 'median' => null, 'min' => null, 'max' => null, 'pct' => null];
    }

    /** Salinan rumus CostingEngine::calculateTier (tanpa menulis) agar Laporan bisa menjelaskan dan mensimulasikan; diverifikasi sama dengan hasil tersimpan. */
    private function chain(IqpCosting $c, IqpCostingPriceTier $t, float $qty, float $marginDelta): array
    {
        $withPpn = $c->ppn_mode !== 'NON_PPN';
        $credit = 1 - 1 / max(SysConstant::number('PPN_INPUT_MATERIAL_DIVISOR', 1.1), 0.0001);
        $hpp = $tooling = $creditPc = $tolGross = $tolNet = 0.0;
        foreach ($c->parts as $p) {
            $perSet = max((float) $p->qty_per_set, 1);
            $toolPc = (float) $p->c_tooling_cost_order / $qty;
            $gross = (float) $p->c_total_part_cost_pc * $perSet + $toolPc;
            $inputMat = ((float) $p->c_paper_cost_pc + (float) $p->c_sheet_cost_pc + (float) $p->c_lam_flat_cost_pc + (float) $p->c_sf_ef_cost_pc) * $perSet;
            $net = $withPpn ? $gross - $inputMat * $credit : $gross;
            $tt = min($p->tolerance_pct !== null ? (float) $p->tolerance_pct : (float) $t->tolerance_pct, 99.9999) / 100;
            if (in_array($p->part_type_code, ['SHEET', 'SHEET_DIGITAL'], true)) {
                $tolGross += $gross / (1 - $tt);
                $tolNet += $net / (1 - $tt);
            } else {
                $tolGross += $gross * (1 + $tt);
                $tolNet += $net + $gross * $tt;
            }
            $hpp += $gross;
            $tooling += $toolPc;
            $creditPc += $gross - $net;
        }
        $step = (float) $t->rounding_step;
        $round = fn (float $v) => $step <= 0 ? $v : match ($t->rounding_mode) {
            'UP', 'CEILING' => ceil($v / $step) * $step, 'DOWN', 'FLOOR' => floor($v / $step) * $step, 'NEAREST' => round($v / $step) * $step, default => $v,
        };
        $m = min((float) $t->margin_pct + $marginDelta, 99.9999) / 100;
        $mPpn = min(($t->margin_ppn_pct !== null ? (float) $t->margin_ppn_pct : (float) $t->margin_pct) + $marginDelta, 99.9999) / 100;
        $base = $withPpn ? $round($tolNet) : $round($tolGross);
        $dpp = $withPpn ? $base / (1 - $mPpn) : $base / (1 - $m);

        return ['hpp' => $hpp, 'tooling' => $tooling, 'credit' => $creditPc, 'tolNet' => $withPpn ? $tolNet : $tolGross, 'rounded' => $base, 'dpp' => $dpp, 'final' => $withPpn ? $dpp * (1 + (float) $c->ppn_pct / 100) : $dpp];
    }
}
