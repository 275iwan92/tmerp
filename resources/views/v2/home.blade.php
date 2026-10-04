@extends('v2.layout', ['title' => t('Home')])
@section('body')
    {{-- Situs ERP v2 = Layer Menu saja (user 2026-09-29); Menu Classic hanya di katalog DevLib. --}}
    <x-dl::layer-shell :classic="false" :brand="\App\Support\Branding\Brand::name()" :tagline="\App\Support\Branding\Brand::tagline()" :logo="\App\Support\Branding\Brand::logoUrl()" :modules="$modules" :user="$user" :logout-action="route('v2.logout')" :chat="$chat" :developer="$user['developer']" :open="$open" :fresh="$fresh" :drawers="['edit' => ['label' => 'Edit profile', 'subtitle' => raw($user['name']), 'icon' => 'fa-user-pen', 'url' => route('v2.profile'), 'source' => 'sec_user + emp_* (self-service)'], 'help' => ['label' => 'Help & support', 'subtitle' => 'F1 · guides, document flows and tickets to the developer team', 'icon' => 'fa-circle-question', 'url' => route('v2.help'), 'source' => 'sys_help_topic, sys_support_ticket']]" :help-url="route('v2.guide', 'aturan')" :profile-page="$profilePage" :assistant="$assistant" :account="$account" menu-source="sys_app_menu ({{ config('database.connections.tm2sys.database') }})">
        {{-- Help & support dan Edit profile = laci lebar (prop drawers), bukan halaman panel Profile (user 2026-10-03). --}}
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
