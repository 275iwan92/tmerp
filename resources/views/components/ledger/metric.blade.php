@props(['label', 'icon' => '▤', 'value', 'pill' => null, 'tone' => 'good', 'sub' => null])
<div class="lg-card">
    <div class="lg-ct">{{ $label }} <span class="lg-ci">{{ $icon }}</span></div>
    <div class="lg-value">{{ $value }}</div>
    @if ($pill)<span class="lg-pill lg-{{ $tone }}">{{ $pill }}</span>@endif
    @if ($sub)<span class="lg-sub">{{ $sub }}</span>@endif
</div>
