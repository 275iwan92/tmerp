<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Models\Sys\SysConstant;

/**
 * One door for the PPIC scheduling settings. A value typed by the user in Settings > Constants (PPIC_*) wins; config/ppic.php holds the
 * assumed start value used until then. Engines never read config('ppic.*') directly for these numbers.
 */
final class PpicSettings
{
    /** config key => constant key. Order and meaning documented in the migration that creates the constants. */
    public const KEYS = [
        'lock_shifts' => 'PPIC_LOCK_SHIFTS',
        'weights.urgency' => 'PPIC_WEIGHT_URGENCY',
        'weights.setup' => 'PPIC_WEIGHT_SETUP',
        'weights.tier' => 'PPIC_WEIGHT_TIER',
        'similar_window_hours' => 'PPIC_SIMILAR_WINDOW_HOURS',
        'horizon_hours' => 'PPIC_HORIZON_HOURS',
        'horizon_days' => 'PPIC_HORIZON_DAYS',
        'group_due_hours' => 'PPIC_GROUP_DUE_HOURS',
        'group_slack_hours' => 'PPIC_GROUP_SLACK_HOURS',
        'color_order_factor' => 'PPIC_COLOR_ORDER_FACTOR',
        'bottleneck_load_pct' => 'PPIC_BOTTLENECK_LOAD_PCT',
        'default_shifts' => 'PPIC_DEFAULT_SHIFTS',
        'default_hours_per_shift' => 'PPIC_HOURS_PER_SHIFT',
        'setup.base_minutes' => 'PPIC_BASE_SETUP_MIN',
        'learning.min_samples' => 'PPIC_LEARN_MIN_SAMPLES',
        'learning.max_step_pct' => 'PPIC_LEARN_MAX_STEP_PCT',
        'learning.blend' => 'PPIC_LEARN_BLEND',
    ];

    /** @var array<string, float> */
    private static array $cache = [];

    public static function number(string $key): float
    {
        $default = (float) config('ppic.'.$key, 0);
        $constant = self::KEYS[$key] ?? null;
        if ($constant === null) {
            return $default;
        }

        return self::$cache[$key] ??= SysConstant::number($constant, $default);
    }

    /** @return array{urgency: float, setup: float, tier: float} */
    public static function weights(): array
    {
        return ['urgency' => self::number('weights.urgency'), 'setup' => self::number('weights.setup'), 'tier' => self::number('weights.tier')];
    }

    /** The setup rule table: config values, with the base minutes replaced by the constant. */
    public static function setupRules(): array
    {
        $rules = (array) config('ppic.setup');
        $rules['base_minutes'] = self::number('setup.base_minutes');
        $rules['color_order_factor'] = self::number('color_order_factor');

        return $rules;
    }

    public static function forget(): void
    {
        self::$cache = [];
    }
}
