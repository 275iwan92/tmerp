<?php

/*
 * REMARK
 * Tujuan       : Cetakan slip gaji satu pegawai (menggantikan halaman v1 pages::hrm.payroll-payslip-print): tabel pendapatan dan potongan, kotak total gaji bersih, pegawai di blok kanan, periode / cara bayar / kehadiran di blok kiri, kolom tanda tangan penerima. Tata letak seluruhnya milik objek DevLib Devlib\PrintDoc\Document.
 * Sumber hulu  : tm2pyr.pyr_payslip + pyr_payroll_run; data lembar dari App\Services\Payroll\PayslipService::sheet (dipakai juga cetak seluruh periode, supaya isinya identik).
 * Input        : $id = id slip (pyr_payslip.id_payslip); opsi bulk = cetak massal tanpa stempel; hak: PayslipService::can (cakupan payroll atas pegawai itu).
 * Output       : Objek Document untuk x-dl::print-doc; membuka slip menstempel printed_at (niat mencetak, sama dengan v1).
 * Dipakai oleh : PrintDocs::MAP jenis 'payslip' (route v2.print), tombol Print di hrm-payslip-form, halaman cetak seluruh periode.
 * Status       : Aktif (T8 Payroll).
 */

namespace App\Support\V2\Print;

use App\Models\Pyr\PyrPayslip;
use App\Services\Payroll\PayslipService;
use Devlib\Locale;
use Devlib\PrintDoc\Document;
use Illuminate\Support\Facades\Auth;

final class PayslipPrint extends Document
{
    public string $type = 'payslip';

    public string $title = 'Employee Payslip';

    public ?string $subtitle = 'Payroll · Confidential';

    public string $numberLabel = 'Payslip No.';

    // Blok kiri di bawah nomor: periode, cara bayar dan ringkasan kehadiran slip itu.
    public array $refs = [
        ['key' => 'period', 'label' => 'Period'],
        ['key' => 'method', 'label' => 'Payment Method'],
        ['key' => 'attendance', 'label' => 'Attendance'],
    ];

    public string $partyLabel = 'Employee';

    public string $nameLabel = 'Component';

    public bool $priced = true;

    // Tanpa kolom Qty / Unit (komponen gaji tidak punya jumlah): zona varian jadi 74 mm (38 + 36), diisi jenis baris Earning atau Deduction.
    public bool $qtyCols = false;

    public array $extra = [['key' => 'kind', 'label' => 'Type', 'width' => 74]];

    public array $receivers = [['Employee', 'Receives the payslip']];

    public array $signLabels = ['approve' => '', 'know' => '', 'make' => 'Prepared By'];

    public static function load(int $id, array $opt = []): static
    {
        $slip = PyrPayslip::with('run')->findOrFail($id);
        abort_unless(PayslipService::can(Auth::user(), $slip), 403);
        // Satu slip: membuka = niat mencetak, langsung distempel. Cetak massal (opt bulk) tidak menstempel di sini; stempelnya lewat tombol Mark as printed.
        if (empty($opt['bulk'])) {
            app(PayslipService::class)->stampPrinted($slip);
        }
        $s = PayslipService::sheet($slip);
        $e = $s['employee'];

        // Pendapatan bernilai positif, potongan ditulis sebagai subtotal negatif; angka 0 tidak dicetak.
        $rows = [];
        foreach ($s['earnings'] as $label => $amount) {
            $amount > 0 && $rows[] = ['name' => $label, 'price' => $amount, 'subtotal' => $amount, 'kind' => 'Earning'];
        }
        foreach ($s['deductions'] as $label => $amount) {
            $amount > 0 && $rows[] = ['name' => $label, 'price' => $amount, 'subtotal' => -$amount, 'kind' => 'Deduction'];
        }
        $bank = $s['bank'];

        return new self([
            'number' => $slip->run->period_type.'-'.$slip->id_payslip, 'date' => now()->toDateString(), 'status' => $slip->run->run_status, 'printedBy' => $opt['printedBy'] ?? null,
            'party' => ['name' => $e?->full_name ?? '—', 'code' => $e?->employee_code, 'lines' => array_values(array_filter([$e?->position?->department?->department_name, $e?->position?->position_name]))],
            'period' => Locale::d($slip->run->period_start).' — '.Locale::d($slip->run->period_end),
            'method' => $bank ? $bank->bank_name.' ('.$bank->account_no.')' : 'CASH',
            'attendance' => $s['attendance']['present'].' present, '.$s['attendance']['late'].' late, '.$s['attendance']['absent'].' absent',
            'rows' => $rows,
            'sums' => ['lines' => [['Total earnings', array_sum($s['earnings'])], ['Total deductions', array_sum($s['deductions']), 'minus' => true]], 'grand' => $s['net']],
        ]);
    }
}
