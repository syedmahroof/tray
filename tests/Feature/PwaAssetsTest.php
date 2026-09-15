<?php

test('the app shell exposes an installable web app manifest', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('rel="manifest" href="/manifest.webmanifest"', false)
        ->assertSee('name="theme-color" content="#1d4ed8"', false)
        ->assertSee('rel="apple-touch-icon" href="/pwa-192x192.png"', false);
});

test('the manifest declares the required icons and standalone display mode', function () {
    $manifest = json_decode(
        file_get_contents(public_path('manifest.webmanifest')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/dashboard')
        ->and($manifest['icons'])->toContain([
            'src' => '/pwa-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ])
        ->toContain([
            'src' => '/pwa-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ]);

    expect(public_path('pwa-192x192.png'))->toBeFile()
        ->and(public_path('pwa-512x512.png'))->toBeFile()
        ->and(public_path('offline.html'))->toBeFile()
        ->and(public_path('service-worker.js'))->toBeFile();
});
