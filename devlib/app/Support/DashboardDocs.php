<?php

namespace App\Support;

// Isi Job Description & SOP per KODE JABATAN untuk opening screen DevLib (tanpa database).
// Format mengikuti dokumen resmi perusahaan: "JD & KPI - STAFF FINANCE" (Daily/Weekly/Monthly/Seasonal + KPI berbobot)
// dan "SOP PERIZINAN KARYAWAN" (Nomor/Perihal/Revisi/Tanggal Efektif + bagian A, B, ...) di docs/attachments.
// Di tmerp: JD = org_position.job_description (HTML, diisi HR Officer), SOP = sop_document.content (HTML).
final class DashboardDocs
{
    public static function jobdesc(?string $position): ?array
    {
        return match ($position) {
            'SRSTAFFSALESMKT' => ['title' => 'Senior Staff Sales & Marketing', 'updated' => '28 Sep 2026 oleh HR Officer', 'html' => self::head('011/IX/TM-HRD/2026 (contoh)', 'Sales & Marketing', 'Operasional', 'Senior Staff Sales & Marketing').<<<'HTML'
                <h4>Job Description</h4>
                <ol type="I">
                  <li><b>Harian</b><ol>
                    <li>Menghubungi prospek dan customer (telepon, email, WhatsApp) sesuai rencana follow-up, lalu mencatat setiap aktivitas di sistem.</li>
                    <li>Menerima kebutuhan kemasan customer dan membuat <b>Inquiry Product</b> lengkap (tipe produk, dimensi, bahan, finishing, jumlah) sebagai dasar ProDev &amp; ProCost.</li>
                    <li>Memantau status inquiry, harga dari ProCost, dan quotation yang menunggu jawaban customer.</li>
                    <li>Menyusun dan mengirim <b>Quotation</b> dari inquiry yang harganya sudah dipublikasikan.</li>
                    <li>Membuat <b>Customer Order</b> setelah customer setuju dan memastikan due date terisi di setiap baris produk.</li>
                    <li>Menjadi penghubung customer untuk proof/sampel dan persetujuan artwork.</li>
                  </ol></li>
                  <li><b>Mingguan</b><ol>
                    <li>Melakukan kunjungan (visit) ke prospek dan customer sesuai jadwal mingguan.</li>
                    <li>Mengikuti rapat mingguan Sales &amp; Marketing: pipeline, quotation terbuka, dan kendala pengiriman.</li>
                    <li>Mengonfirmasi rencana pengiriman (Customer Plan Delivery) bersama bagian Delivery dan PPIC.</li>
                  </ol></li>
                  <li><b>Bulanan</b><ol>
                    <li>Menyusun laporan pencapaian omzet, win rate quotation, customer baru, dan repeat order.</li>
                    <li>Meninjau customer existing yang lama tidak memesan dan menyusun rencana tindak lanjut.</li>
                  </ol></li>
                  <li><b>Musiman</b><ol>
                    <li>Menyiapkan penawaran khusus untuk musim ramai (Ramadan, Lebaran, akhir tahun).</li>
                    <li>Mengikuti pameran atau kegiatan promosi yang ditugaskan perusahaan.</li>
                  </ol></li>
                </ol>
                <h4>Key Performance Indicator</h4>
                <table><thead><tr><th>Aspek Penilaian</th><th>Tolok Ukur</th><th>Bobot</th></tr></thead><tbody>
                  <tr><td>Kedisiplinan</td><td>Jumlah absensi (izin, terlambat, alpha) dibandingkan presensi seharusnya dalam 1 bulan.</td><td>15%</td></tr>
                  <tr><td>Pencapaian Omzet</td><td>Omzet Customer Order CONFIRMED dibandingkan target bulanan.</td><td>40%</td></tr>
                  <tr><td>Win Rate Quotation</td><td>Jumlah quotation menjadi Customer Order dibandingkan quotation terkirim.</td><td>20%</td></tr>
                  <tr><td>Aktivitas Sales</td><td>Jumlah telepon, email, dan visit dibandingkan target aktivitas.</td><td>15%</td></tr>
                  <tr><td>Customer Baru</td><td>Jumlah customer baru (order pertama) dibandingkan target.</td><td>10%</td></tr>
                </tbody></table>
                HTML],
            'HROFFICER' => ['title' => 'Human Resource Officer', 'updated' => '28 Sep 2026 oleh HR Manager', 'html' => self::head('012/IX/TM-HRD/2026 (contoh)', 'Human Capital', 'Human Capital', 'Human Resource Officer').<<<'HTML'
                <h4>Job Description</h4>
                <ol type="I">
                  <li><b>Harian</b><ol>
                    <li>Memantau presensi (fingerprint), keterlambatan, dan pengajuan izin/sakit/cuti sesuai SOP Perizinan.</li>
                    <li>Memeriksa kelengkapan dokumen pendukung izin dan sakit (surat dokter, kwitansi, surat izin keluar).</li>
                    <li>Melayani pertanyaan karyawan terkait kepegawaian.</li>
                  </ol></li>
                  <li><b>Mingguan</b><ol>
                    <li>Merekap absensi dan lembur untuk proses penggajian mingguan.</li>
                    <li>Menjadwalkan interview kandidat bersama atasan pengguna.</li>
                  </ol></li>
                  <li><b>Bulanan</b><ol>
                    <li>Menyusun laporan kehadiran, izin, alpha, dan surat peringatan per departemen.</li>
                    <li>Memperbarui data karyawan, kontrak, dan BPJS.</li>
                  </ol></li>
                  <li><b>Musiman</b><ol>
                    <li>Menyiapkan penilaian kinerja tahunan dan perpanjangan kontrak.</li>
                  </ol></li>
                </ol>
                <h4>Key Performance Indicator</h4>
                <table><thead><tr><th>Aspek Penilaian</th><th>Tolok Ukur</th><th>Bobot</th></tr></thead><tbody>
                  <tr><td>Kedisiplinan</td><td>Jumlah absensi (izin, terlambat, alpha) dibandingkan presensi seharusnya dalam 1 bulan.</td><td>20%</td></tr>
                  <tr><td>Ketepatan Rekap Payroll</td><td>Rekap absensi &amp; lembur terkirim tepat waktu dibandingkan jadwal payroll.</td><td>35%</td></tr>
                  <tr><td>Akurasi Data Karyawan</td><td>Jumlah kesalahan data karyawan dibandingkan total perubahan data.</td><td>25%</td></tr>
                  <tr><td>Rekrutmen</td><td>Posisi terisi dibandingkan permintaan tenaga kerja pada periode yang sama.</td><td>20%</td></tr>
                </tbody></table>
                HTML],
            default => null,
        };
    }

