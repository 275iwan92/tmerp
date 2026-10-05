<div class="dl-demo" style="max-width:680px">
    <x-dl::notification-list :items="[
        ['title' => 'PO baru menunggu approval', 'body' => 'PO-2609-0014 dari PT Contoh Kertas Jaya senilai IDR 12.500.000', 'icon' => 'approve', 'tone' => 'success', 'at' => now()->subMinutes(2)],
        ['title' => 'Pembayaran diterima', 'body' => 'Invoice INV-2609-0031 lunas IDR 48.900.000', 'icon' => 'money', 'tone' => 'accent', 'at' => now()->subMinutes(15)],
        ['title' => 'Customer baru', 'body' => 'CV Contoh Makmur didaftarkan oleh Sales', 'icon' => 'users', 'tone' => 'accent', 'at' => now()->subHour()],
        ['title' => 'Surat Jalan terkirim', 'body' => 'DO-2609-0120 sudah diterima customer', 'icon' => 'submit', 'tone' => 'success', 'at' => now()->subHours(2), 'read' => true],
        ['title' => 'Pembaruan sistem', 'body' => 'DevLib Part 3 terpasang', 'icon' => 'settings', 'tone' => 'warn', 'at' => now()->subHours(3), 'read' => true],
        ['title' => 'Pembayaran gagal', 'body' => 'Transfer ke supplier SUP-003 ditolak bank', 'icon' => 'money', 'tone' => 'danger', 'at' => now()->subHours(4)],
    ]" />
</div>
