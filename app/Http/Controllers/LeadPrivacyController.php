<?php

namespace App\Http\Controllers;

use App\Consent\LeadDataExport;
use App\Consent\LeadEraser;
use App\Consent\OptOut;
use App\Models\Lead;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * A lead's privacy choices: stop or allow messages, and (admins) download
 * or erase their data when they ask.
 */
class LeadPrivacyController extends Controller
{
    public function consent(Request $request, Lead $lead, OptOut $optOut): RedirectResponse
    {
        Gate::authorize('update', $lead);
        $data = $request->validate(['messages' => ['required', 'in:stop,allow']]);
        $user = $request->user();

        if ($data['messages'] === 'stop') {
            $optOut->withdraw($lead, "marked by {$user->name}", $user);

            return back()->with('status', 'Messages stopped: nothing automatic will contact this lead.');
        }

        $optOut->restore($lead, "{$user->name} confirmed they asked for messages again", $user);

        return back()->with('status', 'Messages allowed again.');
    }

    public function export(Lead $lead, LeadDataExport $export, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('admin');
        $audit->log('lead.exported', "Downloaded the data of lead {$lead->name}", $lead);

        return response()->json($export->for($lead), 200, [
            'Content-Disposition' => 'attachment; filename="'.Str::slug($lead->name).'-data.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function erase(Request $request, Lead $lead, LeadEraser $eraser): RedirectResponse
    {
        Gate::authorize('admin');
        $eraser->erase($lead, "on request, by {$request->user()->name}", $request->user());

        return redirect()->route('leads.show', $lead)->with('status', 'Personal data erased. The lead stays in your reports without its details.');
    }
}
