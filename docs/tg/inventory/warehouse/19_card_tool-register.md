# Concept card: Tool Register (dies, plates, blocks)

> Written 2026-10-16 (group Inventory-Warehouse). Verified: 2026-10-16. FACT = mockup DB (`ddt*` through `tm2*`, `--env=mockup`) and code with line numbers, seen this session. FROM DOCUMENT = not checked. No test was found for this screen.

- **Document type**: concept card (simple master list, no numbered document). **Module/sub-module**: Inventory > Warehouse > Tools > Tool Register (`INV_TOOLS` group, `INV_TOOLS_REG` `v2:inv-tools`; FACT `sys_app_menu`, active, READY_FOR_QC).
- **Canonical name**: Tool = `tm2inv.inv_tool` (key `id_tool`, `tool_no` UNIQUE), model `InvTool`, screen `InvToolLookup` (inline-edit grid).
- **Synonyms**: pisau pond (die), plat (plate), klise (block), cetakan, tooling, alat cetak.
- **NOT the same as**: Inquiry/ProCost "tooling cost" lines (Sales costing), the TOOLING storage area (where they are kept, a rack area in A10), machine spare parts (Maintenance), dieline artwork (Sales > ProDev).

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `inv_tool`: 0 rows. Columns `tool_no`, `tool_type` (DIE / PLATE / BLOCK), `description`, `status` (AVAILABLE, IN_USE, AT_VENDOR, REPAIR, RETIRED), `location_id` (a bin in `wh_loc`), `impressions`, `notes`, `activate`, audit columns. Indexes: unique `tool_no`, `status`. No foreign keys. | FACT, `show columns`, `show index`, `referential_constraints`; `app/Models/Inventory/InvTool.php:8-22` |
| Screen | Grid with columns Tool No. (uppercase, locked once saved), Type, Description, Status, Location (Bin) (any active bin), Impressions, Notes, Active. Guide: "Dies, plates and blocks the factory owns. Set the status ... and the bin where the tool is kept. Impressions is the number of impressions made so far." | FACT, `app/Support/V2/Grid/Inv/InvToolLookup.php:47-75` |
| Rules | `tool_no` max 30; type and status from the lists; `impressions` integer >= 0 (typed by an officer). | FACT, `InvToolLookup.php:87-91` |
| Storage area | The dummy has a TOOLING area `TLG` (rack, 16 bins) in A10 floor 2, intended for dies and plates. | FACT, query `wh_zone` |
| Readers | Nothing else reads `inv_tool`: no job order, ProCost line or Inquiry tooling row links to it. | FACT, grep `inv_tool` / `InvTool` in `app`, `routes`, `resources`: only the model and `InvToolLookup` (plus the migration) |
| Origin | Created by migration `2026_10_09_000210_inventory_transit_count_subcon.php` (v2.01). | FACT, grep migrations |

## Relations
- **source**: typed by the warehouse officer; bins from card `10_card_location-hierarchy.md`.
- **used_by**: nobody yet (see finding 1).
- **flows**: none.
- **frozen_zone**: Inquiry/ProDev/ProCost are frozen; do not link tools into them without Jo's decision. Open questions in `docs/design-notes/inventory/15_inv-open-decisions.md` (FROM DOCUMENT: die ownership "catat per die", die owned by customer billed as tooling).

## Findings (recorded, not fixed)
1. **An island table**: the register is not connected to any job, costing or maintenance record; "In use" cannot be derived from a work order, and impressions are typed by hand. The code comment says use per job ("menyusul", still to come) in `InvToolLookup.php:13-14`. FACT.
2. **Empty in the dummy**, so there is no sample row; `location_id` has no foreign key, so a deleted bin leaves a dangling location. FACT (query, `referential_constraints`).
3. **Possible duplicate with Maintenance/ProDev dieline tooling**: not searched beyond `inv_tool`; the rule "prove absence before proposing" applies before anyone adds a tool/die table elsewhere. NOT CHECKED.
4. **No change history**: status moves (AVAILABLE -> IN_USE -> AT_VENDOR) are overwritten in place; there is no `doc_state_log` entry or movement table. FROM CODE (inline-edit grid).

## Already exists? Searched where
Searched `inv_tool`, `InvTool`, `InvToolLookup`, menu `INV_TOOLS*`, zone type TOOLING. Result: exists as a standalone master; not wired to anything.
