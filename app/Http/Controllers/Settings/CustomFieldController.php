<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFieldRequest;
use App\Models\CustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    public function index(): View
    {
        return view('settings.custom-fields', ['fields' => CustomField::query()->ordered()->get()]);
    }

    public function store(CustomFieldRequest $request): RedirectResponse
    {
        CustomField::create($request->validated());

        return back()->with('status', 'Field added. It now appears on every lead.');
    }

    public function update(CustomFieldRequest $request, CustomField $customField): RedirectResponse
    {
        $customField->update($request->safe()->except('key'));

        return back()->with('status', 'Field updated.');
    }

    public function destroy(CustomField $customField): RedirectResponse
    {
        $customField->delete();

        return back()->with('status', 'Field removed.');
    }
}
