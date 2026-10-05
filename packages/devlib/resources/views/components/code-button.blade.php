<div class="dl-code" {!! $dl() !!} data-dl-code>
    <button type="button" class="dl-code__btn" data-dl-code-open title="{{ \Devlib\Lang::text('View this screen\'s code') }}"><i class="fa-solid fa-code"></i></button>
    <div class="dl-mov" data-dl-code-modal>
        <div class="dl-modal dl-code__modal">
            <div class="dl-modal__head">
                <h3><i class="fa-solid fa-code"></i> {{ \Devlib\Lang::text('This screen\'s code') }} <small>{{ $lines() }} {{ \Devlib\Lang::text('lines') }}</small></h3>
                <button type="button" class="dl-modal__x" data-dl-code-close>&times;</button>
            </div>
            <div class="dl-code__tabs">
                <button type="button" class="is-active" data-tab="code">{{ \Devlib\Lang::text('Usage code') }}</button>
                <button type="button" data-tab="view">{{ \Devlib\Lang::text('Component layout') }}</button>
                <button type="button" data-tab="props">{{ \Devlib\Lang::text('Component properties') }}</button>
                @if ($mockupUrl)<a href="{{ $mockupUrl }}" target="_blank">{{ \Devlib\Lang::text('Original mockup') }} ↗</a>@endif
            </div>
            <div class="dl-code__pane is-active" data-pane="code">
                <div class="dl-code__file">{{ $file }}</div>
                <pre class="dl-code__pre"><code>{{ $code }}</code></pre>
            </div>
            <div class="dl-code__pane" data-pane="view">
                @foreach ($info() as $c)
                    @if ($c['view'])
                        <div class="dl-code__tag">&lt;{{ $c['tag'] }}&gt; <small>{{ count(preg_split('/\R/', rtrim($c['view']))) }} {{ \Devlib\Lang::text('lines') }}</small></div>
                        <pre class="dl-code__pre dl-code__pre--sm"><code>{{ $c['view'] }}</code></pre>
                    @endif
                @endforeach
            </div>
            <div class="dl-code__pane" data-pane="props">
                @foreach ($info() as $c)
                    <div class="dl-code__comp">
                        <div class="dl-code__tag">&lt;{{ $c['tag'] }}&gt;</div>
                        <div class="dl-code__lineage">{{ \Devlib\Lang::t('dev_class') }}: {{ $c['lineage'] }}</div>
                        @if ($c['doc'])<div class="dl-code__doc">{{ $c['doc'] }}</div>@endif
                        <table class="dl-code__table">
                            <thead><tr><th>{{ \Devlib\Lang::text('Property') }}</th><th>{{ \Devlib\Lang::text('Type') }}</th><th>Default</th><th>{{ \Devlib\Lang::text('Origin') }}</th></tr></thead>
                            <tbody>
                                @foreach ($c['props'] as $p)
                                    <tr><td>{{ $p['name'] }}</td><td>{{ $p['type'] }}</td><td>{{ $p['default'] }}</td><td>{{ \Devlib\Lang::text($p['inherited'] ? 'parent' : 'own') }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($c['methods'])<div class="dl-code__doc"><b>{{ \Devlib\Lang::text('Methods') }}:</b> {{ implode(' · ', $c['methods']) }}</div>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
