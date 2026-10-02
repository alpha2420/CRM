{{-- Add a to-do, optionally for a lead. --}}
<form method="post" action="{{ route('tasks.store') }}" class="add-task" data-due-picker>
    @csrf
    @isset($lead)<input type="hidden" name="lead_id" value="{{ $lead->id }}">@endisset
    <input name="title" value="{{ old('title') }}" required maxlength="200" placeholder="{{ $placeholder ?? 'Add a to-do, e.g. Send the brochure' }}" aria-label="To-do">
    <select name="due" aria-label="When">
        <option value="today" @selected(old('due', 'today') === 'today')>Today</option>
        <option value="tomorrow" @selected(old('due') === 'tomorrow')>Tomorrow</option>
        <option value="next_week" @selected(old('due') === 'next_week')>Next Monday</option>
        <option value="" @selected(old('due') === '')>No date</option>
        <option value="custom" @selected(old('due') === 'custom')>Pick a time…</option>
    </select>
    <input type="datetime-local" name="due_at" value="{{ old('due_at') }}" aria-label="Date and time" class="due-at" @if (old('due') !== 'custom') hidden @endif>
    @if (($people ?? collect())->count() > 1)
        <select name="user_id" aria-label="For">
            @foreach ($people as $person)<option value="{{ $person->id }}" @selected((int) old('user_id', auth()->id()) === $person->id)>{{ $person->id === auth()->id() ? 'For me' : 'For '.$person->name }}</option>@endforeach
        </select>
    @endif
    <button type="submit" class="btn primary"><x-icon name="plus"/>Add</button>
</form>
@error('title')<p class="field-error">{{ $message }}</p>@enderror
@error('due_at')<p class="field-error">{{ $message }}</p>@enderror
