<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public const PRESETS = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'month' => 'This month'];

    public function __invoke(Request $request, ReportService $reports): View
    {
        $input = $request->validate([
            'range' => ['nullable', 'in:'.implode(',', array_keys(self::PRESETS)).',custom'],
            'from' => ['nullable', 'date', 'required_if:range,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:range,custom'],
        ]);

        $range = $input['range'] ?? '30';
        $now = LocalTime::now();
        [$from, $to] = match ($range) {
            'custom' => [CarbonImmutable::parse($input['from'], $now->timezone), CarbonImmutable::parse($input['to'], $now->timezone)],
            'month' => [$now->startOfMonth(), $now],
            default => [$now->subDays((int) $range - 1), $now],
        };

        // Keep a sane upper bound on what one page computes.
        if ($from->diffInDays($to) > 366) {
            $from = $to->subDays(366);
        }

        return view('reports.index', [
            'report' => $reports->build($request->user(), $from, $to),
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'presets' => self::PRESETS,
        ]);
    }
}
