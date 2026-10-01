<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SourceRequest;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function index(): View
    {
        return view('settings.sources', [
            'sources' => Source::query()->withCount('leads')->orderBy('name')->get(),
        ]);
    }

    public function store(SourceRequest $request): RedirectResponse
    {
        Source::create($request->validated());

        return back()->with('status', 'Source added.');
    }

    public function update(SourceRequest $request, Source $source): RedirectResponse
    {
        $source->update($request->validated());

        return back()->with('status', 'Source updated.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return back()->with('status', 'Source deleted. Its leads now have no source.');
    }
}
