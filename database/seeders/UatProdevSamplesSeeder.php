<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Contoh Pro-Dev per tipe (user 2026-10-10, "2 contoh tiap tipe"): New x2, Revise x2, Manual x2. Hanya ddt*.
// New / Revise = salinan inquiry IQ99MC0004 & IQ99MC0005 (tanpa product / CO / costing, belum Confirm ProDev -> muncul di To Do).
// Manual = prod_part tanpa inquiry (cara kerja sama dengan PDDUM2-*), layout DRAFT.
//   php artisan db:seed --class=UatProdevSamplesSeeder --env=mockup --force
class UatProdevSamplesSeeder extends Seeder
{
    // [no. inquiry baru, sumber, tipe, nama tampil]
    private const INQUIRIES = [
        ['IQ99PD0001', 'IQ99MC0005', 'NEW', 'Inner Box Arang 250g (Contoh New A)'],
        ['IQ99PD0002', 'IQ99MC0004', 'NEW', 'Tutup Box Sarung Exclusive (Contoh New B)'],
        ['IQ99PD0003', 'IQ99MC0005', 'REVISE', 'Inner Box Arang 250g (Contoh Revise A)'],
        ['IQ99PD0004', 'IQ99MC0004', 'REVISE', 'Tutup Box Sarung Exclusive (Contoh Revise B)'],
    ];

    // [kode, nama, p, l, t cm, up, kolom, baris, plano id]
    private const MANUAL = [
        ['PDDUM2-9', 'Folding Box Kosmetik (Contoh Manual A)', 22, 14, 6, 6, 3, 2, 3],
        ['PDDUM2-10', 'Sleeve Box Kado (Contoh Manual B)', 18, 10, 24, 8, 4, 2, 3],
    ];

    public function run(): void
    {
        $sdi = DB::connection('tm2sdi');
        $mst = DB::connection('tm2mst');
        if (! str_starts_with($sdi->getDatabaseName(), 'ddt') || ! str_starts_with($mst->getDatabaseName(), 'ddt')) {
            throw new RuntimeException('UatProdevSamplesSeeder hanya boleh di database dummy ddt* (--env=mockup).');
        }
        $now = now();

        foreach (self::INQUIRIES as [$no, $srcNo, $type, $name]) {
            if ($sdi->table('iqp_inquiry')->where('inquiry_no', $no)->exists()) {
                continue;
            }
            $src = (array) $sdi->table('iqp_inquiry')->where('inquiry_no', $srcNo)->firstOrFail();
            $srcId = $src['id_inquiry'];
            unset($src['id_inquiry']);
            $row = array_merge($src, [
                'inquiry_no' => $no, 'inquiry_date' => $now->toDateString(), 'deadline_quotation' => $now->copy()->addDays(7)->toDateString(),
                'product_draft_name' => $name, 'inquiry_type' => $type, 'co_id' => null, 'sub_state' => 'SUBMITTED',
                'product_id' => $type === 'REVISE' ? $src['product_id'] : null, 'parent_inquiry_id' => $type === 'REVISE' ? $srcId : null,
                'revision_no' => $type === 'REVISE' ? 1 : 0,
                'revise_scope' => $type === 'REVISE' ? 'DIMENSION,PROCESS' : null,
                'revise_reason' => $type === 'REVISE' ? 'Ukuran bentangan dan urutan proses berubah (contoh dummy).' : null,
                'sales_note' => 'Contoh dummy Pro-Dev '.$type.' (salinan '.$srcNo.').',
                'stat_confirm_prodev' => 0, 'confirm_prodev_by' => null, 'confirm_prodev_at' => null,
                'stat_confirm_procost' => 0, 'confirm_procost_by' => null, 'confirm_procost_at' => null,
                'stat_cancel' => 0, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY',
            ]);
            $sdi->transaction(function () use ($sdi, $row, $srcId, $now) {
                $id = $sdi->table('iqp_inquiry')->insertGetId($this->insertable($sdi, 'iqp_inquiry', $row));
                $this->copyChildren($sdi, $srcId, $id, $now);
            });
            $this->command?->info("{$no} ({$type}) dibuat dari {$srcNo}.");
        }

        foreach (self::MANUAL as [$code, $name, $l, $w, $h, $up, $cols, $rows, $plano]) {
            if ($mst->table('prod_part')->where('part_code', $code)->exists()) {
                continue;
            }
            $base = ['created_by' => 'uat', 'updated_by' => 'uat', 'created_at' => $now, 'updated_at' => $now, 'data_origin' => 'DUMMY'];
            $product = $mst->table('prod_product')->insertGetId($base + ['product_code' => $code, 'product_name' => $name, 'activate' => 1]);
            $part = $mst->table('prod_part')->insertGetId($base + ['product_id' => $product, 'part_code' => $code, 'part_name' => $name,
                'cm_length' => $l, 'cm_width' => $w, 'cm_height' => $h, 'activate' => 1]);
            $group = $mst->table('prod_layout_group')->insertGetId($base + ['product_id' => $product, 'mastercard_code' => $code, 'plano_size_id' => $plano,
                'method' => 'TEGAK', 'up_count' => $up, 'status' => 'DRAFT',
                'layout_detail' => json_encode(['layout' => ['cols' => $cols, 'rows' => $rows, 'type' => 'grid', 'rot' => false]])]);
            $mst->table('prod_layout_group_part')->insert(['id_layout_group' => $group, 'part_id' => $part, 'qty_up_in_group' => 1, 'created_at' => $now]);
            $this->command?->info("{$code} (Manual) dibuat.");
        }
    }

