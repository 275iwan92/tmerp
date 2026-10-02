# Render mockup cetak ke JPEG: powershell -File docs/mockups/print_doc/render.ps1 [-Only SuratJalan]
param([string]$Only = '')
$ErrorActionPreference = 'Continue'   # Chrome menulis peringatan ke stderr; jangan dianggap gagal
Add-Type -AssemblyName System.Drawing
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$browser = @("$env:ProgramFiles\Google\Chrome\Application\chrome.exe", "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
$base = ([uri](Join-Path $here 'view.html')).AbsoluteUri
$codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object MimeType -eq 'image/jpeg'
$enc = New-Object System.Drawing.Imaging.EncoderParameters(1)
$enc.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, [long]92)

# nama file, kelas dokumen, query tambahan, tinggi jendela (lebar 1678 = 210 mm + tepi, zoom 2)
$jobs = @(
    @('surat_jalan_a5', 'SuratJalan', '', 1210), @('surat_jalan_a5_deviasi', 'SuratJalan', '&dev=1', 1720), @('surat_jalan_a4', 'SuratJalan', '&case=a4', 2336),
    @('customer_order_a5', 'CustomerOrder', '', 1210), @('customer_order_a5_deviasi', 'CustomerOrder', '&dev=1', 1720), @('customer_order_a4', 'CustomerOrder', '&case=a4', 2336),
    @('customer_order_noprice_a5', 'CustomerOrderNoPrice', '', 1210), @('customer_order_noprice_a4', 'CustomerOrderNoPrice', '&case=a4', 2336),
    @('cpdel_a5', 'CustomerPlanDelivery', '', 1210), @('cpdel_a4', 'CustomerPlanDelivery', '&case=a4', 2336),
    @('purchase_order_a5', 'PurchaseOrder', '', 1210), @('purchase_order_a5_deviasi', 'PurchaseOrder', '&dev=1', 1720),
    @('jadwal_kirim_a5', 'JadwalKirim', '', 1210), @('jadwal_kirim_a5_deviasi', 'JadwalKirim', '&dev=1', 1640), @('jadwal_kirim_a4', 'JadwalKirim', '&case=a4', 2336),
    @('inquiry_a5', 'Inquiry', '', 1210), @('purchase_requisition_a5', 'PurchaseRequisition', '', 1210),
    @('purchase_requisition_nonprod_a5', 'PurchaseRequisition', '&case=nonprod', 1210),
    @('quotation_a5', 'Quotation', '', 1210), @('delivery_order_a5', 'DeliveryOrder', '', 1210), @('proof_of_delivery_a5', 'ProofOfDelivery', '', 1210),
    @('purchase_quotation_a5', 'PurchaseQuotation', '', 1210), @('goods_receipt_a5', 'GoodsReceipt', '', 1210), @('material_booking_a5', 'MaterialBooking', '', 1210),
    @('maintenance_request_a5', 'MaintenanceRequest', '', 1210), @('maintenance_wo_a5', 'MaintenanceWorkOrder', '', 1210), @('petty_cash_a5', 'PettyCash', '', 1210),
    @('stock_in_a5', 'StockIn', '', 1210), @('stock_out_a5', 'StockOut', '', 1210), @('stock_return_a5', 'StockReturn', '', 1210), @('stock_adjustment_a5', 'StockAdjustment', '', 1210),
    @('purchase_return_a5', 'PurchaseReturn', '', 1210), @('work_order_a5', 'WorkOrder', '', 1210), @('journal_voucher_a5', 'JournalVoucher', '', 1210),
    @('supplier_invoice_receipt_a5', 'SupplierInvoiceReceipt', '', 1210), @('down_payment_a5', 'DownPaymentInvoice', '', 1210), @('down_payment_receipt_a5', 'DownPaymentReceipt', '', 1210), @('leave_request_a5', 'LeaveRequest', '', 1210),
    @('manpower_requisition_a5', 'ManpowerRequisition', '', 1210))

foreach ($j in $jobs) {
    if ($Only -and $j[1] -ne $Only) { continue }
    $png = [System.IO.Path]::ChangeExtension([System.IO.Path]::GetTempFileName(), 'png')
    $profile = Join-Path ([System.IO.Path]::GetTempPath()) ('mockup-chrome-' + [guid]::NewGuid())
    & $browser --headless=new --disable-gpu --hide-scrollbars --allow-file-access-from-files "--user-data-dir=$profile" "--window-size=1678,$($j[3])" `
        --virtual-time-budget=4000 "--screenshot=$png" "$base`?doc=$($j[1])$($j[2])" 2>$null | Out-Null
    if (-not (Test-Path $png)) { throw "Screenshot gagal: $($j[0])" }
    $img = [System.Drawing.Image]::FromFile($png)
    try { $img.Save((Join-Path $here "$($j[0]).jpg"), $codec, $enc) } finally { $img.Dispose() }
    Remove-Item $png, $profile -Recurse -Force -ErrorAction SilentlyContinue
    Write-Output "$($j[0]).jpg"
}
