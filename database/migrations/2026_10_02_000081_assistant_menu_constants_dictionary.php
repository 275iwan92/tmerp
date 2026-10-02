<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Asisten AI di ERP (paket packages/assistant; tabelnya dibuat migrasi paket 2026_10_02_000080):
//  - menu Setup > Settings: Assistant Questions (katalog angka) + Assistant Log (pertanyaan user);
//  - konstanta kategori ASSISTANT (aturan "semua data wajib punya inputan"): model lokal, masa simpan riwayat per level,
//    batas bulan deadstock, level minimum pertanyaan gaji;
//  - terjemahan Indonesia teks baku jawaban.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // key => [angka, teks, uraian]
    private const CONSTANTS = [
        'ASSISTANT_MODEL_ENABLED' => [0, null, '1 = the assistant asks the local language model (Ollama) when keyword search finds no sure answer. 0 = keyword search only.'],
        'ASSISTANT_MODEL_URL' => [null, 'http://127.0.0.1:11434', 'Address of the local Ollama server, in Text Value.'],
        'ASSISTANT_MODEL_NAME' => [null, 'qwen2.5:3b', 'Name of the Ollama model the assistant uses, in Text Value.'],
        'ASSISTANT_MODEL_TIMEOUT_SECONDS' => [60, null, 'Longest wait for the local language model, in seconds. After that the assistant answers without it.'],
        'ASSISTANT_HISTORY_DAYS_TOP' => [30, null, 'Days the assistant chat history is kept for top management (security level at or above ASSISTANT_LEVEL_TOP_MIN).'],
        'ASSISTANT_HISTORY_DAYS_MIDDLE' => [14, null, 'Days the assistant chat history is kept for middle management.'],
        'ASSISTANT_HISTORY_DAYS_LOW' => [7, null, 'Days the assistant chat history is kept for the lowest level.'],
        'ASSISTANT_LEVEL_TOP_MIN' => [4, null, 'Lowest security level (group management level) that counts as top management for the assistant chat history.'],
        'ASSISTANT_LEVEL_MIDDLE_MIN' => [2, null, 'Lowest security level that counts as middle management for the assistant chat history.'],
        'ASSISTANT_DEADSTOCK_MONTHS' => [6, null, 'Stock without movement for more than this many months is deadstock in the assistant answer.'],
        'ASSISTANT_SALARY_MIN_LEVEL' => [4, null, 'Lowest security level that may ask the assistant about salaries (General Manager and Directors).'],
    ];

    private const UI = [
        'Assistant Questions' => 'Pertanyaan Asisten', 'Assistant Log' => 'Log Asisten',
        'Ask in plain words: where a screen is, how to use a screen, or a number you are allowed to see.' => 'Tanyakan dengan bahasa biasa: letak sebuah layar, cara pakai layar, atau angka yang boleh Anda lihat.',
        'Type your question...' => 'Tulis pertanyaan...', 'Looking for the answer...' => 'Sedang mencari jawabannya...',
        'The assistant could not answer just now. Try again.' => 'Asisten belum bisa menjawab saat ini. Coba lagi.',
        'Screen found' => 'Layar ditemukan', 'Open :screen' => 'Open :screen', 'How to use' => 'Cara pakai', 'How to use: :screen' => 'Cara pakai: :screen',
        'Source: Help, :screen' => 'Sumber: Help, :screen', 'Open Help' => 'Open Help', 'Field: :field' => 'Isian: :field',
        'Not available for your account' => 'Tidak tersedia untuk akun Anda',
        ':title follows the access rights of your account: it needs access to its module and a high enough security level.' => ':title mengikuti hak akses akun Anda: perlu akses ke modulnya dan level security yang cukup.',
        'I do not have the answer yet' => 'Saya belum punya jawabannya',
        'This question is not in the Help and not in the list of numbers I know. I will not guess.' => 'Pertanyaan ini tidak ada di Help maupun di daftar angka yang saya kenal. Saya tidak akan menebak.',
        'Your question is recorded so the team can add the answer.' => 'Pertanyaan Anda dicatat supaya tim bisa menambahkan jawabannya.',
        'Send as Support Ticket' => 'Kirim sebagai Tiket Bantuan',
        'Sales today' => 'Sales hari ini', ':n documents' => ':n dokumen', 'Waiting for approval' => 'Menunggu approval',
        'Customer Orders dated today, without the cancelled ones. Data at :time.' => 'Customer Order bertanggal hari ini, tanpa yang Cancelled. Data pukul :time.',
        'Your account sees the number of documents; the rupiah value follows the selling-price right.' => 'Akun Anda melihat jumlah dokumen; nilai rupiah mengikuti hak melihat harga jual.',
        'Orders in more than one currency are added as they are.' => 'Order dengan lebih dari satu mata uang dijumlahkan apa adanya.',
        'Deadstock value' => 'Nilai deadstock', ':n items' => ':n item',
        'Deadstock: no movement for more than :n months' => 'Deadstock: tidak bergerak lebih dari :n bulan',
        'Stock still on hand in usable locations whose last movement was before :date, or that never moved, valued at the average purchase price. Data at :time.' => 'Stok yang masih ada di lokasi yang bisa dipakai dengan pergerakan terakhir sebelum :date, atau belum pernah bergerak, dinilai harga beli rata-rata. Data pukul :time.',
        ':n of :all deadstock items have no stock value on their stock card, so the rupiah total is too low.' => ':n dari :all item deadstock tidak punya nilai stok di kartu stoknya, jadi total rupiah ini terlalu rendah.',
        'Salary' => 'Gaji',
        'Salary questions are answered only for the General Manager and the Directors.' => 'Pertanyaan gaji hanya dijawab untuk General Manager dan Direksi.',
        'Your own salary is shown in your user profile.' => 'Gaji Anda sendiri bisa dilihat di profil user Anda.',
        'Salary figures are not in my list of numbers yet. They are on the payroll screens you are allowed to open.' => 'Angka gaji belum ada di daftar angka saya. Angkanya ada di layar payroll yang boleh Anda buka.',
        'Question' => 'Pertanyaan', 'Answer' => 'Jawaban', 'Asked by' => 'Penanya', 'Answer kind' => 'Jenis jawaban', 'Feedback' => 'Penilaian',
        'Trigger phrases' => 'Frasa pemicu', 'Minimum level' => 'Level minimum', 'Not answered' => 'Tidak terjawab', 'Thumbs down' => 'Jempol turun',
        'Thumbs up' => 'Jempol naik', 'Local model' => 'Model lokal', 'Duration (ms)' => 'Lama (ms)', 'Asked at' => 'Waktu tanya',
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $menu = fn () => $sys->table('sys_app_menu');
        if ($settings = $menu()->where('code', 'SET_GRP_SETTINGS')->value('id')) {
            foreach ([['SET_ASSISTANT_Q', 'Assistant Questions', 'fa-solid fa-wand-magic-sparkles', 'grid', 'v2:setup-assistant-questions', 49],
                ['SET_ASSISTANT_LOG', 'Assistant Log', 'fa-solid fa-clipboard-question', 'report', 'v2:setup-assistant-log', 50]] as [$code, $label, $icon, $type, $target, $order]) {
                if (! $menu()->where('code', $code)->exists()) {
                    $menu()->insert(['parent_id' => $settings, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $type,
                        'target' => $target, 'sort_order' => $order, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)',
                        'status_updated_at' => now(), 'created_at' => now()]);
                }
            }
        }

        if (! $sys->table('sys_constant_category')->where('code', 'ASSISTANT')->exists()) {
            $sys->table('sys_constant_category')->insert(['code' => 'ASSISTANT', 'label' => 'Assistant', 'description' => 'AI assistant: local language model, chat history retention, thresholds',
                'sort_order' => 95, 'activate' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (self::CONSTANTS as $key => [$number, $text, $description]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'ASSISTANT', 'constant_group' => 'ASSISTANT', 'constant_number' => $number,
                    'constant_text' => $text, 'description' => $description, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(),
                    'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }

        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            foreach (self::UI as $en => $id) {
                $sys->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
                $key = $sys->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
                if (! $sys->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                    $sys->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
                }
            }
            Dictionary::flush();
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->whereIn('code', ['SET_ASSISTANT_Q', 'SET_ASSISTANT_LOG'])->delete();
        $sys->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
        $sys->table('sys_constant_category')->where('code', 'ASSISTANT')->delete();
    }
};
