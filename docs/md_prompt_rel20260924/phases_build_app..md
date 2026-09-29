# Phases Build App

## Phase #1: Build the Basic DEVLib

Create a pure collection of object-oriented UI/UX components so that programming with components becomes simpler and more straightforward.

- No database connections.
- Just a pure collection of reusable components.
- Place everything under the namespace/package `"devlib.test"` for easy monitoring and debugging.

**Lock Phase 1 first.**  
In Phase 1, focus only on **standard screens**. Special/custom screens will be moved to the mockup phase.

Phase 1 should contain only:
- Basic components
- Basic screens

You may create many variants, but the focus must remain strictly on UI/UX components. Keep the menu structure simple and clean.

Once I explicitly approve Phase 1, we move to Phase 2.

### Scope & Limitation of DEVLib

DEVLib’s purpose is to build standardized, object-oriented screen components that can later be easily reused in the mockup. These components must be easy to modify — both properties and methods can be overridden.

**Example:** A standard Kanban component will be used in the mockup and then overridden (properties and methods) so it matches the specific needs of the user.

I agree with the approach you used for the Approval form — it makes it easy for users to understand. If you can propose an even more attractive block-based layout, that would be even better.

---

## Phase #2: Build the ERP Mockup

Create a complete collection of mockups for the ERP system.  
Focus primarily on:
- Special forms and grids
- Custom/special screens

Standard screens may also exist, but the priority is on special screens.

Also include:
- Print-out examples that can be used as templates
- Coverage from transactional screens → master data → supporting lookup data

No database connection yet.  
However, every field/component must have a **tooltip** that clearly explains:
- Which table it will later connect to
- Which database it belongs to

In addition to focusing on special screens, also focus on:
- Module-to-module flow
- Data Migration flow

All mockups must be placed under `"mockup.test"` for easy monitoring and structuring.

---

## Phase #3: Build ERP with Dummy Data

Connect to a database, but only with **dummy data**.  
This environment will serve as the implementation playground and UAT site.  
Site name: `"erptest.test"`

Main focus:
- Core Engines / Functions (especially optimization engines)
- Building a Local AI

During UAT with dummy data + data migration, the system should help detect if users struggle with editing or data input.

One important goal of this mockup is also to verify whether the database tables are already optimized.  
Therefore, you must maintain a complete list of all columns, tables, and database names so that duplicate columns for the same purpose never occur.

This feature is for developers only and is only visible inside the mockup.  
Whenever the structure is added or removed, this facility must be automatically updated.  
We can call this the **Data Dictionary**.

---

## Phase #4: Build the Real ERP

Build the actual ERP system on the foundation of Phase #1 and Phase #2.  
Site name: `"tmerp.test"`

At this stage, everything is fully connected to the real database.  
The focus must be production-ready (“real”).

**Note:** `erptest.test` and `tmerp.test` share the **same codebase** — only the data is different.