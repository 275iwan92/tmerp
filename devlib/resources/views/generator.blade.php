{{-- Generator devlib.test (user 2026-10-05): form permintaan sederhana + pratinjau yang diisi agen. Form tidak menulis kode;
     isi dan aturan = App\Support\Generator, tabel gen_request / gen_request_object di database devlib. Disusun dari objek x-dl::*. --}}
@extends('layout', ['title' => 'Generator', 'dev' => true, 'bodyClass' => 'dl-page-body'])
@php
    use App\Support\Generator;
    use Devlib\Lang;

    $home = route('generator');
    $closed = $req && in_array($req->status, ['BUILT', 'CANCELLED'], true);
    $pending = collect($objects)->where('decision', 'PENDING');
@endphp
@section('body')
<div class="dl-sum">
    <header class="dl-sum__head"><b>{{ Lang::text('Generator') }}</b><small>{{ Lang::text('Describe what you need in your own words. The request is saved here; the agent reads it, shows a preview, and builds the screen from DevLib objects after you approve.') }}</small></header>

    @if (session('gen_flash'))<x-dl::alert tone="success" :message="session('gen_flash')" />@endif
    @if (session('gen_error'))<x-dl::alert tone="danger" :message="session('gen_error')" />@endif
    @unless ($ready)
        <x-dl::alert tone="warn" message="The generator tables are not installed yet. Run the devlib migration 2026_10_05_000003_create_gen_request_tables." />
    @endunless

    @if ($ready && ! $form)
        <x-dl::grid id="gen-requests" :rows="$rows" search="Search request no., name..." actions="worklist" :active-key="null" :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'"
            source="devlib.gen_request"
            :columns="[
                ['key' => 'no', 'label' => 'Request No. / Name', 'type' => 'doc', 'party' => 'name'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'engine', 'label' => 'Engine'],
                ['key' => 'requested', 'label' => 'Requested', 'type' => 'date'],
                ['key' => 'by', 'label' => 'By'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Generator::statusTones()],
                ['key' => 'agent', 'label' => 'Built By'],
                ['key' => 'screen', 'label' => 'Screen'],
            ]"
            :filters="['type' => 'Type', 'status' => 'Status']" />
    @elseif ($ready)
        <x-dl::form.row :cols="2">
            <x-dl::action-form action="{{ route('generator.save') }}" data-dl-guard>
                @if ($req)<input type="hidden" name="id" value="{{ $req->id_request }}">@endif
                <x-dl::card :title="$req ? Lang::raw($req->request_no.' · '.$req->name) : 'New Request'" icon="fa-wand-magic-sparkles">
                    @if ($req)
                        <x-slot:actions><x-dl::badge :label="Generator::STATUS[$req->status][0]" :tone="Generator::STATUS[$req->status][1]" /></x-slot:actions>
                    @endif
                    <x-dl::field.text name="name" label="Name" required :value="old('name', $req->name ?? '')" placeholder="e.g. Master Mesin" :readonly="$closed" source="devlib.gen_request.name" />
                    <x-dl::field.text name="description" label="Description" required multiline :value="old('description', $req->description ?? '')" :readonly="$closed" source="devlib.gen_request.description" />
                    <x-dl::field.radio name="request_type" label="Type" required :options="Generator::TYPES" :value="old('request_type', $req->request_type ?? 'MASTER')" :disabled="$closed"
                        hint="Master: data that is kept and switched active / inactive. Transactional: a document (Draft, Confirmed, Cancelled, Closed). Custom: a special form that gets its own object." source="devlib.gen_request.request_type" />
                    <x-dl::field.lookup name="source_table" lookup="gen_table" label="Existing table (Master only, optional)" :value="old('source_table', $req->source_table ?? null)" :disabled="$closed"
                        placeholder="Type a table name, e.g. profit, delivery, category ..."
                        hint="Leave it empty and the agent builds it. Choose a table when it already exists and is a simple master: Generate Now then builds the screen at once." />
                    <x-dl::field.checkbox name="need_engine" label="Engine" text="Needs an engine (a calculation or a suggestion that the screen must run)" :value="(bool) old('need_engine', $req->need_engine ?? false)" :disabled="$closed" source="devlib.gen_request.need_engine" />
                    <x-dl::field.text name="engine_description" label="Engine description" multiline :value="old('engine_description', $req->engine_description ?? '')" :readonly="$closed" hint="Required when Needs an engine is ticked." source="devlib.gen_request.engine_description" />
                    <x-dl::field.text name="flow_description" label="Flow (data flow or document flow)" multiline :value="old('flow_description', $req->flow_description ?? '')" :readonly="$closed" source="devlib.gen_request.flow_description" />
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                        @unless ($closed)<x-dl::button preset="save" type="submit" />@endunless
                    </x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>

            <x-dl::form.col>
                @if (! $req)
                    <x-dl::card title="What happens after Save" icon="fa-circle-info">
                        <x-dl::alert tone="info" message="1. The request gets a number and the status Requested. 2. The agent reads open requests at the start of a session and asks only what the description leaves unclear. 3. The agent fills the Preview: objects needed, engine input and output, engine formula. 4. You correct the formula if needed and Approve; an object that is not built yet asks for your approval first. 5. The agent builds the screen and puts its link here; you rate it in Quality & Rating." />
                    </x-dl::card>
                @else
                    @if (Generator::canAuto($req))
                        <x-dl::card title="Automatic build" icon="fa-bolt" :description="Lang::raw(strtr(Lang::text('This Master request names the table :table. The screen can be built now, without the agent.'), [':table' => $req->source_table]))">
                            <x-dl::action-form action="{{ route('generator.generate', $req->id_request) }}">
                                <x-dl::action-bar align="start"><x-dl::button type="submit" icon="fa-bolt" label="Generate Now" /></x-dl::action-bar>
                            </x-dl::action-form>
                        </x-dl::card>
                    @endif
                    @if ($req->build_log)
                        <x-dl::alert :tone="$req->status === 'BUILT' ? 'success' : 'warn'" :message="Lang::raw($req->build_log)" />
                    @endif
                    @if ($req->agent_note)
                        <x-dl::alert :tone="$req->status === 'QUESTION' ? 'warn' : 'info'" :message="Lang::raw(($req->status === 'QUESTION' ? Lang::text('The agent asks').': ' : Lang::text('Agent note').': ').$req->agent_note)" />
                    @endif
                    @foreach ($pending as $o)
                        {{-- Dialog persetujuan objek yang belum ada di DevLib: satu per objek. --}}
                        <x-dl::card title="This object is not built yet" icon="fa-circle-question" :description="Lang::raw(strtr(Lang::text('The request needs :name. It is not in DevLib. Build it as a new DevLib object, so other screens can use it too?'), [':name' => $o->object_name]))">
                            <x-dl::action-form action="{{ route('generator.object', $o->id_object) }}">
                                <x-dl::action-bar align="start">
                                    <x-dl::button type="submit" name="decision" value="approve" preset="approve" label="Approve" />
                                    <x-dl::button type="submit" name="decision" value="reject" variant="secondary" icon="fa-xmark" label="Reject" />
                                </x-dl::action-bar>
                            </x-dl::action-form>
                        </x-dl::card>
                    @endforeach
                    <x-dl::card title="Preview" icon="fa-eye" description="Filled by the agent after it reads the request. Nothing is built before you approve.">
                        @if (! $objects && ! $req->engine_formula && ! $req->engine_input)
                            <x-dl::empty-state title="No preview yet" message="The agent has not read this request yet." icon="fa-hourglass-half" />
                        @else
                            <x-dl::table :striped="false" :columns="['name' => 'Objects this request needs', 'tag' => 'Object', 'state' => 'State']" source="devlib.gen_request_object"
                                :rows="collect($objects)->map(fn ($o) => ['name' => $o->object_name, 'tag' => $o->object_tag ?: '—',
                                    'state' => $o->is_built ? Lang::text('Exists') : Lang::text(['PENDING' => 'Not built yet, waiting for your approval', 'APPROVED' => 'Not built yet, approved', 'REJECTED' => 'Rejected'][$o->decision] ?? $o->decision)])->all()" />
                            <x-dl::action-form action="{{ route('generator.formula', $req->id_request) }}" data-dl-guard>
                                <x-dl::form.row :cols="2">
                                    <x-dl::field.text name="engine_input" label="Engine input" multiline :value="$req->engine_input" :readonly="$closed" source="devlib.gen_request.engine_input" />
                                    <x-dl::field.text name="engine_output" label="Engine output" multiline :value="$req->engine_output" :readonly="$closed" source="devlib.gen_request.engine_output" />
                                </x-dl::form.row>
                                <x-dl::field.text name="engine_formula" label="Engine formula" multiline :case-toggle="false" :value="$req->engine_formula" :readonly="$closed" hint="Written by the agent; correct it here in your own words or symbols." source="devlib.gen_request.engine_formula" />
                                @unless ($closed)<x-dl::action-bar align="start"><x-dl::button preset="save" type="submit" /></x-dl::action-bar>@endunless
                            </x-dl::action-form>
                        @endif
                    </x-dl::card>
                    @if ($req->status === 'PREVIEW')
                        <x-dl::action-form action="{{ route('generator.approve', $req->id_request) }}">
                            <x-dl::action-bar align="start">
                                <x-dl::button type="submit" preset="approve" label="Approve" :disabled="$pending->isNotEmpty()" />
                            </x-dl::action-bar>
                        </x-dl::action-form>
                    @endif
                    @if ($req->status === 'APPROVED')
                        <x-dl::alert tone="success" :message="Lang::raw(Lang::text('Approved').' · '.$req->approved_by.' · '.Generator::stamp($req->approved_at).'. '.Lang::text('The agent builds it in its next session.'))" />
                    @endif
                    @if ($req->status === 'BUILT')
                        <x-dl::alert tone="success" :message="Lang::raw(Lang::text('Built').' · '.Generator::stamp($req->built_at).' · '.$req->screen_label)" />
                        @if ($req->screen_url)<x-dl::action-bar align="start"><x-dl::button icon="fa-up-right-from-square" label="Open" :href="$req->screen_url" target="_blank" /></x-dl::action-bar>@endif
                    @endif
                    @unless ($closed)
                        <x-dl::action-form action="{{ route('generator.cancel', $req->id_request) }}">
                            <x-dl::action-bar align="start"><x-dl::button type="submit" variant="danger" preset="cancel" label="Cancel" /></x-dl::action-bar>
                        </x-dl::action-form>
                    @endunless
                @endif
            </x-dl::form.col>
        </x-dl::form.row>
    @endif
</div>
@endsection
