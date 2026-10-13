{{--
 REMARK
 Tujuan       : Tampilan layar Production Output: kepala proses, Master Card satu baris (dieline + artwork, target, info job), timer SETUP dan PRODUCTION OUTPUT,
                LIST OF JOBS (tanpa grid), dan QC input GOOD / BAD dengan TOTAL (GOOD + BAD) batang proporsi GOOD / BAD, dan panel alasan BAD di bawah kotak BAD (multi baris, jumlah = BAD).
 Sumber hulu  : $desk dari ProductionOutput::desk(); angka dinamis digambar output-desk.js.
 Input        : $desk.
 Output       : Markup bertanda data-od-*; JS mengisi angka, timer dan status.
 Dipakai oleh : MockupParts\Components\Production\OutputDesk.
 Status       : Aktif di mockup (ddt*). Preview acuan: artifact "Entry Output Produksi".
--}}
@php
    use Devlib\Lang;
    $t = fn ($s) => Lang::text($s);
    $icons = [
        'play' => '<svg class="mp-od-ic" viewBox="0 0 16 16"><path d="M4 2l10 6-10 6z" fill="var(--mp-od-play)"/></svg>',
        'pause' => '<svg class="mp-od-ic" viewBox="0 0 16 16"><path d="M4 2h3v12H4zM9 2h3v12H9z" fill="var(--mp-od-pause)"/></svg>',
        'stop' => '<svg class="mp-od-ic" viewBox="0 0 16 16"><rect x="3" y="3" width="10" height="10" fill="var(--mp-od-stop)"/></svg>',
    ];
@endphp
<div {{ $attributes->class('mp-od') }} {!! $dl() !!}>
@if (isset($desk['empty']))
    <x-dl::alert tone="info" :message="$desk['empty']" />
