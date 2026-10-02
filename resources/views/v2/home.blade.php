@extends('v2.layout', ['title' => 'Home'])
@section('body')
    {{-- Situs ERP v2 = Layer Menu saja (user 2026-09-29); Menu Classic hanya di katalog DevLib. --}}
    <x-dl::layer-shell :classic="false" :brand="\App\Support\Branding\Brand::name()" :tagline="\App\Support\Branding\Brand::tagline()" :logo="\App\Support\Branding\Brand::logoUrl()" :modules="$modules" :user="$user" :logout-action="route('v2.logout')" :chat="$chat" :developer="$user['developer']" :open="$open" :help-url="route('v2.guide', 'aturan')" :profile-page="$profilePage" :assistant="$assistant" menu-source="sys_app_menu ({{ config('database.connections.tm2sys.database') }})">
        <x-slot:profilePages>
            {{-- Bantuan & dukungan (F1 / menu akun). Tiap halaman punya Back yang kembali ke Profile. --}}
            <section data-lm-page="help" data-label="Bantuan & dukungan" data-icon="fa-circle-question">
                <p>Tekan <kbd>F1</kbd> di layar mana pun untuk membuka halaman ini.</p>
                <x-dl::action-bar>
                    {{-- Help per layar (sys_help_topic): untuk apa, cara pakai, arti tiap isian -- pintu utama bantuan (aturan E.1). --}}
                    <x-dl::button variant="secondary" block icon="fa-life-ring" label="Help Center" data-dl-tab-url="{{ route('v2.screen', 'help-center') }}" data-dl-tab-code="G-HELP" data-dl-tab-label="Help Center" data-dl-tab-icon="fa-solid fa-life-ring" />
                    <x-dl::button variant="secondary" block icon="fa-book" label="Pedoman Aturan ERP" data-dl-tab-url="{{ route('v2.guide', 'aturan') }}" data-dl-tab-code="G-RULES" data-dl-tab-label="Pedoman Aturan ERP" data-dl-tab-icon="fa-solid fa-book" />
                    <x-dl::button variant="secondary" block icon="fa-book-open" label="Guide: Master Product & Revision" data-dl-tab-url="{{ route('v2.guide', 'product-master') }}" data-dl-tab-code="G-PRODUCT" data-dl-tab-label="Guide: Master Product & Revision" data-dl-tab-icon="fa-solid fa-book-open" />
                    {{-- Alur dokumen: sementara di sini, bukan grup menu "Flow" (user 2026-10-02, akan dipikirkan ulang). --}}
                    <x-dl::button variant="secondary" block icon="fa-diagram-project" label="Sales Flow: Inquiry → CPDEL" data-dl-tab-url="{{ route('v2.flow', 'SALES') }}" data-dl-tab-code="F-SALES" data-dl-tab-label="Sales Flow: Inquiry → CPDEL" data-dl-tab-icon="fa-solid fa-diagram-project" />
                    <x-dl::button variant="secondary" block icon="fa-diagram-project" label="Costing Flow: ProDev → ProCost → Publish" data-dl-tab-url="{{ route('v2.flow', 'COSTING') }}" data-dl-tab-code="F-COSTING" data-dl-tab-label="Costing Flow: ProDev → ProCost → Publish" data-dl-tab-icon="fa-solid fa-diagram-project" />
                    <x-dl::button variant="primary" block icon="fa-ticket" label="Tiket Bantuan" data-lm-page-open="ticket" />
                </x-dl::action-bar>
            </section>

            <section data-lm-page="ticket" data-label="Tiket Bantuan" data-icon="fa-ticket">
                @if (session('v2_flash') && session('v2_profile_page') === 'ticket')<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
                @if ($errors->has('description'))<x-dl::alert tone="danger" :message="$errors->first('description')" />@endif
                <form method="POST" action="{{ route('v2.support.ticket') }}">
                    @csrf
                    <x-dl::field.text name="screen_context" label="Screen / menu" :value="old('screen_context')" placeholder="E.g. Customer Order" />
                    <x-dl::field.text name="description" label="Problem description" :value="old('description')" multiline required placeholder="What happened, and what did you expect?" />
                    <x-dl::action-bar><x-dl::button type="submit" icon="fa-paper-plane" :label="t('Send ticket')" /></x-dl::action-bar>
                </form>
                <h4>Tiket saya</h4>
                @forelse ($tickets as $t)
                    <x-dl::card :title="$t->ticket_no.' · '.\Devlib\Locale::dt($t->created_at)" icon="fa-ticket">
                        <div><x-dl::badge :label="['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'CLOSED' => 'Closed'][$t->status] ?? $t->status" :tone="['OPEN' => 'warn', 'IN_PROGRESS' => 'info', 'DONE' => 'success', 'CLOSED' => 'neutral'][$t->status] ?? 'neutral'" /></div>
                        @if ($t->screen_context)<div>{{ $t->screen_context }}</div>@endif
                        <p>{{ $t->description }}</p>
                        @if ($r = $t->replies->last())<p><i class="fa-solid fa-reply"></i> <b>{{ $r->author }}</b>: {{ $r->body }}</p>@endif
                        @if ($t->resolution_note)<p><i class="fa-solid fa-circle-check"></i> {{ $t->resolution_note }}</p>@endif
                    </x-dl::card>
                @empty
                    <x-dl::empty-state title="No tickets yet" message="Tickets you send appear here together with the support team replies." icon="fa-ticket" />
                @endforelse
            </section>
        </x-slot:profilePages>
        @include('v2.partials.material-alert')
        <p>Ini <b>Build &amp; UAT</b>: layar ERP disusun dari objek <b>DevLib</b> dengan data <b>dummy</b> ({{ config('database.connections.tm2mst.database') }} dst). Lencana di menu:
            <b>Review</b> = layar v2 menunggu penilaian, <b>v1</b> = sementara membuka layar lama, <b>Belum</b> = belum ada layar.
            Alur dokumen antar layar ada di <b>Bantuan &amp; dukungan</b> (tekan <kbd>F1</kbd>).</p>
    </x-dl::layer-shell>
@endsection
