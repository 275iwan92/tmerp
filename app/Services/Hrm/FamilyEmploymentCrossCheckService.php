<?php

namespace App\Services\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;

// Engine background (keputusan user 2026-08-27): cocokkan nama anggota
// keluarga generasi ortu (AYAH/IBU/MERTUA_AYAH/MERTUA_IBU) terhadap seluruh
// emp_employee aktif lain -- deteksi potensi hubungan keluarga sesama
// karyawan (nepotisme). Murni informatif ke HR (notifikasi), TIDAK
// memblokir apa pun. Dipicu inline tiap baris family relevan disimpan --
// tak ada worker/queue terpasang di project ini utk cron sungguhan.
class FamilyEmploymentCrossCheckService
{
    private const PARENT_GENERATION_RELATIONS = ['AYAH', 'IBU', 'MERTUA_AYAH', 'MERTUA_IBU'];

    public function checkFamilyMember(EmpFamily $family): void
    {
        if (! in_array($family->relation, self::PARENT_GENERATION_RELATIONS, true)) {
            return;
        }

        $needle = $this->normalize($family->full_name);
        if ($needle === '') {
            return;
        }

        $employee = EmpEmployee::find($family->employee_id);
        if (! $employee) {
            return;
        }

        $matches = EmpEmployee::query()
            ->where('activate', true)
            ->where('employee_id', '!=', $family->employee_id)
            ->get()
            ->filter(fn (EmpEmployee $e) => $this->normalize($e->full_name) === $needle);

        foreach ($matches as $match) {
            $this->notifyHr($employee, $family, $match);
        }
    }

    private function notifyHr(EmpEmployee $employee, EmpFamily $family, EmpEmployee $match): void
    {
        $label = ucwords(strtolower(str_replace('_', ' ', $family->relation)));
        $message = "{$label} {$employee->full_name} ({$employee->employee_code}) bernama \"{$family->full_name}\" "
            ."kemungkinan sama dengan karyawan {$match->full_name} ({$match->employee_code}). Mohon verifikasi.";

        $title = 'Potensi hubungan keluarga sesama karyawan';

        // Idempotent kasar -- cegah notif sama berulang tiap kali family di-save
        // ulang. sys_notifications.type cuma enum severity (info/success/warning/
        // error), bukan kategori bebas -- jadi identitas cek pakai title+message.
        $exists = SysNotification::query()
            ->where('title', $title)
            ->where('message', $message)
            ->exists();

        if ($exists) {
            return;
        }

        $recipients = SecUser::where('activate', true)->get()
            ->filter(fn (SecUser $u) => $u->canAccessModule('HRM'));

        foreach ($recipients as $recipient) {
            SysNotification::create([
                'user_id' => $recipient->login,
                'title' => $title,
                'message' => $message,
                'type' => 'warning',
                'is_read' => false,
            ]);
        }
    }

    private function normalize(?string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $name)) ?? '');
    }
}
