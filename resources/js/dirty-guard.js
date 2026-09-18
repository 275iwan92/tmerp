/* =============================================================================
 * Dirty-form guard + tombol Back (dimuat di SETIAP halaman lewat app.js,
 * termasuk halaman di dalam iframe tab shell).
 *
 * - Form dianggap "berubah" kalau user mengetik/memilih di field (bukan field
 *   pencarian/filter) dan layar itu punya tombol Save yang terlihat.
 * - Meninggalkan form yang berubah (klik menu di shell, tutup tab, tombol Back,
 *   link lain di halaman) -> dialog "Simpan perubahan?" Yes / No / Cancel.
 *   Yes = klik tombol Save layar itu, tunggu hasil Livewire; gagal validasi ->
 *   tetap di form. No = tinggalkan tanpa simpan. Cancel = batal pindah.
 * - Layar non-standar (ProDev, kanvas JS) daftar adaptor sendiri lewat
 *   window.tmerpDirty.register({ isDirty, save }).
 * - Tombol Back: [data-back] -> kembali ke layar sebelumnya di frame ini,
 *   lewati entri "new/create" dan URL yg sama; href = fallback kalau tak ada.
 * ============================================================================= */

const SEARCH_LIKE = /search|cari|filter|keyword|perpage|per_page|sort|quick|lookup|query/i;
const SAVE_METHOD = /^(save|store|submit|simpan|post|update|confirm|approve)(draft|all|now|layout|forapproval|tocosting|todireksi|co|doc)?$/i;
const SAVE_TEXT = /^[^\p{L}]*(save|simpan)(\s+(all|draft|semua))?[^\p{L}]*$/iu;
const NEW_ENTRY = /\/(new|create|baru)(\/|$)/i;

const changed = new Set();
let manualDirty = false;
let adapter = null;
let bypass = false;
let lastUserAction = 0;
let dialogOpen = null;
const saveWaiters = [];

