<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// User minta foto dummy khusus utk 2 nama tertentu (ANIK NUR AZIZAH / ANDY
// SANJAYA) supaya bisa dicek langsung -- reuse file yang sudah diunduh sah
// dari migration sebelumnya (2026_08_22_000010, i.pravatar.cc), bukan unduh
// baru -- cukup disalin ke folder masing-masing lewat Storage (jalur sama
// dgn upload asli).
return new class extends Migration
{
    private const ASSIGN = [
        'ANIK NUR AZIZAH' => 2, // salin dari foto Dewi Rahmawati (perempuan)
        'ANDY SANJAYA' => 4,    // salin dari foto Rudi Hartono (pria)
    ];

    public function up(): void
    {
        foreach (self::ASSIGN as $name => $sourceEmployeeId) {
            $target = DB::connection('tm2mst')->table('emp_employee')->whereRaw('UPPER(full_name) = ?', [$name])->first();
            $source = DB::connection('tm2mst')->table('emp_employee')->where('employee_id', $sourceEmployeeId)->first();

            if (! $target || ! $source || ! $source->photo_path || $target->photo_path) {
                continue;
            }

            $content = Storage::disk('public')->get($source->photo_path);
            $path = "hrm/{$target->employee_id}/profile/dummy-".uniqid().'.jpg';
            Storage::disk('public')->put($path, $content);

            DB::connection('tm2mst')->table('emp_employee')->where('employee_id', $target->employee_id)->update([
                'photo_path' => $path,
                'updated_by' => 'system',
            ]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::ASSIGN) as $name) {
            $target = DB::connection('tm2mst')->table('emp_employee')->whereRaw('UPPER(full_name) = ?', [$name])->first();
            if ($target && $target->photo_path && str_contains($target->photo_path, 'dummy-')) {
                Storage::disk('public')->deleteDirectory("hrm/{$target->employee_id}/profile");
                DB::connection('tm2mst')->table('emp_employee')->where('employee_id', $target->employee_id)->update(['photo_path' => null]);
            }
        }
    }
};
