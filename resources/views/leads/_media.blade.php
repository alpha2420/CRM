{{-- A file the lead sent: played, shown or offered as a download. --}}
@php($url = $message->media_path ? route('messages.file', $message) : null)
@if (! $url)
    <div class="media-pending">{{ $type->icon() }} {{ $type->label() }}@unless ($message->error) · downloading…@endunless</div>
@elseif ($type === \App\Media\MediaType::Audio)
    <audio class="media-audio" controls preload="none" src="{{ $url }}"></audio>
    @if ($message->transcript)
        <div class="transcript">
            @if ($message->transcript_summary)<div class="transcript-summary"><x-icon name="sparkles"/>{{ $message->transcript_summary }}</div>@endif
            <div class="transcript-text"><span>What they said</span>{{ $message->transcript }}</div>
        </div>
    @endif
@elseif ($type === \App\Media\MediaType::Video)
    <video class="media-video" controls preload="metadata" src="{{ $url }}"></video>
@elseif ($type === \App\Media\MediaType::Document)
    <a href="{{ $url }}" class="media-file"><x-icon name="file"/><span class="grow">{{ $message->media_name ?? 'Document' }}</span><x-icon name="download"/></a>
@else
    <a href="{{ $url }}" target="_blank" rel="noopener" class="media-photo"><img src="{{ $url }}" alt="{{ $message->caption() ?? $type->label() }}" loading="lazy"></a>
@endif
