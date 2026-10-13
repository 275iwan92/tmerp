{{--
 REMARK
 Tujuan       : Menampilkan bagan organisasi (jabatan dan pemegangnya) hanya baca dengan cari, sorot departemen, lipat / buka, zoom, unduh.
 Sumber hulu  : App\Support\V2\Data\OrgStructure (org_position.reports_to_position_id, org_department, emp_employee).
 Input        : -
 Output       : x-dl::org-chart (read-only), alert bila belum ada jabatan atau semua jabatan berdiri sendiri, tombol ke Master Position.
 Dipakai oleh : Rute v2.screen dengan slug 'hrm-org-chart' (menu v2:hrm-org-chart di sys_app_menu), dirender view layar generik v2.
 Status       : Aktif (relasi atasan diatur di Master Position).
--}}
@php
    use App\Support\V2\Data\OrgStructure;
    use App\Support\V2\Src;

    $nodes = OrgStructure::nodes();
@endphp

@if (! $nodes)
    <x-dl::alert tone="info" message="There are no active positions yet." />
@else
    @if (OrgStructure::isFlat($nodes))
        <x-dl::alert tone="info" message="All positions still stand alone (nobody has a superior set). Set the superior of each position in Master Position." />
    @endif
    <x-dl::org-chart :nodes="$nodes" :editable="false" title="Organization Structure" :departments="OrgStructure::departments()" :source="Src::of('tm2mst.org_position', 'tm2mst.org_department', 'tm2mst.emp_employee')" />
    <x-dl::action-bar><x-dl::button variant="secondary" icon="fa-sitemap" label="Master Position" :href="route('v2.screen', 'hrm-position')" /></x-dl::action-bar>
@endif
