<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Passport::tokensCan(['mcp:use' => 'Use Tray MCP tools with your account permissions']);
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::authorizationView(fn (array $parameters): Response => Inertia::render('auth/Authorize', [
            'client' => ['id' => $parameters['client']->id, 'name' => $parameters['client']->name],
            'email' => $parameters['user']->email,
            'authToken' => $parameters['authToken'],
            'csrfToken' => csrf_token(),
            'state' => $parameters['request']->input('state', ''),
        ])->toResponse(request()));

        Gate::before(fn (User $user, string $ability) => $user->hasRole('Super Admin') ? true : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
