<?php

namespace Devlib\Components;

// Setup alur approval per jenis dokumen (mockup_flow_engine: Setup Flow Config). Disusun dari komponen
// yang sudah ada: tab (dl-tabs) + x-dl::lines per dokumen + simulator nilai (langkah mana yang aktif).
// docs: [kode => nama dokumen]; roles: [kode => nama peran]; flows: [kode dok => [['name','role','action','min']]]
// Terkirim: flow[DOK][i][kolom]. Simpan -> event dl:flowcfg-save {flows}.
class FlowConfig extends DevlibComponent
{
    public function __construct(
        public array $docs = [],
        public array $roles = [],
        public array $flows = [],
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::flow-config';
    }

    public function source(): string
    {
        return $this->source ?? 'tm2sys flow_config (rencana)';
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nama Langkah', 'required' => true, 'width' => '220px'],
            ['key' => 'role', 'label' => 'Peran Approver', 'type' => 'select', 'options' => $this->roles, 'required' => true, 'width' => '200px'],
            ['key' => 'action', 'label' => 'Aksi', 'type' => 'select', 'options' => ['KNOW' => 'Mengetahui', 'APPROVE' => 'Menyetujui'], 'required' => true, 'width' => '140px'],
            ['key' => 'min', 'label' => 'Aktif bila nilai ≥ (Rp)', 'type' => 'money', 'width' => '170px'],
        ];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-flowcfg') }} {!! $dl() !!} data-dl-flowcfg data-roles="{{ json_encode($roles) }}">
                <div class="dl-tabs" data-dl-tabs>
                    <div class="dl-tabs__bar" role="tablist">
                        @foreach ($docs as $code => $label)<button type="button" class="dl-tabs__tab {{ $loop->first ? 'is-active' : '' }}" data-tab="{{ $code }}">{{ $code }} · {{ $label }}</button>@endforeach
                    </div>
                    @foreach ($docs as $code => $label)
                        <div class="dl-tabs__panel {{ $loop->first ? 'is-active' : '' }}" data-panel="{{ $code }}">
                            <div class="dl-flowcfg__panel" data-fc-doc="{{ $code }}">
                                <x-dl::lines :name="'flow['.$code.']'" :title="'Langkah approval '.$label" add-label="+ Tambah Langkah" :columns="$columns()" :rows="$flows[$code] ?? []" />
                                <div class="dl-flowcfg__sim">
                                    <label><span>Simulasi: nilai dokumen (Rp)</span><input type="text" inputmode="numeric" value="10.000.000" data-fc-value></label>
                                    <ol class="dl-appr__steps dl-flowcfg__path" data-fc-path></ol>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="dl-flowcfg__foot"><x-dl::button preset="save" data-fc-save /></div>
            </div>
            BLADE;
    }
}
