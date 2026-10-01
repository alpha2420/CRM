<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

#[Fillable(['label', 'key', 'type', 'options', 'is_required', 'sort_order'])]
class CustomField extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Validation rules for this field's value on a lead.
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        $rules = [$this->is_required ? 'required' : 'nullable'];

        return [...$rules, ...match ($this->type) {
            CustomFieldType::Text => ['string', 'max:500'],
            CustomFieldType::Number => ['numeric'],
            CustomFieldType::Date => ['date'],
            CustomFieldType::Select => [Rule::in($this->options ?? [])],
        }];
    }

    public function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $this->type === CustomFieldType::Date ? date('d M Y', strtotime((string) $value)) : (string) $value;
    }
}
