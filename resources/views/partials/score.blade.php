{{-- A lead score badge: 0-100, coloured hot / warm / cold. --}}
@if ($score !== null)
    <span class="score {{ \App\Scoring\LeadScore::bandOf($score) }}" title="Lead score {{ $score }} of 100">{{ $score }}</span>
@endif
