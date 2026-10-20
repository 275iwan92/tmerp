{{--
 REMARK
 Tujuan       : Process Rate: target rate default (qty per jam) per Produk + Part + Proses untuk Mesin (tab Mesin) dan Pegawai manual (tab Pegawai); dipakai WO Generation dan tombol refill di Bill of Process, selalu bisa ditimpa manual per WO.
 Sumber hulu  : App\Support\V2\Grid\Prod\MachineRateLookup dan EmployeeRateLookup (prod_part_process_machine_rate, prod_part_process_employee_rate); pindahan layar v1 pages::production.process-rate.
 Input        : Tidak ada (kedua sheet memuat sendiri).
 Output       : x-dl::tabs dengan dua x-dl::lookup-grid lewat v2.screens._dbgrid.
 Dipakai oleh : Layar v2 slug production-process-rate lewat rute v2.screen (item menu PROD_PROCESS_RATE tetap tersembunyi seperti v1).
 Status       : Aktif
--}}
<x-dl::tabs :tabs="['machine' => 'Machine', 'employee' => 'Employee (Manual)']">
    <x-slot:machine>
        @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Prod\MachineRateLookup]])
    </x-slot:machine>
    <x-slot:employee>
        @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Prod\EmployeeRateLookup]])
    </x-slot:employee>
</x-dl::tabs>
