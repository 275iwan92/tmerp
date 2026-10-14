{{--
 REMARK
 Tujuan       : Menampilkan jumlah set dummy di tiap level (Inquiry, ProDev, ProCost, CO) dan menjalankan engine PDF Master Card -> set dummy baru.
 Sumber hulu  : App\Services\MasterCard\MasterCardDummyEngine (counts, plan, run) lewat route v2.dummy.mastercard (routes/v2_dummy_mastercard.php).
 Input        : counts (inquiry, prodev, procost, co), ready / total PDF, report (design, name, level, note); flash v2_flash / v2_error; khusus developer.
 Output       : x-dl::stat-box jumlah set per level, x-dl::action-form jumlah set baru per level + qty + due (POST v2.dummy.mastercard.run), tabel hasil.
 Dipakai oleh : Menu Developer > Database > Master Card Dummy (PDF).
 Status       : Aktif (hanya database ddt*).
--}}
@extends('v2.layout', ['title' => t('Master Card Dummy (PDF)'), 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        @if (session('v2_flash'))
            <x-dl::alert tone="success" :message="raw(session('v2_flash'))" />
        @endif
        @if (session('v2_error'))
            <x-dl::alert tone="danger" :message="raw(session('v2_error'))" />
        @endif
        @if ($errors->any())
            <x-dl::alert tone="danger" :message="raw($errors->first())" />
        @endif

        <x-dl::card title="Dummy sets now" icon="fa-layer-group">
            <x-dl::kpi-row>
                <x-dl::stat-box label="Inquiry" :value="$counts['inquiry']" />
                <x-dl::stat-box label="ProDev" :value="$counts['prodev']" />
                <x-dl::stat-box label="ProCost" :value="$counts['procost']" />
                <x-dl::stat-box label="CO" :value="$counts['co']" />
                <x-dl::stat-box label="PDF ready to build" :value="raw($ready.' / '.$total)" />
            </x-dl::kpi-row>
        </x-dl::card>

        <x-dl::card title="Build new sets" icon="fa-file-pdf">
            <x-dl::action-form action="{{ route('v2.dummy.mastercard.run') }}">
                <x-dl::cols>
                    <x-dl::field.number name="inquiry" label="Inquiry" :value="old('inquiry', 0)" min="0" />
                    <x-dl::field.number name="prodev" label="ProDev" :value="old('prodev', 0)" min="0" />
                    <x-dl::field.number name="procost" label="ProCost" :value="old('procost', 0)" min="0" />
                    <x-dl::field.number name="co" label="CO" :value="old('co', 0)" min="0" />
                </x-dl::cols>
                <x-dl::cols>
                    <x-dl::field.number name="qty" label="Order qty" :value="old('qty', 5000)" min="1" />
                    <x-dl::field.number name="due" label="Due (days)" :value="old('due', 21)" min="1" />
                </x-dl::cols>
                <x-dl::action-bar>
                    <x-dl::button type="submit" variant="primary" icon="fa-play" label="Run engine"
                        data-dl-confirm="{{ t('The engine writes dummy data to the ddt* databases and can take about 20 seconds per product. Continue?') }}" />
                </x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::card>

        @if ($report)
            <x-dl::card title="Last result" icon="fa-list-check">
                <x-dl::table :columns="['design' => 'Design', 'name' => 'Product', 'level' => 'Level', 'note' => 'Note']"
                    :rows="array_map(fn ($r) => ['design' => raw($r['design'] ?? '-'), 'name' => raw($r['name']), 'level' => raw($r['level'] ?? '-'), 'note' => raw($r['note'])], $report)"
                    empty="Nothing was built." source="MasterCardDummyEngine::run" />
            </x-dl::card>
        @endif
    </x-dl::box>
@endsection
