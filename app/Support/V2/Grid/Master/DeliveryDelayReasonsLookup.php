<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Sdi\CoDelayReason;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Delivery Delay Reasons: alasan jadwal kirim bergeser (CPDEL). Tiap alasan menunjuk departemen penanggung jawab = dasar KPI
// janji kirim per departemen. Nama English; terjemahan di Setup > Translation Dictionary.
class DeliveryDelayReasonsLookup extends DbLookup
{
    public const DEPARTMENTS = ['Production' => 'Production', 'PPIC' => 'PPIC', 'Purchasing' => 'Purchasing', 'Maintenance' => 'Maintenance',
        'Sales' => 'Sales', 'Delivery' => 'Delivery', 'Customer' => 'Customer', 'Other' => 'Other'];

    public function slug(): string
    {
        return 'delivery-delay-reasons';
    }

    public function title(): string
    {
        return 'Delivery Delay Reasons';
    }

    public function module(): string
    {
        return 'MDATA';
    }

    public function moduleLabel(): string
    {
        return 'Master Data';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clock-rotate-left';
    }

    public function guide(): ?string
    {
        return 'Reasons used when production cannot meet the customer slot or the slot is changed. The department is who answers for it in the delivery promise KPI.';
    }

    protected function query(): Builder
    {
        return CoDelayReason::query()->orderBy('id_delay_reason');
    }

    public function columns(): array
    {
        return [
            ['key' => 'reason_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'case' => 'uppercase', 'width' => '190px'],
            ['key' => 'reason_name', 'label' => 'Reason (English)', 'required' => true, 'width' => '320px'],
            ['key' => 'department', 'label' => 'Responsible department', 'type' => 'select', 'required' => true, 'options' => array_map('t', self::DEPARTMENTS), 'width' => '200px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '90px'],
        ];
    }
}
