{{-- Help & support v2: laci LEBAR dari kanan (F1 / Profile > Help & support). Empat bagian (user 2026-10-04):
     Help Topics = Help Center (semua topik per layar), User's Guide = panduan umum (sys_help_topic tipe GUIDE) + pedoman,
     Application's Flow = alur dokumen (App\Support\V2\Flows), Tickets = kirim tiket + lampiran dan balasan tim developer. --}}
@php
    use App\Models\Sys\SysHelpLine;
    use App\Models\Sys\SysHelpTopic;
    use App\Models\Sys\SysSupportTicket;
    use App\Services\Help\HelpCenter;
    use App\Support\Localization\Dictionary;
    use App\Support\V2\Flows;
    use App\Support\V2\Grid\Grids;
    use App\Support\V2\Src;
    use Devlib\Locale;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Storage;

    $tab = request('tab', 'topics');
    $help = fn (?string $en) => Dictionary::t($en, HelpCenter::CONTEXT);
    $guides = SysHelpTopic::query()->where('activate', true)->where('topic_type', SysHelpTopic::GUIDE)
        ->with(['lines' => fn ($q) => $q->where('activate', true)->orderBy('sort_order')])->orderBy('sort_order')->orderBy('title')->get();
    $tickets = SysSupportTicket::with(['replies', 'attachments'])->where('created_by_user_id', Auth::id())->orderByDesc('id_ticket')->limit(20)->get();
    $statusLabel = ['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'CLOSED' => 'Closed'];
    $statusTone = ['OPEN' => 'warn', 'IN_PROGRESS' => 'info', 'DONE' => 'success', 'CLOSED' => 'neutral'];
@endphp

<x-dl::side-screen.layout :sections="['topics' => ['Help Topics', 'fa-life-ring'], 'guide' => ['User\'s Guide', 'fa-book-open'], 'flow' => ['Application\'s Flow', 'fa-diagram-project'], 'tickets' => ['Tickets', 'fa-ticket', $tickets->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count() ?: null]]" :active="$tab" :hints="['topics' => 'Every screen has its help: purpose, steps and an explanation of each field. Open a row to read it.', 'guide' => 'Read the guides from top to bottom the first time; later, open the one you need. Each guide = numbered steps plus an explanation of the parts of the screen.', 'flow' => 'Point the mouse at a block to see its short rule; click a block to open its screen.', 'tickets' => 'Send a ticket to the developer team with screenshots; the reply appears here.']" :crumbs="$crumbs">
    <x-slot:top>
        @if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
        @if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
    </x-slot:top>
    <x-slot:topics>
        @include('v2.screens._dbgrid', ['screen' => Grids::screen('help-center')])
    </x-slot:topics>

    <x-slot:guide>
            <x-dl::card title="Written guides" description="The complete ERP rules and the Master Product & Revision guide." icon="fa-book">
            {{-- Pedoman tertulis = bagian dari User's Guide (user 2026-10-04), bukan tautan terpisah di rel. --}}
            <x-dl::card title="Written guides" description="The complete ERP rules and the Master Product & Revision guide." icon="fa-book">
                <x-dl::action-bar align="start">
                    <x-dl::button variant="secondary" icon="fa-book" label="ERP Rules Guide" :href="route('v2.guide', 'aturan')" />
                    <x-dl::button variant="secondary" icon="fa-book-open" label="Guide: Master Product & Revision" :href="route('v2.guide', 'product-master')" />
                </x-dl::action-bar>
            </x-dl::card>
                <x-dl::action-bar align="start">
                    <x-dl::button variant="secondary" icon="fa-book" label="ERP Rules Guide" :href="route('v2.guide', 'aturan')" />
                    <x-dl::button variant="secondary" icon="fa-book-open" label="Guide: Master Product & Revision" :href="route('v2.guide', 'product-master')" />
                </x-dl::action-bar>
            </x-dl::card>
            @foreach ($guides as $i => $g)
                @php
                    $steps = $g->lines->where('line_type', SysHelpLine::STEP)->values();
                    $fields = $g->lines->where('line_type', SysHelpLine::FIELD)->values();
                @endphp
                <x-dl::card :title="raw(($i + 1).'. '.$help($g->title))" :description="raw($help($g->purpose))" icon="fa-circle-check">
                    @if ($steps->isNotEmpty())
                        <x-dl::table :columns="['n' => 'Step', 'text' => 'What to do']" :rows="$steps->map(fn ($l, $k) => ['n' => $k + 1, 'text' => $help($l->body)])->all()" />
                    @endif
                    @if ($fields->isNotEmpty())
                        <x-dl::table :columns="['field' => 'Part of the screen', 'text' => 'Explanation']" :rows="$fields->map(fn ($l) => ['field' => Dictionary::t($l->field_label), 'text' => $help($l->body)])->all()" />
                    @endif
                </x-dl::card>
            @endforeach
    </x-slot:guide>

    <x-slot:flow>
            @foreach (['SALES', 'COSTING'] as $module)
                @php $flow = Flows::get($module); @endphp
                <x-dl::card :title="raw($flow['title'])" icon="fa-diagram-project">
                    <x-dl::flow :title="''" :steps="$flow['steps']" source="app/Support/V2/Flows.php" />
                </x-dl::card>
            @endforeach
    </x-slot:flow>

    <x-slot:tickets>
        <x-dl::form.row :cols="2">
            <x-dl::action-form action="{{ route('v2.support.ticket') }}" enctype="multipart/form-data" data-dl-guard>
                <input type="hidden" name="return" value="help">
                <x-dl::card title="Send a Ticket" description="The developer team answers in Setup > Support & Ticket; the reply appears here." icon="fa-paper-plane">
                    <x-dl::field.text name="screen_context" label="Screen / menu" :value="old('screen_context')" placeholder="E.g. Customer Order" :source="Src::of('tm2sys.sys_support_ticket.screen_context')" />
                    <x-dl::field.text name="description" label="Problem description" :value="old('description')" multiline required placeholder="What happened, and what did you expect?" :source="Src::of('tm2sys.sys_support_ticket.description')" />
                    <x-dl::field.upload name="files" :multiple="true" :preview="false" label="Attachments (screenshot, PDF, Excel; up to 5 files, 5 MB each)" accept=".jpg,.jpeg,.png,.pdf,.xlsx,.xls,.docx" :source="Src::of('tm2sys.sys_support_ticket_attachment.file_path')" />
                    <x-dl::action-bar><x-dl::button type="submit" icon="fa-paper-plane" label="Send ticket" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
            <x-dl::card title="My Tickets" icon="fa-ticket">
                @forelse ($tickets as $t)
                    <x-dl::card :title="raw($t->ticket_no.' · '.Locale::dt($t->created_at))" :description="raw($t->screen_context ?: '')" icon="fa-ticket">
                        <x-dl::badge :label="$statusLabel[$t->status] ?? $t->status" :tone="$statusTone[$t->status] ?? 'neutral'" />
                        <p>{{ $t->description }}</p>
                        @if ($t->attachments->isNotEmpty())
                            <x-dl::action-bar align="start">
                                @foreach ($t->attachments as $a)
                                    <x-dl::button variant="secondary" icon="fa-paperclip" :label="raw(basename($a->file_path))" :href="Storage::disk('public')->url($a->file_path)" target="_blank" />
                                @endforeach
                            </x-dl::action-bar>
                        @endif
                        @if ($r = $t->replies->last())<x-dl::field.display :label="raw(t('Reply from').' '.$r->author)" :value="$r->body" />@endif
                        @if ($t->resolution_note)<x-dl::field.display label="Resolution" :value="$t->resolution_note" />@endif
                    </x-dl::card>
                @empty
                    <x-dl::empty-state title="No tickets yet" message="Tickets you send appear here together with the support team replies." icon="fa-ticket" />
                @endforelse
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:tickets>
</x-dl::side-screen.layout>
