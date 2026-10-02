<?php

namespace App\Livewire\Concerns;

use App\Models\Acc\FinAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Helper bersama layar Finance & Accounting (blueprint LedgerOne):
// eksekusi aksi service dgn pesan error ramah, nama user, opsi akun.
trait FinanceScreen
{
    public ?string $modal = null;

    public string $errorMessage = '';

    public function closeModal(): void
    {
        $this->modal = null;
        $this->errorMessage = '';
        $this->resetErrorBag();
    }

    protected function login(): string
    {
        return (string) Auth::user()?->login;
    }

    // Jalankan aksi; exception bisnis (DomainException/HttpException 422)
    // ditampilkan sbg pesan, bukan halaman error.
    protected function attempt(callable $fn, ?string $success = null): bool
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $msg = $e->getMessage() ?: t('Process failed.');
            if ($this->modal) {
                $this->errorMessage = $msg;
            } else {
                session()->flash('error', $msg);
            }

            return false;
        }
        if ($success) {
            session()->flash('flash', $success);
        }

        return true;
    }

    /** login => nama tampilan (sec_user.name). */
    protected function userNames(iterable $logins): array
    {
        $logins = collect($logins)->filter()->unique()->values();
        if ($logins->isEmpty()) {
            return [];
        }

        return DB::connection('tm2sys')->table('sec_user')->whereIn('login', $logins)->pluck('name', 'login')->all();
    }

    public function postableAccountOptions(?string $prefix = null): array
    {
        return FinAccount::where('is_postable', true)->where('activate', true)
            ->when($prefix, fn ($q) => $q->where('account_code', 'like', $prefix.'%'))
            ->orderBy('account_code')->get()
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    public function cashBankOptions(): array
    {
        return $this->postableAccountOptions('111');
    }

    // Ekspor CSV sederhana (tombol Export di mockup) -- dibuka Excel langsung.
    protected function csvDownload(string $filename, array $headers, iterable $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM: Excel baca UTF-8
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
