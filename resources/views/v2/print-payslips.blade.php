{{--
 REMARK
 Tujuan       : Cetak massal slip gaji satu periode (menggantikan halaman v1 pages::hrm.payroll-payslip-print-all): satu lembar per slip yang print_flag-nya menyala dan bukan anomali, dalam cakupan payroll user, memakai objek DevLib x-dl::print-doc yang sama dengan cetak satu slip; tombol Mark as printed menstempel printed_at.
 Sumber hulu  : App\Services\Payroll\PayslipService::printable dan App\Support\V2\Print\PayslipPrint (opsi bulk).
 Input        : $run (PyrPayrollRun), $docs (daftar Document), $back.
 Output       : x-dl::action-bar dan x-dl::print-doc per slip; POST v2.hrm.payslip.printed.
 Dipakai oleh : Route v2.hrm.payslip.printall (routes/v2_hrm.php); tombol dari hrm-payroll-run-form.
 Status       : Aktif (T8 Payroll).
--}}
@extends('v2.layout', ['title' => t('Payslips').' '.$run->period_start->format('d-M-Y').' — '.$run->period_end->format('d-M-Y'), 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
            @if (count($docs))
                <x-dl::action-form method="post" action="{{ route('v2.hrm.payslip.printed', $run->id_run) }}">
                    <x-dl::button variant="primary" icon="fa-check" label="Mark as printed" type="submit" />
                </x-dl::action-form>
            @endif
        </x-dl::action-bar>
        @forelse ($docs as $i => $doc)
            <x-dl::print-doc :doc="$doc" :id="'print-doc-'.$i" />
        @empty
            <x-dl::hint note>No payslip of this period is marked for printing. Mark payslips for the print channel in the payslip list first.</x-dl::hint>
        @endforelse
    </x-dl::box>
@endsection
