<?php

namespace App\Support;

/**
 * The brand a request belongs to: its name and logo for the app shell, and the
 * company identity printed on its paperwork.
 */
class Branding
{
    /**
     * Resolve the branding for the given request host.
     *
     * @return array{name: string, subtitle: string|null, logo: string|null, company: array<string, mixed>}
     */
    public static function forHost(string $host): array
    {
        /** @var array<string, array<string, mixed>> $domains */
        $domains = config('branding.domains', []);

        /** @var array<string, mixed> $default */
        $default = config('branding.default', []);

        /** @var array<string, mixed> $brand */
        $brand = $domains[$host] ?? $default;

        return [
            'name' => $brand['name'] ?? config('app.name'),
            'subtitle' => $brand['subtitle'] ?? null,
            'logo' => $brand['logo'] ?? null,
            'company' => [
                ...(array) ($default['company'] ?? []),
                ...(array) ($brand['company'] ?? []),
            ],
        ];
    }

    /**
     * The company identity to print on the given host's paperwork.
     *
     * @return array<string, mixed>
     */
    public static function companyForHost(string $host): array
    {
        return self::forHost($host)['company'];
    }
}
