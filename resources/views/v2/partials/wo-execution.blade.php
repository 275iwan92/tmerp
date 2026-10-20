{{--
 REMARK
 Tujuan       : Kartu Execution di tab Bill of Process layar production-wo: aksi per baris proses menurut statusnya, memindahkan fitur eksekusi v1 pages::production.wo-entry: RUNNING = Report breakdown; QC_HOLD = QC pass / QC reject; QC_REJECTED = Rework; SUBCONTRACT = Receive from subcontract; BREAKDOWN = Re-route to machine / Subcontract PO. Catat Output tidak ada di sini: sudah ada di layar operator (OperatorTimerService).
 Sumber hulu  : $ops (Ppic::workOrder), WoBreakdownService::openEvent, mesin alternatif satu divisi, lookup subcon_supplier; aturan di WoQcGateService / WoBreakdownService.
 Input        : $wo (PrdWo), $ops (array baris proses), $src (Src).
 Output       : x-dl::card, x-dl::action-form (POST v2.production.wo.op.*), x-dl::modal per dialog.
 Dipakai oleh : resources/views/v2/screens/production-wo.blade.php (tab Bill of Process).
 Status       : Aktif (T9 pindahan v1, 2026-10-20).
--}}
@php
    use App\Models\Master\ProdMachine;
    use App\Services\Production\WoBreakdownService;

    $statusTone = ['RUNNING' => 'success', 'QC_HOLD' => 'warn', 'QC_REJECTED' => 'danger', 'SUBCONTRACT' => 'info', 'BREAKDOWN' => 'danger'];
    $actionable = collect($ops)->filter(fn ($o) => isset($statusTone[$o['task_status']]));
    $route = fn (string $name, int $op) => route('v2.production.wo.'.$name, ['wo' => $wo->wo_id, 'op' => $op]);
