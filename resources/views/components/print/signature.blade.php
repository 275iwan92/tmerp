@props(['role', 'company' => null, 'name' => null, 'sub' => null])
<div class="print-sign-cell">
    @if ($company)<div class="co">{{ $company }}</div>@endif
    <div class="line"></div>
    <div class="rol">{{ $role }}</div>
    @if ($name)<div class="nm">{{ $name }}</div>@endif
    @if ($sub)<div class="sub">{{ $sub }}</div>@endif
</div>
