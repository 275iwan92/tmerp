/* ============================================================================
 * Grid Spreadsheet (docs/spreadsheetgridstandard.md) -- adopsi perilaku
 * "AutoFill" DataTables.net versi ringan buatan sendiri (keputusan user
 * 2026-08-30: tanpa dependency baru, gaya visual ikut design system tmerp).
 *
 * SEMUA render baris & state edit murni client-side (rows/orig di sini) --
 * server (App\Livewire\Concerns\HasSpreadsheetGrid) cuma dipanggil 1x lewat
 * saveSpreadsheetBatch() saat tombol "Simpan Semua" diklik (mode BATCH,
 * bukan autosave per sel). Sesudah save, Livewire re-render dgn wire:key
 * baru (lihat spreadsheet-table.blade.php) yang bikin Alpine reinit total --
 * itu sebabnya tidak ada logic "clear dirty" manual di sini.
 * ============================================================================ */
document.addEventListener('alpine:init', () => {
    Alpine.data('spreadsheetGrid', (initialRows, columns, newRowDefaults, initialErrors) => ({
        columns: columns || [],
        rows: [],
        orig: [],
        errors: initialErrors || {},
        active: null, // { r, key }
        filling: false,
        fillFrom: null, // { r, key }
        fillToRow: null,
        nextTemp: -1,
        newRowDefaults: newRowDefaults || {},

        init() {
            this.rows = (initialRows || []).map((r) => ({ ...r }));
            this.orig = JSON.parse(JSON.stringify(this.rows));
            this._onMouseUp = () => this.endFill();
            window.addEventListener('mouseup', this._onMouseUp);
            this.$watch('rows', () => {}); // no-op, cukup supaya Alpine reactive-track array of objects
        },

        destroy() {
            window.removeEventListener('mouseup', this._onMouseUp);
        },

        editableColumns() {
            return this.columns.filter((c) => c.type !== 'action');
        },

        isNewRow(row) {
            return typeof row.id === 'string' && row.id.startsWith('new:');
        },

        isEditable(row, col) {
            if (col.type === 'action') return false;
            if (col.editableWhenExisting === false && !this.isNewRow(row)) return false;
            return true;
        },

        actionVisible(row, col) {
            if (!col.hideWhenColumn) return true;
            return row[col.hideWhenColumn] !== col.hideWhenValue;
        },

        cellError(row, key) {
            const rowErrors = this.errors[String(row.id)];
            return rowErrors && rowErrors[key] ? rowErrors[key][0] : null;
        },

        isDirty(r, key) {
            const row = this.rows[r];
            if (this.isNewRow(row)) return true;
            const o = this.orig[r];

            return !o || o[key] !== row[key];
        },

        rowDirty(r) {
            return this.editableColumns().some((c) => this.isDirty(r, c.key));
        },

        dirtyCount() {
            return this.rows.filter((_, r) => this.rowDirty(r)).length;
        },

        activateCell(r, key) {
            this.active = { r, key };
        },

        isActive(r, key) {
            return !!this.active && this.active.r === r && this.active.key === key;
        },

        blurCell() {
            this.active = null;
        },

        moveActive(r, key, dir) {
            const cols = this.editableColumns().filter((c) => c.type === 'text' || c.type === 'number');
            const idx = cols.findIndex((c) => c.key === key);
            let nr = r;
            let ni = idx;

            if (dir === 'right') {
                ni++;
                if (ni >= cols.length) { ni = 0; nr++; }
            } else if (dir === 'left') {
                ni--;
                if (ni < 0) { nr--; ni = cols.length - 1; }
            } else if (dir === 'down') {
                nr++;
            }

            if (nr < 0 || nr >= this.rows.length || !cols[ni]) {
                this.blurCell();

                return;
            }

            const targetKey = cols[ni].key;
            this.activateCell(nr, targetKey);
            this.$nextTick(() => {
                const el = this.$root.querySelector(`[data-cell="${nr}:${targetKey}"]`);
                if (el) { el.focus(); el.select && el.select(); }
            });
        },

        addRow() {
            const row = { id: `new:${this.nextTemp--}`, ...this.newRowDefaults };
            this.rows.push(row);
            this.orig.push({ ...row });
        },

        removeRow(r) {
            if (!this.isNewRow(this.rows[r])) return;
            this.rows.splice(r, 1);
            this.orig.splice(r, 1);
        },

        // AutoFill sederhana (BUKAN Excel-series-detection asli) -- drag ke
        // bawah cuma COPY nilai sel sumber ke semua baris yg dilewati.
        startFill(r, key) {
            this.filling = true;
            this.fillFrom = { r, key };
            this.fillToRow = r;
        },

        overFill(r) {
            if (this.filling) this.fillToRow = r;
        },

        isFilling(r, key) {
            if (!this.filling || !this.fillFrom || this.fillFrom.key !== key) return false;
            const lo = Math.min(this.fillFrom.r, this.fillToRow);
            const hi = Math.max(this.fillFrom.r, this.fillToRow);

            return r >= lo && r <= hi;
        },

        endFill() {
            if (!this.filling || !this.fillFrom) { this.filling = false; return; }

            const { r: fromR, key } = this.fillFrom;
            const lo = Math.min(fromR, this.fillToRow);
            const hi = Math.max(fromR, this.fillToRow);
            const value = this.rows[fromR][key];
            const col = this.columns.find((c) => c.key === key);

            for (let i = lo; i <= hi; i++) {
                if (i === fromR) continue;
                if (this.isEditable(this.rows[i], col)) {
                    this.rows[i][key] = value;
                }
            }

            this.filling = false;
            this.fillFrom = null;
        },

        runAction(row, col) {
            if (this.isNewRow(row)) return;
            this.$wire.call(col.method, row[col.paramKey || 'id']);
        },

        async saveAll() {
            if (this.dirtyCount() === 0) return;
            await this.$wire.saveSpreadsheetBatch(this.rows);
        },
    }));
});