@endphp
@if ($actionable->isNotEmpty())
    <x-dl::card title="Execution" description="Quality gate, breakdown and subcontract decisions for the processes that are running or waiting for a decision. Output is recorded on the operator screen.">
        @foreach ($actionable as $o)
            @php
                $opId = $o['op_id'];
                $status = $o['task_status'];
                $event = $status === 'BREAKDOWN' ? app(WoBreakdownService::class)->openEvent($opId) : null;
                $alt = $status === 'BREAKDOWN' && $o['machine_id']
                    ? ProdMachine::where('division_id', ProdMachine::find($o['machine_id'])?->division_id)->where('activate', true)->where('machine_id', '!=', $o['machine_id'])->orderBy('machine_name')->pluck('machine_name', 'machine_id')->all()
                    : [];
            @endphp
            <x-dl::action-bar align="start">
                <x-dl::badge :label="$status" :tone="$statusTone[$status]" />
                <x-dl::hint>{{ $o['seq'] }} · {{ $o['process'] }} · {{ $o['machine'] ?? t('Manual') }}</x-dl::hint>
                @if ($status === 'RUNNING')
                    <x-dl::button size="sm" variant="secondary" icon="fa-triangle-exclamation" label="Report breakdown" type="button" :data-dl-open="'wo-bd-'.$opId" />
                @elseif ($status === 'QC_HOLD')
                    <x-dl::action-form action="{{ $route('op.qc-pass', $opId) }}"><x-dl::button size="sm" variant="secondary" icon="fa-check" label="QC pass" type="submit" /></x-dl::action-form>
                    <x-dl::button size="sm" variant="secondary" icon="fa-xmark" label="QC reject" type="button" :data-dl-open="'wo-qc-'.$opId" />
                @elseif ($status === 'QC_REJECTED')
                    <x-dl::action-form action="{{ $route('op.rework', $opId) }}"><x-dl::button size="sm" variant="secondary" icon="fa-rotate-left" label="Rework" type="submit" /></x-dl::action-form>
                @elseif ($status === 'SUBCONTRACT')
                    <x-dl::action-form action="{{ $route('op.receive-subcontract', $opId) }}"><x-dl::button size="sm" variant="secondary" icon="fa-box" label="Receive from subcontract" type="submit" /></x-dl::action-form>
                @elseif ($status === 'BREAKDOWN' && $event)
                    <x-dl::button size="sm" variant="secondary" icon="fa-right-left" label="Re-route to machine" type="button" :data-dl-open="'wo-rr-'.$opId" />
                    <x-dl::button size="sm" variant="secondary" icon="fa-truck-ramp-box" label="Subcontract PO" type="button" :data-dl-open="'wo-sc-'.$opId" />
                @endif
            </x-dl::action-bar>

            @if ($status === 'RUNNING')
                <x-dl::modal :id="'wo-bd-'.$opId" title="Report breakdown" size="md" icon="fa-triangle-exclamation" tone="danger">
                    <x-dl::action-form action="{{ $route('op.breakdown', $opId) }}" :id="'wo-bd-form-'.$opId">
                        <x-dl::field.number name="downtime" label="Estimated downtime (minutes)" :value="60" required :source="$src" />
                    </x-dl::action-form>
                    <x-slot:footer>
                        <x-dl::button preset="cancel" data-dl-close-modal />
                        <x-dl::button variant="danger" icon="fa-triangle-exclamation" label="Report breakdown" type="submit" :form="'wo-bd-form-'.$opId" />
                    </x-slot:footer>
                </x-dl::modal>
            @elseif ($status === 'QC_HOLD')
                <x-dl::modal :id="'wo-qc-'.$opId" title="QC reject" size="md" icon="fa-xmark" tone="danger">
                    <x-dl::action-form action="{{ $route('op.qc-reject', $opId) }}" :id="'wo-qc-form-'.$opId">
                        <x-dl::field.text name="reason" label="Rejection reason" required :case-toggle="false" placeholder="Colour is outside the Delta E tolerance..." :source="$src" />
                    </x-dl::action-form>
                    <x-slot:footer>
                        <x-dl::button preset="cancel" data-dl-close-modal />
                        <x-dl::button variant="danger" icon="fa-xmark" label="QC reject" type="submit" :form="'wo-qc-form-'.$opId" />
                    </x-slot:footer>
                </x-dl::modal>
            @elseif ($status === 'BREAKDOWN' && $event)
                <x-dl::modal :id="'wo-rr-'.$opId" title="Re-route to machine" size="md" icon="fa-right-left">
                    <x-dl::action-form action="{{ $route('op.reroute', $opId) }}" :id="'wo-rr-form-'.$opId">
                        <x-dl::field.select name="alt_machine_id" label="Alternative machine (same division)" :options="$alt" required :source="$src" />
                    </x-dl::action-form>
                    <x-slot:footer>
                        <x-dl::button preset="cancel" data-dl-close-modal />
                        <x-dl::button icon="fa-right-left" label="Re-route to machine" type="submit" :form="'wo-rr-form-'.$opId" />
                    </x-slot:footer>
                </x-dl::modal>
                <x-dl::modal :id="'wo-sc-'.$opId" title="Subcontract PO" size="md" icon="fa-truck-ramp-box">
                    <x-dl::action-form action="{{ $route('op.subcontract', $opId) }}" :id="'wo-sc-form-'.$opId">
                        <x-dl::hint>@t('Approval of a subcontract PO needs an Operations Manager or above.')</x-dl::hint>
                        <x-dl::field.lookup name="vendor_id" lookup="subcon_supplier" label="Vendor (subcontractor)" required placeholder="Type 2+ letters of supplier code / name..." :source="$src" />
                        <x-dl::field.decimal name="estimated_cost" label="Estimated cost (IDR)" required :source="$src" />
                        <x-dl::form.row :cols="2">
                            <x-dl::field.date name="due_out" label="Send date" :value="now()->addDay()->toDateString()" required :source="$src" />
                            <x-dl::field.date name="due_return" label="Return date" :value="now()->addDays(3)->toDateString()" required :source="$src" />
                        </x-dl::form.row>
                    </x-dl::action-form>
                    <x-slot:footer>
                        <x-dl::button preset="cancel" data-dl-close-modal />
                        <x-dl::button icon="fa-truck-ramp-box" label="Subcontract PO" type="submit" :form="'wo-sc-form-'.$opId" />
                    </x-slot:footer>
                </x-dl::modal>
            @endif
        @endforeach
    </x-dl::card>
@endif
