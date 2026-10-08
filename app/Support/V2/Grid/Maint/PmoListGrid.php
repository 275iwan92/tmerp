<?php

namespace App\Support\V2\Grid\Maint;

// Daftar Preventive Maintenance Order (PMO): jenis Preventive dan Inspection, nomor PMO-yymm-nnnn.
class PmoListGrid extends OrderListGrid
{
    public function slug(): string
    {
        return 'maintenance-pmo';
    }

    public function title(): string
    {
        return 'Preventive Maintenance Order';
    }

    protected function series(): string
    {
        return 'PMO';
    }

    protected function listId(): string
    {
        return 'mnt-pmo-list';
    }
}
