{{-- REMARK
Tujuan: markup bingkai layar penuh: gambar latar, widget per posisi (brand, jam, kutipan, ...) dan kotak isi.
Sumber hulu: kelas Devlib\Components\Frame (prop bg, widgets, brand, tagline, logo; brand dari config devlib.brand).
Input: slot isi kotak + prop kelas Frame.
Output: div.dl-frame dengan widget x-dl::brand / clock / quote / announce dan kotak isi.
Dipakai oleh: halaman login dan lupa password devlib.test (demos/login-*, demos/forgot-*).
Status: aktif.
--}}
<div {{ $attributes->class(['dl-frame','dl-frame--box-'.$box, 'dl-frame--'.$boxStyle, 'dl-frame--overlay-'.$overlay]) }} {!! $dl() !!}
    data-dl-frame data-images="{{ json_encode($images()) }}" data-order="{{ $bgOrder }}" data-interval="{{ $bgInterval }}" data-key="{{ $bg }}"
    @if ($boxWidth) style="--dl-box-w:{{ $boxWidth }}" @endif>
    <div class="dl-frame__bg"><img alt="" data-layer="a"><img alt="" data-layer="b"></div>
    <div class="dl-frame__veil"></div>

    @foreach ($widgetMap() as $pos => $names)
        <div class="dl-frame__zone dl-frame__zone--{{ $pos }}">
            @foreach ($names as $w)
                @switch($w)
                    @case('brand') <x-dl::brand :name="$brand" :tagline="$tagline" :logo="$logo" /> @break
                    @case('clock') <x-dl::clock /> @break
                    @case('date') <x-dl::clock mode="date" /> @break
                    @case('greeting') <x-dl::greeting /> @break
                    @case('weather') <x-dl::weather :city="$city" :lat="$lat" :lon="$lon" /> @break
                    @case('quote') <x-dl::quote :quotes="\Devlib\Components\Frame::textLines($quotes)" :order="$quoteOrder" :interval="$quoteInterval" /> @break
                    @case('announce') <x-dl::announce :lines="\Devlib\Components\Frame::textLines($announce)" /> @break
                @endswitch
            @endforeach
        </div>
    @endforeach

    <div class="dl-frame__box dl-frame__box--{{ $color }}" data-dl-frame-box>
        @if ($resizable && in_array($box, ['left', 'right'], true))
            <div class="dl-frame__grip" title="{{ \Devlib\Lang::text('Drag to widen') }}" data-dl-frame-grip></div>
        @endif
        <div class="dl-frame__boxin">{{ $slot }}</div>
    </div>
</div>
