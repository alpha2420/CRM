@extends('layouts.settings')

@section('settings')
    @php($icons = ['web_form' => 'globe', 'whatsapp' => 'whatsapp', 'facebook' => 'megaphone', 'google' => 'search-ad'])
    @php($descriptions = [
        'web_form' => 'A ready-made form to share as a link or embed in any website.',
        'whatsapp' => 'Chat with leads from the CRM, send templates and auto-greet new leads. Official WhatsApp Cloud API.',
        'facebook' => 'Leads from Facebook and Instagram lead ads arrive instantly.',
        'google' => 'Leads from Google Ads lead forms arrive instantly.',
    ])
    <div class="settings-head">
        <div><h2>Integrations</h2><p>Connect the places your leads come from. New leads land in the CRM instantly and are assigned automatically.</p></div>
    </div>

    <div class="integration-grid">
        @foreach (\App\Enums\IntegrationType::cases() as $type)
            @php($integration = $integrations[$type->value] ?? null)
            @php($locked = $type->feature() && ! $organization->canUse($type->feature()))
            <a href="{{ route('settings.integrations.edit', $type) }}" class="card integration">
                <div class="integration-top">
                    <span class="brand-icon {{ $type->value }}"><x-icon :name="$icons[$type->value]"/></span>
                    @if ($locked)
                        <span class="pill warn">Upgrade</span>
                    @elseif ($integration)
                        <span class="pill ok">Connected</span>
                    @else
                        <span class="pill">Not set up</span>
                    @endif
                </div>
                <strong>{{ $type->label() }}</strong>
                <p>{{ $descriptions[$type->value] }}</p>
            </a>
        @endforeach
    </div>

    <section class="card">
        <div class="integration-top" style="margin-bottom:10px">
            <div class="row"><span class="brand-icon api"><x-icon name="code"/></span><div><strong>Developer API</strong><div class="muted small">Send leads from your own website code or other tools.</div></div></div>
            @if ($organization->hasApiKey())<span class="pill ok">Key active</span>@endif
        </div>
        @if (session('api_key'))
            <div class="alert success"><div>Your API key — copy it now, it won't be shown again:<code class="key mt">{{ session('api_key') }}</code></div></div>
        @endif
        <div class="row" style="flex-wrap:wrap">
            <form method="post" action="{{ route('settings.organization.api-key') }}" @if ($organization->hasApiKey()) data-confirm="The current key will stop working immediately. Continue?" @endif>
                @csrf
                <button type="submit" class="btn">{{ $organization->hasApiKey() ? 'Regenerate key' : 'Create API key' }}</button>
            </form>
            <span class="muted small">Keep the key secret: call the API from your server, not from browser code.</span>
        </div>
        <details class="mt">
            <summary class="small">Example request</summary>
<pre class="mt">curl -X POST {{ url('/api/v1/leads') }} \
  -H "X-Api-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name": "Jane Doe", "phone": "+919876543210",
       "email": "jane@example.com", "source": "Website",
       "utm_campaign": "diwali-offer"}'</pre>
            <p class="muted small">Optional: <code>company</code>, <code>city</code>, <code>notes</code>, and for campaign reports <code>utm_campaign</code>, <code>gclid</code> or <code>fbclid</code> from the page's link.</p>
            <p class="muted small"><code>201</code> new lead · <code>200</code> already known (enquiry added to its history) · <code>422</code> invalid · <code>401</code> bad key</p>
        </details>
    </section>
@endsection
