{{--
    Kerangka bersama semua dokumen cetak "Nota" (bar cetak, page A4, kop,
    parties, section bernomor, tanda tangan, footer) -- diekstrak 2026-08-28
    dari inquiry/nota.blade.php + quotation/print.blade.php yang ternyata
    strukturnya identik, beda cuma warna tema & konten isi. Lihat
    docs/printnotastandard.md utk kontrak lengkap & daftar komponen turunan
    (print.sheet, print.header, print.parties/party, print.section,
    print.signatures/signature, print.footer).

    Warna tema di-override via prop `theme` (array key=>hex, TANPA prefix
    --print-) supaya tiap dokumen tetap punya identitas warna sendiri (nota
    hijau, quotation navy, dst) sambil pakai SATU set CSS rule yang sama.
    Konten SPESIFIK per dokumen (tabel harga, tabel item, kotak bank, dst)
    TETAP di halaman pemanggil masing-masing dgn <style> sendiri -- komponen
    ini cuma kerangka umum, bukan pengganti seluruh styling dokumen.
--}}
@props([
    'theme' => [],
    'printLabel' => '🖶 Cetak / Simpan PDF',
])
@php
    $defaultTheme = [
        'ink' => '#1a1d21', 'muted' => '#6b7280', 'line' => '#d4d8dd', 'line2' => '#eceff2',
        'accent' => '#2f6fed', 'accent-soft' => '#eaf0ff', 'warn' => '#8a5a00', 'gold' => '#9a7b18', 'danger' => '#a3241f',
    ];
    $vars = array_merge($defaultTheme, $theme);
    $styleVars = collect($vars)->map(fn ($v, $k) => "--print-{$k}:{$v}")->implode(';');
@endphp
<div {{ $attributes->merge(['class' => 'print-doc']) }} style="{{ $styleVars }}">
    <div class="print-bar">
        <button type="button" onclick="window.print()" class="pr">{{ $printLabel }}</button>
        {{ $toolbar ?? '' }}
        <span class="info">{{ $info ?? '' }}</span>
    </div>

    {{ $slot }}
</div>

