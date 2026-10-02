<?php

namespace Tests\Feature;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Sys\SecUser;
use App\Services\Master\MaterialCategoryService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Material Category = pembentuk KODE ITEM (docs/globaluserrules.md B.16). Pagar: rantai Category 1 -> 5 di mat_category harus
// sama dengan sumbernya (docs/ddl/doc_rmprefix1.sql), aturan simpan menjaga rantai, layar + cetakan menampilkan kode yang benar.
// Test yang menulis memakai nama berawalan ZQTEST dan membersihkan sendiri di tearDown().
class MaterialCategoryChainTest extends TestCase
{
    private const MARK = 'ZQTEST';

    // Penyimpangan data asal yang SUDAH DIKETAHUI (matcategory_delta.md §5.7) -- belum diperbaiki, menunggu keputusan user.
    // Yang lain dari daftar ini = rantai rusak baru.
    private const KNOWN_NAME_DIFF = ['2#26', '2#27', '4#107'];

    private const KNOWN_MISSING = ['2#54', '2#66', '4#158'];

    private const KNOWN_BROKEN = ['ZETLA BROWN'];

    protected function tearDown(): void
    {
        MatRaw::query()->where('material_code', 'like', self::MARK.'%')->delete();
        for ($level = MaterialCategoryService::LEVELS; $level >= 1; $level--) {
            MatCategory::query()->where('level', $level)->where('category_name', 'like', self::MARK.'%')->delete();
        }
        parent::tearDown();
    }

    private function service(): MaterialCategoryService
    {
        return app(MaterialCategoryService::class);
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    /** @return array<int, list<array{id: int, names: list<string>, prefs: list<string>}>> baris sumber per tingkat */
    private function source(): array
    {
        $out = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];
        preg_match_all('/INSERT INTO `doc_rmprefix(\d)` VALUES \((.*)\);/', file_get_contents(base_path('docs/ddl/doc_rmprefix1.sql')), $rows, PREG_SET_ORDER);
        foreach ($rows as $row) {
            $level = (int) $row[1];
            preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'|(NULL)|(\d+)/", $row[2], $tok, PREG_SET_ORDER);
            $vals = array_map(fn ($t) => ($t[3] ?? '') !== '' ? $t[3] : (($t[2] ?? '') === 'NULL' ? null : $t[1]), $tok);
            $this->assertCount(2 + 2 * $level, $vals, 'doc_rmprefix'.$level.': '.$row[2]);
            $out[$level][] = ['id' => (int) $vals[0], 'names' => array_map(fn ($v) => trim((string) $v), array_slice($vals, 1, $level)),
                'prefs' => array_map(fn ($v) => trim((string) $v), array_slice($vals, 1 + $level, $level))];
        }

        return $out;
    }

    public function test_every_chain_of_the_source_file_is_in_the_table_with_the_same_names_and_prefixes(): void
    {
        $svc = $this->service();
        $byPrefix = [];
        foreach ($svc->all() as $n) {
            $chain = $svc->chain($n);
            $byPrefix[$n->level.'|'.implode('|', array_map(fn ($c) => (string) $c->prefix_char, $chain))][] = array_map(fn ($c) => trim((string) $c->category_name), $chain);
        }
        $nameDiff = [];
        $missing = [];
        $exact = 0;
        foreach ($this->source() as $level => $rows) {
            foreach ($rows as $r) {
                $found = $byPrefix[$level.'|'.implode('|', $r['prefs'])] ?? null;
                if (! $found) {
                    $missing[] = $level.'#'.$r['id'];
                } elseif (! in_array($r['names'], $found, true)) {
                    $nameDiff[] = $level.'#'.$r['id'];
                } else {
                    $exact++;
                }
            }
        }

        $this->assertSame(self::KNOWN_NAME_DIFF, $nameDiff, 'Rantai dengan prefix sama tetapi nama berbeda dari sumber.');
        $this->assertSame(self::KNOWN_MISSING, $missing, 'Baris sumber yang tidak ada di mat_category.');
        $this->assertGreaterThanOrEqual(549, $exact);
    }

    public function test_the_table_has_no_broken_chain_and_no_clashing_item_code(): void
    {
        $svc = $this->service();
        $names = array_map(fn ($id) => $svc->all()->get($id)->category_name, array_keys($svc->problems()));
        $this->assertSame(self::KNOWN_BROKEN, array_values(array_filter($names, fn ($n) => ! str_starts_with($n, self::MARK))));

        // Induk selalu satu tingkat di atasnya; prefix sesuai ukuran tingkatnya.
        foreach ($svc->all() as $n) {
            if ($n->parent_id) {
                $this->assertSame((int) $n->level - 1, (int) $svc->all()->get($n->parent_id)->level, 'id '.$n->id_category);
            }
            $this->assertLessThanOrEqual(MaterialCategoryService::prefixLength((int) $n->level), mb_strlen((string) $n->prefix_char), 'id '.$n->id_category);
        }
    }

