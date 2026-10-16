{{-- REMARK
     Tujuan       : Layar Setup > Others > Old Docs Library bergaya artikel blog: indeks artikel dengan pencarian isi, lalu satu artikel dibaca penuh.
     Sumber hulu  : tm2sys.sys_md_library lewat DocsLibraryLookup (feed() untuk indeks, article() untuk satu artikel).
     Input        : ?q= (kata dicari), ?doc= (id artikel); chip jenis dokumen dan kotak cari cepat di indeks.
     Output       : Hanya tampilan baca; tidak ada yang tersimpan.
     Dipakai oleh : Menu SET_DOCS_LIBRARY (DocsLibraryLookup) dan SET_GUIDES (GuidesLookup): keduanya menyediakan feed(), article(), guide(), url().
     Status       : Aktif (ddt*). --}}
@php
    use App\Support\V2\Crumb;

    $grid = $screen['grid'];
    $doc = (int) request('doc');
    $q = trim((string) request('q'));
    $article = $doc ? $grid->article($doc) : null;
@endphp

@if ($article)
    @php
        ob_start();
    @endphp
    <x-dl::button size="sm" variant="secondary" icon="fa-arrow-left" label="All articles" :href="$grid->url()" />
    @php
        Crumb::actions(ob_get_clean());
    @endphp
    <x-dl::review-feed :posts="[$article]" :search="false" :source="'sys_md_library'" />
@else
    @php
        $feed = $grid->feed($q);
    @endphp
    @if ($guide = $grid->guide())<x-dl::alert tone="info" :message="$guide" />@endif
    <x-dl::action-form method="get" action="{{ $grid->url() }}">
        <x-dl::field.text name="q" label="Search inside documents" :value="$q" :case-toggle="false" />
        <x-dl::button size="sm" icon="fa-magnifying-glass" label="Search" type="submit" />
    </x-dl::action-form>
    <x-dl::review-feed :posts="$feed['posts']" :filters="$feed['filters']" :source="$feed['source']" :quick="false" empty="No documents found." />
@endif
