<div class="dl-doc">
    <h1>Breakpoints</h1>
    <p>Batas lebar layar yang dipakai semua komponen. Frame & login otomatis penuh layar di HP (&lt; 760px).</p>
    <x-dl::token-table kind="text" :tokens="[
        '--dl-bp-phone' => 'HP: box login penuh layar, widget disembunyikan, form 1 kolom',
        '--dl-bp-tablet' => 'Tablet: sidebar bisa diciutkan',
        '--dl-bp-laptop' => 'Laptop/desktop: tata letak penuh',
    ]" />
</div>
