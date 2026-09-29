<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DielineSvgGenerator (PHP 8.4)
 * Generates ISO technical dieline cutting (Red), creasing (Green), bleed (Dashed Slate),
 * and dimension arrows for 1-piece folding cartons and 2-piece shoe boxes (Base Tray & Lid).
 */
final class DielineSvgGenerator
{
    /**
     * Generate full standalone SVG string.
     */
    public function generate(
        string $partName,
        string $partType,
        float $length,
        float $width,
        float $height,
        float $glueFlap = 15.0,
        float $tuckFlap = 15.0,
        string $unit = 'mm'
    ): string {
        $multiplier = ($unit === 'cm') ? 10.0 : 1.0;
        $l = $length * $multiplier;
        $w = $width * $multiplier;
        $h = $height * $multiplier;
        $gf = $glueFlap * $multiplier;
        $tf = $tuckFlap * $multiplier;
        $margin = 50.0;

        $isLid = str_contains(strtolower($partName . ' ' . $partType), 'lid') || 
                 str_contains(strtolower($partName . ' ' . $partType), 'tutup');
        $isTray = str_contains(strtolower($partName . ' ' . $partType), 'tray') || 
                  str_contains(strtolower($partName . ' ' . $partType), 'badan') || 
                  str_contains(strtolower($partName . ' ' . $partType), 'base');

        if ($isLid || $isTray) {
            return $this->generateTwoPieceShoeBoxSvg($partName, $isLid, $l, $w, $h, $margin, $unit);
        }

        return $this->generateStandardFoldingBoxSvg($partName, $l, $w, $h, $gf, $tf, $margin, $unit);
    }

    private function generateTwoPieceShoeBoxSvg(
        string $partName,
        bool $isLid,
        float $l,
        float $w,
        float $h,
        float $margin,
        string $unit
    ): string {
        $wall = $h;
        $totalW = $w + (2 * $wall);
        $totalH = $l + (2 * $wall);
        $rollFold = min(15.0, $wall * 0.7);

        $viewW = $totalW + (2 * $margin);
        $viewH = $totalH + (2 * $margin);

        $x1 = $margin;
        $x2 = $margin + $wall;
        $x3 = $margin + $wall + $w;
        $x4 = $x3 + $wall;

        $y1 = $margin;
        $y2 = $margin + $wall;
        $y3 = $margin + $wall + $l;
        $y4 = $y3 + $wall;

        $cutColor = '#ef4444';
        $creaseColor = '#10b981';
        $dimColor = '#6366f1';

        $title = htmlspecialchars($partName, ENT_QUOTES, 'UTF-8');
        $dimLabel = sprintf("%.1f x %.1f x %.1f %s", $l, $w, $h, $unit);

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$viewW} {$viewH}" width="100%" height="100%">
    <defs>
        <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
            <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#f1f5f9" stroke-width="1"/>
        </pattern>
        <marker id="arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M 0 0 L 10 5 L 0 10 z" fill="{$dimColor}"/>
        </marker>
    </defs>

    <!-- Background Grid -->
    <rect width="100%" height="100%" fill="#ffffff"/>
    <rect width="100%" height="100%" fill="url(#grid)"/>

    <!-- Title and Metadata -->
    <g font-family="sans-serif">
        <text x="{$margin}" y="30" font-size="14" font-weight="bold" fill="#0f172a">{$title} (2-Piece Shoe Box Part)</text>
        <text x="{$margin}" y="45" font-size="11" fill="#64748b">Dimensi Terpasang: {$dimLabel} | ECMA D20.20 / FEFCO 0422</text>
    </g>

    <!-- Creasing Score Lines (Green) -->
    <g stroke="{$creaseColor}" stroke-width="1.5" stroke-dasharray="4,3">
        <!-- Vertical fold creases -->
        <line x1="{$x2}" y1="{$y2}" x2="{$x2}" y2="{$y3}" />
        <line x1="{$x3}" y1="{$y2}" x2="{$x3}" y2="{$y3}" />
        <!-- Horizontal fold creases -->
        <line x1="{$x2}" y1="{$y2}" x2="{$x3}" y2="{$y2}" />
        <line x1="{$x2}" y1="{$y3}" x2="{$x3}" y2="{$y3}" />
    </g>

    <!-- Outer Perimeter Cutting Knife Lines (Red) -->
    <path d="
        M {$x2} {$y1}
        L {$x3} {$y1}
        L {$x3} {$y2}
        L {$x4} {$y2}
        L {$x4} {$y3}
        L {$x3} {$y3}
        L {$x3} {$y4}
        L {$x2} {$y4}
        L {$x2} {$y3}
        L {$x1} {$y3}
        L {$x1} {$y2}
        L {$x2} {$y2}
        Z
    " fill="none" stroke="{$cutColor}" stroke-width="2" stroke-linejoin="round"/>

    <!-- Inner Base Floor Tint -->
    <rect x="{$x2}" y="{$y2}" width="{$w}" height="{$l}" fill="#3b82f6" fill-opacity="0.06"/>
    <text x="{$x2 + ($w/2)}" y="{$y2 + ($l/2)}" font-family="sans-serif" font-size="12" font-weight="bold" fill="#3b82f6" text-anchor="middle" dominant-baseline="middle">
        DASAR / FLOOR ({$w} x {$l} {$unit})
    </text>

