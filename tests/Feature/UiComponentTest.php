<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// docs/componentstandard.md -- primitive x-ui.* (class-based, turunan
// App\View\Components\Ui\UiComponent). Tidak menyentuh database.
class UiComponentTest extends TestCase
{
    public function test_button_preset_locks_label_variant_and_type(): void
    {
        $html = Blade::render('<x-ui.button preset="save" />');

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('bg-accent', $html);
        $this->assertStringContainsString('Simpan', $html);

        $cancel = Blade::render('<x-ui.button preset="cancel" wire:click="showGrid" />');
        $this->assertStringContainsString('Batal', $cancel);
        $this->assertStringContainsString('type="button"', $cancel);
        $this->assertStringContainsString('wire:click="showGrid"', $cancel);

        $this->assertStringContainsString('+ New Record', Blade::render('<x-ui.button preset="new" />'));
    }

    public function test_slot_overrides_preset_label_and_attributes_merge(): void
    {
        $html = Blade::render('<x-ui.button variant="danger" size="xs" class="ml-auto">Lepas</x-ui.button>');

        $this->assertStringContainsString('bg-danger', $html);
        $this->assertStringContainsString('ml-auto', $html);
        $this->assertStringContainsString('text-[10.5px]', $html);
        $this->assertStringContainsString('Lepas', $html);
    }

    public function test_button_with_href_renders_anchor(): void
    {
        $html = Blade::render('<x-ui.button href="/x" variant="outline">Buka</x-ui.button>');

        $this->assertStringContainsString('<a href="/x"', $html);
        $this->assertStringNotContainsString('<button', $html);
    }

    public function test_icon_button_inherits_button_variants(): void
    {
        $html = Blade::render('<x-ui.icon-button icon="fa-pen" variant="primary" />');

        $this->assertStringContainsString('fa-pen', $html);
        $this->assertStringContainsString('bg-accent', $html);
        $this->assertStringContainsString('w-8 h-8', $html);
    }

    public function test_badge_tones_come_from_central_palette(): void
    {
        $this->assertStringContainsString('bg-success-soft text-success-ink', Blade::render('<x-ui.badge tone="success">OK</x-ui.badge>'));
        $this->assertStringContainsString('rounded-full', Blade::render('<x-ui.badge tone="danger" pill>X</x-ui.badge>'));
        $this->assertStringContainsString('<button', Blade::render('<x-ui.badge-button tone="highlight">★</x-ui.badge-button>'));
    }

    public function test_tabs_render_from_data_with_active_state_and_badge(): void
    {
        $html = Blade::render(
            '<x-ui.tabs :tabs="$t" active="b" model="activeTab" :badges="[\'b\' => [\'text\' => 3, \'tone\' => \'success\']]" />',
            ['t' => ['a' => 'Satu', 'b' => 'Dua']],
        );

        $this->assertStringContainsString("\$set('activeTab', 'a')", $html);
        $this->assertMatchesRegularExpression('/text-accent border-b-2[^>]*>\s*Dua/', $html);
        $this->assertStringContainsString('bg-success-soft', $html);
    }

    public function test_card_table_modal_progress_metric_render(): void
    {
        $card = Blade::render('<x-ui.card title="Kru" description="Catatan">isi</x-ui.card>');
        $this->assertStringContainsString('text-accent uppercase', $card);
        $this->assertStringContainsString('Catatan', $card);

        $table = Blade::render('<x-ui.table size="sm" framed><tr><td>1</td></tr></x-ui.table>');
        $this->assertStringContainsString('tm-table w-full border-collapse tm-table-sm', $table);
        $this->assertStringContainsString('border border-line rounded-md', $table);

        $modal = Blade::render('<x-ui.modal title="Kontak" close="closeContactModal">body</x-ui.modal>');
        $this->assertStringContainsString('wire:click.self="closeContactModal"', $modal);
        $this->assertStringContainsString('bg-overlay', $modal);

        $bar = Blade::render('<x-ui.progress :value="150" tone="success" :marker="40" />');
        $this->assertStringContainsString('width:100%', $bar);
        $this->assertStringContainsString('left:40%', $bar);

        $this->assertStringContainsString('text-warn-ink', Blade::render('<x-ui.metric label="A" tone="warn" size="lg">70%</x-ui.metric>'));
    }
}
