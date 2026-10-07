{{-- Agent History devlib.test (user 2026-10-07): catatan pengingat user. Ringkasan laporan agen Iwan / Jo, keputusan, catatan dan
     tautan Artifact. Aturan = App\Support\AgentHistory, tabel dev_note di database devlib. Disusun dari objek x-dl::*. --}}
@extends('layout', ['title' => 'Historical', 'dev' => true, 'bodyClass' => 'dl-page-body'])
@php
    use App\Support\AgentHistory;
    use Devlib\Lang;

    $home = route('history');
@endphp
@section('body')
<div class="dl-sum">
    <header class="dl-sum__head"><b>{{ Lang::text('Historical') }}</b><small>{{ Lang::text('Reports, decisions, notes and Artifact links saved by Agent Iwan, Agent Jo and you, kept here as your reminder.') }}</small></header>

    @if (session('hist_flash'))<x-dl::alert tone="success" :message="session('hist_flash')" />@endif
    @if (session('hist_error'))<x-dl::alert tone="danger" :message="session('hist_error')" />@endif
    @unless ($ready)
        <x-dl::alert tone="warn" message="The notes table is not installed yet. Run the devlib migration 2026_10_07_000001_create_dev_note_table." />
    @endunless

    @if ($ready && ! $form && ! $note)
        {{-- Umpan gaya blog / berita: semua narasi terbaru di atas, dibaca berurutan tanpa membuka satu per satu. --}}
        <nav class="dl-history__tabs" style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 14px">
            <x-dl::button :variant="$author ? 'secondary' : 'primary'" label="All" :href="$home" />
            @foreach (AgentHistory::AUTHORS as $key => $name)
                <x-dl::button :variant="$author === $key ? 'primary' : 'secondary'" :label="$name" :href="$home.'?author='.$key" />
            @endforeach
            <x-dl::button variant="secondary" label="New Note" :href="$home.'?new=1'" />
        </nav>
        <x-dl::feed :posts="array_map(fn ($n) => ['title' => Lang::raw($n->title), 'badge' => AgentHistory::state($n), 'tone' => AgentHistory::STATE_TONES[AgentHistory::state($n)], 'url' => $n->url, 'pinned' => (bool) $n->pinned,
            'meta' => Lang::raw((AgentHistory::AUTHORS[$n->author] ?? $n->author).' · '.(AgentHistory::KINDS[$n->kind] ?? $n->kind).' · '.\Devlib\Locale::dateTime($n->created_at)), 'body' => $n->body], $feed)" empty="No notes yet." />
    @elseif ($ready && $note)
        <x-dl::card :title="Lang::raw($note->title)" icon="fa-clock-rotate-left">
            <x-slot:actions><x-dl::badge :label="AgentHistory::state($note)" :tone="AgentHistory::STATE_TONES[AgentHistory::state($note)]" /></x-slot:actions>
            <x-dl::field.text name="author" label="Author" :value="Lang::raw(AgentHistory::AUTHORS[$note->author] ?? $note->author)" readonly source="devlib.dev_note.author" />
            <x-dl::field.text name="kind" label="Kind" :value="Lang::raw(AgentHistory::KINDS[$note->kind] ?? $note->kind)" readonly source="devlib.dev_note.kind" />
            <x-dl::field.text name="saved" label="Saved" :value="Lang::raw(\Devlib\Locale::dateTime($note->created_at))" readonly source="devlib.dev_note.created_at" />
            {{-- Narasi dibaca penuh (markdown: judul, daftar, tabel); HTML mentah di isi catatan di-escape. --}}
            <div class="dl-prose" style="max-width:none;padding:6px 0">{!! \Illuminate\Support\Str::markdown((string) $note->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
            @if ($note->url)<x-dl::field.text name="url" label="Link" :value="Lang::raw($note->url)" readonly source="devlib.dev_note.url" />@endif
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                @if ($note->url)<x-dl::button variant="secondary" label="Open Link" :href="$note->url" target="_blank" />@endif
                <x-dl::action-form action="{{ route('history.toggle', [$note->id_note, 'pin']) }}"><x-dl::button variant="secondary" type="submit" :label="$note->pinned ? 'Unpin' : 'Pin'" /></x-dl::action-form>
                <x-dl::action-form action="{{ route('history.toggle', [$note->id_note, 'read']) }}"><x-dl::button variant="secondary" type="submit" :label="$note->read_at ? 'Mark Unread' : 'Mark Read'" /></x-dl::action-form>
            </x-dl::action-bar>
        </x-dl::card>
    @elseif ($ready)
        <x-dl::action-form action="{{ route('history.save') }}" data-dl-guard>
            <x-dl::card title="New Note" icon="fa-clock-rotate-left">
                <x-dl::field.text name="title" label="Title" required :value="old('title', '')" source="devlib.dev_note.title" />
                <x-dl::field.radio name="author" label="Author" required :options="AgentHistory::AUTHORS" :value="old('author', 'user')" source="devlib.dev_note.author" />
                <x-dl::field.radio name="kind" label="Kind" required :options="AgentHistory::KINDS" :value="old('kind', 'NOTE')" source="devlib.dev_note.kind" />
                <x-dl::field.text name="body" label="Note" multiline :value="old('body', '')" source="devlib.dev_note.body" />
                <x-dl::field.text name="url" label="Link" :value="old('url', '')" hint="Optional: an Artifact or document link." source="devlib.dev_note.url" />
                <x-dl::action-bar>
                    <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                    <x-dl::button preset="save" type="submit" />
                </x-dl::action-bar>
            </x-dl::card>
        </x-dl::action-form>
    @endif
</div>
@endsection
