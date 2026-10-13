{{--
 REMARK
 Tujuan       : Mengelola data dummy UAT: set per stage (Restore / Delete / Save) dan baseline plus reset, termasuk daftar drift struktur.
 Sumber hulu  : App\Console\Commands\DdtStage (set), DdtBaseline (manifest, drift) lewat route.
 Input        : manifest, drift, stages dari route; flash v2_flash / v2_error; khusus developer.
 Output       : x-dl::card, x-dl::stat-box, x-dl::action-form, x-dl::button (konfirmasi) yang mengirim ke route v2.dummy.reset, v2.dummy.save, v2.dummy.stage.do, v2.dummy.stage.save.
 Dipakai oleh : route v2.dummy.index (routes/v2.php, grup dummy).
 Status       : Aktif (hanya database ddt*).
--}}
@extends('v2.layout', ['title' => t('Dummy Data'), 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        @if (session('v2_flash'))
            <x-dl::alert tone="success" :message="raw(session('v2_flash'))" />
        @endif
        @if (session('v2_error'))
            <x-dl::alert tone="danger" :message="raw(session('v2_error'))" />
        @endif
        @if ($errors->any())
            <x-dl::alert tone="danger" :message="raw($errors->first())" />
        @endif

        {{-- Set per stage (user 2026-10-02): stage 1 = belum ada yang di-Confirm / diproses, stage 2 = maju satu langkah, dst.
             Tiap set = dump .sql data saja (App\Console\Commands\DdtStage); Restore mengganti isi data dummy dengan set itu. --}}
        <x-dl::card title="Dummy data sets by stage" icon="fa-layer-group"
            :description="raw(t('Each set is the dummy data frozen at one step of the workflow: in stage 1 nothing is confirmed or processed yet, each next stage is one step further.').' '.t('Restore replaces the business dummy data with that set, so every step can be tested again from a known starting point.').' '.t('Accounts, menus and security (ddtsys) are not touched.'))">
            @forelse ($stages as $s)
                <x-dl::card :title="raw($s['label'])" :description="raw($s['note'])" icon="fa-database">
                    <div class="dl-grid__stats">
                        <x-dl::stat-box label="Set" :value="raw($s['key'])" />
                        <x-dl::stat-box label="Saved" :value="\Devlib\Locale::dt($s['created_at'])" />
                        <x-dl::stat-box label="By" :value="raw($s['by'])" />
                        <x-dl::stat-box label="Made by" :value="($s['built'] ?? false) ? t('Stage builder') : t('Saved by hand')" />
                    </div>
                    @if ($s['blocked'])
                        <x-dl::alert tone="danger" message="This set can no longer be restored: it holds columns or tables that were removed from the database after it was saved. Build the stage sets again." />
                    @endif
                    <x-dl::action-bar>
                        <x-dl::action-form action="{{ route('v2.dummy.stage.do', [$s['key'], 'restore']) }}">
                            <x-dl::button type="submit" variant="primary" icon="fa-rotate-left" label="Restore" :disabled="$s['blocked']"
                                data-dl-confirm="{{ t('The business dummy data is replaced by this set. Everything entered since the last save is lost. Continue?') }}" />
                        </x-dl::action-form>
                        <x-dl::action-form action="{{ route('v2.dummy.stage.do', [$s['key'], 'delete']) }}">
                            <x-dl::button type="submit" variant="secondary" icon="fa-trash" label="Delete"
                                data-dl-confirm="{{ t('This set is deleted from the server. The dummy data in use is not changed. Continue?') }}" data-dl-confirm-tone="warn" />
                        </x-dl::action-form>
                    </x-dl::action-bar>
                </x-dl::card>
            @empty
                <x-dl::alert tone="warn" message="No set yet. Build the stage sets, or save the current dummy data as a set below." />
            @endforelse

            {{-- Build memakan ~6 menit (lebih lama dari batas waktu satu permintaan web) -> lewat perintah, bukan tombol. --}}
            <x-dl::alert tone="info" :message="raw(t('The stage sets are built again, after master data changed, with the command:').' php artisan ddt:stage build --env=mockup')" />

            <x-dl::card title="Save the current dummy data as a set" icon="fa-floppy-disk"
                description="Freezes the dummy data as it is now under a name of your own. Saving under an existing name replaces that set.">
                <x-dl::action-form action="{{ route('v2.dummy.stage.save') }}">
                    <x-dl::field.text name="key" label="Set name" :case-toggle="false" required :value="old('key')" placeholder="my-test-1" hint="Lowercase letters, digits and minus." />
                    <x-dl::field.text name="label" label="Title" :value="old('label')" placeholder="CO waiting for approval" />
                    <x-dl::field.text name="note" label="What it holds" :value="old('note')" />
                    <x-dl::action-bar>
                        <x-dl::button type="submit" variant="secondary" icon="fa-floppy-disk" label="Save" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            </x-dl::card>
        </x-dl::card>

        <x-dl::card title="Dummy Data (UAT) — baseline & reset" icon="fa-rotate-left"
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
                <x-dl::alert tone="danger" :message="raw(t('Reset is blocked: the database structure changed after this baseline was saved.').' '.t('A reset would put the old table definitions back while the migrations stay marked as run, so screens that use the new columns would fail.').' '.t('Save the current dummy data as the new baseline first.'))" />
            @endif

            <x-dl::action-bar>
                <x-dl::action-form action="{{ route('v2.dummy.reset') }}">
                    <x-dl::button type="submit" variant="danger" icon="fa-rotate-left" label="Reset dummy data" :disabled="! $manifest || $drift"
                        data-dl-confirm="{{ t('Every change to the dummy data since the baseline will be lost and restored to the baseline. Continue?') }}" />
                </x-dl::action-form>
                <x-dl::action-form action="{{ route('v2.dummy.save') }}">
                    <x-dl::button type="submit" variant="secondary" icon="fa-floppy-disk" label="Save current state as baseline"
                        data-dl-confirm="{{ t('The old baseline is overwritten with the current dummy data. Continue?') }}" data-dl-confirm-tone="warn" />
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
