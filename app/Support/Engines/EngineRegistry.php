<?php

namespace App\Support\Engines;

use App\Services\Acc\TaxEngine;
use App\Services\Costing\CostingEngine;
use App\Services\Costing\ReverseCostingService;
use App\Services\Flow\FlowEngineService;
use App\Services\Imposition\ImpositionEngine;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseStock;
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
use App\Services\Scheduling\PdrEngine;
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
                'purpose' => 'Tells what is in every bin, how full the bin is and which stock is dead. One source for the Warehouse Map, transfers, labels and tracking.',
                'class' => WarehouseStock::class,
                'methods' => ['positions', 'binSummaries', 'weightKg', 'deadMonths'],
                'notes' => [
                    'positions' => 'Every item that has stock in a bin now, with its last real movement and the dead stock flag.',
                    'binSummaries' => 'Per bin: its items, weight in use, percent of capacity, status and whether it holds dead stock.',
                    'weightKg' => 'Estimated weight of a quantity; empty when it cannot be estimated.',
                    'deadMonths' => 'The dead stock limit in months.',
                ],
                'inputs' => [
                    ['locationIds', 'The bins to read; empty = every bin.'],
                    ['itemFilter', 'Only these items, per stock class (used when tracking one item).'],
                    ['bins', 'The bin rows with their capacity and usable flag.'],
                ],
                'outputs' => [
                    ['Stock positions', 'Class, item, bin, quantity, unit, value, estimated weight, last movement, idle days, dead flag.'],
                    ['Bin status', 'EMPTY, IN_USE, FULL (90% of the weight capacity or more), OVER (above capacity) or BLOCKED.'],
                ],
                'steps' => [
                    'Reads the stock balance views of raw material, semi finished and finished goods: the last stock card row per item and bin.',
                    'Last movement = the latest stock card row of the item in any bin that is not a stock transfer, so moving stock never resets it.',
                    'Dead stock = that movement is older than the limit, or the item never moved. The same rule as the assistant answer and the Deadstock widget.',
                    'Weight: unit KG = the quantity; sheets with grammage and size = quantity x gsm x length x width; anything else is unknown and left out of the capacity.',
                ],
                'reads' => ['tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2mst.wh_loc', 'tm2mst.mat_raw', 'tm2mst.prod_part', 'tm2mst.prod_product'],
                'writes' => [],
                'settings' => ['Settings > Constants: ASSISTANT_DEADSTOCK_MONTHS'],
                'used' => ['Warehouse Map', 'Stock Transfer', 'Stock by Location', 'Print Location Label', 'Print Item Label', 'Track via QR/Barcode'],
                'tests' => ['tests/Feature/StockTransferServiceTest.php'],
                'docs' => ['docs/warehouselayout_tier1.md'],
            ],
            [
                'key' => 'stock-transfer', 'name' => 'Stock Transfer Posting', 'group' => 'Inventory', 'mode' => 'flow',
                'purpose' => 'Moves stock from one bin to another, in the same warehouse or between warehouses, and can take the move back.',
                'class' => StockTransferService::class,
                'methods' => ['confirmErrors', 'confirm', 'cancel', 'balance'],
                'notes' => [
                    'confirmErrors' => 'Why the transfer cannot be confirmed yet; empty = it can.',
                    'confirm' => 'Moves the stock and gives the document its number.',
                    'cancel' => 'Moves the stock back to the source bins; needs a reason.',
                    'balance' => 'Stock of one item in one bin right now.',
                ],
                'inputs' => [
                    ['transfer', 'The transfer document with its lines: item, source bin, destination bin, quantity.'],
                    ['login', 'Who confirms or cancels.'],
                    ['reason', 'Why the transfer is cancelled.'],
                ],
                'outputs' => [
                    ['Stock card rows', 'Two rows per line: out of the source bin and into the destination bin, both marked TF.'],
                    ['Transfer number', 'TF + year + running number, issued at Confirm.'],
                    ['Error text', 'Empty when it worked; otherwise the reason, and nothing is written.'],
                ],
                'steps' => [
                    'Checks every line: quantity, destination bin in the destination warehouse, bin not blocked, enough stock in the source bin (lines from the same bin are added up).',
                    'Per line writes the row out of the source bin and the row into the destination bin; the value moves with the average price of the source bin.',
                    'The destination bin gets a weighted average price. The total stock of the item does not change and no journal is made.',
                    'Notes on the line whether the item was dead stock at that moment.',
                    'Cancel writes the same two rows the other way round; it stops when the stock is no longer in the destination bin.',
                ],
                'reads' => ['tm2inv.inv_transfer', 'tm2inv.inv_transfer_line', 'tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard', 'tm2mst.wh_loc'],
                'writes' => ['tm2inv.rm_stockcard / sfg_stockcard / fg_stockcard', 'tm2inv.inv_transfer', 'tm2inv.inv_transfer_line'],
                'used' => ['Stock Transfer', 'Warehouse Map (Transfer button)'],
                'tests' => ['tests/Feature/StockTransferServiceTest.php'],
                'docs' => ['docs/warehouselayout_tier1.md'],
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
