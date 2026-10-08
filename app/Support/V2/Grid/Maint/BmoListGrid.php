<?php

namespace App\Support\V2\Grid\Maint;

// Daftar Breakdown Maintenance Order (BMO): jenis Breakdown dan Corrective, nomor BMO-yymm-nnnn. Layar dan menu menyusul di Fase 3.
class BmoListGrid extends OrderListGrid
{
    public function slug(): string
    {
        return 'maintenance-bmo';
    }

    public function title(): string
    {
        return 'Breakdown Maintenance Order';
    }

    protected function series(): string
    {
        return 'BMO';
    }

    protected function listId(): string
    {
        return 'mnt-bmo-list';
    }
}
