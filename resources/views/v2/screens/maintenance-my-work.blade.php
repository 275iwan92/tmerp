{{--
 REMARK
 Tujuan       : My Work: daftar kerja teknisi yang login (order Confirmed belum Close, ditugaskan kepadanya atau belum punya teknisi); Open menuju order di PMO atau BMO.
 Sumber hulu  : Grid App\Support\V2\Grid\Maint\MyWorkListGrid (model MntOrder, tm2mnt).
 Input        : Sesi v2_flash; user login.
 Output       : Daftar lewat view generik _dbgrid; tidak ada posting.
 Dipakai oleh : Route v2.screen slug maintenance-my-work (App\Support\V2\Screens, menu sys_app_menu target v2:maintenance-my-work) di menu Maintenance
 Status       : Aktif (Fase 3)
--}}
@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Maint\MyWorkListGrid]])
