@if ($organization->isSuspended())
    <span class="pill bad">Suspended</span>
@elseif ($organization->onTrial())
    <span class="pill">Trial · {{ $organization->trialDaysLeft() }}d left</span>
@elseif ($organization->hasPaidAccess())
    <span class="pill ok">Paying</span>
@else
    <span class="pill bad">Expired</span>
@endif
