{{-- Why this lead has its score: each reason with its points. --}}
<section class="card score-card">
    <div class="card-head" style="margin-bottom:10px">
        <h2>Lead score</h2>
        <span class="score-total"><strong>{{ $score->total }}</strong><span class="temp {{ $score->band() }}">{{ ucfirst($score->band()) }}</span></span>
    </div>
    <div class="meter score-meter {{ $score->band() }}" role="img" aria-label="{{ $score->total }} out of 100"><span style="width: {{ $score->total }}%"></span></div>
    @if ($score->factors)
        <ul class="factors">
            @foreach ($score->factors as $factor)
                <li><span @class(['pts', 'up' => $factor->points > 0, 'down' => $factor->points < 0])>{{ $factor->points > 0 ? '+' : '' }}{{ $factor->points }}</span>{{ $factor->reason }}</li>
            @endforeach
        </ul>
    @endif
    <p class="hint" style="margin:10px 0 0">Every lead starts at {{ \App\Scoring\LeadScorer::BASE }}. The score updates as the lead replies, moves stage or goes quiet.</p>
</section>
