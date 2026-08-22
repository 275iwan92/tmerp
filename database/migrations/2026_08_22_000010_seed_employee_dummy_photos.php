<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// User minta contoh foto dummy (mirip mockup HTML yang dikasih) supaya bisa
// diverifikasi langsung fitur foto Master Employee (grid thumbnail + modal
// perbesar). Sumber: i.pravatar.cc -- layanan avatar placeholder yang memang
// dibuat khusus utk kebutuhan data uji/dummy (bukan hotlink foto stok orang
// sungguhan spt Unsplash di mockup aslinya), disimpan ke disk public via
// Storage sama persis dgn jalur upload asli (hrm/{employee_id}/profile) --
// supaya yang diuji benar-benar mekanisme penyimpanan+tampil yang sama dgn
// upload manual, bukan sekadar hotlink URL luar. HTTP call dibungkus
// try/catch -- kalau internet tak tersedia di environment lain, migration
// tidak gagal keras, cuma lewati baris itu.
return new class extends Migration
{
    private const SAMPLE = [
        1 => 12, // Budi Santoso
        2 => 47, // Dewi Rahmawati
        3 => 33, // Agus Setiawan
        4 => 55, // Rudi Hartono
        5 => 8,  // Wawan Setiadi
    ];

    public function up(): void
    {
        foreach (self::SAMPLE as $employeeId => $avatarImg) {
            $emp = DB::connection('tm2mst')->table('emp_employee')->where('employee_id', $employeeId)->first();
            if (! $emp || $emp->photo_path) {
                continue;
            }

            try {
                $response = Http::timeout(10)->get("https://i.pravatar.cc/400?img={$avatarImg}");
                if (! $response->successful()) {
                    continue;
                }

                $path = "hrm/{$employeeId}/profile/dummy-".uniqid().'.jpg';
                Storage::disk('public')->put($path, $response->body());

                DB::connection('tm2mst')->table('emp_employee')->where('employee_id', $employeeId)->update([
                    'photo_path' => $path,
                    'updated_by' => 'system',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::SAMPLE) as $employeeId) {
            $emp = DB::connection('tm2mst')->table('emp_employee')->where('employee_id', $employeeId)->first();
            if ($emp && $emp->photo_path && str_contains($emp->photo_path, 'dummy-')) {
                Storage::disk('public')->deleteDirectory("hrm/{$employeeId}/profile");
                DB::connection('tm2mst')->table('emp_employee')->where('employee_id', $employeeId)->update(['photo_path' => null]);
            }
        }
    }
};
