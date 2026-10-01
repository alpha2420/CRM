<?php

namespace App\Services;

use App\Enums\Priority;
use App\Models\CustomField;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Bulk-creates leads from a CSV whose first row is a header. Bad rows and
 * duplicate phone numbers are skipped and reported; good rows are created
 * through LeadService, so assignment works exactly as for manual entry.
 */
final class LeadImporter
{
    public const COLUMNS = ['name', 'phone', 'email', 'company', 'city', 'source', 'status', 'value', 'priority', 'notes'];

    private const MAX_REPORTED_ERRORS = 50;

    public function __construct(
        private readonly LeadService $leads,
        private readonly AuditLogger $audit,
    ) {}

    public function import(UploadedFile $file, User $actor): ImportResult
    {
        $handle = fopen($file->getRealPath(), 'r');

        try {
            $header = $this->readHeader($handle);
            $organization = $actor->organization;
            $sources = $this->idsByLowercaseName($organization->sources()->pluck('id', 'name'));
            $statuses = $this->idsByLowercaseName($organization->leadStatuses()->pluck('id', 'name'));
            $existingPhones = $organization->leads()->pluck('phone')->flip();
            $customFields = CustomField::query()->get();

            $created = 0;
            $errors = [];
            $line = 1;
            $maxRows = config('crm.import_max_rows');

            // Closures share the counters by reference (an arrow function would copy them).
            $this->audit->quietly(function () use ($handle, $header, $organization, $actor, $sources, $statuses, $existingPhones, $customFields, $maxRows, &$created, &$errors, &$line) {
                DB::transaction(function () use ($handle, $header, $organization, $actor, $sources, $statuses, $existingPhones, $customFields, $maxRows, &$created, &$errors, &$line) {
                    while (($row = fgetcsv($handle, escape: '')) !== false) {
                        $line++;

                        if ($row === [null]) {
                            continue; // blank line
                        }

                        if ($line - 1 > $maxRows) {
                            $errors[] = "Stopped at row {$line}: a file may contain at most {$maxRows} leads.";
                            break;
                        }

                        $record = $this->combine($header, $row);
                        $data = $this->toLeadAttributes($record, $sources, $statuses);
                        $custom = $this->customValues($record, $customFields);
                        $validator = Validator::make($data + ['custom' => $custom], [
                            'name' => ['required', 'string', 'max:150'],
                            'phone' => ['required', PhoneNumber::RULE],
                            'email' => ['nullable', 'email', 'max:150'],
                            'company' => ['nullable', 'string', 'max:150'],
                            'city' => ['nullable', 'string', 'max:100'],
                            'value' => ['nullable', 'numeric', 'min:0'],
                            'notes' => ['nullable', 'string', 'max:5000'],
                            ...$customFields->mapWithKeys(fn (CustomField $field) => ["custom.{$field->key}" => $field->rules()])->all(),
                        ], [], $customFields->mapWithKeys(fn (CustomField $field) => ["custom.{$field->key}" => $field->label])->all());

                        if ($validator->fails()) {
                            $errors[] = "Row {$line}: ".$validator->errors()->first();

                            continue;
                        }

                        if ($existingPhones->has($data['phone'])) {
                            $errors[] = "Row {$line}: phone {$data['phone']} already exists.";

                            continue;
                        }

                        $data['custom_values'] = array_filter($custom, fn ($value) => $value !== null && $value !== '') ?: null;
                        $this->leads->create($organization, $data, $actor);
                        $existingPhones->put($data['phone'], true);
                        $created++;
                    }
                });
            });

            $this->audit->log('lead.imported', "Imported {$created} leads from {$file->getClientOriginalName()}", actor: $actor);
        } finally {
            fclose($handle);
        }

        return new ImportResult(
            created: $created,
            skipped: count($errors),
            errors: array_slice($errors, 0, self::MAX_REPORTED_ERRORS),
        );
    }

    /**
     * @param  resource  $handle
     * @return list<string>
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle, escape: '');

        if ($header === false || $header === [null]) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        // Drop a UTF-8 byte-order mark (Excel adds one) and normalise names.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);

        if (! in_array('name', $header, true) || ! in_array('phone', $header, true)) {
            throw ValidationException::withMessages(['file' => 'The file needs a header row with at least "name" and "phone" columns.']);
        }

        return $header;
    }

    /**
     * @param  list<string>  $header
     * @param  list<?string>  $row
     * @return array<string, ?string>
     */
    private function combine(array $header, array $row): array
    {
        $row = array_pad(array_slice($row, 0, count($header)), count($header), null);

        return array_combine($header, array_map(fn ($cell) => $cell === null ? null : trim($cell), $row));
    }

    /**
     * @param  array<string, ?string>  $record
     * @return array<string, mixed>
     */
    private function toLeadAttributes(array $record, Collection $sources, Collection $statuses): array
    {
        $blankToNull = fn (?string $value) => $value === '' ? null : $value;

        return [
            'name' => $blankToNull($record['name'] ?? null),
            'phone' => isset($record['phone']) && $record['phone'] !== '' ? PhoneNumber::normalize($record['phone']) : null,
            'email' => $blankToNull($record['email'] ?? null),
            'company' => $blankToNull($record['company'] ?? null),
            'city' => $blankToNull($record['city'] ?? null),
            'source_id' => $sources->get(mb_strtolower($record['source'] ?? '')),
            'status_id' => $statuses->get(mb_strtolower($record['status'] ?? '')),
            'value' => $blankToNull($record['value'] ?? null),
            'priority' => (Priority::tryFrom(strtolower($record['priority'] ?? '')) ?? Priority::Medium)->value,
            'notes' => $blankToNull($record['notes'] ?? null),
        ];
    }

    /**
     * Custom field columns may be headed by the field's label or its key.
     *
     * @param  array<string, ?string>  $record
     * @return array<string, ?string>
     */
    private function customValues(array $record, Collection $customFields): array
    {
        return $customFields->mapWithKeys(function (CustomField $field) use ($record) {
            $value = $record[mb_strtolower($field->label)] ?? $record[$field->key] ?? null;

            return [$field->key => $value === '' ? null : $value];
        })->all();
    }

    private function idsByLowercaseName(Collection $idsByName): Collection
    {
        return $idsByName->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
    }
}
