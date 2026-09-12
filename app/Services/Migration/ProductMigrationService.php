<?php

namespace App\Services\Migration;

use App\Models\Legacy\MstItem;
use App\Models\Master\ProdProduct;

// Engine migrasi tm2old.mst_item (kandidat produk jadi) -> tm2mst.prod_product.
// Dipicu manual dari tab "Migrasi Data Legacy" (Setup, admin-only).
// Idempotent: matching by old_item_code, aman diklik berkali-kali.
//
// Kriteria "kandidat produk jadi": as_fgs=1 (flag finished-goods eksplisit
// di-set true di sumber -- lebih bisa dipercaya drpd material_class yg 45%
// kosong utk sisi raw material).
//
// SENGAJA TIDAK migrasi dimensi fisik (length/width/height sumber) --
// satuannya tidak jelas (mm vs cm tidak konsisten di data lama) dan
// prod_product.cm_* mengasumsikan cm. Salah tebak satuan lebih berbahaya
// drpd dikosongkan -- biarkan diisi ulang manual/lewat ProDev saat produk
// benar-benar dipakai. customer_id/category_id juga dibiarkan NULL, tidak
// ada mapping sumber yg bisa dipercaya -- klasifikasi manual oleh admin.
class ProductMigrationService
{
    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $report = [
            'products' => ['created' => 0, 'updated' => 0, 'unchanged' => 0],
            'errors' => [],
        ];

        MstItem::query()
            ->where('as_fgs', 1)
            ->orderBy('item_code')
            ->chunk(200, function ($chunk) use (&$report, $actor) {
                foreach ($chunk as $item) {
                    try {
                        $oldCode = trim((string) $item->item_code);
                        if ($oldCode === '') {
                            continue;
                        }

                        $data = [
                            'product_name' => $this->blank($item->item_name) ?? $oldCode,
                            'description' => $this->blank($item->note),
                            'uom_stock' => $this->blank($item->uom_stock),
                            'uom_sales' => $this->blank($item->uom_sales),
                            'activate' => ((int) $item->active_item) === 1,
                            'old_item_code' => $oldCode,
                            'old_item_name' => $this->blank($item->item_name),
                            'updated_by' => $actor,
                        ];

                        $existing = ProdProduct::where('old_item_code', $oldCode)->first();

                        if ($existing) {
                            $changed = collect($data)->except(['updated_by'])
                                ->some(fn ($value, $key) => $existing->{$key} != $value);

                            if ($changed) {
                                $existing->update($data);
                                $report['products']['updated']++;
                            } else {
                                $report['products']['unchanged']++;
                            }
                        } else {
                            ProdProduct::create(array_merge($data, [
                                'product_code' => $oldCode,
                                'created_by' => $actor,
                            ]));
                            $report['products']['created']++;
                        }
                    } catch (\Throwable $e) {
                        $report['errors'][] = "{$item->item_code}: {$e->getMessage()}";
                    }
                }
            });

        return $report;
    }

    protected function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
