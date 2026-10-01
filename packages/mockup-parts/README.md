# mockup-parts (diparkir untuk mockup.test — Fase 2)

Komponen **khusus** yang dikeluarkan dari DevLib (keputusan user 2026-09-24: DevLib hanya komponen dasar & layar standar).
Belum dimuat aplikasi mana pun. Saat mockup.test dibangun, paket ini didaftarkan di sana dan tiap
komponen menjadi turunan objek DevLib (namespace `MockupParts\Components`, JS `Devlib.register(...)`).

| Komponen | PHP | JS | Demo |
|---|---|---|---|
| Dieline v1 / v2 / v3 (TMDieline, zona beku: esensi tidak diubah; **v3 dipakai aplikasi**) | Dieline, DielineV2, DielineV3 | dieline.js, dieline-x.js, tmdieline.js | dieline*.blade.php |
| Imposition (penampil hasil ImpositionEngine) | Imposition | imposition.js | imposition.blade.php |
| ProDev workbench (salinan v1; kulit terang `prodev-light.css`; panel atas = panel form standar, Print / Email berpilihan Master Card + ACC Digital) | Prodev\Workbench | prodev/workbench.js | — (layar v2 `prodev`) |
| ProDev Part & Dieline (ProDev mengubah dieline + artwork part; simpan `iqp_part` + `prod_part`) | Prodev\PartDieline | prodev/part-dieline.js | — (layar v2 `prodev`, tab Part & Dieline) |
| Formasi Harian | Formation | formation.js | formation.blade.php |
| Roster Board | RosterBoard | roster.js | roster-board.blade.php |
| Flow Config | FlowConfig | flow-config.js | flow-config.blade.php |
| ID Card | IdCard | — | id-cards.blade.php |

CSS: `resources/assets/css/mockup-parts.css` (memakai token `--dl-*` dari devlib.css).
Data demo: `data/*.json`; pembaca data lama DemoData (dipindah dari devlib):

```php
    // Tipe produk + dieline_spec + parameter + ukuran pasaran, diekspor BACA-SAJA dari tm2mst (resources/data/dieline_types.json).
    public static function dielineTypes(): array
    {
        return json_decode(file_get_contents(resource_path('data/dieline_types.json')), true);
    }

    // Hasil ImpositionEngine asli (dijalankan baca-saja) + dieline tiap job (resources/data/imposition_demo.json).
    public static function imposition(): array
    {
        $d = json_decode(file_get_contents(resource_path('data/imposition_demo.json')), true);
        $types = self::dielineTypes();
        foreach ($d['jobs'] as &$j) {
            $j['spec'] = $types[$j['type']]['spec'];
        }

        return $d;
    }


```

Menunggu port dari tmerp: workbench Imposition ProDev (accordion) + Master Card (`pages/production/prodev.blade.php`).
