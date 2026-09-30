@php
    // Form Approval (user 2026-09-30): Draft -> Confirmed -> Approval L1..Ln (Approve / Reject) -> Approved -> Closed.
    // Cancel hanya oleh user pembuat, kapan saja sebelum Closed. Approve/Reject hanya oleh pemeriksa tingkat berjalan (level >= tahap),
    // pemeriksa tidak bisa input. Rejected = berhenti (Duplicate utk mulai ulang); tanpa Back to Draft setelah
    // masuk approval. Closed otomatis dari proses lanjutan (di sini hanya pintasan "Lihat status"). Status baru ditentukan "server" (blok ini).
    $me = 'pr';
    $levels = 2;
    $roles = ['maker' => 'Pembuat (data-entry)', 'l1' => 'Pemeriksa L1 (supervisor)', 'l2' => 'Pemeriksa L2 (manager)', 'super-admin' => 'super-admin (UAT)'];
    $as = array_key_exists(request('as'), $roles) ? request('as') : 'maker';
    $act = request('doc') === $me ? request('_action') : null;
    $level = (int) request($me.'_level', 0);
    $status = request()->boolean($me.'_new') ? 'new' : request($me.'_status', 'draft');
    if ($act) {
        [$status, $level] = match ($act) {
            'confirm' => ['approval', 1],
            'approve' => $level < $levels ? ['approval', $level + 1] : ['approved', $level],
            'reject' => ['rejected', $level],
            'hold' => ['hold', $level],
            'cancel' => ['cancelled', in_array($status, ['approval', 'hold'], true) ? $level : 0],
            'draft' => ['draft', 0],
            'save' => [$status === 'new' ? 'draft' : $status, $level],
            'delete', 'duplicate' => ['new', 0],
            default => [$status, $level],
        };
    }
    $roleLevel = ['maker' => 0, 'l1' => 1, 'l2' => 2, 'super-admin' => 9][$as];
    $canApprove = in_array($status, ['approval', 'hold'], true) && $roleLevel >= $level;
    $canInput = in_array($as, ['maker', 'super-admin'], true);
    $canReopen = false;  // tidak ada Back to Draft setelah masuk approval
    $isNew = $status === 'new';
    $here = url()->current();
    $keep = fn (array $x = []) => $here.'?'.http_build_query($x + ['as' => $as, $me.'_status' => $status === 'new' ? 'draft' : $status, $me.'_level' => $level]).'#form-approval';
    $now = now()->format('Y-m-d H:i:s');
    $why = request('_reason');
    $log = $isNew ? [] : array_values(array_filter([
        $act && ! in_array($act, ['save', 'delete', 'duplicate'], true) ? ['at' => $now, 'by' => ['maker' => 'yuna', 'l1' => 'andi (spv)', 'l2' => 'nisa (mgr)', 'super-admin' => 'johiwan'][$as], 'action' => ['confirm' => 'Confirmed', 'approve' => 'Approved', 'reject' => 'Rejected', 'hold' => 'Hold', 'cancel' => 'Cancelled', 'draft' => 'Back to Draft'][$act] ?? ucfirst($act), 'note' => trim((in_array($act, ['approve', 'reject', 'hold'], true) ? 'Level '.$level.'. ' : '').($why ?? ''))] : null,
        ['at' => '2026-09-24 09:40:00', 'by' => 'yuna', 'action' => 'Saved', 'note' => null],
        ['at' => '2026-09-24 08:12:00', 'by' => 'yuna', 'action' => 'Created', 'note' => null],
    ]));
@endphp
<div class="dl-row" style="margin-bottom:6px">
    <span class="dl-note" style="margin:0">Simulasi login:</span>
    @foreach ($roles as $rk => $rl)
        <x-dl::button size="sm" :variant="$rk === $as ? 'primary' : 'secondary'" :label="$rl" :href="$here.'?'.http_build_query(['as' => $rk, $me.'_status' => $isNew ? 'draft' : $status, $me.'_level' => $level]).'#form-approval'" />
    @endforeach
</div>
<div class="dl-row" style="margin-bottom:8px">
    <span class="dl-note" style="margin:0">Lihat status:</span>
    @foreach (['draft' => ['Draft', 0], 'approval1' => ['Waiting L1', 1], 'approval2' => ['Waiting L2', 2], 'hold' => ['Hold L1', 1], 'rejected' => ['Rejected L1', 1], 'approved' => ['Approved', 2], 'cancelled' => ['Cancelled', 0], 'closed' => ['Closed', 2]] as $sk => [$sl, $sv])
        <x-dl::button size="sm" variant="secondary" :label="$sl" :href="$here.'?'.http_build_query(['as' => $as, $me.'_status' => str_starts_with($sk, 'approval') ? 'approval' : $sk, $me.'_level' => $sv]).'#form-approval'" />
    @endforeach
