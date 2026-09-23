<div class="dl-kvedit" data-dl-kv data-name="{{ $name }}">
    <table class="dl-kvedit__table">
        <thead><tr><th>{{ $keyLabel }}</th><th>{{ $valueLabel }}</th><th></th></tr></thead>
        <tbody data-dl-kv-body>
            @foreach ((array) $value as $k => $v)
                <tr><td><input type="text" name="{{ $name }}[{{ $loop->index }}][key]" value="{{ $k }}"></td><td><input type="text" name="{{ $name }}[{{ $loop->index }}][value]" value="{{ $v }}"></td><td><button type="button" data-dl-kv-x title="Hapus baris">&times;</button></td></tr>
            @endforeach
        </tbody>
    </table>
    <button type="button" class="dl-kvedit__add" data-dl-kv-add @disabled($disabled || $readonly)>+ Tambah baris</button>
</div>
