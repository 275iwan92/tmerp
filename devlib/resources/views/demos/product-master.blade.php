{{-- Form khusus Master Product (halaman satu product). Contoh ringkas: product tanpa pohon rakitan; di aplikasi pohonnya
     (objek x-mp::sales.product-tree) terisi dari ProductMaster::doc().
     Di aplikasi: <x-mp::sales.product-master :doc="$doc" :orders="ProductMaster::orders($id)" :types="Sales::dielineTypes()"
                    :acc-types="ProdProductAcc::TYPES" :home="..." :name-action="..." /> --}}
<div class="dl-demo">
    <x-mp::sales.product-master home="#" name-action="#"
        :doc="[
            'id' => 1, 'code' => 'FB-0012', 'name' => 'Folding Box Serum 30 ml', 'design_no' => 'D-2026-0031', 'status' => 'ACTIVE', 'join' => null, 'parent' => null, 'revision_no' => 0,
            'customer' => 'PT Contoh Kosmetika', 'dims' => [4.0, 4.0, 11.0], 'tree' => [], 'components' => [], 'revisions' => [],
            'accs' => [['type' => 'DIGITAL', 'at' => '02-Oct-2026', 'by' => 'Ibu Rina', 'channel' => 'WhatsApp']],
        ]"
        :orders="[['date' => '28-Sep-2026', 'quotation' => 'QT2600051', 'co' => 'CO2600029', 'qty' => 25000, 'price' => 3840, 'result' => 'WIN']]"
        :acc-types="['DIGITAL' => 'Digital ACC', 'PROOF' => 'Proof ACC', 'PHYSICAL' => 'Physical Proof ACC']" />
</div>
