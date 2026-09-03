<?php
/**
 * ERP Packaging Pre-Press & Imposition Optimizer Mockup
 * Standalone Tool (Can be embedded into ERP / Scriptcase / Laravel / Custom ERP)
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Prepress & Packaging Imposition Optimizer</title>
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-hover: #1d4ed8;
            --accent: #0284c7;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #cbd5e1;
            --text: #0f172a;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.5;
            padding: 24px;
        }

        .header-bar {
            background: linear-gradient(135deg, #1e3a8a, #0369a1);
            color: #ffffff;
            padding: 20px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .header-bar h1 {
            font-size: 20pt;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-bar p {
            font-size: 10.5pt;
            opacity: 0.9;
            margin-top: 4px;
        }

        .tab-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 8px;
            overflow-x: auto;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 10px 18px;
            font-size: 11pt;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .tab-btn:hover {
            background-color: #e2e8f0;
            color: var(--text);
        }

        .tab-btn.active {
            background-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card h3 {
            font-size: 13pt;
            margin-bottom: 16px;
            color: var(--primary);
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 9.5pt;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }

        input[type="number"], select, input[type="text"] {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 10pt;
            background-color: #ffffff;
            outline: none;
            transition: border-color 0.2s;
        }

        input[type="number"]:focus, select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(2,132,199,0.15);
        }

        .btn-calc {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: #ffffff;
            font-size: 11pt;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }

        .btn-calc:hover {
            background-color: var(--primary-hover);
        }

        /* Metric Highlights */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .metric-box {
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            text-align: center;
        }

        .metric-box.highlight {
            background-color: #eff6ff;
            border-color: #bfdbfe;
        }

        .metric-title {
            font-size: 9pt;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 18pt;
            font-weight: 800;
            color: var(--primary);
            margin: 4px 0;
        }

        .metric-desc {
            font-size: 8.5pt;
            color: var(--text-muted);
        }

        /* Canvas Preview Container */
        .canvas-wrapper {
            background: #ffffff;
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 420px;
            position: relative;
        }

        .canvas-toolbar {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 8px;
            background: rgba(255,255,255,0.9);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }

        .canvas-legend {
            display: flex;
            gap: 16px;
            margin-top: 12px;
            font-size: 9pt;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
        }

        /* Comparison Table */
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-top: 12px;
        }

        .table-custom th, .table-custom td {
            padding: 10px 12px;
            border: 1px solid var(--border);
            text-align: left;
        }

        .table-custom th {
            background-color: #f1f5f9;
            font-weight: 700;
            color: var(--text);
        }

        .badge-best {
            background-color: #dcfce7;
            color: #166534;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 8pt;
            font-weight: 700;
        }

        .info-callout {
            background-color: #f0fdf4;
            border-left: 4px solid var(--success);
            padding: 12px;
            border-radius: 4px;
            font-size: 9.5pt;
            margin-top: 16px;
        }
    </style>
