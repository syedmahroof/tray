<?php

namespace App\Http\Middleware;

use App\Support\ReminderNotifications;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'brand' => $this->brandForHost($request->getHost()),
            'auth' => [
                'user' => $user,
                'roles' => fn () => $user?->getRoleNames() ?? [],
                'permissions' => fn () => $user?->getAllPermissions()->pluck('name') ?? [],
            ],
            'notifications' => fn () => $user ? ReminderNotifications::forUser($user) : ['items' => [], 'total' => 0],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Resolve the branding (name, subtitle, logo) for the given request host.
     *
     * @return array{name: string, subtitle: string|null, logo: string|null}
     */
    private function brandForHost(string $host): array
    {
        /** @var array<string, array{name?: string, subtitle?: string|null, logo?: string|null}> $domains */
        $domains = config('branding.domains', []);

        /** @var array{name?: string, subtitle?: string|null, logo?: string|null} $brand */
        $brand = $domains[$host] ?? config('branding.default', []);

        return [
            'name' => $brand['name'] ?? config('app.name'),
            'subtitle' => $brand['subtitle'] ?? null,
            'logo' => $brand['logo'] ?? null,
        ];
    }
}