@once
    <style>
        .print-doc{background:#8e959c;font-family:"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
             font-size:10pt;color:var(--print-ink);padding:24px 0;
             -webkit-print-color-adjust:exact;print-color-adjust:exact}
        .print-doc *{box-sizing:border-box}

        .print-doc .print-bar{width:210mm;margin:0 auto 12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .print-doc .print-bar button,.print-doc .print-bar a{border:0;border-radius:5px;padding:8px 16px;font-size:9.5pt;font-weight:600;
                    cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block}
        .print-doc .print-bar .pr{background:var(--print-accent);color:#fff}
        .print-doc .print-bar .sc{background:#fff;color:var(--print-ink);border:1px solid var(--print-line)}
        .print-doc .print-bar .info{color:#fff;font-size:8.5pt;margin-left:auto;opacity:.9}

        .print-doc .print-page{width:210mm;min-height:297mm;margin:0 auto 18px;background:#fff;
              padding:12mm 12mm 18mm;position:relative;box-shadow:0 6px 24px rgba(0,0,0,.28)}

        .print-doc .print-kop{display:flex;justify-content:space-between;align-items:flex-start;
             border-bottom:2.5px solid var(--print-accent);padding-bottom:7px}
        .print-doc .print-kop .logo{width:42px;height:42px;border-radius:7px;background:var(--print-accent);color:#fff;
              display:flex;align-items:center;justify-content:center;font-weight:700;
              font-size:14pt;letter-spacing:-1px;flex:0 0 auto}
        .print-doc .print-kop .kop-l{display:flex;gap:10px;align-items:center}
        .print-doc .print-kop .co-name{font-size:13pt;font-weight:700;letter-spacing:-.3px;line-height:1.1;color:var(--print-accent)}
        .print-doc .print-kop .co-addr{font-size:7.2pt;color:var(--print-muted);line-height:1.4;margin-top:2px}
        .print-doc .print-kop .kop-r{text-align:right}
        .print-doc .print-kop .doc-title{font-size:11.5pt;font-weight:700;color:var(--print-accent);letter-spacing:.5px}
        .print-doc .print-kop .doc-no{font-size:14pt;font-weight:700;font-family:Consolas,monospace;
                letter-spacing:-.5px;margin-top:1px}
        .print-doc .print-kop .doc-meta{font-size:7.6pt;color:var(--print-muted);margin-top:2px;line-height:1.45}

        .print-doc .print-parties{display:grid;grid-template-columns:repeat(auto-fit, minmax(0, 1fr));margin-top:8px;border:1px solid var(--print-line)}
        .print-doc .print-party{padding:6px 9px}
        .print-doc .print-party+.print-party{border-left:1px solid var(--print-line)}
        .print-doc .print-party h4{font-size:6.6pt;letter-spacing:1px;color:var(--print-muted);font-weight:700;margin-bottom:2px}
        .print-doc .print-party .nm{font-size:10pt;font-weight:600;line-height:1.25}
        .print-doc .print-party .dt{font-size:7.6pt;color:#4b5158;line-height:1.5;margin-top:1px}

        .print-doc .print-sec{margin-top:10px}
        .print-doc .print-sec-h{display:flex;align-items:center;gap:6px;margin-bottom:5px}
        .print-doc .print-sec-h .n{width:15px;height:15px;border-radius:3px;background:var(--print-accent);color:#fff;
                  font-size:7.6pt;font-weight:700;display:flex;align-items:center;
                  justify-content:center;flex:0 0 auto}
        .print-doc .print-sec-h h3{font-size:8.6pt;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:var(--print-accent)}
        .print-doc .print-sec-h .rule{flex:1;height:1px;background:var(--print-line)}
        .print-doc .print-sec-h .hint{font-size:7pt;color:var(--print-muted);font-weight:400;letter-spacing:0}

        .print-doc .print-two{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:2px}
        .print-doc .print-box{border:1px solid var(--print-line);padding:6px 9px;font-size:7.8pt;line-height:1.58}
        .print-doc .print-box h5{font-size:6.6pt;letter-spacing:.9px;color:var(--print-muted);font-weight:700;margin-bottom:3px}
        .print-doc ol.print-terms{margin-left:13px;font-size:7.8pt;line-height:1.62}
        .print-doc ol.print-terms li{margin-bottom:1.5px}
        .print-doc .print-warn{border:1.5px dashed var(--print-warn);background:#fffbf2;padding:10px 12px;
              page-break-inside:avoid}
        .print-doc .print-warn .t{font-size:10.5pt;font-weight:700;color:var(--print-warn)}
        .print-doc .print-warn .d{font-size:8.4pt;color:#5f4a1c;line-height:1.55;margin-top:3px}

        .print-doc .print-sign{border:1.5px solid var(--print-accent);margin-top:10px;page-break-inside:avoid}
        .print-doc .print-sign-h{background:var(--print-accent);color:#fff;padding:4px 9px;font-size:7.8pt;
                   font-weight:700;letter-spacing:.8px}
        .print-doc .print-sign-b{padding:9px}
        .print-doc .print-sign-intro{font-size:7.8pt;line-height:1.55;color:#4b5158;margin-bottom:8px}
        .print-doc .print-sign-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(0, 1fr));gap:10px;text-align:center;margin-top:10px}
        .print-doc .print-sign-cell{font-size:7.8pt}
        .print-doc .print-sign-cell .co{font-size:6.8pt;color:var(--print-muted);margin-bottom:2px}
        .print-doc .print-sign-cell .line{border-bottom:1px solid var(--print-ink);height:42px;margin-bottom:3px}
        .print-doc .print-sign-cell .rol{font-weight:700;text-transform:uppercase;letter-spacing:.4px;font-size:7.4pt;color:var(--print-accent)}
        .print-doc .print-sign-cell .nm{font-size:7.8pt;font-weight:600;margin-top:1px}
        .print-doc .print-sign-cell .sub{font-size:6.8pt;color:var(--print-muted)}

        .print-doc .print-foot{position:absolute;bottom:8mm;left:12mm;right:12mm;border-top:1px solid var(--print-line);
              padding-top:4px;font-size:6.6pt;color:var(--print-muted);display:flex;
              justify-content:space-between;gap:10px}

        @media print{
          .print-doc{background:#fff;padding:0}
          .print-doc .print-bar{display:none}
          .print-doc .print-page{box-shadow:none;margin:0;width:auto;min-height:auto;padding:11mm 11mm 16mm}
          @page{size:A4 portrait;margin:0}
        }
    </style>
@endonce
