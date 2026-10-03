@extends('v2.layout', ['title' => t('Home')])
@section('body')
    {{-- Situs ERP v2 = Layer Menu saja (user 2026-09-29); Menu Classic hanya di katalog DevLib. --}}
    <x-dl::layer-shell :classic="false" :brand="\App\Support\Branding\Brand::name()" :tagline="\App\Support\Branding\Brand::tagline()" :logo="\App\Support\Branding\Brand::logoUrl()" :modules="$modules" :user="$user" :logout-action="route('v2.logout')" :chat="$chat" :developer="$user['developer']" :open="$open" :fresh="$fresh" :profile-links="[['label' => t('Personal data, leave, documents, shifts'), 'url' => route('self-service.index'), 'icon' => 'fa-id-card', 'code' => 'SELF'], ['label' => t('Account security (recovery questions)'), 'url' => route('self-service.index', ['tab' => 'security']), 'icon' => 'fa-shield-halved', 'code' => 'SELF-SEC']]" :help-url="route('v2.guide', 'aturan')" :profile-page="$profilePage" :assistant="$assistant" :account="$account" menu-source="sys_app_menu ({{ config('database.connections.tm2sys.database') }})">
        <x-slot:profilePages>
            {{-- Bantuan & dukungan (F1 / menu akun). Tiap halaman punya Back yang kembali ke Profile. --}}
            <section data-lm-page="help" data-label="{{ t('Help & support') }}" data-icon="fa-circle-question" data-lm-children="ticket">
                <p>{!! t('Press <kbd>F1</kbd> on any screen to open this page.') !!}</p>
                <x-dl::action-bar>
                    {{-- Help per layar (sys_help_topic): untuk apa, cara pakai, arti tiap isian -- pintu utama bantuan (aturan E.1). --}}
                    <x-dl::button variant="secondary" block icon="fa-life-ring" label="Help Center" data-dl-tab-url="{{ route('v2.screen', 'help-center') }}" data-dl-tab-code="G-HELP" data-dl-tab-label="{{ t('Help Center') }}" data-dl-tab-icon="fa-solid fa-life-ring" />
                    <x-dl::button variant="secondary" block icon="fa-book" label="ERP Rules Guide" data-dl-tab-url="{{ route('v2.guide', 'aturan') }}" data-dl-tab-code="G-RULES" data-dl-tab-label="{{ t('ERP Rules Guide') }}" data-dl-tab-icon="fa-solid fa-book" />
                    <x-dl::button variant="secondary" block icon="fa-book-open" label="Guide: Master Product & Revision" data-dl-tab-url="{{ route('v2.guide', 'product-master') }}" data-dl-tab-code="G-PRODUCT" data-dl-tab-label="{{ t('Guide: Master Product & Revision') }}" data-dl-tab-icon="fa-solid fa-book-open" />
                    {{-- Alur dokumen: sementara di sini, bukan grup menu "Flow" (user 2026-10-02, akan dipikirkan ulang). --}}
                    <x-dl::button variant="secondary" block icon="fa-diagram-project" label="Sales Flow: Inquiry → CPDEL" data-dl-tab-url="{{ route('v2.flow', 'SALES') }}" data-dl-tab-code="F-SALES" data-dl-tab-label="{{ t('Sales Flow: Inquiry → CPDEL') }}" data-dl-tab-icon="fa-solid fa-diagram-project" />
                    <x-dl::button variant="secondary" block icon="fa-diagram-project" label="Costing Flow: ProDev → ProCost → Publish" data-dl-tab-url="{{ route('v2.flow', 'COSTING') }}" data-dl-tab-code="F-COSTING" data-dl-tab-label="{{ t('Costing Flow: ProDev → ProCost → Publish') }}" data-dl-tab-icon="fa-solid fa-diagram-project" />
                </x-dl::action-bar>
            </section>

            <section data-lm-page="ticket" data-label="{{ t('Support Ticket') }}" data-icon="fa-ticket">
                @if (session('v2_flash') && session('v2_profile_page') === 'ticket')<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
                @if ($errors->has('description'))<x-dl::alert tone="danger" :message="raw($errors->first('description'))" />@endif
                <x-dl::action-form action="{{ route('v2.support.ticket') }}">
                    <x-dl::field.text name="screen_context" label="Screen / menu" :value="old('screen_context')" placeholder="E.g. Customer Order" />
                    <x-dl::field.text name="description" label="Problem description" :value="old('description')" multiline required placeholder="What happened, and what did you expect?" />
                    <x-dl::action-bar><x-dl::button type="submit" icon="fa-paper-plane" :label="t('Send ticket')" /></x-dl::action-bar>
                </x-dl::action-form>
                <h4>@t('My tickets')</h4>
                @forelse ($tickets as $t)
                    <x-dl::card :title="raw($t->ticket_no.' · '.\Devlib\Locale::dt($t->created_at))" icon="fa-ticket">
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
        {{-- Opening screen: yang punya papan widget (top management) melihat salam + papan; yang lain layar pembuka lama (user 2026-10-02). --}}
        @if ($board)
            <x-slot:dashboard>
                @include('v2.partials.material-alert')
                <x-dl::dashboard :board="$board" source="App\Support\V2\Board\Board" />
            </x-slot:dashboard>
        @else
            @include('v2.partials.material-alert')
        @endif
        <p>{!! t('This is <b>Build & UAT</b>: the ERP screens are built from <b>DevLib</b> objects with <b>dummy</b> data') !!} ({{ config('database.connections.tm2mst.database') }} {{ t('etc.') }}).
            {!! t('Badges in the menu: <b>Review</b> = v2 screen waiting for review, <b>v1</b> = opens the old screen for now, <b>To Do</b> = no screen yet.') !!}
            {!! t('The document flow between screens is in <b>Help & support</b> (press <kbd>F1</kbd>).') !!}</p>
    </x-dl::layer-shell>
@endsection
