<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

// Lapisan resolusi approver_role -> sec_user_id, pengganti sementara asumsi
// role->org_position Tier2 SECTION 2 (lihat migration
// 2026_08_29_000003_create_recruitment_tm2hrr_schema.php header). Data-driven:
// tinggal update baris ini kalau org_position "HR Manager"/"Dept Head" sudah
// dirapikan, TIDAK perlu ubah kode.
class RcrApproverAssignment extends Model
{
    protected $connection = 'tm2hrr';

    protected $table = 'rcr_approver_assignment';

    protected $primaryKey = 'id_assignment';

    protected $fillable = ['approver_role', 'sec_user_id', 'department_id', 'notes', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];

    public static function resolve(string $role, ?int $departmentId): ?int
    {
        return static::query()
            ->where('approver_role', $role)
            ->where('activate', true)
            ->where(function ($q) use ($departmentId) {
                $q->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->orderByRaw('department_id IS NULL') // spesifik departemen menang atas company-wide
            ->value('sec_user_id');
    }
}