const visible = (el) => !!(el && el.isConnected && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');

function methodOf(el) {
    for (const a of el.attributes || []) {
        if (a.name.startsWith('wire:click') || a.name.startsWith('wire:submit')) {
            return a.value.trim().replace(/\(.*$/s, '').replace(/^\$wire\./, '');
        }
    }
    return '';
}

function isSearchLike(el) {
    if (el.type === 'search' || el.closest('[data-dirty-ignore]')) return true;
    let attrs = [el.name, el.id, el.placeholder];
    for (const a of el.attributes) {
        if (a.name.startsWith('wire:model') || a.name.startsWith('x-model')) attrs.push(a.value);
    }
    return attrs.some((v) => v && SEARCH_LIKE.test(v));
}

function findSaveButton() {
    const marked = [...document.querySelectorAll('[data-dirty-save]')].find(visible);
    if (marked) return marked;
    const btns = [...document.querySelectorAll('button, input[type=submit], [role=button], a[wire\\:click]')]
        .filter((b) => visible(b) && !b.disabled && !b.closest('[data-dirty-ignore]'));
    return btns.find((b) => /^(save|simpan|savedraft|saveall)$/i.test(methodOf(b)))
        || btns.find((b) => SAVE_TEXT.test((b.textContent || b.value || '').trim()))
        || btns.find((b) => b.type === 'submit' && b.form && [...b.form.attributes].some((a) => a.name.startsWith('wire:submit')) && SAVE_TEXT.test((b.textContent || '').trim()));
}

function hasChanges() {
    if (adapter) return !!adapter.isDirty();
    if (manualDirty) return true;
    for (const el of changed) {
        if (!el.isConnected) changed.delete(el);
    }
    return changed.size > 0;
}

function isActive() {
    if (bypass) return false;
    if (adapter) return !!adapter.isDirty();
    return hasChanges() && !!findSaveButton();
}

function clear() {
    changed.clear();
    manualDirty = false;
}

['pointerdown', 'keydown'].forEach((t) => document.addEventListener(t, (e) => {
    if (e.isTrusted) lastUserAction = Date.now();
}, true));

['input', 'change'].forEach((t) => document.addEventListener(t, (e) => {
    const el = e.target;
    if (!(el instanceof Element)) return;
    const field = el.matches('input, select, textarea, [contenteditable]') ? el : null;
    if (!field || ['button', 'submit', 'reset'].includes(field.type)) return;
    // Widget kustom (date-picker, searchable-select) memicu event sintetis --
    // dihitung hanya kalau baru saja ada aksi user, bukan saat inisialisasi.
    if (!e.isTrusted && Date.now() - lastUserAction > 1500) return;
    if (isSearchLike(field)) return;
    changed.add(field);
}, true));

function hookLivewire() {
    if (!window.Livewire || !window.Livewire.interceptMessage || hookLivewire.done) return;
    hookLivewire.done = true;
    window.Livewire.interceptMessage(({ message, onSuccess, onError, onFinish }) => {
        const names = [...(message.actions || [])].map((a) => a.name);
        if (!names.length) return;
        const isSave = names.some((n) => SAVE_METHOD.test(n));
        let ok = false;
        onSuccess(({ payload }) => {
            let snap = payload && payload.snapshot;
            if (typeof snap === 'string') { try { snap = JSON.parse(snap); } catch { snap = null; } }
            const errs = snap && snap.memo && snap.memo.errors;
            ok = !(errs && Object.keys(errs).length);
            if (isSave && ok) clear();
        });
        onError(() => { ok = false; });
        onFinish(() => {
            if (!isSave) return;
            saveWaiters.splice(0).forEach((resolve) => resolve(ok));
        });
    });
}
document.addEventListener('livewire:init', hookLivewire);
hookLivewire();

function runSave() {
    if (adapter && adapter.save) return Promise.resolve(adapter.save()).then((r) => r !== false);
    const btn = findSaveButton();
    if (!btn) return Promise.resolve(false);
    return new Promise((resolve) => {
        let settled = false;
        const done = (v) => { if (!settled) { settled = true; resolve(v); } };
        saveWaiters.push(done);
        setTimeout(() => done(!hasChanges()), 20000);
        btn.click();
    });
}

function buildDialog(canSave) {
    const wrap = document.createElement('div');
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-modal', 'true');
    wrap.style.cssText = 'position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.45);font-family:inherit';
    wrap.innerHTML = `
        <div style="background:#fff;color:#1e293b;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,.3);width:min(420px,calc(100vw - 32px));padding:20px 22px">
            <div style="font-weight:700;font-size:15px;margin-bottom:6px">Simpan perubahan?</div>
            <div style="font-size:13px;color:#475569;margin-bottom:6px">Ada perubahan di form ini yang belum disimpan.</div>
            <div data-msg style="font-size:12px;color:#b91c1c;min-height:16px;margin-bottom:10px"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" data-act="yes" style="background:#43508a;color:#fff;border:0;border-radius:8px;padding:7px 18px;font-weight:600;cursor:pointer">Yes</button>
                <button type="button" data-act="no" style="background:#e2e8f0;color:#1e293b;border:0;border-radius:8px;padding:7px 18px;font-weight:600;cursor:pointer">No</button>
                <button type="button" data-act="cancel" style="background:#fff;color:#1e293b;border:1px solid #cbd5e1;border-radius:8px;padding:7px 18px;font-weight:600;cursor:pointer">Cancel</button>
            </div>
        </div>`;
    if (!canSave) {
        const yes = wrap.querySelector('[data-act=yes]');
        yes.disabled = true;
        yes.style.opacity = '.45';
        yes.style.cursor = 'not-allowed';
        wrap.querySelector('[data-msg]').textContent = 'Tombol Save tidak tersedia di layar ini.';
    }
    return wrap;
}

function confirmLeave() {
    if (!isActive()) return Promise.resolve(true);
    if (dialogOpen) return dialogOpen;
    const canSave = !!(adapter ? adapter.save : findSaveButton());
    const wrap = buildDialog(canSave);
    document.body.appendChild(wrap);
    wrap.querySelector(canSave ? '[data-act=yes]' : '[data-act=cancel]').focus();

    dialogOpen = new Promise((resolve) => {
        const finish = (proceed) => {
            document.removeEventListener('keydown', onKey, true);
            wrap.remove();
            dialogOpen = null;
            if (proceed) bypass = true;
            resolve(proceed);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); finish(false); }
        };
        document.addEventListener('keydown', onKey, true);
        wrap.addEventListener('click', async (e) => {
            const act = e.target.closest('[data-act]')?.dataset.act;
            if (!act || e.target.closest('[data-act]').disabled) return;
            if (act === 'cancel') return finish(false);
            if (act === 'no') return finish(true);
            wrap.querySelectorAll('button').forEach((b) => { b.disabled = true; });
            wrap.querySelector('[data-msg]').textContent = 'Menyimpan…';
            wrap.style.visibility = 'hidden';
            const ok = await runSave();
            if (ok) return finish(true);
            // Gagal (validasi/aturan bisnis): tetap di form supaya pesan error terlihat.
            finish(false);
        });
    });
    return dialogOpen;
}

