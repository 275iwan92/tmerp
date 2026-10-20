{{--
 REMARK
 Tujuan       : Form Payroll Calculation (menggantikan halaman v1 pages::hrm.payroll-batch-run): satu periode payroll dengan siklus DRAFT, CALCULATED, VERIFIED, CONFIRMED, PAID, CLOSED lewat tombol Run Calculation, Verify Attendance, Confirm (Lock Attendance), Mark as Paid, Mark Payslips as Sent dan Close Period; ringkasan Bank vs Cash dan jumlah anomali; tab History. Periode baru dibuat dari form kosong (new=1).
 Sumber hulu  : tm2pyr.pyr_payroll_run + pyr_payslip (nominal terenkripsi lewat cast model), tm2mst.emp_bank_account; aturan di App\Services\Payroll\PayrollRunService dan PayrollEngineService, cakupan data lewat PayrollAccess.
 Input        : Query id (pyr_payroll_run.id_run) atau new=1; session v2_flash dan errors.
 Output       : POST v2.hrm.payrollrun.save (action calculate, verify, confirm, paid, sent, close).
 Dipakai oleh : Dibuka dari grid hrm-payroll-run (Open dan + New Record); payslip periode ini di grid hrm-payslip.
 Status       : Aktif (T8 Payroll).
--}}
@php
    use App\Models\Pyr\PyrPayrollRun;
    use App\Services\Payroll\PayrollRunService as Run;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'hrm-payroll-run');
    $svc = app(Run::class);
    $user = Auth::user();
    $r = request('id') ? PyrPayrollRun::find((int) request('id')) : null;
    abort_if(request('id') && ! $r, 404);
    abort_unless($r || request('new'), 404);
    $new = ! $r;
    $st = $new ? 'DRAFT' : $r->run_status;
    // Periode hanya bisa diubah dan dihitung ulang selama belum VERIFIED (sama dengan engine: calculate menolak sesudah itu).
    $editable = $new || in_array($st, ['DRAFT', 'CALCULATED'], true);
    $summary = $new ? null : Run::summary($r, $user);
    $s = fn (string $col) => Src::of('tm2pyr.pyr_payroll_run.'.$col);
    $val = fn (string $col, $default = null) => old($col, $r?->{$col} ?? $default);
    $date = fn ($d) => $d ? Locale::date($d) : '—';
    $idr = fn ($n) => 'IDR '.Locale::number((float) $n, 0);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-payroll-run-form" :title="$new ? 'New Payroll Period' : raw($date($r->period_start).' — '.$date($r->period_end))" :subtitle="$new ? null : t(Run::TYPES[$r->period_type] ?? $r->period_type)"
    panel="top" kind="master" status="active" :stage="$new ? null : t(ucfirst(strtolower($st)))" :can-input="$editable" :hide="['deactivate', 'new', 'undo', 'save']"
    :action="route('v2.hrm.payrollrun.save')" action-field="action" :back="$home" :hidden="$new ? [] : ['id' => $r->id_run]" :tabs="['period' => t('Period')]" :log="$new ? [] : $svc->history($r)">
    <x-slot:special>
        {{-- Tombol mengikuti status; engine tetap menolak langkah yang salah urutan, tombol hanya menyembunyikannya. --}}
        @if ($editable)
            <x-dl::button variant="primary" icon="fa-bolt" label="Run Calculation" type="submit" name="action" value="calculate" />
        @endif
        @if ($st === 'CALCULATED')
            <x-dl::button variant="primary" icon="fa-check" label="Verify Attendance" type="submit" name="action" value="verify" />
        @endif
        @if ($st === 'VERIFIED')
            <x-dl::button variant="primary" icon="fa-lock" label="Confirm (Lock Attendance)" type="submit" name="action" value="confirm" formnovalidate data-dl-confirm="{{ t('Lock the attendance of this period? It cannot be recalculated afterwards.') }}" />
        @endif
        @if (! $new && $st !== 'DRAFT')
            {{-- Slip periode ini dibuat saat perhitungan; cetak massal hanya untuk slip dengan kanal Print menyala. --}}
            <x-dl::button variant="secondary" icon="fa-print" label="Print all payslips" :href="route('v2.hrm.payslip.printall', $r->id_run)" />
        @endif
        @if ($st === 'CONFIRMED')
            <x-dl::button variant="success" icon="fa-money-bill-transfer" label="Mark as Paid" type="submit" name="action" value="paid" formnovalidate data-dl-confirm="{{ t('Confirm that the payment has been executed (bank transfer/cash)?') }}" />
        @endif
        @if ($st === 'PAID')
            @unless ($r->payslip_sent_at)
                <x-dl::button variant="secondary" icon="fa-envelope" label="Mark Payslips as Sent" type="submit" name="action" value="sent" formnovalidate />
            @endunless
            <x-dl::button variant="primary" icon="fa-flag-checkered" label="Close Period" type="submit" name="action" value="close" formnovalidate data-dl-confirm="{{ t('Close this period? The next period will be created automatically.') }}" />
        @endif
    </x-slot:special>
    @unless ($new)
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Period" :value="raw($date($r->period_start).' — '.$date($r->period_end))" />
            <x-dl::strip-kv kind="grand" label="Status" :value="t(ucfirst(strtolower($st)))" />
            <x-dl::strip-kv kind="grand" label="Employees" :value="$summary ? Locale::number((float) $summary['employees'], 0) : '—'" />
            <x-dl::strip-kv kind="grand" label="Total net salary" :value="$summary ? $idr($summary['grand_total']) : '—'" />
        </x-slot:strip>
    @endunless

    <x-slot:period>
        <x-dl::card title="Run Payroll Calculation">
            <x-dl::hint note>Calculates base salary, allowances, overtime, meal allowance, BPJS / PPh21 (if active) and penalties from the daily attendance, and separates Bank from Cash. A period can be recalculated until it is verified.</x-dl::hint>
            <x-dl::form.row :cols="3">
                <x-dl::field.select name="period_type" label="Period" required :options="array_map('t', Run::TYPES)" :value="$val('period_type', 'MONTHLY')" :placeholder="null" :source="$s('period_type')" />
                <x-dl::field.date name="period_start" label="From" required :value="old('period_start', $r?->period_start?->toDateString() ?? now()->startOfMonth()->toDateString())" :source="$s('period_start')" />
                <x-dl::field.date name="period_end" label="Until" required :value="old('period_end', $r?->period_end?->toDateString() ?? now()->endOfMonth()->toDateString())" :source="$s('period_end')" />
            </x-dl::form.row>
        </x-dl::card>

        @if ($st === 'CALCULATED')
            <x-dl::card title="Verify clock-in / out times">
                <x-dl::field.select name="method" label="Verification method" :options="array_map('t', Run::METHODS)" :value="old('method', 'FULL')" :placeholder="null" :source="$s('verification_method')" />
            </x-dl::card>
        @endif

        @if ($summary)
            <x-dl::card title="Summary (read-only)">
                <x-dl::form.row :cols="4">
                    <x-dl::field.display label="Employees" :value="Locale::number((float) $summary['employees'], 0)" :source="Src::of('tm2pyr.pyr_payslip.id_payslip')" />
                    <x-dl::field.display label="Salary via bank transfer" :value="$summary['bank_count'].' · '.$idr($summary['bank_total'])" :source="Src::of('tm2pyr.pyr_payslip.total_gaji_bersih')" />
                    <x-dl::field.display label="Salary via cash" :value="$summary['cash_count'].' · '.$idr($summary['cash_total'])" :source="Src::of('tm2pyr.pyr_payslip.total_gaji_bersih')" />
                    <x-dl::field.display label="Total net salary" :value="$idr($summary['grand_total'])" :source="Src::of('tm2pyr.pyr_payslip.total_gaji_bersih')" />
                </x-dl::form.row>
                @if ($summary['anomalies'] > 0)
                    <x-dl::alert tone="warning" :message="tf(':n employees with anomalies (no active salary rate) are not included in the totals; check Master Employee Salary & Tax.', ['n' => $summary['anomalies']])" />
                @endif
            </x-dl::card>
        @endif
    </x-slot:period>
</x-dl::form>