    public static function sops(?string $position): array
    {
        return match ($position) {
            'SRSTAFFSALESMKT' => [
                ['code' => 'SOP-SLS-001', 'title' => 'Penanganan Inquiry Customer', 'revision' => '0', 'effective' => '1 Okt 2026 (contoh)', 'html' => <<<'HTML'
                    <h4>A. Tujuan</h4><p>Memastikan setiap permintaan customer tercatat lengkap sehingga ProDev dan ProCost dapat bekerja tanpa bertanya ulang.</p>
                    <h4>B. Langkah</h4><ol>
                      <li>Catat kebutuhan customer: tipe produk, dimensi, bahan, jumlah warna, finishing, dan jumlah pesanan.</li>
                      <li>Buat <b>Inquiry Product</b> — pilih Baru, Revisi (spesifikasi berubah), atau Rekalkulasi (spesifikasi sama, harga bahan berubah).</li>
                      <li>Lampirkan artwork atau contoh fisik bila ada.</li>
                      <li>Confirm inquiry agar masuk antrean ProDev / ProCost.</li>
                      <li>Informasikan perkiraan waktu penawaran kepada customer.</li>
                    </ol>
                    HTML],
                ['code' => 'SOP-SLS-002', 'title' => 'Penyusunan & Pengiriman Quotation', 'revision' => '0', 'effective' => '1 Okt 2026 (contoh)', 'html' => <<<'HTML'
                    <h4>A. Ketentuan</h4><ol>
                      <li>Quotation hanya disusun dari inquiry yang harganya sudah dipublikasikan ProCost.</li>
                      <li>Harga, jumlah, dan diskon per baris boleh disesuaikan sebelum publish; diskon mengikuti kewenangan atasan.</li>
                      <li>Kirim quotation ke customer dan catat tanggal kirim.</li>
                      <li>Lakukan follow-up paling lambat 3 hari kerja setelah quotation dikirim.</li>
                    </ol>
                    HTML],
                ['code' => 'SOP-SLS-003', 'title' => 'Customer Order & Due Date', 'revision' => '0', 'effective' => '1 Okt 2026 (contoh)', 'html' => <<<'HTML'
                    <h4>A. Ketentuan</h4><ol>
                      <li>Satu Customer Order berasal dari satu Quotation yang disetujui customer, atau dibuat manual dari Master Product.</li>
                      <li>Due date wajib terisi di setiap baris produk sebelum Customer Order di-Confirm.</li>
                      <li>Due date dihitung 14 hari kerja sejak <b>approval proof fisik</b>, bukan sejak PO atau mockup.</li>
                      <li>Setelah Confirm, periksa Customer Plan Delivery: split atau gabung jadwal kirim bila diminta customer.</li>
                    </ol>
                    HTML],
                ['code' => 'SOP-SLS-004', 'title' => 'Kunjungan & Follow-up Customer', 'revision' => '0', 'effective' => '1 Okt 2026 (contoh)', 'html' => <<<'HTML'
                    <h4>A. Ketentuan</h4><ol>
                      <li>Susun rencana visit mingguan; prioritaskan prospek panas dan customer yang lama tidak memesan.</li>
                      <li>Bawa sampel dan katalog terbaru yang relevan dengan kebutuhan customer.</li>
                      <li>Catat hasil setiap telepon, email, dan visit di sistem pada hari yang sama.</li>
                      <li>Keluar area perusahaan untuk visit wajib memakai Surat Izin Keluar / penugasan sesuai SOP Perizinan.</li>
                    </ol>
                    HTML],
                self::perizinan(),
            ],
            'HROFFICER' => [self::perizinan()],
            default => $position ? [self::perizinan()] : [],
        };
    }

