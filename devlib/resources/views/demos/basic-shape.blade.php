<div class="dl-doc">
    <h1>Spacing · Radius · Shadow</h1>
    <p>Jarak, sudut, dan bayangan baku. Komponen baru wajib memakai token ini, bukan angka sendiri.</p>
    <h2>Spacing</h2>
    <x-dl::token-table kind="space" :tokens="['--dl-space-1' => 'Rapat sekali', '--dl-space-2' => 'Ikon ke teks', '--dl-space-3' => 'Antar field (form)', '--dl-space-4' => 'Isi tombol kecil', '--dl-space-5' => 'Isi kartu', '--dl-space-6' => 'Antar bagian', '--dl-space-7' => 'Isi halaman', '--dl-space-8' => 'Jarak besar']" />
    <h2>Radius</h2>
    <x-dl::token-table kind="radius" :tokens="['--dl-radius-sm' => 'Input form, tombol kecil', '--dl-radius' => 'Pesan, dropdown', '--dl-radius-md' => 'Input & tombol login', '--dl-radius-lg' => 'Kartu, widget', '--dl-radius-xl' => 'Modal, kotak login']" />
    <h2>Shadow</h2>
    <x-dl::token-table kind="shadow" :tokens="['--dl-shadow-pop' => 'Menu popup, dropdown', '--dl-shadow-modal' => 'Modal, kotak login']" />
</div>
