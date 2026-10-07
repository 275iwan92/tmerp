<?php

namespace App\Support\V2\Print;

use App\Models\Pyr\PyrPayrollRun;
use App\Support\V2\Data\PayrollDisbursement;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Serah terima gaji tunai (user 2026-10-07, pindahan "Cetak Serah Terima" layar v1): satu baris per pegawai tanpa rekening bank, nilai gaji bersih,
// rincian amplop per pecahan uang, dan kolom tanda tangan tangan. $id = id periode payroll (pyr_payroll_run). Nilai dari PayrollDisbursement::cashRows.
final class CashRosterPrint extends Document
{
    public string $type = 'cash-roster';

    public string $title = 'Cash Salary Handover';

    public ?string $subtitle = 'Payroll · Internal';

    public string $numberLabel = 'Period';

    public array $refs = [
        ['key' => 'employees', 'label' => 'Employees'],
        ['key' => 'denominations', 'label' => 'Notes Needed'],
    ];

    public string $partyLabel = 'Paid By';

    public string $nb = 'Each employee signs when the envelope is received. Count the notes against the envelope breakdown before handing it over.';

    public array $extra = [
        ['key' => 'envelope', 'label' => 'Envelope Breakdown', 'width' => 22, 'wrap' => true],
        ['key' => 'sign', 'label' => 'Signature', 'width' => 16, 'hand' => true],
    ];

    public bool $priced = true;

    public array $receivers = [['Payroll Officer', 'Hands over the envelopes']];

    public static function load(int $id, array $opt = []): static
    {
        $run = PyrPayrollRun::findOrFail($id);
        $cash = PayrollDisbursement::cashRows($id);
        $notes = collect(PayrollDisbursement::denominationTotals($cash))->filter()->map(fn ($n, $d) => $n.'x '.($d / 1000).'k')->implode(', ');

        return new self([
            'number' => Locale::d($run->period_start).' — '.Locale::d($run->period_end), 'date' => now()->toDateString(), 'status' => $run->run_status, 'printedBy' => $opt['printedBy'] ?? null,
            'sums' => ['lines' => [], 'grand' => array_sum(array_column($cash, 'net'))],
            'employees' => count($cash), 'denominations' => $notes ?: '—',
            'party' => ['name' => $opt['printedBy'] ?? '', 'lines' => []],
            'rows' => array_map(fn ($r) => ['name' => $r['code'].' / '.$r['name'], 'sub' => $r['dept'], 'qty' => 1, 'unit' => '', 'price' => $r['net'], 'subtotal' => $r['net'],
                'envelope' => PayrollDisbursement::breakdownText($r['breakdown']), 'sign' => ''], $cash),
        ]);
    }
}