</div>
<x-dl::form :title="$isNew ? 'Purchase Requisition · New' : 'Purchase Requisition · PR-2609-0012'" subtitle="Divisi Offset Printing"
    panel="top" kind="document" :status="$status" :pipeline="true" :approval-levels="$levels" :approval-at="$level" :can-approve="$canApprove"
    :can-input="$canInput" :can-reopen="$canReopen" :log="$log"
    :action="$here.'#form-approval'" method="GET" :new-url="$here.'?'.$me.'_new=1&as='.$as.'#form-approval'"
    :hidden="['doc' => $me, 'as' => $as, $me.'_status' => $status, $me.'_level' => $level]"
    :tabs="['general' => 'General', 'files' => 'Attachments & Notes']"
    :created-by="$isNew ? null : 'yuna'" :created-at="$isNew ? null : '2026-09-24 08:12:00'"
    :updated-by="$isNew ? null : ($act ? 'johiwan' : 'yuna')" :updated-at="$isNew ? null : ($act ? $now : '2026-09-24 09:40:00')">
    <x-slot:strip>
        <div class="dl-strip__kv dl-strip__doc"><span>No. PR</span><b>{{ $isNew ? '(otomatis)' : 'PR-2609-0012' }}</b></div>
        <x-dl::field.date name="pr_date" label="Tanggal PR" :value="$isNew ? now()->format('Y-m-d') : '2026-09-24'" required />
        <x-dl::field.select name="divisi" label="Divisi" :options="['Offset Printing' => 'Offset Printing', 'Digital Printing' => 'Digital Printing', 'Gudang' => 'Gudang']" :value="$isNew ? null : 'Offset Printing'" required />
        <div class="dl-strip__kv dl-strip__grand"><span>Est. Total</span><b data-l-val="pr_lines:grand">Rp 0</b></div>
    </x-slot:strip>
    <x-slot:general>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="requester" label="Pemohon" :options="['Yuna' => 'Yuna', 'Andi' => 'Andi']" :value="$isNew ? null : 'Yuna'" required />
            <x-dl::field.date name="need_date" label="Dibutuhkan Tanggal" :value="$isNew ? null : '2026-10-01'" placeholder="—" required />
            <x-dl::field.text name="reason" label="Justifikasi" :value="$isNew ? null : 'Stok kertas & tinta untuk CO-2609-0031.'" />
        </x-dl::form.row>
    </x-slot:general>
    <x-slot:files>
        <x-dl::form.row>
            <x-dl::field.upload name="pr_file" label="Lampiran" />
            <x-dl::field.text name="note" label="Note Internal" multiline />
        </x-dl::form.row>
    </x-slot:files>
    <x-slot:detail>
        <x-dl::lines name="pr_lines" title="Details" add-label="+ Add Line" :rows="$isNew ? [] : [
                ['item' => 'RM-IVR-0310', 'item_label' => 'Ivory 310 gsm', 'qty' => 70, 'uom' => 'LBR', 'price' => 4200],
                ['item' => 'RM-TNT-0045', 'item_label' => 'Tinta Offset Magenta', 'qty' => 20, 'uom' => 'KG', 'price' => 192000],
            ]" :min-rows="$isNew ? 1 : 0" :readonly="in_array($status, ['approval', 'hold', 'approved', 'rejected', 'cancelled', 'closed'], true) || ! $canInput"
            :totals="['of' => 'subtotal']"
            :columns="[
                ['key' => 'item', 'label' => 'Item', 'type' => 'lookup', 'lookup' => 'item', 'required' => true, 'width' => '260px'],
                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '100px'],
                ['key' => 'uom', 'label' => 'Satuan', 'type' => 'select', 'options' => ['PCS', 'LBR', 'RIM', 'KG', 'ROLL'], 'width' => '90px'],
                ['key' => 'price', 'label' => 'Est. Harga', 'type' => 'money', 'width' => '130px'],
                ['key' => 'subtotal', 'label' => 'Est. Subtotal', 'type' => 'money', 'formula' => 'qty * price', 'sum' => true, 'width' => '140px'],
            ]" />
    </x-slot:detail>
</x-dl::form>
<p class="dl-note">Alur: <b>Draft</b> → Confirm → <b>Approval 1</b> → <b>Approval 2</b> → <b>Approved</b> → <b>Closed</b> (otomatis dari proses lanjutan). Approve / Reject / <b>Hold</b> hanya muncul untuk pemeriksa tingkat yang sedang menunggu (coba "Simulasi login"); Hold menunda, lalu tetap diputuskan Approve/Reject. <b>Reject</b> = berhenti (pembuat pakai Duplicate). Tidak ada Back to Draft setelah masuk approval. <b>Cancel</b> hanya oleh pembuat, wajib alasan, kapan saja sebelum Closed. <b>Closed</b> otomatis saat PR diteruskan jadi PO. Semua langkah tercatat di tab History.</p>
