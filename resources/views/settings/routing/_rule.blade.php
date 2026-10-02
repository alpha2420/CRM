{{-- One routing rule as a sentence: "If source is Facebook and city is Pune → Asha, Ravi". --}}
@php
    $conditions = [];
    if ($id = $rule->conditions['source_id'] ?? null) $conditions[] = 'source is <b>'.e($sources->firstWhere('id', $id)?->name ?? 'a removed source').'</b>';
    if (filled($rule->conditions['city'] ?? null)) $conditions[] = 'city is <b>'.e($rule->conditions['city']).'</b>';
    if ($key = $rule->conditions['field_key'] ?? null) $conditions[] = '<b>'.e($fields->firstWhere('key', $key)?->label ?? $key).'</b> is <b>'.e($rule->conditions['field_value'] ?? '').'</b>';
    $names = $users->whereIn('id', $rule->agentIds())->pluck('name');
@endphp
If {!! implode(' and ', $conditions) !!} → {{ $names->isEmpty() ? 'nobody active (falls back to everyone)' : $names->join(', ', ' and ') }}{{ $names->count() > 1 ? ', taking turns' : '' }}