    // Kolom generated (mis. primary_key_guard) tidak boleh ikut di-insert.
    private function insertable($db, string $table, array $row): array
    {
        $generated = $db->table('information_schema.COLUMNS')->where('TABLE_SCHEMA', $db->getDatabaseName())->where('TABLE_NAME', $table)
            ->where('EXTRA', 'like', '%GENERATED%')->where('EXTRA', 'not like', 'DEFAULT_GENERATED%')->pluck('COLUMN_NAME')->all();

        return array_diff_key($row, array_flip($generated));
    }

    private function copyChildren($sdi, int $srcInquiry, int $newInquiry, $now): void
    {
        $sdi->table('iqp_tier')->where('inquiry_id', $srcInquiry)->get()->each(function ($t) use ($sdi, $newInquiry, $now) {
            $r = (array) $t;
            unset($r['id_tier']);
            $sdi->table('iqp_tier')->insert($this->insertable($sdi, 'iqp_tier', array_merge($r, ['inquiry_id' => $newInquiry, 'created_at' => $now, 'updated_at' => $now])));
        });

        // Contoh ini hanya punya part tunggal (tanpa induk), jadi parent_part_id tidak perlu dipetakan.
        $sdi->table('iqp_part')->where('inquiry_id', $srcInquiry)->get()->each(function ($p) use ($sdi, $newInquiry, $now) {
            $r = (array) $p;
            $old = $r['id_part'];
            unset($r['id_part']);
            $new = $sdi->table('iqp_part')->insertGetId($this->insertable($sdi, 'iqp_part', array_merge($r, ['inquiry_id' => $newInquiry, 'prod_part_id' => null,
                'created_at' => $now, 'updated_at' => $now])));
            foreach (['iqp_part_material', 'iqp_part_ink', 'iqp_part_process', 'iqp_part_finishing'] as $t) {
                $pk = 'id_'.substr($t, 4);
                $sdi->table($t)->where('part_id', $old)->get()->each(function ($c) use ($sdi, $t, $pk, $new, $now) {
                    $x = (array) $c;
                    unset($x[$pk]);
                    $sdi->table($t)->insert($this->insertable($sdi, $t, array_merge($x, ['part_id' => $new, 'created_at' => $now, 'updated_at' => $now])));
                });
            }
        });
    }
}
