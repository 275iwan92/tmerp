@props(['kind' => 'indent', 'label', 'value' => ''])
<div class="lg-srow {{ $kind }}"><span>{{ $label }}</span><span @class(['lg-money' => $kind === 'indent'])>{{ $value }}</span></div>
