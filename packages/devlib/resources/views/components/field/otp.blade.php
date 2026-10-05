<div class="dl-otp" data-dl-otp>
    <input type="hidden" name="{{ $name }}" data-dl-value>
    @for ($i = 0; $i < $length; $i++)
        <input type="text" inputmode="numeric" maxlength="1" class="dl-otp__box" @if ($i === 0) id="{{ $uid }}" @endif @disabled($disabled) @readonly($readonly)>
    @endfor
</div>
