# Sub-module: Master Data (Sales > Inquiry & Quotation)

> Written 2026-10-18 (group B, read/write and relation sections). This page only summarizes and links; table/column facts are in the cards. FACT = seen directly (menu `sys_app_menu` mockup; code with lines).

- **Module**: Sales > Inquiry & Quotation > Master Data (menu group id 1142). Menu (FACT, `sys_app_menu` mockup, active): Master Customer (`v2:md-customer`), Master Product (`v2:master-product`), Master Raw Material (`v2:md-raw`), Master Raw Material Category (`v2:md-matcat`), Master Process (`v2:prod-process`), Master Machine (`v2:prod-machine`). The Sales Person master (`v2:md-sales-person`) is NOT in this group: it sits in Sales > CRM > Master Data (id 1192) and in the module Master Data (id 1182); it is mapped on this page because the folder holds its card.
- **Purpose**: the master data that Sales picks from while it builds Inquiry, Quotation and Customer Order: who the customer is, which product exists, which raw material and category build the item code, and which process and machine the plant has.
- **Real masters**: Master Process and Master Machine are REAL plant data (`prod_process`, `prod_machine`, `prod_process_machine`); only a user changes them and this page only describes what the code can do. Nothing here was run against them.
- **Access gate differs per screen** (FACT, `routes/v2.php:186-200` takes the module of the screen, not of the menu group): Master Customer, Raw Material, Raw Material Category and Sales Person are module MDATA, Master Product is SALES, Process and Machine are PROD. A Sales user without PROD or MDATA gets 403 on those items although they sit in the Sales menu. Not tried on screen.

## Relations
- **source**: users of Master Data (Sales, Finance, Production), legacy migration, quick customer from Inquiry, Quotation WIN (creates the Master Product, see the Quotation page).
- **used_by**: Inquiry, Quotation, Customer Order, ProDev, Pro-Cost, Procurement, Inventory (see the cards).

## Concept cards in this sub-module
| Card | Content |
|---|---|
| [10_card_customer.md](10_card_customer.md) | Customer on the Sales side (`cust_customer`, quick customer, approval pipeline) |
| [11_card_sales-pic.md](11_card_sales-pic.md) | Sales PIC (`sales_person`, text name in Inquiry and Quotation, id in CO) |

There is no card yet for Master Product, Raw Material, Raw Material Category, Process and Machine; this page maps their processes only.

## Engines in this sub-module
None. The raw material code is built by the screen (`resources/views/pages/master/raw-material/entry.blade.php:268-294`) and the category rules by `App\Services\Master\MaterialCategoryService`; neither is registered as an engine in these files (registry not checked).

## Read/Write map

[![Read/Write map](../img/rw-master-data.svg)](../img/rw-master-data.svg) Click the picture to open it full size.
Every process of the six menu items and the Sales Person master with the tables it reads and writes (C create, U update, D delete), checked in code on 2026-10-18 against the dummy DBs (ddtmst, ddtsdi, ddtacc, ddtprd, ddtsys: all table names exist; `v_geo_full_address` is a view; no DB triggers found in ddtmst, ddtsdi, ddtacc, ddtprd, ddtsys). Grid screens read their rows through `App\Support\V2\Grid\DbGrid::rows` (`app/Support/V2/Grid/DbGrid.php:359-372`); opening a row or New Record on Customer, Raw Material, Process and Machine leaves the v2 grid for the v1 Livewire page, so the v1 component is the evidence for those writes. Processes on Process and Machine are described, not run.

