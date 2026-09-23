<div class="dl-demo" style="max-width:1100px">
    <div class="dl-errors-grid">
        @foreach (array_keys(\Devlib\Components\ErrorPage::CODES) as $code)
            <x-dl::error-page :code="$code" :full="false" home="#" />
        @endforeach
    </div>
    <x-dl::alert tone="info" message="Halaman error situs DevLib yang sungguhan (mis. alamat yang tidak ada) memakai komponen yang sama: coba buka /halaman-tidak-ada." />
</div>
