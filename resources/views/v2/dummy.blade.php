@extends('v2.layout', ['title' => 'Data Dummy', 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        @if (session('v2_flash'))
            <x-dl::alert tone="success" :message="session('v2_flash')" />
        @endif
        @if (session('v2_error'))
            <x-dl::alert tone="danger" :message="session('v2_error')" />
        @endif

        <x-dl::card title="Data Dummy (UAT) — baseline & reset" icon="fa-rotate-left"
            description="Baseline = a copy of the dummy data considered correct. Reset restores every business dummy database exactly to the baseline. Accounts, menus and security (ddtsys) are not reset.">
            @if ($manifest)
                <div class="dl-grid__stats">
                    <x-dl::stat-box label="Baseline created" :value="$manifest['created_at']" />
                    <x-dl::stat-box label="By" :value="$manifest['by']" />
                    <x-dl::stat-box label="Database" :value="count($manifest['databases'])" />
                    <x-dl::stat-box label="File upload" :value="$manifest['files']" />
                </div>
                <p class="dl-note">{{ implode(' · ', $manifest['databases']) }}</p>
            @else
                <x-dl::alert tone="warn" message="No baseline yet. Save the current dummy data as the baseline first." />
            @endif

            <x-dl::action-bar>
                <x-dl::action-form action="{{ route('v2.dummy.reset') }}">
                    <x-dl::button type="submit" variant="danger" icon="fa-rotate-left" label="Reset data dummy" :disabled="! $manifest"
                        data-dl-confirm="Semua perubahan pada data dummy sejak baseline akan hilang dan dikembalikan ke baseline. Lanjutkan?" />
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.dummy.save') }}">
                    <x-dl::button type="submit" variant="secondary" icon="fa-floppy-disk" label="Jadikan kondisi sekarang baseline"
                        data-dl-confirm="Baseline lama ditimpa dengan kondisi data dummy sekarang. Lanjutkan?" data-dl-confirm-tone="warn" />
                </x-dl::action-form>
            </x-dl::action-bar>
        </x-dl::card>
    </div>
@endsection