| Process | Trigger | Reads | Writes | Evidence |
|---|---|---|---|---|
| Open the Master Customer grid | Menu Master Customer, GET v2.screen md-customer | `cust_customer`, `fin_payment_term`, `co_order` | - | `routes/v2.php:186-200`, `app/Support/V2/Grid/Master/CustomerGrid.php:47-56` |
| Open the v1 customer page (list, form) | Grid row, or + New Record (GET /master/customer ?open or ?new) | `cust_customer`, `cust_customer_branch`, `cust_customer_contact`, `fin_payment_term`, `cust_category`, `org_division`, `v_geo_full_address` | - | `routes/web.php:210`, `resources/views/pages/master/customer.blade.php:216-229`, `resources/views/pages/master/customer.blade.php:255-280`, `app/Models/Master/CustCustomer.php:153-168` |
| Save a customer (new or edit) | v1 customer form, Save | `cust_customer`, `cust_category`, `fin_payment_term`, `org_division` | `cust_customer` (CU) | `resources/views/pages/master/customer.blade.php:665-749` |
| Change approval status, Block, Unblock | v1 customer form, status buttons, Block, Unblock | `cust_customer` | `cust_customer` (U) | `resources/views/pages/master/customer.blade.php:645-662`, `resources/views/pages/master/customer.blade.php:764-783`, `resources/views/pages/master/customer.blade.php:793-820` |
| Toggle a customer Active or Inactive | Grid switch (POST v2.grid.toggle) or v1 list action | `cust_customer` | `cust_customer` (U) | `routes/v2.php:226-231`, `app/Support/V2/Grid/DbGrid.php:375-390`, `resources/views/pages/master/customer.blade.php:751-757` |
| Customer branches: save, delete, set default | v1 customer form, Branch tab | `cust_customer_branch` | `cust_customer_branch` (CUD) | `resources/views/pages/master/customer.blade.php:353-413` |
| Customer contacts: save, delete, set default | v1 customer form, Contact tab | `cust_customer_contact` | `cust_customer_contact` (CUD) | `resources/views/pages/master/customer.blade.php:444-503` |
| Quick customer from Inquiry | Inquiry New, Customer lookup create (POST v2.inquiry.customer) | `cust_customer` | `cust_customer` (C) | `routes/v2.php:453-461`, `app/Services/Sales/InquiryWriter.php:158-179` |
| Open the Master Product grid | Menu Master Product, GET v2.screen master-product (no id) | `prod_product`, `cust_customer`, `prod_part`, `prod_part_artwork`, `prod_product_acc`, `mat_join_type` | - | `resources/views/v2/screens/master-product.blade.php:25`, `app/Support/V2/Grid/Sales/ProductMasterListGrid.php:31-33`, `app/Support/V2/Data/ProductMaster.php:33-52` |
| Open one product (identity, parts, ACC, order history) | Product grid, open a row (GET v2.screen master-product ?id) | `prod_product`, `cust_customer`, `prod_part`, `mat_join_type`, `prod_part_artwork`, `prod_part_material`, `mat_raw`, `mat_category`, `prod_part_ink`, `prod_part_process`, `prod_process`, `prod_machine`, `prod_part_finishing`, `ref_finishing_type`, `prod_product_acc`, `iqp_inquiry`, `quo_quotation_item`, `quo_quotation`, `co_order`, `co_order_item`, `co_order_delivery` | - | `resources/views/v2/screens/master-product.blade.php:16`, `resources/views/v2/screens/master-product.blade.php:29`, `app/Support/V2/Data/ProductMaster.php:55-79`, `app/Support/V2/Data/ProductMaster.php:86-115`, `app/Support/V2/Data/ProductMaster.php:117-153` |
| Rename the product | Product page, Product name Save (POST v2.product.name) | `prod_product` | `prod_product` (U) | `routes/v2.php:466-476` |
| Save the Digital Proof fields | Product page, Digital Proof Save (POST v2.product.proof) | `prod_product` | `prod_product` (U) | `routes/v2.php:477-482` |
| Record a customer ACC | Product page, ACC form with evidence file (POST v2.product.acc) | `prod_product` | `prod_product_acc` (C), `files:public` | `routes/v2.php:483-495` |
| Open the Master Raw Material grid | Menu Master Raw Material, GET v2.screen md-raw | `mat_raw` | - | `app/Support/V2/Grid/Master/RawMaterialGrid.php:52-57`, `app/Support/V2/Grid/DbGrid.php:359-372` |
| Open the v1 raw material list | v1 /master/raw-material | `mat_raw`, `sys_grid_preference` | - | `routes/web.php:215`, `resources/views/pages/master/raw-material/index.blade.php:65-71`, `resources/views/pages/master/raw-material/index.blade.php:94-103`, `app/Livewire/Concerns/HasCustomizableGrid.php:126-143` |
| Open a raw material item (form) | Grid row or + New Record (GET /master/raw-material/{id}/edit or /create) | `mat_raw`, `mat_category`, `uom_unit`, `sup_supplier`, `fin_account`, `fin_cost_center`, `fin_profit_center` | - | `routes/web.php:216-217`, `resources/views/pages/master/raw-material/entry.blade.php:121-168`, `resources/views/pages/master/raw-material/entry.blade.php:196-253` |
| Save a raw material item (code built from the category chain) | v1 item form, Simpan | `mat_raw`, `mat_category` | `mat_raw` (CU) | `resources/views/pages/master/raw-material/entry.blade.php:268-294`, `resources/views/pages/master/raw-material/entry.blade.php:296-373` |
| Confirm a raw material item | v1 item form, Confirm | `mat_raw` | `mat_raw` (U) | `resources/views/pages/master/raw-material/entry.blade.php:375-400` |
| Delete a raw material item | v1 item form, Hapus | `mat_raw` | `mat_raw` (D) | `resources/views/pages/master/raw-material/entry.blade.php:402-410` |
| Bulk delete unconfirmed items | v1 list, select rows, bulk delete | `mat_raw` | `mat_raw` (D) | `resources/views/pages/master/raw-material/index.blade.php:75-82` |
| Toggle an item Active or Inactive | Grid switch (POST v2.grid.toggle) or v1 list switch | `mat_raw` | `mat_raw` (U) | `routes/v2.php:226-231`, `resources/views/pages/master/raw-material/index.blade.php:105-112` |
| Save the user's grid columns | v1 raw material list, column dialog, sort, group, reset | `sys_grid_preference` | `sys_grid_preference` (CU) | `app/Livewire/Concerns/HasCustomizableGrid.php:145-156`, `app/Livewire/Concerns/HasCustomizableGrid.php:265-272` |
| Open the Material Category chain | Menu Master Raw Material Category, GET v2.screen md-matcat (?id, ?new, ?parent) | `mat_category`, `fin_account`, `mat_raw` | - | `app/Support/V2/Grid/Master/MatCategoryGrid.php:52-66`, `app/Support/V2/Grid/Master/MatCategoryGrid.php:107-123`, `resources/views/v2/screens/md-matcat.blade.php:52-53`, `app/Services/Master/MaterialCategoryService.php:100-113` |
| Save a category (name, prefix, accounts) | Category form, Save (POST v2.matcat.save) | `mat_category`, `fin_account`, `mat_raw` | `mat_category` (CU), `mat_raw` (U) | `routes/v2.php:1725-1738`, `app/Services/Master/MaterialCategoryService.php:115-232` |
| Activate or deactivate a category | Category form or grid switch (POST v2.matcat.save, action=activate or deactivate; POST v2.grid.toggle) | `mat_category` | `mat_category` (U) | `routes/v2.php:1730-1734`, `app/Support/V2/Grid/Master/MatCategoryGrid.php:125-130`, `app/Services/Master/MaterialCategoryService.php:236-243` |
| Print the whole category chain | Category grid, Print (GET v2.report.print md-matcat) | `mat_category` | - | `routes/v2.php:216-223`, `app/Support/V2/Grid/Master/MatCategoryGrid.php:160-184` |
| Open the Master Process grid | Menu Master Process, GET v2.screen prod-process | `prod_process`, `org_department`, `prod_process_machine` | - | `app/Support/V2/Grid/Prod/ProcessGrid.php:37-40`, `app/Support/V2/Grid/DbGrid.php:359-372` |
| Open the v1 process page (form, capability matrix, where used) | Grid row or + New Record (GET /production/master-process) | `prod_process`, `org_department`, `prod_process_machine`, `prod_machine`, `prod_part_process`, `prod_part_process_machine_rate`, `iqp_part_process`, `prd_wo_operation` | - | `routes/web.php:322`, `resources/views/pages/production/master-process.blade.php:116-139`, `resources/views/pages/production/master-process.blade.php:155-194`, `resources/views/pages/production/master-process.blade.php:213-216`, `app/Services/Production/MachineCapabilityService.php:208-222` |
| Save a process | v1 process form, Save | `prod_process` | `prod_process` (CU) | `resources/views/pages/production/master-process.blade.php:302-384` |
| Toggle a process Active or Inactive | Grid switch (POST v2.grid.toggle) or v1 list | `prod_process` | `prod_process` (U) | `routes/v2.php:226-231`, `resources/views/pages/production/master-process.blade.php:386-394` |
| Open the Master Machine grid | Menu Master Machine, GET v2.screen prod-machine | `prod_machine`, `prod_machine_model`, `prod_process_machine` | - | `app/Support/V2/Grid/Prod/MachineGrid.php:37-40`, `app/Support/V2/Grid/DbGrid.php:359-372` |
| Open the v1 machine page (form, capability matrix, downtime, where used) | Grid row or + New Record (GET /master/machine) | `prod_machine`, `prod_machine_model`, `org_division`, `org_department`, `prod_process_machine`, `prod_process`, `prod_machine_downtime`, `emp_work_center`, `prod_part_process`, `prod_part_process_machine_rate`, `prd_wo_operation` | - | `routes/web.php:212`, `resources/views/pages/master/machine.blade.php:209-227`, `resources/views/pages/master/machine.blade.php:235-268`, `resources/views/pages/master/machine.blade.php:301-317`, `app/Services/Production/MachineCapabilityService.php:224-237` |
| Save a machine | v1 machine form, Save | `prod_machine`, `prod_machine_model` | `prod_machine` (CU) | `resources/views/pages/master/machine.blade.php:462-583` |
| Toggle a machine Active or Inactive | Grid switch (POST v2.grid.toggle) or v1 list | `prod_machine` | `prod_machine` (U) | `routes/v2.php:226-231`, `resources/views/pages/master/machine.blade.php:634-646` |
| Machine downtime: add, close | v1 machine form, Maintenance tab | `prod_machine_downtime` | `prod_machine_downtime` (CU) | `resources/views/pages/master/machine.blade.php:585-626` |
| Process and machine capability: add, remove, restore, primary, certified, parameters | v1 process form tab Machines, or v1 machine form tab Processes | `prod_process_machine`, `prod_process`, `prod_machine` | `prod_process_machine` (CU) | `app/Livewire/Concerns/ManagesProcessMachineCapability.php:42-73`, `app/Livewire/Concerns/ManagesProcessMachineCapability.php:75-118`, `app/Livewire/Concerns/ManagesProcessMachineCapability.php:136-168` |
| Open the Sales Person master | Menu Sales Person, GET v2.screen md-sales-person | `sales_person`, `emp_employee` | - | `app/Support/V2/Grid/Master/SalesPersonLookup.php:42-53`, `app/Support/V2/Grid/DbGrid.php:359-372` |
| Save Sales Person rows (new, changed, Active) | Sales Person sheet, Save (POST v2.lookup.save) | `sales_person` | `sales_person` (CU) | `routes/v2.php:234-242`, `app/Support/V2/Grid/DbLookup.php:98-131`, `app/Support/V2/Grid/Master/SalesPersonLookup.php:55-85` |

