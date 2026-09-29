<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DielineSvgGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * InquiryController (PHP 8.4 / Laravel 13)
 * Handles Commercial Inquiry entry, PDF / Brief generation, and Dieline SVG downloads.
 */
final class InquiryController extends Controller
{
    public function __construct(
        private readonly DielineSvgGenerator $dielineGenerator
    ) {}

    /**
     * Display the Livewire Inquiry interactive screen.
     */
    public function create(): View
    {
        return view('inquiry.create');
    }

    /**
     * Export standalone SVG CAD dieline for CAD plotter, laser dieboard, or sample cutting table.
     */
    public function exportDielineSvg(Request $request): Response
    {
        $validated = $request->validate([
            'part_name' => 'required|string|max:100',
            'part_type' => 'required|string',
            'length' => 'required|numeric|min:10',
            'width' => 'required|numeric|min:10',
            'height' => 'required|numeric|min:5',
            'glue_flap' => 'nullable|numeric|min:5',
            'tuck_flap' => 'nullable|numeric|min:5',
            'unit' => 'nullable|string|in:mm,cm',
        ]);

        $svgMarkup = $this->dielineGenerator->generate(
            partName: $validated['part_name'],
            partType: $validated['part_type'],
            length: (float)$validated['length'],
            width: (float)$validated['width'],
            height: (float)$validated['height'],
            glueFlap: (float)($validated['glue_flap'] ?? 15.0),
            tuckFlap: (float)($validated['tuck_flap'] ?? 15.0),
            unit: $validated['unit'] ?? 'mm'
        );

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $validated['part_name']);
        $filename = "Dieline_{$safeName}_" . date('Ymd_His') . ".svg";

        return response($svgMarkup, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Content-Disposition' => "attachment; filename="{$filename}"",
        ]);
    }
}
