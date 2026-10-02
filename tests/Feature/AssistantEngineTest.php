<?php

namespace Tests\Feature;

use Assistant\Contracts\Asker;
use Assistant\Contracts\Knowledge;
use Assistant\Contracts\LanguageModel;
use Assistant\Contracts\Metric;
use Assistant\Contracts\Navigator;
use Assistant\Engine;
use Assistant\Llm\NoModel;
use Assistant\MetricCatalog;
use Assistant\Reply;
use Tests\TestCase;

// Mesin jawab paket asisten diuji dengan adaptor tiruan (tanpa database): yang dijaga = urutan keputusan dan gerbang hak.
// Angka dihitung kelas metric, model bahasa hanya memilih dari calon -- keduanya tidak boleh "menembus" gerbang hak.
class AssistantEngineTest extends TestCase
{
    private const SCREENS = [
        ['key' => 'SALES_SO', 'label' => 'Customer Order', 'path' => 'Sales › Sales Order', 'url' => '/co', 'words' => 'co, pesanan, sales order', 'about' => 'Enter customer orders.', 'help' => 'customer-order'],
        ['key' => 'SALES_DN_SUBCON', 'label' => 'Subcontract Delivery Note', 'path' => 'Sales › Delivery', 'url' => '/sdn', 'words' => 'surat jalan subkon, subcon', 'about' => 'Delivery note for subcontract work.', 'help' => null],
    ];

    private const TOPICS = [
        ['key' => 'customer-order', 'title' => 'Customer Order', 'purpose' => 'Enter customer orders.', 'words' => 'co, pesanan, sales order, quotation',
            'steps' => ['Click + New Record.', 'Click Save.'], 'fields' => [['label' => 'Due Date', 'text' => 'Date the product must be ready.']]],
    ];

    private function engine(?LanguageModel $model = null): Engine
    {
        $metric = new class implements Metric
        {
            public function key(): string
            {
                return 'sales_today';
            }

            public function title(): string
            {
                return 'Sales today';
            }

            public function phrases(): array
            {
                return ['sales hari ini', 'sales today'];
            }

            public function module(): ?string
            {
                return 'SALES';
            }

            public function minLevel(): int
            {
                return 0;
            }

            public function suggest(): bool
            {
                return true;
            }

            public function answer(Asker $asker, string $question): Reply
            {
                return new Reply(Reply::NUMBER, 'Sales today', big: 'Rp 100');
            }
        };
        $catalog = new class($metric) extends MetricCatalog
        {
            public function __construct(private Metric $metric) {}

            public function all(): array
            {
                return [['key' => 'sales_today', 'metric' => $this->metric, 'title' => 'Sales today', 'phrases' => $this->metric->phrases(), 'module' => 'SALES', 'min_level' => 0]];
            }
        };
        $navigator = new class implements Navigator
        {
            public function screens(Asker $asker): array
            {
                return AssistantEngineTest::screens();
            }
        };
        $knowledge = new class implements Knowledge
        {
            public function topics(Asker $asker): array
            {
                return AssistantEngineTest::topics();
            }

            public function url(): ?string
            {
                return '/help';
            }
        };

        return new Engine($catalog, $navigator, $knowledge, $model ?? new NoModel);
    }

    public static function screens(): array
    {
        return self::SCREENS;
    }

    public static function topics(): array
    {
        return self::TOPICS;
    }

    private function asker(array $modules, int $level = 1): Asker
    {
        return new class($modules, $level) implements Asker
        {
            public function __construct(private array $modules, private int $level) {}

            public function id(): string
            {
                return 'test';
            }

            public function name(): string
            {
                return 'Test';
            }

            public function lang(): string
            {
                return 'en';
            }

            public function level(): int
            {
                return $this->level;
            }

            public function can(string $module): bool
            {
                return in_array($module, $this->modules, true);
            }
        };
    }

    public function test_number_question_is_answered_by_the_metric_class_for_a_user_with_the_module_right(): void
    {
        $reply = $this->engine()->ask($this->asker(['SALES']), 'berapa sales hari ini?');

        $this->assertSame(Reply::NUMBER, $reply->kind);
        $this->assertSame('Rp 100', $reply->big);
        $this->assertSame('sales_today', $reply->key);
    }

    public function test_number_question_is_denied_without_the_module_right_and_shows_no_number(): void
    {
        $reply = $this->engine()->ask($this->asker(['INV']), 'sales today');

        $this->assertSame(Reply::DENIED, $reply->kind);
        $this->assertNull($reply->big);
        $this->assertSame([], $reply->rows);
    }

    public function test_where_question_finds_the_screen_by_indonesian_keywords(): void
    {
        $reply = $this->engine()->ask($this->asker(['SALES']), 'surat jalan subkon dimana ya');

        $this->assertSame(Reply::SCREEN, $reply->kind);
        $this->assertSame('SALES_DN_SUBCON', $reply->key);
        $this->assertSame('/sdn', $reply->actions[0]['url']);
    }

    public function test_how_question_quotes_the_help_steps(): void
    {
        $reply = $this->engine()->ask($this->asker(['SALES']), 'cara bikin customer order tanpa quotation');

        $this->assertSame(Reply::HELP, $reply->kind);
        $this->assertSame(['Click + New Record.', 'Click Save.'], $reply->steps);
    }

    public function test_field_question_quotes_the_field_explanation(): void
    {
        $reply = $this->engine()->ask($this->asker(['SALES']), 'due date itu diisi apa?');

        $this->assertSame(Reply::HELP, $reply->kind);
        $this->assertSame([['Customer Order', 'Date the product must be ready.']], $reply->rows);
    }

    public function test_asking_to_open_a_report_is_not_treated_as_a_number_question(): void
    {
        $reply = $this->engine()->ask($this->asker(['SALES']), 'buka customer order');

        $this->assertSame(Reply::SCREEN, $reply->kind);
    }

    public function test_unknown_question_is_not_guessed(): void
    {
        $reply = $this->engine()->ask($this->asker(['SALES']), 'kenapa mesin sering macet?');

        $this->assertSame(Reply::UNKNOWN, $reply->kind);
        $this->assertFalse($reply->answered());
    }

    public function test_language_model_only_picks_from_the_candidates_it_is_given(): void
    {
        $model = new class implements LanguageModel
        {
            public array $seen = [];

            public function available(): bool
            {
                return true;
            }

            public function choose(string $question, array $options): ?string
            {
                $this->seen = array_keys($options);

                return 'screen:NOT_A_CANDIDATE';
            }
        };
        $engine = $this->engine($model);
        $reply = $engine->ask($this->asker(['SALES']), 'tolong pesanan dari pembeli');

        // Jawaban di luar daftar calon diabaikan: tetap "tidak tahu", tidak pernah layar karangan model.
        $this->assertTrue($engine->modelUsed);
        $this->assertNotEmpty($model->seen);
        $this->assertSame(Reply::UNKNOWN, $reply->kind);
    }
}
