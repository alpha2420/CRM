<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadCaptureRequest;
use App\Models\Organization;
use App\Services\LeadIntake;
use Illuminate\Http\JsonResponse;

/**
 * Public endpoint for a customer's website or landing-page form.
 * 201 = new lead; 200 = phone already known, enquiry added to its history.
 */
class LeadCaptureController extends Controller
{
    public function __invoke(LeadCaptureRequest $request, LeadIntake $intake): JsonResponse
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');
        $source = $this->source($organization, $request->validated('source'));

        $result = $intake->capture($organization, $request->safe()->except('source'), $source['name'], $source['id']);

        return $result->created
            ? response()->json(['message' => 'Lead created.', 'id' => $result->lead->id], 201)
            : response()->json(['message' => 'Lead already exists; the enquiry was added to its history.', 'id' => $result->lead->id], 200);
    }

    /**
     * Match the given source by name; fall back to "Website". Unknown names
     * are not created, so outside callers cannot clutter the settings.
     *
     * @return array{id: ?int, name: string}
     */
    private function source(Organization $organization, ?string $name): array
    {
        $sources = $organization->sources()->get(['id', 'name']);
        $find = fn (string $wanted) => $sources->first(fn ($source) => strcasecmp($source->name, $wanted) === 0);
        $source = ($name !== null ? $find($name) : null) ?? $find('Website');

        return ['id' => $source?->id, 'name' => $source?->name ?? 'Website'];
    }
}
