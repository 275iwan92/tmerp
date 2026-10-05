<?php

namespace App\Console\Commands;

use App\Support\Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Untuk AGEN di awal sesi (user 2026-10-05): daftar permintaan Generator yang masih terbuka, atau isi lengkap satu permintaan.
// Agen lalu mengisi pratinjau dengan App\Support\Generator::preview() / ask(), dan setelah user Approve membangun layarnya dan
// menutupnya dengan Generator::built(). Jalankan dari folder devlib:  php artisan gen:requests   |   php artisan gen:requests RQ-0004
class GenRequests extends Command
{
    protected $signature = 'gen:requests {no? : nomor permintaan, mis. RQ-0004}';

    protected $description = 'Daftar permintaan Generator yang terbuka (atau isi satu permintaan) untuk dikerjakan agen';

    public function handle(): int
    {
        if (! Generator::ready()) {
            $this->warn('Tabel gen_request belum ada: jalankan migrasi 2026_10_05_000003_create_gen_request_tables.');

            return self::SUCCESS;
        }
        if ($no = $this->argument('no')) {
            $r = DB::table('gen_request')->where('request_no', $no)->first();
            if (! $r) {
                $this->error("Permintaan {$no} tidak ada.");

                return self::FAILURE;
            }
            foreach (['request_no', 'status', 'name', 'request_type', 'need_engine', 'requested_by', 'description', 'engine_description', 'flow_description',
                'engine_input', 'engine_output', 'engine_formula', 'agent_note', 'approved_by', 'approved_at'] as $k) {
                $this->line(str_pad($k, 20).': '.(string) $r->{$k});
            }
            foreach (Generator::objects($r->id_request) as $o) {
                $this->line('object              : '.$o->object_name.' | '.($o->object_tag ?: 'NOT BUILT').' | '.$o->decision);
            }

            return self::SUCCESS;
        }
        $rows = DB::table('gen_request')->whereIn('status', Generator::OPEN)->orderBy('id_request')->get();
        if ($rows->isEmpty()) {
            $this->info('Tidak ada permintaan Generator yang terbuka.');

            return self::SUCCESS;
        }
        // REQUESTED = baca & isi pratinjau; QUESTION / PREVIEW = menunggu user; APPROVED = boleh dibangun.
        $this->table(['No', 'Status', 'Type', 'Engine', 'Name', 'By'], $rows->map(fn ($r) => [$r->request_no, $r->status, $r->request_type, $r->need_engine ? 'yes' : 'no', $r->name, $r->requested_by])->all());

        return self::SUCCESS;
    }
}
