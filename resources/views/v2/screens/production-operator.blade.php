{{--
 REMARK
 Tujuan       : Production Output: layar entry hasil produksi di mesin. Terbuka langsung untuk crew yang login (tanpa grid / pemilih), dengan LIST OF JOBS
                dari antrean CONFIRMED PPIC Planning pada mesin + proses crew itu.
 Sumber hulu  : App\Support\V2\Data\ProductionOutput::desk() (login -> employee -> prd_crew_assignment -> operasi; Ppic::operations; Master Card).
 Input        : ?op= (job aktif); ?machine= & ?process= (administrator, atau di antara pasangan crew sendiri).
 Output       : Objek x-mp::production.output-desk; Play/Pause/Stop dan SIMPAN HASIL menulis lewat OperatorTimerService (+ prd_output_action_log).
 Dipakai oleh : Menu Production > Planning (item Operator Queue -> Production Output), rute v2.screen production-operator.
 Status       : Aktif di mockup (ddt*).
--}}
<x-mp::production.output-desk :desk="App\Support\V2\Data\ProductionOutput::desk(auth()->user(), ['op' => request()->integer('op') ?: null, 'machine' => request()->integer('machine') ?: null, 'process' => request('process')])" />
