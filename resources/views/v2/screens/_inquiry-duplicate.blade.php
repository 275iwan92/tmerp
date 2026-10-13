{{--
 REMARK
 Tujuan       : Modal Duplicate Inquiry: menyalin spesifikasi inquiry ke customer lain sebagai NEW DRAFT baru.
 Sumber hulu  : Data\CustomerOrder::customerOptions() (tm2mst.cust_customer); inquiry sumber dari $source.
 Input        : $source (id inquiry sumber), $label (keterangan asal); isian customer_id target.
 Output       : POST ke route v2.inquiry.duplicate lewat x-dl::modal, x-dl::action-form, x-dl::field.select, x-dl::alert, x-dl::button (Cancel, Duplicate).
 Dipakai oleh : Di-include inquiry-new, inquiry-recalc dan inquiry-revise (tombol Duplicate di form, di luar form dokumen).
 Status       : Aktif. Qty dan harga tidak disalin; hasilnya lewat ProDev dan ProCost lagi.
--}}
<x-dl::modal id="dup-modal" title="Duplicate Inquiry" size="md" icon="copy">
    <x-dl::action-form action="{{ route('v2.inquiry.duplicate') }}" id="dup-form" class="mp-dup">
        <input type="hidden" name="source_id" value="{{ $source }}">
        <x-dl::box as="p" class="mp-dup__src">{{ $label }}</x-dl::box>
        <x-dl::field.select name="customer_id" label="Target customer" required :options="\App\Support\V2\Data\CustomerOrder::customerOptions()" placeholder="Choose customer..."
            :source="\App\Support\V2\Src::of('tm2mst.cust_customer.customer_name')" />
        <x-dl::alert tone="info" message="Spec (parts, material, process, finishing, ink) is copied as is. Qty & price are not: the new inquiry is born as a DRAFT New Product and goes through Pro-Dev → Pro-Cost again." />
    </x-dl::action-form>
    <x-slot:footer>
        <x-dl::button preset="cancel" data-dl-close-modal />
        <x-dl::button icon="copy" label="Duplicate" type="submit" form="dup-form" />
    </x-slot:footer>
</x-dl::modal>