</head>
<body>

    <div class="header-bar">
        <h1>📐 ERP Prepress Imposition & Packaging Optimizer</h1>
        <p>Modul Cerdas Optimasi Potong Plano, Susunan Imposisi, Interlocking Nesting & Estimasi Pisau Pond untuk Industri Cetak Offset & Kemasan.</p>
    </div>

    <!-- Navigation Tabs -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('tab-p2s')">📦 Pcs to Plano (Cari Kertas Termurah)</button>
        <button class="tab-btn" onclick="switchTab('tab-s2p')">📄 Plano to Pcs (Optimasi Up dari Stok Plano)</button>
        <button class="tab-btn" onclick="switchTab('tab-nesting')">🧩 Interlocking Nesting (Model Kemasan L/T)</button>
        <button class="tab-btn" onclick="switchTab('tab-diemaking')">🔪 Die-Rule Estimator (Biaya Pisau Pond)</button>
        <button class="tab-btn" onclick="switchTab('tab-gang')">📑 Multi-Job Gang Run (Gabungan Cetak)</button>
    </div>

    <!-- ==================== TAB 1: PCS TO PLANO ==================== -->
    <div id="tab-p2s" class="tab-content active">
        <div class="grid-container">
            <!-- Parameter Form -->
            <div class="card">
                <h3>Dimensi Bentangan Dus (Pcs)</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Panjang Bentangan (cm)</label>
                        <input type="number" id="p2s_box_w" value="22.5" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Lebar Bentangan (cm)</label>
                        <input type="number" id="p2s_box_h" value="16.0" step="0.1">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Bleed / Margin Dus (mm)</label>
                        <input type="number" id="p2s_bleed" value="3" step="1">
                    </div>
                    <div class="form-group">
                        <label>Jarak Antar Pcs / Gap (mm)</label>
                        <input type="number" id="p2s_gap" value="3" step="1">
                    </div>
                </div>

                <div class="form-group">
                    <label>Target Oplah Dus (Pcs)</label>
                    <input type="number" id="p2s_qty" value="10000" step="500">
                </div>

                <div class="form-group">
                    <label>Gramatur Kertas (GSM)</label>
                    <select id="p2s_gsm">
                        <option value="250">Duplex 250 GSM</option>
                        <option value="310" selected>Ivory / Art Carton 310 GSM</option>
                        <option value="350">Ivory / Art Carton 350 GSM</option>
                        <option value="400">Duplex / Kraft 400 GSM</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Harga Kertas (Rp / kg)</label>
                    <input type="number" id="p2s_paper_price" value="16500" step="500">
                </div>

                <div class="form-group">
                    <label>Inschiet / Wastage Cetak (%)</label>
                    <input type="number" id="p2s_waste" value="5" step="0.5">
                </div>

                <button class="btn-calc" onclick="calculatePcsToPlano()">Hitung & Bandingkan Plano</button>
            </div>

            <!-- Result & Layout Preview -->
            <div class="card">
                <h3>Hasil Optimasi & Rekomendasi Plano</h3>
                
                <div class="metrics-grid">
                    <div class="metric-box highlight">
                        <div class="metric-title">Plano Terbaik</div>
                        <div class="metric-value" id="p2s_res_plano">-</div>
                        <div class="metric-desc" id="p2s_res_cut_scheme">Skema Potong: 1/2 Plano</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-title">Total Up Per Plano</div>
                        <div class="metric-value" id="p2s_res_total_up">-</div>
                        <div class="metric-desc" id="p2s_res_eff">Efisiensi Area: 0%</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-title">Kebutuhan Plano (Gross)</div>
                        <div class="metric-value" id="p2s_res_sheets">-</div>
                        <div class="metric-desc" id="p2s_res_rims">0 Rim (0 kg)</div>
                    </div>
                    <div class="metric-box highlight">
                        <div class="metric-title">Total Biaya Kertas</div>
                        <div class="metric-value" id="p2s_res_cost">Rp 0</div>
                        <div class="metric-desc" id="p2s_res_cost_per_pcs">Rp 0 / pcs dus</div>
                    </div>
                </div>

                <div class="canvas-wrapper">
                    <div class="canvas-toolbar">
                        <span style="font-size: 8.5pt; color: var(--text-muted);">Preview Visual Lembar Cetak</span>
                    </div>
                    <canvas id="p2s_canvas" width="540" height="340"></canvas>
                    <div class="canvas-legend">
                        <div class="legend-item"><div class="legend-color" style="background:#0284c7;"></div> Area Cetak Dus (Up)</div>
                        <div class="legend-item"><div class="legend-color" style="background:#cbd5e1;"></div> Margin / Waste Kertas</div>
                        <div class="legend-item"><div class="legend-color" style="background:#f59e0b;"></div> Gripper Mesin (12mm)</div>
                    </div>
                </div>

                <h4 style="margin-top:20px; font-size:11pt; color:var(--text);">Perbandingan Semua Ukuran Standar Plano Pabrik:</h4>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Ukuran Plano (cm)</th>
                            <th>Skema Potong Mesin</th>
                            <th>Up / Plano</th>
                            <th>Kebutuhan Kertas</th>
                            <th>Efisiensi Kertas</th>
                            <th>Total Biaya Kertas</th>
                            <th>Rekomendasi</th>
                        </tr>
                    </thead>
                    <tbody id="p2s_table_body">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 2: PLANO TO PCS ==================== -->
    <div id="tab-s2p" class="tab-content">
        <div class="grid-container">
            <div class="card">
                <h3>Input Kertas Plano yang Tersedia</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Lebar Plano (cm)</label>
                        <input type="number" id="s2p_plano_w" value="79" step="0.5">
                    </div>
                    <div class="form-group">
                        <label>Tinggi Plano (cm)</label>
                        <input type="number" id="s2p_plano_h" value="109" step="0.5">
                    </div>
                </div>

                <div class="form-group">
                    <label>Skema Potong Plano ke Mesin Cetak</label>
                    <select id="s2p_cut_type">
                        <option value="1">1/1 Plano Penuh (Tanpa Potong Awal)</option>
                        <option value="2" selected>1/2 Plano (Potong 2 bagian sama)</option>
                        <option value="3">1/3 Plano (Potong 3)</option>
                        <option value="4">1/4 Plano (Potong 4)</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Lebar Dus Bentangan (cm)</label>
                        <input type="number" id="s2p_box_w" value="21" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Tinggi Dus Bentangan (cm)</label>
                        <input type="number" id="s2p_box_h" value="15" step="0.1">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Gripper Margin Mesin (mm)</label>
                        <input type="number" id="s2p_gripper" value="12" step="1">
                    </div>
                    <div class="form-group">
                        <label>Side / Tail Margin (mm)</label>
                        <input type="number" id="s2p_margin" value="8" step="1">
                    </div>
                </div>

                <button class="btn-calc" onclick="calculatePlanoToPcs()">Hitung Pola Susun Cetak</button>
            </div>

            <div class="card">
                <h3>Visualisasi Pola Imposisi Sheet Mesin Cetak</h3>
                <div class="metrics-grid">
                    <div class="metric-box">
                        <div class="metric-title">Ukuran Sheet Cetak</div>
                        <div class="metric-value" id="s2p_sheet_size">0 x 0 cm</div>
                        <div class="metric-desc">Masuk ke Feeder Mesin</div>
                    </div>
                    <div class="metric-box highlight">
                        <div class="metric-title">Up Per Lembar Cetak</div>
                        <div class="metric-value" id="s2p_up_sheet">0 Up</div>
                        <div class="metric-desc" id="s2p_up_plano">Total 0 Up / Plano</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-title">Pola Susun Terbaik</div>
                        <div class="metric-value" id="s2p_pattern_name" style="font-size:14pt;">-</div>
                        <div class="metric-desc">Otomatisasi Rotasi & Grid</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-title">Wastage / Sisa Area</div>
                        <div class="metric-value" id="s2p_waste_pct">0%</div>
                        <div class="metric-desc" id="s2p_waste_cm2">0 cm² terbuang</div>
                    </div>
                </div>

                <div class="canvas-wrapper">
                    <canvas id="s2p_canvas" width="540" height="340"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 3: PACKAGING NESTING ==================== -->
    <div id="tab-nesting" class="tab-content">
        <div class="grid-container">
            <div class="card">
                <h3>Parameter Interlocking Nesting</h3>
                <p style="font-size: 9pt; color: var(--text-muted); margin-bottom: 14px;">
                    Fitur gaya <em>Esko ArtiosCAD</em> untuk menghitung penghematan kertas pada dus berbentuk khusus (Lidah Flap / Botol / Hanger Box) dengan cara saling mengunci (Interlock).
                </p>

                <div class="form-group">
                    <label>Tipe Model Dus</label>
                    <select id="nest_type" onchange="renderNestingDemo()">
                        <option value="tuck">Straight Tuck End (Dus Obat / Kosmetik Standar)</option>
                        <option value="lock">Auto-Lock Bottom (Dus Kue / Makanan Berat)</option>
                        <option value="hanger">Header / Blister Hanger Box</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Panjang Kotak P (cm)</label>
                        <input type="number" id="nest_box_p" value="8" step="0.5">
                    </div>
                    <div class="form-group">
                        <label>Lebar Kotak L (cm)</label>
                        <input type="number" id="nest_box_l" value="5" step="0.5">
                    </div>
                </div>

                <div class="form-group">
                    <label>Tinggi Kotak T (cm)</label>
                    <input type="number" id="nest_box_t" value="14" step="0.5">
                </div>

                <div class="form-group">
                    <label>Metode Penataan Pisau (Nesting Pattern)</label>
                    <select id="nest_pattern" onchange="renderNestingDemo()">
                        <option value="interlock">Interlocking (Putar 180° - Flap Saling Mengunci)</option>
                        <option value="straight">Straight Grid (Kotak Biasa Tanpa Interlock)</option>
                    </select>
                </div>

                <button class="btn-calc" onclick="renderNestingDemo()">Simulasikan Nesting Pisau</button>

                <div class="info-callout">
                    💡 <strong>Tips ERP:</strong> Menggunakan pola <em>Interlocking</em> dapat menghemat 12% hingga 24% luas kertas plano dibandingkan metode lurus biasa (*Straight Grid*).
                </div>
            </div>

            <div class="card">
                <h3>Visualisasi Interlocking & Pisau Bersama (Common Cut)</h3>
                
                <div class="metrics-grid">
                    <div class="metric-box highlight">
                        <div class="metric-title">Hemat Area Kertas</div>
                        <div class="metric-value" id="nest_saving_pct">14.8%</div>
                        <div class="metric-desc">Lebih efisien dari grid lurus</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-title">Pengurangan Garis Pisau</div>
                        <div class="metric-value" id="nest_common_knife">18%</div>
                        <div class="metric-desc">Menggunakan Common Knife Cut</div>
                    </div>
                </div>

                <div class="canvas-wrapper">
                    <canvas id="nest_canvas" width="540" height="340"></canvas>
                    <div class="canvas-legend">
                        <div class="legend-item"><div class="legend-color" style="background:#3b82f6;"></div> Pcs Orientasi A</div>
                        <div class="legend-item"><div class="legend-color" style="background:#10b981;"></div> Pcs Orientasi B (Rotasi 180°)</div>
                        <div class="legend-item"><div class="legend-color" style="background:#ef4444;"></div> Garis Pisau Potong (Die Cut)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 4: DIE-RULE ESTIMATOR ==================== -->
    <div id="tab-diemaking" class="tab-content">
        <div class="grid-container">
            <div class="card">
                <h3>Parameter Pisau Pond (Die Making Cost)</h3>
                <div class="form-group">
                    <label>Jumlah Up dalam 1 Papan Pisau</label>
                    <input type="number" id="die_total_up" value="6" step="1">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Keliling Potong 1 Dus (cm)</label>
                        <input type="number" id="die_cut_per_pcs" value="95" step="1">
                    </div>
                    <div class="form-group">
                        <label>Panjang Garis Rel / Crease (cm)</label>
                        <input type="number" id="die_crease_per_pcs" value="70" step="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Biaya Pisau Potong (Rp / meter)</label>
                    <input type="number" id="die_rate_cut" value="18000" step="1000">
                </div>
                <div class="form-group">
                    <label>Biaya Pisau Rel (Rp / meter)</label>
                    <input type="number" id="die_rate_crease" value="14000" step="1000">
                </div>
                <div class="form-group">
                    <label>Biaya Kayu Papan Die & Laser Base (Rp)</label>
                    <input type="number" id="die_board_cost" value="150000" step="10000">
                </div>
                <button class="btn-calc" onclick="calculateDieCost()">Hitung Estimasi HPP Pisau</button>
            </div>

            <div class="card">
                <h3>Rincian Estimasi Biaya Pembuatan Pisau Pond</h3>
                <div class="metrics-grid">
                    <div class="metric-box highlight">
                        <div class="metric-title">Total Biaya Pisau</div>
                        <div class="metric-value" id="die_total_cost">Rp 0</div>
                        <div class="metric-desc">Masuk ke HPP Order / Pisau Baru</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-title">Total Meter Pisau Potong</div>
                        <div class="metric-value" id="die_total_cut_m">0 m</div>
                        <div class="metric-desc" id="die_cost_cut_sub">Rp 0</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-title">Total Meter Pisau Rel</div>
                        <div class="metric-value" id="die_total_crease_m">0 m</div>
                        <div class="metric-desc" id="die_cost_crease_sub">Rp 0</div>
                    </div>
                </div>

                <div class="info-callout">
                    📋 <strong>Integrasi ERP:</strong> Nilai ini langsung dialokasikan ke modul <em>Costing Estimation</em> sebagai komponen <em>Tooling / Mold Charge</em> pada pembuatan SPK pertama kali.
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 5: MULTI-JOB GANG RUN ==================== -->
    <div id="tab-gang" class="tab-content">
        <div class="card">
            <h3>📑 Multi-Job Gang-Run Optimizer (Gabungan Cetak Dus Berbeda Ukuran)</h3>
            <p style="font-size: 10pt; color: var(--text-muted); margin-bottom: 16px;">
                Menggabungkan beberapa Surat Perintah Kerja (SPK) dengan jenis kertas & gramatur yang sama ke dalam satu plano cetak untuk menekan biaya plat CTP dan waktu naik mesin.
            </p>

            <table class="table-custom">
                <thead>
                    <tr>
                        <th>No SPK</th>
                        <th>Nama Item Dus</th>
                        <th>Ukuran Bentangan (cm)</th>
                        <th>Target Oplah (Pcs)</th>
                        <th>Alokasi Up di Plano</th>
                        <th>Hasil Cetak Riil</th>
                        <th>Over/Under</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>SPK-2026-081</strong></td>
                        <td>Kemasan Box Serum 30ml</td>
                        <td>14.5 x 18.0</td>
                        <td>5.000</td>
                        <td><input type="number" value="2" style="width:70px; padding:4px;" onchange="recalcGang()"> Up</td>
                        <td id="gang_res_1">5.200</td>
                        <td><span class="badge-best">+200 (Aman)</span></td>
                    </tr>
                    <tr>
                        <td><strong>SPK-2026-082</strong></td>
                        <td>Kemasan Box Night Cream 50g</td>
                        <td>16.0 x 20.5</td>
                        <td>2.500</td>
                        <td><input type="number" value="1" style="width:70px; padding:4px;" onchange="recalcGang()"> Up</td>
                        <td id="gang_res_2">2.600</td>
                        <td><span class="badge-best">+100 (Aman)</span></td>
                    </tr>
                    <tr>
                        <td><strong>SPK-2026-083</strong></td>
                        <td>Kemasan Box Toner 100ml</td>
                        <td>18.0 x 22.0</td>
                        <td>5.000</td>
                        <td><input type="number" value="2" style="width:70px; padding:4px;" onchange="recalcGang()"> Up</td>
                        <td id="gang_res_3">5.200</td>
                        <td><span class="badge-best">+200 (Aman)</span></td>
                    </tr>
                </tbody>
            </table>

            <div class="info-callout" style="margin-top: 16px;">
                ✨ <strong>Efisiensi Gang-Run:</strong> Menghemat 2 set Plat CTP (Rp 300.000) dan 2x Biaya Setup Mesin Cetak Offset (Rp 500.000).
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        // Database Ukuran Standar Plano Pabrik di Indonesia
        const STANDARD_PLANOS = [
            { name: "61 x 86 cm", w: 61, h: 86 },
            { name: "65 x 90 cm", w: 65, h: 90 },
            { name: "65 x 100 cm", w: 65, h: 100 },
            { name: "79 x 109 cm", w: 79, h: 109 },
            { name: "70 x 100 cm", w: 70, h: 100 },
            { name: "60 x 90 cm", w: 60, h: 90 }
        ];

        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');

            if(tabId === 'tab-p2s') calculatePcsToPlano();
            if(tabId === 'tab-s2p') calculatePlanoToPcs();
            if(tabId === 'tab-nesting') renderNestingDemo();
            if(tabId === 'tab-diemaking') calculateDieCost();
        }

        // Format Currency Rupiah
        function formatRp(val) {
            return "Rp " + Math.round(val).toLocaleString('id-ID');
        }

        // TAB 1: Pcs to Plano Calculation
        function calculatePcsToPlano() {
            const boxW = parseFloat(document.getElementById('p2s_box_w').value);
            const boxH = parseFloat(document.getElementById('p2s_box_h').value);
            const bleed = parseFloat(document.getElementById('p2s_bleed').value) / 10;
            const gap = parseFloat(document.getElementById('p2s_gap').value) / 10;
            const targetQty = parseInt(document.getElementById('p2s_qty').value);
            const gsm = parseInt(document.getElementById('p2s_gsm').value);
            const paperPriceKg = parseFloat(document.getElementById('p2s_paper_price').value);
            const wastePct = parseFloat(document.getElementById('p2s_waste').value) / 100;

            const unitW = boxW + (2 * bleed) + gap;
            const unitH = boxH + (2 * bleed) + gap;

            const gripperMargin = 1.2; // 12mm gripper
            const sideMargin = 0.8;   // 8mm side/tail margin

            let results = [];

            STANDARD_PLANOS.forEach(plano => {
                // Skema 1: Potong 2 (1/2 Plano)
                const sHalfW = plano.w / 2;
                const sHalfH = plano.h;
                const effW1 = sHalfW - (2 * sideMargin);
                const effH1 = sHalfH - gripperMargin - sideMargin;

                const upStraight1 = Math.floor(effW1 / unitW) * Math.floor(effH1 / unitH);
                const upRotated1 = Math.floor(effW1 / unitH) * Math.floor(effH1 / unitW);
                const bestUpHalf = Math.max(upStraight1, upRotated1);
                const totalUpPlanoHalf = bestUpHalf * 2;

                // Skema 2: 1/1 Plano Penuh
                const effWFull = plano.w - (2 * sideMargin);
                const effHFull = plano.h - gripperMargin - sideMargin;
                const upStraightFull = Math.floor(effWFull / unitW) * Math.floor(effHFull / unitH);
                const upRotatedFull = Math.floor(effWFull / unitH) * Math.floor(effHFull / unitW);
                const totalUpPlanoFull = Math.max(upStraightFull, upRotatedFull);

                // Pilih skema terbaik untuk plano ini
                let bestScheme = "1/2 Plano";
                let bestTotalUp = totalUpPlanoHalf;
                let sheetW = sHalfW, sheetH = sHalfH;
                let isRotated = upRotated1 > upStraight1;

                if (totalUpPlanoFull > totalUpPlanoHalf) {
                    bestScheme = "1/1 Plano";
                    bestTotalUp = totalUpPlanoFull;
                    sheetW = plano.w; sheetH = plano.h;
                    isRotated = upRotatedFull > upStraightFull;
                }

                if (bestTotalUp > 0) {
                    const grossPlanoSheets = Math.ceil((targetQty / bestTotalUp) * (1 + wastePct));
                    const totalRims = (grossPlanoSheets / 500).toFixed(2);
                    const areaM2 = (plano.w / 100) * (plano.h / 100);
                    const totalWeightKg = (areaM2 * (gsm / 1000) * grossPlanoSheets);
                    const totalCost = totalWeightKg * paperPriceKg;
                    const efficiency = ((bestTotalUp * (boxW * boxH)) / (plano.w * plano.h) * 100).toFixed(1);

                    results.push({
                        planoName: plano.name,
                        planoW: plano.w,
                        planoH: plano.h,
                        sheetW: sheetW,
                        sheetH: sheetH,
                        scheme: bestScheme,
                        totalUp: bestTotalUp,
                        grossSheets: grossPlanoSheets,
                        rims: totalRims,
                        weightKg: totalWeightKg.toFixed(1),
                        totalCost: totalCost,
                        efficiency: efficiency,
                        isRotated: isRotated
                    });
                }
            });

            // Urutkan berdasarkan total biaya termurah
            results.sort((a, b) => a.totalCost - b.totalCost);

            if (results.length > 0) {
                const best = results[0];
                document.getElementById('p2s_res_plano').innerText = best.planoName;
                document.getElementById('p2s_res_cut_scheme').innerText = `Skema Mesin: ${best.scheme}`;
                document.getElementById('p2s_res_total_up').innerText = `${best.totalUp} Up`;
                document.getElementById('p2s_res_eff').innerText = `Efisiensi Area: ${best.efficiency}%`;
                document.getElementById('p2s_res_sheets').innerText = `${best.grossSheets.toLocaleString('id-ID')} Lbr`;
                document.getElementById('p2s_res_rims').innerText = `${best.rims} Rim (${best.weightKg} kg)`;
                document.getElementById('p2s_res_cost').innerText = formatRp(best.totalCost);
                document.getElementById('p2s_res_cost_per_pcs').innerText = `${formatRp(best.totalCost / targetQty)} / pcs dus`;

                // Render Table
                let html = '';
                results.forEach((r, idx) => {
                    html += `<tr>
                        <td><strong>${r.planoName}</strong></td>
                        <td>${r.scheme}</td>
                        <td><strong>${r.totalUp} Up</strong></td>
                        <td>${r.grossSheets.toLocaleString('id-ID')} lbr (${r.rims} Rim)</td>
                        <td>${r.efficiency}%</td>
                        <td><strong>${formatRp(r.totalCost)}</strong></td>
                        <td>${idx === 0 ? '<span class="badge-best">⭐ Paling Hemat</span>' : '-'}</td>
                    </tr>`;
                });
                document.getElementById('p2s_table_body').innerHTML = html;

                // Draw Canvas Preview
                drawSheetCanvas('p2s_canvas', best.sheetW, best.sheetH, unitW, unitH, best.isRotated);
            }
        }

        // Draw Imposition Canvas
        function drawSheetCanvas(canvasId, sW, sH, uW, uH, rotated) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            const scale = Math.min((canvas.width - 40) / sW, (canvas.height - 40) / sH);
            const offsetX = (canvas.width - (sW * scale)) / 2;
            const offsetY = (canvas.height - (sH * scale)) / 2;

            // Sheet Kertas (Background)
            ctx.fillStyle = "#f1f5f9";
            ctx.strokeStyle = "#475569";
            ctx.lineWidth = 2;
            ctx.fillRect(offsetX, offsetY, sW * scale, sH * scale);
            ctx.strokeRect(offsetX, offsetY, sW * scale, sH * scale);

            // Gripper Machine (Bawah / Bawah Sheet)
            ctx.fillStyle = "#fde68a";
            ctx.fillRect(offsetX, offsetY + (sH * scale) - (1.2 * scale), sW * scale, 1.2 * scale);

            // Kotak-kotak Imposisi (Up)
            const itemW = (rotated ? uH : uW) * scale;
            const itemH = (rotated ? uW : uH) * scale;

            const cols = Math.floor((sW - 1.6) / (rotated ? uH : uW));
            const rows = Math.floor((sH - 2.0) / (rotated ? uW : uH));

            ctx.fillStyle = "#38bdf8";
            ctx.strokeStyle = "#0284c7";
            ctx.lineWidth = 1.5;

            for (let r = 0; r < rows; r++) {
                for (let c = 0; c < cols; c++) {
                    const x = offsetX + (0.8 * scale) + (c * itemW);
                    const y = offsetY + (0.8 * scale) + (r * itemH);
                    
                    ctx.fillRect(x + 2, y + 2, itemW - 4, itemH - 4);
                    ctx.strokeRect(x + 2, y + 2, itemW - 4, itemH - 4);

                    // Cross mark in center
                    ctx.fillStyle = "#ffffff";
                    ctx.font = "10px sans-serif";
                    ctx.fillText(`UP ${r*cols + c + 1}`, x + 6, y + 16);
                    ctx.fillStyle = "#38bdf8";
                }
            }
        }

        // TAB 2: Plano to Pcs Calculation
        function calculatePlanoToPcs() {
            const pW = parseFloat(document.getElementById('s2p_plano_w').value);
            const pH = parseFloat(document.getElementById('s2p_plano_h').value);
            const cutType = parseInt(document.getElementById('s2p_cut_type').value);
            const bW = parseFloat(document.getElementById('s2p_box_w').value);
            const bH = parseFloat(document.getElementById('s2p_box_h').value);
            const gripper = parseFloat(document.getElementById('s2p_gripper').value) / 10;
            const margin = parseFloat(document.getElementById('s2p_margin').value) / 10;

            let sW = pW, sH = pH;
            if (cutType === 2) { sW = pW / 2; sH = pH; }
            if (cutType === 3) { sW = pW / 3; sH = pH; }
            if (cutType === 4) { sW = pW / 2; sH = pH / 2; }

            const effW = sW - (2 * margin);
            const effH = sH - gripper - margin;

            const up1 = Math.floor(effW / bW) * Math.floor(effH / bH);
            const up2 = Math.floor(effW / bH) * Math.floor(effH / bW);

            const isRot = up2 > up1;
            const bestUpPerSheet = Math.max(up1, up2);
            const totalUpPlano = bestUpPerSheet * cutType;

            const totalUsedArea = bestUpPerSheet * (bW * bH);
            const sheetArea = sW * sH;
            const wasteArea = sheetArea - totalUsedArea;
            const wastePct = ((wasteArea / sheetArea) * 100).toFixed(1);

            document.getElementById('s2p_sheet_size').innerText = `${sW.toFixed(1)} x ${sH.toFixed(1)} cm`;
            document.getElementById('s2p_up_sheet').innerText = `${bestUpPerSheet} Up`;
            document.getElementById('s2p_up_plano').innerText = `Total ${totalUpPlano} Up / Plano Penuh`;
            document.getElementById('s2p_pattern_name').innerText = isRot ? "Rotasi 90°" : "Lurus (Straight)";
            document.getElementById('s2p_waste_pct').innerText = `${wastePct}%`;
            document.getElementById('s2p_waste_cm2').innerText = `${Math.round(wasteArea)} cm² terbuang`;

            drawSheetCanvas('s2p_canvas', sW, sH, bW, bH, isRot);
        }

        // TAB 3: Nesting Demo Simulation
        function renderNestingDemo() {
            const pattern = document.getElementById('nest_pattern').value;
            const canvas = document.getElementById('nest_canvas');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (pattern === 'interlock') {
                document.getElementById('nest_saving_pct').innerText = "16.4%";
                document.getElementById('nest_common_knife').innerText = "22%";
            } else {
                document.getElementById('nest_saving_pct').innerText = "0.0% (Standar)";
                document.getElementById('nest_common_knife').innerText = "0%";
            }

            // Draw illustrative nested packaging flaps
            ctx.lineWidth = 2;
            const count = 4;
            const stepX = 110;

            for (let i = 0; i < count; i++) {
                const x = 40 + (i * stepX);
                const isEven = i % 2 === 0;

                ctx.fillStyle = isEven || pattern === 'straight' ? "rgba(59, 130, 246, 0.4)" : "rgba(16, 185, 129, 0.4)";
                ctx.strokeStyle = "#dc2626";

                ctx.beginPath();
                if (pattern === 'interlock' && !isEven) {
                    // Inverted box shape
                    ctx.moveTo(x + 10, 40);
                    ctx.lineTo(x + 90, 40);
                    ctx.lineTo(x + 90, 240);
                    ctx.lineTo(x + 60, 280); // interlocking tuck tab
                    ctx.lineTo(x + 10, 240);
                } else {
                    // Regular box shape with top flap
                    ctx.moveTo(x + 10, 80);
                    ctx.lineTo(x + 40, 40); // top flap
                    ctx.lineTo(x + 90, 80);
                    ctx.lineTo(x + 90, 280);
                    ctx.lineTo(x + 10, 280);
                }
                ctx.closePath();
                ctx.fill();
                ctx.stroke();

                // Creasing dashed lines
                ctx.strokeStyle = "#475569";
                ctx.setLineDash([4, 4]);
                ctx.beginPath();
                ctx.moveTo(x + 10, 140);
                ctx.lineTo(x + 90, 140);
                ctx.stroke();
                ctx.setLineDash([]);
            }
        }

        // TAB 4: Die-Making Cost Calculator
        function calculateDieCost() {
            const up = parseInt(document.getElementById('die_total_up').value);
            const cutPcs = parseFloat(document.getElementById('die_cut_per_pcs').value) / 100; // to meter
            const creasePcs = parseFloat(document.getElementById('die_crease_per_pcs').value) / 100;
            const rateCut = parseFloat(document.getElementById('die_rate_cut').value);
            const rateCrease = parseFloat(document.getElementById('die_rate_crease').value);
            const boardCost = parseFloat(document.getElementById('die_board_cost').value);

            const totalCutM = (up * cutPcs);
            const totalCreaseM = (up * creasePcs);

            const subCut = totalCutM * rateCut;
            const subCrease = totalCreaseM * rateCrease;
            const grandTotal = subCut + subCrease + boardCost;

            document.getElementById('die_total_cost').innerText = formatRp(grandTotal);
            document.getElementById('die_total_cut_m').innerText = `${totalCutM.toFixed(1)} m`;
            document.getElementById('die_cost_cut_sub').innerText = formatRp(subCut);
            document.getElementById('die_total_crease_m').innerText = `${totalCreaseM.toFixed(1)} m`;
            document.getElementById('die_cost_crease_sub').innerText = formatRp(subCrease);
        }

        function recalcGang() {
            // Sample recalculation for multi-job table
        }

        // Init calculations
        window.onload = function() {
            calculatePcsToPlano();
            calculatePlanoToPcs();
            calculateDieCost();
        };
    </script>
</body>
</html>
