{{--
 REMARK
 Tujuan       : Form Payslip (menggantikan halaman v1 pages::hrm.payroll-payslip-list dan print): satu slip gaji baca-saja (pegawai, periode, cara bayar, kehadiran, pendapatan, potongan, gaji bersih), tombol Print Payslip, dan pilihan kanal Print dan WhatsApp (tombol toggle). Slip anomali tidak bisa dicetak dan kanalnya terkunci. Nominal rahasia: hanya user dengan cakupan payroll atas pegawai itu.
 Sumber hulu  : tm2pyr.pyr_payslip + pyr_payroll_run (nominal terenkripsi lewat cast model), tm2mst.emp_employee + emp_bank_account + att_daily_status; data lembar dari App\Services\Payroll\PayslipService::sheet, aturan kanal di PayslipService.
 Input        : Query id (pyr_payslip.id_payslip); session v2_flash dan errors.
 Output       : POST v2.hrm.payslip.channel (id, channel print atau wa); tombol Print ke route v2.print (jenis payslip).
 Dipakai oleh : Dibuka dari grid hrm-payslip (Open); cetak seluruh periode dari hrm-payroll-run-form.
 Status       : Aktif (T8 Payroll).
--}}
@php
    use App\Models\Pyr\PyrPayslip;
    use App\Services\Payroll\PayslipService as Slip;
    use App\Support\V2\Print\PrintDocs;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-payslip');
    $user = Auth::user();
    $p = PyrPayslip::with('run')->find((int) request('id'));
    abort_unless($p, 404);
    abort_unless(Slip::can($user, $p), 403, t('You do not have access to this payslip.'));
    $sheet = Slip::sheet($p);
    $e = $sheet['employee'];
    $s = fn (string $col) => Src::of('tm2pyr.pyr_payslip.'.$col);
    $idr = fn ($n) => 'IDR '.Locale::number((float) $n, 0);
    $ts = fn ($t) => $t ? Locale::dateTime($t) : '—';
    $phone = (string) ($e?->phone ?? '');
    $lines = fn (array $m) => collect($m)->map(fn ($v, $k) => ['item' => t($k), 'amount' => $idr($v)])->values()->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-payslip-form" :title="raw($e?->full_name ?? '—')" :subtitle="raw(($p->run?->period_type ?? '').' '.Locale::date($p->run->period_start).' — '.Locale::date($p->run->period_end))" panel="top" kind="master" :status="$p->has_anomaly ? 'inactive' : 'active'"
    :stage="$p->has_anomaly ? t('Anomaly') : null" :can-input="false" :hide="['deactivate']" :action="route('v2.hrm.payslip.channel')" action-field="channel" :back="$home" :hidden="['id' => $p->id_payslip]"
    :tabs="['slip' => t('Payslip')]">
    <x-slot:special>
        @unless ($p->has_anomaly)
            <x-dl::button variant="primary" icon="fa-print" label="Print Payslip" :href="PrintDocs::url('payslip', $p->id_payslip)" />
            <x-dl::button :variant="$p->print_flag ? 'success' : 'secondary'" icon="fa-file-lines" :label="$p->print_flag ? 'Print channel: ON' : 'Print channel: OFF'" type="submit" name="channel" value="print" formnovalidate />
            <x-dl::button :variant="$p->wa_flag ? 'success' : 'secondary'" icon="fa-brands fa-whatsapp" :label="$p->wa_flag ? 'WhatsApp channel: ON' : 'WhatsApp channel: OFF'" type="submit" name="channel" value="wa" formnovalidate :disabled="blank($phone)" />
        @endunless
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Employee" :value="raw($e?->employee_code ?? '—')" />
        <x-dl::strip-kv kind="grand" label="Payment method" :value="$sheet['bank'] ? raw($sheet['bank']->bank_name) : 'CASH'" />
        <x-dl::strip-kv kind="grand" label="Net salary" :value="$idr($sheet['net'])" />
        <x-dl::strip-kv kind="grand" label="Printed" :value="$ts($p->printed_at)" />
    </x-slot:strip>

    <x-slot:slip>
        @if ($p->has_anomaly)
            <x-dl::alert tone="warning" :message="raw($p->anomaly_note ?: t('Salary rate not active — payslip not valid yet'))" />
        @endif
        <x-dl::card title="Employee and payment (read-only)">
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Employee" :value="raw($e ? $e->employee_code.' '.$e->full_name : '—')" :source="$s('employee_id')" />
                <x-dl::field.display label="Department / position" :value="raw(($e?->position?->department?->department_name ?? '—').' / '.($e?->position?->position_name ?? '—'))" :source="$s('employee_id')" />
                <x-dl::field.display label="Payment method" :value="$sheet['bank'] ? raw($sheet['bank']->bank_name.' ('.$sheet['bank']->account_no.')') : 'CASH'" :source="$s('employee_id')" />
                <x-dl::field.display label="Attendance" :value="$sheet['attendance']['present'].' '.t('present').', '.$sheet['attendance']['late'].' '.t('late').', '.$sheet['attendance']['absent'].' '.t('absent')" :source="$s('employee_id')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="WhatsApp number" :value="blank($phone) ? t('Not filled in') : raw($phone)" :source="Src::of('tm2mst.emp_employee.phone')" />
                <x-dl::field.display label="Print channel" :value="t($p->print_flag ? 'On' : 'Off')" :source="$s('print_flag')" />
                <x-dl::field.display label="WhatsApp channel" :value="t($p->wa_flag ? 'On' : 'Off')" :source="$s('wa_flag')" />
                <x-dl::field.display label="WhatsApp sent" :value="$ts($p->wa_sent_at)" :source="$s('wa_sent_at')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Earnings (+)"><x-dl::table :columns="['item' => 'Component', 'amount' => 'Amount']" :rows="$lines($sheet['earnings'])" /></x-dl::card>
        <x-dl::card title="Deductions (−)"><x-dl::table :columns="['item' => 'Component', 'amount' => 'Amount']" :rows="$lines($sheet['deductions'])" /></x-dl::card>
        <x-dl::card title="Net salary received"><x-dl::field.display label="Net salary (IDR)" :value="$idr($sheet['net'])" :source="$s('total_gaji_bersih')" /></x-dl::card>
    </x-slot:slip>
</x-dl::form>
