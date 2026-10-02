<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quick lead search for the command palette (Ctrl/⌘ K).
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['leads' => []]);
        }

        $leads = Lead::query()
            ->visibleTo($request->user())
            ->filter(['q' => mb_substr($query, 0, 100)])
            ->with('status')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Lead $lead) => [
                'name' => $lead->name,
                'detail' => collect([$lead->phone, $lead->company, $lead->status?->name])->filter()->implode(' · '),
                'url' => route('leads.show', $lead),
            ]);

        return response()->json(['leads' => $leads]);
    }
}
