<?php

/*
 * REMARK
 * Tujuan       : Engine harga beli lembar gelombang (BF, BCF, single wall, double wall) per supplier, dari Excel Jo (sheet UJK, RCA): up, lebar trim, running meter, tingkat diskon, harga per lembar.
 * Sumber hulu  : sup_corrugated_rule (lebar trim maksimum + diskon per tingkat), sup_corrugated_index (index per m2 per substance), tarif PPN pada tanggal dokumen (TaxRate), nama bahan (substance, ukuran) lewat PaperSpec.
 * Input        : MatRaw, id supplier, jumlah order (lembar), tanggal dokumen.
 * Output       : Rincian (up, lebar trim, running meter, tingkat, diskon, harga) dan harga di luar PPN per lembar, atau null dengan alasan (di bawah minimum, substance tidak ada, ukuran tidak terbaca).
 * Dipakai oleh : PaperPriceEngine::priceFor (harga baris PO; PR tanpa jumlah tidak mendapat harga), tes CorrugatedSheetPriceEngineTest.
 * Status       : Aktif. Rumus Excel: up = INT((lebar_maks - 2) / lebar_serat); trim = CEILING(lebar_serat x up + 2, 5); RM = jumlah / up x panjang / 100; harga = (trim x panjang / 10000 x index / up) x (1 - diskon); diskon = nilai tabel, petugas boleh menimpa harga; tidak menulis apa pun.
 */

namespace App\Services\Pur;

use App\Models\Master\MatRaw;
use App\Models\Master\SupCorrugatedIndex;
use App\Models\Master\SupCorrugatedRule;
use Carbon\CarbonInterface;

final class CorrugatedSheetPriceEngine
{
    // Batas bawah running meter per tingkat -> kolom diskon di sup_corrugated_rule.
    public const TIERS = [5000 => 'disc_5000', 2000 => 'disc_2000', 1000 => 'disc_1000', 500 => 'disc_500', 300 => 'disc_300'];

    private const SHEETS_PER_REAM = 500;

    // Bahan lembar gelombang = nama diawali SH atau SHEET dan memuat substance (mis. K150/M125/K125).
    public function matches(MatRaw $m): bool
    {
        $name = (string) $m->material_name;

        return (bool) preg_match('/^\s*(SH|SHEET)\b/i', $name) && PaperSpec::substanceKey($name) !== '';
    }

    // Harga di luar PPN per satuan beli (LBR / PCS / kosong = per lembar, RIM = 500 lembar); null bila tidak ada harga.
    public function priceFor(MatRaw $m, ?int $supplierId, ?float $qty = null, ?CarbonInterface $on = null): ?float
    {
        $price = $this->quote($m, $supplierId, $qty, $on)['price'];
        $uom = strtoupper((string) $m->uom_purchase);

        return $price === null || ! in_array($uom, ['', 'LBR', 'PCS', 'RIM'], true) ? null : round($uom === 'RIM' ? $price * self::SHEETS_PER_REAM : $price, 4);
    }

    // Rincian perhitungan. 'price' = per lembar di luar PPN; 'reason' terisi bila price null.
    public function quote(MatRaw $m, ?int $supplierId, ?float $qty = null, ?CarbonInterface $on = null): array
    {
        $out = ['price' => null, 'reason' => null, 'ups' => null, 'trim_width' => null, 'running_meter' => null, 'tier' => null, 'discount_pct' => null, 'index' => null];
        $name = (string) $m->material_name;
        $size = PaperSpec::sizeM($name);
        $rule = $supplierId ? $this->rule($supplierId, $on) : null;
        $index = $supplierId ? $this->index($supplierId, $name, $on) : null;
        $uom = strtoupper((string) $m->uom_purchase);
        $sheets = $qty === null ? 0.0 : $qty * ($uom === 'RIM' ? self::SHEETS_PER_REAM : 1);
        $fail = fn (string $why) => array_merge($out, ['reason' => $why]);
        if (! $size) {
            return $fail('size');
        }
        if (! $rule) {
            return $fail('rule');
        }
        if (! $index) {
            return $fail('index');
        }
        if ($sheets <= 0) {
            return $fail('qty');
        }

        // Ukuran pertama di nama = lebar serat, kedua = panjang (cm).
        [$width, $length] = [$size[0] * 100, $size[1] * 100];
        $ups = (int) floor(((float) $rule->max_trim_width_cm - 2) / $width);
        if ($ups < 1) {
            return $fail('width');
        }
        $trim = ceil(($width * $ups + 2) / 5) * 5;
        $rm = $sheets / $ups * ($length / 100);
        [$tier, $disc] = $this->tier($rule, $rm);
        $out = array_merge($out, ['ups' => $ups, 'trim_width' => $trim, 'running_meter' => round($rm, 2), 'tier' => $tier, 'discount_pct' => $disc, 'index' => (float) $index->index_per_m2]);
        $div = app(PaperPriceEngine::class)->divisor($index->price_basis, $on);
        if ($tier === null || $disc === null) {
            return array_merge($out, ['reason' => 'minimum']);
        }
        if (! $div) {
            return array_merge($out, ['reason' => 'ppn']);
        }
        $incl = ($trim * $length / 10000 * (float) $index->index_per_m2 / $ups) * (1 - $disc / 100);

        return array_merge($out, ['price' => round($incl / $div, 4)]);
    }

    // Aturan yang berlaku: aktif, tanggal berlaku <= tanggal dokumen, yang terbaru.
    public function rule(int $supplierId, ?CarbonInterface $on = null): ?SupCorrugatedRule
    {
        return SupCorrugatedRule::query()->where('supplier_id', $supplierId)->where('activate', true)
            ->whereDate('effective_date', '<=', ($on ?? now())->toDateString())->orderByDesc('effective_date')->first();
    }

    // Index yang berlaku: substance sama dengan nama bahan (kunci token), kalau tidak ada = substance umum SW / DW; yang tanggalnya paling baru.
    public function index(int $supplierId, string $name, ?CarbonInterface $on = null): ?SupCorrugatedIndex
    {
        $key = PaperSpec::substanceKey($name);
        $rows = SupCorrugatedIndex::query()->where('supplier_id', $supplierId)->where('activate', true)
            ->whereDate('effective_date', '<=', ($on ?? now())->toDateString())->orderByDesc('effective_date')->orderByDesc('id_corr_index')->get();

        return $rows->first(fn (SupCorrugatedIndex $r) => PaperSpec::substanceKey($r->substance_name) === $key)
            ?? $rows->first(fn (SupCorrugatedIndex $r) => strtoupper(trim($r->substance_name)) === PaperSpec::wall($name));
    }

    // Tingkat dan diskon (persen) untuk running meter: tingkat tertinggi yang batas bawahnya terlewati dan diskonnya terisi; di bawah 300 = null.
    public function tier(SupCorrugatedRule $rule, float $rm): array
    {
        foreach (self::TIERS as $min => $col) {
            if ($rm >= $min && $rule->{$col} !== null) {
                return [$min, (float) $rule->{$col}];
            }
        }

        return [null, null];
    }
}
