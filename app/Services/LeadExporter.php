<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\Lead;
use App\Models\User;
use App\Support\CsvCell;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LeadExporter
{
    private const HEADER = [
        'name', 'phone', 'email', 'company', 'city', 'source', 'status', 'assigned_to',
        'value', 'priority', 'notes', 'next_follow_up_at', 'created_at',
    ];

    /**
     * Streams the CSV in chunks, so memory stays flat however many leads exist.
     */
    public function download(User $user): StreamedResponse
    {
        $customFields = CustomField::query()->ordered()->get();

        return response()->streamDownload(function () use ($user, $customFields) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [...self::HEADER, ...$customFields->pluck('label')], escape: '');

            Lead::query()
                ->visibleTo($user)
                ->with(['source', 'status', 'assignee'])
                ->lazyById(500)
                ->each(fn (Lead $lead) => fputcsv($out, array_map(CsvCell::safe(...), [
                    $lead->name,
                    $lead->phone,
                    $lead->email,
                    $lead->company,
                    $lead->city,
                    $lead->source?->name,
                    $lead->status?->name,
                    $lead->assignee?->email,
                    $lead->value,
                    $lead->priority->value,
                    $lead->notes,
                    $lead->next_follow_up_at?->toDateTimeString(),
                    $lead->created_at->toDateTimeString(),
                    ...$customFields->map(fn (CustomField $field) => $lead->custom_values[$field->key] ?? ''),
                ]), escape: ''));

            fclose($out);
        }, 'leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, LeadImporter::COLUMNS, escape: '');
            fputcsv($out, ['Jane Doe', '+919876543210', 'jane@example.com', 'Acme Ltd', 'Delhi', 'Website', 'New', '25000', 'high', 'Asked for a callback'], escape: '');
            fclose($out);
        }, 'lead-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
