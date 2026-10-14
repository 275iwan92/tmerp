{{--
 REMARK
 Tujuan       : Info ringkas engine PDF Master Card -> data dummy berlevel: input apa yang dibaca, output apa yang dihasilkan, jumlah set per level, dan tombol Run.
 Sumber hulu  : App\Services\MasterCard\MasterCardDummyEngine (counts, plan, run) lewat route v2.dummy.mastercard (routes/v2_dummy_mastercard.php).
 Input        : counts (inquiry, prodev, procost, co), ready / total PDF dari route; flash v2_flash / v2_error; khusus developer.
 Output       : x-dl::stat-box jumlah PDF dan set per level, x-dl::button Run (POST v2.dummy.mastercard.run). Hasil run = data di ddt*, bukan tampilan.
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

        <x-dl::card title="Input" icon="fa-file-pdf"
            description="Master Card PDF in docs/attachments/mastercard, with the card picture in the extracted folder next to it. The engine reads the card text (size P x L x T, knife, material, colours, finishing, process, dates) and the master data (customer, material, process, machine).">
            <x-dl::kpi-row>
                <x-dl::stat-box label="PDF in folder" :value="$total" />
                <x-dl::stat-box label="Ready to build" :value="$ready" />
            </x-dl::kpi-row>
        </x-dl::card>

        <x-dl::card title="Output" icon="fa-layer-group"
            description="Dummy data in this site (mockup.test): Inquiry with dieline, artwork and sizes; ProDev layout, BOP and BOM; ProCost costing; Quotation and Customer Order. A set at a higher level carries every level behind it.">
            <x-dl::kpi-row>
                <x-dl::stat-box label="Inquiry" :value="$counts['inquiry']" />
                <x-dl::stat-box label="ProDev" :value="$counts['prodev']" />
                <x-dl::stat-box label="ProCost" :value="$counts['procost']" />
                <x-dl::stat-box label="CO" :value="$counts['co']" />
            </x-dl::kpi-row>
            <x-dl::action-form action="{{ route('v2.dummy.mastercard.run') }}">
                <x-dl::action-bar>
                    <x-dl::button type="submit" variant="primary" icon="fa-play" label="Run engine"
                        data-dl-confirm="{{ t('Every PDF that is ready is built into dummy data, spread over Inquiry, ProDev, ProCost and CO in turn. It can take about 20 seconds per product. Continue?') }}" />
                </x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::card>
    </x-dl::box>
@endsection
