{{-- Navigasi layar standar (user 2026-09-30): setiap layar input = GRID dulu -> klik 2x = FORM (?id=).
     1) Klik menu layar ini lagi di Shell -> selalu kembali ke GRID. Form yang sudah diubah -> Simpan / Buang / Batal
        (Batal = tetap di form). Objeknya: Shell::toGrid + dirty-guard (form ber-data-dl-guard).
     2) Breadcrumb bisa diklik: Modul / Grup membuka Layer Menu di cabang itu, nama layar kembali ke grid. --}}
@php
    $grid = url()->current();
    $id = request('id');
    $rows = [
        ['id' => 1, 'name' => 'PT Contoh Kertas Jaya', 'code' => 'SUP-001', 'city' => 'Surabaya', 'status' => 'Draft'],
        ['id' => 2, 'name' => 'CV Tinta Warna', 'code' => 'SUP-002', 'city' => 'Sidoarjo', 'status' => 'Confirmed'],
        ['id' => 3, 'name' => 'PT Lem Kuat', 'code' => 'SUP-003', 'city' => 'Gresik', 'status' => 'Draft'],
    ];
    $row = collect($rows)->firstWhere('id', (int) $id);
@endphp
<div class="dl-demo">
    <x-dl::breadcrumb menu :items="['Menus, Accounts & Layout' => null, 'Screen Navigation (Grid ↔ Form)' => $grid]" :current="$row ? 'Form '.$row['code'] : null" />
    <p class="dl-note">Buka layar ini dari Shell (menu kiri). Klik 2x baris → form. Ubah isian, lalu klik <b>menu layar ini lagi</b>: muncul <b>Save / Undo / Cancel</b>. Breadcrumb: klik nama modul/grup untuk membuka menu di cabang itu, klik nama layar untuk kembali ke grid.</p>

    @if (! $row)
        <x-dl::grid id="screen-nav" :rows="$rows" actions="worklist" :active-key="null" :new-record="null" :open-url="$grid.'?id={id}'"
            :columns="[
                ['key' => 'name', 'label' => 'Supplier', 'type' => 'identity', 'code' => 'code'],
                ['key' => 'city', 'label' => 'Kota'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Draft' => 'neutral', 'Confirmed' => 'success']],
            ]" />
    @else
        @if (request('saved'))<x-dl::alert tone="success" message="Tersimpan (demo, tanpa database)." />@endif
        <form method="get" action="{{ $grid }}" data-dl-guard>
            <input type="hidden" name="id" value="{{ $row['id'] }}">
            <input type="hidden" name="saved" value="1">
            <x-dl::card :title="$row['name']">
                <x-dl::form.row cols="2">
                    <x-dl::field.text name="name" label="Nama Supplier" :value="$row['name']" />
                    <x-dl::field.text name="city" label="Kota" :value="$row['city']" />
                </x-dl::form.row>
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$grid" />
                    <x-dl::button preset="save" />
                </x-dl::action-bar>
            </x-dl::card>
        </form>
    @endif
</div>
