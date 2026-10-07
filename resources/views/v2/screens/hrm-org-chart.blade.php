{{-- HRM > Organization > Organization Chart (v2). Data = App\Support\V2\Data\OrgStructure (org_position.reports_to_position_id + pemegang jabatan aktif), tampil
     read-only lewat x-dl::org-chart: cari nama / jabatan, sorot departemen, lipat / buka cabang, zoom, unduh HTML. Relasi atasan diatur di Master Position. --}}
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
