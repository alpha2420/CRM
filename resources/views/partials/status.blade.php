@if ($status)
    <span class="badge" style="--c: {{ $status->color }}">{{ $status->name }}</span>
@else
    <span class="faint">—</span>
@endif
