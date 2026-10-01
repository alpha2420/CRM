@props(['name', 'title', 'settings', 'unavailable' => null])
{{-- One Autopilot item: a switch, a title and a sentence that may hold inputs. --}}
<div @class(['auto-row', 'off' => $unavailable])>
    <label class="switch">
        <input type="checkbox" name="{{ $name }}" value="1" aria-describedby="{{ $name }}-text"
               @checked(! $unavailable && ($errors->any() ? old($name) : $settings->on($name))) @disabled($unavailable)>
        <span class="sr-only">{{ $title }}</span>
    </label>
    <div class="auto-body">
        <strong>{{ $title }}</strong>
        <div class="auto-text" id="{{ $name }}-text">{{ $slot }}</div>
        @if ($unavailable)<div class="hint">{{ $unavailable }}</div>@endif
    </div>
</div>
