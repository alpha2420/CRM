<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\LeadExporter;
use App\Services\LeadImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV import and export of leads (admins only).
 */
class LeadTransferController extends Controller
{
    public function create(): View
    {
        return view('leads.import');
    }

    public function store(Request $request, LeadImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $result = $importer->import($request->file('file'), $request->user());

        return redirect()->route('leads.import')
            ->with('status', "Imported {$result->created} leads, skipped {$result->skipped}.")
            ->with('import_errors', $result->errors);
    }

    public function export(Request $request, LeadExporter $exporter): StreamedResponse
    {
        app(AuditLogger::class)->log('lead.exported', 'Exported all leads to CSV');

        return $exporter->download($request->user());
    }

    public function template(LeadExporter $exporter): StreamedResponse
    {
        return $exporter->template();
    }
}
