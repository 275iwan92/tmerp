{{--
 REMARK
 Tujuan       : Entry OUTPUT (Production Output): layar entry hasil produksi di mesin. Dari menu langsung FORM untuk crew yang login (by login),
                dengan LIST OF JOBS dari antrean CONFIRMED PPIC Planning pada mesin + proses crew itu. Administrator tanpa crew memilih "Masuk sebagai".
 Sumber hulu  : App\Support\V2\Data\ProductionOutput::desk() (login -> employee -> prd_crew_assignment / crew BOP -> operasi; Ppic::operations; Master Card).
 Input        : ?op= (job aktif); ?machine= & ?process= (pilihan "Masuk sebagai" administrator, atau di antara pasangan crew sendiri).
 Output       : Objek x-mp::production.output-desk, atau kartu pilihan proses + mesin (administrator tanpa crew);
                Play/Pause/Stop dan SIMPAN HASIL menulis lewat OperatorTimerService (+ prd_output_action_log).
 Dipakai oleh : Menu Manufacture > Entry OUTPUT > Production Output, rute v2.screen production-operator.
 Status       : Aktif di mockup (ddt*).
--}}
@php
    $desk = App\Support\V2\Data\ProductionOutput::desk(auth()->user(), ['op' => request()->integer('op') ?: null, 'machine' => request()->integer('machine') ?: null, 'process' => request('process')]);
@endphp
@if (isset($desk['picker']))
    <x-dl::card title="Enter as" description="Choose the process and machine to enter output for. A crew member goes straight to the form of their own crew.">
        <x-dl::action-bar>
            @foreach ($desk['picker'] as $p)
                <x-dl::button variant="secondary" :label="raw($p['label'])" :href="$p['url']" />
            @endforeach
        </x-dl::action-bar>
    </x-dl::card>
@else
    <x-mp::production.output-desk :desk="$desk" />
@endif
