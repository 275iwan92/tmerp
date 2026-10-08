<?php

namespace App\Support\Engines;

use App\Services\Acc\TaxEngine;
use App\Services\Costing\CostingEngine;
use App\Services\Costing\ReverseCostingService;
use App\Services\Flow\FlowEngineService;
use App\Services\Imposition\ImpositionEngine;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseStock;
use App\Services\Maintenance\KpiService;
use App\Services\Maintenance\OrderService;
use App\Services\Maintenance\PartSupplyService;
use App\Services\Maintenance\PlanService;
use App\Services\Maintenance\RequestService;
use App\Services\Payroll\PayrollEngineService;
use App\Services\Planning\MaterialPlanningEngine;
use App\Services\ProDev\SpecEngine;
use App\Services\ProDev\UsageRateEngine;
use App\Services\Production\RoutingTemplateService;
use App\Services\Production\SpeedTarget\SpeedTargetEngine;
use App\Services\Pur\PqMatcher;
use App\Services\Sales\CoOrderBuilder;
use App\Services\Sales\CpdelPromiseService;
use App\Services\Scheduling\InterruptionOptionGenerator;
use App\Services\Scheduling\Learning\LearningEngine;
use App\Services\Scheduling\MaintenanceBlockProvider;
use App\Services\Scheduling\Operator\OperatorTimerService;
use App\Services\Scheduling\PdrEngine;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\GateService;
use App\Services\Scheduling\Plan\PlanConfirmService;
use App\Services\Scheduling\Plan\ScheduleSolver;
use App\Services\Scheduling\Plan\SetupTimeEngine;
use App\Services\Scheduling\Plan\WoPlanningEnricher;
use App\Services\Scheduling\SchedulingRuleEngine;
use App\Services\Scheduling\WoGenerationService;
use Assistant\Engine as AssistantEngine;
use Devlib\Engine\EngineInspector;

