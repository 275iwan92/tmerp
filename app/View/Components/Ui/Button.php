<?php

namespace App\View\Components\Ui;

// Tombol baku. preset = aksi umum yang labelnya LOCKED (CLAUDE.md §3 "Tombol
// aksi"): label, variant & type ikut preset, jadi tak ada layar yang bisa
// menulis "Save"/"Simpan Data"/"OK" sendiri-sendiri. Label English, tampil lewat kamus sys_dictionary.
class Button extends UiComponent
{
    public const PRESETS = [
        'save' => ['variant' => 'primary', 'type' => 'submit', 'label' => 'Save'],
        'cancel' => ['variant' => 'secondary', 'type' => 'button', 'label' => 'Cancel'],
        'close' => ['variant' => 'secondary', 'type' => 'button', 'label' => 'Close'],
        'confirm' => ['variant' => 'success', 'type' => 'button', 'label' => 'Confirm'],
        'apply' => ['variant' => 'navy', 'type' => 'button', 'label' => 'Apply'],
        'delete' => ['variant' => 'danger', 'type' => 'button', 'label' => 'Delete'],
        // griduistandard.md §6: label tombol tambah level grid selalu ini.
        'new' => ['variant' => 'navy', 'type' => 'button', 'label' => '+ New Record', 'fixed' => true],
    ];

    public string $label = '';

    public function __construct(
        string $variant = 'primary',
        string $size = 'md',
        public ?string $preset = null,
        public ?string $type = null,
        public ?string $href = null,
        public ?string $icon = null,
        public ?string $iconRight = null,
        public bool $active = false,
        public bool $block = false,
    ) {
        $this->variant = $variant;
        $this->size = $size;

        if ($preset && isset(self::PRESETS[$preset])) {
            $p = self::PRESETS[$preset];
            $this->variant = $variant !== 'primary' ? $variant : $p['variant'];
            $this->type ??= $p['type'];
            $this->label = empty($p['fixed']) ? t($p['label']) : $p['label'];
        }

        $this->type ??= 'button';
    }

    protected function base(): string
    {
        return 'inline-flex items-center justify-center gap-1.5 rounded-md font-semibold whitespace-nowrap no-underline cursor-pointer transition '
            .'disabled:opacity-45 disabled:cursor-not-allowed';
    }

    protected function variants(): array
    {
        return [
            'primary' => 'bg-accent text-white border border-accent hover:brightness-110',
            'secondary' => 'bg-surface text-ink border border-line hover:bg-surface-alt',
            'neutral' => 'bg-neutral-soft text-neutral-ink border border-transparent hover:brightness-95',
            'navy' => 'bg-navy text-white border border-navy hover:brightness-110',
            'success' => 'bg-success text-white border border-success hover:brightness-110',
            'danger' => 'bg-danger text-white border border-danger hover:brightness-110',
            'outline' => 'bg-surface text-accent border border-accent hover:bg-accent-soft',
            'ghost' => 'bg-transparent text-ink-soft border border-transparent hover:bg-line-soft',
            'link' => 'bg-transparent text-accent border-0 hover:underline',
            // Tombol ikon kolom Aksi grid (griduistandard.md §5).
            'action' => 'bg-surface text-accent border border-field-border hover:bg-accent-soft',
            'action-danger' => 'bg-surface text-danger-ink border border-danger-line hover:bg-danger-soft',
            'action-success' => 'bg-surface text-success-ink border border-success-line hover:bg-success-soft',
            'action-plain' => 'bg-transparent text-accent border border-transparent hover:bg-accent-soft',
            'action-plain-danger' => 'bg-transparent text-danger-ink border border-transparent hover:bg-danger-soft',
            'action-plain-success' => 'bg-transparent text-success-ink border border-transparent hover:bg-success-soft',
            // Chip = pilihan segmented (7/30/90 hari, filter cepat); :active menyalakan.
            'chip' => $this->active
                ? 'bg-accent text-white border border-accent'
                : 'bg-line-soft text-ink-soft border border-transparent hover:brightness-95',
        ];
    }

    protected function sizes(): array
    {
        return [
            'md' => 'px-4 py-2 text-xs',
            'sm' => 'px-3.5 py-1.5 text-xs',
            'xs' => 'px-2 py-1 text-[10.5px]',
            'square' => 'w-8 h-8 text-[11.5px]',
        ];
    }

    protected function extra(): string
    {
        return $this->block ? 'w-full' : '';
    }

    public function render()
    {
        return view('components.ui.button');
    }
}
