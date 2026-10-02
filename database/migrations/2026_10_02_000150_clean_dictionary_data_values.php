<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pembersihan sekali jalan: kamus `ui` terisi NILAI DATA (no. dokumen, nama customer / product) karena layar mengirimnya
// ke prop teks komponen x-dl::* (judul form, judul kartu) tanpa raw() -- lihat Devlib\Lang::raw(). Yang dihapus HANYA baris
// yang dicatat otomatis (created_by = 'auto') dan belum punya terjemahan di bahasa mana pun, jadi tidak ada hasil kerja
// penerjemah yang hilang; label sungguhan yang ikut terhapus tercatat lagi saat layarnya dibuka.
return new class extends Migration
{
    // tabel => kolom nama (koneksi tm2mst / tm2sdi; di mockup.test = ddt*).
    private const NAMES = [
        'tm2mst' => [
            // tanpa kolom nickname: nama panggilan pendek (mis. "CC") bisa sama dengan label layar
            'cust_customer' => ['customer_name', 'trade_name', 'contact_name'],
            'sup_supplier' => ['supplier_name', 'contact_name'],
            'prod_product' => ['product_name'],
            'prod_master_card' => ['product_name'],
        ],
        'tm2sdi' => ['iqp_inquiry' => ['prospect_name', 'product_draft_name']],
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $ids = array_keys($this->polluted());
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::connection('tm2sys')->table('sys_dictionary')->whereIn('id_dictionary', $chunk)->delete();  // baris _lang ikut (cascade)
        }
        if ($ids) {
            Dictionary::flush();
        }
    }

    public function down(): void
    {
        // baris yang dihapus adalah nilai data, tidak dikembalikan
    }

    /** @return array<int, array{0: string, 1: string}> id_dictionary => [source_en, alasan] */
    public function polluted(): array
    {
        $db = DB::connection('tm2sys');
        $candidates = $db->table('sys_dictionary as d')->where('d.context', 'ui')->where('d.created_by', 'auto')
            ->whereNotExists(fn ($q) => $q->from('sys_dictionary_lang as l')->whereColumn('l.id_dictionary', 'd.id_dictionary')->where('l.text', '<>', ''))
            ->pluck('d.source_en', 'd.id_dictionary');
        $names = $this->names();
        // Teks hasil terjemahan yang tercatat lagi sebagai "sumber English" (layar memanggil t() lalu mengirimnya ke komponen).
        $translated = array_flip($db->table('sys_dictionary_lang as l')->join('sys_dictionary as d', 'd.id_dictionary', '=', 'l.id_dictionary')
            ->where('d.context', 'ui')->where('l.text', '<>', '')->pluck('l.text')->all());
        // Kalimat berpola ':n materials ...' yang tercatat lagi setelah angkanya diisi.
        $templates = [];
        foreach (array_merge($db->table('sys_dictionary')->where('context', 'ui')->where('source_en', 'regexp', ':[a-z]+')->pluck('source_en')->all(),
            array_filter(array_keys($translated), fn ($t) => preg_match('/:[a-z]+\b/', $t))) as $t) {
            $templates[$t] = '/^'.preg_replace('/\\\\:[a-z]+\b/', '.+?', preg_quote($t, '/')).'$/su';
        }

        $out = [];
        foreach ($candidates as $id => $source) {
            $parts = array_map('trim', preg_split('/ [·—] |: /u', $source));
            $reason = match (true) {
                Dictionary::looksLikeData($source) => 'document number',
                (bool) array_filter($parts, fn ($p) => isset($names[$p])) => 'master name',
                isset($translated[$source]) => 'already translated text',
                default => null,
            };
            foreach ($reason === null && preg_match('/\d/', $source) ? $templates : [] as $template => $pattern) {
                if ($source !== $template && preg_match($pattern, $source)) {
                    $reason = 'filled-in message';
                    break;
                }
            }
            if ($reason !== null) {
                $out[$id] = [$source, $reason];
            }
        }

        return $out;
    }

    /** @return array<string, int> nama customer / supplier / product yang ada di master */
    private function names(): array
    {
        $names = [];
        foreach (self::NAMES as $connection => $tables) {
            foreach ($tables as $table => $columns) {
                if (! Schema::connection($connection)->hasTable($table)) {
                    continue;
                }
                foreach ($columns as $column) {
                    if (Schema::connection($connection)->hasColumn($table, $column)) {
                        $names += array_flip(array_filter(array_map('trim', DB::connection($connection)->table($table)->whereNotNull($column)->distinct()->pluck($column)->all()),
                            fn ($n) => mb_strlen($n) >= 4));
                    }
                }
            }
        }

        return $names;
    }
};
