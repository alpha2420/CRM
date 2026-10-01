@php($change = $previous > 0 ? round(($current - $previous) / $previous * 100) : null)
@if ($change !== null)
    <span @class(['delta', 'up' => $change > 0, 'down' => $change < 0])>{{ $change > 0 ? '▲' : ($change < 0 ? '▼' : '') }} {{ abs($change) }}%</span>
    <span class="faint small">vs previous period</span>
@else
    <span class="muted small">No earlier data</span>
@endif
