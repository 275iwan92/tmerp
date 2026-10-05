<?php

namespace MockupParts\Components\Hrm;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Form khusus Edit profile / self-service karyawan (user 2026-10-03): satu layar penuh di dalam objek x-dl::side-screen.layout
// dengan sebelas bagian -- My Data, Job Description, SOP, Family & Status, Documents & Education, Leave / Sick / Permit, Out Permit,
// Shift Swap, My Schedule, ID Card, Security -- dan delapan pop-up pengajuan (koreksi data, bank gaji, resign, lahir, meninggal,
// pasangan, unggah dokumen, pelatihan). Data pribadi hanya dibaca; setiap perubahan = pengajuan yang disetujui HR. Akun login dan
// pertanyaan pemulihan (bagian Security) diubah langsung. Akun yang belum terhubung ke pegawai hanya mendapat bagian Security.
// Layar hanya menyerahkan $me (App\Support\V2\Data\SelfService::desk()) dan $crumbs. Aturan cuti / izin / tukar shift tetap di
// kelas data dan service HR. Mengubah objek ini = mengubah Edit profile di semua situs.
class SelfService extends DevlibComponent
{
    public function __construct(
        public array $me = [],
        public array $crumbs = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::hrm.self-service';
    }

    public function linked(): bool
    {
        return (bool) ($this->me['linked'] ?? false);
    }

    // Kelompok menu kiri: kunci bagian pertama tiap kelompok => judul kelompok.
    public function groups(): array
    {
        return ['profile' => 'Personal data', 'leave' => 'Requests', 'schedule' => 'Others'];
    }

    // Petunjuk singkat per bagian (tampil di kepala bagian).
    public function hints(): array
    {
        return [
            'profile' => 'Data update SOP: the information below is read-only. If an entry is wrong, click Request Correction; the change takes effect after HR verifies it.',
            'jobdesc' => 'Only the HR Officer can change this description; it is read-only here.',
            'sop' => 'Procedures that apply to your position; open one to read it.',
            'family' => 'Deceased family members are not deleted from the history, to keep past allowance and claim records accurate.',
            'documents' => 'Identity documents and your education & training history.',
            'leave' => 'Annual leave: request at least H+7 ahead, maximum 3 working days per request, Sundays do not count.',
            'outing' => 'Permit to leave the work area during working hours.',
            'swap' => 'Swap a shift with a colleague; both schedules change after approval.',
            'schedule' => 'Your upcoming roster.',
            'badge' => 'Your ID card; send a new photo if needed.',
            'security' => 'Login account data (not HR data), changed directly without HR approval.',
        ];
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::hrm.self-service');
    }
}
