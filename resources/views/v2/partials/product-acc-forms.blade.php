{{-- Catat ACC customer + isian Proof Digital untuk 1 product (dipakai di layar ProDev, user 2026-09-29). $doc = ProductMaster::doc(). --}}
@php
    use App\Models\Master\ProdProductAcc;
    use App\Support\V2\Src;
@endphp
<div class="mp-noprint">
        <x-dl::card title="Isian Proof Digital" description="Designer & design date are printed in the DRAWING BY / DATE block.">
            <x-dl::action-form action="{{ route('v2.product.proof', $doc['id']) }}">
                <x-dl::form.row cols="3">
                    <x-dl::field.text name="drawing_by" label="Drawing by (desainer)" :value="old('drawing_by', $doc['drawing_by'])" :source="Src::of('tm2mst.prod_product.drawing_by')" />
                    <x-dl::field.date name="design_date" label="Design date" :value="old('design_date', optional($doc['design_date'])->toDateString())" :source="Src::of('tm2mst.prod_product.design_date')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::card>

        <x-dl::card title="Catat ACC customer" description="So far via WhatsApp / e-mail — record the date & time, approver, channel and evidence.">
            <x-dl::action-form action="{{ route('v2.product.acc', $doc['id']) }}" enctype="multipart/form-data">
                <x-dl::form.row cols="3">
                    <x-dl::field.select name="acc_type" label="ACC Type" required :options="ProdProductAcc::TYPES" :value="old('acc_type', 'DIGITAL')" :placeholder="null" :source="Src::of('tm2mst.prod_product_acc.acc_type')" />
                    <x-dl::field.date-time name="acc_at" label="ACC date & time" required :value="old('acc_at', now()->format('Y-m-d H:i'))" :source="Src::of('tm2mst.prod_product_acc.acc_at')" />
                    <x-dl::field.text name="approved_by" label="Approved by (customer)" required :value="old('approved_by')" placeholder="mis. Pak Habibi" :source="Src::of('tm2mst.prod_product_acc.approved_by')" />
                </x-dl::form.row>
                <x-dl::form.row cols="3">
                    <x-dl::field.select name="channel" label="Saluran" required :options="ProdProductAcc::CHANNELS" :value="old('channel', 'WA')" :placeholder="null" :source="Src::of('tm2mst.prod_product_acc.channel')" />
                    <x-dl::field.upload name="evidence" label="Bukti (screenshot / PDF)" accept=".jpg,.jpeg,.png,.pdf" :source="Src::of('tm2mst.prod_product_acc.evidence_path')" />
                    <x-dl::field.text name="note" label="Notes" :value="old('note')" :source="Src::of('tm2mst.prod_product_acc.note')" />
                </x-dl::form.row>
                <x-dl::action-bar><x-dl::button preset="save" /></x-dl::action-bar>
            </x-dl::action-form>
            <x-dl::table :columns="['type' => 'Jenis', 'at' => 'Tanggal & jam', 'by' => 'Di-ACC oleh', 'channel' => 'Saluran', 'note' => 'Catatan', 'evidence' => 'Bukti']"
                :rows="array_map(fn ($a) => ['type' => ProdProductAcc::TYPES[$a['type']] ?? $a['type'], 'at' => $a['at'], 'by' => $a['by'], 'channel' => $a['channel'], 'note' => $a['note'] ?? '—', 'evidence' => $a['evidence_name'] ?? '—'], $doc['accs'])"
                empty="Belum ada ACC tercatat." source="{{ Src::of('tm2mst.prod_product_acc') }}" />
        </x-dl::card>
    </div>
