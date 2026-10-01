@props(['name' => '?', 'size' => null, 'square' => false])
<span {{ $attributes->class(['avatar', $size, 'square' => $square]) }} style="--a: {{ \App\Support\Avatar::color($name) }}" aria-hidden="true">{{ \App\Support\Avatar::initials($name) }}</span>
