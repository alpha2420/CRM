@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div><h2>Custom fields</h2><p>Capture what matters to your business — budget, course, property type. Fields appear on every lead and in imports and exports.</p></div>
    </div>
    <section class="card flush">
        @if ($fields->isNotEmpty())
            <div class="list-row field-row head"><span>Label</span><span>Type</span><span>Choices (dropdown)</span><span>Required</span><span>Order</span><span></span><span></span></div>
        @endif
        @foreach ($fields as $field)
            <div class="list-row field-row">
                <form method="post" action="{{ route('settings.custom-fields.update', $field) }}" class="contents">
                    @csrf @method('put')
                    <input name="label" value="{{ $field->label }}" required maxlength="60" aria-label="Label">
                    <select name="type" aria-label="Type">
                        @foreach (\App\Enums\CustomFieldType::cases() as $type)
                            <option value="{{ $type->value }}" @selected($field->type === $type)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <input name="options" value="{{ implode(', ', $field->options ?? []) }}" placeholder="e.g. 1BHK, 2BHK, 3BHK" aria-label="Choices">
                    <label class="check"><input type="checkbox" name="is_required" value="1" @checked($field->is_required)> Yes</label>
                    <input type="number" name="sort_order" value="{{ $field->sort_order }}" min="0" max="999" required aria-label="Order">
                    <button type="submit" class="btn small">Save</button>
                </form>
                <form method="post" action="{{ route('settings.custom-fields.destroy', $field) }}" data-confirm="Remove this field? Values saved on leads are hidden.">
                    @csrf @method('delete')
                    <button type="submit" class="icon-btn" aria-label="Delete {{ $field->label }}"><x-icon name="trash"/></button>
                </form>
            </div>
        @endforeach
        <form method="post" action="{{ route('settings.custom-fields.store') }}" class="list-row field-row add">
            @csrf
            <input name="label" placeholder="New field, e.g. Budget" required maxlength="60" value="{{ old('label') }}" aria-label="New field label">
            <select name="type" aria-label="Type">
                @foreach (\App\Enums\CustomFieldType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <input name="options" placeholder="Choices, comma separated" value="{{ old('options') }}" aria-label="Choices">
            <label class="check"><input type="checkbox" name="is_required" value="1"> Yes</label>
            <input type="number" name="sort_order" value="{{ ($fields->max('sort_order') ?? 0) + 1 }}" min="0" max="999" required aria-label="Order">
            <button type="submit" class="btn small primary">Add</button>
        </form>
    </section>
@endsection