    public function test_item_code_is_the_prefixes_joined_along_the_chain(): void
    {
        $svc = $this->service();
        $find = function (array $names) use ($svc) {
            $parent = null;
            foreach ($names as $name) {
                $node = $svc->children($parent)->first(fn (MatCategory $n) => trim($n->category_name) === $name);
                $this->assertNotNull($node, implode(' > ', $names).' @ '.$name);
                $parent = $node->id_category;
            }

            return $parent;
        };

        $this->assertSame('PCC02002', $svc->code($find(['Paper', 'Art carton', '210gr', '65  X 100 CM', 'GOLDEN'])));
        $this->assertSame('PDA01001', $svc->code($find(['Paper', 'Duplex', '250gr', '105 X 102 CM', 'DAYASA'])));
        $this->assertSame('IOH31', $svc->code($find(['Ink', 'OFFSET', 'HN', 'AMBRI', 'AOK RED SAR'])));
        $this->assertSame('PCC', $svc->code($find(['Paper', 'Art carton', '210gr'])));
    }

    private function freePrefix(?int $parentId): string
    {
        $used = $this->service()->children($parentId)->map(fn (MatCategory $n) => mb_strtoupper((string) $n->prefix_char))->all();
        foreach (str_split('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ') as $c) {
            if (! in_array($c, $used, true)) {
                return $c;
            }
        }
        $this->fail('Tidak ada prefix bebas.');
    }

    private function rejects(callable $save, string $field): void
    {
        try {
            $save();
            $this->fail('Seharusnya ditolak: '.$field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors(), json_encode($e->errors()));
        }
    }

