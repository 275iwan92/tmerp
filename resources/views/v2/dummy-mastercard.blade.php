{{--
 REMARK
 Tujuan       : Info jumlah set dummy di tiap level (Inquiry, ProDev, ProCost, CO) dan jumlah PDF Master Card yang siap dibuat. Tahap ini hanya info.
 Sumber hulu  : App\Services\MasterCard\MasterCardDummyEngine (counts, plan) lewat route v2.dummy.mastercard (routes/v2_dummy_mastercard.php).
 Input        : counts (inquiry, prodev, procost, co), ready / total PDF dari route; khusus developer.
 Output       : x-dl::stat-box per level dan cara menjalankan engine (perintah). Tidak menulis apa pun.
 Dipakai oleh : Menu Developer > Database > Master Card Dummy (PDF).
 Status       : Aktif (hanya database ddt*).
--}}
@extends('v2.layout', ['title' => t('Master Card Dummy (PDF)'), 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        <x-dl::card title="Dummy sets now" icon="fa-layer-group">
            <x-dl::kpi-row>
                <x-dl::stat-box label="Inquiry" :value="$counts['inquiry']" />
                <x-dl::stat-box label="ProDev" :value="$counts['prodev']" />
                <x-dl::stat-box label="ProCost" :value="$counts['procost']" />
                <x-dl::stat-box label="CO" :value="$counts['co']" />
                <x-dl::stat-box label="PDF ready to build" :value="raw($ready.' / '.$total)" />
            </x-dl::kpi-row>
            <x-dl::alert tone="info" :message="raw(t('Sets are built from new Master Card PDFs with the command:').' php artisan dummy:mastercard --env=mockup --levels=inquiry:2,prodev:2,procost:2,co:2')" />
        </x-dl::card>
    </x-dl::box>
@endsection
