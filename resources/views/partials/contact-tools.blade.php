{{-- One-tap Call and WhatsApp for a lead. Opens the in-app chat when WhatsApp is connected. --}}
<span class="tools">
    <a href="tel:{{ $lead->phone }}" data-call="{{ route('leads.calls.store', $lead) }}" data-call-name="{{ $lead->firstName() }}" data-call-log="{{ route('leads.show', $lead) }}" class="tool" aria-label="Call {{ $lead->name }}" title="Call"><x-icon name="phone"/></a>
    @if ($nav['inbox'])
        <a href="{{ route('inbox', ['lead' => $lead->id]) }}" class="tool wa" aria-label="WhatsApp {{ $lead->name }}" title="WhatsApp"><x-icon name="whatsapp"/></a>
    @else
        <a href="https://wa.me/{{ \App\Support\WhatsAppNumber::fromPhone($lead->phone, '91') }}" target="_blank" rel="noopener" class="tool wa" aria-label="WhatsApp {{ $lead->name }}" title="WhatsApp"><x-icon name="whatsapp"/></a>
    @endif
</span>
