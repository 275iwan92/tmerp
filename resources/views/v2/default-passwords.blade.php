{{--
 REMARK
 Tujuan       : Setup > Security > Default Passwords (SEMENTARA, tahap UAT): daftar SEMUA pengguna (nama login, nama lengkap, tanggal lahir, no. smartphone, password bawaan) dengan Reset to Default per baris. Password disamarkan; mata / salin / saklar "Show all passwords" mengambilnya dari server per permintaan (tercatat di jejak audit), jadi HTML daftar tidak memuat password. Di HP baris menjadi kartu.
 Sumber hulu  : tm2sys.sec_user lewat App\Services\Security\DefaultPasswordViewer::rows() (default_password disimpan POLOS sampai UAT selesai, keputusan Jo 2026-10-22); aturan reset = SecurityAccountService::resetToDefault.
 Input        : $rows dari route v2.setup.default-passwords.index; session v2_flash, errors, v2_login_card (kartu tampil sekali).
 Output       : POST v2.setup.default-passwords.reveal (JSON id => password) dan POST v2.setup.default-passwords.reset (per baris, dengan konfirmasi). Hanya login di config uat.password_viewers (server: 403 untuk lainnya); respons no-store.
 Dipakai oleh : routes/v2_setup.php (v2.setup.default-passwords.index); menu SET_DEFAULT_PW (migration 2026_10_24_000100).
 Status       : Aktif, SEMENTARA. SETELAH UAT SELESAI: password bawaan kembali dienkripsi dan layar ini dinonaktifkan / dihapus (langkahnya di REMARK DefaultPasswordViewer).
--}}
@extends('v2.layout', ['title' => t('Default Passwords'), 'bodyClass' => 'dl-page-body'])
@section('body')
    @php use Devlib\Locale; @endphp
    <style>
        .pw-bar { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: flex-end; margin-bottom: 12px; }
        .pw-bar .dl-field { margin: 0; min-width: 260px; }
        .pw-count { color: var(--dl-muted); font-size: calc(12px * var(--dl-font-scale)); margin-left: auto; }
        .pw-val { font-family: ui-monospace, Consolas, monospace; letter-spacing: .3px; }
        .pw-cell { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .pw-none, .pw-off { color: var(--dl-muted); font-style: italic; }
        .pw-actions form { margin: 0; }
        @media (max-width: 599.98px) {
            /* HP (phone.md): baris menjadi kartu, semua kontrol di dalam kartu, tidak ada yang menempel di bawah layar */
            .pw-table thead { display: none; }
            .pw-table, .pw-table tbody { display: block; }
            .pw-table tr { display: block; margin: 0 0 10px; border: 1px solid var(--dl-border); border-radius: var(--dl-radius); background: var(--dl-surface); padding: 6px 12px 10px; }
            .pw-table td { display: flex; justify-content: space-between; align-items: center; gap: 12px; border: 0 !important; padding: 5px 0 !important; background: none !important; }
            .pw-table td::before { content: attr(data-label); color: var(--dl-muted); font-size: calc(11px * var(--dl-font-scale)); text-transform: uppercase; letter-spacing: .4px; flex: none; }
            .pw-table td.pw-actions { justify-content: flex-end; padding-top: 8px !important; }
            .pw-table td.pw-actions::before { display: none; }
            .pw-bar .dl-field { min-width: 0; flex: 1 1 100%; }
            .pw-count { margin-left: 0; }
        }
    </style>
    <x-dl::box class="dl-cat" id="pw-screen" data-csrf="{{ csrf_token() }}" data-reveal="{{ route('v2.setup.default-passwords.reveal') }}">
        @if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
        @if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
        <x-dl::alert tone="warn" message="Temporary screen for the UAT stage: default passwords are stored in plain text until UAT ends, and this screen is visible only to the account(s) listed in UAT_PASSWORD_VIEWERS. Every opening, every display of a password and every reset is logged (who, when, which account; never the password). Do not copy this list anywhere else." />

        <x-dl::card title="Default Passwords" icon="fa-key" description="All users with the default password stored for them. Reset to Default sets the account back to that password; the user must change it at the next login.">
            <div class="pw-bar">
                <x-dl::field.text name="pw_q" label="Search" placeholder="Login, name or phone" :case-toggle="false" autocomplete="off" />
                <x-dl::field.toggle name="pw_show_all" label="Show all passwords" on-label="Shown" off-label="Hidden" :value="false" />
                <span class="pw-count" id="pw-count" aria-live="polite"></span>
            </div>

            <div class="dl-table-wrap">
                <table class="dl-table is-striped pw-table">
                    <thead><tr>
                        <th>{{ t('Login') }}</th><th>{{ t('Full name') }}</th><th>{{ t('Date of birth') }}</th><th>{{ t('Smartphone no.') }}</th><th>{{ t('Default password') }}</th><th></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr data-pw-row data-q="{{ mb_strtolower($r['login'].' '.$r['name'].' '.preg_replace('/\D+/', '', (string) $r['phone']).' '.$r['phone']) }}">
                                <td data-label="{{ t('Login') }}"><strong>{{ $r['login'] }}</strong>@unless ($r['active']) <span class="pw-off">({{ t('inactive') }})</span>@endunless</td>
                                <td data-label="{{ t('Full name') }}">{{ $r['name'] }}</td>
                                <td data-label="{{ t('Date of birth') }}">{{ $r['birth_date'] ? Locale::date($r['birth_date']) : '—' }}</td>
                                <td data-label="{{ t('Smartphone no.') }}">{{ $r['phone'] ?: '—' }}</td>
                                <td data-label="{{ t('Default password') }}">
                                    @if ($r['has_default'])
                                        <span class="pw-cell" data-pw-id="{{ $r['id'] }}">
                                            <span class="pw-val">••••••••</span>
                                            <button type="button" class="dl-iconbtn" data-pw-eye title="{{ t('Show / hide password') }}" aria-label="{{ t('Show / hide password') }}"><i class="fa-solid fa-eye"></i></button>
                                            <button type="button" class="dl-iconbtn" data-pw-copy title="{{ t('Copy password') }}" aria-label="{{ t('Copy password') }}"><i class="fa-solid fa-copy"></i></button>
                                        </span>
                                    @else
                                        <span class="pw-none">{{ t('no default password') }}</span>
                                    @endif
                                </td>
                                <td class="pw-actions">
                                    <form method="post" action="{{ route('v2.setup.default-passwords.reset', $r['id']) }}">
                                        @csrf
                                        <x-dl::button variant="secondary" size="sm" icon="fa-rotate-left" label="Reset to Default" type="submit" :disabled="! $r['has_default']"
                                            data-dl-confirm="{{ tf('Set the password of :login back to the stored default password?', ['login' => $r['login']]) }}" />
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        <tr id="pw-empty" hidden><td colspan="6" class="dl-table__empty">{{ t('No user matches the search.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </x-dl::card>
    </x-dl::box>

    @include('v2.partials.login-card')

    <script>
        (function () {
            const root = document.getElementById('pw-screen');
            if (!root) return;
            const MASK = '••••••••';
            const revealUrl = root.dataset.reveal, csrf = root.dataset.csrf;
            const cache = new Map(), shown = new Set();
            const cells = [...root.querySelectorAll('[data-pw-id]')];
            const rows = [...root.querySelectorAll('[data-pw-row]')];
            const q = root.querySelector('input[name=pw_q]'), all = root.querySelector('input[name=pw_show_all]');
            const count = document.getElementById('pw-count'), empty = document.getElementById('pw-empty');
            const idOf = c => +c.dataset.pwId;

            // Password diminta ke server (tercatat di audit); tidak pernah ada di HTML daftar.
            async function load(ids) {
                const need = ids.filter(i => !cache.has(i));
                if (!need.length) return;
                const r = await fetch(revealUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify({ids: need})});
                if (!r.ok) throw new Error('HTTP ' + r.status);
                Object.entries((await r.json()).passwords || {}).forEach(([k, v]) => cache.set(+k, v));
            }
            function paint(c) {
                const id = idOf(c), on = shown.has(id) && cache.has(id);
                c.querySelector('.pw-val').textContent = on ? cache.get(id) : MASK;
                c.querySelector('[data-pw-eye] i').className = 'fa-solid ' + (on ? 'fa-eye-slash' : 'fa-eye');
            }
            function hide(id) { shown.delete(id); cache.delete(id); }
            function fail(e) { count.textContent = '{{ t('Could not load the password.') }} (' + e.message + ')'; }

            async function setAll(on) {
                try {
                    if (on) { await load(cells.map(idOf)); cells.forEach(c => shown.add(idOf(c))); } else { shown.clear(); cache.clear(); }
                } catch (e) { fail(e); all.checked = false; shown.clear(); cache.clear(); }
                cells.forEach(paint);
            }
            all.addEventListener('change', () => setAll(all.checked));

            function copyText(text) {
                if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
                return new Promise((ok, no) => { // cadangan: HTTP biasa / peramban lama tanpa navigator.clipboard
                    const t = document.createElement('textarea');
                    t.value = text; t.setAttribute('readonly', ''); t.style.cssText = 'position:fixed;left:-9999px;top:0';
                    document.body.appendChild(t); t.select();
                    try { document.execCommand('copy') ? ok() : no(new Error('copy')); } catch (e) { no(e); } finally { t.remove(); }
                });
            }

            root.addEventListener('click', async e => {
                const eye = e.target.closest('[data-pw-eye]'), cp = e.target.closest('[data-pw-copy]');
                const cell = (eye || cp)?.closest('[data-pw-id]');
                if (!cell) return;
                const id = idOf(cell);
                try {
                    if (eye) {
                        if (shown.has(id)) { hide(id); all.checked = false; } else { await load([id]); shown.add(id); }
                        paint(cell);
                    } else {
                        await load([id]);
                        await copyText(cache.get(id));
                        if (!shown.has(id)) cache.delete(id);
                        const ic = cp.querySelector('i'); ic.className = 'fa-solid fa-check'; setTimeout(() => { ic.className = 'fa-solid fa-copy'; }, 1200);
                    }
                } catch (err) { fail(err); }
            });

            function filter() {
                const w = q.value.trim().toLowerCase();
                let n = 0;
                rows.forEach(r => { const hit = !w || r.dataset.q.includes(w); r.hidden = !hit; if (hit) n++; });
                empty.hidden = n > 0;
                count.textContent = n + ' / ' + rows.length + ' {{ t('users') }}';
            }
            q.addEventListener('input', filter);
            filter();
        })();
    </script>
@endsection
