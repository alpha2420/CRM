{{-- The lead's follow-up sequence: what it's in now, or start one. --}}
<section class="card">
    <div class="card-head" style="margin-bottom:10px">
        <h2>Sequence</h2>
        @if ($enrollment)<span class="pill info">Running</span>@endif
    </div>
    @if ($enrollment)
        @php($steps = $enrollment->sequence->steps)
        @php($next = $steps->values()[$enrollment->next_step] ?? null)
        <strong>{{ $enrollment->sequence->name }}</strong>
        <div class="meter mt" role="img" aria-label="Step {{ $enrollment->next_step }} of {{ $steps->count() }} done"><span style="width: {{ $steps->count() ? $enrollment->next_step / $steps->count() * 100 : 0 }}%"></span></div>
        <p class="muted small" style="margin:8px 0 0">{{ $enrollment->next_step }} of {{ $steps->count() }} steps done.@if ($next) Next, {{ $enrollment->next_run_at?->local()->format('D j M') }}: {{ Str::lcfirst($next->summary()) }}.@endif</p>
        @can('update', $lead)
            <form method="post" action="{{ route('leads.sequence.stop', $lead) }}" class="mt">
                @csrf @method('delete')
                <button type="submit" class="btn small">Stop sequence</button>
            </form>
        @endcan
    @elseif ($sequences->isNotEmpty())
        @can('update', $lead)
            <form method="post" action="{{ route('leads.sequence.start', $lead) }}" class="stack" style="gap:8px">
                @csrf
                <label class="sr-only" for="sequence-id">Sequence</label>
                <select name="sequence_id" id="sequence-id" required>
                    @foreach ($sequences as $sequence)<option value="{{ $sequence->id }}">{{ $sequence->name }}</option>@endforeach
                </select>
                <button type="submit" class="btn block"><x-icon name="send"/>Start sequence</button>
            </form>
        @endcan
    @else
        <p class="muted" style="margin:0">No sequences yet. @can('admin')<a href="{{ route('settings.sequences.create') }}">Create one</a> to follow up automatically over several days.@endcan</p>
    @endif
</section>
