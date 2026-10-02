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

            @if ($drift)
                <x-dl::alert tone="danger" message="Reset is blocked: the database structure changed after this baseline was saved. A reset would put the old table definitions back while the migrations stay marked as run, so screens that use the new columns would fail. Save the current dummy data as the new baseline first." />
            @endif

            <x-dl::action-bar>
                <x-dl::action-form action="{{ route('v2.dummy.reset') }}">
                    <x-dl::button type="submit" variant="danger" icon="fa-rotate-left" label="Reset data dummy" :disabled="! $manifest || $drift"
                        data-dl-confirm="Semua perubahan pada data dummy sejak baseline akan hilang dan dikembalikan ke baseline. Lanjutkan?" />
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.dummy.save') }}">
                    <x-dl::button type="submit" variant="secondary" icon="fa-floppy-disk" label="Jadikan kondisi sekarang baseline"
                        data-dl-confirm="Baseline lama ditimpa dengan kondisi data dummy sekarang. Lanjutkan?" data-dl-confirm-tone="warn" />
                </x-dl::action-form>
            </x-dl::action-bar>
        </x-dl::card>

        @if ($manifest)
            <x-dl::card title="Structure changes since the baseline" icon="fa-code-compare"
                description="Tables and columns of the dummy databases now, compared with the baseline file. Reset only runs when this list is empty. A table created after the baseline is not in the baseline file: a reset never touches it, its rows stay.">
                <x-dl::table :columns="['db' => 'Database', 'table' => 'Table', 'change' => 'Difference', 'columns' => 'Columns', 'effect' => 'On Reset']"
                    :rows="$drift" empty="No structure change since the baseline. Reset is safe."
                    source="storage/app/ddt-baseline/*.sql vs information_schema.COLUMNS (ddt*)" />
            </x-dl::card>
        @endif
    </div>
@endsection
