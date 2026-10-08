{{-- My Work v2 (Fase 3): daftar kerja teknisi yang login (order Confirmed belum Close, ditugaskan kepadanya + belum punya teknisi). Daftar = objek Grid
     Transactional App\Support\V2\Grid\Maint\MyWorkListGrid, tampil lewat view generik _dbgrid. Open menuju order di layar PMO / BMO. --}}
@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Maint\MyWorkListGrid]])
