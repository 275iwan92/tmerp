<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Form khusus Customer 360 (user 2026-10-02, CRM): halaman SATU customer, hanya membaca. Kepala dengan tombol + Activity,
// + Complaint, Master Customer, Back dan dua baris angka kunci; lalu tab Overview (grafik order 24 bulan, product yang sering
// dipesan, profil dari Master Customer), Activities, Quotations, Orders, Deliveries, Complaints, Contacts.
// Layar hanya menyerahkan $card (App\Support\V2\Data\Crm::card()); 'price' = false menyembunyikan semua nilai rupiah (hak lihat
// harga jual diputuskan pemilik data). Kolom tiap tab ditetapkan di sini.
class CustomerCard extends DevlibComponent
{
    public const DUE_STATS = [
        ['label' => 'All'],
        ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'],
        ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'],
        ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow'],
    ];

    public function __construct(
        public array $card = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.customer-card';
    }

    public function price(): bool
    {
        return (bool) ($this->card['price'] ?? false);
    }

    public function tabs(): array
    {
        return ['overview' => ['Overview', 'fa-chart-column'], 'activities' => ['Activities', 'fa-phone-volume'], 'quotations' => ['Quotations', 'fa-file-signature'],
            'orders' => ['Orders', 'fa-bag-shopping'], 'deliveries' => ['Deliveries', 'fa-truck'], 'complaints' => ['Complaints', 'fa-triangle-exclamation'], 'contacts' => ['Contacts', 'fa-address-book']];
    }

    public function activityColumns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'topic', 'label' => 'Topic'],
            ['key' => 'contact', 'label' => 'Contact'],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'toneKey' => 'result_tone'],
            ['key' => 'notes', 'label' => 'Notes'],
            ['key' => 'next_date', 'label' => 'Follow-up', 'type' => 'date'],
            ['key' => 'next', 'label' => 'Next Action'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
            ['key' => 'by', 'label' => 'By'],
        ];
    }

    public function quotationColumns(): array
    {
        return array_values(array_filter([
            ['key' => 'no', 'label' => 'Quotation No.'],
            ['key' => 'date', 'label' => 'Issued', 'type' => 'date'],
            ['key' => 'valid', 'label' => 'Valid Until', 'type' => 'date'],
            $this->price() ? ['key' => 'total', 'label' => 'Total', 'type' => 'money'] : null,
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'PUBLISHED' => 'info', 'WON' => 'success', 'LOST' => 'danger', 'CANCELLED' => 'danger']],
        ]));
    }

    public function orderColumns(): array
    {
        return array_values(array_filter([
            ['key' => 'no', 'label' => 'Order No.'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'po', 'label' => 'Customer PO'],
            $this->price() ? ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true] : null,
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'CONFIRMED' => 'success', 'CLOSED' => 'info', 'CANCELLED' => 'danger', 'UNKNOWN' => 'neutral']],
            ['key' => 'source', 'label' => 'Source'],
        ]));
    }

    public function deliveryColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Delivery No.'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'ship', 'label' => 'Shipped', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'CONFIRMED' => 'success', 'SHIPPED' => 'info', 'CANCELLED' => 'danger']],
            ['key' => 'source', 'label' => 'Source'],
        ];
    }

    public function complaintColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Complaint No.'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'qa', 'label' => 'QA', 'type' => 'badge', 'tones' => $this->card['qaTones'] ?? []],
            ['key' => 'resolution', 'label' => 'Resolution'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => $this->card['complaintTones'] ?? []],
        ];
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::sales.customer-card');
    }
}
