{{--
    1 lembar slip gaji. Dipakai 2 layar: cetak 1 slip (payroll-payslip-print)
    dan cetak massal 1 periode (payroll-payslip-print-all), jadi isinya HARUS
    tetap identik di dua jalur itu.

    Variabel: $payslip, $employee, $bankAccount, $attendance, $company.
--}}
<x-print.sheet>
    <x-print.header :company="$company" doc-title="SLIP GAJI KARYAWAN" doc-no="{{ $payslip->run->period_type }}-{{ $payslip->id_payslip }}">
        <x-slot:meta>
            Periode: {{ $payslip->run->period_start->translatedFormat('d F Y') }} — {{ $payslip->run->period_end->translatedFormat('d F Y') }}
        </x-slot:meta>
    </x-print.header>

    <x-print.parties columns="1fr 1fr">
        <x-print.party label="IDENTITAS PEGAWAI">
            <div class="nm">{{ $employee?->full_name ?? '—' }}</div>
            <div class="dt">
                NIK: <b>{{ $employee?->employee_code ?? '—' }}</b><br>
                Divisi/Posisi: {{ $employee?->position?->department?->department_name ?? '—' }} / {{ $employee?->position?->position_name ?? '—' }}
            </div>
        </x-print.party>
        <x-print.party label="PEMBAYARAN & KEHADIRAN">
            <div class="dt">
                Metode:
                @if ($bankAccount)
                    <b>{{ $bankAccount->bank_name }} ({{ $bankAccount->account_no }})</b>
                @else
                    <b>CASH (TUNAI)</b>
                @endif
                <br>
                Kehadiran: {{ $attendance['hadir'] }} Hadir, {{ $attendance['telat'] }} Telat, {{ $attendance['alpha'] }} Alpha
            </div>
        </x-print.party>
    </x-print.parties>

    <x-print.section number="1" title="Rincian Gaji">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:8.5pt">
            <div>
                <h4 style="color:#1f6f4a;text-transform:uppercase;font-size:7.6pt;letter-spacing:.6px;border-bottom:1px solid #d4d8dd;padding-bottom:3px;margin-bottom:5px">(+) Pendapatan</h4>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Gaji Pokok</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_pokok, 0, ',', '.') }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Tunjangan Keahlian</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_tj_keahlian, 0, ',', '.') }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Tunjangan Loyalitas</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_tj_loyalitas, 0, ',', '.') }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Tunjangan Prestasi</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_tj_prestasi, 0, ',', '.') }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Bonus Pagi + Hadir</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_bonus_pagi + (float) $payslip->total_bonus_hadir, 0, ',', '.') }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Uang Lembur</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_overtime_pay, 0, ',', '.') }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Uang Roti</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_uang_roti, 0, ',', '.') }}</span></div>
            </div>
            <div>
                <h4 style="color:#a3241f;text-transform:uppercase;font-size:7.6pt;letter-spacing:.6px;border-bottom:1px solid #d4d8dd;padding-bottom:3px;margin-bottom:5px">(-) Potongan & Denda</h4>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>Denda Presensi</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_denda, 0, ',', '.') }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>BPJS Ketenagakerjaan+Kesehatan</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_bpjs_employee, 0, ',', '.') }}</span></div>
                <div style="display:flex;justify-content:space-between;padding:1.5px 0"><span>PPh 21</span><span style="font-family:Consolas,monospace">Rp {{ number_format((float) $payslip->total_pph21, 0, ',', '.') }}</span></div>
            </div>
        </div>
    </x-print.section>

    <div style="background:#e9f6ef;border:1px solid #a7e5bf;padding:10px 14px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;margin-top:10px">
        <div>
            <div style="font-size:8pt;font-weight:700;color:#166534;text-transform:uppercase">Gaji Bersih Diterima (Net Salary)</div>
            @if ($bankAccount)
                <div style="font-size:7pt;color:#166534">Ditransfer ke {{ $bankAccount->bank_name }} a.n {{ $bankAccount->account_holder_name }}</div>
            @endif
        </div>
        <div style="font-size:16pt;font-weight:800;font-family:Consolas,monospace;color:#14532d">Rp {{ number_format((float) $payslip->total_gaji_bersih, 0, ',', '.') }}</div>
    </div>

    <x-print.signatures :columns="'1fr 1fr'">
        <x-print.signature role="Penerima" :name="'( '.($employee?->full_name ?? '—').' )'" />
        <x-print.signature role="Finance / Payroll Dept" name="( Staff Finance )" />
    </x-print.signatures>

    <x-print.footer :page="'Slip #'.$payslip->id_payslip">Dicetak {{ now()->translatedFormat('d F Y H:i') }} WIB</x-print.footer>
</x-print.sheet>
