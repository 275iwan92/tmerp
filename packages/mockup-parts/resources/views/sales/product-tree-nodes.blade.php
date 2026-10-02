@if ($nodes)
    <ul class="mp-ptree">
        @foreach ($nodes as $n)
            <li>
                <button type="button" class="mp-ptree__n {{ $active === $n['id'] ? 'is-on' : '' }}" data-pm-node="{{ $n['id'] }}">
                    <span class="mp-ptree__ic">{{ $n['kind'] === 'ASSEMBLY' ? 'R' : mb_substr(preg_replace('/^Part\s+/i', '', $n['name']), 0, 1) }}</span>
                    <span>{{ $n['name'] }}<small>{{ $n['kind'] === 'ASSEMBLY' ? t('Assembly').' · '.($n['join'] ?? t('join not filled in yet')) : t('Component').($n['qty_in_parent'] != 1 ? ' × '.$n['qty_in_parent'] : '') }}</small></span>
                </button>
                @include('mp::sales.product-tree-nodes', ['nodes' => $n['children'], 'active' => $active])
            </li>
        @endforeach
    </ul>
@endif
