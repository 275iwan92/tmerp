{{--
 REMARK
 Tujuan       : Layar Inquiry Note: daftar inquiry dengan akses cetak Nota Inquiry (price offer + spesifikasi ke customer). Menggantikan layar placeholder dan cetak v1 pages::inquiry.nota.
 Sumber hulu  : App\Support\V2\Grid\Sales\InquiryNoteGrid; cetakan App\Support\V2\Print\InquiryNotePrint.
 Input        : -
 Output       : Daftar lewat _dbgrid; klik baris membuka v2.print type inquiry-note.
 Dipakai oleh : Route v2.screen slug nota-inquiry (menu sys_app_menu target v2:nota-inquiry); tombol Print Note di Inquiry New membuka cetakan langsung.
 Status       : Aktif (T10, 2026-10-20).
--}}
@include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Sales\InquiryNoteGrid]])
