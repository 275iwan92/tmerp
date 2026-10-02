<?php

namespace App\Livewire\Shell;

use App\Models\Sys\SecLoginAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

// Isi drawer "Aktivitas saya" (disetujui user 2026-09-23): riwayat login +
// dokumen yang terakhir dibuat/diubah user ini. Tidak ada audit trail terpusat
// di tm2*, jadi sumbernya kolom created_by/updated_by (berisi login) di header
// dokumen utama. Dimuat hanya saat drawer dibuka (load()), bukan tiap render shell.
class UserActivity extends Component
{
    public bool $loaded = false;

    // [label, koneksi, tabel, kolom nomor dokumen]
    private const DOCUMENTS = [
        ['Inquiry', 'tm2sdi', 'iqp_inquiry', 'inquiry_no'],
        ['Quotation', 'tm2sdi', 'quo_quotation', 'quotation_no'],
        ['Customer Order', 'tm2sdi', 'co_order', 'co_no'],
        ['Purchase Requisition', 'tm2pur', 'pr_requisition', 'doc_no'],
        ['Purchase Order', 'tm2pur', 'po_order', 'doc_no'],
        ['Receipt (PV)', 'tm2pur', 'pv_receipt', 'doc_no'],
        ['Voucher', 'tm2acc', 'fin_voucher', 'voucher_number'],
        ['Work Order', 'tm2prd', 'prd_wo', 'wo_no'],
    ];

    public function load(): void
    {
        $this->loaded = true;
    }

    public function logins()
    {
        return SecLoginAttempt::where('login', Auth::user()->login)
            ->orderByDesc('attempted_at')
            ->limit(10)
            ->get();
    }

    public function documents(int $limit = 20): array
    {
        $login = Auth::user()->login;
        $rows = [];

        foreach (self::DOCUMENTS as [$label, $conn, $table, $noCol]) {
            $schema = Schema::connection($conn);
            if (! $schema->hasTable($table) || ! $schema->hasColumns($table, [$noCol, 'created_by', 'updated_by', 'created_at', 'updated_at'])) {
                continue;
            }

            $found = DB::connection($conn)->table($table)
                ->where(fn ($q) => $q->where('created_by', $login)->orWhere('updated_by', $login))
                ->orderByDesc(DB::raw('COALESCE(updated_at, created_at)'))
                ->limit($limit)
                ->get([$noCol.' as doc_no', 'created_by', 'updated_by', 'created_at', 'updated_at']);

            foreach ($found as $r) {
                $edited = $r->updated_by === $login && $r->updated_at && $r->updated_at != $r->created_at;
                $rows[] = [
                    'type' => $label,
                    'no' => $r->doc_no ?: '—',
                    'action' => $edited ? 'Diubah' : 'Dibuat',
                    'at' => $edited ? $r->updated_at : $r->created_at,
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($rows, 0, $limit);
    }

    public function render()
    {
        return view('livewire.shell.user-activity');
    }
}
