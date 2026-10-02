<?php

namespace App\Support\V2\Grid\Acc;

use App\Support\V2\Grid\DbGrid;

// Induk grid Finance & Accounting (hak akses FINACC). Kelas akun untuk saringan = pengelompokan layar v1.
abstract class AccGrid extends DbGrid
{
    protected const ACCOUNT_CLASSES = ['ASSET' => 'Asset', 'LIABILITY' => 'Liability', 'EQUITY' => 'Equity', 'REVENUE' => 'Revenue', 'COGS' => 'Cost of sales',
        'EXPENSE' => 'Expense', 'OTHER_INCOME' => 'Other income', 'OTHER_EXPENSE' => 'Other expense'];

    public function module(): string
    {
        return 'FINACC';
    }

    public function moduleLabel(): string
    {
        return 'Finance & Accounting';
    }

    protected function accountClass(?string $type): ?string
    {
        return isset(self::ACCOUNT_CLASSES[$type]) ? t(self::ACCOUNT_CLASSES[$type]) : $type;
    }
}
