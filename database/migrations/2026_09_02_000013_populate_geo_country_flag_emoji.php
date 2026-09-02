<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Isi flag_emoji (docs/configurationstandard.md §2.5, BACKLOG.md §2) --
// BUKAN tebak-tebak per negara. geo_country.iso_code sudah ada (ISO 3166-1
// ALPHA-3, mis. "IDN"), tabel referensi di bawah = mapping standar alpha-3
// -> alpha-2 (data ISO baku, bukan hasil generate/tebakan AI), lalu flag
// emoji DIHITUNG dari alpha-2 lewat rumus Unicode Regional Indicator Symbol
// (0x1F1E6 = 'A', deterministik, bukan hardcode karakter emoji manual --
// menghindari salah ketik emoji). Baris yang iso_code-nya TIDAK ketemu di
// tabel referensi SENGAJA dibiarkan NULL (dilaporkan lewat query verifikasi
// terpisah), bukan ditebak.
return new class extends Migration
{
    private const ALPHA3_TO_ALPHA2 = [
        'ABW' => 'AW', 'AFG' => 'AF', 'AGO' => 'AO', 'AIA' => 'AI', 'ALA' => 'AX', 'ALB' => 'AL', 'AND' => 'AD', 'ARE' => 'AE', 'ARG' => 'AR', 'ARM' => 'AM', 'ASM' => 'AS', 'ATA' => 'AQ', 'ATF' => 'TF', 'ATG' => 'AG', 'AUS' => 'AU', 'AUT' => 'AT', 'AZE' => 'AZ',
        'BDI' => 'BI', 'BEL' => 'BE', 'BEN' => 'BJ', 'BES' => 'BQ', 'BFA' => 'BF', 'BGD' => 'BD', 'BGR' => 'BG', 'BHR' => 'BH', 'BHS' => 'BS', 'BIH' => 'BA', 'BLM' => 'BL', 'BLR' => 'BY', 'BLZ' => 'BZ', 'BMU' => 'BM', 'BOL' => 'BO', 'BRA' => 'BR', 'BRB' => 'BB', 'BRN' => 'BN', 'BTN' => 'BT', 'BVT' => 'BV', 'BWA' => 'BW',
        'CAF' => 'CF', 'CAN' => 'CA', 'CCK' => 'CC', 'CHE' => 'CH', 'CHL' => 'CL', 'CHN' => 'CN', 'CIV' => 'CI', 'CMR' => 'CM', 'COD' => 'CD', 'COG' => 'CG', 'COK' => 'CK', 'COL' => 'CO', 'COM' => 'KM', 'CPV' => 'CV', 'CRI' => 'CR', 'CUB' => 'CU', 'CUW' => 'CW', 'CXR' => 'CX', 'CYM' => 'KY', 'CYP' => 'CY', 'CZE' => 'CZ',
        'DEU' => 'DE', 'DJI' => 'DJ', 'DMA' => 'DM', 'DNK' => 'DK', 'DOM' => 'DO', 'DZA' => 'DZ',
        'ECU' => 'EC', 'EGY' => 'EG', 'ERI' => 'ER', 'ESH' => 'EH', 'ESP' => 'ES', 'EST' => 'EE', 'ETH' => 'ET',
        'FIN' => 'FI', 'FJI' => 'FJ', 'FLK' => 'FK', 'FRA' => 'FR', 'FRO' => 'FO', 'FSM' => 'FM',
        'GAB' => 'GA', 'GBR' => 'GB', 'GEO' => 'GE', 'GGY' => 'GG', 'GHA' => 'GH', 'GIB' => 'GI', 'GIN' => 'GN', 'GLP' => 'GP', 'GMB' => 'GM', 'GNB' => 'GW', 'GNQ' => 'GQ', 'GRC' => 'GR', 'GRD' => 'GD', 'GRL' => 'GL', 'GTM' => 'GT', 'GUF' => 'GF', 'GUM' => 'GU', 'GUY' => 'GY',
        'HKG' => 'HK', 'HMD' => 'HM', 'HND' => 'HN', 'HRV' => 'HR', 'HTI' => 'HT', 'HUN' => 'HU',
        'IDN' => 'ID', 'IMN' => 'IM', 'IND' => 'IN', 'IOT' => 'IO', 'IRL' => 'IE', 'IRN' => 'IR', 'IRQ' => 'IQ', 'ISL' => 'IS', 'ISR' => 'IL', 'ITA' => 'IT',
        'JAM' => 'JM', 'JEY' => 'JE', 'JOR' => 'JO', 'JPN' => 'JP',
        'KAZ' => 'KZ', 'KEN' => 'KE', 'KGZ' => 'KG', 'KHM' => 'KH', 'KIR' => 'KI', 'KNA' => 'KN', 'KOR' => 'KR', 'KWT' => 'KW',
        'LAO' => 'LA', 'LBN' => 'LB', 'LBR' => 'LR', 'LBY' => 'LY', 'LCA' => 'LC', 'LIE' => 'LI', 'LKA' => 'LK', 'LSO' => 'LS', 'LTU' => 'LT', 'LUX' => 'LU', 'LVA' => 'LV',
        'MAC' => 'MO', 'MAF' => 'MF', 'MAR' => 'MA', 'MCO' => 'MC', 'MDA' => 'MD', 'MDG' => 'MG', 'MDV' => 'MV', 'MEX' => 'MX', 'MHL' => 'MH', 'MKD' => 'MK', 'MLI' => 'ML', 'MLT' => 'MT', 'MMR' => 'MM', 'MNE' => 'ME', 'MNG' => 'MN', 'MNP' => 'MP', 'MOZ' => 'MZ', 'MRT' => 'MR', 'MSR' => 'MS', 'MTQ' => 'MQ', 'MUS' => 'MU', 'MWI' => 'MW', 'MYS' => 'MY', 'MYT' => 'YT',
        'NAM' => 'NA', 'NCL' => 'NC', 'NER' => 'NE', 'NFK' => 'NF', 'NGA' => 'NG', 'NIC' => 'NI', 'NIU' => 'NU', 'NLD' => 'NL', 'NOR' => 'NO', 'NPL' => 'NP', 'NRU' => 'NR', 'NZL' => 'NZ',
        'OMN' => 'OM',
        'PAK' => 'PK', 'PAN' => 'PA', 'PCN' => 'PN', 'PER' => 'PE', 'PHL' => 'PH', 'PLW' => 'PW', 'PNG' => 'PG', 'POL' => 'PL', 'PRI' => 'PR', 'PRK' => 'KP', 'PRT' => 'PT', 'PRY' => 'PY', 'PSE' => 'PS', 'PYF' => 'PF',
        'QAT' => 'QA',
        'REU' => 'RE', 'ROU' => 'RO', 'RUS' => 'RU', 'RWA' => 'RW',
        'SAU' => 'SA', 'SDN' => 'SD', 'SEN' => 'SN', 'SGP' => 'SG', 'SGS' => 'GS', 'SHN' => 'SH', 'SJM' => 'SJ', 'SLB' => 'SB', 'SLE' => 'SL', 'SLV' => 'SV', 'SMR' => 'SM', 'SOM' => 'SO', 'SPM' => 'PM', 'SRB' => 'RS', 'SSD' => 'SS', 'STP' => 'ST', 'SUR' => 'SR', 'SVK' => 'SK', 'SVN' => 'SI', 'SWE' => 'SE', 'SWZ' => 'SZ', 'SXM' => 'SX', 'SYC' => 'SC', 'SYR' => 'SY',
        'TCA' => 'TC', 'TCD' => 'TD', 'TGO' => 'TG', 'THA' => 'TH', 'TJK' => 'TJ', 'TKL' => 'TK', 'TKM' => 'TM', 'TLS' => 'TL', 'TON' => 'TO', 'TTO' => 'TT', 'TUN' => 'TN', 'TUR' => 'TR', 'TUV' => 'TV', 'TWN' => 'TW', 'TZA' => 'TZ',
        'UGA' => 'UG', 'UKR' => 'UA', 'UMI' => 'UM', 'URY' => 'UY', 'USA' => 'US', 'UZB' => 'UZ',
        'VAT' => 'VA', 'VCT' => 'VC', 'VEN' => 'VE', 'VGB' => 'VG', 'VIR' => 'VI', 'VNM' => 'VN', 'VUT' => 'VU',
        'WLF' => 'WF', 'WSM' => 'WS',
        'XKX' => 'XK',
        'YEM' => 'YE',
        'ZAF' => 'ZA', 'ZMB' => 'ZM', 'ZWE' => 'ZW',
    ];

    public function up(): void
    {
        $rows = DB::connection('tm2mst')->table('geo_country')->select('id_country', 'iso_code', 'country_name')->get();
        $unmatched = [];

        foreach ($rows as $row) {
            $alpha2 = self::ALPHA3_TO_ALPHA2[strtoupper((string) $row->iso_code)] ?? null;

            if (! $alpha2) {
                $unmatched[] = $row->country_name.' ('.$row->iso_code.')';

                continue;
            }

            DB::connection('tm2mst')->table('geo_country')
                ->where('id_country', $row->id_country)
                ->update(['flag_emoji' => self::regionalIndicatorFlag($alpha2)]);
        }

        if ($unmatched) {
            // Bukan error fatal -- laporkan, jangan gagalkan migration. Baris
            // ini tetap NULL, bisa dicek lewat "SELECT * FROM geo_country
            // WHERE flag_emoji IS NULL" kapan saja.
            fwrite(STDOUT, "\n  [flag_emoji] ".count($unmatched)." negara TIDAK ketemu di tabel referensi, dibiarkan NULL: ".implode(', ', $unmatched)."\n");
        }
    }

    // Regional Indicator Symbol Unicode: A=U+1F1E6 ... Z=U+1F1FF. Flag emoji
    // = 2 simbol berurutan sesuai 2 huruf kode negara (mis. "ID" -> 🇮🇩).
    private static function regionalIndicatorFlag(string $alpha2): string
    {
        $chars = str_split(strtoupper($alpha2));

        return mb_convert_encoding('&#'.(0x1F1E6 + (ord($chars[0]) - 65)).';&#'.(0x1F1E6 + (ord($chars[1]) - 65)).';', 'UTF-8', 'HTML-ENTITIES');
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('geo_country')->update(['flag_emoji' => null]);
    }
};
