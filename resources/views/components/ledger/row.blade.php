@props(['icon' => '•', 'tone' => 'bl', 'title', 'text' => null])
<div class="lg-lr">
    <div class="lg-lic {{ $tone }}">{{ $icon }}</div>
    <div><b>{{ $title }}</b>@if ($text)<span class="d">{{ $text }}</span>@endif</div>
    <div>{{ $slot }}</div>
</div>
