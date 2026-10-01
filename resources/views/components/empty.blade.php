@props(['icon' => 'leads', 'title', 'text' => null])
<div {{ $attributes->class(['empty-state']) }}>
    <div class="empty-icon"><x-icon :name="$icon"/></div>
    <h3>{{ $title }}</h3>
    @if ($text)<p>{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="actions">{{ $slot }}</div>@endif
</div>
