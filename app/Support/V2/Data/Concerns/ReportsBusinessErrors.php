<?php

declare(strict_types=1);

namespace App\Support\V2\Data\Concerns;

use Illuminate\Validation\ValidationException;
use Throwable;

// Layar Finance memanggil service akuntansi yang menolak dengan abort(422) / DomainException berpesan ramah. Trait ini mengubahnya menjadi ValidationException
// (kunci = konstanta ERROR_KEY kelas pemakai, bawaan `finance`) supaya layar menampilkan pesannya sebagai galat biasa, bukan halaman error.
trait ReportsBusinessErrors
{
    protected static function run(callable $fn): mixed
    {
        $key = defined(static::class.'::ERROR_KEY') ? constant(static::class.'::ERROR_KEY') : 'finance';
        try {
            return $fn();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages([$key => $e->getMessage() ?: t('The process failed.')]);
        }
    }
}
