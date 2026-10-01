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
        Master\HolidayCalendarGrid::class,
        Master\UomUnitLookup::class,
        Master\UomConversionLookup::class,
        Master\CurrencyLookup::class,
        Master\ExchangeRateLookup::class,
        Master\SupplierCategoryLookup::class,
        Master\OfficeHoursLookup::class,
        Master\CustomerReceivingHoursLookup::class,
        Master\DeliveryDelayReasonsLookup::class,
        Prod\MachineGrid::class,
        Prod\MachineModelLookup::class,
        Prod\ProcessGrid::class,
        Prod\RoutingTemplateGrid::class,
        Prod\CrewGrid::class,
        Prod\ScrapTypeGrid::class,
        Prod\NoOutputReport::class,
        Sales\ProductMarketGrid::class,
        Sales\InquiryNewGrid::class,
        Sales\ProdevNewGrid::class,
        Sales\ProdevReviseGrid::class,
        Sales\CostingListGrid::class,
        Sales\CostingReviseGrid::class,
        Sales\CostingRecalcGrid::class,
        Sales\WinRateGrid::class,
        Sales\CoSummaryReport::class,
        Sales\CoOrderReport::class,
        Sales\CoShipDetailReport::class,
        Sales\GeneralCoSummaryReport::class,
        Sales\GeneralCoOrderReport::class,
        Sales\GeneralCoShipDetailReport::class,
        Acc\CoaGrid::class,
        Maint\MaintenanceCodeLookup::class,
        Maint\MaintenanceWoReport::class,
        Setup\TranslationDictionaryLookup::class,
        Planning\MaterialBookingGrid::class,
        Planning\MaterialPlanningGrid::class,
        Planning\DemandForecastLookup::class,
        Planning\UsageStandardLookup::class,
        Planning\PurchaseLeadTimeLookup::class,
        Rcr\RequisitionGrid::class,
        Rcr\VacancyGrid::class,
        Rcr\ApplicationGrid::class,
        Rcr\InterviewGrid::class,
        Rcr\AssessmentGrid::class,
        Rcr\OfferGrid::class,
        Rcr\ProvisionGrid::class,
        Rcr\SyncLogReport::class,
        Prod\WorkOrderGrid::class,
        Prod\RuleLibraryGrid::class,
        Pur\OutstandingPrReport::class,
        Pur\OutstandingPoReport::class,
        Pur\OutstandingPvReport::class,
        Inv\RmStockInGrid::class,
        Inv\RmStockOutGrid::class,
        Inv\RmStockReturnGrid::class,
        Inv\RmStockAdjGrid::class,
        Inv\FgStockInGrid::class,
        Inv\FgStockOutGrid::class,
        Inv\FgStockReturnGrid::class,
        Inv\FgStockAdjGrid::class,
        Inv\SfgStockInGrid::class,
        Inv\SfgStockOutGrid::class,
        Inv\SfgStockReturnGrid::class,
        Inv\SfgStockAdjGrid::class,
        Inv\RmStockBalanceReport::class,
        Inv\RmStockCardReport::class,
        Inv\RmSlowMovingReport::class,
        Inv\FgStockBalanceReport::class,
        Inv\FgStockCardReport::class,
        Inv\FgSlowMovingReport::class,
        Inv\SfgStockBalanceReport::class,
        Inv\SfgStockCardReport::class,
        Inv\SfgSlowMovingReport::class,
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
