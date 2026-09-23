<?php

namespace Devlib;

// Format nomor telepon per negara (sumber tunggal untuk PHP & JS).
// Tiap aturan: [regex digit nasional (tanpa 0 awal), mask (9 = digit; 'a|b' = pilih mask sesuai panjang), minimal digit].
// trunk = di dalam negeri nomor ditulis dengan 0 di depan (0812..., 031...).
final class PhoneFormat
{
    public const COUNTRIES = [
        'ID' => ['name' => 'Indonesia', 'dial' => '+62', 'flag' => '🇮🇩', 'trunk' => true,
            'mobile' => [['^8', '999-999-999|999-9999-999|999-9999-9999|999-9999-99999', 9]],
            'landline' => [['^(21|22|24|31|61)', '99-999-9999|99-9999-9999', 9], ['^[2-79]', '999-999-999|999-999-9999', 9]],
            'example' => ['mobile' => '812-3456-7890', 'landline' => '31-555-1234']],
        'SG' => ['name' => 'Singapore', 'dial' => '+65', 'flag' => '🇸🇬', 'trunk' => false,
            'mobile' => [['^[89]', '9999-9999', 8]], 'landline' => [['^6', '9999-9999', 8]],
            'example' => ['mobile' => '8123-4567', 'landline' => '6123-4567']],
        'MY' => ['name' => 'Malaysia', 'dial' => '+60', 'flag' => '🇲🇾', 'trunk' => true,
            'mobile' => [['^1', '99-9999-9999', 9]], 'landline' => [['^[3-9]', '9-9999-9999', 8]],
            'example' => ['mobile' => '12-345-6789', 'landline' => '3-1234-5678']],
        'US' => ['name' => 'United States', 'dial' => '+1', 'flag' => '🇺🇸', 'trunk' => false,
            'mobile' => [['^[2-9]', '(999) 999-9999', 10]], 'landline' => [['^[2-9]', '(999) 999-9999', 10]],
            'example' => ['mobile' => '(415) 555-2671', 'landline' => '(212) 555-0100']],
        'AU' => ['name' => 'Australia', 'dial' => '+61', 'flag' => '🇦🇺', 'trunk' => true,
            'mobile' => [['^4', '999-999-999', 9]], 'landline' => [['^[2378]', '9-9999-9999', 9]],
            'example' => ['mobile' => '412-345-678', 'landline' => '2-1234-5678']],
        'JP' => ['name' => 'Japan', 'dial' => '+81', 'flag' => '🇯🇵', 'trunk' => true,
            'mobile' => [['^[789]0', '99-9999-9999', 10]], 'landline' => [['^[36]', '9-9999-9999', 9], ['^[1-9]', '99-999-9999', 9]],
            'example' => ['mobile' => '90-1234-5678', 'landline' => '3-1234-5678']],
        'CN' => ['name' => 'China', 'dial' => '+86', 'flag' => '🇨🇳', 'trunk' => true,
            'mobile' => [['^1', '999-9999-9999', 11]], 'landline' => [['^(10|2\d)', '99-9999-9999', 10], ['^[3-9]', '999-9999-999', 10]],
            'example' => ['mobile' => '139-1234-5678', 'landline' => '10-1234-5678']],
        'IN' => ['name' => 'India', 'dial' => '+91', 'flag' => '🇮🇳', 'trunk' => true,
            'mobile' => [['^[6-9]', '99999-99999', 10]], 'landline' => [['^[1-5]', '99-9999-9999', 10]],
            'example' => ['mobile' => '98765-43210', 'landline' => '11-2345-6789']],
        'GB' => ['name' => 'United Kingdom', 'dial' => '+44', 'flag' => '🇬🇧', 'trunk' => true,
            'mobile' => [['^7', '9999-999999', 10]], 'landline' => [['^20', '99-9999-9999', 10], ['^[1-3]', '9999-999999', 10]],
            'example' => ['mobile' => '7400-123456', 'landline' => '20-7946-0958']],
    ];

    public static function list(array $codes = []): array
    {
        return $codes ? array_intersect_key(self::COUNTRIES, array_flip($codes)) : self::COUNTRIES;
    }
}
