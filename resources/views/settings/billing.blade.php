@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div><h2>Billing</h2><p>Simple monthly plans. Prices in rupees, excluding GST. Change or cancel any time.</p></div>
    </div>

    <section class="card current-plan">
        <span class="kpi-icon"><x-icon name="card"/></span>
        <div class="grow">
            <div class="muted small">Current plan</div>
            <span class="plan-name">{{ $organization->plan()->name }}</span>
            <div class="muted">
                @if ($organization->isSuspended())
                    Suspended by support.
                @elseif ($organization->onTrial())
                    Trial ends {{ $organization->trial_ends_at->local()->format('d M Y') }} — {{ $organization->trialDaysLeft() }} {{ Str::plural('day', $organization->trialDaysLeft()) }} left. Pick a plan any time; you keep everything.
                @elseif ($organization->subscription_status === 'cancelled')
                    Cancelled. Access until {{ $organization->current_period_end?->local()->format('d M Y') ?? 'the end of the period' }}.
                @elseif ($organization->hasPaidAccess())
                    Active{{ $organization->current_period_end ? ' · renews '.$organization->current_period_end->local()->format('d M Y') : '' }}.
                @else
                    Your plan has ended. Choose a plan below to continue.
                @endif
            </div>
        </div>
        <div style="min-width:180px">
            <div class="row-between small"><span class="muted">Seats</span><strong>{{ $organization->seatsUsed() }} / {{ $organization->plan()->maxUsers }}</strong></div>
            <div class="meter" style="margin-top:6px"><span style="width: {{ min(100, $organization->seatsUsed() / max(1, $organization->plan()->maxUsers) * 100) }}%"></span></div>
        </div>
    </section>

    @unless ($paymentsEnabled)
        <div class="alert info">
            Online payment isn't switched on yet.
            @if (config('crm.support_email'))
                Email <a href="mailto:{{ config('crm.support_email') }}?subject={{ rawurlencode('Upgrade '.$organization->name) }}">{{ config('crm.support_email') }}</a> with the plan you want and we'll switch it on for you.
            @else
                Contact support to upgrade.
            @endif
        </div>
    @endunless

    <div class="plans">
        @foreach ($plans as $plan)
            @php($current = $organization->plan === $plan->key && $organization->hasPaidAccess())
            @php($recommended = $plan->key === 'growth')
            <section @class(['card', 'plan', 'recommended' => $recommended && ! $current])>
                @if ($recommended && ! $current)<span class="ribbon">Recommended</span>@endif
                <div>
                    <h3>{{ $plan->name }}</h3>
                    <div class="muted small">Up to {{ $plan->maxUsers }} users</div>
                </div>
                <div class="price">₹{{ number_format($plan->price) }}<span> / month</span></div>
                <ul>
                    @foreach (config('plans.included') as $item)
                        <li><x-icon name="check"/>{{ $item }}</li>
                    @endforeach
                    @foreach ($plan->features as $feature)
                        <li><x-icon name="check"/><strong>{{ $feature->label() }}</strong></li>
                    @endforeach
                </ul>
                @if ($current)
                    <span class="btn block" aria-disabled="true">Current plan</span>
                @else
                    <form method="post" action="{{ route('settings.billing.subscribe', $plan->key) }}">
                        @csrf
                        <button type="submit" @class(['btn', 'block', 'primary' => $recommended]) @disabled(! $paymentsEnabled)>Choose {{ $plan->name }}</button>
                    </form>
                @endif
            </section>
        @endforeach
    </div>

    @if ($organization->razorpay_subscription_id && $organization->hasPaidAccess() && $organization->subscription_status !== 'cancelled')
        <form method="post" action="{{ route('settings.billing.cancel') }}" data-confirm="Cancel your subscription at the end of this period?" class="mt">
            @csrf
            <button type="submit" class="link danger small">Cancel subscription</button>
        </form>
    @endif
@endsection
