<?php

namespace App\Services\Hrm;

use App\Models\Master\EmpBankAccount;
use App\Models\Master\SupSupplier;

// Satu-satunya jalur ubah rekening gaji pegawai (keputusan user 2026-09-23):
// dipakai Master Employee, Payroll > Rekening Bank, dan persetujuan koreksi
// Self-Service. Bank dipilih dari Master Supplier kategori BANK; pegawai hanya
// menyimpan No. Rekening + Atas Nama. bank_name = salinan nama bank saat disimpan
// (dipakai Generator Transfer Bank).
class EmployeeBankService
{
    /** @return array<int, string> id_supplier => nama bank (supplier kategori BANK aktif) */
    public static function bankOptions(): array
    {
        return SupSupplier::where('category', 'BANK')->where('activate', true)
            ->orderBy('supplier_name')->pluck('supplier_name', 'id_supplier')->all();
    }

    public static function primary(int $employeeId): ?EmpBankAccount
    {
        return EmpBankAccount::where('employee_id', $employeeId)->where('is_primary', true)->where('activate', true)->first();
    }

    // bankSupplierId null = pegawai dibayar CASH: rekening lama dinonaktifkan
    // (bukan dihapus, jejak histori tetap ada).
    public static function setPrimary(int $employeeId, ?int $bankSupplierId, ?string $accountNo, ?string $holderName, string $login): ?EmpBankAccount
    {
        $current = self::primary($employeeId);

        if (! $bankSupplierId) {
            $current?->update(['activate' => false, 'updated_by' => $login]);

            return null;
        }

        $bank = SupSupplier::where('category', 'BANK')->findOrFail($bankSupplierId);
        $data = [
            'bank_supplier_id' => $bank->id_supplier,
            'bank_name' => trim($bank->supplier_name),
            'account_no' => trim((string) $accountNo),
            'account_holder_name' => trim((string) $holderName),
            'is_primary' => true,
            'activate' => true,
            'updated_by' => $login,
        ];

        if ($current) {
            $current->update($data);

            return $current;
        }

        return EmpBankAccount::create($data + ['employee_id' => $employeeId, 'created_by' => $login]);
    }
}
