{{-- Form khusus Customer 360 (halaman satu customer, hanya membaca). 'price' => false menyembunyikan semua nilai rupiah.
     Di aplikasi: <x-mp::sales.customer-card :card="Crm::card($customer, $home)" /> --}}
@php
    $months = ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'];
@endphp
<div class="dl-demo">
    <x-mp::sales.customer-card :card="[
        'home' => '#', 'price' => true, 'title' => 'PT Contoh Kosmetika', 'description' => 'C0042 · Contoh Group · Cosmetics · Active',
        'buttons' => [['fa-phone-volume', '+ Activity', '#'], ['fa-triangle-exclamation', '+ Complaint', '#'], ['fa-building', 'Master Customer', '#'], ['back', 'Back', '#']],
        'facts' => [
            [['First Order', '14-Mar-2021', []], ['Last Order', '28-Sep-2026', []], ['Orders (all time)', '86', []], ['Order Value (all time)', 'IDR 4.250.000.000', []], ['Last Contact', '01-Oct-2026', []]],
            [['Open Quotations', '2', []], ['Open Orders', '3', []], ['Open Complaints', '1', []], ['Open Follow-ups', '1', []], ['Next Follow-up', '07-Oct-2026 · Due soon · Send new price', []]],
        ],
        'chart' => ['labels' => $months, 'count' => [3, 2, 4, 3, 5, 2, 4, 6, 3, 4, 5, 2], 'value' => [120, 80, 160, 130, 210, 90, 170, 260, 140, 180, 220, 95]],
        'products' => [['product' => 'Folding Box Serum 30 ml', 'code' => 'FB-0012', 'n' => 14, 'qty' => '350.000', 'unit' => 'PCS', 'last' => '28-Sep-2026']],
        'profile' => [[4, [['Contact', 'Ibu Rina', []], ['Phone', '031-555-0100', []], ['E-mail', 'purchasing@example.com', []], ['Payment Term', '30 days', []]]]],
        'activities' => [['id' => 1, 'date' => '2026-10-01', 'type' => 'Call', 'topic' => 'Price', 'contact' => 'Ibu Rina', 'result' => 'Follow up', 'result_tone' => 'warn',
            'notes' => 'Asked for a new price', 'next_date' => '2026-10-07', 'next' => 'Send new price', 'due' => 'Due soon', 'due_tone' => 'yellow', 'by' => 'sales1', 'bucket' => 'SOON']],
        'activityUrl' => '#', 'quotations' => [['id' => 1, 'no' => 'QT2600051', 'date' => '2026-09-20', 'valid' => '2026-10-20', 'total' => 185000000, 'status' => 'PUBLISHED']], 'quotationUrl' => '#',
        'orders' => [['no' => 'CO2600029', 'date' => '2026-09-28', 'po' => 'PO-7781', 'total' => 96000000, 'status' => 'CONFIRMED', 'source' => 'New system']],
        'deliveries' => [['no' => 'DO2600014', 'date' => '2026-10-02', 'ship' => '2026-10-03', 'status' => 'SHIPPED', 'source' => 'New system']],
        'complaints' => [], 'complaintUrl' => '#', 'qaTones' => [], 'complaintTones' => [],
        'contacts' => [['name' => 'Ibu Rina', 'position' => 'Purchasing', 'phone' => '0812-0000-0000', 'email' => 'rina@example.com', 'note' => '—']],
        'branches' => [],
    ]" />
</div>
