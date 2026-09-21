@props(['num', 'done' => false, 'title', 'text' => null])
<div @class(['lg-step', 'done' => $done])>
    <div class="lg-sn">{{ $done ? '✓' : $num }}</div>
    <div><b>{{ $title }}</b>@if ($text)<span class="d">{{ $text }}</span>@endif</div>
    <div class="lg-actions">{{ $slot }}</div>
</div>
