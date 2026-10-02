@extends('v2.layout', ['title' => t('Data Dictionary'), 'bodyClass' => 'dl-page-body'])
@section('body')
    @php
        $colDefs = [['key' => 'col', 'label' => t('Columns')], ['key' => 'type', 'label' => t('Type')], ['key' => 'null', 'label' => t('Null')], ['key' => 'key', 'label' => t('Key')], ['key' => 'fk', 'label' => t('FK to')], ['key' => 'comment', 'label' => t('Description')]];
    @endphp
    <div class="dl-cat">
        <x-dl::card title="Data Dictionary — MySQL tmerp (developer only)" icon="fa-database"
            :description="raw(str_replace(':at', $stats['generated'], t('Structure only (information_schema), refreshed automatically after php artisan migrate / rollback in tmerp (manual: php artisan schema:dictionary). Last: :at.')))">
            <div class="dl-grid__stats">
                <x-dl::stat-box label="Database" :value="$stats['db']" />
                <x-dl::stat-box label="Table / view" :value="$stats['tables']" />
                <x-dl::stat-box label="Columns" :value="\Devlib\Locale::number($stats['columns'], 0)" />
                <x-dl::stat-box label="Possible duplicate columns" :value="$stats['dup']" tone="warn" />
                <x-dl::stat-box label="Master data copies" :value="$stats['copies']" tone="danger" />
            </div>
        </x-dl::card>

        <x-dl::tabs :tabs="['cols' => ['All Columns', 'columns'], 'dup' => ['Possible Duplicates', 'fa-clone'], 'copy' => ['Master Data Copies', 'copy'], 'tables' => ['Tables', 'fa-table'], 'changes' => ['Change Log', 'fa-clock-rotate-left']]">
            <x-slot:cols>
                <x-dl::grid id="dd-cols" :rows="$columns" row-key="id" :active-key="null" actions="none" :new-record="false" :per-page="50" search="Search column, table, type, description..."
                    :filters="['db' => 'Database', 'table' => 'Table', 'key' => 'Key']"
                    :columns="[['key' => 'db', 'label' => 'Database'], ['key' => 'table', 'label' => 'Table'], ['key' => 'col', 'label' => t('Columns')], ['key' => 'type', 'label' => 'Type'], ['key' => 'null', 'label' => 'Null'], ['key' => 'key', 'label' => 'Key'], ['key' => 'fk', 'label' => t('FK to')], ['key' => 'comment', 'label' => t('Description')]]"
                    source="information_schema.COLUMNS (tm2*)" />
            </x-slot:cols>
            <x-slot:dup>
                <p class="dl-note">{!! t('<b>Non-standard name</b> = violates the column names locked in CLAUDE.md §3.') !!} {!! t('<b>Synonym</b> = different name, same meaning.') !!} {!! t('<b>Type differs</b> = same name but the type/precision is not uniform.') !!} {!! t('<b>Same name</b> = the same column in many tables (check whether the same data is stored twice).') !!} {{ t('PK, FK, standard audit columns & the legacy DB tm2old are not counted. Open a row to see where it is.') }}</p>
                <x-dl::grid id="dd-dup" :rows="$duplicates" row-key="id" :active-key="null" actions="none" :new-record="false" :per-page="50" search="Search column name..."
                    :filters="['flag' => 'Indication']"
                    :columns="[['key' => 'flag', 'label' => 'Indication', 'type' => 'badge', 'tones' => ['Nama tidak baku' => 'danger', 'Sinonim' => 'violet', 'Tipe beda' => 'warn', 'Nama sama' => 'neutral']], ['key' => 'name', 'label' => t('Column (normalised)')], ['key' => 'count', 'label' => t('Amount'), 'type' => 'number'], ['key' => 'names', 'label' => t('Names used')], ['key' => 'types', 'label' => t('Types used')]]"
                    :detail="['key' => 'where', 'title' => t('Found in'), 'columns' => [['key' => 'at', 'label' => t('Database.Table')], ['key' => 'col', 'label' => t('Columns')], ['key' => 'type', 'label' => t('Type')]]]"
                    source="analysis App\Support\V2\Schema::duplicates()" />
            </x-slot:dup>
            <x-slot:copy>
                <p class="dl-note">{!! t('Columns <code>&lt;x&gt;_name / _code / _label</code> next to <code>&lt;x&gt;_id</code>: the master value is copied into this table.') !!} {!! t('It can be <b>intentional</b> (snapshot principle: a transaction freezes the master value) or <b>redundant</b> — needs a decision per row.') !!}</p>
                <x-dl::grid id="dd-copy" :rows="$copies" row-key="id" :active-key="null" actions="none" :new-record="false" search="Search table/column..." :filters="['db' => 'Database']"
                    :columns="[['key' => 'db', 'label' => 'Database'], ['key' => 'table', 'label' => 'Table'], ['key' => 'col', 'label' => t('Copied column')], ['key' => 'ref', 'label' => t('Referenced column')], ['key' => 'fk', 'label' => 'FK / master']]"
                    source="analysis App\Support\V2\Schema::copies()" />
            </x-slot:copy>
            <x-slot:tables>
                <x-dl::grid id="dd-tables" :rows="$tables" row-key="id" :active-key="null" actions="none" :new-record="false" :per-page="50" search="Search database, table, description..."
                    :filters="['db' => 'Database', 'kind' => 'Kind']"
                    :columns="[['key' => 'db', 'label' => 'Database'], ['key' => 'table', 'label' => 'Table'], ['key' => 'kind', 'label' => t('Type'), 'type' => 'badge', 'tones' => ['table' => 'info', 'view' => 'violet']], ['key' => 'n', 'label' => t('Columns'), 'type' => 'number'], ['key' => 'nfk', 'label' => 'FK', 'type' => 'number'], ['key' => 'comment', 'label' => t('Description')]]"
                    :detail="['key' => 'cols', 'title' => t('Columns'), 'columns' => $colDefs]"
                    source="information_schema.TABLES (tm2*)" />
            </x-slot:tables>
            <x-slot:changes>
                @if ($changes)
                    <x-dl::grid id="dd-changes" :rows="$changes" row-key="id" :active-key="null" actions="none" :new-record="false" search="Search..." :filters="['kind' => 'Modification', 'db' => 'Database']"
                        :columns="[['key' => 'at', 'label' => 'Timestamp'], ['key' => 'kind', 'label' => 'Modification', 'type' => 'badge', 'tones' => ['tabel baru' => 'success', 'kolom baru' => 'success', 'tabel dihapus' => 'danger', 'kolom dihapus' => 'danger', 'tipe berubah' => 'warn']], ['key' => 'db', 'label' => 'Database'], ['key' => 'table', 'label' => 'Table'], ['key' => 'column', 'label' => t('Columns')], ['key' => 'detail', 'label' => 'Detail']]"
                        source="resources/data/schema-changes.json" />
                @else
                    <x-dl::empty-state title="No changes recorded yet" message="History starts filling when the database structure changes (migrate / rollback)." icon="fa-clock-rotate-left" />
                @endif
            </x-slot:changes>
        </x-dl::tabs>
    </div>
@endsection
