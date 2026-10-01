<?php

namespace App\Support\V2\Grid\Sales;

// Pro-Dev > New: inquiry NEW yang sudah Confirm oleh Sales (stat_confirm_sales = 1). To Do = belum Confirm ProDev.
// Buka baris = workbench ProDev part pertama yang belum Confirmed (part master dibuat saat itu, App\Services\Sales\InquiryStage).
class ProdevNewGrid extends InquiryDeskGrid
{
    public function slug(): string
    {
        return 'prodev-'.strtolower($this->type());
    }

    public function title(): string
    {
        return 'Pro-Dev '.self::TYPE_LABELS[$this->type()];
    }

    public function module(): string
    {
        return 'PROD';
    }

    public function icon(): string
    {
        return $this->type() === 'NEW' ? 'fa-solid fa-pen-ruler' : 'fa-solid fa-ruler-combined';
    }

    protected function type(): string
    {
        return 'NEW';
    }

    protected function desk(): string
    {
        return 'PRODEV';
    }

    protected function rowUrl(array $r): string
    {
        return route('v2.prodev.open', $r['id']);
    }
}
