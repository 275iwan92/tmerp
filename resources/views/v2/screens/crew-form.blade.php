{{--
 REMARK
 Tujuan       : Layar Form Assign Crew (v2:crew-form): satu mesin dari grid, satu kotak crew per shift produksi aktif; seluruh tampilan = objek form khusus x-mp::production.crew-form.
 Sumber hulu  : App\Support\V2\Data\CrewForm::desk() (mesin, crew + periode berlaku, Master Shift, karyawan produksi); aturan di MachineCrewService.
 Input        : Query machine (wajib; hanya mesin Offset, selain itu 404).
 Output       : POST v2.crew.save -> MachineCrewService::saveMachine (semua kotak atau tidak sama sekali).
 Dipakai oleh : Dibuka dari grid Assign Crew (v2:crew).
 Status       : Aktif (dummy). Layar = satu baris; bentuk layar = pilihan Jo 14-Okt-2026 (alternatif A, dropdown Shift per kotak).
--}}
<x-mp::production.crew-form :crew="\App\Support\V2\Data\CrewForm::desk(\App\Services\Scheduling\Crew\MachineCrewService::assignableMachines()->findOrFail((int) request('machine')))" />
