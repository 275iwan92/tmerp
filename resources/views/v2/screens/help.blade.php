{{-- Help & support v2: dibuka sebagai laci LEBAR dari kanan (F1 / Profile > Help & support, user 2026-10-03).
     Isi = pintu bantuan per layar (Help Center, aturan E.1), pedoman & alur dokumen, dan Support Ticket + balasan tim developer. --}}
@php
    use App\Models\Sys\SysSupportTicket;
    use App\Support\V2\Src;
    use Devlib\Locale;
    use Illuminate\Support\Facades\Auth;

    $tickets = SysSupportTicket::with('replies')->where('created_by_user_id', Auth::id())->orderByDesc('id_ticket')->limit(20)->get();
    $statusLabel = ['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'CLOSED' => 'Closed'];
    $statusTone = ['OPEN' => 'warn', 'IN_PROGRESS' => 'info', 'DONE' => 'success', 'CLOSED' => 'neutral'];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::tabs :tabs="['help' => ['Help & Guides', 'fa-life-ring'], 'ticket' => ['Support Ticket', 'fa-ticket']]" :active="request('tab', 'help')">
    <x-slot:help>
        <x-dl::form.row :cols="2">
            <x-dl::card title="Help Center" description="What a screen is for, how to use it and what each field means — press F1 on any screen." icon="fa-life-ring">
                <x-dl::action-bar align="start"><x-dl::button icon="fa-life-ring" label="Open Help Center" :href="route('v2.screen', 'help-center')" /></x-dl::action-bar>
            </x-dl::card>
            <x-dl::card title="Guides" description="The ERP rules and the Master Product & Revision guide." icon="fa-book">
                <x-dl::action-bar align="start">
                    <x-dl::button variant="secondary" icon="fa-book" label="ERP Rules Guide" :href="route('v2.guide', 'aturan')" />
                    <x-dl::button variant="secondary" icon="fa-book-open" label="Guide: Master Product & Revision" :href="route('v2.guide', 'product-master')" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::form.row>
        {{-- Alur dokumen: sementara di sini, bukan grup menu "Flow" (user 2026-10-02, akan dipikirkan ulang). --}}
        <x-dl::card title="Document Flows" description="How documents move between screens." icon="fa-diagram-project">
            <x-dl::action-bar align="start">
                <x-dl::button variant="secondary" icon="fa-diagram-project" label="Sales Flow: Inquiry → CPDEL" :href="route('v2.flow', 'SALES')" />
                <x-dl::button variant="secondary" icon="fa-diagram-project" label="Costing Flow: ProDev → ProCost → Publish" :href="route('v2.flow', 'COSTING')" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-slot:help>
    <x-slot:ticket>
        <x-dl::form.row :cols="2">
            <x-dl::action-form action="{{ route('v2.support.ticket') }}" data-dl-guard>
                <input type="hidden" name="return" value="help">
                <x-dl::card title="Send a Ticket" description="The developer team answers in Setup > Support Tickets; the reply appears here." icon="fa-paper-plane">
                    <x-dl::field.text name="screen_context" label="Screen / menu" :value="old('screen_context')" placeholder="E.g. Customer Order" :source="Src::of('tm2sys.sys_support_ticket.screen_context')" />
                    <x-dl::field.text name="description" label="Problem description" :value="old('description')" multiline required placeholder="What happened, and what did you expect?" :source="Src::of('tm2sys.sys_support_ticket.description')" />
                    <x-dl::action-bar><x-dl::button type="submit" icon="fa-paper-plane" label="Send ticket" /></x-dl::action-bar>
                </x-dl::card>
            </x-dl::action-form>
            <x-dl::card title="My Tickets" icon="fa-ticket">
                @forelse ($tickets as $t)
                    <x-dl::card :title="raw($t->ticket_no.' · '.Locale::dt($t->created_at))" :description="raw($t->screen_context ?: '')" icon="fa-ticket">
                        <x-dl::badge :label="$statusLabel[$t->status] ?? $t->status" :tone="$statusTone[$t->status] ?? 'neutral'" />
                        <p>{{ $t->description }}</p>
                        @if ($r = $t->replies->last())<x-dl::field.display :label="raw(t('Reply from').' '.$r->author)" :value="$r->body" />@endif
                        @if ($t->resolution_note)<x-dl::field.display label="Resolution" :value="$t->resolution_note" />@endif
                    </x-dl::card>
                @empty
                    <x-dl::empty-state title="No tickets yet" message="Tickets you send appear here together with the support team replies." icon="fa-ticket" />
                @endforelse
            </x-dl::card>
        </x-dl::form.row>
    </x-slot:ticket>
</x-dl::tabs>
