<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\WorkspaceEraser;
use App\Services\WorkspaceExporter;
use App\Support\LocalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Data portability and erasure for the whole workspace.
 */
class DataController extends Controller
{
    public function export(Request $request, WorkspaceExporter $exporter, AuditLogger $audit): BinaryFileResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $organization = $request->user()->organization;

        $path = $exporter->build($organization);
        $audit->log('data.exported', 'Downloaded a full data export');

        return response()
            ->download($path, Str::slug($organization->name).'-export-'.LocalTime::now()->format('Y-m-d').'.zip')
            ->deleteFileAfterSend();
    }

    public function destroy(Request $request, WorkspaceEraser $eraser): RedirectResponse
    {
        $organization = $request->user()->organization;

        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm_name' => ['required', 'in:'.$organization->name],
        ], ['confirm_name.in' => 'Type the workspace name exactly to confirm.']);

        if ($organization->razorpay_subscription_id && $organization->hasPaidAccess() && $organization->subscription_status !== 'cancelled') {
            return back()->withErrors(['confirm_name' => 'Cancel your subscription under Billing first.']);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $eraser->erase($organization);

        return redirect()->route('home')->with('status', 'Your workspace and all its data have been deleted.');
    }
}
