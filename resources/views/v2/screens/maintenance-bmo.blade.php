{{--
 REMARK
 Tujuan       : Layar Breakdown Maintenance Order (BMO): jenis Breakdown dan Corrective, nomor BMO-yymm-nnnn; hanya membungkus partial order bersama PMO.
 Sumber hulu  : Partial v2.partials.maintenance-order (OrderService, model MntOrder di tm2mnt).
 Input        : series BMO diberikan ke partial; query id dibaca partial.
 Output       : Merender partial maintenance-order (grid, form order, aksi Confirm, Start, Hold, Done, Close).
 Dipakai oleh : Route v2.screen slug maintenance-bmo (App\Support\V2\Screens, menu sys_app_menu target v2:maintenance-bmo) di menu Maintenance
 Status       : Aktif
--}}
@include('v2.partials.maintenance-order', ['series' => 'BMO'])
