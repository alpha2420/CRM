<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Automation;
use App\Models\CustomField;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\Organization;
use App\Models\Source;
use App\Models\WhatsAppMessage;
use App\Support\CsvCell;
use App\Support\LocalTime;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Everything a workspace has stored, as a ZIP of CSV files (data
 * portability). Large tables are streamed to temporary files in chunks.
 */
final class WorkspaceExporter
{
    /**
     * @return string path of the ZIP file (the caller deletes it after sending)
     */
    public function build(Organization $organization): string
    {
        $dir = storage_path('app/private/exports/'.uniqid('ws'.$organization->id.'-', true));
        File::ensureDirectoryExists($dir);
        $zipPath = $dir.'.zip';
        $customFields = CustomField::query()->ordered()->get();

        $files = [
            'leads.csv' => $this->csv($dir, 'leads.csv',
                ['id', 'name', 'phone', 'email', 'company', 'city', 'source', 'status', 'owner', 'value', 'priority', 'notes', 'next_follow_up_at', 'created_at', ...$customFields->pluck('label')],
                Lead::query()->with(['source', 'status', 'assignee'])->lazyById(500),
                fn (Lead $l) => [$l->id, $l->name, $l->phone, $l->email, $l->company, $l->city, $l->source?->name, $l->status?->name, $l->assignee?->email, $l->value, $l->priority->value, $l->notes, $this->time($l->next_follow_up_at), $this->time($l->created_at), ...$customFields->map(fn ($f) => $l->custom_values[$f->key] ?? '')]),
            'follow_ups.csv' => $this->csv($dir, 'follow_ups.csv',
                ['lead_id', 'status', 'note', 'next_follow_up_at', 'by', 'created_at'],
                LeadActivity::query()->with(['status', 'user'])->lazyById(500),
                fn (LeadActivity $a) => [$a->lead_id, $a->status?->name, $a->note, $this->time($a->next_follow_up_at), $a->user->email ?? 'automation', $this->time($a->created_at)]),
            'whatsapp_messages.csv' => $this->csv($dir, 'whatsapp_messages.csv',
                ['lead_id', 'direction', 'type', 'template', 'body', 'status', 'created_at'],
                WhatsAppMessage::query()->lazyById(500),
                fn (WhatsAppMessage $m) => [$m->lead_id, $m->direction, $m->type, $m->template_name, $m->body, $m->status, $this->time($m->created_at)]),
            'team.csv' => $this->csv($dir, 'team.csv',
                ['name', 'email', 'role', 'active', 'two_factor', 'joined'],
                $organization->users()->orderBy('name')->get(),
                fn ($u) => [$u->name, $u->email, $u->role->value, $u->is_active ? 'yes' : 'no', $u->hasTwoFactor() ? 'yes' : 'no', $this->time($u->created_at)]),
            'pipeline.csv' => $this->csv($dir, 'pipeline.csv', ['name', 'type', 'colour', 'order'], LeadStatus::query()->ordered()->get(),
                fn ($s) => [$s->name, $s->type->value, $s->color, $s->sort_order]),
            'sources.csv' => $this->csv($dir, 'sources.csv', ['name'], Source::query()->orderBy('name')->get(), fn ($s) => [$s->name]),
            'custom_fields.csv' => $this->csv($dir, 'custom_fields.csv', ['label', 'key', 'type', 'choices', 'required'], $customFields,
                fn ($f) => [$f->label, $f->key, $f->type->value, implode(', ', $f->options ?? []), $f->is_required ? 'yes' : 'no']),
            'automations.csv' => $this->csv($dir, 'automations.csv', ['name', 'trigger', 'conditions', 'actions', 'active', 'runs'], Automation::query()->get(),
                fn ($a) => [$a->name, $a->trigger->value, json_encode($a->conditions), json_encode($a->actions), $a->is_active ? 'yes' : 'no', $a->runs]),
            'activity_log.csv' => $this->csv($dir, 'activity_log.csv', ['when', 'who', 'action', 'description', 'ip'],
                AuditLog::query()->with('user')->lazyById(500),
                fn (AuditLog $e) => [$this->time($e->created_at), $e->user->email ?? 'automation', $e->action, $e->description, $e->ip_address]),
        ];

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export file.');
        }
        $zip->addFromString('README.txt', "Data export for {$organization->name}\nCreated ".LocalTime::now()->format('d M Y H:i T')."\nTimes are in {$organization->timezone}. Files are UTF-8 CSV and open in Excel or Google Sheets.\n");
        foreach ($files as $name => $path) {
            $zip->addFile($path, $name);
        }
        $zip->close();
        File::deleteDirectory($dir);

        return $zipPath;
    }

    /**
     * @param  iterable<mixed>  $rows
     */
    private function csv(string $dir, string $name, array $header, iterable $rows, callable $map): string
    {
        $path = "{$dir}/{$name}";
        $out = fopen($path, 'w');
        fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 (Hindi, ₹) correctly
        fputcsv($out, $header, escape: '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(CsvCell::safe(...), $map($row)), escape: '');
        }
        fclose($out);

        return $path;
    }

    private function time(?\DateTimeInterface $at): string
    {
        return $at ? LocalTime::of($at)->format('Y-m-d H:i') : '';
    }
}
