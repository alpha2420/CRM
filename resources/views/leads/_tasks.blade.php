{{-- A lead's to-dos, with a quick add. --}}
<section class="card lead-tasks">
    <div class="card-head"><h2>To-dos</h2>@if ($tasks->isNotEmpty())<span class="pill">{{ $tasks->count() }} open</span>@endif</div>
    @if ($tasks->isNotEmpty())
        <ul class="agenda compact">
            @foreach ($tasks as $task)
                @include('tasks._item', ['item' => \App\Tasks\AgendaItem::task($task), 'onLead' => true])
            @endforeach
        </ul>
    @else
        <p class="muted small" style="margin:0 0 12px">Anything to remember for this lead: send a quote, check a document, call their CA.</p>
    @endif
    @can('update', $lead)
        @include('tasks._add', ['lead' => $lead, 'placeholder' => 'Add a to-do for '.$lead->firstName()])
    @endcan
</section>