    public function test_save_rules_keep_the_chain_and_the_item_code_right(): void
    {
        $svc = $this->service();
        $p1 = $this->freePrefix(null);
        $root = $svc->save(null, ['level' => 1, 'category_name' => self::MARK.' Root', 'prefix_char' => strtolower($p1), 'stock_class' => 'INDIRECT'], 'test');
        $this->assertSame($p1, $root->prefix_char);
        $this->assertSame('INDIRECT', $root->stock_class);
        $this->assertNull($root->parent_id);

        // Tingkat 2 wajib di bawah tingkat 1; tingkat 3 tidak boleh langsung di bawah tingkat 1.
        $this->rejects(fn () => $svc->save(null, ['level' => 2, 'category_name' => self::MARK.' X', 'prefix_char' => 'A'], 'test'), 'parent_id');
        $this->rejects(fn () => $svc->save(null, ['level' => 3, 'parent_id' => $root->id_category, 'category_name' => self::MARK.' X', 'prefix_char' => 'A'], 'test'), 'parent_id');
        $this->rejects(fn () => $svc->save(null, ['level' => 1, 'parent_id' => $root->id_category, 'category_name' => self::MARK.' X', 'prefix_char' => $this->freePrefix(null)], 'test'), 'parent_id');

        $type = $svc->save(null, ['level' => 2, 'parent_id' => $root->id_category, 'category_name' => self::MARK.' Type', 'prefix_char' => 'T'], 'test');
        $this->assertSame($root->id_category, (int) $type->parent_id);
        $this->assertNull($type->stock_class, 'Kelas stok hanya milik Category 1.');
        $this->assertSame($p1.'T', $svc->code($type));

        // Prefix: tingkat 1-3 satu karakter, hanya huruf / angka; saudara tidak boleh berprefix atau bernama sama.
        $in = fn (array $more) => $more + ['level' => 2, 'parent_id' => $root->id_category, 'category_name' => self::MARK.' Other', 'prefix_char' => 'U'];
        $this->rejects(fn () => $svc->save(null, $in(['prefix_char' => 'AB']), 'test'), 'prefix_char');
        $this->rejects(fn () => $svc->save(null, $in(['prefix_char' => '-']), 'test'), 'prefix_char');
        $this->rejects(fn () => $svc->save(null, $in(['prefix_char' => 't']), 'test'), 'prefix_char');
        $this->rejects(fn () => $svc->save(null, $in(['category_name' => self::MARK.' type']), 'test'), 'category_name');

        // Tingkat 4 boleh sampai tiga karakter; kode gabungan tidak boleh sama dengan rantai lain (1 + 23 = 12 + 3).
        $spec = $svc->save(null, ['level' => 3, 'parent_id' => $type->id_category, 'category_name' => self::MARK.' Spec', 'prefix_char' => 'S'], 'test');
        $sizeA = $svc->save(null, ['level' => 4, 'parent_id' => $spec->id_category, 'category_name' => self::MARK.' Size A', 'prefix_char' => '1'], 'test');
        $sizeB = $svc->save(null, ['level' => 4, 'parent_id' => $spec->id_category, 'category_name' => self::MARK.' Size B', 'prefix_char' => '12'], 'test');
        $this->rejects(fn () => $svc->save(null, ['level' => 4, 'parent_id' => $spec->id_category, 'category_name' => self::MARK.' Size C', 'prefix_char' => '1234'], 'test'), 'prefix_char');
        $brand = $svc->save(null, ['level' => 5, 'parent_id' => $sizeA->id_category, 'category_name' => self::MARK.' Brand', 'prefix_char' => '23'], 'test');
        $this->assertSame($p1.'TS123', $svc->code($brand));
        $this->rejects(fn () => $svc->save(null, ['level' => 5, 'parent_id' => $sizeB->id_category, 'category_name' => self::MARK.' Brand', 'prefix_char' => '3'], 'test'), 'prefix_char');
        $this->rejects(fn () => $svc->save(null, ['level' => 6, 'parent_id' => $brand->id_category, 'category_name' => self::MARK.' Deep', 'prefix_char' => '1'], 'test'), 'level');

        // Posisi terkunci: level dan induk yang dikirim saat mengubah diabaikan.
        $moved = $svc->save($type, ['level' => 1, 'parent_id' => null, 'category_name' => self::MARK.' Type', 'prefix_char' => 'T'], 'test');
        $this->assertSame(2, (int) $moved->level);
        $this->assertSame($root->id_category, (int) $moved->parent_id);

        // Prefix yang sudah dipakai entri di bawahnya terkunci.
        $this->rejects(fn () => $svc->save($type->fresh(), ['category_name' => self::MARK.' Type', 'prefix_char' => 'V'], 'test'), 'prefix_char');

        // Tidak bisa menambah di bawah induk nonaktif; tidak bisa mengaktifkan di bawah induk nonaktif.
        $svc->setActive($sizeB->fresh(), false, 'test');
        $this->rejects(fn () => $svc->save(null, ['level' => 5, 'parent_id' => $sizeB->id_category, 'category_name' => self::MARK.' Brand B', 'prefix_char' => '9'], 'test'), 'parent_id');
        $svc->setActive($spec->fresh(), false, 'test');
        $this->rejects(fn () => $svc->setActive($sizeB->fresh(), true, 'test'), 'activate');

        $this->assertSame([], array_filter($svc->problems(), fn ($id) => str_starts_with($svc->all()->get($id)->category_name, self::MARK), ARRAY_FILTER_USE_KEY));
    }

    public function test_renaming_a_category_renames_it_on_the_materials_that_use_it(): void
    {
        $svc = $this->service();
        $root = $svc->save(null, ['level' => 1, 'category_name' => self::MARK.' Root', 'prefix_char' => $this->freePrefix(null)], 'test');
        $type = $svc->save(null, ['level' => 2, 'parent_id' => $root->id_category, 'category_name' => self::MARK.' Type', 'prefix_char' => 'T'], 'test');
        $item = MatRaw::create(['material_code' => self::MARK.'-1', 'material_name' => self::MARK.' item', 'item_type' => 'MATERIAL',
            'category1' => $root->category_name, 'category2' => $type->category_name]);
        $this->assertSame(1, $svc->usage($type->fresh()));

        // Prefix terkunci selama ada item yang memakainya; nama boleh diganti dan item ikut.
        $this->rejects(fn () => $svc->save($type->fresh(), ['category_name' => self::MARK.' Type', 'prefix_char' => 'V'], 'test'), 'prefix_char');
        $svc->save($type->fresh(), ['category_name' => self::MARK.' Type Renamed', 'prefix_char' => 'T'], 'test');

        $this->assertSame(self::MARK.' Type Renamed', $item->fresh()->category2);
        $this->assertSame($root->category_name, $item->fresh()->category1);
        $this->assertSame(1, $svc->usage($type->fresh()));
    }

