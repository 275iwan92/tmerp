<?php

namespace App\Support\V2\Grid;

// Daftar semua grid DB (turunan DbGrid). Menu v2 menunjuk ke sini lewat sys_app_menu.target = 'v2:<slug>'.
final class Grids
{
    private const ALL = [
        Hrm\EmployeeGrid::class,
        Hrm\PositionGrid::class,
        Hrm\SopGrid::class,
        Hrm\CorrectionRequestGrid::class,
        Hrm\LeaveRequestGrid::class,
        Hrm\GpsApprovalGrid::class,
        Hrm\AttendanceGrid::class,
        Hrm\PayrollRunGrid::class,
        Hrm\PayslipGrid::class,
        Hrm\SalaryGrid::class,
        Hrm\BankAccountGrid::class,
        Hrm\ContactGapGrid::class,
        Hrm\DisciplineGrid::class,
        Master\CustomerGrid::class,
        Master\SupplierGrid::class,
        Master\RawMaterialGrid::class,
        Master\MatCategoryGrid::class,
        Master\WarehouseGrid::class,
        Master\ShiftGrid::class,
        Prod\MachineGrid::class,
        Prod\ProcessGrid::class,
        Prod\RoutingTemplateGrid::class,
        Prod\CrewGrid::class,
        Prod\ScrapTypeGrid::class,
        Sales\ProductMarketGrid::class,
        Sales\CostingListGrid::class,
        Sales\WinRateGrid::class,
        Sales\CoOpenReport::class,
        Sales\CoAllReport::class,
        Sales\CoStatusReport::class,
        Sales\CoShipSummaryReport::class,
        Sales\CoShipDetailReport::class,
        Sales\CoPriceReport::class,
        Sales\CoTotalSalesReport::class,
        Sales\CoOutstandingReport::class,
        Acc\CoaGrid::class,
    ];

    public static function find(string $slug): ?DbGrid
    {
        foreach (self::ALL as $class) {
            $g = new $class;
            if ($g->slug() === $slug) {
                return $g;
            }
        }

        return null;
    }

    public static function screen(string $slug): ?array
    {
        return self::find($slug)?->screen();
    }

    /** @return DbGrid[] */
    public static function all(): array
    {
        return array_map(fn ($c) => new $c, self::ALL);
    }
}
