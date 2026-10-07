<?php

namespace Tests\Feature;

use App\Support\V2\Data\AttendanceUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// HRM Attendance Upload v2 (C6): langkah baca file + validasi kolom. Langkah simpan menulis presensi sungguhan, jadi hanya jalur tanpa tulis diuji.
class AttendanceUploadTest extends TestCase
{
    private const USER = 987654;

    protected function tearDown(): void
    {
        AttendanceUpload::discard(self::USER);
        parent::tearDown();
    }

    public function test_unknown_pins_are_listed_and_not_matched(): void
    {
        $csv = "device_pin,tanggal,jam_masuk,jam_keluar\nZZ-NOPE-1,2026-10-05,07:30,16:30\nZZ-NOPE-2,2026-10-05,07:45,\n";
        $n = AttendanceUpload::parse(self::USER, UploadedFile::fake()->createWithContent('att.csv', $csv));

        $this->assertSame(2, $n);
        $pending = AttendanceUpload::pending(self::USER);
        $this->assertSame('att.csv', $pending['file_name']);
        $this->assertSame(['ZZ-NOPE-1', 'ZZ-NOPE-2'], $pending['unmatched']);
        $this->assertFalse($pending['rows'][0]['matched']);
        $this->assertEmpty($pending['rows'][1]['jam_keluar']);
    }

    public function test_missing_required_column_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        AttendanceUpload::parse(self::USER, UploadedFile::fake()->createWithContent('bad.csv', "device_pin,tanggal\nA,2026-10-05\n"));
    }

    public function test_process_without_pending_file_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        AttendanceUpload::process(self::USER, 'test');
    }
}
