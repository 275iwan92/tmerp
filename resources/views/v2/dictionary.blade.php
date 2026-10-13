{{--
 REMARK
 Tujuan       : Menampilkan kamus struktur database tm2* (kolom, indikasi duplikat, salinan data master, tabel, riwayat perubahan) untuk developer.
 Sumber hulu  : App\Support\V2\Schema (information_schema + resources/data/schema-changes.json) lewat route.
 Input        : stats, columns, tables, duplicates, copies, changes dari route; khusus Administrator / grup DEVELOPER.
 Output       : x-dl::card, x-dl::stat-box, x-dl::tabs dan beberapa x-dl::grid read-only.
 Dipakai oleh : route v2.dictionary (routes/v2.php); menu Database (App\Support\V2\AppMenu, Screens).
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => 'Data Dictionary', 'bodyClass' => 'dl-page-body'])
@section('body')
    @php
        $colDefs = [['key' => 'col', 'label' => t('Columns')], ['key' => 'type', 'label' => 'Type'], ['key' => 'null', 'label' => 'Null'], ['key' => 'key', 'label' => 'Key'], ['key' => 'fk', 'label' => t('FK to')], ['key' => 'comment', 'label' => t('Description')]];
    @endphp
    <x-dl::box class="dl-cat">
        <x-dl::card title="Data Dictionary — MySQL tmerp (developers only)" icon="fa-database"
            :description="'Struktur saja (information_schema), diperbarui otomatis setelah php artisan migrate / rollback di tmerp (manual: php artisan schema:dictionary). Terakhir: '.raw($stats['generated'].'.')">
            <x-dl::kpi-row>
                <x-dl::stat-box label="Database" :value="$stats['db']" />
                <x-dl::stat-box label="Tables / views" :value="$stats['tables']" />
                <x-dl::stat-box label="Columns" :value="\Devlib\Locale::number($stats['columns'], 0)" />
                <x-dl::stat-box label="Possible duplicate columns" :value="$stats['dup']" tone="warn" />
                <x-dl::stat-box label="Master data copies" :value="$stats['copies']" tone="danger" />
            </x-dl::kpi-row>
        </x-dl::card>

        <x-dl::tabs :tabs="['cols' => ['Semua Kolom', 'columns'], 'dup' => ['Indikasi Duplikat', 'fa-clone'], 'copy' => ['Salinan Data Master', 'copy'], 'tables' => ['Tabel', 'fa-table'], 'changes' => ['Perubahan', 'fa-clock-rotate-left']]">
            <x-slot:cols>
                <x-dl::grid id="dd-cols" :rows="$columns" row-key="id" :active-key="null" actions="none" :new-record="false" :per-page="50" search="Search column, table, type, description..."
                    :filters="['db' => 'Database', 'table' => 'Tabel', 'key' => 'Key']"
                    :columns="[['key' => 'db', 'label' => 'Database'], ['key' => 'table', 'label' => 'Table'], ['key' => 'col', 'label' => t('Columns')], ['key' => 'type', 'label' => 'Type'], ['key' => 'null', 'label' => 'Null'], ['key' => 'key', 'label' => 'Key'], ['key' => 'fk', 'label' => t('FK to')], ['key' => 'comment', 'label' => t('Description')]]"
                    source="information_schema.COLUMNS (tm2*)" />
            </x-slot:cols>
            <x-slot:dup>
                <x-dl::box as="p" class="dl-note"><x-dl::box as="b">Nama tidak baku</x-dl::box> = melanggar nama kolom yang dikunci CLAUDE.md §3. <x-dl::box as="b">Sinonim</x-dl::box> = nama beda, arti sama. <x-dl::box as="b">Tipe beda</x-dl::box> = nama sama tapi tipe/presisi tidak seragam. <x-dl::box as="b">Nama sama</x-dl::box> = kolom sama di banyak tabel (cek apakah data yang sama disimpan dua kali). PK, FK, kolom audit baku & DB legacy tm2old tidak dihitung. Buka baris untuk melihat letaknya.</x-dl::box>
                <x-dl::grid id="dd-dup" :rows="$duplicates" row-key="id" :active-key="null" actions="none" :new-record="false" :per-page="50" search="Search column name..."
                    :filters="['flag' => 'Indikasi']"
                    :columns="[['key' => 'flag', 'label' => 'Indication', 'type' => 'badge', 'tones' => ['Nama tidak baku' => 'danger', 'Sinonim' => 'violet', 'Tipe beda' => 'warn', 'Nama sama' => 'neutral']], ['key' => 'name', 'label' => t('Column (normalised)')], ['key' => 'count', 'label' => t('Amount'), 'type' => 'number'], ['key' => 'names', 'label' => t('Names used')], ['key' => 'types', 'label' => t('Types used')]]"
                    :detail="['key' => 'where', 'title' => 'Where', 'columns' => [['key' => 'at', 'label' => 'Database.Table'], ['key' => 'col', 'label' => t('Columns')], ['key' => 'type', 'label' => 'Type']]]"
                    source="analisis App\Support\V2\Schema::duplicates()" />
            </x-slot:dup>
            <x-slot:copy>
                <x-dl::box as="p" class="dl-note">Kolom <x-dl::box as="code">&lt;x&gt;_name / _code / _label</x-dl::box> yang berdampingan dengan <x-dl::box as="code">&lt;x&gt;_id</x-dl::box>: nilai master disalin ke tabel ini. Bisa <x-dl::box as="b">disengaja</x-dl::box> (prinsip snapshot: transaksi membekukan nilai master) atau <x-dl::box as="b">redundan</x-dl::box> — perlu keputusan per baris.</x-dl::box>
                <x-dl::grid id="dd-copy" :rows="$copies" row-key="id" :active-key="null" actions="none" :new-record="false" search="Search table/column..." :filters="['db' => 'Database']"
                    :columns="[['key' => 'db', 'label' => 'Database'], ['key' => 'table', 'label' => 'Table'], ['key' => 'col', 'label' => t('Copied column')], ['key' => 'ref', 'label' => t('Referenced column')], ['key' => 'fk', 'label' => 'FK / master']]"
                    source="analisis App\Support\V2\Schema::copies()" />
            </x-slot:copy>
            <x-slot:tables>
                <x-dl::grid id="dd-tables" :rows="$tables" row-key="id" :active-key="null" actions="none" :new-record="false" :per-page="50" search="Search database, table, description..."
                    :filters="['db' => 'Database', 'kind' => 'Jenis']"
                    :columns="[['key' => 'db', 'label' => 'Database'], ['key' => 'table', 'label' => 'Table'], ['key' => 'kind', 'label' => t('Type'), 'type' => 'badge', 'tones' => ['table' => 'info', 'view' => 'violet']], ['key' => 'n', 'label' => t('Columns'), 'type' => 'number'], ['key' => 'nfk', 'label' => 'FK', 'type' => 'number'], ['key' => 'comment', 'label' => t('Description')]]"
                    :detail="['key' => 'cols', 'title' => t('Columns'), 'columns' => $colDefs]"
                    source="information_schema.TABLES (tm2*)" />
            </x-slot:tables>
            <x-slot:changes>
                @if ($changes)
                    <x-dl::grid id="dd-changes" :rows="$changes" row-key="id" :active-key="null" actions="none" :new-record="false" search="Search..." :filters="['kind' => 'Perubahan', 'db' => 'Database']"
                        :columns="[['key' => 'at', 'label' => 'When'], ['key' => 'kind', 'label' => 'Change', 'type' => 'badge', 'tones' => ['tabel baru' => 'success', 'kolom baru' => 'success', 'tabel dihapus' => 'danger', 'kolom dihapus' => 'danger', 'tipe berubah' => 'warn']], ['key' => 'db', 'label' => 'Database'], ['key' => 'table', 'label' => 'Table'], ['key' => 'column', 'label' => t('Columns')], ['key' => 'detail', 'label' => 'Detail']]"
                        source="resources/data/schema-changes.json" />
                @else
                    <x-dl::empty-state title="No changes recorded yet" message="History starts filling when the database structure changes (migrate / rollback)." icon="fa-clock-rotate-left" />
                @endif
            </x-slot:changes>
        </x-dl::tabs>
    </x-dl::box>
@endsection
