<span {{ $attributes->class('dl-docshare') }} {!! $dl() !!} data-dl-docshare data-target="{{ $target }}" data-type="{{ $type }}" data-id="{{ $docId }}"
    data-no="{{ $number }}" data-title="{{ $title }}" data-endpoint="{{ $endpoint }}" data-csrf="{{ csrf_token() }}" data-orientation="{{ $orientation }}"
    data-print-themes="{{ json_encode($printThemes()) }}" data-mail="{{ $uid }}-mail" data-online="{{ $uid }}-online">
    <span class="dl-docshare__menu">
        <x-dl::button preset="print" :size="$size" data-ds-toggle><i class="fa-solid fa-caret-down dl-docshare__caret"></i></x-dl::button>
        <span class="dl-docshare__list" role="menu">
            <button type="button" role="menuitem" data-ds-do="pdf"><i class="fa-solid fa-file-pdf"></i> PDF<small>unduh, dengan QR</small></button>
            <button type="button" role="menuitem" data-ds-do="jpeg"><i class="fa-solid fa-file-image"></i> JPEG<small>unduh gambar, dengan QR</small></button>
            @if ($endpoint)<button type="button" role="menuitem" data-ds-do="online"><i class="fa-solid fa-qrcode"></i> HTML online<small>link + QR serah terima</small></button>@endif
            <button type="button" role="menuitem" data-ds-do="printer"><i class="fa-solid fa-print"></i> Printer<small>dialog cetak browser</small></button>
        </span>
    </span>
    @if ($email && $endpoint)<x-dl::button preset="email" :size="$size" data-ds-do="email" />@endif

    {{-- Dipindah ke <body> oleh JS saat mulai: tag ini boleh berada di dalam <form> layar tanpa ikut terkirim. --}}
    @if ($endpoint)
        <x-dl::modal :id="$uid.'-online'" title="Dokumen online" icon="qr" size="sm">
            <div class="dl-docshare__online">
                <span class="dl-docshare__qr" data-ds-qr></span>
                <x-dl::field.text name="ds_url" label="Link (tanpa login)" readonly :case-toggle="false" data-ds-url />
                <x-dl::alert tone="info" message="Salinan dokumen saat ini. Pindai QR atau kirim link-nya untuk serah terima." />
            </div>
            <x-slot:footer>
                <x-dl::button variant="secondary" icon="copy" label="Salin link" data-ds-copy />
                <x-dl::button variant="secondary" icon="open" label="Buka" data-ds-open />
                <x-dl::button preset="close" data-dl-close-modal />
            </x-slot:footer>
        </x-dl::modal>
        @if ($email)
            <x-dl::modal :id="$uid.'-mail'" title="Email dokumen" icon="email" size="md">
                <div class="dl-docshare__mailform" data-ds-mailform>
                    <x-dl::field.text name="to" label="To" required :value="$emailTo" :case-toggle="false" placeholder="nama@perusahaan.com, ..." hint="Pisahkan beberapa alamat dengan koma." />
                    <x-dl::form.row cols="2">
                        <x-dl::field.text name="cc" label="CC" :case-toggle="false" />
                        <x-dl::field.text name="bcc" label="BCC" :case-toggle="false" />
                    </x-dl::form.row>
                    <x-dl::field.text name="subject" label="Subjek" required :value="$subject ?? trim($title.' '.$number)" />
                    <x-dl::field.text name="message" label="Pesan" multiline :value="$message" />
                    <x-dl::field.checkbox name="with_link" text="Link dokumen online (QR)" :value="true" />
                    <x-dl::field.checkbox name="with_image" text="Dokumen tampil di isi e-mail (gambar)" :value="true" />
                    <x-dl::field.checkbox name="with_pdf" text="Lampirkan PDF" />
                </div>
                <x-slot:footer>
                    <x-dl::button preset="cancel" data-dl-close-modal />
                    <x-dl::button icon="mail" label="Kirim" data-ds-send />
                </x-slot:footer>
            </x-dl::modal>
        @endif
    @endif
</span>