    <!-- Side Wall Labels -->
    <text x="{$x2 + ($w/2)}" y="{$y1 + ($wall/2)}" font-family="sans-serif" font-size="10" fill="#64748b" text-anchor="middle">Dinding Atas (H: {$wall})</text>
    <text x="{$x2 + ($w/2)}" y="{$y3 + ($wall/2)}" font-family="sans-serif" font-size="10" fill="#64748b" text-anchor="middle">Dinding Bawah (H: {$wall})</text>
    <text x="{$x1 + ($wall/2)}" y="{$y2 + ($l/2)}" font-family="sans-serif" font-size="10" fill="#64748b" text-anchor="middle" transform="rotate(-90 {$x1 + ($wall/2)} {$y2 + ($l/2)})">Kiri (H: {$wall})</text>
    <text x="{$x3 + ($wall/2)}" y="{$y2 + ($l/2)}" font-family="sans-serif" font-size="10" fill="#64748b" text-anchor="middle" transform="rotate(90 {$x3 + ($wall/2)} {$y2 + ($l/2)})">Kanan (H: {$wall})</text>

    <!-- Dimension Annotations -->
    <g stroke="{$dimColor}" stroke-width="1.2">
        <!-- Width Arrow -->
        <line x1="{$x2}" y1="{$y4 + 18}" x2="{$x3}" y2="{$y4 + 18}" marker-start="url(#arrow)" marker-end="url(#arrow)" />
        <!-- Length Arrow -->
        <line x1="{$x4 + 18}" y1="{$y2}" x2="{$x4 + 18}" y2="{$y3}" marker-start="url(#arrow)" marker-end="url(#arrow)" />
    </g>
    <text x="{$x2 + ($w/2)}" y="{$y4 + 32}" font-family="sans-serif" font-size="11" font-weight="bold" fill="{$dimColor}" text-anchor="middle">Lebar (W) = {$w} {$unit}</text>
    <text x="{$x4 + 35}" y="{$y2 + ($l/2)}" font-family="sans-serif" font-size="11" font-weight="bold" fill="{$dimColor}" text-anchor="middle" transform="rotate(90 {$x4 + 35} {$y2 + ($l/2)})">Panjang (L) = {$l} {$unit}</text>
</svg>
SVG;
    }

    private function generateStandardFoldingBoxSvg(
        string $partName,
        float $l,
        float $w,
        float $h,
        float $gf,
        float $tf,
        float $margin,
        string $unit
    ): string {
        $totalW = $gf + (2 * $w) + (2 * $l);
        $totalH = $h + (2 * $tf) + (2 * $w);

        $viewW = $totalW + (2 * $margin);
        $viewH = $totalH + (2 * $margin);

        $x0 = $margin;
        $x1 = $x0 + $gf;
        $x2 = $x1 + $w;
        $x3 = $x2 + $l;
        $x4 = $x3 + $w;
        $x5 = $x4 + $l;

        $yTop = $margin;
        $yFlapTop = $yTop + $tf;
        $yScoreTop = $yFlapTop + $w;
        $yScoreBottom = $yScoreTop + $h;
        $yFlapBottom = $yScoreBottom + $w;
        $yBottom = $yFlapBottom + $tf;

        $cutColor = '#ef4444';
        $creaseColor = '#10b981';
        $title = htmlspecialchars($partName, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$viewW} {$viewH}" width="100%" height="100%">
    <rect width="100%" height="100%" fill="#ffffff"/>
    <text x="{$margin}" y="28" font-family="sans-serif" font-size="14" font-weight="bold" fill="#0f172a">{$title} (Folding Box STE)</text>

    <!-- Main Vertical Creases -->
    <g stroke="{$creaseColor}" stroke-width="1.5" stroke-dasharray="4,3">
        <line x1="{$x1}" y1="{$yScoreTop}" x2="{$x1}" y2="{$yScoreBottom}" />
        <line x1="{$x2}" y1="{$yScoreTop}" x2="{$x2}" y2="{$yScoreBottom}" />
        <line x1="{$x3}" y1="{$yScoreTop}" x2="{$x3}" y2="{$yScoreBottom}" />
        <line x1="{$x4}" y1="{$yScoreTop}" x2="{$x4}" y2="{$yScoreBottom}" />
        <!-- Horizontal Creases -->
        <line x1="{$x1}" y1="{$yScoreTop}" x2="{$x5}" y2="{$yScoreTop}" />
        <line x1="{$x1}" y1="{$yScoreBottom}" x2="{$x5}" y2="{$yScoreBottom}" />
    </g>

    <!-- Main Outer Cut Line -->
    <rect x="{$x1}" y="{$yScoreTop}" width="{$totalW - $gf}" height="{$h}" fill="none" stroke="{$cutColor}" stroke-width="2"/>
    <!-- Glue Flap -->
    <polygon points="{$x0},{$yScoreTop + 5} {$x1},{$yScoreTop} {$x1},{$yScoreBottom} {$x0},{$yScoreBottom - 5}" fill="#f8fafc" stroke="{$cutColor}" stroke-width="1.5"/>
</svg>
SVG;
    }
}
