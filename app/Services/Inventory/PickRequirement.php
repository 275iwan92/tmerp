<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;

// Syarat bahan yang disebut spesifikasi baris booking (arah serat, sertifikasi, food grade). Dibaca dari teks spesifikasi
// (rm_booking_d.spec_text), bukan ditebak: spesifikasi yang tidak menyebutnya = tanpa syarat. Bahan tanpa batch tidak bisa
// dibuktikan memenuhi syarat, jadi ditolak bila ada syarat.
final class PickRequirement
{
    public function __construct(public readonly ?string $grain = null, public readonly ?string $certification = null, public readonly bool $foodGrade = false) {}

    public static function fromSpec(?string $spec): self
    {
        $s = (string) $spec;
        $grain = match (true) {
            (bool) preg_match('/\b(long\s*grain|grain\s*long|serat\s*panjang)\b/i', $s) => 'LONG',
            (bool) preg_match('/\b(short\s*grain|grain\s*short|serat\s*pendek)\b/i', $s) => 'SHORT',
            default => null,
        };
        $cert = match (true) {
            (bool) preg_match('/\bFSC\b/i', $s) => 'FSC',
            (bool) preg_match('/\bPEFC\b/i', $s) => 'PEFC',
            default => null,
        };

        return new self($grain, $cert, (bool) preg_match('/food[\s-]*grade/i', $s));
    }

    public function isEmpty(): bool
    {
        return $this->grain === null && $this->certification === null && ! $this->foodGrade;
    }

    public function label(): ?string
    {
        $parts = array_filter([$this->grain ? $this->grain.' grain' : null, $this->certification, $this->foodGrade ? 'food grade' : null]);

        return $parts ? implode(' · ', $parts) : null;
    }

    // null = batch memenuhi syarat; selain itu alasan penolakan (English, untuk catatan alokasi).
    public function refusal(?InvBatch $batch): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }
        if ($batch === null) {
            return 'no batch record to prove '.$this->label();
        }
        if ($this->grain && $batch->grain_direction !== $this->grain) {
            return 'grain direction is '.($batch->grain_direction ?: 'unknown').', need '.$this->grain;
        }
        if ($this->certification && $batch->certification !== $this->certification) {
            return 'certification is '.$batch->certification.', need '.$this->certification;
        }
        if ($this->foodGrade && ! $batch->food_grade) {
            return 'batch is not food grade';
        }

        return null;
    }
}
