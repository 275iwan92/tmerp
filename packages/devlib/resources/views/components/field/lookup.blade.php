@php($cur = $current())
<div class="dl-dd dl-lookup" data-dl-lookup data-fill="{{ json_encode($fill) }}" data-rows="{{ json_encode($def['rows']) }}" data-key="{{ $def['key'] }}" data-label="{{ $def['label'] }}" @if ($create) data-create @endif @if ($url()) data-url="{{ $url() }}" data-columns="{{ json_encode(array_keys($def['columns'])) }}" @endif>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-value>
    <button type="button" id="{{ $uid }}" class="dl-dd__btn" data-dl-dd-toggle @disabled($disabled || $readonly)>
        <span data-dl-dd-text>{{ $cur[$def['label']] ?? ($placeholder ?? 'Pilih...') }}</span><span><i class="fa-solid fa-magnifying-glass"></i></span>
    </button>
    <div class="dl-dd__panel dl-lookup__panel" data-dl-dd-panel>
        <div class="dl-dd__search"><input type="text" placeholder="{{ $url() ? 'Ketik min. 2 huruf untuk mencari '.strtolower($label).'...' : 'Cari '.strtolower($label).'...' }}" data-dl-dd-search></div>
        <table class="dl-lookup__table">
            <thead><tr>@foreach ($def['columns'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            <tbody data-dl-dd-list>
                @foreach ($def['rows'] as $i => $r)
                    <tr class="dl-dd__item" data-i="{{ $i }}">@foreach (array_keys($def['columns']) as $c)<td>{{ $r[$c] ?? '' }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
        @if ($create)<button type="button" class="dl-lookup__create" data-dl-lookup-create hidden>+ Tambah baru: <b data-dl-lookup-new></b></button>@endif
    </div>
</div>
