{{--
 REMARK
 Tujuan       : Layar Preventive Maintenance Order (PMO): jenis Preventive dan Inspection, nomor PMO-yymm-nnnn; hanya membungkus partial order bersama BMO.
 Sumber hulu  : Partial v2.partials.maintenance-order (OrderService, model MntOrder di tm2mnt; Draft dibuat juga oleh perintah maintenance:generate-pm).
 Input        : series PMO diberikan ke partial; query id dibaca partial.
 Output       : Merender partial maintenance-order (grid, form order, aksi Confirm, Start, Hold, Done, Close).
 Dipakai oleh : Route v2.screen slug maintenance-pmo (App\Support\V2\Screens, menu sys_app_menu target v2:maintenance-pmo) di menu Maintenance; tautan dari PM Calendar dan Preventive Plan
 Status       : Aktif
--}}
@include('v2.partials.maintenance-order', ['series' => 'PMO'])
