<?php

namespace Tests\Feature;

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecGroupPermission;
use App\Models\Sys\SecModule;
use App\Support\V2\Data\GroupPermissions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Groups on Modules v2: tingkat matriks <-> tanda sec_group_permission. Membuat grup uji sendiri dan membersihkannya.
class GroupPermissionsTest extends TestCase
{
    private SecGroup $group;

    private int $moduleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = SecGroup::create(['group_code' => 'ZZTEST', 'group_name' => 'ZZ Test Group', 'mgmt_level' => 1, 'activate' => true]);
        $this->moduleId = (int) SecModule::query()->value('id_module');
    }

    protected function tearDown(): void
    {
        SecGroupPermission::where('group_id', $this->group->id_group)->delete();
        $this->group->delete();
        parent::tearDown();
    }

    public function test_levels_map_to_flags_and_back(): void
    {
        foreach ([0, 1, 2, 3, 4] as $level) {
            GroupPermissions::save('ZZ Test Group', [$this->moduleId => $level], 'test');
            $row = SecGroupPermission::where('group_id', $this->group->id_group)->where('module_id', $this->moduleId)->first();
            $this->assertSame($level, $row ? GroupPermissions::levelOf($row) : 0);
            $this->assertSame($level, GroupPermissions::levels()['ZZ Test Group'][(string) $this->moduleId] ?? 0);
        }
    }

    public function test_unchanged_level_is_not_rewritten(): void
    {
        $this->assertSame(1, GroupPermissions::save('ZZ Test Group', [$this->moduleId => 2], 'test'));
        $this->assertSame(0, GroupPermissions::save('ZZ Test Group', [$this->moduleId => 2], 'test'));
    }

    public function test_invalid_level_or_module_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        GroupPermissions::save('ZZ Test Group', [$this->moduleId => 9], 'test');
    }

    public function test_unknown_group_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        GroupPermissions::save('No Such Group', [], 'test');
    }
}
