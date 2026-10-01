<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Audit integritas data, HANYA MEMBACA (backlog09 §14 + backlog05 05.17). Tidak mengubah satu baris pun: hasilnya untuk
// diputuskan user / diperbaiki lewat layar.
//   php artisan data:audit                  (tm2*)
//   php artisan data:audit --env=mockup     (ddt*)
//   php artisan data:audit --only=identity | --only=stock
// identity: akun (sec_user) yang tertaut ke pegawai (emp_employee) -- nama beda, pegawai tidak ada, akun aktif pada pegawai
//           nonaktif, satu pegawai dipakai beberapa akun.
// stock:    kartu stok RM / FG / SFG -- saldo akhir = saldo awal + masuk - keluar + penyesuaian, saldo awal = saldo akhir baris
//           sebelumnya (per item + lokasi), dan tidak ada saldo minus.
class DataAudit extends Command
{
    protected $signature = 'data:audit {--only= : identity | stock}';

    protected $description = 'Audit integritas data (hanya baca): identitas akun <-> pegawai, saldo berjalan kartu stok';

    private const STOCKCARDS = ['rm_stockcard' => 'material_id', 'fg_stockcard' => 'product_id', 'sfg_stockcard' => 'parts_id'];

    public function handle(): int
    {
        $only = $this->option('only');
        $findings = 0;
        if (! $only || $only === 'identity') {
            $findings += $this->identity();
        }
        if (! $only || $only === 'stock') {
            $findings += $this->stock();
        }
        $this->newLine();
        $findings ? $this->warn("$findings finding(s). Nothing was changed.") : $this->info('No findings.');

        return self::SUCCESS;
    }

    private function identity(): int
    {
        $sys = DB::connection('tm2sys');
        $this->line('<options=bold>Identity</> ('.$sys->getDatabaseName().'.sec_user <-> '.DB::connection('tm2mst')->getDatabaseName().'.emp_employee)');
        $users = $sys->table('sec_user')->whereNotNull('employee_id')->orderBy('login')->get(['login', 'name', 'employee_id', 'activate']);
        $employees = DB::connection('tm2mst')->table('emp_employee')->whereIn('employee_id', $users->pluck('employee_id')->unique())
            ->get(['employee_id', 'employee_code', 'full_name', 'activate'])->keyBy('employee_id');
        $plain = fn (?string $s) => preg_replace('/[^a-z]/', '', strtolower((string) $s));

        $rows = [];
        foreach ($users as $u) {
            $e = $employees[$u->employee_id] ?? null;
            if (! $e) {
                $rows[] = ['Employee not found', $u->login, 'employee_id '.$u->employee_id];

                continue;
            }
            [$a, $b] = [$plain($u->name), $plain($e->full_name)];
            // Nama akun sering nama panggilan / sebagian nama lengkap: hanya dilaporkan bila tidak saling memuat.
            if ($a !== $b && ! str_contains($b, $a) && ! str_contains($a, $b)) {
                $rows[] = ['Name differs', $u->login, "account \"{$u->name}\" / employee {$e->employee_code} \"{$e->full_name}\""];
            }
            if ($u->activate && ! $e->activate) {
                $rows[] = ['Active account, inactive employee', $u->login, $e->employee_code.' '.$e->full_name];
            }
        }
        foreach ($users->groupBy('employee_id')->filter(fn ($g) => $g->count() > 1) as $id => $g) {
            $rows[] = ['One employee, several accounts', $g->pluck('login')->implode(', '), ($employees[$id]->employee_code ?? $id).' '.($employees[$id]->full_name ?? '')];
        }

        $this->line('  '.$users->count().' of '.$sys->table('sec_user')->count().' accounts are linked to an employee.');
        $rows ? $this->table(['Finding', 'Login', 'Detail'], $rows) : $this->line('  OK');

        return count($rows);
    }

    private function stock(): int
    {
        $inv = DB::connection('tm2inv');
        $this->line('<options=bold>Stock card running balance</> ('.$inv->getDatabaseName().')');
        $rows = [];
        foreach (self::STOCKCARDS as $table => $key) {
            $prev = [];
            foreach ($inv->table($table)->orderBy($key)->orderBy('location_id')->orderBy('doc_date')->orderBy('doc_id')
                ->get([$key.' as item', 'location_id', 'doc_id', 'doc_date', 'qty_bb', 'qty_in', 'qty_out', 'qty_adj', 'qty_eb']) as $r) {
                $k = $r->item.'|'.$r->location_id;
                $where = "item {$r->item}, location {$r->location_id}, doc_id {$r->doc_id} (".substr((string) $r->doc_date, 0, 10).')';
                $expected = round((float) $r->qty_bb + (float) $r->qty_in - (float) $r->qty_out + (float) $r->qty_adj, 4);
                if (abs($expected - (float) $r->qty_eb) > 0.0001) {
                    $rows[] = [$table, 'Closing is not opening + in - out + adj', $where, "expected $expected, stored ".(float) $r->qty_eb];
                }
                if (isset($prev[$k]) && abs((float) $prev[$k] - (float) $r->qty_bb) > 0.0001) {
                    $rows[] = [$table, 'Opening is not the previous closing', $where, 'previous '.(float) $prev[$k].', opening '.(float) $r->qty_bb];
                }
                if ((float) $r->qty_bb < -0.0001 || (float) $r->qty_eb < -0.0001) {
                    $rows[] = [$table, 'Negative balance', $where, 'opening '.(float) $r->qty_bb.', closing '.(float) $r->qty_eb];
                }
                $prev[$k] = $r->qty_eb;
            }
        }
        $rows ? $this->table(['Table', 'Finding', 'Row', 'Detail'], $rows) : $this->line('  OK');

        return count($rows);
    }
}