// Daftar ENGINE tmerp (user 2026-10-02): satu tempat yang menjelaskan tiap engine -- kegunaan, input, output, cara kerja,
// tabel yang dibaca / ditulis, setelan, layar pemakai, test. Ditampilkan objek DevLib x-dl::engine-catalog di devlib.test
// (lewat resources/data/v2/engines.json, perintah `php artisan engine:catalog`) dan di /v2/engines.
// Tanda tangan method TIDAK ditulis di sini: dibaca dari kodenya (Devlib\Engine\EngineInspector), jadi tidak bisa basi.
// Engine baru (kelas *Engine / *EngineService di app/Services) WAJIB didaftarkan di sini -- dijaga EngineCatalogTest.
// Teks English (bahasa dasar, LOCKED 2026-10-01). Rumus harga ProCost rahasia: tulis alurnya saja, tanpa angka / rumus.
final class EngineRegistry
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            [
                'key' => 'dieline', 'name' => 'Dieline Engine (TMDieline)', 'group' => 'Inquiry Product', 'mode' => 'calc',
                'purpose' => 'Draws the flat dieline of a product type from its dimensions, live while Sales types the size.',
                'file' => 'resources/js/tmdieline.js',
                'calls' => [
                    ['TMDieline.render(el, spec, dims)', 'Draws the dieline into an element; shows a hint when the type has no spec or the size is incomplete.'],
                    ['TMDieline.svg(spec, dims): string|null', 'Returns the SVG text only.'],
                    ['TMDieline.flat(spec, dims)', 'Returns the flat (unfolded) width and height.'],
                ],
                'inputs' => [
                    ['spec', 'The dieline recipe of the product type (prod_type.dieline_spec): canvas size, variables and a list of elements (cut, crease, bleed, hole) written as formulas.'],
                    ['dims', 'The sizes typed on the form: product length (A), width (B), height (C), diameter, lid height, glue flap, tuck flaps, dust flap, overflow margin.'],
                ],
                'outputs' => [
                    ['SVG drawing', 'Cut lines red, crease lines green, bleed grey dashed, holes blue.'],
                    ['Flat size', 'Width and height of the unfolded sheet, used by Pro-Dev as the starting size for imposition.'],
                ],
                'steps' => [
                    'Reads the short names (A, B, C, ...) and maps them to the form fields.',
                    'Evaluates every formula of the recipe with those values (plus, minus, times, divide, max, min, round ...). No geometry is hard coded.',
                    'Computes the canvas width and height; without a valid size nothing is drawn.',
                    'Draws each element of the recipe in order and returns one SVG.',
                ],
                'reads' => ['tm2mst.prod_type.dieline_spec'],
                'used' => ['Inquiry Product (New / Revise / Recalc)', 'Pro-Dev > Part & Dieline', 'Master Card Collection'],
                'tests' => ['tests/Unit/TmDielineTest.php'],
                'docs' => ['docs/mockups/inquiry_product_costing/inquiryproduct_tier1.md'],
            ],
            [
                'key' => 'imposition', 'name' => 'Imposition Engine', 'group' => 'Pro-Dev', 'mode' => 'suggest',
                'purpose' => 'Finds how many pieces (UP) fit on a plano sheet and which layout wastes the least paper.',
                'class' => ImpositionEngine::class,
                'methods' => ['run', 'sweepFlap', 'sweepInterlock', 'bestSheet', 'combinePair', 'dieRuleMeterLari', 'cells', 'applyFlap'],
                'notes' => [
                    'run' => 'One plano, one flat size: the basic layout.',
                    'sweepFlap' => 'Level 1: tries every flap length in the allowed range and ranks the results.',
                    'sweepInterlock' => 'Level 2: also tries the "69" interlock, the second copy turned 180 degrees and shifted.',
                    'bestSheet' => 'Tool #2: tries all active standard planos and ranks them by paper used.',
                    'combinePair' => 'Tool #10: two parts of one product on the same sheet, compared with separate sheets.',
                    'dieRuleMeterLari' => 'Tool #4: running metres of cutting and creasing rule for the die.',
                    'cells' => 'Position of every piece on the sheet (mm), for the drawing.',
                    'applyFlap' => 'Flat size = body plus flap on the chosen side.',
                ],
                'inputs' => [
                    ['planoW, planoH', 'Plano sheet size in mm.'],
                    ['flatW, flatH', 'Flat (unfolded) size of one piece in mm.'],
                    ['gap', 'Distance between pieces in mm.'],
                    ['gripper', 'Strip the press needs to grip the sheet, in mm.'],
                    ['margin', 'Free edge around the sheet in mm.'],
                    ['flapMin, flapMax, flapSide', 'Allowed flap length range and the side the flap sits on (sweep tools).'],
                    ['targetQty', 'Order quantity; when given, the best sheet is judged on the sheets really needed for it.'],
                ],
                'outputs' => [
                    ['up', 'Pieces per sheet.'],
                    ['method', 'TEGAK (upright), PUTAR (rotated) or CAMPUR (main block plus a rotated block in the rest area).'],
                    ['efficiency_pct, waste_pct', 'Share of the usable area that is used / wasted.'],
                    ['layout', 'Columns, rows and piece size per block: the data behind the drawing.'],
                    ['area_w, area_h', 'Usable area after margin and gripper.'],
                    ['recommend, saving_pct', 'combinePair only: COMBINED, SEPARATE or NONE, and the area saved per set.'],
                ],
                'steps' => [
                    'Usable area = plano minus margins and gripper.',
                    'Counts pieces upright and rotated; the higher count wins.',
                    'Tries the mixed layout: upright block first, then rotated pieces in the remaining strip; kept only when it gives more pieces.',
                    'Efficiency = area of all pieces divided by the usable area.',
                    'The sweep tools repeat this for every flap length (and shift), then rank by UP, then by efficiency.',
                    'The officer picks one of the top results. Nothing is saved by the engine.',
                ],
                'reads' => ['tm2mst.mat_plano_size (bestSheet)'],
                'writes' => ['Nothing: pure calculation. The Pro-Dev screen saves the chosen layout to prod_layout_group.'],
                'used' => ['Pro-Dev workbench (Imposition tab)', 'Costing Engine (flat area per part)'],
                'tests' => ['tests/Unit/ImpositionEngineProDevToolsTest.php'],
                'docs' => ['docs/mockups/inquiry_product_costing/productdevelopment_tier1.md §4.3'],
            ],
            [
                'key' => 'spec', 'name' => 'Spec Engine (BOP · BOM · Ink)', 'group' => 'Pro-Dev', 'mode' => 'suggest',
                'purpose' => 'Builds the production specification of a part: process steps, materials and ink configuration.',
                'class' => SpecEngine::class,
                'methods' => ['generate', 'persist', 'forWorkOrder', 'saved', 'tierQty', 'stockFor'],
                'notes' => [
                    'generate' => 'Calculates the three sections. Writes nothing.',
                    'persist' => 'Saves the result to the part; sections the officer overrode or typed by hand are left alone.',
                    'forWorkOrder' => 'BOP and BOM for one Work Order quantity, from the layout Pro-Dev already saved.',
                    'saved' => 'Rows on the part that do not come from the engine (override / manual).',
                ],
                'inputs' => [
                    ['part', 'The master part (prod_part).'],
                    ['iqp', 'The inquiry part: finishing, material, colour notation, quantity per pack, order quantity, artwork.'],
                    ['ctx', 'Layout decisions of Pro-Dev: sheet size, plano size, sheets per plano, press, die-cut machine, UP, order quantity, artwork colours.'],
                    ['overrides', 'Sections the officer changed (bop, bom, inks); null = follow the engine.'],
                ],
                'outputs' => [
                    ['bop', 'Bill of Process: steps in order with the suggested machine.'],
                    ['bom', 'Bill of Material: material, quantity, unit, waste.'],
                    ['inks', 'Ink configuration per colour.'],
                    ['warnings', 'What is missing or unusual (no machine fits, no rule for a finishing ...).'],
                    ['override', 'The officer sections, normalised, ready to save.'],
                ],
                'steps' => [
                    'Reads the inquiry input and the layout decisions.',
                    'Applies the admin rules in prod_bop_rule to turn finishing and material into process steps and usage rates.',
                    'Calculates sheets, material quantities and ink per colour for the order quantity.',
                    'Returns the result with a source per row: ENGINE, OVERRIDE (officer changed it) or MANUAL (old / typed data).',
                    'On Save, only ENGINE sections are rewritten; "back to the engine result" removes an override.',
                ],
                'reads' => ['tm2mst.prod_bop_rule', 'tm2mst.prod_process', 'tm2mst.mat_raw', 'tm2sdi.iqp_part_material / _process / _ink', 'tm2sdi.iqp_layout', 'tm2mst.prod_layout_group'],
                'writes' => ['tm2mst.prod_part_process', 'tm2mst.prod_part_material', 'tm2mst.prod_part_ink (persist only)'],
                'settings' => ['Setup > BOP Rules (prod_bop_rule)'],
                'used' => ['Pro-Dev workbench', 'Work Order Generation', 'Master Product sync'],
                'docs' => ['docs/BACKLOG.md §18'],
            ],
            [
                'key' => 'usage-rate', 'name' => 'Usage Rate Engine', 'group' => 'Pro-Dev', 'mode' => 'suggest',
                'purpose' => 'Learns material usage rates and waste percentages from real production data and proposes new values for the BOP rules.',
                'class' => UsageRateEngine::class,
                'methods' => ['refresh', 'minSample'],
                'notes' => ['refresh' => 'Runs monthly (prodev:usage-rates) or on demand.'],
                'inputs' => [
                    ['from', 'Start date of the production data to learn from.'],
                    ['apply', 'true = write the proposals; false = only show them.'],
                ],
                'outputs' => [
                    ['rules', 'Per rule: current rate, proposed rate, sample size.'],
                    ['applied', 'How many rules were changed automatically (only rules marked auto apply).'],
                    ['sample_total, from, note', 'Size of the sample, the period and a remark when there is too little data.'],
                ],
                'steps' => [
                    'Proposed rate = current rate x (material really used / material planned).',
                    'Planned = quantity required on the Work Order; real = issued from the warehouse minus returned.',
                    'Waste per process = defect / (good + defect) from the production output records.',
                    'Below the minimum sample nothing is proposed.',
                    'Values typed by an officer are never overwritten; a rule is changed without asking only when it is marked auto apply.',
                ],
                'reads' => ['tm2prd.prd_wo', 'tm2prd.prd_wo_material', 'tm2prd.prd_wo_operation_output (+ _defect)', 'tm2inv.v_inv_wo_reconcile'],
                'writes' => ['tm2mst.prod_bop_rule (proposal columns; the rate itself only with auto apply)'],
                'settings' => ['Setup > BOP Rules'],
                'used' => ['Scheduled command prodev:usage-rates (monthly)', 'Setup > BOP Rules'],
            ],
            [
                'key' => 'routing', 'name' => 'Routing Template Engine', 'group' => 'Pro-Dev', 'mode' => 'suggest',
                'purpose' => 'Checks the order of process steps and turns a routing template into ready steps with a suggested machine.',
                'class' => RoutingTemplateService::class,
                'methods' => ['validate', 'validateTemplate', 'expand', 'suggestFor'],
                'inputs' => [
                    ['steps', 'Steps to check: process, sequence, branch. May be an unsaved draft.'],
                    ['templateId', 'The routing template to check or to apply.'],
                    ['constraints', 'Sheet length, sheet width, gsm: machines that cannot take the size are not suggested.'],
                    ['prodTypeCode, categoryId', 'The product, to find the templates that fit it.'],
                ],
                'outputs' => [
                    ['BLOCK', 'Order that is technically impossible (stripping before die-cutting).'],
                    ['WARN', 'Unusual order that still has real cases; never stops the officer.'],
                    ['Steps', 'Each step with the suggested machine and its resolved parameters.'],
                    ['Templates', 'Templates that fit the product; the default first, never chosen silently when there is no default.'],
                ],
                'steps' => [
                    'Compares each pair of steps with the precedence table.',
                    'For each step, picks a machine from the process x machine capability matrix that fits the sheet size and gsm.',
                    'Parameters come from three layers: the process x machine pair, then the machine, then the process default.',
                ],
                'reads' => ['tm2mst.prod_routing_template / _step', 'tm2mst.prod_process_precedence', 'tm2mst.prod_process_machine', 'tm2mst.prod_machine'],
                'writes' => ['Nothing.'],
                'used' => ['Pro-Dev (BOP)', 'Setup > Routing Template'],
            ],
            [
                'key' => 'costing', 'name' => 'Costing Engine (Pro-Cost)', 'group' => 'Pro-Cost', 'mode' => 'calc',
                'purpose' => 'Calculates the cost price (HPP) of every part and the selling price per quantity tier of an inquiry.',
                'class' => CostingEngine::class,
                'methods' => ['run'],
                'restricted' => 'The cost recipe is confidential (Costing and Directors only). This page shows the flow, not the formulas or rates.',
                'inputs' => [
                    ['costing', 'The costing document (iqp_costing) with its parts, coatings and price tiers.'],
                    ['Part type', 'DC, DC+SF, DC+SHEET, KRAFT (offset family) or SHEET, SHEET_DIGITAL (corrugated).'],
                    ['Part data', 'Paper and grammage, print size, plano size, UP, coating, lamination, tooling, quantity per set, tolerance.'],
                    ['Tier data', 'Quantity, tolerance, margin (non VAT and VAT), rounding step and mode.'],
                    ['Data source', 'Mode 1: pulled from the confirmed Pro-Dev layout. Mode 2: typed by the officer. Both must give the same HPP for the same geometry.'],
                ],
                'outputs' => [
                    ['Per part', 'Paper, sheet, process, coating, lamination and tooling cost per piece, and the part total.'],
                    ['Header', 'Total material, process and finishing cost per piece, and tooling for the order.'],
                    ['Per tier', 'HPP per piece, price after tolerance, after rounding, after margin, and the price with VAT.'],
                    ['Status', 'The document becomes CALCULATED.'],
                ],
                'steps' => [
                    'Takes the main tier quantity as the base quantity.',
                    'Calculates every part with the rule of its type (offset family or corrugated).',
                    'Rolls the parts up to the header, weighted by quantity per set.',
                    'For every tier: adds tooling divided by that quantity, applies tolerance (rule differs per part type), rounding, margin and VAT.',
                    'Everything runs in one transaction; rates are frozen on the document, so later master changes do not move an old costing.',
                ],
                'reads' => ['tm2sdi.iqp_costing / _part / _price_tier', 'tm2mst.mat_coating_rate, mat_sheet_rate, mat_grammage, mat_flute_allowance', 'tm2sys.sys_constant (Costing group)'],
                'writes' => ['tm2sdi.iqp_costing', 'tm2sdi.iqp_costing_part', 'tm2sdi.iqp_costing_price_tier'],
                'settings' => ['Settings > Constants (Costing)'],
                'used' => ['Pro-Cost (Costing Hub)', 'Quotation (published price)'],
                'tests' => ['tests/Feature/CostingEngineTest.php', 'CostingExcelParityTest.php', 'CostingLegacyParityTest.php', 'CostingInstantModeTest.php'],
                'docs' => ['docs/mockups/inquiry_product_costing/procost_formula_library.md (restricted)'],
            ],
            [
                'key' => 'reverse-costing', 'name' => 'Reverse Costing', 'group' => 'Pro-Cost', 'mode' => 'auto',
                'purpose' => 'Turns a stand-alone (instant) costing into an integrated one by creating the documents that normally come before it.',
                'class' => ReverseCostingService::class,
                'methods' => ['reverse'],
                'inputs' => [
                    ['costing', 'An instant costing that has no inquiry yet.'],
                    ['actingLogin', 'The user who pressed Reverse.'],
                ],
                'outputs' => [
                    ['Inquiry', 'A new inquiry of type NEW linked to the costing.'],
                    ['Master Product + parts', 'Created as Draft.'],
                    ['Pro-Dev layout', 'Created as Draft; only a person can Confirm it.'],
                ],
                'steps' => [
                    'Creates the Master Product and its parts (Draft).',
                    'Creates the inquiry and its parts and links them to the costing.',
                    'Creates the Pro-Dev layout as Draft.',
                    'The numbers of the costing do not change: the parts stay "typed by officer".',
                ],
                'reads' => ['tm2sdi.iqp_costing / _part'],
                'writes' => ['tm2mst.prod_product, prod_part, prod_layout_group', 'tm2sdi.iqp_inquiry, iqp_part'],
                'used' => ['Pro-Cost (Reverse button)'],
                'docs' => ['docs/mockups/inquiry_product_costing/procost_formula_library.md §V-VI'],
            ],
            [
                'key' => 'flow', 'name' => 'Flow Engine (Approval)', 'group' => 'Approval', 'mode' => 'flow',
                'purpose' => 'Moves a document through its approval steps, the same way for every module.',
                'class' => FlowEngineService::class,
                'methods' => ['submit', 'approve', 'reject', 'hold', 'pendingForUser', 'history', 'activeConfig', 'resolveActingUser'],
                'notes' => [
                    'submit' => 'Sends the document to the first step that matches; null = no active flow, the document stays where it is.',
                    'approve' => 'Approves the waiting step and opens the next one, or ends the flow.',
                    'reject' => 'Stops the flow; a reason is required.',
                    'hold' => 'Postpones the decision; the status does not change.',
                    'pendingForUser' => 'The approval queue of one user, including positions he is acting for.',
                ],
                'inputs' => [
                    ['moduleCode', 'Which flow: CO, PR, PO, AD ...'],
                    ['docId', 'The document.'],
                    ['statusFrom', 'Status the document leaves (for example DRAFT).'],
                    ['amount', 'Document value; steps can apply to a range of amounts.'],
                    ['divisionId', 'Division, when the flow differs per division.'],
                    ['stepId, reason', 'The step being decided and the reason (reject / hold).'],
                ],
                'outputs' => [
                    ['Next step', 'The step now waiting and the position that must decide, or the final status.'],
                    ['Log', 'Every action is written to the flow log: who, when, which step, the note.'],
                ],
                'steps' => [
                    'Finds the active flow configuration of the module (and division).',
                    'Picks the first step whose status-from and amount range match.',
                    'Finds who decides: the holder of the position, or the person acting for it within the acting dates.',
                    'Approve looks for the next matching step; none left = the flow is finished.',
                    'Steps of kind AUTO or NOTIFY ONLY are executed without waiting.',
                ],
                'reads' => ['tm2mst.flow_config', 'tm2mst.flow_config_step', 'tm2mst.flow_position_acting'],
                'writes' => ['tm2mst.flow_log'],
                'settings' => ['Setup > Approval Setting', 'Setup > Acting Position'],
                'used' => ['Customer Order (CO)', 'Purchase Requisition (PR)', 'Purchase Order (PO)', 'Stock Adjustment (AD)', 'Approval Center'],
            ],
            [
                'key' => 'cpdel', 'name' => 'CPDEL Engine (Customer Plan Delivery)', 'group' => 'Sales Order', 'mode' => 'auto',
                'purpose' => 'Creates the delivery schedule when a Customer Order is confirmed.',
                'class' => CoOrderBuilder::class,
                'methods' => ['submit', 'confirm'],
                'notes' => [
                    'submit' => 'Confirm on the screen = send the order to the approval flow; null = success, otherwise the message.',
                    'confirm' => 'Runs when the last approval passes: the order becomes Confirmed and the schedule is created.',
                ],
                'inputs' => [
                    ['order', 'The Customer Order with its lines.'],
                    ['Due date', 'Required on every product line before Confirm.'],
                ],
                'outputs' => [
                    ['Delivery lines', 'One line per product line: full quantity on the due date.'],
                    ['Order status', 'Submitted, then Confirmed after the last approval.'],
                ],
                'steps' => [
                    'Refuses when a product line has no due date (or, for an order without quotation, no Master Product), or when a product still has more than one quantity line to choose from.',
                    'Sends the order to the Flow Engine (CO).',
                    'After the last approval, creates one delivery line per product line that has no manual schedule yet.',
                    'Lines already scheduled by hand are not touched.',
                    'Sales can then Split or Join the lines on the CPDEL screen.',
                ],
                'reads' => ['tm2sdi.co_order', 'tm2sdi.co_order_item'],
                'writes' => ['tm2sdi.co_order_delivery', 'tm2sdi.co_order (status)'],
                'used' => ['Customer Order', 'Approval Center'],
                'docs' => ['docs/md_for_systems/SALES_QUO_ORDER, CPDEL.md'],
            ],
            [
                'key' => 'cpdel-promise', 'name' => 'CPDEL Two Promises', 'group' => 'Sales Order', 'mode' => 'flow',
                'purpose' => 'Keeps the promise to the customer (delivery slot) and the promise of production (ready date) in line.',
                'class' => CpdelPromiseService::class,
                'methods' => ['state', 'due', 'slotProblems', 'commitReady', 'reschedule', 'customerRejected', 'opsDecision', 'merge', 'gapDays'],
                'notes' => [
                    'commitReady' => 'PPIC: when the goods will be ready; later than the slot needs a reason.',
                    'reschedule' => 'Sales: the new slot agreed with the customer, may split part of the quantity.',
                    'customerRejected' => 'Sales: the customer refused, escalate to the Operational Manager.',
                    'opsDecision' => 'Operational Manager: overtime, priority, subcontract or a new offer.',
                    'due' => 'Row colour by working days left to the slot.',
                    'slotProblems' => 'Why a slot is not valid; empty = valid.',
                ],
                'inputs' => [
                    ['d', 'One delivery line (co_order_delivery).'],
                    ['in', 'The form values of the action: date, time, reason, quantity to split.'],
                    ['login', 'Who did it.'],
                ],
                'outputs' => [
                    ['State', 'WAITING_PPIC, ALIGNED, NEED_NEGOTIATION, ESCALATED, LATE or SHIPPED.'],
                    ['Due colour', 'Past = red, today = orange, soon = yellow, shipped = grey.'],
                    ['Negotiation log', 'Every action is recorded; the first promised date never changes.'],
                ],
                'steps' => [
                    'A slot is valid only inside Telasih office hours, the customer receiving hours and on a working day.',
                    'PPIC fills the ready date; when it is later than the slot the line asks Sales to negotiate.',
                    'Sales records the new slot (or a split) agreed by WhatsApp or phone.',
                    'A refusal goes to the Operational Manager, whose decision sends the line back to PPIC.',
                ],
                'reads' => ['tm2sdi.co_order_delivery', 'tm2mst.cust_receiving_hour', 'tm2mst.sys_office_hour', 'tm2mst.sys_holiday'],
                'writes' => ['tm2sdi.co_order_delivery', 'tm2sdi.co_delivery_negotiation'],
                'settings' => ['Settings > Constants: CPDEL_DUE_SOON_DAYS', 'Setup > Work Calendar'],
                'used' => ['Customer Plan Delivery'],
                'tests' => ['tests/Feature/CpdelDueColourTest.php'],
            ],
            [
                'key' => 'material-planning', 'name' => 'Material Planning Engine (MPS · MRP · Booking)', 'group' => 'Planning', 'mode' => 'suggest',
                'purpose' => 'Works out which materials every order needs, books stock for it and shows what is short.',
                'class' => MaterialPlanningEngine::class,
                'methods' => ['plan', 'demands', 'explode', 'persist', 'confirm', 'unlock', 'runAll', 'release', 'pick'],
                'notes' => [
                    'plan' => 'The whole picture: every demand line and every material with its coverage.',
                    'demands' => 'Step 1: what must be made and when.',
                    'explode' => 'Step 2: the materials one demand needs.',
                    'persist' => 'Saves the proposed booking of a confirmed order (Draft, soft).',
                    'confirm' => 'PPIC locks the booking: stock is reserved per location.',
                    'unlock' => 'Back to Draft: stock is released and the plan recalculated.',
                    'runAll' => 'Recalculates every order (button, nightly command, order changes).',
                    'pick' => 'PPIC chooses the stock item for a line that only has a specification.',
                ],
                'inputs' => [
                    ['Orders', 'Active Customer Orders: Confirmed = firm; Draft / Submitted = shown only.'],
                    ['Forecast', 'The rest of the MPS forecast after the orders of the same month.'],
                    ['Need date', 'The earlier of the delivery date and order date plus the working days in BOOKING_NEED_WORKDAYS.'],
                    ['Stock and supply', 'Available stock, open Purchase Orders and Requisitions with their dates.'],
                ],
                'outputs' => [
                    ['lines', 'Per demand and material: needed, covered by stock / PO / PR, short.'],
                    ['materials', 'Per material: total need, available, first needed, order by (latest), coverage status.'],
                    ['Booking', 'One booking document per order (Draft or Confirmed).'],
                ],
                'steps' => [
                    'Finished goods stock of the same product is booked first.',
                    'The rest is broken down into all materials: paper from the Pro-Dev layout, then costing, then BOM; ink, varnish, coating, film and glue from the usage standards.',
                    'Netting per material: Confirmed first, then Draft, then Forecast, each by need date.',
                    'Supply is used in this order: available stock, PO on time, PR, late PO / PR, then SHORT.',
                    'A booking confirmed by PPIC is frozen: read from the database, not recalculated.',
                    'The engine only reads master data; it writes bookings and requisitions only.',
                ],
                'reads' => ['tm2sdi.co_order / _item / _delivery', 'tm2mst.prod_part, prod_part_material, mat_raw, mat_usage_standard', 'tm2inv.v_rm_available', 'tm2pur.po_line, pr_line'],
                'writes' => ['tm2inv.rm_booking', 'tm2inv.rm_booking_d'],
                'settings' => ['Settings > Constants: BOOKING_NEED_WORKDAYS'],
                'used' => ['Material Planning', 'Material Booking', 'Opening screen alert', 'Scheduled command planning:run (daily)'],
                'docs' => ['docs/materialbooking_tier1.md §6-§8'],
            ],
            [
                'key' => 'warehouse-stock', 'name' => 'Warehouse Stock (bins, capacity, dead stock)', 'group' => 'Inventory', 'mode' => 'calc',
                'purpose' => 'Tells what is in every bin, how full the bin is by weight and by volume, when the goods arrived, and which stock is dead or expired. One source for the Warehouse Map, transfers, labels and tracking.',
                'class' => WarehouseStock::class,
                'methods' => ['positions', 'binSummaries', 'stacks', 'binGeometry', 'weightKg', 'volumeM3', 'unitHeightM', 'sheetCount', 'remnantSheets', 'deadMonths', 'fullPct', 'expirySoonDays'],
                'notes' => [
                    'positions' => 'Every item that has stock in a bin now, with its arrival date, its last real movement and the dead stock flag.',
                    'binSummaries' => 'Per bin: its items, weight and volume in use, percent of capacity, status, pallets, the stack, and whether it holds dead, expired, remnant or buried stock.',
                    'binGeometry' => 'Per bin: floor area, stacking height, volume and the stack limit, taken from the storage area and the building.',
                    'stacks' => 'Per bin: its labelled pallets and packs from the top down, each with its stack number, age, estimated height and the marks remnant, old remnant and buried.',
                    'unitHeightM' => 'Estimated height of one pallet or pack in a stack; empty when it cannot be estimated.',
                    'sheetCount' => 'Number of sheets of a quantity when the unit is sheets or reams; empty for other units.',
                    'remnantSheets' => 'From how many sheets or fewer a labelled unit counts as a remnant.',
                    'weightKg' => 'Estimated weight of a quantity; empty when it cannot be estimated.',
                    'volumeM3' => 'Estimated volume of a quantity of sheets; empty when it cannot be estimated.',
                    'deadMonths' => 'The dead stock limit in months.',
                    'fullPct' => 'From which percent of its capacity a bin counts as Full.',
                    'expirySoonDays' => 'How many days before the expiry date a pallet is tagged Expires soon.',
                ],
                'inputs' => [
                    ['locationIds', 'The bins to read; empty = every bin.'],
                    ['itemFilter', 'Only these items, per stock class (used when tracking one item).'],
                    ['bins', 'The bin rows with their capacity, storage area and usable flag.'],
                    ['pallets', 'The active labelled pallets and packs, grouped per bin.'],
                ],
                'outputs' => [
                    ['Stack', 'Per bin the units from the top down: stack number (1 = bottom), pallet or pack, quantity, age, estimated height, and the marks remnant, old remnant and buried.'],
                    ['Stock positions', 'Class, item, bin, quantity, unit, value, estimated weight and volume, arrival date, last movement, idle days, dead flag.'],
                    ['Bin size', 'Floor area (m2), stacking height (m) and volume (m3) of the bin.'],
                    ['Bin status', 'EMPTY, IN_USE, FULL (weight or volume at the Full percent or more), OVER (above capacity) or BLOCKED.'],
                ],
                'steps' => [
                    'Reads the stock balance views of raw material, semi finished and finished goods: the last stock card row per item and bin.',
                    'Arrival date = the latest stock-in or opening row of the item in that bin; a bin filled only by transfers uses the latest arrival of the item anywhere.',
                    'Last movement = the latest stock card row of the item in any bin that is not a stock transfer, so moving stock never resets it.',
                    'Dead stock = that movement is older than the limit, or the item never moved. The same rule as the assistant answer and the Deadstock widget.',
                    'Bin size: floor area = area of the storage area divided by its positions; height = stacking height of the area (or the clear height of the floor) divided by the rack levels; volume = area x height.',
                    'Weight: unit KG = the quantity; sheets with grammage and size = sheets x gsm x length x width. Volume: sheets x length x width x thickness (thickness from the master, or grammage x the paper bulk constant). Anything else is unknown and left out of the capacity.',
                    'How full a bin is = the higher of the weight percent and the volume percent.',
                    'Expiry of a bin comes from its pallet labels: Expired, or Expires soon within the number of days in the constant.',
                    'Stack: on a rack the level is the bin. On the floor every labelled unit has a stack number in its bin, 1 = bottom. A pack is a remnant; an old remnant arrived longer ago than the dead stock limit.',
                    'Buried = the unit to use first for its item (a remnant first, then the earliest expiry, then the oldest arrival, compared with every unit of the item in any bin) has another pallet on top of it. A twin pallet of the same arrival does not count.',
                    'Stack height = the sum of the unit heights (sheets x thickness, plus the wooden pallet) against the stacking height of the bin. A bin with more pallets than the stack limit of its area is marked; an empty limit only monitors.',
                    'Stock in a bin that is not on a label has no known place in the stack and is reported as Stack not recorded.',
                ],
                'reads' => ['tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard', 'tm2inv.inv_pallet', 'tm2mst.wh_loc', 'tm2mst.wh_zone', 'tm2mst.wh_warehouse', 'tm2mst.mat_raw', 'tm2mst.prod_part', 'tm2mst.prod_product'],
                'writes' => [],
                'settings' => ['Settings > Constants: ASSISTANT_DEADSTOCK_MONTHS, WH_BIN_FULL_PCT, WH_PAPER_BULK_CM3_PER_G, WH_EXPIRY_SOON_DAYS, WH_REMNANT_MAX_SHEETS, WH_PALLET_BASE_CM',
                    'Inventory > Warehouse > Buildings: clear height per floor', 'Inventory > Warehouse > Storage Areas: stack height, max. stacked pallets'],
                'used' => ['Warehouse Map', 'Stack Monitor', 'Stock Transfer', 'Stock by Location', 'Print Location Label', 'Print Item Label', 'Print Pallet Label', 'Track via QR/Barcode'],
                'tests' => ['tests/Feature/StockTransferServiceTest.php', 'tests/Feature/PalletServiceTest.php'],
                'docs' => ['docs/warehouselayout_tier1.md'],
            ],
            [
                'key' => 'stock-posting', 'name' => 'Stock Document Posting (stock card order)', 'group' => 'Inventory', 'mode' => 'flow',
                'purpose' => 'Writes a stock document (Stock In, Out, Return, Adjustment, goods receipt and its reversal) to the stock card in document-date order and keeps every later balance right.',
                'class' => StockPostingService::class,
                'methods' => ['postDocument'],
                'notes' => [
                    'postDocument' => 'Posts one stock document. Refused as a whole when a balance would go below zero or the date is in a closed inventory period.',
                ],
                'inputs' => [
                    ['category', 'Stock class: RM, SFG or FG.'],
                    ['docId', 'The stock document (header with its lines: item, bin, quantity, unit price).'],
                    ['actingUser', 'Who posts.'],
                ],
                'outputs' => [
                    ['Stock card rows', 'One row per document line, placed by document date; all later rows of that item in that bin are recalculated.'],
                    ['posted / error', 'True when it worked; otherwise the reason, and nothing is written.'],
                    ['journal_warning', 'Usage journal lines that could not be made because an account is missing.'],
                    ['revaluation / stock_warning', 'How many later stock-out rows got another average price and by how much their value changed; their journals are not changed.'],
                ],
                'steps' => [
                    'Refuses a document that was posted or cancelled before, and a document dated in a closed inventory period (the correction is a Stock Adjustment dated in an open period).',
                    'Per line inserts a stock card row at its place: after every row with the same or an earlier date, before rows dated later.',
                    'Recalculates that row and all later rows of the item in that bin: opening balance, closing balance, moving average price, value (StockCardChain).',
                    'A receipt changes the average price (weighted with the stock before it); stock out, adjustment and transfer rows carry the average price of the row before.',
                    'When the average price of a transfer-out row changes, the value that moved is corrected in the destination bin and that bin is recalculated too.',
                    'When the new row or a later row would go below zero the whole document is refused (adjustments and physical counts are exempt, as before).',
                    'Marks the document Posted, updates the item total on the master, then makes the usage journal for real stock-out lines.',
                    'A posted document is never changed; a mistake is corrected with a Stock Adjustment.',
                ],
                'reads' => ['tm2inv.rm_stock / sfg_stock / fg_stock (+ lines)', 'tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard', 'tm2inv.inv_period'],
                'writes' => ['tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard', 'tm2inv.rm_stock / sfg_stock / fg_stock', 'tm2mst.mat_raw / prod_part / prod_product (cur_stock)', 'tm2acc.fin_voucher (usage journal)'],
                'used' => ['Stock In', 'Stock Out', 'Stock Return', 'Stock Adjustment & Physical Count', 'Purchase Receive (post and cancel)'],
                'tests' => ['tests/Feature/StockPostingOrderTest.php', 'tests/Feature/PvStockIntegrationTest.php'],
                'docs' => ['docs/md_for_systems/inventory_tier2.md'],
            ],
            [
                'key' => 'stock-transfer', 'name' => 'Stock Transfer Posting', 'group' => 'Inventory', 'mode' => 'flow',
                'purpose' => 'Moves stock from one bin to another, in the same warehouse or between warehouses, with or without approval, and can take the move back.',
                'class' => StockTransferService::class,
                'methods' => ['confirmErrors', 'confirm', 'submit', 'post', 'cancel', 'value', 'balance'],
                'notes' => [
                    'confirmErrors' => 'Why the transfer cannot be confirmed yet; empty = it can.',
                    'confirm' => 'Approval switched off: moves the stock. Approval switched on: sends the transfer for approval.',
                    'submit' => 'Gives the document its number and puts it in the approval queue; the stock does not move yet.',
                    'post' => 'Moves the stock: from a Draft, or after the last approval.',
                    'cancel' => 'Moves the stock back to the source bins, or withdraws a transfer that still waits for approval; needs a reason.',
                    'value' => 'Stock value of the transfer, used for the approval limits.',
                    'balance' => 'Stock of one item in one bin right now.',
                ],
                'inputs' => [
                    ['transfer', 'The transfer document with its lines: item, source bin, destination bin, quantity.'],
                    ['login', 'Who confirms, approves or cancels.'],
                    ['approved', 'True when called after the last approval.'],
                    ['reason', 'Why the transfer is cancelled.'],
                ],
                'outputs' => [
                    ['Stock card rows', 'Two rows per line: out of the source bin and into the destination bin, both marked TF.'],
                    ['Transfer number', 'TF + year + running number, issued at Confirm.'],
                    ['Pallet moves', 'Labelled pallets and packs follow the stock to the destination bin and go on top of the stack there.'],
                    ['Error text', 'Empty when it worked; otherwise the reason, and nothing is written.'],
                ],
                'steps' => [
                    'Checks every line: quantity, destination bin in the destination warehouse, bin not blocked, enough stock in the source bin (lines from the same bin are added up).',
                    'Approval (Setup > Security > Approval Levels, module TF) is switched off by default. Switched on: Confirm only sends the transfer for approval and the stock moves when the last level approves; a rejected transfer never moves stock.',
                    'Per line writes the row out of the source bin and the row into the destination bin; the value moves with the average price of the source bin.',
                    'The destination bin gets a weighted average price. The total stock of the item does not change and no journal is made.',
                    'Notes on the line whether the item was dead stock at that moment.',
                    'Pallets follow: a line made from one pallet moves that pallet; a line that empties the bin moves all its pallets; otherwise the oldest pallets move when their quantities add up exactly, else they stay and the label work list shows the difference.',
                    'Cancel writes the same two rows the other way round and brings the pallets back; it stops when the stock is no longer in the destination bin.',
                ],
                'reads' => ['tm2inv.inv_transfer', 'tm2inv.inv_transfer_line', 'tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard', 'tm2inv.inv_pallet', 'tm2mst.wh_loc', 'tm2mst.flow_config'],
                'writes' => ['tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard', 'tm2inv.inv_transfer', 'tm2inv.inv_transfer_line', 'tm2inv.inv_pallet', 'tm2inv.inv_pallet_log', 'tm2mst.flow_log'],
                'settings' => ['Setup > Security > Approval Levels: module TF (switched off by default)'],
                'used' => ['Stock Transfer', 'Stock Transfer Approval Center', 'Warehouse Map (Transfer button)', 'Track via QR/Barcode (Move this Pallet)'],
                'tests' => ['tests/Feature/StockTransferServiceTest.php', 'tests/Feature/PalletServiceTest.php'],
                'docs' => ['docs/warehouselayout_tier1.md'],
            ],
            [
                'key' => 'pallet-labels', 'name' => 'Pallet Labels (arrival label, relabel after use)', 'group' => 'Inventory', 'mode' => 'suggest',
                'purpose' => 'Keeps one label per pallet and tells which stock still needs a pallet label: goods that arrived without one, and pallets whose label no longer matches because stock was used.',
                'class' => PalletService::class,
                'methods' => ['worklist', 'create', 'relabel', 'restack', 'compact', 'kindFor', 'printed', 'followTransfer', 'revertTransfer', 'receiptLines', 'suggestedExpiry'],
                'notes' => [
                    'worklist' => 'Every stock position with its labelled pallets and what is still to do: arrival label or relabel.',
                    'create' => 'Makes the pallets of an arrival: quantity, supplier pallet code, lot and expiry date per pallet.',
                    'relabel' => 'Replaces the quantity of a pallet with what is left after use; zero closes the pallet and the units above it move down. A small remainder becomes a remnant pack.',
                    'restack' => 'Swaps a unit with the one above or below it in the stack of its bin. No stock document: the bin and the quantity do not change.',
                    'compact' => 'Renumbers the stack of one bin to 1, 2, 3 without gaps, keeping the order.',
                    'kindFor' => 'Pallet or pack: what the officer chose, otherwise a pack when sheet goods are at or below the remnant limit.',
                    'printed' => 'Counts a reprint of a pallet label.',
                    'followTransfer' => 'Moves the pallets that belong to a confirmed stock transfer.',
                    'revertTransfer' => 'Brings those pallets back when the transfer is cancelled.',
                    'receiptLines' => 'The items and bins of one posted goods receipt, with what is not on a pallet label yet.',
                    'suggestedExpiry' => 'Arrival date plus the shelf life of the item; empty when the item has no shelf life.',
                ],
                'inputs' => [
                    ['locationIds', 'The bins to look at; empty = every bin.'],
                    ['positionKey', 'One stock position: class, item, part and bin.'],
                    ['rows', 'The new pallets: quantity, supplier pallet code, lot number, expiry date.'],
                    ['common', 'Arrival date, receipt number, a note and the unit kind (pallet, pack or automatic) for all new pallets.'],
                    ['dir', 'up or down: where the unit goes in the stack.'],
                    ['kind', 'Pallet or pack; empty = decided from the remnant limit.'],
                    ['pallet', 'The pallet to relabel.'],
                    ['remaining', 'What is left on the pallet.'],
                    ['pv', 'A posted goods receipt.'],
                ],
                'outputs' => [
                    ['Label work', 'Per stock position: on hand, quantity on pallet labels, the difference, and Arrival label or Relabel after use.'],
                    ['Pallets', 'Pallet number PL + year + running number, its kind (pallet or pack), its stack number in the bin, and its history: created, relabelled, moved, restacked, closed.'],
                    ['Error text', 'Why pallets cannot be made or relabelled, for example more than what is in the bin.'],
                ],
                'steps' => [
                    'The stock card stays the only truth for quantities. A pallet is a labelled physical unit inside that stock and never changes it.',
                    'Per item and bin compares the stock on hand with the total quantity on active pallet labels.',
                    'More stock than labels = goods arrived without a pallet label. Less stock than labels = stock was used and a label still shows the old quantity.',
                    'New pallets may not add up to more than the unlabelled quantity. The expiry date is proposed from the arrival date and the shelf life of the item; the officer may change it.',
                    'The engine only shows the difference; the officer decides which pallet holds how much.',
                    'Stack number: 1 = bottom. New units go on top in the order they are made; a unit that arrives by stock transfer goes on top in the destination bin; when a unit is used up or leaves, the units above it move down.',
                    'Remnant: a unit of sheet goods (unit LBR or RIM, 1 ream = 500 sheets) at or below WH_REMNANT_MAX_SHEETS becomes a pack; the officer may also choose pallet or pack. A pack is not counted as a pallet.',
                ],
                'reads' => ['tm2inv.inv_pallet', 'tm2inv.v_rm_onhand / v_sfg_onhand / v_fg_onhand', 'tm2inv.rm_stock', 'tm2inv.rm_stock_d', 'tm2pur.pv_receipt', 'tm2mst.mat_raw.shelf_life_days'],
                'writes' => ['tm2inv.inv_pallet', 'tm2inv.inv_pallet_log'],
                'settings' => ['Settings > Constants: WH_EXPIRY_SOON_DAYS, WH_REMNANT_MAX_SHEETS', 'Master Raw Material: shelf life (days)'],
                'used' => ['Print Pallet Label', 'Stack Monitor', 'Goods Receipt (Pallet Labels button)', 'Print Item Label', 'Warehouse Map', 'Stock by Location', 'Track via QR/Barcode'],
                'tests' => ['tests/Feature/PalletServiceTest.php'],
                'docs' => ['docs/warehouselayout_tier1.md'],
            ],
            [
                'key' => 'stock-ledger', 'name' => 'Stock Period Ledger', 'group' => 'Inventory', 'mode' => 'calc',
                'purpose' => 'Works out the stock of a period for the reports: opening balance, movements and closing balance of every item, in quantity and value, for all stock classes.',
                'class' => StockLedger::class,
                'methods' => ['between', 'movements', 'positions', 'items'],
                'notes' => [
                    'between' => 'The ledger of one period (first and last date).',
                    'movements' => 'Every stock card row of the period by date, with its value and the running balance of the item.',
                    'positions' => 'One row per item and bin that had stock before the period or moved in it.',
                    'items' => 'One row per item: all its bins added up.',
                ],
                'inputs' => [
                    ['from', 'First date of the period.'],
                    ['to', 'Last date of the period.'],
                ],
                'outputs' => [
                    ['Opening and closing', 'Quantity and value just before the first date and at the end of the last date.'],
                    ['In, out, adjustment', 'Quantity and value of the period, per kind of movement. Stock transfers are kept apart.'],
                    ['Movement rows', 'Document, kind of movement, bin, quantity, value and the balance of the item after it.'],
                ],
                'steps' => [
                    'Opening balance of an item in a bin = its last stock card row before the first date.',
                    'Reads the stock card rows of the period in date order. In, Out and Adjustment come from the row; a stock transfer changes the bin, not the item.',
                    'Value of a movement = value of the bin after the row minus its value before, so opening + in - out + adjustment always equals closing.',
                    'Closing balance = the last row of the period, or the opening balance when nothing moved.',
                ],
                'reads' => ['tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard', 'tm2inv.v_inv_movement'],
                'writes' => [],
                'used' => ['Stock Card', 'Stock Recapitulation', 'Stock Summary'],
                'tests' => ['tests/Feature/StockReportsTest.php'],
                'docs' => ['docs/md_for_systems/inventoryreport_review.md'],
            ],
            [
                'key' => 'wo-generation', 'name' => 'Work Order Generation', 'group' => 'Production', 'mode' => 'auto',
                'purpose' => 'Creates the Work Orders when a delivery line is posted to Production.',
                'class' => WoGenerationService::class,
                'methods' => ['generateFromDelivery'],
                'inputs' => [
                    ['delivery', 'One delivery line of a Confirmed and approved Customer Order.'],
                ],
                'outputs' => [
                    ['Work Orders', 'One per part of the product, with operations (routing) and materials. A product without parts gets one simple Work Order for PPIC to complete.'],
                ],
                'steps' => [
                    'Refuses when the line already has Work Orders, the order is not Confirmed and approved, or the line has no Master Product.',
                    'One inquiry part = one Work Order.',
                    'Operations and materials come from the Spec Engine for this quantity.',
                    'Target rates are taken from the product and part code.',
                ],
                'reads' => ['tm2sdi.co_order_delivery', 'tm2sdi.iqp_part', 'tm2mst.prod_part'],
                'writes' => ['tm2prd.prd_wo', 'tm2prd.prd_wo_operation', 'tm2prd.prd_wo_material'],
                'used' => ['Customer Plan Delivery (Post to Production)'],
                'tests' => ['tests/Feature/ProductionWoGenerationTest.php'],
                'docs' => ['docs/md_for_systems/production_scheduling_wo_generation_delta.md'],
            ],
            [
                'key' => 'pdr', 'name' => 'PDR Engine (Scheduling)', 'group' => 'Production', 'mode' => 'suggest',
                'purpose' => 'Decides which job a machine should run next and plans a Work Order backwards from its due date.',
                'class' => PdrEngine::class,
                'methods' => ['buildResourceQueue', 'pickNext', 'backwardSchedule', 'backwardScheduleAndPersist', 'validateManualReschedule'],
                'notes' => [
                    'buildResourceQueue' => 'The waiting jobs of one machine, in order.',
                    'pickNext' => 'The job that should start now; null when its crew is not available.',
                    'backwardSchedule' => 'Target start and finish per operation; not saved.',
                    'validateManualReschedule' => 'Checks a drag / resize on the board; empty list = allowed.',
                ],
                'inputs' => [
                    ['machineId, processName', 'The resource.'],
                    ['proposedCrewIds', 'The crew PPIC wants to assign.'],
                    ['now', 'The moment to judge from (default: now).'],
                    ['wo', 'The Work Order to plan backwards.'],
                    ['task, newStart, newFinish', 'A manual move on the Scheduling Board.'],
                ],
                'outputs' => [
                    ['Queue', 'Jobs or groups of jobs, highest priority first.'],
                    ['Next job', 'One operation, or nothing.'],
                    ['Targets', 'Target start and finish per operation.'],
                    ['Reasons', 'Why a manual move is refused.'],
                ],
                'steps' => [
                    'Batching: jobs that can run together (same dieline ...) become one unit.',
                    'Priority score in layers: hot job, critical ratio, earliest due date, resource fit, first in first out.',
                    'Sequencing: smooths the order among jobs of equal priority only.',
                    'Crew check: the top job is skipped when its crew is not on shift.',
                    'Backward scheduling: from the due date back through the operations, using the rate band of each process and the machine calendar.',
                ],
                'reads' => ['tm2prd.prd_wo, prd_wo_operation', 'tm2prd.prd_scheduling_rule', 'tm2mst.prod_machine', 'tm2mst.emp_shift_roster', 'tm2mst.sys_holiday'],
                'writes' => ['tm2prd.prd_wo_operation (target dates, only with ...AndPersist)'],
                'settings' => ['Scheduling rules (prd_scheduling_rule)'],
                'used' => ['Scheduling Board', 'Work Order Worklist'],
                'tests' => ['tests/Feature/ProductionSchedulingBoardSmokeTest.php'],
                'docs' => ['docs/md_for_systems/production_scheduling_tier1.md §5A', 'production_scheduling_tier2.md §4'],
            ],
            [
                'key' => 'scheduling-rules', 'name' => 'Scheduling Rule Engine', 'group' => 'Production', 'mode' => 'calc',
                'purpose' => 'Applies the batching and sequencing rules kept as data, not as code.',
                'class' => SchedulingRuleEngine::class,
                'methods' => ['applyBatching', 'applySequencing'],
                'inputs' => [
                    ['candidates', 'The operations waiting for a machine.'],
                    ['sortedByPriority', 'The same list after the priority score.'],
                    ['machineId, processName', 'The resource the rules belong to.'],
                ],
                'outputs' => [
                    ['Groups', 'Lists of operations; a group of one = no batching fits.'],
                    ['Order', 'The list reordered inside equal priorities.'],
                ],
                'steps' => [
                    'Reads the active rules of the resource.',
                    'Batching joins operations of different Work Orders that share the rule key.',
                    'Sequencing rules are applied oldest rule first as the main key, later rules as tie breakers.',
                    'The priority order (hot job, critical ratio, due date) is never changed.',
                ],
                'reads' => ['tm2prd.prd_scheduling_rule'],
                'writes' => ['Nothing.'],
                'used' => ['PDR Engine'],
            ],
            [
                'key' => 'interruption', 'name' => 'Hot Job Option Generator', 'group' => 'Production', 'mode' => 'suggest',
                'purpose' => 'When an urgent job must be inserted, proposes up to three ways to do it with their measured impact.',
                'class' => InterruptionOptionGenerator::class,
                'methods' => ['generateOptions', 'recordDecision'],
                'inputs' => [
                    ['hotTask', 'The urgent operation.'],
                    ['machineId, processName', 'Where it must run.'],
                ],
                'outputs' => [
                    ['Options', 'One to three options; each shows which job moves, by how many hours, and whether it stays safe.'],
                    ['Decision', 'The option PPIC chose is recorded.'],
                ],
                'steps' => [
                    'Takes the waiting queue of the machine from the PDR Engine.',
                    'For the first two waiting jobs: "insert and move this job", with the delay in hours (the duration of the urgent job) and whether the moved job still meets its due date (safe / at risk).',
                    'Always adds a last option: send the urgent job to a subcontractor, so no job moves (needs a subcontract Purchase Order).',
                    'Shows the options; PPIC decides. There is no automatic answer.',
                ],
                'reads' => ['tm2prd.prd_wo_operation'],
                'writes' => ['tm2prd.prd_decision_feedback_log (recordDecision)'],
                'used' => ['Scheduling Board'],
                'docs' => ['docs/md_for_systems/production_scheduling_tier1.md §5A.2'],
            ],
            [
                'key' => 'speed-target', 'name' => 'Speed Target Engine', 'group' => 'Production', 'mode' => 'suggest',
                'purpose' => 'Proposes the target speed of every machine and of every process x machine pair.',
                'class' => SpeedTargetEngine::class,
                'methods' => ['run', 'suggestMachine', 'suggestCapabilities', 'shouldPropose'],
                'inputs' => [
                    ['contexts', 'Per machine: its data, its own output history and its process pairs.'],
                    ['current', 'The target filled in now (for the decision whether to propose).'],
                ],
                'outputs' => [
                    ['machine', 'A suggestion: speed, source, confidence (Low / Medium / High), sample size.'],
                    ['capabilities', 'A suggestion per process x machine pair that has its own history.'],
                ],
                'steps' => [
                    'Sources are tried in order; the first that gives a number wins: v2 output, old-system output of the machine, old-system output of the process, same machine model, ratio to similar machines, the model specification, the process default.',
                    'Pass 1 uses the history of each machine; pass 2 lets machines without history borrow from the others.',
                    'A pair is proposed only from its own history; without it, it inherits the machine target.',
                    'PPIC is asked only when the target is empty, or the difference is larger than the minimum percentage with at least Medium confidence.',
                    'The engine never reads or writes the database itself: a repository loads the data, a store keeps the proposals as PENDING.',
                ],
                'reads' => ['tm2prd.prd_wo_operation_output', 'tm2old production output', 'tm2mst.prod_machine, prod_process_machine'],
                'writes' => ['tm2mst.prod_speed_suggestion (PENDING); targets change only when PPIC accepts.'],
                'settings' => ['Settings > Constants, group SPEED_ENGINE'],
                'used' => ['Scheduled command production:suggest-speed-targets (daily)', 'Master Machine'],
                'tests' => ['tests/Unit/SpeedTargetEngineTest.php'],
            ],
            [
                'key' => 'maintenance-plan', 'name' => 'Preventive Plan Engine', 'group' => 'Maintenance', 'mode' => 'auto',
                'purpose' => 'Works out when each preventive plan is next due and opens the Draft preventive orders on time.',
                'class' => PlanService::class,
                'methods' => ['nextDue', 'isDue', 'generateFromPlan', 'generateDue', 'afterClose'],
                'inputs' => [
                    ['plan', 'A Preventive Plan: time interval, meter interval or both, floating or fixed schedule, lead days.'],
                    ['meter readings', 'The latest meter value of the asset (hours, km or count), for meter-based plans.'],
                ],
                'outputs' => [
                    ['next due', 'The date or meter value when the plan falls due next.'],
                    ['Draft order', 'A preventive order with the plan checklist and spare parts copied in, never two open orders for one plan.'],
                ],
                'steps' => [
                    'Floating plans count from the last Closed order; fixed plans keep the calendar and skip missed dates.',
                    'A plan is due when the next due date is within its lead days, or the meter has reached the interval, whichever comes first.',
                    'generateDue (scheduled daily) opens a Draft order for every due plan that has no open order yet; the head of maintenance confirms it.',
                    'Closing the order moves the plan to its next due date.',
                ],
                'reads' => ['tm2mnt.mnt_plan', 'tm2mnt.mnt_meter_reading', 'tm2mnt.mnt_order'],
                'writes' => ['tm2mnt.mnt_order (Draft)', 'tm2mnt.mnt_plan (next due)'],
                'settings' => ['Settings > Constants, group MAINTENANCE'],
                'used' => ['Preventive Plan', 'Preventive Maintenance Order', 'PM Calendar', 'Scheduled command maintenance:generate-pm'],
                'tests' => ['tests/Feature/MaintenancePreventiveTest.php'],
            ],
            [
                'key' => 'maintenance-order', 'name' => 'Maintenance Order Lifecycle', 'group' => 'Maintenance', 'mode' => 'flow',
                'purpose' => 'Moves a Maintenance Order (PMO, BMO, IMO) from Draft to Closed and keeps the machine status and downtime log true.',
                'class' => OrderService::class,
                'methods' => ['create', 'save', 'confirm', 'start', 'hold', 'resume', 'done', 'close', 'cancel'],
                'inputs' => [
                    ['order', 'The order with its checklist, spare parts and labor lines.'],
                    ['actor', 'The head of maintenance decides (Confirm, Close, Cancel); technicians work (Start, Hold, Done).'],
                ],
                'outputs' => [
                    ['order number', 'PMO-, BMO- or IMO-yymm-nnnn, born at Confirm.'],
                    ['downtime', 'One downtime row for a machine asset that must stop, opened at Start and closed at Done.'],
                    ['cost', 'Spare parts, labor at the standard rate and vendor service, final at Close.'],
                ],
                'steps' => [
                    'Draft can be edited freely; Confirm gives the number and puts the order in the technician list (Scheduled).',
                    'Start opens the downtime and sets the machine to Maintenance (planned) or Breakdown; assets that are not machines have no downtime.',
                    'Done is refused while a required checklist task has no result; it closes the downtime and sets the machine Ready.',
                    'Close fixes the cost and tells the plan to move to its next due date. Cancel needs a reason and closes any open downtime.',
                ],
                'reads' => ['tm2mnt.mnt_order', 'tm2mst.prod_machine'],
                'writes' => ['tm2mnt.mnt_order', 'tm2mst.prod_machine_downtime', 'tm2mst.prod_machine (status)'],
                'used' => ['Preventive Maintenance Order', 'Breakdown Maintenance Order', 'My Work'],
                'tests' => ['tests/Feature/MaintenancePreventiveTest.php'],
            ],
            [
                'key' => 'maintenance-request', 'name' => 'Maintenance Request Engine', 'group' => 'Maintenance', 'mode' => 'suggest',
                'purpose' => 'Turns a problem report into a Breakdown Maintenance Order, and suggests how urgent it is.',
                'class' => RequestService::class,
                'methods' => ['suggestPriority', 'open', 'fromBreakdown', 'accept', 'reject'],
                'inputs' => [
                    ['request', 'Asset, kind (Breakdown, Corrective, Safety), whether the asset is stopped, and the symptom.'],
                    ['breakdown event', 'A machine breakdown reported on the shop floor in Work Order entry.'],
                ],
                'outputs' => [
                    ['priority', 'Suggested P1 to P3; the reporter may overrule it.'],
                    ['order', 'A Draft BMO when the head of maintenance accepts; a Confirmed BMO at once for P1.'],
                ],
                'steps' => [
                    'Safety is P1; a stopped machine of criticality A or B is P1; another stopped asset is P2; a critical asset that still runs is P2; the rest is P3.',
                    'P1 does not wait: the request is accepted and its BMO is confirmed so it shows in My Work immediately.',
                    'Other requests wait for the head of maintenance: Accept makes a Draft BMO, Reject needs a reason.',
                    'A shop floor breakdown creates one request per event; a machine without a Maintenance asset is skipped and the production report is never blocked.',
                ],
                'reads' => ['tm2mnt.mnt_asset', 'tm2prd.prd_breakdown_event (link only)'],
                'writes' => ['tm2mnt.mnt_request', 'tm2mnt.mnt_order'],
                'used' => ['Maintenance Request', 'Daily Check', 'Work Order entry (report breakdown)'],
                'tests' => ['tests/Feature/MaintenanceBreakdownTest.php'],
            ],
            [
                'key' => 'maintenance-parts', 'name' => 'Maintenance Spare Part Supply', 'group' => 'Maintenance', 'mode' => 'auto',
                'purpose' => 'Shows spare part stock on an order, issues the parts from the warehouse, and drafts a purchase requisition for what is short.',
                'class' => PartSupplyService::class,
                'methods' => ['onHand', 'toIssue', 'issue', 'createPr'],
                'inputs' => [
                    ['order', 'A Confirmed order in progress with spare part lines (planned and used quantity).'],
                ],
                'outputs' => [
                    ['Stock Out', 'An ordinary raw material Stock Out per warehouse, posted through Inventory, so the stock card follows.'],
                    ['PR draft', 'One Draft purchase requisition with the parts that stock cannot cover.'],
                ],
                'steps' => [
                    'On hand = usable stock of the material over all locations (Inventory view).',
                    'Issue takes each part from the locations with most stock; a part the stock cannot cover is skipped and reported, never half-issued.',
                    'Inventory is never written directly: the document is saved and posted by the Inventory services, which also refuse closed periods and negative stock.',
                    'Create PR needs a cost account for each short part (material category or item COGS account) and requests each line once.',
                ],
                'reads' => ['tm2inv.v_rm_onhand', 'tm2mnt.mnt_order_part'],
                'writes' => ['tm2inv.rm_stock (Stock Out, posted)', 'tm2pur.pr_requisition (Draft)', 'tm2mnt.mnt_order_part'],
                'settings' => ['Settings > Constants: MNT_DEPARTMENT_CODE'],
                'used' => ['Maintenance Order (Issue Parts, Create PR, In Stock column)'],
                'tests' => ['tests/Feature/MaintenanceBreakdownTest.php'],
            ],
            [
                'key' => 'maintenance-kpi', 'name' => 'Maintenance KPI', 'group' => 'Maintenance', 'mode' => 'calc',
                'purpose' => 'Computes the Dashboard and report figures: availability, MTBF, MTTR, PM compliance, planned work, backlog, downtime and cost.',
                'class' => KpiService::class,
                'methods' => ['targets', 'indicators', 'backlogWeeks', 'actions', 'criticalPartsBelowMin', 'complianceTrend', 'topDowntime', 'downtimeByKind', 'failuresByComponent', 'cost'],
                'inputs' => [
                    ['period', 'From and to dates, and optionally one machine.'],
                ],
                'outputs' => [
                    ['indicators', 'Each figure, or nothing when it cannot be calculated; it is never shown as 0 for missing data.'],
                    ['counts', 'What needs action today: machines down, open requests, overdue preventive orders, orders in progress or waiting for parts, critical parts below minimum.'],
                ],
                'steps' => [
                    'OEE, availability and MTBF / MTTR per machine come from the Production engine (MachineOeeService); Maintenance only adds up the machines.',
                    'MTBF = (machines x period hours - repair hours) / breakdowns; MTTR = repair hours / breakdowns.',
                    'PM compliance and planned work come from the orders; backlog = estimated hours of open orders / technician hours per week.',
                    'Targets are Constants (MNT_TARGET_*); the lamp compares each figure with its target.',
                ],
                'reads' => ['tm2mnt.mnt_order', 'tm2mnt.mnt_request', 'tm2mst.prod_machine_downtime', 'tm2mst.prod_machine'],
                'writes' => ['Nothing: read only.'],
                'settings' => ['Settings > Constants, group MAINTENANCE'],
                'used' => ['Maintenance Dashboard', 'Machine Reliability'],
                'tests' => ['tests/Feature/MaintenanceBreakdownTest.php'],
            ],
            [
                'key' => 'maintenance-block', 'name' => 'Maintenance Machine Hold (Scheduling)', 'group' => 'Maintenance', 'mode' => 'calc',
                'purpose' => 'Tells Scheduling when a machine belongs to Maintenance, so production is never planned into those hours.',
                'class' => MaintenanceBlockProvider::class,
                'methods' => ['blocksFor', 'blocksOn', 'overlapping'],
                'inputs' => [
                    ['window', 'A time window and optionally the machines to look at.'],
                ],
                'outputs' => [
                    ['blocks', 'Machine, start, end and order for every Confirmed order that must stop the machine.'],
                ],
                'steps' => [
                    'Counts orders that are Confirmed, not Done, Closed or Cancelled, need the machine stopped, and sit on an asset linked to the machine.',
                    'The window runs from the actual (or planned) start to the planned end, or start plus the estimate, or 60 minutes when neither is known.',
                    'Backward scheduling skips the window; a manual move into it is refused; the Scheduling Board draws it as MTN.',
                ],
                'reads' => ['tm2mnt.mnt_order', 'tm2mnt.mnt_asset'],
                'writes' => ['Nothing: read only.'],
                'used' => ['Backward scheduling', 'Scheduling Board', 'Manual reschedule validation'],
                'tests' => ['tests/Feature/MaintenanceSchedulingBlockTest.php'],
            ],
            [
                'key' => 'pq-matcher', 'name' => 'Purchase Quotation Matcher', 'group' => 'Procurement', 'mode' => 'suggest',
                'purpose' => 'Matches the text of a supplier quotation file to the supplier master and to the requisition lines.',
                'class' => PqMatcher::class,
                'methods' => ['supplier', 'line', 'score'],
                'inputs' => [
                    ['name', 'Supplier name as written in the file.'],
                    ['text', 'One line of the quotation.'],
                    ['prLines', 'The lines of the requisition the quotation answers.'],
                ],
                'outputs' => [
                    ['Supplier', 'Supplier id and a score.'],
                    ['Line', 'The requisition line or Material Master item and a percentage.'],
                ],
                'steps' => [
                    'Score = average of shared words (sizes and grammage count) and letter similarity.',
                    'First tries the requisition lines, then the Material Master.',
                    'A score below "sure" is marked yellow: the officer must choose.',
                ],
                'reads' => ['tm2mst.sup_supplier', 'tm2mst.mat_raw', 'tm2pur.pr_line'],
                'writes' => ['Nothing.'],
                'used' => ['Purchase Quotation (upload)'],
            ],
            [
                'key' => 'tax', 'name' => 'Tax Engine', 'group' => 'Finance', 'mode' => 'calc',
                'purpose' => 'Builds the monthly tax register (VAT, income tax 21 and 23), posts payments and reconciles with the ledger.',
                'class' => TaxEngine::class,
                'methods' => ['generateMonth', 'pay', 'file', 'provisionCit', 'reconciliation', 'dueDates', 'payrollPph21'],
                'notes' => [
                    'generateMonth' => 'Builds or refreshes the register of one month; paid or filed rows are not changed.',
                    'pay' => 'Journal for the tax payment to the bank.',
                    'file' => 'Records the filing reference.',
                    'provisionCit' => 'Corporate income tax provision on the profit so far this year; only the difference is posted.',
                    'reconciliation' => 'Register against ledger against source, for the status panel.',
                ],
                'inputs' => [
                    ['year, month', 'The tax period.'],
                    ['reg', 'One register row to pay or file.'],
                    ['date, ntpn, bankAccountId', 'Payment date, state payment number and the bank account.'],
                    ['user, origin', 'Who runs it and whether the data is live or dummy.'],
                ],
                'outputs' => [
                    ['Register rows', 'Per tax: base, tax, due dates, status.'],
                    ['Journals', 'Payment and provision journals.'],
                    ['Reconciliation', 'Differences between register, ledger and source.'],
                ],
                'steps' => [
                    'Classify, calculate, post, reconcile, then pay and file.',
                    'All rates and due dates are read from the tax scheme table, so they change without code.',
                    'VAT payment nets output against input.',
                ],
                'reads' => ['tm2mst.fin_tax_scheme', 'tm2acc ledger', 'tm2pyr.pyr_payroll_run, pyr_payslip'],
                'writes' => ['tm2acc.fin_tax_register', 'tm2acc journals'],
                'settings' => ['Master Tax Scheme'],
                'used' => ['Finance > Tax'],
            ],
            [
                'key' => 'payroll', 'name' => 'Payroll Engine', 'group' => 'HR & Payroll', 'mode' => 'calc',
                'purpose' => 'Calculates the payslips of a period from attendance and takes the run through verification, payment and closing.',
                'class' => PayrollEngineService::class,
                'methods' => ['calculate', 'verify', 'confirm', 'markPaid', 'markPayslipsSent', 'close'],
                'notes' => [
                    'calculate' => 'May be repeated while the run is Draft or Calculated.',
                    'verify' => 'HR checks all employees or a random sample.',
                    'confirm' => 'Locks attendance; refused while a payslip still has an anomaly.',
                    'close' => 'Closes the period and opens the next one as an empty Draft.',
                ],
                'inputs' => [
                    ['periodType, periodStart, periodEnd', 'The pay period.'],
                    ['run', 'The payroll run being moved to its next status.'],
                    ['method, sampleEmployeeIds', 'Full check or random sample.'],
                    ['triggeredBy / verifiedBy / ...', 'Who did the step.'],
                ],
                'outputs' => [
                    ['Payroll run', 'Draft, Calculated, Verified, Confirmed, Paid, Closed.'],
                    ['Payslips', 'One per employee with its components.'],
                    ['Anomalies', 'An employee without an active salary rate stays visible and blocks Confirm.'],
                ],
                'steps' => [
                    'Loads the rule matrix (status, penalty, overtime) once per run.',
                    'Reads the daily attendance status of every employee in the period.',
                    'Builds each payslip: salary components, overtime per hour, deductions.',
                    'Verification, then Confirm locks attendance; Paid and Closed are status records.',
                ],
                'reads' => ['tm2mst.att_daily_status', 'tm2pyr.pyr_employee_salary', 'tm2pyr.pyr_overtime_rate'],
                'writes' => ['tm2pyr.pyr_payroll_run', 'tm2pyr.pyr_payslip', 'tm2pyr.pyr_payslip_detail'],
                'used' => ['Payroll Run'],
            ],
            [
                'key' => 'assistant', 'name' => 'Assistant Engine (offline)', 'group' => 'Help', 'mode' => 'auto',
                'purpose' => 'Answers a question typed by the user without sending anything outside the company.',
                'class' => AssistantEngine::class,
                'methods' => ['ask', 'topic'],
                'inputs' => [
                    ['asker', 'Who asks (the logged-in user).'],
                    ['question', 'The question as typed.'],
                    ['key', 'A help topic, for the "How to use" button.'],
                ],
                'outputs' => [
                    ['Reply', 'A number, a help text, a screen to open, or "I do not know".'],
                ],
                'steps' => [
                    'Tries in order: a registered number whose phrase matches, help when the question has a question word, a screen, help without a question word, the local language model choosing among the closest candidates, then "I do not know".',
                    'Numbers come only from registered metric classes; the language model never calculates.',
                    'Help text is quoted from the help tables, never composed.',
                ],
                'reads' => ['tm2sys.sys_help_topic, sys_help_line', 'tm2sys.sys_app_menu', 'tm2sys.sys_assistant_metric'],
                'writes' => ['tm2sys.sys_assistant_message'],
                'used' => ['AI assistant drawer', 'Help per screen (F1)'],
                'tests' => ['tests/Feature/AssistantEngineTest.php'],
            ],
            [
                'key' => 'ppic-auto-plan', 'name' => 'PPIC Auto-Planner', 'group' => 'Production', 'mode' => 'suggest',
                'purpose' => 'Prepares a Draft plan for every open Work Order: gates, estimates, schedule and the list of what needs a person. Nothing becomes the real schedule until the officer confirms.',
                'class' => AutoPlanRunner::class,
                'methods' => ['run', 'solver'],
                'notes' => ['run' => 'One planning run. Writes a Draft only; the confirmed and locked part of the schedule is never touched.'],
                'inputs' => [
                    ['trigger', 'DAILY, SHIFT, EVENT (new Work Order, officer priority, drag) or MANUAL.'],
                    ['actor', 'ENGINE or the officer who started it.'],
                    ['now', 'The moment to plan from (default: now).'],
                ],
                'outputs' => [
                    ['Plan run', 'One Draft run with the number of operations planned and the number that need attention.'],
                    ['Draft schedule', 'Machine, start, finish, setup and gate of each operation, saved as Draft on the operation.'],
                    ['Exceptions', 'Late risk, material waiting, tooling blocked, no machine, overload.'],
                ],
                'steps' => [
                    'Refreshes Work Orders that have no process ids yet and re-reads the material and tooling gates.',
                    'Loads open operations outside the confirmed lock, the work calendar, holidays, wait rules and machine changeover values.',
                    'Hands the facts to the Schedule Solver, which ranks the jobs and places them on machines.',
                    'Discards the previous Draft, writes the new one and the exception list, and logs the run.',
                ],
                'reads' => ['tm2prd.prd_wo', 'tm2prd.prd_wo_operation', 'tm2mst.prod_machine', 'tm2mst.prod_process_machine', 'tm2mst.prod_wait_rule', 'tm2mst.prod_machine_changeover', 'tm2sys.sys_constant (PPIC_*)'],
                'writes' => ['tm2prd.prd_plan_run', 'tm2prd.prd_wo_operation (Draft columns)', 'tm2prd.prd_plan_decision'],
                'settings' => ['PPIC_LOCK_SHIFTS', 'PPIC_WEIGHT_URGENCY / SETUP / TIER', 'PPIC_HORIZON_HOURS / DAYS', 'PPIC_BOTTLENECK_LOAD_PCT'],
                'used' => ['Production Scheduling', 'Work Order (Create Work Order)', 'scheduler: ppic:auto-plan (nightly and at shift start)'],
                'tests' => ['tests/Unit/Ppic/ScheduleSolverTest.php'],
                'docs' => ['resources/data/help/ppic_technical_plan.json'],
            ],
            [
                'key' => 'ppic-schedule-solver', 'name' => 'Schedule Solver (finite capacity)', 'group' => 'Production', 'mode' => 'calc',
                'purpose' => 'Pure calculation: ranks the jobs and places each operation on the machine that finishes it earliest, with setup, wait and calendar included.',
                'class' => ScheduleSolver::class,
                'methods' => ['solve'],
                'inputs' => [
                    ['wos', 'Work Orders with their operations, candidate machines, rates, gates and fixed (running or locked) placements.'],
                    ['cal', 'The work calendar: shifts per machine, holidays.'],
                    ['now', 'The moment to plan from.'],
                ],
                'outputs' => [
                    ['Placements', 'Machine, setup start, run start and finish per operation.'],
                    ['Exceptions', 'Late risk, blocked, waiting and no-machine operations with a reason.'],
                ],
                'steps' => [
                    'Ranks the Work Orders: Hot job first, then Critical Ratio below one, then due date; inside the similar-urgency window the setup-aware score decides.',
                    'Places the operations of a Work Order in routing order: each starts after the previous one finishes plus its wait.',
                    'For each operation picks the eligible machine with the earliest finish including setup, then lower load, then preference rank.',
                    'Fixed placements (running, locked) are never moved; blocked operations are listed, not placed.',
                ],
                'reads' => ['Nothing: it works on the facts it is given.'],
                'writes' => ['Nothing.'],
                'used' => ['PPIC Auto-Planner', 'Plan Control (drag impact)'],
                'tests' => ['tests/Unit/Ppic/ScheduleSolverTest.php'],
            ],
            [
                'key' => 'ppic-setup-time', 'name' => 'Setup Time Engine', 'group' => 'Production', 'mode' => 'calc',
                'purpose' => 'Setup minutes and extra waste between two jobs on a machine: base setup plus every change (colour, ink type, substrate, size, plate, die) plus tool mounting.',
                'class' => SetupTimeEngine::class,
                'methods' => ['between'],
                'inputs' => [
                    ['prev, next', 'Facts of the job before and the job after on the machine (colours, ink type, gsm, sheet size, plate, die).'],
                    ['rules', 'Minutes and waste per kind of change; machine rows override the start values.'],
                    ['stage', 'PRESS, DIECUT and so on.'],
                ],
                'outputs' => [
                    ['Minutes and waste', 'Total setup minutes and extra waste sheets.'],
                    ['Breakdown', 'The list of changes that produced them, for display.'],
                ],
                'steps' => [
                    'Starts from the base setup minutes of the machine (or the constant).',
                    'Adds one item for every difference between the two jobs, using the changeover table.',
                    'Adds the tool mounting minutes of the next job (first run or repeat run) from the tooling master.',
                ],
                'reads' => ['Nothing: rules are passed in.'],
                'writes' => ['Nothing.'],
                'settings' => ['PPIC_BASE_SETUP_MIN', 'Setup and Changeover master'],
                'used' => ['Schedule Solver', 'Work Order screen'],
                'tests' => ['tests/Unit/Ppic/ScheduleSolverTest.php'],
            ],
            [
                'key' => 'ppic-gates', 'name' => 'Readiness Gates', 'group' => 'Production', 'mode' => 'auto',
                'purpose' => 'Marks every operation Ready, Waiting (with a date) or Blocked (with a reason) for material, tooling and artwork.',
                'class' => GateService::class,
                'methods' => ['material', 'tooling', 'apply'],
                'inputs' => [
                    ['wo', 'The Work Order.'],
                    ['process', 'The process of the operation (tooling needs a die or plates).'],
                ],
                'outputs' => [
                    ['Gates', 'Ready, Waiting or Blocked per gate, with the note and the date material is ready.'],
                ],
                'steps' => [
                    'Material: reads the Material Planning result for the Work Order (stock, booking, incoming purchases); short material becomes Waiting with the incoming date, or Blocked when nothing is coming.',
                    'Tooling: looks up the plate set or die by dieline reference; Ready or In use passes, Not made, In making or Needs repair blocks; not registered passes with a note.',
                    'Writes the gates and the note on each operation.',
                ],
                'reads' => ['tm2prd.prd_wo_material', 'tm2mst.prod_tooling_asset', 'Material Planning Engine'],
                'writes' => ['tm2prd.prd_wo_operation (gate columns)'],
                'used' => ['PPIC Auto-Planner', 'Work Order', 'Production Scheduling'],
            ],
            [
                'key' => 'ppic-wo-enrich', 'name' => 'Work Order Enrichment', 'group' => 'Production', 'mode' => 'suggest',
                'purpose' => 'Completes a new Work Order with what scheduling must know: process ids, the machines that can run each step, target per hour, waste, spoilage and setup.',
                'class' => WoPlanningEnricher::class,
                'methods' => ['enrich'],
                'inputs' => [
                    ['wo', 'A Work Order created from Product Development data.'],
                ],
                'outputs' => [
                    ['Operations', 'Process id, quantity in and good out, target, setup minutes, candidate machines with eligibility and the reason when not eligible.'],
                    ['Header facts', 'Sheet size, ups per sheet, board weight, colours, dieline reference, customer tier.'],
                ],
                'steps' => [
                    'Reads layout, board, colours, dieline and the customer tier from Product Development and the customer master.',
                    'Matches each operation to its process and checks every machine of that process against sheet size, board weight, colours and certification.',
                    'Takes the target per hour from the machine and process rates and estimates setup from the changeover table.',
                    'Flows quantities back from the last step: each step asks for what it needs including its waste and spoilage.',
                ],
                'reads' => ['tm2prd.prd_wo', 'tm2mst.prod_part', 'tm2mst.prod_process_machine', 'tm2mst.cust_customer'],
                'writes' => ['tm2prd.prd_wo', 'tm2prd.prd_wo_operation', 'tm2prd.prd_wo_operation_machine'],
                'used' => ['Create Work Order', 'Work Order (Refresh from ProDev)'],
            ],
            [
                'key' => 'ppic-plan-control', 'name' => 'Plan Confirm and Change', 'group' => 'Production', 'mode' => 'flow',
                'purpose' => 'The officer side of the plan: Confirm makes the Draft the real schedule and locks the next shifts; priority and drag changes go into a new Draft with their impact shown first.',
                'class' => PlanConfirmService::class,
                'methods' => ['confirm', 'discard'],
                'notes' => ['confirm' => 'Writes the real start and finish, sets Confirmed and locks the first N running shifts.'],
                'inputs' => [
                    ['runId', 'The Draft run.'],
                    ['login', 'The officer.'],
                ],
                'outputs' => [
                    ['Confirmed plan', 'Number of operations confirmed and locked.'],
                ],
                'steps' => [
                    'Only a Draft can be confirmed.',
                    'Copies the draft machine and times to the real columns, marks the operations Confirmed and Assigned.',
                    'Locks operations that start inside the next N running shifts (N = PPIC_LOCK_SHIFTS).',
                    'Writes the decision log.',
                ],
                'reads' => ['tm2prd.prd_plan_run', 'tm2prd.prd_wo_operation'],
                'writes' => ['tm2prd.prd_wo_operation', 'tm2prd.prd_plan_run', 'tm2prd.prd_plan_decision'],
                'settings' => ['PPIC_LOCK_SHIFTS'],
                'used' => ['Production Scheduling (Confirm plan, Hot job, Move up, Gantt drag)'],
            ],
            [
                'key' => 'ppic-operator-timer', 'name' => 'Operator Timer', 'group' => 'Production', 'mode' => 'flow',
                'purpose' => 'Play, Pause and Stop at the machine: records real setup, run and pause time and the quantities of each job.',
                'class' => OperatorTimerService::class,
                'methods' => ['play', 'pause', 'stop', 'addQuantity', 'summary'],
                'inputs' => [
                    ['operationId', 'The job in the confirmed queue.'],
                    ['reason', 'Why the timer is paused: waiting material, break, machine problem, quality hold, waiting crew, other.'],
                    ['good, waste, reject', 'Quantities at Stop, with the scrap type of the reject and what changed at setup.'],
                ],
                'outputs' => [
                    ['Time log', 'One row per segment: setup, run or pause with its reason.'],
                    ['Output', 'Good quantity and waste / reject lines in the production output tables.'],
                ],
                'steps' => [
                    'One timer runs per machine at a time; Play on a waiting job starts setup, Play again starts the run.',
                    'Pause closes the segment and opens a pause with the reason; Play continues the kind it was.',
                    'Stop closes the timer, saves the quantities, adds the setup minutes to the operation and finishes it.',
                ],
                'reads' => ['tm2prd.prd_wo_operation'],
                'writes' => ['tm2prd.prd_operation_time_log', 'tm2prd.prd_wo_operation', 'tm2prd.prd_wo_operation_output', 'tm2prd.prd_wo_operation_output_defect'],
                'used' => ['Operator Queue'],
            ],
            [
                'key' => 'ppic-learning', 'name' => 'Learning Engine (changeover)', 'group' => 'Production', 'mode' => 'auto',
                'purpose' => 'Corrects the Assumed changeover minutes from the setup times operators really needed, in small logged steps that can be put back.',
                'class' => LearningEngine::class,
                'methods' => ['run', 'revert'],
                'notes' => ['revert' => 'Puts the old value back and freezes it so the engine leaves it alone.'],
                'inputs' => [
                    ['actor', 'ENGINE or the officer.'],
                    ['decisionId', 'The logged change to put back.'],
                ],
                'outputs' => [
                    ['Adjusted values', 'Machine, kind of change, old and new minutes and the number of jobs.'],
                    ['Log', 'One decision row per change (event LEARN or LEARN_REVERT).'],
                ],
                'steps' => [
                    'Takes the setup minutes of finished jobs per machine and kind of change from the time log.',
                    'Needs at least the minimum number of jobs; the typical actual is the median of the last ones minus the base setup.',
                    'New value = old + blend x (typical - old), moved by at most the step limit; only Assumed or Learning values that are not frozen.',
                    'Learns only from jobs finished after the last adjustment.',
                ],
                'reads' => ['tm2prd.prd_operation_time_log'],
                'writes' => ['tm2mst.prod_machine_changeover', 'tm2prd.prd_plan_decision'],
                'settings' => ['PPIC_LEARN_MIN_SAMPLES', 'PPIC_LEARN_MAX_STEP_PCT', 'PPIC_LEARN_BLEND'],
                'used' => ['Learning Changes', 'scheduler: ppic:learn'],
            ],
        ];
    }

    // Kelas PHP yang terdaftar (untuk penjaga: engine baru wajib ada di sini).
    /** @return list<class-string> */
    public static function classes(): array
    {
        return array_values(array_filter(array_column(self::all(), 'class')));
    }

    // Daftar siap tampil: penjelasan di atas + tanda tangan method, letak berkas dan jumlah baris yang dibaca dari kode.
    /** @return list<array<string, mixed>> */
    public static function catalog(): array
    {
        return array_map(function (array $e) {
            $code = isset($e['class'])
                ? EngineInspector::describe($e['class'], $e['methods'] ?? [], base_path())
                : EngineInspector::file(base_path($e['file']), base_path());
            $entries = array_map(fn (array $m) => $m + ['note' => $e['notes'][$m['method']] ?? null], $code['entries']);
            foreach ($e['calls'] ?? [] as [$call, $note]) {
                $entries[] = ['method' => $call, 'static' => false, 'params' => [], 'returns' => 'mixed', 'note' => $note, 'literal' => true];
            }
            unset($e['methods'], $e['notes'], $e['calls']);

            return ['class' => $code['class'], 'file' => $code['file'], 'lines' => $code['lines'], 'entries' => $entries] + $e;
        }, self::all());
    }
}
