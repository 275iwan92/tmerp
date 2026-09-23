<div class="dl-code" {!! $dl() !!} data-dl-code>
    <button type="button" class="dl-code__btn" data-dl-code-open title="Lihat kode layar ini"><i class="fa-solid fa-code"></i></button>
    <div class="dl-mov" data-dl-code-modal>
        <div class="dl-modal dl-code__modal">
            <div class="dl-modal__head">
                <h3><i class="fa-solid fa-code"></i> Kode layar ini <small>{{ $lines() }} baris</small></h3>
                <button type="button" class="dl-modal__x" data-dl-code-close>&times;</button>
            </div>
            <div class="dl-code__tabs">
                <button type="button" class="is-active" data-tab="code">Kode pemakaian</button>
                <button type="button" data-tab="view">Susunan komponen</button>
                <button type="button" data-tab="props">Properti komponen</button>
                @if ($mockupUrl)<a href="{{ $mockupUrl }}" target="_blank">Mockup asli ↗</a>@endif
            </div>
            <div class="dl-code__pane is-active" data-pane="code">
                <div class="dl-code__file">{{ $file }}</div>
                <pre class="dl-code__pre"><code>{{ $code }}</code></pre>
            </div>
            <div class="dl-code__pane" data-pane="view">
                @foreach ($info() as $c)
                    @if ($c['view'])
                        <div class="dl-code__tag">&lt;{{ $c['tag'] }}&gt; <small>{{ count(preg_split('/\R/', rtrim($c['view']))) }} baris</small></div>
                        <pre class="dl-code__pre dl-code__pre--sm"><code>{{ $c['view'] }}</code></pre>
                    @endif
                @endforeach
            </div>
            <div class="dl-code__pane" data-pane="props">
                @foreach ($info() as $c)
                    <div class="dl-code__comp">
                        <div class="dl-code__tag">&lt;{{ $c['tag'] }}&gt;</div>
                        <div class="dl-code__lineage">turunan: {{ $c['lineage'] }}</div>
                        @if ($c['doc'])<div class="dl-code__doc">{{ $c['doc'] }}</div>@endif
                        <table class="dl-code__table">
                            <thead><tr><th>Properti</th><th>Tipe</th><th>Default</th><th>Asal</th></tr></thead>
                            <tbody>
                                @foreach ($c['props'] as $p)
                                    <tr><td>{{ $p['name'] }}</td><td>{{ $p['type'] }}</td><td>{{ $p['default'] }}</td><td>{{ $p['inherited'] ? 'induk' : 'sendiri' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
