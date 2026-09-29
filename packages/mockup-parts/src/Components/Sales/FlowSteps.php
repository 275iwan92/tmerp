<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Bar alur dokumen di atas layar Sales (user 2026-09-29): posisi layar ini di perjalanan order, istilah Inggris yang jelas.
// Urutan = docs/flow_product_master.md §1. $current = kunci langkah yang disorot; langkah sebelumnya ditandai selesai.
class FlowSteps extends DevlibComponent
{
    public const STEPS = [
        'inquiry' => ['Inquiry', 'Sales records the request'],
        'prodev' => ['Product Development', 'ProDev: layout, BOM, BOP'],
        'procost' => ['Product Costing', 'ProCost: price per qty tier'],
        'priced' => ['Priced Inquiry', 'Price returns to Sales'],
        'quotation' => ['Quotation', 'WIN → Master Product'],
        'order' => ['Customer Order', 'Order confirmed'],
        'shipping' => ['Shipping Schedule', 'Delivery plan (CPDEL)'],
        'production' => ['Post to Production', 'Work Order'],
    ];

    public function __construct(public string $current = 'inquiry', public ?string $note = null) {}

    public function tag(): string
    {
        return 'x-mp::sales.flow-steps';
    }

    public function state(string $key): string
    {
        $keys = array_keys(self::STEPS);
        $at = array_search($this->current, $keys, true);
        $i = array_search($key, $keys, true);

        return $i < $at ? 'is-done' : ($i === $at ? 'is-now' : '');
    }

    public function render()
    {
        return <<<'BLADE'
            <nav {{ $attributes->class('mp-flow') }} {!! $dl() !!} aria-label="Order flow">
                <ol>
                    @foreach (\MockupParts\Components\Sales\FlowSteps::STEPS as $key => [$title, $sub])
                        <li class="{{ $state($key) }}" @if ($state($key) === 'is-now') aria-current="step" @endif><b>{{ $loop->iteration }}</b><span>{{ $title }}<small>{{ $sub }}</small></span></li>
                    @endforeach
                </ol>
                @if ($note)<p>{{ $note }}</p>@endif
            </nav>
            BLADE;
    }
}
