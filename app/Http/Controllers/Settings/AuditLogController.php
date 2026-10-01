<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /** Filter label => action prefixes it covers. */
    public const AREAS = [
        'lead' => 'Leads',
        'user' => 'Team',
        'auth' => 'Sign-ins',
        'security' => 'Security',
        'settings' => 'Settings',
        'workspace' => 'Workspace',
        'data' => 'Data',
    ];

    private const AREA_ACTIONS = [
        'settings' => ['stage', 'source', 'field', 'automation', 'integration'],
        'data' => ['data'],
    ];

    public function index(Request $request): View
    {
        $area = array_key_exists((string) $request->query('area'), self::AREAS) ? (string) $request->query('area') : null;
        $prefixes = $area ? (self::AREA_ACTIONS[$area] ?? [$area]) : [];

        $entries = AuditLog::query()
            ->with('user')
            ->when($prefixes, fn (Builder $q) => $q->where(fn (Builder $q) => collect($prefixes)->each(fn ($p) => $q->orWhere('action', 'like', "{$p}.%"))))
            ->when($request->integer('user'), fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($request->query('q'), fn (Builder $q, string $term) => $q->where('description', 'like', "%{$term}%"))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('settings.activity', [
            'entries' => $entries,
            'area' => $area,
            'areas' => self::AREAS,
            'users' => $request->user()->organization->users()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
