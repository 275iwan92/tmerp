<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk layar Material Category v2 (dropdown saling terkait Category 1 -> 5 yang membentuk kode item,
// docs/globaluserrules.md B.16), pesan aturan rantainya, dan cetakannya. "Category 1 … Category 5", Prefix, Level, tombol dan
// status tetap English. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        // layar + form
        'Item Code' => 'Kode Item', 'Under' => 'Di Bawah', 'Entries Below' => 'Entri di Bawahnya', 'Materials Using It' => 'Bahan yang Memakai',
        'Top of the chain' => 'Puncak rantai', 'No default' => 'Tanpa bawaan', 'Stock Asset Account' => 'Akun Aset Persediaan',
        'COGS / Expense Account' => 'Akun HPP / Beban', 'GR/IR Clearing Account' => 'Akun Kliring GR/IR',
        'Search category, item code...' => 'Cari kategori, kode item...',
        'The position is locked after saving.' => 'Posisi terkunci setelah disimpan.',
        'Prefix 1 + 2 + 3 + 4 + 5 along the chain.' => 'Prefix 1 + 2 + 3 + 4 + 5 sepanjang rantai.',
        'Used when the material has no account of its own.' => 'Dipakai bila bahan tidak punya akun sendiri.',
        '1 character (a letter or a digit).' => '1 karakter (huruf atau angka).',
        '1 to 3 characters (letters or digits).' => '1 sampai 3 karakter (huruf atau angka).',
        'Renaming also renames the category on :n materials.' => 'Mengganti nama ikut mengganti nama kategori pada :n bahan.',
        'Locked: :below entries below and :used materials build their item code from it.' => 'Terkunci: :below entri di bawahnya dan :used bahan membentuk kode itemnya dari prefix ini.',
        'Entries with a broken chain: :n. They cannot build an item code and are marked red in the list.' => 'Entri dengan rantai putus: :n. Tidak bisa membentuk kode item dan ditandai merah di daftar.',
        'Broken chain (:why): this entry does not sit under a complete chain, so it cannot build an item code and cannot be picked.' => 'Rantai putus (:why): entri ini tidak berada di bawah rantai yang utuh, sehingga tidak bisa membentuk kode item dan tidak bisa dipilih.',
        'Broken chain' => 'Rantai putus', 'no parent' => 'tanpa induk', 'broken chain' => 'rantai putus',
        'prefix not valid for its level' => 'prefix tidak sah untuk tingkatnya',
        // pesan simpan
        ':name saved — item code :code.' => ':name disimpan — kode item :code.', ':name activated.' => ':name diaktifkan.', ':name deactivated.' => ':name dinonaktifkan.',
        // aturan rantai (App\Services\Master\MaterialCategoryService)
        'The category level must be 1 to 5.' => 'Tingkat kategori harus 1 sampai 5.',
        'Category 1 is the top of the chain and has no parent.' => 'Category 1 adalah puncak rantai dan tidak punya induk.',
        'Pick Category :n first.' => 'Pilih Category :n dulu.',
        'Category :n must sit directly under a Category :p with a complete chain.' => 'Category :n harus berada tepat di bawah Category :p yang rantainya utuh.',
        ':name is inactive; activate it before adding entries below it.' => ':name nonaktif; aktifkan dulu sebelum menambah entri di bawahnya.',
        'Prefix :n takes exactly 1 character (a letter or a digit).' => 'Prefix :n harus tepat 1 karakter (huruf atau angka).',
        'Prefix :n takes 1 to 3 characters (letters or digits).' => 'Prefix :n harus 1 sampai 3 karakter (huruf atau angka).',
        'Prefix :p is already used by :name here — the item code would clash.' => 'Prefix :p sudah dipakai :name di sini — kode item akan bentrok.',
        'Item code :code is already produced by :path.' => 'Kode item :code sudah dihasilkan oleh :path.',
        'The prefix is locked: :below entries below it and :used materials already build their item code from it.' => 'Prefix terkunci: :below entri di bawahnya dan :used bahan sudah membentuk kode itemnya dari prefix ini.',
        ':name already exists here (prefix :p).' => ':name sudah ada di sini (prefix :p).',
        'Pick a postable account.' => 'Pilih akun yang bisa diposting.',
        ':name sits under an inactive category; activate the one above it first.' => ':name berada di bawah kategori nonaktif; aktifkan dulu yang di atasnya.',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
