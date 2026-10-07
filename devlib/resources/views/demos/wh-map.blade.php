{{-- Contoh x-dl::wh-map: gedung berbentuk huruf L (30 x 20 m, sudut kanan-atas 10 x 8 m dipotong), 2 lantai.
     Data contoh di sini saja (devlib.test tidak punya database bisnis); di aplikasi isinya dari database. --}}
@php
    ['floors' => $floors, 'bins' => $bins] = \App\Support\DemoData::warehouse();
@endphp
<div class="dl-demo">
    <x-dl::wh-map id="demo-whmap" title="Building L · L shape 30 x 20 m" :width="30" :height="20"
        :outline="[[0, 0], [20, 0], [20, 8], [30, 8], [30, 20], [0, 20]]" :floors="$floors" :bins="$bins" selected="L-PPR-L03-01" restack-url="#restack-{id}-{dir}"
        :actions="[['label' => 'Transfer', 'icon' => 'fa-right-left', 'tone' => 'primary', 'when' => 'stock', 'url' => '#transfer-{code}'], ['label' => 'Label', 'icon' => 'fa-qrcode', 'url' => '#label-{code}']]" />
</div>
