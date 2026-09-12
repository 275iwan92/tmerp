<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// Keluarga Master Card tm2old -> tm2mst.prod_card_legacy dkk.
//
// Ini data rekayasa produk yang selama ini cuma hidup di ScriptCase: bahan
// (mst_card_rawmat), urutan proses + mesin (mst_card_process), pisau
// (mst_card_diecut/mst_diecut). Jembatan ke item ada di mst_product_mc
// (item_code <-> mastercard_id), jadi rantainya:
//   mst_item -> mst_product_mc -> mst_card -> card_process/_rawmat/_diecut
//
// mst_card_rawmat_copy (5.091) SENGAJA dilewati -- salinan cadangan manual
// dari mst_card_rawmat, bukan data independen.
// Idempoten via truncate+reload.
class MasterCardMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $report = [
            'diecut' => ['inserted' => 0],
            'cards' => ['inserted' => 0],
            'card_process' => ['inserted' => 0],
            'card_rawmat' => ['inserted' => 0, 'no_material_match' => 0],
            'card_diecut' => ['inserted' => 0],
            'components' => ['inserted' => 0, 'no_item_match' => 0, 'no_card_match' => 0],
            'errors' => [],
        ];

        $materialMap = $this->preloadMap('tm2mst', 'mat_raw', 'old_item_code', 'material_id');
        $productMap = $this->preloadMap('tm2mst', 'prod_product', 'old_item_code', 'id_product');
        $supplierMap = $this->preloadMap('tm2mst', 'sup_supplier', 'legacy_vendor_code', 'id_supplier');

        foreach (['prod_card_process_legacy', 'prod_card_rawmat_legacy', 'prod_card_diecut_legacy', 'prod_product_component_legacy', 'prod_card_legacy', 'prod_diecut_legacy'] as $t) {
            DB::connection('tm2mst')->table($t)->delete();
        }

        $now = now();

        // --- 1. Pisau (dipakai sbg referensi kode oleh card_process/card_diecut) ---
        $diecuts = [];
        foreach (DB::connection('tm2old')->table('mst_diecut')->get() as $d) {
            $code = trim((string) $d->diecut_code);
            if ($code === '') {
                continue;
            }
            // Kunci dedup disamakan dgn perilaku unique index MySQL
            // (utf8mb4_unicode_ci = case-insensitive) + spasi ganda
            // dirapikan -- kalau tidak, varian beda huruf/spasi lolos di
            // PHP tapi tabrakan di DB.
            $key = mb_strtoupper(preg_replace('/\s+/', ' ', $code));
            if (isset($diecuts[$key])) {
                continue;
            }
            $diecuts[$key] = [
                'diecut_code' => $code,
                'description' => $this->blank($d->description),
                'diecut_length' => $d->diecut_length,
                'diecut_width' => $d->diecut_width,
                'product_length' => $d->product_length,
                'product_width' => $d->product_width,
                'product_height' => $d->product_height,
                'product_diameter' => $d->product_diameter,
                'product_circumference' => $d->product_circumference,
                'product_area' => $d->product_area,
                'attachment' => $this->blank($d->attachment),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $report['diecut']['inserted'] = $this->insertChunked('tm2mst', 'prod_diecut_legacy', array_values($diecuts));

        // --- 2. Header Master Card ---
        $cards = [];
        foreach (DB::connection('tm2old')->table('mst_card')->orderBy('mastercard_id')->get() as $c) {
            $cards[(int) $c->mastercard_id] = [
                'legacy_mastercard_id' => (int) $c->mastercard_id,
                'card_name' => $this->blank($c->mastercard_name),
                'card_description' => $this->blank($c->mastercard_description),
                'form_joint' => $this->blank($c->form_joint),
                'type_joint' => $this->blank($c->type_joint),
                'design_filename' => $this->blank($c->design_print_filename),
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 'migration:tm2old.mst_card',
            ];
        }
        $report['cards']['inserted'] = $this->insertChunked('tm2mst', 'prod_card_legacy', array_values($cards));

        $cardIdMap = DB::connection('tm2mst')->table('prod_card_legacy')
            ->pluck('id_card_legacy', 'legacy_mastercard_id')->all();

        // --- 3. Proses per card ---
        $processes = [];
        DB::connection('tm2old')->table('mst_card_process')->orderBy('mastercard_process_id')
            ->chunk(2000, function ($chunk) use (&$processes, $cardIdMap, $supplierMap, $now) {
                foreach ($chunk as $p) {
                    $cardId = $cardIdMap[(int) $p->mastercard_id] ?? null;
                    if ($cardId === null) {
                        continue;
                    }
                    $processes[] = [
                        'card_legacy_id' => $cardId,
                        'process_seq' => $p->process_seq,
                        'process_name' => $this->blank($p->process_name),
                        'machine_name' => $this->blank($p->machine_name),
                        'diecut_code' => $this->blank($p->diecut_code),
                        'pack_contains' => $p->pack_contains,
                        'pack_uom' => $this->blank($p->pack_uom),
                        'supplier_id' => $supplierMap[trim((string) $p->supplier_code)] ?? null,
                        'process_notes' => $this->blank($p->process_notes),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            });
        $report['card_process']['inserted'] = $this->insertChunked('tm2mst', 'prod_card_process_legacy', $processes, 2000);

        // --- 4. Bahan per card ---
        $rawmats = [];
        DB::connection('tm2old')->table('mst_card_rawmat')->orderBy('mastercard_rawmat_id')
            ->chunk(2000, function ($chunk) use (&$rawmats, $cardIdMap, $materialMap, $now, &$report) {
                foreach ($chunk as $r) {
                    $cardId = $cardIdMap[(int) $r->mastercard_id] ?? null;
                    if ($cardId === null) {
                        continue;
                    }
                    $code = trim((string) $r->rawmat_code);
                    $materialId = $materialMap[$code] ?? null;
                    if ($materialId === null) {
                        $report['card_rawmat']['no_material_match']++;
                    }
                    $rawmats[] = [
                        'card_legacy_id' => $cardId,
                        'material_id' => $materialId,
                        'legacy_rawmat_code' => $code !== '' ? $code : null,
                        'rawmat_grade' => $r->rawmat_grade,
                        'rawmat_category' => $this->blank($r->rawmat_category),
                        'ink_seq' => $r->ink_seq,
                        'plano_contains' => $r->plano_contains,
                        'image_contains' => $r->image_contains,
                        'cutsize_length' => $r->rawmat_cutsize_length,
                        'cutsize_width' => $r->rawmat_cutsize_width,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            });
        $report['card_rawmat']['inserted'] = $this->insertChunked('tm2mst', 'prod_card_rawmat_legacy', $rawmats, 2000);

        // --- 5. Pisau per card ---
        $cardDiecuts = [];
        foreach (DB::connection('tm2old')->table('mst_card_diecut')->get() as $cd) {
            $cardId = $cardIdMap[(int) $cd->mastercard_id] ?? null;
            if ($cardId === null) {
                continue;
            }
            $cardDiecuts[] = [
                'card_legacy_id' => $cardId,
                'diecut_code' => $this->blank($cd->diecut_code),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $report['card_diecut']['inserted'] = $this->insertChunked('tm2mst', 'prod_card_diecut_legacy', $cardDiecuts, 2000);

        // --- 6. Jembatan item <-> card ---
        $components = [];
        DB::connection('tm2old')->table('mst_product_mc')->orderBy('detail_product_id')
            ->chunk(2000, function ($chunk) use (&$components, $cardIdMap, $productMap, $materialMap, $now, &$report) {
                foreach ($chunk as $m) {
                    $code = trim((string) $m->item_code);
                    $productId = $productMap[$code] ?? null;
                    $materialId = $productId === null ? ($materialMap[$code] ?? null) : null;
                    if ($productId === null && $materialId === null) {
                        $report['components']['no_item_match']++;
                    }
                    $cardId = $cardIdMap[(int) $m->mastercard_id] ?? null;
                    if ($cardId === null) {
                        $report['components']['no_card_match']++;
                    }
                    $components[] = [
                        'legacy_detail_product_id' => (int) $m->detail_product_id,
                        'product_id' => $productId,
                        'material_id' => $materialId,
                        'legacy_item_code' => $code !== '' ? $code : null,
                        'card_legacy_id' => $cardId,
                        'component_type' => $this->blank($m->component_type),
                        'stat_active' => ((int) $m->stat_active) === 1,
                        'total_rawmat' => $m->total_rawmat,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            });
        $report['components']['inserted'] = $this->insertChunked('tm2mst', 'prod_product_component_legacy', $components, 2000);

        return $report;
    }
}
