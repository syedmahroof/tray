<?php

use App\Models\User;

test('a known domain resolves to its own logo and name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('https://crm.kaptechengineering.com/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('brand.name', 'Kaptech CRM')
            ->where('brand.logo', '/images/logos/crm.png'));

    $this->actingAs($user)
        ->get('https://longlast.kaptechengineering.com/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('brand.name', 'Longlast')
            ->where('brand.logo', '/images/logos/longlast.png'));
});

test('an unknown domain falls back to the default branding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('https://unknown.example.com/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('brand.name', 'Build Tech')
            ->where('brand.logo', '/images/logos/buildtech.png'));
});
