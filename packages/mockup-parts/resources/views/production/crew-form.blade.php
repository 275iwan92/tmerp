{{--
 REMARK
 Tujuan       : Tampilan form Assign Crew: periode di atas, lalu satu kotak per shift (dropdown Shift, nama crew besar dan tebal, daftar anggota dengan peran).
 Sumber hulu  : $crew dari App\Support\V2\Data\CrewForm::desk() (atau contoh statis di demo devlib.test).
 Input        : $crew (lihat MockupParts\Components\Production\CrewForm).
 Output       : x-dl::form panel=top kind=master; isian valid_from, valid_to, crews[i][shift_id], crews[i][members][j][employee_id|role].
 Dipakai oleh : MockupParts\Components\Production\CrewForm.
 Status       : Aktif (dummy). Tombol Undo / Save / Back + konfirmasi dari x-dl::form; tanpa Delete (kotak kosong = crew shift itu nonaktif).
--}}
<div {{ $attributes }} {!! $dl() !!}>
    @if (! empty($crew['flash']))<x-dl::alert tone="success" :message="raw($crew['flash'])" />@endif
    <x-dl::form id="crew-form" :title="raw($crew['title'])" panel="top" kind="master" :status="$crew['status']"
        :hide="['deactivate', 'activate', 'new', 'delete']" :action="$crew['action']" :back="$crew['back']" :hidden="$crew['hidden']">
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="valid_from" label="Period: from date" required :value="$crew['from']" :source="$crew['source'] ?? null" />
            <x-dl::field.date name="valid_to" label="Until date (empty = open)" :value="$crew['to']" :source="$crew['source'] ?? null" />
        </x-dl::form.row>
        <x-dl::form.row :cols="count($crew['boxes'])">
            @foreach ($crew['boxes'] as $i => $b)
                <x-dl::box>
                    <x-dl::field.select name="crews[{{ $i }}][shift_id]" label="Shift" required :options="$crew['shifts']" :value="$b['shift']" :source="$crew['source'] ?? null" data-dl-swap="crew-shift" data-dl-was="{{ $b['shift'] }}" />
                    <x-dl::field.display class="dl-display--plain dl-display--lead" label="Crew name" :value="raw($crew['nick'].' - '.$crew['shifts'][$b['shift']])" :source="$crew['source'] ?? null" data-dl-echo="crews[{{ $i }}][shift_id]" data-dl-echo-prefix="{{ $crew['nick'] }} -" />
                    <x-dl::member-list :name="'crews['.$i.'][members]'" title="Choose user names · maximum 10 rows" :rows="$b['rows']" :options="$crew['employees']" :roles="$crew['roles']" :max="$crew['max']" />
                </x-dl::box>
            @endforeach
        </x-dl::form.row>
        @if (! empty($crew['error']))<x-dl::alert tone="danger" :message="raw($crew['error'])" />@endif
    </x-dl::form>
</div>