    // SOP resmi perusahaan (docs/attachments/SOP PERIZINAN KARYAWAN TELASIH MAKMUR.pdf), diringkas. Berlaku semua karyawan.
    private static function perizinan(): array
    {
        return ['code' => '002/RP/TM/VII/2026', 'title' => 'Rules & Policy Perizinan Karyawan', 'revision' => '-', 'effective' => '6 Juli 2026', 'html' => <<<'HTML'
            <h4>A. Tujuan</h4><p>Meningkatkan kedisiplinan serta efektivitas &amp; efisiensi kinerja seluruh karyawan.</p>
            <h4>B. Ketentuan Umum</h4><ol>
              <li>Ketidakhadiran di luar sakit dan cuti yang disetujui mengikuti ketentuan <b>No Work No Pay</b>.</li>
              <li>Karyawan wajib memberi informasi ketidakhadiran kepada atasan <b>dan</b> HRD; izin mendadak lewat WhatsApp.</li>
              <li>Izin sah setelah disetujui atasan yang berwenang dan/atau HRD; izin memotong cuti tahunan bila hak cuti masih ada.</li>
            </ol>
            <h4>C. Sakit</h4><ol>
              <li>Informasikan paling lambat <b>pukul 07.30 WIB</b> pada hari ketidakhadiran.</li>
              <li>Lampirkan Surat Keterangan Dokter serta kwitansi/resep, paling lambat hari H masuk kerja; bila tidak lengkap dicatat sebagai Izin.</li>
            </ol>
            <h4>D. Izin &amp; Cuti</h4><ol>
              <li>Izin yang diketahui sebelumnya diajukan minimal <b>H-7</b>; maksimal 3 kali dalam 1 bulan, yang ke-4 dianggap Alpha.</li>
              <li>Cuti diajukan minimal H-7 kerja, maksimal 3 hari berturut-turut, dengan surat cuti yang disetujui atasan.</li>
              <li>Tukar shift memakai Form Tukar Shift paling lambat H-1.</li>
            </ol>
            <h4>E. Alpha &amp; Keluar Jam Kerja</h4><ol>
              <li>Tidak hadir tanpa pemberitahuan 1 x 24 jam = Alpha (sanksi Rp100.000).</li>
              <li>Keluar area perusahaan pada jam kerja wajib Surat Izin Keluar bertanda tangan Atasan Langsung dan HRD, serta finger out / finger in.</li>
              <li>Jam kerja efektif kurang dari 4,5 jam dalam sehari dihitung sebagai Izin.</li>
            </ol>
            HTML];
    }

    private static function head(string $no, string $dept, string $div, string $pos): string
    {
        return '<table class="dl-dash__meta"><tr><td>Nomor</td><td>: '.e($no).'</td></tr><tr><td>Departemen</td><td>: '.e($dept).'</td></tr>'
            .'<tr><td>Divisi</td><td>: '.e($div).'</td></tr><tr><td>Posisi</td><td>: '.e($pos).'</td></tr></table>';
    }
}
