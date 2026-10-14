{{--
 REMARK
 Tujuan       : Status tiap PDF Master Card (siap / sudah ada / kurang apa), jumlah set dummy di tiap level (Master Product, Inquiry, ProDev, ProCost, CO), dan tiga tombol engine.
 Sumber hulu  : App\Services\MasterCard\MasterCardDummyEngine (counts, plan, run, reset) lewat routes/v2_dummy_mastercard.php.
 Input        : plan (design, name, cards, status), counts (product, inquiry, prodev, procost, co) dari route; flash v2_flash / v2_error; khusus developer.
 Output       : x-dl::table status PDF, x-dl::stat-box per level, tiga x-dl::button: Make Master Product, Make dummy data (POST v2.dummy.mastercard.run, mode product / dummy), Reset (POST v2.dummy.mastercard.reset). Hasil = data di ddt*.
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

        <x-dl::card title="Master Card PDF" icon="fa-file-pdf"
            description="PDF in docs/attachments/mastercard (card picture in the extracted folder next to it). The status is computed when this screen opens; nothing is written.">
            <x-dl::table :columns="['design' => 'Design', 'name' => 'Product', 'cards' => 'Cards', 'status' => 'Status']"
                :rows="array_map(fn ($p) => ['design' => raw($p['design'] ?? '-'), 'name' => raw($p['name']), 'cards' => $p['cards'], 'status' => raw($p['status'] === 'ready' ? t('Ready to build') : t($p['status']))], $plan)"
                empty="No Master Card PDF found." source="docs/attachments/mastercard/*.pdf" />
        </x-dl::card>
        <x-dl::card title="Dummy sets now" icon="fa-layer-group">
            <x-dl::kpi-row>
                <x-dl::stat-box label="Master Product" :value="$counts['product']" />
                <x-dl::stat-box label="Inquiry" :value="$counts['inquiry']" />
                <x-dl::stat-box label="ProDev" :value="$counts['prodev']" />
                <x-dl::stat-box label="ProCost" :value="$counts['procost']" />
                <x-dl::stat-box label="CO" :value="$counts['co']" />
            </x-dl::kpi-row>
            <x-dl::action-bar>
                <x-dl::action-form action="{{ route('v2.dummy.mastercard.run', 'product') }}">
                    <x-dl::button type="submit" variant="secondary" icon="fa-box" label="Make Master Product"
                        data-dl-confirm="{{ t('Every PDF that is ready becomes a Master Product (part, dieline, artwork, BOP from the card). Continue?') }}" />
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.dummy.mastercard.run', 'dummy') }}">
                    <x-dl::button type="submit" variant="primary" icon="fa-play" label="Make dummy data"
                        data-dl-confirm="{{ t('Every PDF that is ready is built into dummy data, spread over Inquiry, ProDev, ProCost and CO in turn. It can take about 20 seconds per product. Continue?') }}" />
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.dummy.mastercard.reset') }}">
                    <x-dl::button type="submit" variant="danger" icon="fa-rotate-left" label="Reset"
                        data-dl-confirm="{{ t('Removes every set made by this engine (the cards read from PDF and their Inquiry, product, costing and Customer Order). Other dummy data is not touched. Continue?') }}" />
                </x-dl::action-form>
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::box>
@endsection
