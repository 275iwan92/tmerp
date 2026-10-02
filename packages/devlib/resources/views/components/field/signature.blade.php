<div class="dl-sign {{ $readonly || $disabled ? 'is-readonly' : '' }}" data-dl-sign style="--dl-sign-h:{{ $height }}px">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-value>
    <div class="dl-sign__pad">
        <canvas id="{{ $uid }}" aria-label="{{ \Devlib\Lang::text('Signature area') }}"></canvas>
        <span class="dl-sign__ph" data-dl-sign-ph><i class="fa-solid fa-signature"></i> {{ $placeholder ?? \Devlib\Lang::text('Sign here') }}</span>
        <span class="dl-sign__line"></span>
    </div>
    <div class="dl-sign__bar">
        <span class="dl-sign__status" data-dl-sign-status>{{ \Devlib\Lang::t('sg_unsigned') }}</span>
        <x-dl::button variant="ghost" size="sm" icon="undo" label="Undo" data-dl-sign-undo />
        <x-dl::button variant="ghost" size="sm" icon="eraser" label="Clear" data-dl-sign-clear />
        <x-dl::button variant="ghost" size="sm" icon="download" label="PNG" data-dl-sign-png />
    </div>
</div>
