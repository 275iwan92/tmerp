<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Services\Report\ManagementReportService;
use App\Support\Localization\Dictionary;
use Devlib\Locale;

// Management Reports > Executive Summary (v2): penjualan (shipment / PO masuk), pembelian, invoice dan utang usaha dalam satu layar.
// Angka & aturan = App\Services\Report\ManagementReportService (sama dengan layar v1 report.executive); kelas ini hanya menyusun
// bentuk yang dibaca objek DevLib (x-dl::kpi, x-dl::chart, x-dl::table, x-dl::progress, x-dl::alert).
final class ExecutiveSummary
{
    public const BASES = ['shipment' => 'Shipment (Delivery Note)', 'order' => 'Incoming PO (Customer Order)'];

    private function __construct(private readonly ManagementReportService $svc, public readonly int $year, public readonly string $basis, public readonly bool $confirmedOnly) {}

    public static function for(?int $year, ?string $basis, bool $confirmedOnly): self
    {
        $svc = app(ManagementReportService::class);

        return new self($svc, $year ?: (int) ($svc->availableYears()[0] ?? now()->year), array_key_exists((string) $basis, self::BASES) ? (string) $basis : 'shipment', $confirmedOnly);
    }

    /** @return list<int> */
    public function years(): array
    {
        return $this->svc->availableYears();
    }

    /** @return list<string> nama bulan 3 huruf, mengikuti bahasa tampilan */
    public static function months(): array
    {
        return array_map(fn (string $m) => Dictionary::t($m, 'date.month_short'), ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    }

    /** Harga di Surat Jalan baru dicatat sistem lama sejak pertengahan 2023: dasar Shipment untuk tahun lebih awal tampak 0. */
    public function shipmentDataWarning(): bool
    {
        return $this->basis === 'shipment' && $this->year < ManagementReportService::SHIPMENT_VALUE_FROM_YEAR;
    }

    /** @return list<array<string, mixed>> kartu KPI (props x-dl::kpi) */
    public function kpis(): array
    {
        $s = $this->svc->yearSummary($this->year, $this->confirmedOnly);
        $invoice = $this->svc->invoiceSummary($this->year);
        $purchase = $this->svc->purchaseSummary($this->year);
        $payable = $this->svc->accountsPayable();
        $before = fn (float $value, ?float $growth) => $growth === null || $growth <= -100 ? null : $value / (1 + $growth / 100);

        return array_map(fn (array $k) => $k + ['format' => 'money'], [
            ['label' => 'Revenue by Shipment', 'value' => $s['shipment'], 'previous' => $before((float) $s['shipment'], $s['shipment_growth']), 'icon' => 'fa-truck-fast', 'tone' => 'accent',
                'note' => tf('vs :year · :n delivery notes', ['year' => $this->year - 1, 'n' => $s['shipment_docs']])],
            ['label' => 'Revenue by Incoming PO', 'value' => $s['order'], 'previous' => $before((float) $s['order'], $s['order_growth']), 'icon' => 'fa-file-invoice', 'tone' => 'success',
                'note' => tf('vs :year · :n COs', ['year' => $this->year - 1, 'n' => $s['order_docs']])],
            ['label' => 'Invoices Issued', 'value' => $invoice['value'], 'icon' => 'fa-receipt', 'tone' => 'violet', 'note' => tf(':n invoices issued', ['n' => $invoice['docs']])],
            ['label' => 'Purchase Value (PO)', 'value' => $purchase['po_value'], 'icon' => 'fa-cart-shopping', 'tone' => 'warn', 'note' => tf(':n purchase orders', ['n' => $purchase['po_docs']])],
            ['label' => 'Accounts Payable Outstanding', 'value' => $payable['outstanding'], 'goodWhen' => 'down', 'icon' => 'fa-scale-balanced', 'tone' => 'danger',
                'note' => tf('of :value invoiced', ['value' => Locale::money($payable['invoice_value'])])],
        ]);
    }

    /** @return array{labels: list<string>, series: list<array<string, mixed>>} omset bulanan tahun ini vs lalu */
    public function monthlyTrend(): array
    {
        $yoy = $this->svc->yearOverYear($this->year, $this->basis, $this->confirmedOnly);

        return ['labels' => self::months(), 'series' => [
            ['name' => (string) $this->year, 'data' => array_column($yoy, 'current')],
            ['name' => (string) ($this->year - 1), 'data' => array_column($yoy, 'previous')],
        ]];
    }

    /** @return array{labels: list<string>, series: list<array<string, mixed>>} */
    public function customerMix(): array
    {
        $top = $this->topCustomers();

        return ['labels' => array_column($top, 'name'), 'series' => [['name' => t('Revenue'), 'data' => array_column($top, 'value')]]];
    }

    /** @return list<array<string, mixed>> */
    public function topCustomers(): array
    {
        return $this->svc->topCustomers($this->year, $this->basis, 5);
    }

    /** @return array{labels: list<string>, series: list<array<string, mixed>>} */
    public function quarterly(): array
    {
        $q = $this->svc->quarterly($this->year);

        return ['labels' => ['Q1', 'Q2', 'Q3', 'Q4'], 'series' => [
            ['name' => t('Shipment'), 'data' => array_column($q, 'shipment')], ['name' => t('Incoming PO'), 'data' => array_column($q, 'order')]]];
    }

    /** @return array{labels: list<string>, series: list<array<string, mixed>>} */
    public function purchaseTrend(): array
    {
        return ['labels' => self::months(), 'series' => [['name' => t('Purchase'), 'data' => array_column($this->svc->monthlyPurchase($this->year), 'value')]]];
    }

    /** @return array{pr: int, po: int, receive: int, po_value: float} */
    public function purchaseDocs(): array
    {
        $p = $this->svc->purchaseSummary($this->year);

        return ['pr' => $p['pr_docs'], 'po' => $p['po_docs'], 'receive' => $p['receive_docs'], 'po_value' => $p['po_value']];
    }

    public function purchaseLastDate(): ?string
    {
        return $this->purchaseDocs()['po'] === 0 ? $this->svc->purchaseDataLastDate() : null;
    }

    /** @return list<array{label: string, pct: float}> */
    public function dataHealth(): array
    {
        return $this->svc->purchasingDataHealth();
    }

    /** @return list<array{tone: string, title: string, text: string}> */
    public function attention(): array
    {
        return $this->svc->managementAttention();
    }
}
