@extends('layouts.app')
@section('title', 'Your account')
@section('subtitle', 'Two-factor login and the devices you are signed in on.')

@section('content')
    @include('profile._tabs')

    <section class="card" style="max-width: 720px">
        <div class="card-head">
            <div><h2>Two-factor login</h2><p class="muted small">A code from your phone in addition to your password. Protects your leads even if your password leaks.</p></div>
            @if ($user->hasTwoFactor())<span class="pill ok">On</span>@else<span class="pill">Off</span>@endif
        </div>

        @if (session('recovery_codes'))
            <div class="alert warning">
                <div>
                    <strong>Save these recovery codes</strong> somewhere safe. Each works once if you lose your phone. They won't be shown again.
                    <code class="key mt" style="column-count:2">@foreach (session('recovery_codes') as $code){{ $code }}<br>@endforeach</code>
                </div>
            </div>
        @endif

        @if ($user->hasTwoFactor())
            <p class="muted">Turned on {{ $user->two_factor_confirmed_at->local()->format('d M Y') }}. {{ count($user->two_factor_recovery_codes ?? []) }} recovery codes left.</p>
            <div class="grid-2" style="margin-bottom:0">
                <form method="post" action="{{ route('security.two-factor.recovery') }}" class="stack">
                    @csrf
                    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
                    <button type="submit" class="btn">Show new recovery codes</button>
                </form>
                @unless ($required)
                    <form method="post" action="{{ route('security.two-factor.disable') }}" class="stack" data-confirm="Turn off two-factor login?">
                        @csrf @method('delete')
                        <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
                        <button type="submit" class="btn danger">Turn off</button>
                    </form>
                @endunless
            </div>
        @elseif ($settingUp)
            <ol class="steps">
                <li>Install an authenticator app: Google Authenticator, Microsoft Authenticator or 1Password.</li>
                <li>Scan this QR code with the app:
                    <div class="mt" style="background:#fff; display:inline-block; padding:8px; border:1px solid var(--border); border-radius:8px">{!! $qrCode !!}</div>
                    <div class="hint mt">Can't scan? Enter this key: <code>{{ trim(chunk_split($user->two_factor_secret, 4, ' ')) }}</code></div>
                </li>
                <li>Enter the 6-digit code it shows:
                    <form method="post" action="{{ route('security.two-factor.confirm') }}" class="inline-form mt">
                        @csrf
                        <input name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" placeholder="123456" style="width:140px">
                        <button type="submit" class="btn primary">Turn on</button>
                    </form>
                </li>
            </ol>
        @else
            <form method="post" action="{{ route('security.two-factor.enable') }}">
                @csrf
                <button type="submit" class="btn primary"><x-icon name="shield"/>Set up two-factor login</button>
            </form>
        @endif
    </section>

    <section class="card flush" style="max-width: 720px">
        <div class="card-head"><div><h2>Where you're signed in</h2><p class="muted small">Don't recognise a device? Sign it out and change your password.</p></div></div>
        @if ($sessions)
            <table>
                <tbody>
                @foreach ($sessions as $session)
                    <tr>
                        <td><span class="cell-main"><span class="kpi-icon"><x-icon :name="$session['mobile'] ? 'smartphone' : 'monitor'"/></span><span><strong>{{ $session['device'] }}</strong><span class="sub">{{ $session['ip'] ?? 'Unknown IP' }}</span></span></span></td>
                        <td class="num">@if ($session['current'])<span class="pill ok">This device</span>@else<span class="muted small">Active {{ $session['last_active']->diffForHumans() }}</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        <div class="card-foot">
            <form method="post" action="{{ route('security.sessions.logout-others') }}" class="inline-form">
                @csrf
                <input type="password" name="password" required placeholder="Your password" autocomplete="current-password" aria-label="Password">
                <button type="submit" class="btn">Sign out other devices</button>
            </form>
        </div>
    </section>
@endsection
