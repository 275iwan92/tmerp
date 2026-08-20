<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Tuntaskan migrasi legacy m_* -> prod_*/ref_* yang KETINGGALAN:
// 1. m_product_type & m_product_type_param SUDAH 1:1 pindah ke
//    prod_type/prod_type_param sebelumnya. prod_type_size TERNYATA masih
//    kosong padahal prod_type_size_val SUDAH berisi 255 baris yang
//    mereferensikan type_size_id 1-83 — orphan, nunggu induknya. ID
//    id_type_size DIPERTAHANKAN SAMA PERSIS dengan size_id lama supaya
//    prod_type_size_val yang sudah ada otomatis nyambung tanpa diubah.
//    Dipakai nyata oleh resources/views/pages/inquiry/form.blade.php
//    (preset ukuran per tipe produk) — sebelum ini jalan, dropdown ukuran
//    di form Inquiry New kosong.
// 2. Tabel ref_param_option TERNYATA sudah ada (kolom param_key — sengaja
//    dicocokkan dengan prod_type_param.param_key, bukan column_name),
//    dibuat lebih dulu tapi belum pernah diisi. Migrasi 26 baris dari
//    m_ref_param_option ke sana.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');

        if ($db->table('prod_type_size')->count() === 0) {
            $sizes = $db->table('m_product_type_size')->orderBy('size_id')->get();

            foreach ($sizes as $s) {
                $db->table('prod_type_size')->insert([
                    'id_type_size' => $s->size_id,
                    'type_code' => $s->product_type_code,
                    'size_code' => $this->slugify($s->size_name),
                    'size_label' => $s->size_name,
                    'sort_order' => $s->sort_order,
                    'is_active' => $s->is_active,
                    'created_at' => $s->create_log,
                    'updated_at' => $s->update_log,
                ]);
            }
        }

        if ($db->table('ref_param_option')->count() === 0) {
            $options = $db->table('m_ref_param_option')->orderBy('column_name')->orderBy('sort_order')->get();

            foreach ($options as $o) {
                $db->table('ref_param_option')->insert([
                    'param_key' => $o->column_name,
                    'option_code' => $o->option_code,
                    'option_label' => $o->option_label,
                    'sort_order' => $o->sort_order,
                    'is_active' => $o->is_active,
                ]);
            }
        }
    }

    protected function slugify(string $label): string
    {
        $slug = mb_strtoupper(trim($label));
        $slug = preg_replace('/[^A-Z0-9]+/', '_', $slug);
        $slug = trim((string) $slug, '_');

        return mb_substr($slug, 0, 40);
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');
        $db->table('prod_type_size')->whereBetween('id_type_size', [1, 83])->delete();
        $db->table('ref_param_option')->truncate();
    }
};
