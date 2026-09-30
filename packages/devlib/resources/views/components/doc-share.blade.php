<span {{ $attributes->class('dl-docshare') }} {!! $dl() !!} data-dl-docshare data-target="{{ $target }}" data-type="{{ $type }}" data-id="{{ $docId }}"
    data-no="{{ $number }}" data-title="{{ $title }}" data-endpoint="{{ $endpoint }}" data-csrf="{{ csrf_token() }}" data-orientation="{{ $orientation }}"
    data-print-themes="{{ json_encode($printThemes()) }}" data-mail="{{ $uid }}-mail" data-online="{{ $uid }}-online">
    <span class="dl-docshare__menu">
        <x-dl::button preset="print" :size="$size" data-ds-toggle><i class="fa-solid fa-caret-down dl-docshare__caret"></i></x-dl::button>
        <span class="dl-docshare__list" role="menu">
            @if (count($langs()) > 1)<span class="dl-docshare__lang" role="group" data-ds-langs><span>{{ \Devlib\Lang::t('print_lang') }}</span>@foreach ($langs() as $code => $name)<button type="button" data-ds-lang="{{ $code }}" title="{{ $name }}">{{ strtoupper($code) }}</button>@endforeach</span>@endif
            <button type="button" role="menuitem" data-ds-do="pdf"><i class="fa-solid fa-file-pdf"></i> PDF<small>{{ \Devlib\Lang::t('ds_pdf_sub') }}</small></button>
            <button type="button" role="menuitem" data-ds-do="jpeg"><i class="fa-solid fa-file-image"></i> JPEG<small>{{ \Devlib\Lang::t('ds_jpeg_sub') }}</small></button>
            @if ($endpoint)<button type="button" role="menuitem" data-ds-do="online"><i class="fa-solid fa-qrcode"></i> HTML online<small>{{ \Devlib\Lang::t('ds_online_sub') }}</small></button>@endif
            <button type="button" role="menuitem" data-ds-do="printer"><i class="fa-solid fa-print"></i> Printer<small>{{ \Devlib\Lang::t('ds_printer_sub') }}</small></button>
        </span>
    </span>
    @if ($email && $endpoint)<x-dl::button preset="email" :size="$size" data-ds-do="email" />@endif

    {{-- Dipindah ke <body> oleh JS saat mulai: tag ini boleh berada di dalam <form> layar tanpa ikut terkirim. --}}
    @if ($endpoint)
        <x-dl::modal :id="$uid.'-online'" :title="\Devlib\Lang::t('ds_online')" icon="qr" size="sm">
            <div class="dl-docshare__online">
                <span class="dl-docshare__qr" data-ds-qr></span>
                <x-dl::field.text name="ds_url" :label="\Devlib\Lang::t('ds_link')" readonly :case-toggle="false" data-ds-url />
                <x-dl::alert tone="info" :message="\Devlib\Lang::t('ds_online_msg')" />
            </div>
            <x-slot:footer>
                <x-dl::button variant="secondary" icon="copy" :label="\Devlib\Lang::t('ds_copy')" data-ds-copy />
                <x-dl::button variant="secondary" icon="open" :label="\Devlib\Lang::t('ds_open')" data-ds-open />
                <x-dl::button preset="close" data-dl-close-modal />
            </x-slot:footer>
        </x-dl::modal>
        @if ($email)
            <x-dl::modal :id="$uid.'-mail'" :title="\Devlib\Lang::t('ds_email')" icon="email" size="md">
                <div class="dl-docshare__mailform" data-ds-mailform>
                    @if (count($langs()) > 1)<span class="dl-docshare__lang" role="group" data-ds-langs><span>{{ \Devlib\Lang::t('print_lang') }}</span>@foreach ($langs() as $code => $name)<button type="button" data-ds-lang="{{ $code }}" title="{{ $name }}">{{ strtoupper($code) }}</button>@endforeach</span>@endif
                    <x-dl::field.text name="to" label="To" required :value="$emailTo" :case-toggle="false" placeholder="name@company.com, ..." :hint="\Devlib\Lang::t('ds_to_hint')" />
                    <x-dl::form.row cols="2">
                        <x-dl::field.text name="cc" label="CC" :case-toggle="false" />
                        <x-dl::field.text name="bcc" label="BCC" :case-toggle="false" />
                    </x-dl::form.row>
                    <x-dl::field.text name="subject" :label="\Devlib\Lang::t('ds_subject')" required :value="$subject ?? trim($title.' '.$number)" />
                    <x-dl::field.text name="message" :label="\Devlib\Lang::t('ds_message')" multiline :value="$message" />
                    <x-dl::field.checkbox name="with_link" :text="\Devlib\Lang::t('ds_with_link')" :value="true" />
                    <x-dl::field.checkbox name="with_image" :text="\Devlib\Lang::t('ds_with_image')" :value="true" />
                    <x-dl::field.checkbox name="with_pdf" :text="\Devlib\Lang::t('ds_with_pdf')" />
                </div>
                <x-slot:footer>
                    <x-dl::button preset="cancel" data-dl-close-modal />
                    <x-dl::button icon="mail" :label="\Devlib\Lang::t('ds_send')" data-ds-send />
                </x-slot:footer>
            </x-dl::modal>
        @endif
    @endif
</span>