Notes for debugging
- Customer: the form Save is a plain `create` or `update` of the header with no transaction (`resources/views/pages/master/customer.blade.php:738-743`); branch and contact are separate saves, so a new customer can exist without them. The unique customer code is checked by validation only (`resources/views/pages/master/customer.blade.php:666-669`). Unblock needs an admin or module FINACC (`resources/views/pages/master/customer.blade.php:794`), but Block and the approval-status buttons have no such check (`resources/views/pages/master/customer.blade.php:645-662`, `resources/views/pages/master/customer.blade.php:764-783`). There is no delete of a customer in the page; the toggle is the only way out. The Active switch in the grid goes through the v1 `toggleActive` (`app/Support/V2/Grid/DbGrid.php:378-381`). A customer row deleted directly in the database would CASCADE to its branches and contacts and SET NULL on `prod_product.customer_id` (rules read in ddtmst); the transaction tables (Inquiry, Quotation, Customer Order, CRM) hold the id without any foreign key, so nothing stops it there.
- Master Product: a product is not created on this screen; it is born at Quotation WIN or as a DRAFT at hand-over to ProDev (see the Quotation and Inquiry Product pages). The three posts here (name, proof, ACC) each update or create one row with no transaction (`routes/v2.php:471-495`); the ACC evidence file is stored under `product/{id}/acc` on the public disk before the row is created (`routes/v2.php:489-492`), so a failed insert leaves the file. The name is taken from the inquiry only at the first official sync, so a rename by Sales stays afterwards (`app/Services/Sales/ProductMasterSync.php:33-34`).
- Raw material: the item code is rebuilt from the prefixes of the chosen category chain until the item is Confirmed, with a numeric suffix when the code is taken (`resources/views/pages/master/raw-material/entry.blade.php:268-294`); after Confirm the category and code are locked on screen. The categories are stored on the item as NAMES (`category1` to `category5`), not ids (`resources/views/pages/master/raw-material/entry.blade.php:310-340`). The single-item Delete is shown for a Confirmed item too and `delete()` checks neither Confirm nor usage (`resources/views/pages/master/raw-material/entry.blade.php:402-410`, `resources/views/pages/master/raw-material/entry.blade.php:624-626`); the database blocks it only when a Master Product part material still points to the item (foreign key without action, checked in ddtmst), while Inquiry, Procurement and Inventory rows hold the id without any foreign key. Bulk delete filters `stat_confirm = false` in the query itself (`resources/views/pages/master/raw-material/index.blade.php:77`).
- Material category: Save and rename run in ONE tm2mst transaction and a rename rewrites the category NAME on every raw material that uses the chain (`app/Services/Master/MaterialCategoryService.php:200-231`); the prefix is locked once entries below it or items use it (`app/Services/Master/MaterialCategoryService.php:175-181`). A category cannot be activated under an inactive parent (`app/Services/Master/MaterialCategoryService.php:236-239`). Deactivating does not touch items that already use the chain. The grid and its toggle go through the same service (`app/Support/V2/Grid/Master/MatCategoryGrid.php:125-130`).
- Process and Machine (REAL data): `toggleActive` flips the flag at once and, for a machine, also sets `machine_status` to RETIRED or READY (`resources/views/pages/master/machine.blade.php:634-642`); the "where used" counts (`app/Services/Production/MachineCapabilityService.php:208-237`) are only displayed, no code path found that blocks a toggle or a save because of them. Removing a capability is a soft removal (`activate` = 0), never a delete (`app/Livewire/Concerns/ManagesProcessMachineCapability.php:75-88`), and the pair is unique in the database, so adding the same pair again re-activates the old row (`app/Livewire/Concerns/ManagesProcessMachineCapability.php:57-60`). Creating a new process or machine saves the header only and stays on the page for the capability step (`resources/views/pages/master/machine.blade.php:569-577`). The `prod_process_machine` rows carry their own speed, setup and waste parameters, so they are not a plain link table.
- Sales Person: a whole sheet is saved in ONE transaction, all rows or none (`app/Support/V2/Grid/DbLookup.php:104-131`); a saved name cannot be changed (`lockSaved`, `app/Support/V2/Grid/Master/SalesPersonLookup.php:57`) and an EXTERNAL person cannot be linked to an employee (`app/Support/V2/Grid/Master/SalesPersonLookup.php:81-84`). The table has no unique index on `sales_name` (index list checked in ddtmst); uniqueness is only the validation rule of the sheet. Documents store the NAME as text (see the Sales PIC card), so changing or deleting a row in the database directly does not break any foreign key.
- Not checked: `MachineOeeService` (OEE and reliability on the machine page) and the tables it reads; the object `x-mp::sales.product-master` and the x-dl form components; the Customer Order, Inquiry and ProDev writers of the same master tables; the Excel/print routes of the grids other than the category print; the unit-of-measure and supplier lookups beyond the reads listed; access gates beyond the screen-route module check; screen chrome (menu, QC bar, dictionary register).

