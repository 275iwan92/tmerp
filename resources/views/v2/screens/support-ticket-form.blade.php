{{--
 REMARK
 Tujuan       : Form Support Ticket (menggantikan dialog tiket halaman v1 pages::setup.support-tickets): satu tiket Bantuan & dukungan baca-saja (pelapor, layar asal, masalah, lampiran) dengan percakapan balasan, kolom balasan (Send reply) dan catatan penyelesaian, serta tombol status In progress, Mark done dan Not a bug (close); tombol Reopen untuk tiket selesai. Hanya administrator.
 Sumber hulu  : tm2sys.sys_support_ticket + sys_support_replies + sys_support_ticket_attachment + sec_user; aturan di App\Services\Support\SupportTicketService.
 Input        : Query id (sys_support_ticket.id_ticket); session v2_flash dan errors.
 Output       : POST v2.setup.ticket.save (action reply, progress, done, close, reopen).
 Dipakai oleh : Dibuka dari grid support-tickets (Open); tiket baru dibuat pelapor dari menu akun.
 Status       : Aktif (T8 Setup).
--}}
@php
    use App\Models\Sys\SysSupportTicket;
    use App\Services\Support\SupportTicketService as Tickets;
    use App\Support\V2\Src;
    use Devlib\Locale;
    use Illuminate\Support\Facades\Storage;

    $home = route('v2.screen', 'support-tickets');
    $svc = app(Tickets::class);
    abort_unless(Auth::user()->canSetup(), 403, t('This page is for administrators only.'));
    $t = SysSupportTicket::with(['creator', 'attachments', 'replies'])->find((int) request('id'));
    abort_unless($t, 404);
    $st = $t->status ?: 'OPEN';
    $closed = $st === 'CLOSED';
    $s = fn (string $col) => Src::of('tm2sys.sys_support_ticket.'.$col);
    $ts = fn ($d) => $d ? Locale::dateTime($d) : '—';
    $thread = $t->replies->map(fn ($r) => ['at' => $ts($r->created_at), 'author' => raw($r->author), 'body' => raw($r->body)])->all();
    $files = $t->attachments->map(fn ($a) => ['file' => raw(basename($a->file_path)), 'url' => Storage::disk('public')->url($a->file_path)]);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="support-ticket-form" :title="raw($t->ticket_no)" :subtitle="raw($t->creator?->name ?: $t->creator?->login ?: '—')" panel="top" kind="master" :status="in_array($st, ['DONE', 'CLOSED'], true) ? 'inactive' : 'active'" :stage="t(Tickets::STATUSES[$st] ?? $st)"
    :can-input="true" :hide="['deactivate', 'activate', 'new', 'undo', 'save']" :action="route('v2.setup.ticket.save')" action-field="action" :back="$home" :hidden="['id' => $t->id_ticket]"
    :tabs="['ticket' => t('Ticket')]" :log="$svc->history($t)" :created-at="$t->created_at?->toDateTimeString()" :updated-at="$t->updated_at?->toDateTimeString()">
    <x-slot:special>
        @unless ($closed)
            <x-dl::button variant="primary" icon="fa-paper-plane" label="Send reply" type="submit" name="action" value="reply" formnovalidate />
            @if ($st === 'OPEN')
                <x-dl::button variant="secondary" icon="fa-spinner" label="In progress" type="submit" name="action" value="progress" formnovalidate />
            @endif
            @if ($st !== 'DONE')
                <x-dl::button variant="success" icon="fa-check" label="Mark done" type="submit" name="action" value="done" formnovalidate />
            @endif
            <x-dl::button variant="secondary" icon="fa-ban" label="Not a bug (close)" type="submit" name="action" value="close" formnovalidate data-dl-confirm="{{ t('Close this ticket without a fix?') }}" />
        @endunless
        @if (in_array($st, ['DONE', 'CLOSED'], true))
            <x-dl::button variant="secondary" icon="fa-rotate-left" label="Reopen" type="submit" name="action" value="reopen" formnovalidate />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Ticket" :value="raw($t->ticket_no)" />
        <x-dl::strip-kv kind="grand" label="Status" :value="t(Tickets::STATUSES[$st] ?? $st)" />
        <x-dl::strip-kv kind="grand" label="Replies" :value="(string) $t->replies->count()" />
        <x-dl::strip-kv kind="grand" label="Sent" :value="$ts($t->created_at)" />
    </x-slot:strip>

    <x-slot:ticket>
        <x-dl::card title="Ticket (read-only)">
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Reported by" :value="raw($t->creator?->name ?: $t->creator?->login ?: '—')" :source="$s('created_by_user_id')" />
                <x-dl::field.display label="Screen" :value="raw($t->screen_context ?: '—')" :source="$s('screen_context')" />
                <x-dl::field.display label="Resolved" :value="$t->resolved_at ? $ts($t->resolved_at).' · '.$t->resolved_by : '—'" :source="$s('resolved_at')" />
            </x-dl::form.row>
            <x-dl::field.display label="Problem" :value="raw($t->description)" :source="$s('description')" />
            @foreach ($files as $f)
                <x-dl::button size="sm" variant="secondary" icon="fa-paperclip" :label="$f['file']" :href="$f['url']" />
            @endforeach
        </x-dl::card>
        <x-dl::card title="Conversation">
            <x-dl::table :columns="['at' => 'When', 'author' => 'From', 'body' => 'Message']" :rows="$thread" />
        </x-dl::card>
        @unless ($closed)
            <x-dl::card title="Your answer">
                <x-dl::field.text name="reply" label="Reply to the reporter" multiline :value="old('reply')" :source="Src::of('tm2sys.sys_support_replies.body')" />
                <x-dl::field.text name="resolution_note" label="Resolution note (what was fixed / why closed)" multiline :value="old('resolution_note', $t->resolution_note)" :source="$s('resolution_note')" />
            </x-dl::card>
        @else
            <x-dl::field.display label="Resolution note" :value="raw($t->resolution_note ?: '—')" :source="$s('resolution_note')" />
        @endunless
    </x-slot:ticket>
</x-dl::form>
