{{--
    Wrapper scroll+sticky-header standar semua grid (rekonsiliasi 2026-08-27 --
    dulu co/list pakai max-h-[65vh]+sticky th, raw-material tidak, sekarang
    disamakan: sticky header menang, dipakai semua grid yang migrasi ke
    component ini). <th> di dalam <thead> tetap wajib class
    "sticky top-0 z-[1]" manual (jumlah & lebar kolom beda-beda per grid,
    tak cukup digeneralisasi jadi 1 component tanpa slot kompleks).
--}}
<div class="max-h-[65vh] overflow-auto">
    {{ $slot }}
</div>