function previousInFrame() {
    const here = location.pathname;
    const nav = window.navigation;
    if (nav && nav.currentEntry && nav.entries) {
        const entries = nav.entries();
        for (let i = nav.currentEntry.index - 1; i >= 0; i--) {
            const u = new URL(entries[i].url);
            if (u.origin !== location.origin || u.pathname === here || NEW_ENTRY.test(u.pathname) || u.pathname === '/menu') continue;
            return { key: entries[i].key };
        }
        return null;
    }
    if (document.referrer) {
        const u = new URL(document.referrer);
        if (u.origin === location.origin && u.pathname !== here && !NEW_ENTRY.test(u.pathname) && u.pathname !== '/menu') {
            return { url: u.href };
        }
    }
    return null;
}

function goBack(fallback) {
    bypass = true;
    const prev = previousInFrame();
    if (prev && prev.key) return window.navigation.traverseTo(prev.key);
    if (prev) { location.href = prev.url; return; }
    // Tanpa riwayat di tab shell & fallback ke shell sendiri -> tutup tab,
    // jangan muat shell di dalam iframe.
    const inShellTab = window.parent !== window && !!window.parent.document?.querySelector?.('iframe[data-tab-code]');
    if (inShellTab && new URL(fallback, location.href).pathname === '/menu') {
        window.parent.postMessage({ tmerp: 'closeTab' }, location.origin);
        return;
    }
    location.href = fallback;
}

document.addEventListener('click', (e) => {
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (e.target.closest('[role=dialog][aria-modal]') && dialogOpen) return;

    const back = e.target.closest('a[data-back]');
    if (back) {
        e.preventDefault();
        e.stopImmediatePropagation();
        confirmLeave().then((ok) => ok && goBack(back.href));
        return;
    }

    const guarded = e.target.closest('[data-dirty-guard]');
    if (guarded && isActive()) {
        e.preventDefault();
        e.stopImmediatePropagation();
        confirmLeave().then((ok) => {
            if (!ok) return;
            clear();
            bypass = false;
            guarded.click();
        });
        return;
    }

    const a = e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
    const href = a.getAttribute('href') || '';
    if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) return;
    if (!isActive()) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    confirmLeave().then((ok) => ok && (location.href = a.href));
}, true);

window.addEventListener('beforeunload', (e) => {
    if (!isActive()) return;
    e.preventDefault();
    e.returnValue = '';
});

window.tmerpDirty = {
    isActive,
    confirmLeave,
    onForm: () => !!(adapter || findSaveButton()),
    mark() { manualDirty = true; },
    clear,
    register(a) { adapter = a; },
    resetBypass() { bypass = false; },
};