@else
    <div class="mp-od__panel" data-mp-output data-desk="{{ json_encode($desk) }}">
        <div class="mp-od__head">
            <svg class="mp-od-ic" style="width:40px;height:40px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>
            <b>{{ $desk['title'] }}</b><small>{{ $desk['machine'] }}</small>
        </div>

        <section class="mp-od__mcard">
            <div class="mp-od__mch"><b>MASTER CARD</b><small>Artwork + Dieline</small></div>
            <div class="mp-od__mcb">
                <figure>
                    @if ($desk['card']['image'])
                        <img src="{{ $desk['card']['image'] }}" alt="Dieline + artwork" data-od-zoom>
                        <figcaption>{{ $t('click to zoom') }}</figcaption>
                    @else
                        <div class="mp-od__nodie">{{ $t('This job has no dieline and artwork in its Master Card yet.') }}</div>
                    @endif
                </figure>
                @php $f = collect($desk['card']['info'])->mapWithKeys(fn ($r) => [$r[0] => $r[1]]); @endphp
                <div class="mp-od__rt">
                    <div class="mp-od__tgt">
                        <div class="mp-od__tr"><span class="mp-od__tl">{{ $t('TARGET PER HOUR') }}</span><span><span class="mp-od__tv" data-od="perhour"></span> <span class="mp-od__tu" data-od="perhour-u"></span></span></div>
                        <div class="mp-od__tr"><span class="mp-od__tl">{{ $t('TARGET QTY') }}</span><span><span class="mp-od__qv" data-od="qty"></span> <span class="mp-od__tu">{{ $desk['target']['uom'] }}</span></span></div>
                        <div class="mp-od__tr"><span class="mp-od__tl">{{ $t('TARGET DURATION') }}</span><span class="mp-od__dv" data-od="duration"></span></div>
                    </div>
                    <div class="mp-od__facts">
                        <div class="mp-od__size"><span class="mp-od__tl">{{ $t('SIZE') }}</span> <x-dl::box as="b" class="mp-od__hl">{{ $f['P × L × T'] ?? '—' }}</x-dl::box> <span>{{ $t('Flat') }} <b>{{ $f['Datar'] ?? '—' }}</b></span></div>
                        <dl class="mp-od__info">
                            <dt>{{ $t('Job') }}</dt><dd>{{ $f['Job'] ?? '—' }} · {{ $f['Product'] ?? '—' }}</dd>
                            <dt>{{ $t('Qty Order') }}</dt><dd>{{ $f['Qty Order'] ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        @foreach (['setup' => 'SETUP TIME', 'prod' => 'PRODUCTION OUTPUT'] as $key => $title)
            <div class="mp-od__timer mp-od__timer--{{ $key }}" data-od-timer="{{ $key }}">
                <span class="mp-od__tn">{{ $title }}</span>
                <span class="mp-od__ts" data-od="state"></span>
                <span class="mp-od__tc" data-od="clock">00:00:00</span>
                <span class="mp-od__btns">
                    @foreach (['play', 'pause', 'stop'] as $a)
                        <button type="button" data-od-act="{{ $a }}" aria-label="{{ ucfirst($a) }} {{ $title }}">{!! $icons[$a] !!}</button>
                    @endforeach
                </span>
                <span class="mp-od__tt" data-od="times"></span>
            </div>
        @endforeach

        <div class="mp-od__low">
            <div class="mp-od__box">
                <h3>{{ $t('List of Jobs') }}</h3>
                <div class="mp-od__tw">
                    <table>
                        <thead><tr><th>No</th><th>Job No</th><th>Product</th><th>Customer</th><th>Qty</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($desk['jobs'] as $j)
                                <tr class="mp-od__row {{ $j['current'] ? 'is-sel' : '' }}" tabindex="0" data-od-job="{{ $j['url'] }}" data-od-status="{{ $j['status'] }}">
                                    <td>{{ $j['no'] }}</td><td>{{ $j['wo'] }}</td><td>{{ $j['product'] }}</td><td>{{ $j['customer'] }}</td><td>{{ $j['qty'] }}</td>
                                    <td><span class="mp-od__dot mp-od__dot--{{ $j['status'] }}"></span><span data-od="job-status"></span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mp-od__box mp-od__qc">
                <h3>{{ $t('QC — Input Result Qty') }} ({{ $desk['target']['uom'] }})</h3>
                @foreach (['good' => ['✔ GOOD', 100], 'bad' => ['✖ BAD', 10]] as $k => [$label, $step])
                    <div class="mp-od__qb mp-od__qb--{{ $k }}">
                        <b>{{ $label }}</b>
                        <div class="mp-od__qrow">
                            <div class="mp-od__step">
                                <button type="button" data-od-step="{{ $k }}" data-d="-{{ $step }}" aria-label="−{{ $step }} {{ $k }}">−</button>
                                <input type="number" min="0" inputmode="numeric" value="0" data-od-in="{{ $k }}" aria-label="Qty {{ $k }}">
                                <button type="button" data-od-step="{{ $k }}" data-d="{{ $step }}" aria-label="+{{ $step }} {{ $k }}">+</button>
                            </div>
                            <span>{{ $desk['target']['uom'] }}</span>
                        </div>
                    </div>
                @endforeach
                <div class="mp-od__bar" aria-hidden="true"><i class="mp-od__bar-good" data-od="bar-good"></i><i class="mp-od__bar-bad" data-od="bar-bad"></i></div>
                <x-dl::box as="p" class="mp-od__total">{{ $t('TOTAL') }} <b data-od="total">0</b> {{ $desk['target']['uom'] }}
                    <x-dl::badge label="GOOD 0%" tone="success" data-od="pct-good" /> <x-dl::badge label="BAD 0%" tone="danger" data-od="pct-bad" /></x-dl::box>
                <div class="mp-od__reasons" data-od-reasons hidden>
                    <div data-od-bad-rows></div>
                    <div class="mp-od__reasons-foot">
                        <x-dl::button size="sm" variant="secondary" icon="fa-plus" label="Add reason row" type="button" data-od-bad-add />
                        <span class="mp-od__total">{{ $t('Reasons') }} <b data-od="bad-total">0</b> / <b data-od="bad-want">0</b> {{ $desk['target']['uom'] }}</span>
                    </div>
                </div>
                <button type="button" class="mp-od__save" data-od-save>{{ $t('SAVE RESULT') }}</button>
                <p class="mp-od__sum" data-od="sum"></p>
            </div>
        </div>

        <div class="mp-od__zoom" data-od-zoomlayer hidden><img alt="Dieline + artwork"></div>
    </div>
@endif
</div>