## Table relations

Relations of the tables this sub-module uses. Solid line = real foreign key (databases `tm2mst` and `tm2sdi`). Dashed line = logical link without foreign key (cross-database, matched in code, or a name matched as text). Facts: foreign keys read from `information_schema` of ddtmst and ddtsdi in the mockup database; the logical links from the columns and the code read for this page (the target table of a logical link is taken from the code, for example `app/Services/Master/MaterialCategoryService.php:105-113` for the category names). No foreign key crosses a database (checked in every `ddt*` schema). `mat_raw.category1` to `mat_raw.category5` hold the category NAMES of the chain level by level; the row below shows the first one. `prod_process_machine` carries its own parameters, so it is written as two 1:N rows and no N:M row.

[![Table relations](../img/rel-master-data.svg)](../img/rel-master-data.svg) Click the picture to open it full size.

| Parent | Child | Cardinality | Column | Link |
|---|---|---|---|---|
| `fin_payment_term` | `cust_customer` | 1:N | `payment_term` | FK |
| `cust_category` | `cust_customer` | 1:N | `category` | FK |
| `org_division` | `cust_customer` | 1:N | `sales_division_id` | FK |
| `cust_customer` | `cust_customer_branch` | 1:N | `customer_id` | FK |
| `cust_customer` | `cust_customer_contact` | 1:N | `customer_id` | FK |
| `cust_customer` | `prod_product` | 1:N | `customer_id` | FK |
| `prod_product` | `prod_product` | 1:N | `parent_product_id` | FK |
| `prod_product` | `prod_product_acc` | 1:N | `product_id` | FK |
| `prod_product` | `prod_part` | 1:N | `product_id` | FK |
| `prod_part` | `prod_part` | 1:N | `parent_part_id` | FK |
| `mat_join_type` | `prod_part` | 1:N | `join_type_id` | FK |
| `prod_part` | `prod_part_artwork` | 1:N | `part_id` | FK |
| `prod_part` | `prod_part_material` | 1:N | `part_id` | FK |
| `prod_part` | `prod_part_ink` | 1:N | `part_id` | FK |
| `prod_part` | `prod_part_process` | 1:N | `part_id` | FK |
| `prod_part` | `prod_part_finishing` | 1:N | `part_id` | FK |
| `ref_finishing_type` | `prod_part_finishing` | 1:N | `finishing_type_id` | FK |
| `mat_raw` | `prod_part_material` | 1:N | `material_id` | FK |
| `mat_category` | `prod_part_material` | 1:N | `category_id` | FK |
| `mat_category` | `mat_category` | 1:N | `parent_id` | FK |
| `mat_category` | `mat_raw` | 1:N | `prefix5_id` | FK |
| `prod_process` | `prod_part_process` | 1:N | `process_id` | FK |
| `prod_machine` | `prod_part_process` | 1:N | `machine_id` | FK |
| `prod_process` | `prod_process_machine` | 1:N | `process_id` | FK |
| `prod_machine` | `prod_process_machine` | 1:N | `machine_id` | FK |
| `prod_machine_model` | `prod_machine` | 1:N | `machine_model_id` | FK |
| `org_division` | `prod_machine` | 1:N | `division_id` | FK |
| `org_division` | `org_department` | 1:N | `division_id` | FK |
| `prod_machine` | `prod_machine_downtime` | 1:N | `machine_id` | FK |
| `prod_process` | `prod_part_process_machine_rate` | 1:N | `process_id` | FK |
| `prod_machine` | `prod_part_process_machine_rate` | 1:N | `machine_id` | FK |
| `prod_product` | `prod_part_process_machine_rate` | 1:N | `product_id` | FK |
| `quo_quotation` | `quo_quotation_item` | 1:N | `quotation_id` | FK |
| `iqp_inquiry` | `quo_quotation_item` | 1:N | `inquiry_id` | FK |
| `quo_quotation` | `co_order` | 1:1 | `quotation_id` | FK |
| `co_order` | `co_order_item` | 1:N | `order_id` | FK |
| `co_order` | `co_order_delivery` | 1:N | `order_id` | FK |
| `co_order_item` | `co_order_delivery` | 1:N | `item_id` | FK |
| `iqp_inquiry` | `co_order_item` | 1:N | `inquiry_id` | FK |
| `emp_employee` | `sales_person` | 1:N | `employee_id` | logical |
| `fin_account` | `mat_category` | 1:N | `default_coa_account_id` | logical |
| `fin_account` | `mat_raw` | 1:N | `stock_asset_account_id` | logical |
| `fin_cost_center` | `mat_raw` | 1:N | `cost_center_id` | logical |
| `fin_profit_center` | `mat_raw` | 1:N | `profit_center_id` | logical |
| `sup_supplier` | `mat_raw` | 1:N | `suggest_supplier_id` | logical |
| `mat_category` | `mat_raw` | 1:N | `category1` | logical |
| `org_department` | `prod_process` | 1:N | `department_id` | logical |
| `org_department` | `prod_machine` | 1:N | `department_id` | logical |
| `prod_machine` | `emp_work_center` | 1:N | `machine_id` | logical |
| `prod_process` | `iqp_part_process` | 1:N | `process_id` | logical |
| `prod_machine` | `iqp_part_process` | 1:N | `machine_id` | logical |
| `prod_machine` | `prd_wo_operation` | 1:N | `machine_id` | logical |
| `prod_process` | `prd_wo_operation` | 1:N | `process_name` | logical |
| `cust_customer` | `quo_quotation` | 1:N | `customer_id` | logical |
| `cust_customer` | `co_order` | 1:N | `customer_id` | logical |
| `iqp_inquiry` | `prod_product` | 1:N | `source_inquiry_id` | logical |
| `prod_product` | `iqp_inquiry` | 1:N | `product_id` | logical |
| `prod_product` | `co_order_item` | 1:N | `product_id` | logical |

- Re-checked 2026-10-18 after the `routes/web.php` edit (v1 `/self-service` route replaced by a redirect to `v2.profile`, rule D.25); the block keeps its 4 lines so every cited line number is unchanged, content still true.
