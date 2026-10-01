<?php

namespace App\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lists and ends a user's sessions (database session driver).
 */
final class SessionManager
{
    public function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return list<array{device: string, ip: ?string, last_active: Carbon, current: bool}>
     */
    public function for(Request $request): array
    {
        if (! $this->supported()) {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'device' => $this->describe((string) $session->user_agent),
                'ip' => $session->ip_address,
                'last_active' => Carbon::createFromTimestamp($session->last_activity),
                'current' => $session->id === $request->session()->getId(),
            ])
            ->all();
    }

    /**
     * End every other session and invalidate "remember me" cookies.
     */
    public function endOthers(Request $request): int
    {
        $user = $request->user();
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (! $this->supported()) {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    private function describe(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$os}";
    }
}
