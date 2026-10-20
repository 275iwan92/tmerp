{{--
 REMARK
 Tujuan       : Halaman cetak Roster Shift (roster mingguan, master definisi shift, formasi mingguan) di lembar A4 berbagian; menggantikan halaman v1 pages::hrm.shift-roster-print.
 Sumber hulu  : App\Support\V2\Data\RosterPrint (props x-dl::print-sheet); setelan sembunyi shift milik user pencetak.
 Input        : $data (RosterPrint), $back; query mode, schedule, week.
 Output       : x-dl::action-bar (Back, pilihan mode, x-dl::doc-share) dan x-dl::print-sheet.
 Dipakai oleh : Route v2.hrm.roster.print (routes/v2_hrm.php); tombol Print di layar hrm-roster.
 Status       : Aktif (T9 pindahan v1, 2026-10-20).
--}}
@extends('v2.layout', ['title' => $data->fileTitle(), 'bodyClass' => 'dl-page-body'])
@section('body')
    @php
        $self = fn (string $m) => route('v2.hrm.roster.print', ['mode' => $m] + request()->only('schedule', 'week'));
        $sheet = $data->sheet();
    @endphp
    <x-dl::box class="dl-cat">
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="$back" />
            @foreach (['roster' => 'Roster', 'shift' => 'Shift Definitions', 'formasi' => 'Formation'] as $mode => $label)
                <x-dl::button :variant="$data->mode === $mode ? 'primary' : 'secondary'" :label="$label" :href="$self($mode)" />
            @endforeach
            <x-dl::doc-share target="#print-sheet" type="print-roster" :doc-id="0" :number="$data->code()" :title="$data->fileTitle()" orientation="portrait" />
        </x-dl::action-bar>
        <x-dl::print-sheet id="print-sheet" :title="$sheet['title']" :code="$sheet['code']" :info="$sheet['info']" :sections="$sheet['sections']" :signatures="$sheet['signatures']" :footer="$sheet['footer']" />
    </x-dl::box>
@endsection
