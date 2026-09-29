# PROMPT SPECIFICATION & ARCHITECTURAL CONTRACT
## Module: Offset Packaging ERP — New Product Inquiry

Dokumentasi spesifikasi lengkap yang digunakan untuk memandu AI maupun engineering team dalam mereproduksi fungsionalitas New Product Inquiry secara presisi.

### I. Identity & Purpose
- **System**: Web-based ERP exclusively tailored for Offset Packaging & Printing SPK workflow.
- **Audience**: Technical Packaging Sales, Prepress CAD Engineers, Costing (HPP) Specialists, and Executive Management.
- **Compliance**: ECMA (European Carton Makers Association) and FEFCO standards.

### II. Core Functional Requirements
1. **Header Commercial**: Inquiry No auto-sequencing, Customer search, Product Name draft with case conversion, Sales PIC, Market Variant shortcuts.
2. **Tiered Quantity Engine**: Sequential tiers, duplicate 1.5x / 2x, exact 1 Primary tier validation.
3. **Multi-Part & Split Dieline CAD**:
   - Support for 2-piece configurations (e.g. Dus Sepatu: Badan & Tutup Terpisah).
   - Side-by-side split screen dieline visualization.
   - ISO color codes: Red cut line (`#ef4444`), Green score crease (`#10b981`), Slate bleed margin.
   - Live knife meters KPI calculations.
4. **3D Interactive CSS Viewport**:
   - 360° mouse drag orbital rotation.
   - Exploded view elevation slider for top lid (`0px` to `120px`).
   - Texture mapping support for artwork proofs.
5. **Tooling, Materials & Costs**: Substrate master catalog, CTP plate calculations, laser dieboard tooling entries, freight, and third-party testing fees.

### III. Target Stack Conversion
- **Backend**: PHP 8.4, Laravel 13
- **Frontend / Reactivity**: Livewire 4, Alpine.js, Tailwind CSS v4
- **CAD Vector**: SVG XML generation service
