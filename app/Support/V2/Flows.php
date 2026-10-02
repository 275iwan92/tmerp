<?php

namespace App\Support\V2;

// Alur antar layar/modul (mengikuti tmerp sekarang, CLAUDE.md §4). Status blok = status layar mockup-nya.
final class Flows
{
    public static function get(string $module): ?array
    {
        $s = function (string $slug, ?string $label = null, ?string $module = null, ?string $note = null, array $branch = []) {
            $m = Screens::screen($slug);

            return ['key' => $slug, 'label' => $label !== null ? t($label) : $m['label'], 'icon' => $m['icon'] ?? 'file', 'module' => $module !== null ? t($module) : ($m['module'] ?? ''),
                'url' => $m['url'] ?? null, 'status' => $m['status'] ?? 'todo', 'note' => $note !== null ? t($note) : null, 'branch' => $branch];
        };
        $b = fn (string $slug, ?string $label = null) => ($m = Screens::screen($slug)) ? ['label' => $label !== null ? t($label) : $m['label'], 'icon' => $m['icon'], 'url' => $m['url'], 'status' => $m['status']] : null;
        $later = fn (string $label, string $icon, string $module, string $note) => ['key' => $label, 'label' => t($label), 'icon' => $icon, 'module' => t($module), 'status' => 'todo', 'note' => t($note)];

        return match ($module) {
            'SALES' => ['title' => t('Sales flow: from Inquiry to delivery'), 'steps' => [
                $s('inquiry', 'Inquiry Product', 'Sales', '3 document types: NEW (from scratch), REVISE (spec changed), RECALC (material price only)',
                    array_filter([$b('inquiry-new', 'New'), $b('inquiry-revise', 'Revise'), $b('inquiry-recalc', 'Recalculate')])),
                $s('prodev', null, 'Sales · Production', 'A NEW inquiry is sent to ProDev: find the most economical physical layout -> prod_layout_group CONFIRMED'),
                $s('sales-price', 'Pro-Cost: tiers & prices', 'Product Costing', 'COGS is calculated in the Product Costing module (confidential); Sales only sees tiers & prices after they are published'),
                $s('quotation', null, 'Sales', 'Choose priced InqProds of the same customer; price/qty/discount are filled in automatically and may be changed before publishing'),
                $s('customer-order', null, 'Sales', 'Mode #1: from a PUBLISHED Quotation (1 CO = 1 Quotation). Mode #2 manual (needs a Master Product) is not in tmerp yet'),
                $s('cpdel', null, 'Sales', 'CO Confirm -> automatic delivery schedule per item (full qty @ due date); can be Split / Joined'),
                $s('delivery-order', null, 'Sales', 'Delivery based on the CPDEL schedule', array_filter([$b('delivery-notes', 'Delivery Note')])),
                $later('Invoice & Receivables', 'fa-file-invoice-dollar', 'Finance', 'Finance module (coming later)'),
            ]],
            'COSTING' => ['title' => t('Product Costing flow: from ProDev layout to published price'), 'steps' => [
                $s('inquiry', 'Inquiry Product (NEW)', 'Sales', 'Rough specification from Sales'),
                $s('prodev', null, 'Production', 'Mode #1 integrated: starts from iqp_part; Mode #2 manual without an Inquiry'),
                $s('procost', 'Pro-Cost (take layout data)', 'Product Costing', 'Mode #1: pull prod_layout_group CONFIRMED',
                    array_filter([$b('procost-instant', 'Mode #2: manual input (Instant)')])),
                $later('Publish (Directors approval)', 'fa-stamp', 'Product Costing', 'Publish = declassify the price to Side B (Sales). COGS/margin stay confidential'),
                $s('quotation', 'Used by Sales Quotation', 'Sales', 'Only costing with published_flag = true can be pulled'),
            ]],
            default => null,
        };
    }
}