    public function test_code_chain_object_links_the_dropdowns_and_builds_the_code(): void
    {
        $nodes = [[1, 0, 1, 'Paper', 'P', 1], [2, 0, 1, 'Ink', 'I', 1], [3, 1, 2, 'Art carton', 'C', 1], [4, 1, 2, 'Duplex', 'D', 0], [5, 3, 3, '210gr', 'C', 1], [6, 2, 2, 'OFFSET', 'O', 1]];
        $html = Blade::render('<x-dl::code-chain name="category" :nodes="$nodes" :levels="[\'Category 1\', \'Category 2\', \'Category 3\']" :value="5" code-label="Item Code" add-url="/x?new={level}&parent={parent}" />', ['nodes' => $nodes]);

        $this->assertStringContainsString('data-dl-chain', $html);
        $this->assertMatchesRegularExpression('/data-cc-code>PCC</', $html);
        $this->assertStringContainsString('name="category" value="5"', $html);
        $this->assertStringContainsString('name="category_code" value="PCC"', $html);
        // Category 2 hanya berisi anak Paper yang aktif (Duplex nonaktif dan OFFSET milik Ink tidak ikut).
        $this->assertStringContainsString('data-value="3">Art carton</div>', $html);
        $this->assertStringNotContainsString('>Duplex', $html);
        $this->assertStringNotContainsString('data-value="6"', $html);
        // Add di tingkat 3 = entri baru di bawah Art carton.
        $this->assertStringContainsString('/x?new=3&amp;parent=3', $html);

        $all = Blade::render('<x-dl::code-chain name="category" :nodes="$nodes" :levels="[\'Category 1\', \'Category 2\', \'Category 3\']" :value="1" inactive />', ['nodes' => $nodes]);
        $this->assertStringContainsString('data-value="4">Duplex', $all);
        $this->assertStringContainsString('disabled', $all, 'Category 3 terkunci sampai Category 2 dipilih.');
    }

    public function test_screen_form_and_print_show_the_chain_and_the_item_code(): void
    {
        $svc = $this->service();
        $golden = $svc->all()->first(fn (MatCategory $n) => $svc->code($n) === 'PCC02002' && (int) $n->level === 5);
        $this->assertNotNull($golden);
        $this->actingAs($this->admin());

        $this->get('/v2/s/md-matcat')->assertOk()->assertSee('data-dl-chain', false)->assertSee('Category 5');
        $this->get('/v2/s/md-matcat?id='.$golden->id_category)->assertOk()->assertSee('PCC02002')->assertSee('GOLDEN')->assertSee('name="prefix_char"', false);
        $this->get('/v2/s/md-matcat?new=2&parent='.$svc->chain($golden)[0]->id_category)->assertOk()->assertSee('name="parent_id"', false);
        // Entri baru tingkat 3 tidak boleh ditawarkan di bawah Category 1.
        $this->get('/v2/s/md-matcat?new=3&parent='.$svc->chain($golden)[0]->id_category)->assertNotFound();
        $this->get('/v2/report/md-matcat/print')->assertOk()->assertSee('PCC02002')->assertSee('Category 5');
    }

    public function test_saving_from_the_screen_creates_the_entry_under_the_picked_chain(): void
    {
        $svc = $this->service();
        $paper = $svc->children(null)->first(fn (MatCategory $n) => $n->category_name === 'Paper');
        $prefix = $this->freePrefix($paper->id_category);

        $this->actingAs($this->admin())->post('/v2/material-category/save', ['level' => 2, 'parent_id' => $paper->id_category, 'category_name' => self::MARK.' Board', 'prefix_char' => $prefix])
            ->assertRedirect()->assertSessionHasNoErrors();
        $saved = MatCategory::query()->where('category_name', self::MARK.' Board')->firstOrFail();
        $this->assertSame($paper->id_category, (int) $saved->parent_id);
        $this->assertSame('P'.$prefix, app(MaterialCategoryService::class)->code($saved->id_category));

        $this->post('/v2/material-category/save', ['id' => $saved->id_category, 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse((bool) $saved->fresh()->activate);

        // Prefix yang sudah dipakai saudaranya ditolak dan tidak ada baris baru.
        $this->from('/v2/s/md-matcat?new=2&parent='.$paper->id_category)
            ->post('/v2/material-category/save', ['level' => 2, 'parent_id' => $paper->id_category, 'category_name' => self::MARK.' Clash', 'prefix_char' => $prefix])
            ->assertSessionHasErrors('prefix_char');
        $this->assertSame(0, MatCategory::query()->where('category_name', self::MARK.' Clash')->count());
    }
}
