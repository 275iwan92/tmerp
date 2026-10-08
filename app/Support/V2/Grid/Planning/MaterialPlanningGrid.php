<?php

namespace App\Support\V2\Grid\Planning;

use App\Support\V2\Data\MaterialPlanning;

// Material Planning (MRP baru): satu baris per bahan -- butuh (CO + forecast), ditutup stok / PO / PR, kurang, pesan paling lambat.
// Klik 2x = proyeksi saldo bahan itu per tanggal. Create PR dari bahan yang kurang.
class MaterialPlanningGrid extends MaterialBookingGrid
{
    public function slug(): string
    {
        return 'material-planning';
    }

    public function title(): string
    {
        return 'Material Planning (MRP)';
    }

    public function icon(): string
    {
        return 'fa-solid fa-chart-gantt';
    }

    public function search(): string
    {
        return 'Search material, order no....';
    }

    public function rows(): array
    {
        return MaterialPlanning::materialRows();
    }

    public function detail(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            ['key' => 'material', 'label' => 'Material', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'role', 'label' => 'Kind'],
            ['key' => 'need', 'label' => 'Needed', 'type' => 'number', 'decimals' => 2],
            ['key' => 'stock', 'label' => 'From Stock', 'type' => 'number', 'decimals' => 2],
            ['key' => 'incoming', 'label' => 'From PO / PR', 'type' => 'number', 'decimals' => 2],
            ['key' => 'short', 'label' => 'Short', 'type' => 'number', 'decimals' => 2],
            ['key' => 'uom', 'label' => 'UoM'],
            ['key' => 'first_need', 'label' => 'First Needed', 'type' => 'date'],
            ['key' => 'order_by', 'label' => 'Order By (latest)', 'type' => 'date'],
            ['key' => 'lead', 'label' => 'Lead (days)', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Coverage', 'type' => 'badge', 'tones' => MaterialPlanning::COVER_TONES],
            ['key' => 'supplier', 'label' => 'Suggested Supplier'],
            ['key' => 'orders', 'label' => 'For Orders'],
            ['key' => 'stage', 'label' => 'Stage', 'type' => 'badge', 'tones' => ['Not CO yet' => 'info', 'Includes not CO yet' => 'info']],
            ['key' => 'code', 'label' => 'Code', 'visible' => false],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Short', 'where' => ['status' => 'Short'], 'tone' => 'danger'],
            ['label' => 'Need item', 'where' => ['status' => ['Need item', 'Need layout']], 'tone' => 'danger'],
            ['label' => 'Late', 'where' => ['status' => 'Late'], 'tone' => 'warn'],
            ['label' => 'Waiting PO/PR', 'where' => ['status' => 'Waiting PO/PR'], 'tone' => 'info'],
            ['label' => 'Covered', 'where' => ['status' => ['Covered by stock', 'Booked']], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['role' => 'Kind', 'status' => 'Coverage', 'supplier' => 'Suggested Supplier'];
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.material-planning'] + parent::screen();
    }
}
